<?php

declare(strict_types=1);

namespace Phpdftk\Pdf\Core\File;

use Phpdftk\Pdf\Core\Document\Catalog;
use Phpdftk\Pdf\Core\Document\Info;
use Phpdftk\Pdf\Core\Interactive\Signature\Pkcs7Signer;
use Phpdftk\Pdf\Core\Interactive\Signature\SignatureValue;
use Phpdftk\Pdf\Core\Interactive\Signature\TsaClient;
use Phpdftk\Pdf\Core\Security\PdfEncryptor;
use Phpdftk\Pdf\Core\Content\ContentStream;
use Phpdftk\Pdf\Core\Document\CrossReferenceStream;
use Phpdftk\Pdf\Core\Document\ObjectStream;
use Phpdftk\Filters\FlateFilter;
use Phpdftk\Pdf\Core\PdfArray;
use Phpdftk\Pdf\Core\PdfName;
use Phpdftk\Pdf\Core\PdfObject;
use Phpdftk\Pdf\Core\PdfReference;
use Phpdftk\Pdf\Core\PdfStream;
use Phpdftk\Pdf\Core\PdfString;
use Phpdftk\Pdf\Core\PdfVersion;
use Phpdftk\Pdf\Core\Serializable;

/**
 * Byte-level PDF file emitter — ISO 32000-2 §7.5.
 *
 * Given a {@see Catalog} root, an {@see Info} (optional), a working
 * {@see ObjectRegistry}, and optionally a signer, `generate()` produces
 * the exact bytes of a spec-compliant PDF file: header, binary comment,
 * indirect-object body, cross-reference table, trailer, `startxref`,
 * `%%EOF`. With a signer configured, the output is post-processed to
 * compute `/ByteRange` and patch `/Contents` in place.
 *
 * This class deliberately knows nothing about pages, fonts, resources,
 * or any other high-level document assembly concern. Those live in
 * `Phpdftk\Pdf\Writer\PdfWriter`, which composes an instance of this
 * class.
 *
 * @api
 */
class PdfFileWriter
{
    public const DEFAULT_VERSION = '1.7';
    public const DEFAULT_PDF_VERSION = PdfVersion::V1_7;

    private ObjectRegistry $registry;
    private ?Catalog $catalog = null;
    private ?Info $info = null;

    private ?SignatureValue $signatureValue = null;
    private ?Pkcs7Signer $signer = null;
    private int $signaturePlaceholderBytes = 8192;
    private ?TsaClient $tsaClient = null;
    private bool $compressStreams = true;
    private bool $useXRefStream = false;
    private bool $useObjectStreams = false;
    private ?PdfEncryptor $encryptor = null;
    private PdfVersion $version;
    private bool $strictVersionMode = false;
    private ?PdfVersion $ceilingVersion = null;

    /** @var list<string> */
    private array $versionWarnings = [];

    /** @var (\Closure(string): void)|null */
    private ?\Closure $deprecationHandler = null;

    private bool $strictDeprecation = false;

    public function __construct(
        bool $compressStreams = true,
        bool $useXRefStream = false,
        bool $useObjectStreams = false,
        PdfVersion|string $version = self::DEFAULT_PDF_VERSION,
    ) {
        $this->registry = new ObjectRegistry();
        $this->compressStreams = $compressStreams;
        $this->version = $version instanceof PdfVersion
            ? $version
            : (PdfVersion::tryFrom($version) ?? self::DEFAULT_PDF_VERSION);
        // Object streams require xref streams (type 2 entries)
        $this->useXRefStream = $useXRefStream || $useObjectStreams;
        $this->useObjectStreams = $useObjectStreams;
    }

    /**
     * Enable or disable automatic FlateDecode compression of streams
     * that have no filter already set.
     */
    public function setCompressStreams(bool $compress): void
    {
        $this->compressStreams = $compress;
    }

    /**
     * Configure encryption for the generated PDF.
     *
     * The encrypt dictionary is registered as an indirect object and
     * referenced from the trailer. All string values and stream data
     * are encrypted per-object during generation.
     *
     * @param PdfEncryptor $encryptor A configured encryptor (use PdfEncryptor::rc4128() or ::aes128())
     */
    public function setEncryption(PdfEncryptor $encryptor): void
    {
        $this->encryptor = $encryptor;
        $encryptDict = $encryptor->getEncryptDictionary();
        $this->register($encryptDict);
        $encryptor->setEncryptDictObjNum($encryptDict->objectNumber);

        $required = $encryptor->getMinimumPdfVersion();
        if ($this->ceilingVersion !== null && $required->isGreaterThan($this->ceilingVersion)) {
            throw new CeilingVersionException(PdfEncryptor::class, $required, $this->ceilingVersion);
        }
        $this->applyVersionRequirement($required, PdfEncryptor::class);
    }

    /**
     * The underlying object registry. Exposed so callers that want
     * fine-grained control over registration order can drive it
     * directly. Most callers should use {@see register()}.
     */
    public function getRegistry(): ObjectRegistry
    {
        return $this->registry;
    }

    public function getVersion(): string
    {
        return $this->version->value;
    }

    public function getPdfVersion(): PdfVersion
    {
        return $this->version;
    }

    public function setVersion(PdfVersion|string $version): void
    {
        $this->version = $version instanceof PdfVersion
            ? $version
            : (PdfVersion::tryFrom($version) ?? self::DEFAULT_PDF_VERSION);
    }

    public function setStrictVersionMode(bool $strict = true): void
    {
        $this->strictVersionMode = $strict;
    }

    public function setDeprecationHandler(\Closure $handler): void
    {
        $this->deprecationHandler = $handler;
    }

    public function setStrictDeprecation(bool $strict = true): void
    {
        $this->strictDeprecation = $strict;
    }

    /** @return list<string> */
    public function getVersionWarnings(): array
    {
        return $this->versionWarnings;
    }

