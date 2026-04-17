<?php

declare(strict_types=1);

namespace ApprLabs\Pdf\Reader;

use ApprLabs\Pdf\Core\Document\Catalog;
use ApprLabs\Pdf\Core\Document\Page;
use ApprLabs\Pdf\Core\File\PdfHydrator;
use ApprLabs\Pdf\Core\PdfArray;
use ApprLabs\Pdf\Core\PdfDictionary;
use ApprLabs\Pdf\Core\PdfName;
use ApprLabs\Pdf\Core\PdfNumber;
use ApprLabs\Pdf\Core\PdfObject;
use ApprLabs\Pdf\Core\PdfReference;
use ApprLabs\Pdf\Core\PdfString;
use ApprLabs\Pdf\Core\Serializable;
use ApprLabs\Pdf\Reader\Exception\InvalidPdfException;
use ApprLabs\Pdf\Reader\Parser\ObjectParser;
use ApprLabs\Pdf\Reader\Parser\ObjectScanner;
use ApprLabs\Pdf\Reader\Parser\StreamParser;
use ApprLabs\Pdf\Reader\Parser\XrefParser;
use ApprLabs\Pdf\Reader\Parser\XrefStreamParser;
use ApprLabs\Pdf\Reader\Tokenizer\FileSource;
use ApprLabs\Pdf\Reader\Tokenizer\Source;
use ApprLabs\Pdf\Reader\Tokenizer\StringSource;
use ApprLabs\Pdf\Reader\Tokenizer\Tokenizer;

/**
 * PDF reader — parses existing PDF files into the phpdftk object model.
 *
 * Phase 1 supports unencrypted PDFs with classic cross-reference tables.
 * Returns raw `PdfDictionary` objects; typed hydration (into `Catalog`,
 * `Page`, etc.) is a future phase.
 *
 * Three factory methods mirror the writer's output modes:
 *
 * ```php
 * $pdf = PdfReader::fromFile('/path/to/document.pdf');
 * $pdf = PdfReader::fromString($bytes);
 * $pdf = PdfReader::fromStream(fopen('php://stdin', 'rb'));
 * ```
 */
final class PdfReader
{
    /** @var list<string> */
    private array $parseWarnings = [];

    private function __construct(
        private readonly string $version,
        private readonly PdfDictionary $trailer,
        private readonly ObjectResolver $resolver,
    ) {
    }

    /**
     * Return warnings accumulated during parsing.
     *
     * @return list<string>
     */
    public function getParseWarnings(): array
    {
        return $this->parseWarnings;
    }

    // -----------------------------------------------------------------------
    // Factory methods
    // -----------------------------------------------------------------------

    public static function fromFile(string $path, string $password = '', bool $strict = true): self
    {
        return self::build(new FileSource($path), $password, $strict);
    }

    public static function fromString(string $content, string $password = '', bool $strict = true): self
    {
        return self::build(new StringSource($content), $password, $strict);
    }

    /** @param resource $stream */
    public static function fromStream($stream, string $password = '', bool $strict = true): self
    {
        if (!is_resource($stream)) {
            throw new \InvalidArgumentException('Expected a stream resource');
        }
        $content = stream_get_contents($stream);
        if ($content === false) {
            throw new \RuntimeException('Failed to read stream');
        }
        return self::fromString($content, $password, $strict);
    }

    // -----------------------------------------------------------------------
    // Public API
    // -----------------------------------------------------------------------

    /** PDF version string, e.g. "1.7". */
    public function getVersion(): string
    {
        return $this->version;
    }

    /** The raw trailer dictionary. */
    public function getTrailer(): PdfDictionary
    {
        return $this->trailer;
    }

    /** Resolve /Root from the trailer — returns the Catalog dictionary. */
    public function getCatalog(): PdfDictionary
    {
        $root = $this->trailer->get('Root');
        if ($root instanceof PdfReference) {
            $obj = $this->resolver->resolveReference($root);
            if ($obj instanceof PdfDictionary) {
                return $obj;
            }
        }
        throw new InvalidPdfException('Unable to resolve /Root catalog');
    }

    /** Resolve /Info from the trailer. */
    public function getInfo(): ?PdfDictionary
    {
        $info = $this->trailer->get('Info');
        if ($info instanceof PdfReference) {
            $obj = $this->resolver->resolveReference($info);
            if ($obj instanceof PdfDictionary) {
                return $obj;
            }
        }
        return null;
    }

