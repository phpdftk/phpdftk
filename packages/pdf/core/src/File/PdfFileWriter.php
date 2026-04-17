<?php

declare(strict_types=1);

namespace ApprLabs\Pdf\Core\File;

use ApprLabs\Pdf\Core\Document\Catalog;
use ApprLabs\Pdf\Core\Document\Info;
use ApprLabs\Pdf\Core\Interactive\Signature\Pkcs7Signer;
use ApprLabs\Pdf\Core\Interactive\Signature\SignatureValue;
use ApprLabs\Pdf\Core\Security\PdfEncryptor;
use ApprLabs\Pdf\Core\Content\ContentStream;
use ApprLabs\Pdf\Core\Document\CrossReferenceStream;
use ApprLabs\Filters\FlateFilter;
use ApprLabs\Pdf\Core\PdfArray;
use ApprLabs\Pdf\Core\PdfName;
use ApprLabs\Pdf\Core\PdfObject;
use ApprLabs\Pdf\Core\PdfReference;
use ApprLabs\Pdf\Core\PdfStream;
use ApprLabs\Pdf\Core\PdfString;

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
    public const VERSION = '1.7';

    private ObjectRegistry $registry;
    private ?Catalog $catalog = null;
    private ?Info $info = null;

    private ?SignatureValue $signatureValue = null;
    private ?Pkcs7Signer $signer = null;
    private int $signaturePlaceholderBytes = 8192;
    private bool $compressStreams = true;
    private bool $useXRefStream = false;
    private ?PdfEncryptor $encryptor = null;

    public function __construct(bool $compressStreams = true, bool $useXRefStream = false)
    {
        $this->registry = new ObjectRegistry();
        $this->compressStreams = $compressStreams;
        $this->useXRefStream = $useXRefStream;
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

    /**
     * Register the document catalog and return its reference. The
     * catalog becomes the `/Root` of the emitted file.
     */
    public function setCatalog(Catalog $catalog): PdfReference
    {
        $this->catalog = $catalog;
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
     * Generate the complete PDF as a binary string.
     */
    public function generate(): string
    {
        if ($this->catalog === null) {
            throw new \RuntimeException(
                'PdfFileWriter::generate() called before setCatalog()'
            );
        }

        $xref   = new CrossReferenceTable();
        $chunks = [];

        // PDF header
        $chunks[] = '%PDF-' . self::VERSION . "\n";
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

            // Build entries: object 0 is free, then in-use entries from $xref
            $xrefStream->addFreeEntry(0, 65535);
            foreach ($xref->getEntries() as $objNum => $byteOffset) {
                $xrefStream->addInUseEntry($byteOffset);
            }

            // The xref stream itself is the next sequential object number
            $xrefStreamObjNum = $this->registry->getSize();
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

        if ($this->signer !== null && $this->signatureValue !== null) {
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

        // Feed the signer the concatenation of the two byte ranges.
        $signedData = substr($pdf, 0, $valueStart) . substr($pdf, $valueEnd);
        /** @var Pkcs7Signer $signer */
        $signer = $this->signer;
        $der = $signer->sign($signedData);

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
}