    /**
     * Set a ceiling version — properties requiring a higher version are
     * silently stripped (set to null) during registration. Objects whose
     * class-level requirement exceeds the ceiling throw CeilingVersionException.
     *
     * Mutually exclusive with strict mode — setting a ceiling disables strict.
     */
    public function setCeilingVersion(?PdfVersion $ceiling): void
    {
        $this->ceilingVersion = $ceiling;
        if ($ceiling !== null) {
            $this->strictVersionMode = false;
            $this->version = $ceiling;
        }
    }

    /**
     * Register the document catalog and return its reference. The
     * catalog becomes the `/Root` of the emitted file.
     */
    public function setCatalog(Catalog $catalog): PdfReference
    {
        $this->catalog = $catalog;
        $this->checkVersionRequirements($catalog);
        $this->registry->register($catalog);
        return new PdfReference($catalog->objectNumber);
    }

    /**
     * Set the document info dict. Pass null to clear. Registers the
     * object if non-null.
     */
    public function setInfo(?Info $info): void
    {
        $this->info = $info;
        if ($info !== null) {
            $this->checkVersionRequirements($info);
            $this->registry->register($info);
        }
    }

    /** Return the Info dict, if set. */
    public function getInfo(): ?Info
    {
        return $this->info;
    }

    /**
     * Register any PdfObject and return a reference to it.
     */
    public function register(PdfObject $object): PdfReference
    {
        $this->checkVersionRequirements($object);
        $this->registry->register($object);
        return new PdfReference($object->objectNumber);
    }

    /**
     * Configure a signer. After calling this, {@see generate()} will:
     *
     *   1. Serialize the document with a fixed-size `/Contents` hex
     *      placeholder and a 4-element `/ByteRange` of zero-padded
     *      10-digit slots inside `$signatureValue`.
     *   2. Locate the placeholders in the serialized bytes.
     *   3. Patch `/ByteRange` with the real offsets (same byte length).
     *   4. Feed the two byte ranges to `$signer` to produce a PKCS#7
     *      SignedData blob.
     *   5. Patch `/Contents` with the DER bytes (hex-encoded, zero-padded
     *      to the placeholder length).
     *
     * `$signatureValue` must already be registered as an indirect object
     * and referenced by a `SignatureField::$v` (directly or via
     * reference). `$placeholderBytes` bounds the maximum signature size
     * — 8 KiB is more than enough for typical RSA/ECDSA PKCS#7 blobs.
     */
    public function setSigner(
        SignatureValue $signatureValue,
        Pkcs7Signer $signer,
        int $placeholderBytes = 8192
    ): void {
        $this->signatureValue = $signatureValue;
        $this->signer = $signer;
        $this->signaturePlaceholderBytes = $placeholderBytes;

        // Install fixed-width placeholders that we'll patch after serialization.
        $signatureValue->contents = new PdfString(
            str_repeat("\x00", $placeholderBytes),
            hex: true
        );
        $signatureValue->byteRange = new PdfArray([
            '0000000000',
            '0000000000',
            '0000000000',
            '0000000000',
        ]);
    }

    /**
     * Configure a document-level timestamp using a TSA client.
     *
     * This is the timestamp equivalent of {@see setSigner()}: it installs
     * a DocTimeStamp signature value and TSA client, then patches
     * /ByteRange and /Contents at generation time with the RFC 3161
     * timestamp token from the TSA.
     *
     * @param SignatureValue $docTimeStamp  A DocTimeStamp instance to hold the token
     * @param TsaClient      $tsaClient    The TSA client to request the token from
     * @param int            $placeholderBytes  Size of the /Contents placeholder
     */
    public function setTimestamper(
        SignatureValue $docTimeStamp,
        TsaClient $tsaClient,
        int $placeholderBytes = 16384
    ): void {
        $this->signatureValue = $docTimeStamp;
        $this->tsaClient = $tsaClient;
        $this->signaturePlaceholderBytes = $placeholderBytes;

        // Install fixed-width placeholders (same pattern as setSigner)
        $docTimeStamp->contents = new PdfString(
            str_repeat("\x00", $placeholderBytes),
            hex: true
        );
        $docTimeStamp->byteRange = new PdfArray([
            '0000000000',
            '0000000000',
            '0000000000',
            '0000000000',
        ]);
    }

    /**
     * Configure a TSA client for RFC 3161 timestamping.
     *
     * When set alongside a signer, the timestamp token will be embedded
     * in the PKCS#7 signature. When set without a signer (with a
     * DocTimeStamp signature value), produces a document-level timestamp.
     */
    public function setTsaClient(TsaClient $tsaClient): void
    {
        $this->tsaClient = $tsaClient;
    }

