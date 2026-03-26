<?php

declare(strict_types=1);

namespace ApprLabs\Pdf\Core\Document;

use ApprLabs\Pdf\Core\PdfDictionary;
use ApprLabs\Pdf\Core\PdfName;
use ApprLabs\Pdf\Core\PdfObject;

/**
 * PDF Collection Schema dictionary.
 *
 * Defines the schema for collection fields in a PDF portfolio.
 *
 * Example:
 *   $schema = new CollectionSchema();
 *   $schema->fields->set('FileName', $fieldDefDict);
 */
class CollectionSchema extends PdfObject
{
    public const PDF_TYPE = 'CollectionSchema';

    public PdfDictionary $fields;

    public function __construct()
    {
        $this->fields = new PdfDictionary();
    }

    public function toPdf(): string
    {
        $dict = new PdfDictionary();
        $dict->set('Type', new PdfName(self::PDF_TYPE));

        // Merge field entries from $this->fields into the dict
        foreach ($this->fields->entries as $key => $value) {
            $dict->set($key, $value);
        }

        return $dict->toPdf();
    }
}
