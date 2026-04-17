<?php

declare(strict_types=1);

namespace ApprLabs\Pdf\Core\Font;

use ApprLabs\Pdf\Core\PdfArray;
use ApprLabs\Pdf\Core\PdfDictionary;
use ApprLabs\Pdf\Core\PdfName;
use ApprLabs\Pdf\Core\PdfNumber;
use ApprLabs\Pdf\Core\PdfObject;
use ApprLabs\Pdf\Core\PdfReference;
use ApprLabs\Pdf\Core\PdfString;

/**
 * Font Descriptor object (/Type /FontDescriptor).
 * Describes the metrics and other attributes of a font program.
 */
class FontDescriptor extends PdfObject
{
    public const PDF_TYPE = 'FontDescriptor';

    public PdfName $fontName;                    // /FontName - required
    public ?PdfString $fontFamily = null;        // /FontFamily
    public ?PdfName $fontStretch = null;         // /FontStretch
    public ?int $fontWeight = null;              // /FontWeight
    public int $flags = 0;                       // /Flags - required
    public ?PdfArray $fontBBox = null;           // /FontBBox
    public float $italicAngle = 0;               // /ItalicAngle - required
    public float $ascent = 0;                    // /Ascent
    public float $descent = 0;                   // /Descent
    public float $leading = 0;                   // /Leading
    public float $capHeight = 0;                 // /CapHeight
    public float $xHeight = 0;                   // /XHeight
    public float $stemV = 0;                     // /StemV
    public float $stemH = 0;                     // /StemH
    public float $avgWidth = 0;                  // /AvgWidth
    public float $maxWidth = 0;                  // /MaxWidth
    public float $missingWidth = 0;              // /MissingWidth
    public ?PdfReference $fontFile = null;       // /FontFile (Type1)
    public ?PdfReference $fontFile2 = null;      // /FontFile2 (TrueType)
    public ?PdfReference $fontFile3 = null;      // /FontFile3 (other)
    public ?PdfString $charSet = null;           // /CharSet
    public ?PdfDictionary $style = null;         // /Style
    public ?PdfString $lang = null;              // /Lang
    public ?PdfDictionary $fd = null;            // /FD - glyph metric overrides
    public ?PdfReference $cidSet = null;         // /CIDSet - subset CIDFont stream

    public function __construct(PdfName $fontName)
    {
        $this->fontName = $fontName;
    }

    public function toPdf(): string
    {
        $dict = new PdfDictionary();
        $dict->set('Type', new PdfName(self::PDF_TYPE));
        $dict->set('FontName', $this->fontName);
        $dict->set('Flags', new PdfNumber($this->flags));
        $dict->set('ItalicAngle', new PdfNumber($this->italicAngle));

        if ($this->fontFamily !== null) {
            $dict->set('FontFamily', $this->fontFamily);
        }
        if ($this->fontStretch !== null) {
            $dict->set('FontStretch', $this->fontStretch);
        }
        if ($this->fontWeight !== null) {
            $dict->set('FontWeight', new PdfNumber($this->fontWeight));
        }
        if ($this->fontBBox !== null) {
            $dict->set('FontBBox', $this->fontBBox);
        }
        if ($this->ascent != 0) {
            $dict->set('Ascent', new PdfNumber($this->ascent));
        }
        if ($this->descent != 0) {
            $dict->set('Descent', new PdfNumber($this->descent));
        }
        if ($this->leading != 0) {
            $dict->set('Leading', new PdfNumber($this->leading));
        }
        if ($this->capHeight != 0) {
            $dict->set('CapHeight', new PdfNumber($this->capHeight));
        }
        if ($this->xHeight != 0) {
            $dict->set('XHeight', new PdfNumber($this->xHeight));
        }
        if ($this->stemV != 0) {
            $dict->set('StemV', new PdfNumber($this->stemV));
        }
        if ($this->stemH != 0) {
            $dict->set('StemH', new PdfNumber($this->stemH));
        }
        if ($this->avgWidth != 0) {
            $dict->set('AvgWidth', new PdfNumber($this->avgWidth));
        }
        if ($this->maxWidth != 0) {
            $dict->set('MaxWidth', new PdfNumber($this->maxWidth));
        }
        if ($this->missingWidth != 0) {
            $dict->set('MissingWidth', new PdfNumber($this->missingWidth));
        }
        if ($this->fontFile !== null) {
            $dict->set('FontFile', $this->fontFile);
        }
        if ($this->fontFile2 !== null) {
            $dict->set('FontFile2', $this->fontFile2);
        }
        if ($this->fontFile3 !== null) {
            $dict->set('FontFile3', $this->fontFile3);
        }
        if ($this->charSet !== null) {
            $dict->set('CharSet', $this->charSet);
        }
        if ($this->style !== null) {
            $dict->set('Style', $this->style);
        }
        if ($this->lang !== null) {
            $dict->set('Lang', $this->lang);
        }
        if ($this->fd !== null) {
            $dict->set('FD', $this->fd);
        }
        if ($this->cidSet !== null) {
            $dict->set('CIDSet', $this->cidSet);
        }

        return $dict->toPdf();
    }
}
