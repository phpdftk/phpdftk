<?php

declare(strict_types=1);

namespace ApprLabs\Pdf\Core\Font;

use ApprLabs\Pdf\Core\PdfArray;
use ApprLabs\Pdf\Core\PdfDictionary;
use ApprLabs\Pdf\Core\PdfName;
use ApprLabs\Pdf\Core\PdfObject;
use ApprLabs\Pdf\Core\PdfReference;

/**
 * Type 0 (composite) font (/Type /Font /Subtype /Type0).
 * Used for multi-byte character sets such as CJK.
 */
class Type0Font extends PdfObject
{
    public const PDF_TYPE = 'Font';

    public PdfName $subtype;             // /Subtype = /Type0
    public PdfName $baseFont;            // /BaseFont
    public PdfArray $descendantFonts;    // /DescendantFonts
    public ?PdfReference $encoding = null;   // /Encoding
    public ?PdfReference $toUnicode = null;  // /ToUnicode

    public function __construct(string $baseFontName, PdfArray $descendantFonts, PdfReference|string|null $encoding = null)
    {
        $this->subtype = new PdfName('Type0');
        $this->baseFont = new PdfName($baseFontName);
        $this->descendantFonts = $descendantFonts;
        if ($encoding instanceof PdfReference) {
            $this->encoding = $encoding;
        }
    }

    public function toPdf(): string
    {
        $dict = new PdfDictionary();
        $dict->set('Type', new PdfName(self::PDF_TYPE));
        $dict->set('Subtype', $this->subtype);
        $dict->set('BaseFont', $this->baseFont);
        $dict->set('DescendantFonts', $this->descendantFonts);

        if ($this->encoding !== null) {
            $dict->set('Encoding', $this->encoding);
        }
        if ($this->toUnicode !== null) {
            $dict->set('ToUnicode', $this->toUnicode);
        }

        return $dict->toPdf();
    }
}
