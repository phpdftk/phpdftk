<?php

declare(strict_types=1);

namespace Phpdftk\Pdf\Core\Document;

use Phpdftk\Pdf\Core\PdfDictionary;
use Phpdftk\Pdf\Core\PdfName;
use Phpdftk\Pdf\Core\PdfObject;
use Phpdftk\Pdf\Core\PdfReference;
use Phpdftk\Pdf\Core\PdfVersion;
use Phpdftk\Pdf\Core\RequiresPdfVersion;

/**
 * PDF Collection dictionary (ISO 32000-2 Table 152).
 *
 * Defines a PDF portfolio (collection of embedded files).
 *
 * Example:
 *   $collection = new Collection();
 *   $collection->view = new PdfName('D');
 *   $writer->register($collection);
 */
#[RequiresPdfVersion(PdfVersion::V1_7)]
class Collection extends PdfObject
{
    public const PDF_TYPE = 'Collection';

    public ?PdfReference $schema = null;  // /Schema
    public ?PdfName $d = null;            // /D - default document
    public ?PdfName $view = null;         // /View (D, T, H)
    public ?PdfReference $sort = null;    // /Sort

    public function toPdf(): string
    {
        $dict = new PdfDictionary();
        $dict->set('Type', new PdfName(self::PDF_TYPE));

        if ($this->schema !== null) {
            $dict->set('Schema', $this->schema);
        }
        if ($this->d !== null) {
            $dict->set('D', $this->d);
        }
        if ($this->view !== null) {
            $dict->set('View', $this->view);
        }
        if ($this->sort !== null) {
            $dict->set('Sort', $this->sort);
        }

        return $dict->toPdf();
    }
}
