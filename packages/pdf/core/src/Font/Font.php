<?php

declare(strict_types=1);

namespace Phpdftk\Pdf\Core\Font;

use Phpdftk\Pdf\Core\PdfArray;
use Phpdftk\Pdf\Core\PdfDictionary;
use Phpdftk\Pdf\Core\PdfName;
use Phpdftk\Pdf\Core\PdfObject;
use Phpdftk\Pdf\Core\PdfReference;

/**
 * Base PDF Font object (/Type /Font).
 */
class Font extends PdfObject
{
    public const PDF_TYPE = 'Font';

    public ?PdfName $subtype = null;             // /Subtype
    public ?PdfName $baseFont = null;            // /BaseFont
    public ?int $firstChar = null;               // /FirstChar
    public ?int $lastChar = null;                // /LastChar
    public ?PdfArray $widths = null;             // /Widths
    public ?PdfReference $fontDescriptor = null; // /FontDescriptor
    public PdfReference|PdfName|null $encoding = null; // /Encoding
    public ?PdfReference $toUnicode = null;      // /ToUnicode

    public function toPdf(): string
    {
        $dict = new PdfDictionary();
        $dict->set('Type', new PdfName(self::PDF_TYPE));

        if ($this->subtype !== null) {
            $dict->set('Subtype', $this->subtype);
        }
        if ($this->baseFont !== null) {
            $dict->set('BaseFont', $this->baseFont);
        }
        if ($this->firstChar !== null) {
            $dict->set('FirstChar', $this->firstChar);
        }
        if ($this->lastChar !== null) {
            $dict->set('LastChar', $this->lastChar);
        }
        if ($this->widths !== null) {
            $dict->set('Widths', $this->widths);
        }
        if ($this->fontDescriptor !== null) {
            $dict->set('FontDescriptor', $this->fontDescriptor);
        }
        if ($this->encoding !== null) {
            $dict->set('Encoding', $this->encoding);
        }
        if ($this->toUnicode !== null) {
            $dict->set('ToUnicode', $this->toUnicode);
        }

        return $dict->toPdf();
    }
}
