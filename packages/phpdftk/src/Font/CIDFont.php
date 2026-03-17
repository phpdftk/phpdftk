<?php

declare(strict_types=1);

namespace Phpdftk\Font;

use Phpdftk\Core\PdfArray;
use Phpdftk\Core\PdfDictionary;
use Phpdftk\Core\PdfName;
use Phpdftk\Core\PdfNumber;
use Phpdftk\Core\PdfObject;
use Phpdftk\Core\PdfReference;

/**
 * CIDFont dictionary (/Type /Font /Subtype /CIDFontType0 or /CIDFontType2).
 * Used as the descendant of a Type0 composite font.
 */
class CIDFont extends PdfObject
{
    public const PDF_TYPE = 'Font';

    public PdfName $subtype;                      // /Subtype /CIDFontType0 or /CIDFontType2
    public PdfName $baseFont;                     // /BaseFont
    public PdfDictionary $cidSystemInfo;          // /CIDSystemInfo
    public ?PdfReference $fontDescriptor = null;  // /FontDescriptor
    public ?int $dw = null;                       // /DW default width
    public ?PdfArray $w = null;                   // /W widths
    public ?PdfArray $dw2 = null;                 // /DW2
    public ?PdfArray $w2 = null;                  // /W2

    public function __construct(
        string $subtype,
        string $baseFontName,
        PdfDictionary $cidSystemInfo
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