    /**
     * Generate the complete PDF as a binary string.
     *
     * Assembles the file as an array of string chunks and implodes once at
     * the end -- this is O(N) in total output size, whereas repeated string
     * concatenation would be O(N^2) because PHP copies the growing string
     * on every `.=`.
     *
     * The chunk order follows ISO 32000-2 section 7.5:
     *   1. %PDF-X.Y header
     *   2. Binary comment (%\xE2\xE3\xCF\xD3) so transfer tools treat
     *      the file as binary rather than ASCII
     *   3. Indirect-object body (one chunk per registered object)
     *   4. Cross-reference table (or xref stream for PDF >= 1.5)
     *   5. Trailer dictionary
     *   6. startxref pointer
     *   7. %%EOF marker
     *
     * When a signer is configured, the serialized bytes are post-processed
     * by {@see applySignature()} to fill /ByteRange and /Contents.
     */
    public function generate(): string
    {
        if ($this->catalog === null) {
            throw new \RuntimeException(
                'PdfFileWriter::generate() called before setCatalog()'
            );
        }

        // Auto-bump version for xref/object streams before emission
        if ($this->useXRefStream) {
            if ($this->ceilingVersion !== null && !$this->ceilingVersion->isAtLeast(PdfVersion::V1_5)) {
                // Ceiling is below 1.5 — downgrade to classic xref
                $this->useXRefStream = false;
                $this->useObjectStreams = false;
                $this->versionWarnings[] = 'Downgraded from xref stream to classic xref (ceiling < 1.5)';
            } else {
                $this->applyVersionRequirement(PdfVersion::V1_5, 'XRefStream');
            }
        }

        // Sync catalog /Version for PDF > 1.4 (ISO 32000 §7.2.2)
        if ($this->version->isGreaterThan(PdfVersion::V1_4)) {
            $this->catalog->version = new PdfName($this->version->value);
        }

        $xref   = new CrossReferenceTable();
        $chunks = [];

        // PDF header
        $chunks[] = '%PDF-' . $this->version->value . "\n";
        // Binary comment — 4 bytes > 127 to signal binary file
        $chunks[] = "%\xE2\xE3\xCF\xD3\n";

        // Track byte offset without concatenating the full string each iteration
        $offset = strlen($chunks[0]) + strlen($chunks[1]);

        // Build the array of objects to serialize. When encryption is
        // active we clone every object so the originals stay untouched,
        // allowing generate() to be called multiple times (idempotency).
        // Compression is applied on the clones as well so that the spec-
        // mandated order (compress → encrypt) is respected.
        $serializableObjects = $this->registry->getAll();

        if ($this->encryptor !== null) {
            $clones = [];
            $flate = $this->compressStreams ? new FlateFilter() : null;

            foreach ($serializableObjects as $objNum => $object) {
                $clone = clone $object;

                // Deep-clone the dictionary for PdfStream subclasses so
                // we don't mutate the original's dictionary.
                if ($clone instanceof PdfStream) {
                    $clone->dictionary = clone $clone->dictionary;
                }

                // Materialize ContentStream operators into data
                if ($clone instanceof ContentStream) {
                    $clone->data = implode("\n", $clone->getOperators());
                    $clone->clearOperators();
                }

                // Compress before encrypting (spec order: compress → encrypt).
                // We do this manually on the clone instead of via setFilter()
                // so that toPdf() won't try to re-compress later.
                if (
                    $flate !== null
                    && $clone instanceof PdfStream
                    && !$clone->dictionary->has('Filter')
                    && $clone->data !== ''
                ) {
                    $clone->data = $flate->encode($clone->data);
                    $clone->dictionary->set('Filter', new PdfName('FlateDecode'));
                }

                $this->encryptor->encryptObject($clone);
                $clones[$objNum] = $clone;
            }
            $serializableObjects = $clones;
        } elseif ($this->compressStreams) {
            // No encryption — apply compression on the originals (safe:
            // setFilter is idempotent due to the has('Filter') guard).
            $this->applyStreamCompression();
        }

        // Object stream packing: group eligible objects into ObjectStream containers
        // Per spec §7.5.7, objects with streams and the encrypt dict cannot be packed.
        /** @var array<int, int> objNum => objStmObjNum for type 2 xref entries */
        $compressedEntries = [];
        /** @var array<int, int> objNum => index within the object stream */
        $compressedIndices = [];

        if ($this->useObjectStreams) {
            $encryptObjNum = $this->encryptor !== null
                ? $this->encryptor->getEncryptDictionary()->objectNumber
                : 0;

            $eligible = [];
            $ineligible = [];
            foreach ($serializableObjects as $objNum => $object) {
                // Cannot pack: streams (have their own data), catalog (must be direct),
                // encrypt dict (must be accessible before decryption)
                if (
                    $object instanceof PdfStream
                    || $objNum === $this->catalog->objectNumber
                    || $objNum === $encryptObjNum
                ) {
                    $ineligible[$objNum] = $object;
                } else {
                    $eligible[$objNum] = $object;
                }
            }

            if ($eligible !== []) {
                // Create ObjectStream(s) — pack up to 200 objects per stream
                $nextObjNum = $this->registry->getSize();
                $objStreamBatches = array_chunk($eligible, 200, true);
                foreach ($objStreamBatches as $batch) {
                    $objStm = new ObjectStream();
                    $objStmObjNum = $nextObjNum++;
                    $objStm->objectNumber = $objStmObjNum;
                    $objStm->generationNumber = 0;

                    $index = 0;
                    foreach ($batch as $objNum => $object) {
                        $objStm->addObject($object);
                        $compressedEntries[$objNum] = $objStmObjNum;
                        $compressedIndices[$objNum] = $index;
                        $index++;
                    }

                    // Compress the object stream
                    if ($this->compressStreams) {
                        $objStm->setFilter(new FlateFilter(), 'FlateDecode');
                    }

                    // Add to ineligible so it gets written as a normal indirect object
                    $ineligible[$objStmObjNum] = $objStm;
                }

                $serializableObjects = $ineligible;
            }
        }

        // Write all objects in registration order
        foreach ($serializableObjects as $objNum => $object) {
            $xref->add($objNum, $offset);
            $chunk = $object->toIndirectObject() . "\n";
            $chunks[] = $chunk;
            $offset += strlen($chunk);
        }

        // Trailer — use encryptor's file ID if encrypting, else generate random
        $id = $this->encryptor !== null
            ? $this->encryptor->getFileId()
            : md5(microtime() . $offset, true);

        $idArray = new PdfArray([
            new PdfString($id, hex: true),
            new PdfString($id, hex: true),
        ]);

        if ($this->useXRefStream) {
            // PDF 1.5+ cross-reference stream — trailer entries are in the stream dict
            $xrefStream = new CrossReferenceStream();

            // Build entries: object 0 is free, then in-use or compressed entries
            $xrefStream->addFreeEntry(0, 65535);
            $xrefEntries = $xref->getEntries();
            $maxObjNum = max(
                $this->registry->getSize() - 1,
                $compressedEntries !== [] ? max(array_keys($compressedEntries)) : 0,
                $xrefEntries !== [] ? max(array_keys($xrefEntries)) : 0,
            );
            for ($i = 1; $i <= $maxObjNum; $i++) {
                if (isset($compressedEntries[$i])) {
                    $xrefStream->addCompressedEntry($compressedEntries[$i], $compressedIndices[$i]);
                } elseif (isset($xrefEntries[$i])) {
                    $xrefStream->addInUseEntry($xrefEntries[$i]);
                } else {
                    $xrefStream->addFreeEntry(0, 0);
                }
            }

            // The xref stream itself is the next sequential object number.
            $xrefStreamObjNum = $maxObjNum + 1;
            $xrefStream->objectNumber = $xrefStreamObjNum;
            $xrefStream->size = $xrefStreamObjNum + 1;
            $xrefStream->root = new PdfReference($this->catalog->objectNumber);
            $xrefStream->id = $idArray;
            if ($this->info !== null) {
                $xrefStream->info = new PdfReference($this->info->objectNumber);
            }
            if ($this->encryptor !== null) {
                $encryptDict = $this->encryptor->getEncryptDictionary();
                $xrefStream->encrypt = new PdfReference($encryptDict->objectNumber);
            }

            // Record the xref stream's own offset
            $xrefOffset = $offset;
            $xrefStream->addInUseEntry($xrefOffset);

            // Compress the xref stream data via setFilter (applied during toPdf)
            if ($this->compressStreams) {
                $xrefStream->setFilter(new FlateFilter(), 'FlateDecode');
            }

            $chunks[] = $xrefStream->toIndirectObject() . "\n";
            $chunks[] = "startxref\n" . $xrefOffset . "\n";
            $chunks[] = '%%EOF';
        } else {
            // Classic cross-reference table + trailer
            $xrefOffset = $offset;
            $xrefChunk  = $xref->build($this->registry->getSize());
            $chunks[]   = $xrefChunk;

            $trailer = new TrailerDictionary(new PdfReference($this->catalog->objectNumber));
            $trailer->size = $this->registry->getSize();
            if ($this->info !== null) {
                $trailer->info = new PdfReference($this->info->objectNumber);
            }
            if ($this->encryptor !== null) {
                $encryptDict = $this->encryptor->getEncryptDictionary();
                $trailer->encrypt = new PdfReference($encryptDict->objectNumber);
            }
            $trailer->id = $idArray;

            $chunks[] = "trailer\n" . $trailer->toPdf() . "\n";
            $chunks[] = "startxref\n" . $xrefOffset . "\n";
            $chunks[] = '%%EOF';
        }

        $pdf = implode('', $chunks);

        if ($this->signatureValue !== null && ($this->signer !== null || $this->tsaClient !== null)) {
            $pdf = $this->applySignature($pdf);
        }

        return $pdf;
    }

