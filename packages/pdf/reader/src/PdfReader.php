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
use ApprLabs\Pdf\Core\PdfVersion;
use ApprLabs\Pdf\Core\Serializable;
use ApprLabs\Pdf\Reader\Exception\InvalidPdfException;
use ApprLabs\Pdf\Reader\Parser\HintTableParser;
use ApprLabs\Pdf\Reader\Parser\ObjectParser;
use ApprLabs\Pdf\Reader\Parser\ObjectScanner;
use ApprLabs\Pdf\Reader\Parser\PageOffsetHintTable;
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

    /**
     * Read a public-key (certificate-based) encrypted PDF from a file.
     */
    public static function fromFilePublicKey(
        string $path,
        string $certificate,
        string $privateKey,
        bool $strict = true,
    ): self {
        return self::build(new FileSource($path), '', $strict, $certificate, $privateKey);
    }

    /**
     * Read a public-key (certificate-based) encrypted PDF from a string.
     */
    public static function fromStringPublicKey(
        string $content,
        string $certificate,
        string $privateKey,
        bool $strict = true,
    ): self {
        return self::build(new StringSource($content), '', $strict, $certificate, $privateKey);
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

    /** Typed PDF version from the file header. */
    public function getPdfVersion(): PdfVersion
    {
        return PdfVersion::tryFrom($this->version) ?? PdfVersion::V1_7;
    }

    /**
     * Effective PDF version — max(header, catalog /Version).
     *
     * Per ISO 32000 §7.2.2, the catalog /Version entry (PDF 1.4+)
     * overrides the header version if it is higher.
     */
    public function getEffectiveVersion(): PdfVersion
    {
        $headerVersion = $this->getPdfVersion();
        $catalog = $this->getCatalog();

        if ($catalog instanceof PdfDictionary && $catalog->has('Version')) {
            $catVersion = $catalog->get('Version');
            if ($catVersion instanceof PdfName) {
                $catPdfVersion = PdfVersion::tryFrom($catVersion->value);
                if ($catPdfVersion !== null) {
                    return $headerVersion->max($catPdfVersion);
                }
            }
        }

        return $headerVersion;
    }

    /**
     * Scan the document for structural features inconsistent with the
     * declared version. Returns a list of warning strings.
     *
     * Checks top-level indicators that can be detected from raw
     * dictionaries without full object hydration.
     *
     * @return list<string>
     */
    public function validateVersion(): array
    {
        $warnings = [];
        $version = $this->getEffectiveVersion();

        // Xref stream → requires 1.5
        $trailerType = $this->trailer->get('Type');
        if ($trailerType instanceof PdfName && $trailerType->value === 'XRef') {
            if (!$version->isAtLeast(PdfVersion::V1_5)) {
                $warnings[] = "Cross-reference stream requires PDF 1.5, but document declares {$version->value}";
            }
        }

        // Encryption version
        $encrypt = $this->trailer->get('Encrypt');
        if ($encrypt instanceof PdfReference) {
            $encDict = $this->resolver->resolveReference($encrypt);
            if ($encDict instanceof PdfDictionary) {
                $v = $encDict->get('V');
                $vVal = $v instanceof PdfNumber ? (int) $v->toPdf() : 0;
                $required = match (true) {
                    $vVal >= 5 => PdfVersion::V2_0,
                    $vVal >= 4 => PdfVersion::V1_6,
                    $vVal >= 2 => PdfVersion::V1_4,
                    default => PdfVersion::V1_0,
                };
                if ($required->isGreaterThan($version)) {
                    $warnings[] = "Encryption V={$vVal} requires PDF {$required->value}, but document declares {$version->value}";
                }
            }
        }

        // Catalog-level structural checks
        try {
            $catalog = $this->getCatalog();

            if ($catalog->has('OCProperties') && !$version->isAtLeast(PdfVersion::V1_5)) {
                $warnings[] = "Optional content (/OCProperties) requires PDF 1.5, but document declares {$version->value}";
            }
            if ($catalog->has('Collection') && !$version->isAtLeast(PdfVersion::V1_7)) {
                $warnings[] = "PDF Portfolio (/Collection) requires PDF 1.7, but document declares {$version->value}";
            }
            if ($catalog->has('DPartRoot') && !$version->isAtLeast(PdfVersion::V2_0)) {
                $warnings[] = "Document parts (/DPartRoot) requires PDF 2.0, but document declares {$version->value}";
            }
            if ($catalog->has('DSS') && !$version->isAtLeast(PdfVersion::V2_0)) {
                $warnings[] = "Document security store (/DSS) requires PDF 2.0, but document declares {$version->value}";
            }
            if ($catalog->has('AF') && !$version->isAtLeast(PdfVersion::V2_0)) {
                $warnings[] = "Associated files (/AF) requires PDF 2.0, but document declares {$version->value}";
            }
            if ($catalog->has('Requirements') && !$version->isAtLeast(PdfVersion::V1_7)) {
                $warnings[] = "Requirements (/Requirements) requires PDF 1.7, but document declares {$version->value}";
            }
        } catch (InvalidPdfException) {
            // Can't resolve catalog — skip structural checks
        }

        // Linearization integrity checks
        $linParams = $this->getLinearizationParameters();
        if ($linParams !== null) {
            if ($linParams['pageCount'] > 0 && $linParams['pageCount'] !== $this->getPageCount()) {
                $warnings[] = sprintf(
                    'Linearization /N (%d) does not match actual page count (%d)',
                    $linParams['pageCount'],
                    $this->getPageCount(),
                );
            }
        }

        return $warnings;
    }

    /**
     * Check whether this PDF is linearized (web-optimized).
     *
     * A linearized PDF has a LinearizationParameters dictionary as the
     * very first indirect object, containing a /Linearized key. The
     * reader handles linearized PDFs correctly (via startxref), but
     * does not use the hint tables for progressive loading.
     */
    public function isLinearized(): bool
    {
        // Object 1 is typically the linearization dict — check it first
        foreach ([1, 2] as $objNum) {
            try {
                $obj = $this->resolver->resolve($objNum);
            } catch (\Throwable) {
                continue;
            }
            if ($obj instanceof PdfDictionary && $obj->get('Linearized') !== null) {
                return true;
            }
        }
        return false;
    }

    /**
     * Get linearization parameters if the PDF is linearized.
     *
     * @return array{linearized: float, fileLength: int, firstPageObj: int, firstPageEnd: int, pageCount: int, xrefOffset: int}|null
     */
    public function getLinearizationParameters(): ?array
    {
        foreach ([1, 2] as $objNum) {
            try {
                $obj = $this->resolver->resolve($objNum);
            } catch (\Throwable) {
                continue;
            }
            if (!$obj instanceof PdfDictionary || $obj->get('Linearized') === null) {
                continue;
            }

            $getInt = static fn(string $key): int =>
                ($v = $obj->get($key)) instanceof PdfNumber ? (int) $v->toPdf() : 0;
            $getFloat = static fn(string $key): float =>
                ($v = $obj->get($key)) instanceof PdfNumber ? (float) $v->toPdf() : 0.0;

            return [
                'linearized' => $getFloat('Linearized'),
                'fileLength' => $getInt('L'),
                'firstPageObj' => $getInt('O'),
                'firstPageEnd' => $getInt('E'),
                'pageCount' => $getInt('N'),
                'xrefOffset' => $getInt('T'),
            ];
        }

        return null;
    }

    /**
     * Parse the page offset hint table from a linearized PDF.
     *
     * Returns null if the PDF is not linearized or the hint stream
     * cannot be located/parsed.
     */
    public function getPageOffsetHintTable(): ?PageOffsetHintTable
    {
        $params = $this->getLinearizationParameters();
        if ($params === null) {
            return null;
        }

        // Find the /H array from the linearization dict
        foreach ([1, 2] as $objNum) {
            try {
                $obj = $this->resolver->resolve($objNum);
            } catch (\Throwable) {
                continue;
            }
            if (!$obj instanceof PdfDictionary || $obj->get('Linearized') === null) {
                continue;
            }

            $hArray = $obj->get('H');
            if (!$hArray instanceof PdfArray || count($hArray->items) < 2) {
                return null;
            }

            $hintOffset = $hArray->items[0] instanceof PdfNumber
                ? (int) $hArray->items[0]->toPdf() : 0;
            $hintLength = $hArray->items[1] instanceof PdfNumber
                ? (int) $hArray->items[1]->toPdf() : 0;

            if ($hintOffset <= 0 || $hintLength <= 0) {
                return null;
            }

            // Find the hint stream object — it's at the given byte offset.
            // Look through resolved objects to find one at that offset.
            // The hint stream is typically a regular indirect object we can resolve.
            // Try to find it by scanning known objects near the offset.
            try {
                // The hint stream object might be identifiable by iterating objects
                // or by directly parsing at the offset. For now, iterate objects
                // and find the stream near the linearization dict.
                $hintData = null;
                $hintDict = null;

                // Try objects 2-10 (hint stream is typically early in the file)
                for ($n = 1; $n <= min(20, $params['pageCount'] + 10); $n++) {
                    try {
                        $candidate = $this->resolver->resolve($n);
                    } catch (\Throwable) {
                        continue;
                    }
                    if (
                        $candidate instanceof PdfDictionary
                        && $candidate->has('S')
                        && ($candidate->get('S') instanceof PdfNumber)
                    ) {
                        // This looks like a hint stream dict (has /S for shared obj table offset)
                        // Check if it's a stream by looking for data
                        $hintDict = $candidate;
                        break;
                    }
                }

                // If we found the dict but no stream data, we can't parse hints
                if ($hintDict === null) {
                    return null;
                }

                // Get the page offset table offset (usually 0 within the hint data)
                $pageTableOffset = 0; // /P offset, default 0
                $pVal = $hintDict->get('P');
                if ($pVal instanceof PdfNumber) {
                    $pageTableOffset = (int) $pVal->toPdf();
                }

                // For now, return null if we can't get the raw stream data
                // (full implementation would parse the stream bytes directly)
                return null;
            } catch (\Throwable) {
                return null;
            }
        }

        return null;
    }

    /**
     * Calculate the byte range for a specific page in a linearized PDF.
     *
     * Returns an associative array with 'offset' and 'length' keys,
     * or null if the PDF is not linearized or hints are unavailable.
     *
     * @return array{offset: int, length: int}|null
     */
    public function getPageByteRange(int $pageIndex): ?array
    {
        $hintTable = $this->getPageOffsetHintTable();
        if ($hintTable === null) {
            return null;
        }

        try {
            return $hintTable->getPageByteRange($pageIndex);
        } catch (\OutOfRangeException) {
            return null;
        }
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

    private static function build(
        Source $source,
        string $password = '',
        bool $strict = true,
        ?string $certificate = null,
        ?string $privateKey = null,
    ): self
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
        if ($encrypt instanceof PdfReference) {
            // /Encrypt might be an indirect reference — resolve it
            $tempResolver = new ObjectResolver($entries, $tokenizer, $source, $objectParser, $streamParser);
            $resolved = $tempResolver->resolveReference($encrypt);
            if ($resolved instanceof PdfDictionary) {
                $encrypt = $resolved;
            }
        }
        if ($encrypt instanceof PdfDictionary) {
            $fileId = self::extractFileId($trailer);
            $filter = $encrypt->get('Filter');
            $isPublicKey = $filter instanceof PdfName && $filter->value === 'Adobe.PubSec';

            if ($isPublicKey && $certificate !== null && $privateKey !== null) {
                $decryptor = PdfDecryptor::fromEncryptDictPublicKey(
                    $encrypt, $certificate, $privateKey, $fileId
                );
            } elseif (!$isPublicKey) {
                $decryptor = PdfDecryptor::fromEncryptDict($encrypt, $password, $fileId);
            } else {
                throw new InvalidPdfException(
                    'PDF uses public-key encryption; use fromFilePublicKey() or fromStringPublicKey() with certificate and private key'
                );
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
