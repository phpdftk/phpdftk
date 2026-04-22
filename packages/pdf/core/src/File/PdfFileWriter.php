<?php

declare(strict_types=1);

namespace ApprLabs\Pdf\Core\File;

use ApprLabs\Pdf\Core\Document\Catalog;
use ApprLabs\Pdf\Core\Document\Info;
use ApprLabs\Pdf\Core\Interactive\Signature\Pkcs7Signer;
use ApprLabs\Pdf\Core\Interactive\Signature\SignatureValue;
use ApprLabs\Pdf\Core\Interactive\Signature\TsaClient;
use ApprLabs\Pdf\Core\Security\PdfEncryptor;
use ApprLabs\Pdf\Core\Content\ContentStream;
use ApprLabs\Pdf\Core\Document\CrossReferenceStream;
use ApprLabs\Pdf\Core\Document\ObjectStream;
use ApprLabs\Filters\FlateFilter;
use ApprLabs\Pdf\Core\PdfArray;
use ApprLabs\Pdf\Core\PdfName;
use ApprLabs\Pdf\Core\PdfObject;
use ApprLabs\Pdf\Core\PdfReference;
use ApprLabs\Pdf\Core\PdfStream;
use ApprLabs\Pdf\Core\PdfString;
use ApprLabs\Pdf\Core\PdfVersion;
use ApprLabs\Pdf\Core\Serializable;

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
 * `ApprLabs\Pdf\Writer\PdfWriter`, which composes an instance of this
 * class.
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
                }
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
        if ($object instanceof \ApprLabs\Pdf\Core\PdfVersionAware) {
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