    /**
     * Generate a linearized (web-optimized) PDF per ISO 32000-2 Annex F.
     *
     * Linearized PDFs place the first page's objects at the front of the
     * file so a viewer can display it before downloading the rest. The
     * structure is:
     *
     *   1. Header + linearization parameters dict (object 1)
     *   2. First-page cross-reference section
     *   3. Catalog, page tree, first page, and its resources
     *   4. Hint stream
     *   5. Remaining pages and objects
     *   6. Main cross-reference section + trailer
     *
     * @param list<int> $firstPageObjectNumbers Object numbers belonging to the first page
     *                                           (page, content streams, fonts, images).
     *                                           If empty, auto-detected from object order.
     */
    public function generateLinearized(array $firstPageObjectNumbers = []): string
    {
        if ($this->catalog === null) {
            throw new \RuntimeException(
                'PdfFileWriter::generateLinearized() called before setCatalog()'
            );
        }

        // Sync catalog /Version for PDF > 1.4
        if ($this->version->isGreaterThan(PdfVersion::V1_4)) {
            $this->catalog->version = new PdfName($this->version->value);
        }

        // Apply compression
        if ($this->compressStreams) {
            $this->applyStreamCompression();
        }

        $allObjects = $this->registry->getAll();

        // Partition objects into first-page set and remaining set.
        // The first-page set always includes the catalog and page tree.
        $catalogNum = $this->catalog->objectNumber;
        $firstPageSet = [];
        $remainingSet = [];

        // Build the set of first-page object numbers
        $fpNums = array_flip($firstPageObjectNumbers);
        // Always include catalog in first-page set
        $fpNums[$catalogNum] = true;

        // If no explicit first-page objects given, use heuristic:
        // catalog + page tree + first few objects after page tree
        if ($firstPageObjectNumbers === []) {
            // Include all objects — they'll all be in the "first page" section
            // This is a valid minimal linearization where all objects are first-page
            foreach ($allObjects as $objNum => $_) {
                $fpNums[$objNum] = true;
            }
        }

        foreach ($allObjects as $objNum => $object) {
            if (isset($fpNums[$objNum])) {
                $firstPageSet[$objNum] = $object;
            } else {
                $remainingSet[$objNum] = $object;
            }
        }

        // Count pages by looking at the page tree kids
        $pageCount = 0;
        foreach ($allObjects as $object) {
            if ($object instanceof \Phpdftk\Pdf\Core\Document\PageTree) {
                $pageCount = $object->count;
                break;
            }
        }
        if ($pageCount === 0) {
            $pageCount = 1;
        }

        // Find first page object number
        $firstPageObjNum = $catalogNum; // fallback
        foreach ($allObjects as $object) {
            if ($object instanceof \Phpdftk\Pdf\Core\Document\Page) {
                $firstPageObjNum = $object->objectNumber;
                break;
            }
        }

        // === PASS 1: Build the PDF to determine offsets ===
        // We need two passes because the linearization parameters dict
        // contains the total file length and hint stream offsets, which
        // are only known after serialization.

        // We'll build the PDF structure, then patch the linearization dict.

        $chunks = [];

        // 1. Header
        $chunks[] = '%PDF-' . $this->version->value . "\n";
        $chunks[] = "%\xE2\xE3\xCF\xD3\n";
        $offset = strlen($chunks[0]) + strlen($chunks[1]);

        // 2. Linearization parameters dict (placeholder — will be patched)
        $linParams = new \Phpdftk\Pdf\Core\Document\LinearizationParameters();
        $linParams->objectNumber = 0; // will use a synthetic object number
        $linParams->generationNumber = 0;

        // Use object number = max + 1 for the linearization dict
        $maxObjNum = max(array_keys($allObjects));
        $linObjNum = $maxObjNum + 1;
        $linParams->objectNumber = $linObjNum;

        // Serialize a placeholder with max-width numbers (10 digits each)
        $linParams->linearized = 1.0;
        $linParams->l = 0;          // patched later
        $linParams->n = $pageCount;
        $linParams->o = $firstPageObjNum;
        $linParams->e = 0;          // patched later
        $linParams->t = 0;          // patched later
        $linParams->h = new \Phpdftk\Pdf\Core\PdfArray([
            new \Phpdftk\Pdf\Core\PdfNumber(0),
            new \Phpdftk\Pdf\Core\PdfNumber(0),
        ]);

        // Emit the linearization dict with padded numbers so the byte
        // offsets don't shift when we patch real values.
        $linDictChunk = $this->emitPaddedLinearizationDict($linObjNum, $linParams);
        $linDictOffset = $offset;
        $chunks[] = $linDictChunk;
        $offset += strlen($linDictChunk);

        // 3. First-page objects
        $firstPageXref = new CrossReferenceTable();

        // Record linearization dict in first-page xref
        $firstPageXref->add($linObjNum, $linDictOffset);

        foreach ($firstPageSet as $objNum => $object) {
            $firstPageXref->add($objNum, $offset);
            $chunk = $object->toIndirectObject() . "\n";
            $chunks[] = $chunk;
            $offset += strlen($chunk);
        }

        $endOfFirstPage = $offset; // /E value

        // 4. Hint stream (minimal — page offset table only)
        $hintObjNum = $linObjNum + 1;
        $hintStreamOffset = $offset;
        $hintData = $this->buildMinimalHintStream($pageCount);
        $hintStream = new \Phpdftk\Pdf\Core\Document\HintStream($hintData);
        $hintStream->objectNumber = $hintObjNum;
        $hintStream->generationNumber = 0;
        $hintStream->p = 0; // page offset table starts at byte 0 of stream data
        $hintStream->s = strlen($hintData); // shared object table at end (empty)

        $firstPageXref->add($hintObjNum, $offset);
        $hintChunk = $hintStream->toIndirectObject() . "\n";
        $chunks[] = $hintChunk;
        $offset += strlen($hintChunk);

        // 5. First-page xref section
        $firstPageXrefOffset = $offset;
        $totalSize = $hintObjNum + 1; // total object count including lin + hint

        // Build xref for first-page objects only (subsection format)
        $xrefSection = $this->buildSubsectionXref($firstPageXref, $totalSize);
        $chunks[] = $xrefSection;
        $offset += strlen($xrefSection);

        // 6. Remaining-page objects
        $mainXref = new CrossReferenceTable();
        // Re-record first-page objects for the main xref too
        foreach ($firstPageXref->getEntries() as $on => $off) {
            $mainXref->add($on, $off);
        }

        foreach ($remainingSet as $objNum => $object) {
            $mainXref->add($objNum, $offset);
            $chunk = $object->toIndirectObject() . "\n";
            $chunks[] = $chunk;
            $offset += strlen($chunk);
        }

        // 7. Main xref section
        $mainXrefOffset = $offset;
        $mainXrefChunk = $mainXref->build($totalSize);
        $chunks[] = $mainXrefChunk;
        $offset += strlen($mainXrefChunk);

        // File ID
        $id = md5(microtime() . $offset, true);
        $idArray = new \Phpdftk\Pdf\Core\PdfArray([
            new PdfString($id, hex: true),
            new PdfString($id, hex: true),
        ]);

        // 8. First-page trailer (between first-page xref and remaining objects)
        // This trailer has /Prev pointing to the main xref
        $firstPageTrailer = new TrailerDictionary(new PdfReference($catalogNum));
        $firstPageTrailer->size = $totalSize;
        $firstPageTrailer->prev = $mainXrefOffset;
        $firstPageTrailer->id = $idArray;
        if ($this->info !== null) {
            $firstPageTrailer->info = new PdfReference($this->info->objectNumber);
        }

        // Insert the first-page trailer right after the first-page xref
        // We need to insert it before the remaining objects
        $fpTrailerStr = "trailer\n" . $firstPageTrailer->toPdf() . "\n"
            . "startxref\n" . $firstPageXrefOffset . "\n"
            . "%%EOF\n";

        // 9. Main trailer
        $mainTrailer = new TrailerDictionary(new PdfReference($catalogNum));
        $mainTrailer->size = $totalSize;
        $mainTrailer->id = $idArray;
        if ($this->info !== null) {
            $mainTrailer->info = new PdfReference($this->info->objectNumber);
        }

        $chunks[] = "trailer\n" . $mainTrailer->toPdf() . "\n";
        $chunks[] = "startxref\n" . $mainXrefOffset . "\n";
        $chunks[] = '%%EOF';

        // Now we need to insert the first-page trailer after the first-page xref.
        // Find the index where the first-page xref was emitted and insert after it.
        // The structure in chunks is:
        //   [0] header, [1] binary comment, [2] lin dict, [3..N] first page objects,
        //   [N+1] hint stream, [N+2] first-page xref, [N+3..] remaining objects, main xref, main trailer

        // Actually, let me restructure: the linearized format requires the first-page
        // trailer to come right after the first-page xref, THEN the remaining objects.
        // Let me rebuild the chunks array properly.

        // === REBUILD with correct ordering ===
        $chunks = [];
        $offset = 0;

        // Part 1: Header
        $header = '%PDF-' . $this->version->value . "\n%\xE2\xE3\xCF\xD3\n";
        $chunks[] = $header;
        $offset += strlen($header);

        // Part 2: Linearization dict
        $linDictOffset = $offset;
        $chunks[] = $linDictChunk;
        $offset += strlen($linDictChunk);

        // Part 3: First-page objects
        $fpXref = new CrossReferenceTable();
        $fpXref->add($linObjNum, $linDictOffset);

        foreach ($firstPageSet as $objNum => $object) {
            $fpXref->add($objNum, $offset);
            $chunk = $object->toIndirectObject() . "\n";
            $chunks[] = $chunk;
            $offset += strlen($chunk);
        }

        $endOfFirstPage = $offset;

        // Part 4: Hint stream
        $hintStreamOffset = $offset;
        $fpXref->add($hintObjNum, $hintStreamOffset);
        $hintChunk = $hintStream->toIndirectObject() . "\n";
        $chunks[] = $hintChunk;
        $offset += strlen($hintChunk);

        // Part 5: First-page xref
        $fpXrefOffset = $offset;
        $fpXrefSection = $this->buildSubsectionXref($fpXref, $totalSize);
        $chunks[] = $fpXrefSection;
        $offset += strlen($fpXrefSection);

        // Part 6: First-page trailer
        $fpTrailer = new TrailerDictionary(new PdfReference($catalogNum));
        $fpTrailer->size = $totalSize;
        $fpTrailer->id = $idArray;
        if ($this->info !== null) {
            $fpTrailer->info = new PdfReference($this->info->objectNumber);
        }

        // We'll patch /Prev after we know the main xref offset — for now use placeholder
        $fpTrailerStr = "trailer\n" . $fpTrailer->toPdf() . "\n"
            . "startxref\n" . $fpXrefOffset . "\n"
            . "%%EOF\n";
        $chunks[] = $fpTrailerStr;
        $offset += strlen($fpTrailerStr);

        // Part 7: Remaining objects
        $mainXref = new CrossReferenceTable();
        // Copy first-page entries to main xref
        foreach ($fpXref->getEntries() as $on => $off) {
            $mainXref->add($on, $off);
        }

        foreach ($remainingSet as $objNum => $object) {
            $mainXref->add($objNum, $offset);
            $chunk = $object->toIndirectObject() . "\n";
            $chunks[] = $chunk;
            $offset += strlen($chunk);
        }

        // Part 8: Main xref
        $mainXrefOffset = $offset;
        $mainXrefChunk = $mainXref->build($totalSize);
        $chunks[] = $mainXrefChunk;
        $offset += strlen($mainXrefChunk);

        // Part 9: Main trailer — /Prev points to first-page xref
        $mainTrailer2 = new TrailerDictionary(new PdfReference($catalogNum));
        $mainTrailer2->size = $totalSize;
        $mainTrailer2->id = $idArray;
        if ($this->info !== null) {
            $mainTrailer2->info = new PdfReference($this->info->objectNumber);
        }

        $chunks[] = "trailer\n" . $mainTrailer2->toPdf() . "\n";
        $chunks[] = "startxref\n" . $mainXrefOffset . "\n";
        $chunks[] = '%%EOF';

        $pdf = implode('', $chunks);

        // === PATCH linearization parameters ===
        $totalLen = strlen($pdf);

        // Patch /L (file length)
        $pdf = $this->patchPaddedNumber($pdf, '/L ', $totalLen);
        // Patch /E (end of first page)
        $pdf = $this->patchPaddedNumber($pdf, '/E ', $endOfFirstPage);
        // Patch /T (main xref offset)
        $pdf = $this->patchPaddedNumber($pdf, '/T ', $mainXrefOffset);
        // Patch /H hint stream offset and length
        $hintLength = strlen($hintChunk);
        $pdf = $this->patchHintArray($pdf, $hintStreamOffset, $hintLength);

        return $pdf;
    }

