<?php

declare(strict_types=1);

namespace ApprLabs\Pdf\Reader;

use ApprLabs\Pdf\Core\PdfNull;
use ApprLabs\Pdf\Core\PdfReference;
use ApprLabs\Pdf\Core\PdfStream;
use ApprLabs\Pdf\Core\Serializable;
use ApprLabs\Pdf\Reader\Exception\InvalidPdfException;
use ApprLabs\Pdf\Reader\Parser\ObjectParser;
use ApprLabs\Pdf\Reader\Parser\ObjectStreamParser;
use ApprLabs\Pdf\Reader\Parser\StreamParser;
use ApprLabs\Pdf\Reader\Tokenizer\Source;
use ApprLabs\Pdf\Reader\Tokenizer\Tokenizer;

/**
 * Lazy-loading object cache. Resolves indirect references by seeking
 * to the xref-recorded byte offset, parsing the object, decompressing
 * any stream data, and caching the result.
 */
final class ObjectResolver
{
    /** @var array<int, Serializable> */
    private array $cache = [];

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
    ) {
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

        [$parsedObjNum, $parsedGenNum, $value] = $this->objectParser->parseIndirectObject();

        if ($parsedObjNum !== $objNum) {
            throw new InvalidPdfException(
                "Xref says object $objNum is at offset {$entry->offset}, "
                . "but found object $parsedObjNum there"
            );
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
                "ObjStm $objStmNum is not a stream"
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
