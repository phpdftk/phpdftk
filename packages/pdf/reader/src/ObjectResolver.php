<?php

declare(strict_types=1);

namespace Phpdftk\Pdf\Reader;

use Phpdftk\Pdf\Core\PdfNull;
use Phpdftk\Pdf\Core\PdfReference;
use Phpdftk\Pdf\Core\PdfStream;
use Phpdftk\Pdf\Core\Serializable;
use Phpdftk\Pdf\Reader\Exception\InvalidPdfException;
use Phpdftk\Pdf\Reader\Parser\ObjectParser;
use Phpdftk\Pdf\Reader\Parser\ObjectScanner;
use Phpdftk\Pdf\Reader\Parser\ObjectStreamParser;
use Phpdftk\Pdf\Reader\Parser\StreamParser;
use Phpdftk\Pdf\Reader\Tokenizer\Source;
use Phpdftk\Pdf\Reader\Tokenizer\Tokenizer;

/**
 * Lazy-loading object cache. Resolves indirect references by seeking
 * to the xref-recorded byte offset, parsing the object, decompressing
 * any stream data, and caching the result.
 */
final class ObjectResolver
{
    /** @var array<int, Serializable> */
    private array $cache = [];

    private bool $strict = true;
    private bool $rescanned = false;

    /**
     * @param array<int, XrefEntry> $entries
     */
    public function __construct(
        private array $entries,
        private readonly Tokenizer $tokenizer,
        private readonly Source $source,
        private readonly ObjectParser $objectParser,
        private readonly StreamParser $streamParser,
        private readonly ?PdfDecryptor $decryptor = null,
    ) {}

    /**
     * Configure whether the resolver should attempt to recover from
     * corrupted xref entries (lenient mode = false).
     */
    public function setStrict(bool $strict): void
    {
        $this->strict = $strict;
    }

    /**
     * Merge additional xref entries (older entries do NOT overwrite
     * newer ones — newer entries from later xref sections take
     * precedence). Used for `/Prev` chain following.
     *
     * @param array<int, XrefEntry> $olderEntries
     */
    public function mergeOlderEntries(array $olderEntries): void
    {
        foreach ($olderEntries as $objNum => $entry) {
            // Only add if we don't already have a (newer) entry
            if (!isset($this->entries[$objNum])) {
                $this->entries[$objNum] = $entry;
            }
        }
    }

    public function resolve(int $objNum, int $genNum = 0): Serializable
    {
        if (isset($this->cache[$objNum])) {
            return $this->cache[$objNum];
        }

        $entry = $this->entries[$objNum] ?? null;
        if ($entry === null || $entry->type === XrefEntry::TYPE_FREE) {
            return new PdfNull();
        }

        if ($entry->type === XrefEntry::TYPE_IN_USE) {
            return $this->resolveInUse($objNum, $entry);
        }

        if ($entry->type === XrefEntry::TYPE_COMPRESSED) {
            return $this->resolveCompressed($objNum, $entry);
        }

        return new PdfNull();
    }

    public function resolveReference(PdfReference $ref): Serializable
    {
        return $this->resolve($ref->objectNumber, $ref->generationNumber);
    }

    public function has(int $objNum): bool
    {
        return isset($this->entries[$objNum])
            && $this->entries[$objNum]->type !== XrefEntry::TYPE_FREE;
    }

    public function getEntry(int $objNum): ?XrefEntry
    {
        return $this->entries[$objNum] ?? null;
    }

    /** @return list<int> */
    public function getObjectNumbers(): array
    {
        return array_keys($this->entries);
    }

    /** @return array<int, XrefEntry> */
    public function getEntries(): array
    {
        return $this->entries;
    }