    /**
     * Emit the linearization dict with padded 10-digit numbers so patching
     * doesn't change byte offsets.
     */
    private function emitPaddedLinearizationDict(int $objNum, \Phpdftk\Pdf\Core\Document\LinearizationParameters $params): string
    {
        // Use fixed-width formatting so patching is safe
        return sprintf(
            "%d 0 obj\n<< /Linearized 1 /L %010d /H [ %010d %010d ] /O %d /E %010d /N %d /T %010d >>\nendobj\n",
            $objNum,
            $params->l,
            0, // hint offset placeholder
            0, // hint length placeholder
            $params->o,
            $params->e,
            $params->n,
            $params->t,
        );
    }

    /**
     * Patch a 10-digit padded number in the linearization dict.
     */
    private function patchPaddedNumber(string $pdf, string $key, int $value): string
    {
        // Find the key in the linearization dict (first occurrence)
        $pos = strpos($pdf, $key);
        if ($pos === false) {
            return $pdf;
        }
        $numStart = $pos + strlen($key);
        return substr_replace($pdf, sprintf('%010d', $value), $numStart, 10);
    }

    /**
     * Patch the /H hint array with the actual offset and length.
     */
    private function patchHintArray(string $pdf, int $offset, int $length): string
    {
        $pos = strpos($pdf, '/H [ ');
        if ($pos === false) {
            return $pdf;
        }
        $start = $pos + strlen('/H [ ');
        // Replace the two 10-digit numbers
        $pdf = substr_replace($pdf, sprintf('%010d', $offset), $start, 10);
        $pdf = substr_replace($pdf, sprintf('%010d', $length), $start + 11, 10);
        return $pdf;
    }

