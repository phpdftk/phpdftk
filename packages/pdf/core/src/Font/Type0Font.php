<?php

declare(strict_types=1);

namespace Phpdftk\Pdf\Core\Font;

use Phpdftk\Pdf\Core\PdfArray;
use Phpdftk\Pdf\Core\PdfDictionary;
use Phpdftk\Pdf\Core\PdfName;
use Phpdftk\Pdf\Core\PdfObject;
use Phpdftk\Pdf\Core\PdfReference;
use Phpdftk\Pdf\Core\PdfVersion;
use Phpdftk\Pdf\Core\RequiresPdfVersion;

/**
 * Type 0 (composite) font (/Type /Font /Subtype /Type0).
 * Used for multi-byte character sets such as CJK.
 */
#[RequiresPdfVersion(PdfVersion::V1_2)]
class Type0Font extends PdfObject
{
    public const PDF_TYPE = 'Font';

    public PdfName $subtype;             // /Subtype = /Type0
    public PdfName $baseFont;            // /BaseFont
    public PdfArray $descendantFonts;    // /DescendantFonts
    public PdfName|PdfReference|null $encoding = null;   // /Encoding
    public ?PdfReference $toUnicode = null;  // /ToUnicode

    public function __construct(string $baseFontName, PdfArray $descendantFonts, PdfReference|PdfName|string|null $encoding = null)
    {
        $this->subtype = new PdfName('Type0');
        $this->baseFont = new PdfName($baseFontName);
        $this->descendantFonts = $descendantFonts;
        if ($encoding instanceof PdfReference || $encoding instanceof PdfName) {
            $this->encoding = $encoding;
        } elseif (is_string($encoding)) {
            $this->encoding = new PdfName($encoding);
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
