<?php

declare(strict_types=1);

namespace Phpdftk\Pdf\Core\Font;

use Phpdftk\Pdf\Core\PdfArray;
use Phpdftk\Pdf\Core\PdfDictionary;
use Phpdftk\Pdf\Core\PdfName;
use Phpdftk\Pdf\Core\PdfObject;

/**
 * Encoding object (/Type /Encoding).
 * Defines the mapping of character codes to glyph names.
 */
class Encoding extends PdfObject
{
    public const PDF_TYPE = 'Encoding';

    public ?PdfName $baseEncoding = null;   // /BaseEncoding
    public ?PdfArray $differences = null;   // /Differences

    public function toPdf(): string
    {
        $dict = new PdfDictionary();
        $dict->set('Type', new PdfName(self::PDF_TYPE));

        if ($this->baseEncoding !== null) {
            $dict->set('BaseEncoding', $this->baseEncoding);
        }
        if ($this->differences !== null) {
            $dict->set('Differences', $this->differences);
        }

        return $dict->toPdf();
    }
}
