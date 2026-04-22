<?php

declare(strict_types=1);

namespace ApprLabs\Pdf\Core\Document;

use ApprLabs\Pdf\Core\PdfDictionary;
use ApprLabs\Pdf\Core\PdfObject;
use ApprLabs\Pdf\Core\PdfVersion;
use ApprLabs\Pdf\Core\RequiresPdfVersion;

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
