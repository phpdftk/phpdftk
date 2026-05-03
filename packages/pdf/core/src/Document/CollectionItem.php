<?php

declare(strict_types=1);

namespace Phpdftk\Pdf\Core\Document;

use Phpdftk\Pdf\Core\PdfDictionary;
use Phpdftk\Pdf\Core\PdfObject;
use Phpdftk\Pdf\Core\PdfVersion;
use Phpdftk\Pdf\Core\RequiresPdfVersion;

/**
 * PDF Collection Item dictionary.
 *
 * Holds field values for a collection entry (PDF portfolio).
 *
 * Example:
 *   $item = new CollectionItem();
 *   $item->fields->set('FileName', new PdfString('report.pdf'));
 */
#[RequiresPdfVersion(PdfVersion::V1_7)]
class CollectionItem extends PdfObject
{
    public PdfDictionary $fields;

    public function __construct()
    {
        $this->fields = new PdfDictionary();
    }

    public function toPdf(): string
    {
        return $this->fields->toPdf();
    }
}
