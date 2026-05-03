<?php

declare(strict_types=1);

namespace Phpdftk\Pdf\Core\Document;

use Phpdftk\Pdf\Core\PdfArray;
use Phpdftk\Pdf\Core\PdfDictionary;
use Phpdftk\Pdf\Core\PdfName;
use Phpdftk\Pdf\Core\PdfNumber;
use Phpdftk\Pdf\Core\PdfReference;
use Phpdftk\Pdf\Core\PdfStream;
use Phpdftk\Pdf\Core\PdfVersion;
use Phpdftk\Pdf\Core\RequiresPdfVersion;

/**
 * Cross-reference stream (/Type /XRef) — ISO 32000-2 §7.5.8.
 *
 * A PDF 1.5+ alternative to the classic xref table. Holds xref entries as
 * binary data inside a stream object whose dictionary also carries the
 * trailer entries (Size, Root, Info, ID, Prev, Encrypt).
 *
 * Stream entries are variable-width records described by the /W array:
 *   [type, field2, field3]
 * Three standard entry types:
 *   0 = free   (next free obj num, generation to reuse)
 *   1 = in use (byte offset, generation)
 *   2 = compressed (obj num of containing ObjStm, index within stream)
 */
#[RequiresPdfVersion(PdfVersion::V1_5)]
class CrossReferenceStream extends PdfStream
{
    public const PDF_TYPE = 'XRef';

    public int $size = 0;                       // /Size  (required)
    public ?PdfArray $index = null;             // /Index (optional)
    public ?int $prev = null;                   // /Prev  (optional)
    public ?PdfArray $w = null;                 // /W     (required)
    public ?PdfReference $root = null;          // /Root
    public ?PdfReference $info = null;          // /Info
    public ?PdfArray $id = null;                // /ID
    public ?PdfReference $encrypt = null;       // /Encrypt

    /** @var int[] Raw entries: [type, field2, field3, ...] triples */
    private array $rawEntries = [];

    /** @var int[] Field widths in bytes [w0, w1, w2] */
    private array $fieldWidths = [1, 4, 2];

    public function __construct()
    {
        parent::__construct(new PdfDictionary(), '');
    }

    /**
     * Append a type-1 (in-use) entry.
     */
    public function addInUseEntry(int $offset, int $generation = 0): void
    {
        $this->rawEntries[] = [1, $offset, $generation];
    }

    /**
     * Append a type-0 (free) entry.
     */
    public function addFreeEntry(int $nextFree = 0, int $generation = 65535): void
    {
        $this->rawEntries[] = [0, $nextFree, $generation];
    }

    /**
     * Append a type-2 (compressed) entry referring to an object stream.
     */
    public function addCompressedEntry(int $objStmObjNum, int $indexInStream): void
    {
        $this->rawEntries[] = [2, $objStmObjNum, $indexInStream];
    }

    /**
     * Auto-detect optimal /W field widths based on the maximum values
     * in the recorded entries, then pack all entries into stream data.
     *
     * Called automatically by toPdf(); can also be called manually
     * to inspect the packed data before serialization.
     */
    public function packAllEntries(): void
    {
        if (empty($this->rawEntries)) {
            return;
        }

        // Find max values for each field
        $maxField2 = 0;
        $maxField3 = 0;
        foreach ($this->rawEntries as [$type, $f2, $f3]) {
            if ($f2 > $maxField2) {
                $maxField2 = $f2;
            }
            if ($f3 > $maxField3) {
                $maxField3 = $f3;
            }
        }

        // Determine minimum bytes needed for each field
        $this->fieldWidths = [
            1, // type is always 1 byte
            self::bytesNeeded($maxField2),
            self::bytesNeeded($maxField3),
        ];

        $this->w = new PdfArray([
            new PdfNumber($this->fieldWidths[0]),
            new PdfNumber($this->fieldWidths[1]),
            new PdfNumber($this->fieldWidths[2]),
        ]);

        // Pack all entries with the computed widths
        $this->data = '';
        foreach ($this->rawEntries as [$type, $f2, $f3]) {
            $this->data .= self::packField($type, $this->fieldWidths[0]);
            $this->data .= self::packField($f2, $this->fieldWidths[1]);
            $this->data .= self::packField($f3, $this->fieldWidths[2]);
        }
    }

    /**
     * Determine minimum bytes to represent a value.
     */
    private static function bytesNeeded(int $value): int
    {
        if ($value <= 0xFF) {
            return 1;
        }
        if ($value <= 0xFFFF) {
            return 2;
        }
        if ($value <= 0xFFFFFF) {
            return 3;
        }
        return 4;
    }

    /**
     * Pack an integer into a big-endian byte string of the given width.
     */
    private static function packField(int $value, int $width): string
    {
        $result = '';
        for ($i = $width - 1; $i >= 0; $i--) {
            $result .= chr(($value >> ($i * 8)) & 0xFF);
        }
        return $result;
    }

    public function toPdf(): string
    {
        // Pack entries with auto-detected optimal field widths
        $this->packAllEntries();

        $this->dictionary = new PdfDictionary();
        $this->dictionary->set('Type', new PdfName(self::PDF_TYPE));
        $this->dictionary->set('Size', new PdfNumber($this->size));
        if ($this->index !== null) {
            $this->dictionary->set('Index', $this->index);
        }
        if ($this->prev !== null) {
            $this->dictionary->set('Prev', new PdfNumber($this->prev));
        }
        if ($this->w !== null) {
            $this->dictionary->set('W', $this->w);
        }
        if ($this->root !== null) {
            $this->dictionary->set('Root', $this->root);
        }
        if ($this->info !== null) {
            $this->dictionary->set('Info', $this->info);
        }
        if ($this->id !== null) {
            $this->dictionary->set('ID', $this->id);
        }
        if ($this->encrypt !== null) {
            $this->dictionary->set('Encrypt', $this->encrypt);
        }

        return parent::toPdf();
    }
}