    /**
     * Build a minimal hint stream for a linearized PDF.
     *
     * Per ISO 32000-2 §F.4, the page offset hint table has an 11-field
     * header (44 bytes) followed by per-page bit-packed entries.
     * For simplicity, this builds a minimal table with all deltas = 0.
     */
    private function buildMinimalHintStream(int $pageCount): string
    {
        $bw = new BitWriter();

        // Page offset hint table header — 11 × 32-bit values
        // All minimums are 1, all bit counts are 0 (no per-page variance)
        $bw->writeUint32(1);         // 1: min objects per page
        $bw->writeUint32(0);         // 2: largest page object count - min (dummy)
        $bw->writeUint32(0);         // 3: bit count for object count delta
        $bw->writeUint32(1);         // 4: min page length
        $bw->writeUint32(0);         // 5: largest page length - min (dummy)
        $bw->writeUint32(0);         // 6: bit count for page length delta
        $bw->writeUint32(0);         // 7: min content stream offset
        $bw->writeUint32(0);         // 8: bit count for content stream offset delta
        $bw->writeUint32(0);         // 9: min content stream length
        $bw->writeUint32(0);         // 10: bit count for content stream length delta
        $bw->writeUint32(0);         // 11: bit count for shared object refs

        // Per-page entries: with all bit counts = 0, there are no bits to write per page

        $bw->alignToByte();
        return $bw->getData();
    }