    private function resolveInUse(int $objNum, XrefEntry $entry): Serializable
    {
        $this->tokenizer->seek($entry->offset);

        try {
            [$parsedObjNum, $parsedGenNum, $value] = $this->objectParser->parseIndirectObject();
        } catch (\Throwable $e) {
            if ($this->strict) {
                throw $e;
            }
            // Lenient mode — try to find the object by re-scanning the file
            return $this->recoverByRescan($objNum) ?? throw $e;
        }

        if ($parsedObjNum !== $objNum) {
            if ($this->strict) {
                throw new InvalidPdfException(
                    "Xref says object $objNum is at offset {$entry->offset}, "
                    . "but found object $parsedObjNum there",
                );
            }
            // Lenient mode — try to find the correct offset for $objNum
            $recovered = $this->recoverByRescan($objNum);
            if ($recovered !== null) {
                return $recovered;
            }
            // If we cannot recover, accept the parsed object as-is so we
            // can keep going. The caller will treat a non-dict result as
            // a soft failure.
            return new PdfNull();
        }

        // Decrypt object if a decryptor is configured
        if ($this->decryptor !== null) {
            $value = $this->decryptor->decryptObject($value, $parsedObjNum, $parsedGenNum);
        }

        // Decompress stream data if applicable
        if ($value instanceof PdfStream && $value->data !== '') {
            try {
                $value->data = $this->streamParser->decode($value->data, $value->dictionary);
            } catch (\Throwable) {
                // If decoding fails (e.g., image-only stream), keep raw data
            }
        }

        $this->cache[$objNum] = $value;
        return $value;
    }

    /**
     * Lenient-mode fallback: rescan the entire source for indirect-object
     * headers and rebuild xref entries. Returns the requested object's
     * value if it can be parsed at the rescanned offset, or null if not
     * found / unparseable.
     */
    private function recoverByRescan(int $objNum): ?Serializable
    {
        if (!$this->rescanned) {
            $this->rescanFile();
            $this->rescanned = true;
        }

        $entry = $this->entries[$objNum] ?? null;
        if ($entry === null || $entry->type !== XrefEntry::TYPE_IN_USE) {
            return null;
        }

        $this->tokenizer->seek($entry->offset);
        try {
            [$parsedObjNum, $parsedGenNum, $value] = $this->objectParser->parseIndirectObject();
        } catch (\Throwable) {
            return null;
        }
        if ($parsedObjNum !== $objNum) {
            return null;
        }

        if ($this->decryptor !== null) {
            $value = $this->decryptor->decryptObject($value, $parsedObjNum, $parsedGenNum);
        }
        if ($value instanceof PdfStream && $value->data !== '') {
            try {
                $value->data = $this->streamParser->decode($value->data, $value->dictionary);
            } catch (\Throwable) {
                // ignore
            }
        }

        $this->cache[$objNum] = $value;
        return $value;
    }

    /**
     * Rescan the entire source for indirect-object headers and
     * overwrite the in-use entries with the discovered offsets.
     */
    private function rescanFile(): void
    {
        $this->source->seek(0);
        $bytes = $this->source->read($this->source->size());
        $map = ObjectScanner::scan($bytes);
        foreach ($map as $num => $offset) {
            $existing = $this->entries[$num] ?? null;
            if ($existing === null || $existing->type !== XrefEntry::TYPE_COMPRESSED) {
                $this->entries[$num] = new XrefEntry(XrefEntry::TYPE_IN_USE, $offset, 0);
            }
        }
    }

    /**
     * Scan the file once and return the discovered object map. Used by
     * the reader to find catalogs / pages roots when the trailer /Root
     * cannot be resolved.
     *
     * @return array<int, int>
     */
    public function scanObjectMap(): array
    {
        $this->source->seek(0);
        $bytes = $this->source->read($this->source->size());
        return ObjectScanner::scan($bytes);
    }

    /**
     * Read a window of raw bytes from the source. Used by the reader's
     * catalog-recovery code to peek at object bodies.
     */
    public function readRaw(int $offset, int $length): string
    {
        $this->source->seek($offset);
        return $this->source->read($length);
    }

    /**
     * Resolve a compressed object from an ObjStm.
     * entry->offset = containing ObjStm object number
     * entry->generation = index within the ObjStm
     */
    private function resolveCompressed(int $objNum, XrefEntry $entry): Serializable
    {
        $objStmNum = $entry->offset;

        // Resolve the containing ObjStm itself (must be type 1)
        $objStm = $this->resolve($objStmNum);
        if (!$objStm instanceof PdfStream) {
            throw new InvalidPdfException(
                "ObjStm $objStmNum is not a stream",
            );
        }

        // Unpack all objects from the ObjStm and cache them
        $parser = new ObjectStreamParser($this->streamParser);
        $unpacked = $parser->unpack($objStm);
        foreach ($unpacked as $num => $value) {
            if (!isset($this->cache[$num])) {
                $this->cache[$num] = $value;
            }
        }

        return $this->cache[$objNum] ?? new PdfNull();
    }
}
