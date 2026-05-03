<?php

declare(strict_types=1);

namespace Phpdftk\Pdf\Core\Document;

use Phpdftk\Pdf\Core\PdfDictionary;
use Phpdftk\Pdf\Core\PdfName;
use Phpdftk\Pdf\Core\PdfObject;
use Phpdftk\Pdf\Core\PdfVersion;
use Phpdftk\Pdf\Core\RequiresPdfVersion;

/**
 * PDF Collection Schema dictionary.
 *
 * Defines the schema for collection fields in a PDF portfolio.
 *
 * Example:
 *   $schema = new CollectionSchema();
 *   $schema->fields->set('FileName', $fieldDefDict);
 */
#[RequiresPdfVersion(PdfVersion::V1_7)]
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