    /** Get the total page count from /Pages -> /Count. */
    public function getPageCount(): int
    {
        $catalog = $this->getCatalog();
        $pagesRef = $catalog->get('Pages');
        if ($pagesRef instanceof PdfReference) {
            $pages = $this->resolver->resolveReference($pagesRef);
            if ($pages instanceof PdfDictionary) {
                $count = $pages->get('Count');
                if ($count instanceof PdfNumber) {
                    return (int) $count->toPdf();
                }
            }
        }
        return 0;
    }

    /**
     * Get all Page dictionaries by traversing the page tree.
     *
     * @return list<PdfDictionary>
     */
    public function getPages(): array
    {
        $catalog = $this->getCatalog();
        $pagesRef = $catalog->get('Pages');
        if (!$pagesRef instanceof PdfReference) {
            return [];
        }
        $pagesDict = $this->resolver->resolveReference($pagesRef);
        if (!$pagesDict instanceof PdfDictionary) {
            return [];
        }
        $result = [];
        $this->collectPages($pagesDict, $result);
        return $result;
    }

    /** Get a specific page by zero-based index. */
    public function getPage(int $index): PdfDictionary
    {
        $pages = $this->getPages();
        if (!isset($pages[$index])) {
            throw new \OutOfRangeException("Page index $index out of range (0.." . (count($pages) - 1) . ')');
        }
        return $pages[$index];
    }

    /** Resolve any object by number. */
    public function getObject(int $objNum): Serializable
    {
        return $this->resolver->resolve($objNum);
    }

    /** Resolve an indirect reference to its target. */
    public function resolveReference(PdfReference $ref): Serializable
    {
        return $this->resolver->resolveReference($ref);
    }

    /** The underlying object resolver. */
    public function getResolver(): ObjectResolver
    {
        return $this->resolver;
    }

    // -----------------------------------------------------------------------
    // Text extraction
    // -----------------------------------------------------------------------

    /**
     * Extract text from a page by index (zero-based).
     *
     * Interprets content stream operators, resolves font encodings
     * (ToUnicode CMap, /Encoding + /Differences, WinAnsi fallback),
     * and infers spacing from text positioning operators.
     */
    public function extractText(int $pageIndex): string
    {
        $page = $this->getPage($pageIndex);
        $extractor = new TextExtractor($this->resolver);
        return $extractor->extractFromPage($page);
    }

    /**
     * Extract text from all pages, concatenated with page separators.
     *
     * @param string $separator Separator between pages (default: newline)
     */
    public function extractAllText(string $separator = "\n"): string
    {
        $pages = $this->getPages();
        $texts = [];
        $extractor = new TextExtractor($this->resolver);
        foreach ($pages as $page) {
            $texts[] = $extractor->extractFromPage($page);
        }
        return implode($separator, $texts);
    }

    // -----------------------------------------------------------------------
    // Hydration — typed object access
    // -----------------------------------------------------------------------

    /**
     * Return the document catalog as a typed Catalog object.
     */
    public function getTypedCatalog(): Catalog
    {
        PdfHydrator::registerDefaults();
        $dict = $this->getCatalog();
        $root = $this->trailer->get('Root');
        $objNum = $root instanceof PdfReference ? $root->objectNumber : 0;

        $result = PdfHydrator::hydrate($dict, $objNum);
        if ($result instanceof Catalog) {
            return $result;
        }

        throw new Exception\InvalidPdfException('Failed to hydrate /Root as Catalog');
    }

    /**
     * Return a specific page as a typed Page object.
     */
    public function getTypedPage(int $index): Page
    {
        PdfHydrator::registerDefaults();
        $dict = $this->getPage($index);

        $result = PdfHydrator::hydrate($dict);
        if ($result instanceof Page) {
            return $result;
        }

        throw new Exception\InvalidPdfException("Failed to hydrate page $index as Page");
    }

    /**
     * Return all pages as typed Page objects.
     *
     * @return list<Page>
     */
    public function getTypedPages(): array
    {
        PdfHydrator::registerDefaults();
        $pages = [];
        foreach ($this->getPages() as $dict) {
            $result = PdfHydrator::hydrate($dict);
            if ($result instanceof Page) {
                $pages[] = $result;
            }
        }
        return $pages;
    }