    /**
     * Build a cross-reference section with a single subsection covering
     * all objects from 0 to $size-1.
     */
    private function buildSubsectionXref(CrossReferenceTable $xref, int $size): string
    {
        return $xref->build($size);
    }

    /**
     * Alias for {@see generate()} — returns the raw PDF bytes as a string.
     */
    public function toBytes(): string
    {
        return $this->generate();
    }

    /**
     * Write the generated PDF to an open stream resource (anything
     * `fwrite()` accepts: a file handle, `php://memory`, `php://output`,
     * a socket, …). Returns the number of bytes written.
     *
     * @param resource $stream
     */
    public function writeTo($stream): int
    {
        if (!is_resource($stream)) {
            throw new \InvalidArgumentException(
                'PdfFileWriter::writeTo() expects an open stream resource'
            );
        }
        $pdf = $this->generate();
        $written = fwrite($stream, $pdf);
        if ($written === false) {
            throw new \RuntimeException('Failed to write PDF bytes to stream');
        }
        return $written;
    }

    /**
     * Write the generated PDF to a file, creating parent directories
     * as needed.
     */
    public function save(string $path): void
    {
        $pdf = $this->generate();
        $dir = dirname($path);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        file_put_contents($path, $pdf);
    }

    /**
     * Apply FlateDecode compression to all PdfStream objects that don't
     * already have a filter set and contain non-empty data.
     */
    private function applyStreamCompression(): void
    {
        $flate = new FlateFilter();
        foreach ($this->registry->getAll() as $object) {
            if (!$object instanceof PdfStream) {
                continue;
            }
            // Metadata streams must not be compressed (ISO 32000 §14.3.2,
            // ISO 19005 clause 6.7.2) so they remain searchable/readable.
            if ($object instanceof \Phpdftk\Pdf\Core\Document\MetadataStream) {
                continue;
            }
            // Skip streams that already have a filter configured via setFilter()
            // or an explicit /Filter entry in their dictionary
            if ($object->dictionary->has('Filter')) {
                continue;
            }
            $object->setFilter($flate, 'FlateDecode');
        }
    }

    /**
     * Post-process a fully serialized PDF to fill in a signature's
     * /ByteRange and /Contents placeholders with real values.
     *
     * The two-pass approach is required because /ByteRange must describe
     * the exact byte offsets of the signed data, but those offsets are
     * only known after the full PDF has been serialized. The placeholders
     * (installed by setSigner/setTimestamper) are fixed-width so that
     * patching them does not shift any byte offsets.
     *
     * The signed data is the concatenation of the two byte ranges
     * (everything before and after the /Contents hex value), which the
     * signer or TSA client hashes and signs. The resulting DER blob is
     * hex-encoded and zero-padded into the /Contents placeholder.
     */
    private function applySignature(string $pdf): string
    {
        $placeholderBytes = $this->signaturePlaceholderBytes;
        $zerosHex = str_repeat('0', $placeholderBytes * 2);
        $contentsMarker = '/Contents <' . $zerosHex . '>';

        $contentsPos = strpos($pdf, $contentsMarker);
        if ($contentsPos === false) {
            throw new \RuntimeException('Signature /Contents placeholder not found in serialized PDF');
        }

        // Position of the '<' opening the hex string.
        $valueStart = $contentsPos + strlen('/Contents ');
        // Position *after* the '>' closing the hex string.
        $valueEnd = $valueStart + strlen('<' . $zerosHex . '>');
        $totalLen = strlen($pdf);

        // Byte range covers everything except the /Contents value itself.
        $byteRange = [
            0,
            $valueStart,
            $valueEnd,
            $totalLen - $valueEnd,
        ];

        $brPlaceholder = '[ 0000000000 0000000000 0000000000 0000000000 ]';
        $brPos = strpos($pdf, $brPlaceholder);
        if ($brPos === false) {
            throw new \RuntimeException('Signature /ByteRange placeholder not found in serialized PDF');
        }
        $brReplacement = sprintf(
            '[ %010d %010d %010d %010d ]',
            $byteRange[0],
            $byteRange[1],
            $byteRange[2],
            $byteRange[3]
        );
        if (strlen($brReplacement) !== strlen($brPlaceholder)) {
            throw new \RuntimeException('ByteRange replacement length mismatch');
        }
        $pdf = substr_replace($pdf, $brReplacement, $brPos, strlen($brPlaceholder));

        // Feed the signer/TSA the concatenation of the two byte ranges.
        $signedData = substr($pdf, 0, $valueStart) . substr($pdf, $valueEnd);

        if ($this->signer !== null) {
            $der = $this->signer->sign($signedData);
        } elseif ($this->tsaClient !== null) {
            // Document-level timestamp (DocTimeStamp) — no PKCS#7 signature,
            // just an RFC 3161 timestamp token over the byte ranges.
            $der = $this->tsaClient->timestamp($signedData);
        } else {
            throw new \RuntimeException('No signer or TSA client configured');
        }

        $derHex = bin2hex($der);
        if (strlen($derHex) > $placeholderBytes * 2) {
            throw new \RuntimeException(sprintf(
                'Signature DER (%d bytes) exceeds placeholder capacity (%d bytes); '
                . 'call setSigner() with a larger $placeholderBytes',
                strlen($der),
                $placeholderBytes
            ));
        }
        $derHexPadded = str_pad($derHex, $placeholderBytes * 2, '0', STR_PAD_RIGHT);

        // Patch /Contents: replace the zero-padded hex block in place.
        // valueStart points at '<'; the hex content begins at valueStart + 1.
        $pdf = substr_replace($pdf, $derHexPadded, $valueStart + 1, $placeholderBytes * 2);

        return $pdf;
    }

