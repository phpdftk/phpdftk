<?php

declare(strict_types=1);

namespace Phpdftk\Pdf\Core\Font;

use Phpdftk\Pdf\Core\PdfArray;
use Phpdftk\Pdf\Core\PdfDictionary;
use Phpdftk\Pdf\Core\PdfName;

/**
 * CIDFontType2 — CID-keyed font backed by a TrueType font program
 * (ISO 32000-2 §9.7.4). Used as the descendant of a /Type0 font when the
 * glyphs come from a TrueType or OpenType TTF font program.
 *
 * Adds the /CIDToGIDMap entry, which may be either the name /Identity or
 * a reference/stream supplying the CID → GID mapping.
 */
class CIDFontType2Font extends CIDFont
{
    public PdfName|\Phpdftk\Pdf\Core\PdfReference|null $cidToGidMap = null; // /CIDToGIDMap

    public function __construct(string $baseFontName, CIDSystemInfo $cidSystemInfo)
    {
        parent::__construct('CIDFontType2', $baseFontName, $cidSystemInfo);
        $this->cidToGidMap = new PdfName('Identity');
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
            $dict->set('DW', new \Phpdftk\Pdf\Core\PdfNumber($this->dw));
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
        if ($this->cidToGidMap !== null) {
            $dict->set('CIDToGIDMap', $this->cidToGidMap);
        }

        return $dict->toPdf();
    }
}