    /**
     * Hydrate any resolved object by object number.
     */
    public function getTypedObject(int $objNum): PdfObject|PdfDictionary
    {
        PdfHydrator::registerDefaults();
        $obj = $this->resolver->resolve($objNum);
        if ($obj instanceof PdfDictionary) {
            return PdfHydrator::hydrate($obj, $objNum);
        }
        if ($obj instanceof PdfObject) {
            return $obj;
        }
        return new PdfDictionary();
    }

    // -----------------------------------------------------------------------
    // Internal
    // -----------------------------------------------------------------------

    private static function build(Source $source, string $password = '', bool $strict = true): self
    {
        $warnings = [];

        // 1. Validate header — check first 20 bytes, then scan up to 1024 in lenient mode
        $header = $source->read(20);
        if (preg_match('/^%PDF-(\d+\.\d+)/', $header, $m)) {
            $version = $m[1];
        } else {
            // Header not at byte 0 — scan first 1024 bytes
            $source->seek(0);
            $headerBlock = $source->read(min(1024, $source->size()));
            if (preg_match('/%PDF-(\d+\.\d+)/', $headerBlock, $m)) {
                if ($strict) {
                    throw new InvalidPdfException('Not a PDF file (missing %PDF- header)');
                }
                $version = $m[1];
                $warnings[] = 'PDF header not at byte 0; found at offset ' . strpos($headerBlock, '%PDF-');
            } else {
                throw new InvalidPdfException('Not a PDF file (missing %PDF- header)');
            }
        }

        // 2. Build parser chain
        $tokenizer = new Tokenizer($source);
        $objectParser = new ObjectParser($tokenizer, $source);
        $streamParser = new StreamParser();
        $xrefParser = new XrefParser($tokenizer, $source, $objectParser);
        $xrefStreamParser = new XrefStreamParser($tokenizer, $source, $objectParser, $streamParser);

        // 3. Find startxref + parse xref + trailer — with reconstruction fallback
        $entries = null;
        $trailer = null;
        $reconstructed = false;

        try {
            $startxrefOffset = self::findStartxref($source);

            // 4. Parse xref + trailer — auto-detect classic vs stream
            [$entries, $trailer] = self::parseXrefAt(
                $source, $startxrefOffset, $xrefParser, $xrefStreamParser, $strict, $warnings
            );
        } catch (InvalidPdfException $e) {
            if ($strict) {
                throw $e;
            }
            // Fall through to reconstruction
        }

        if ($entries === null || $trailer === null) {
            if ($strict) {
                throw new InvalidPdfException('Cannot parse xref table or trailer');
            }
            [$entries, $trailer] = self::reconstructXref($source);
            $warnings[] = 'xref table reconstructed from object scan';
            $reconstructed = true;
        }

        // 5. Set up decryptor if /Encrypt is present
        $decryptor = null;
        $encrypt = $trailer->get('Encrypt');
        if ($encrypt instanceof PdfDictionary) {
            $fileId = self::extractFileId($trailer);
            $decryptor = PdfDecryptor::fromEncryptDict($encrypt, $password, $fileId);
        } elseif ($encrypt instanceof PdfReference) {
            // /Encrypt might be an indirect reference — resolve it
            $tempResolver = new ObjectResolver($entries, $tokenizer, $source, $objectParser, $streamParser);
            $encryptObj = $tempResolver->resolveReference($encrypt);
            if ($encryptObj instanceof PdfDictionary) {
                $fileId = self::extractFileId($trailer);
                $decryptor = PdfDecryptor::fromEncryptDict($encryptObj, $password, $fileId);
            }
        }

        // 6. Build resolver (with optional decryptor)
        $resolver = new ObjectResolver(
            $entries, $tokenizer, $source, $objectParser, $streamParser, $decryptor
        );

        // Wire resolver into stream parser for resolving indirect /DecodeParms
        $streamParser->setResolver($resolver);

        // 7. Follow /Prev chain for incremental updates (skip if reconstructed)
        if (!$reconstructed) {
            $prev = $trailer->get('Prev');
            while ($prev instanceof PdfNumber) {
                $prevOffset = (int) $prev->toPdf();
                [$olderEntries, $olderTrailer] = self::parseXrefAt(
                    $source, $prevOffset, $xrefParser, $xrefStreamParser
                );
                $resolver->mergeOlderEntries($olderEntries);
                $prev = $olderTrailer->get('Prev');
            }
        }

        $reader = new self($version, $trailer, $resolver);
        $reader->parseWarnings = $warnings;
        return $reader;
    }