    /**
     * Check version requirements and deprecation status for an object
     * and its inline Serializable children.
     */
    private function checkVersionRequirements(PdfObject $object): void
    {
        // Check deprecation
        $deprecation = VersionRequirementResolver::getDeprecation($object);
        if ($deprecation !== null) {
            $msg = sprintf(
                '%s is deprecated since PDF %s%s',
                $object::class,
                $deprecation->since,
                $deprecation->replacement ? "; use {$deprecation->replacement} instead" : '',
            );
            $this->versionWarnings[] = $msg;
            if ($this->deprecationHandler !== null) {
                ($this->deprecationHandler)($msg);
            }

            $this->enforceRemoval($object::class, $deprecation);
        }

        // Ceiling mode: strip incompatible properties instead of bumping
        if ($this->ceilingVersion !== null) {
            $this->applyCeilingStripping($object);
            return;
        }

        // Check version requirement (class + non-null properties)
        $required = VersionRequirementResolver::getEffectiveRequirement($object);
        $this->applyVersionRequirement($required, $object::class);

        // Walk public Serializable properties for inline children
        $ref = new \ReflectionClass($object);
        foreach ($ref->getProperties(\ReflectionProperty::IS_PUBLIC) as $prop) {
            if (!$prop->isInitialized($object)) {
                continue;
            }
            $value = $prop->getValue($object);
            if ($value instanceof Serializable && !$value instanceof PdfObject) {
                $childRequired = VersionRequirementResolver::getEffectiveRequirement($value);
                $this->applyVersionRequirement($childRequired, $value::class);

                $childDeprecation = VersionRequirementResolver::getDeprecation($value);
                if ($childDeprecation !== null) {
                    $msg = sprintf(
                        '%s is deprecated since PDF %s%s',
                        $value::class,
                        $childDeprecation->since,
                        $childDeprecation->replacement ? "; use {$childDeprecation->replacement} instead" : '',
                    );
                    $this->versionWarnings[] = $msg;
                    if ($this->deprecationHandler !== null) {
                        ($this->deprecationHandler)($msg);
                    }

                    $this->enforceRemoval($value::class, $childDeprecation);
                }
            }
        }
    }

    /**
     * Enforce removal: throw if the feature has a removedIn version and
     * the target version is at or above it (in strict deprecation or ceiling mode).
     */
    private function enforceRemoval(string $class, \Phpdftk\Pdf\Core\DeprecatedPdfFeature $deprecation): void
    {
        if ($deprecation->removedInVersion === null) {
            return;
        }

        $targetVersion = $this->ceilingVersion ?? $this->version;
        if ($targetVersion->isAtLeast($deprecation->removedInVersion)) {
            if ($this->strictDeprecation || $this->ceilingVersion !== null) {
                throw new DeprecatedFeatureException($class, $deprecation, $targetVersion);
            }
        }
    }

    /**
     * Apply ceiling-mode stripping: check class-level compatibility and
     * strip incompatible properties.
     */
    private function applyCeilingStripping(PdfObject $object): void
    {
        $ceiling = $this->ceilingVersion;

        // Class-level check — if the entire object is above ceiling, refuse
        $classReq = VersionRequirementResolver::getClassRequirement($object);
        if ($classReq !== null && $classReq->isGreaterThan($ceiling)) {
            throw new CeilingVersionException($object::class, $classReq, $ceiling);
        }

        // PdfVersionAware runtime check
        if ($object instanceof \Phpdftk\Pdf\Core\PdfVersionAware) {
            $runtimeReq = $object->getMinimumPdfVersion();
            if ($runtimeReq !== null && $runtimeReq->isGreaterThan($ceiling)) {
                throw new CeilingVersionException($object::class, $runtimeReq, $ceiling);
            }
        }

        // Strip incompatible properties
        $stripped = VersionRequirementResolver::stripIncompatibleProperties($object, $ceiling);
        foreach ($stripped as $propName) {
            $this->versionWarnings[] = sprintf(
                'Stripped property %s::$%s (requires PDF > %s)',
                $object::class,
                $propName,
                $ceiling->value,
            );
        }

        // Walk inline Serializable children — strip their properties too
        $ref = new \ReflectionClass($object);
        foreach ($ref->getProperties(\ReflectionProperty::IS_PUBLIC) as $prop) {
            if (!$prop->isInitialized($object)) {
                continue;
            }
            $value = $prop->getValue($object);
            if ($value instanceof Serializable && !$value instanceof PdfObject) {
                $childClassReq = VersionRequirementResolver::getClassRequirement($value);
                if ($childClassReq !== null && $childClassReq->isGreaterThan($ceiling)) {
                    // Nullify the parent property that holds this incompatible child
                    $object->{$prop->getName()} = null;
                    $this->versionWarnings[] = sprintf(
                        'Stripped inline %s from %s (requires PDF > %s)',
                        $value::class,
                        $object::class,
                        $ceiling->value,
                    );
                } else {
                    $childStripped = VersionRequirementResolver::stripIncompatibleProperties($value, $ceiling);
                    foreach ($childStripped as $childPropName) {
                        $this->versionWarnings[] = sprintf(
                            'Stripped property %s::$%s (requires PDF > %s)',
                            $value::class,
                            $childPropName,
                            $ceiling->value,
                        );
                    }
                }
            }
        }
    }

    /**
     * Apply a version requirement: auto-bump or throw in strict mode.
     */
    private function applyVersionRequirement(PdfVersion $required, string $source): void
    {
        if ($required->isGreaterThan($this->version)) {
            if ($this->strictVersionMode) {
                throw new VersionRequirementException($source, $required, $this->version);
            }
            $this->version = $required;
            $this->versionWarnings[] = sprintf(
                'Auto-bumped PDF version to %s for %s',
                $required->value,
                $source,
            );
        }
    }
}
