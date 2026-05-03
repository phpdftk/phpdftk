<?php

declare(strict_types=1);

namespace Phpdftk\Pdf\Core\Font;

use Phpdftk\Pdf\Core\PdfArray;
use Phpdftk\Pdf\Core\PdfDictionary;
use Phpdftk\Pdf\Core\PdfName;
use Phpdftk\Pdf\Core\PdfNumber;
use Phpdftk\Pdf\Core\PdfObject;
use Phpdftk\Pdf\Core\PdfReference;
use Phpdftk\Pdf\Core\PdfVersion;
use Phpdftk\Pdf\Core\RequiresPdfVersion;

/**
 * CIDFont dictionary (/Type /Font /Subtype /CIDFontType0 or /CIDFontType2).
 * Used as the descendant of a Type0 composite font.
 */
#[RequiresPdfVersion(PdfVersion::V1_2)]
class CIDFont extends PdfObject
{
    public const PDF_TYPE = 'Font';

    public PdfName $subtype;                      // /Subtype /CIDFontType0 or /CIDFontType2
    public PdfName $baseFont;                     // /BaseFont
    public CIDSystemInfo $cidSystemInfo;          // /CIDSystemInfo
    public ?PdfReference $fontDescriptor = null;  // /FontDescriptor
    public ?int $dw = null;                       // /DW default width
    public ?PdfArray $w = null;                   // /W widths
    public ?PdfArray $dw2 = null;                 // /DW2
    public ?PdfArray $w2 = null;                  // /W2

    public function __construct(
        string $subtype,
        string $baseFontName,
        CIDSystemInfo $cidSystemInfo
    ) {
        $this->subtype = new PdfName($subtype);
        $this->baseFont = new PdfName($baseFontName);
        $this->cidSystemInfo = $cidSystemInfo;
    }

    public function toPdf(): string
    {
        $dict = new PdfDictionary();
        $dict->set('Type', new PdfName(self::PDF_TYPE));
        $dict->set('Subtype', $this->subtype);
        $dict->set('BaseFont', $this->baseFont);
        $dict->set('CIDSystemInfo', $this->cidSystemInfo);

        if ($this->fontDescriptor !== null) {
            $dict->set('FontDescriptor', $this->fontDescriptor);
        }
        if ($this->dw !== null) {
            $dict->set('DW', new PdfNumber($this->dw));
        }
        if ($this->w !== null) {
            $dict->set('W', $this->w);
        }
        if ($this->dw2 !== null) {
            $dict->set('DW2', $this->dw2);
        }
        if ($this->w2 !== null) {
            $dict->set('W2', $this->w2);
        }

        return $dict->toPdf();
    }
}