    /**
     * Extract the first element of the /ID array from the trailer.
     */
    private static function extractFileId(PdfDictionary $trailer): string
    {
        $id = $trailer->get('ID');
        if ($id instanceof PdfArray && isset($id->items[0])) {
            $first = $id->items[0];
            if ($first instanceof PdfString) {
                return $first->value;
            }
        }
        return '';
    }

    /**
     * Auto-detect classic xref table vs cross-reference stream at the
     * given offset and parse accordingly.
     *
     * @return array{0: array<int, XrefEntry>, 1: PdfDictionary}
     */
    /**
     * @param list<string> $warnings
     * @return array{0: array<int, XrefEntry>, 1: PdfDictionary}
     */
    private static function parseXrefAt(
        Source $source,
        int $offset,
        XrefParser $classicParser,
        XrefStreamParser $streamParser,
        bool $strict = true,
        array &$warnings = [],
    ): array {
        // Peek at the bytes at the offset to decide which parser to use.
        $source->seek($offset);
        $peek = $source->peek(4);
        if (str_starts_with($peek, 'xref')) {
            return $classicParser->parseClassicXref($offset, $strict, $warnings);
        }
        // Otherwise assume it's a cross-reference stream (starts with "N M obj")
        return $streamParser->parseXrefStream($offset);
    }

    /**
     * Scan backward from EOF to find the `startxref` byte offset.
     */
    private static function findStartxref(Source $source): int
    {
        $size = $source->size();

        // Try progressively larger tail sizes: 1024, 8192, 65536
        foreach ([1024, 8192, 65536] as $tryLength) {
            $tailLength = min($tryLength, $size);
            $source->seek($size - $tailLength);
            $tail = $source->read($tailLength);

            $pos = strrpos($tail, 'startxref');
            if ($pos !== false) {
                $after = substr($tail, $pos + strlen('startxref'));
                if (preg_match('/\s+(\d+)/', $after, $m)) {
                    return (int) $m[1];
                }
            }
        }

        throw new InvalidPdfException('Cannot find startxref');
    }

    /**
     * Reconstruct xref entries and trailer by scanning for object definitions.
     *
     * Used as a fallback when the normal xref/trailer parsing fails in lenient mode.
     *
     * @return array{0: array<int, XrefEntry>, 1: PdfDictionary}
     */
    private static function reconstructXref(Source $source): array
    {
        $source->seek(0);
        $allBytes = $source->read($source->size());

        $objectMap = ObjectScanner::scan($allBytes);

        if ($objectMap === []) {
            throw new InvalidPdfException('Cannot reconstruct xref: no objects found');
        }

        // Build xref entries
        $entries = [];
        foreach ($objectMap as $objNum => $offset) {
            $entries[$objNum] = new XrefEntry(XrefEntry::TYPE_IN_USE, $offset, 0);
        }

        // Find the catalog by peeking at each object
        $catalogObjNum = null;
        foreach ($objectMap as $objNum => $offset) {
            $peekStart = $offset;
            $peekLength = min(512, strlen($allBytes) - $peekStart);
            $peek = substr($allBytes, $peekStart, $peekLength);
            if (preg_match('/\/Type\s*\/Catalog\b/', $peek)) {
                $catalogObjNum = $objNum;
                break;
            }
        }

        if ($catalogObjNum === null) {
            throw new InvalidPdfException('Cannot reconstruct trailer: no /Type /Catalog found');
        }

        // Build synthetic trailer
        $maxObjNum = max(array_keys($objectMap));
        $trailer = new PdfDictionary();
        $trailer->set('Root', new PdfReference($catalogObjNum, 0));
        $trailer->set('Size', new PdfNumber($maxObjNum + 1));

        return [$entries, $trailer];
    }

    /**
     * Recursively collect Page dicts from a Pages tree node.
     *
     * @param list<PdfDictionary> $result
     */
    private function collectPages(PdfDictionary $node, array &$result): void
    {
        $kids = $node->get('Kids');
        if (!$kids instanceof PdfArray) {
            return;
        }
        foreach ($kids->items as $kidRef) {
            if (!$kidRef instanceof PdfReference) {
                continue;
            }
            $kid = $this->resolver->resolveReference($kidRef);
            if (!$kid instanceof PdfDictionary) {
                continue;
            }
            $type = $kid->get('Type');
            if ($type instanceof PdfName && $type->value === 'Pages') {
                $this->collectPages($kid, $result);
            } else {
                $result[] = $kid;
            }
        }
    }
}
