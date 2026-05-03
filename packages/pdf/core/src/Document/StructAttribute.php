<?php

declare(strict_types=1);

namespace Phpdftk\Pdf\Core\Document;

use Phpdftk\Pdf\Core\PdfDictionary;
use Phpdftk\Pdf\Core\PdfName;
use Phpdftk\Pdf\Core\PdfVersion;
use Phpdftk\Pdf\Core\RequiresPdfVersion;
use Phpdftk\Pdf\Core\Serializable;

/**
 * Structure attribute object — ISO 32000-2 §14.7.5.
 *
 * Attribute objects carry layout, list, table, print-field, etc. properties
 * attached to structure elements via /A. The /O entry names the owning
 * attribute class (e.g. Layout, List, Table).
 *
 * Example:
 *   $attr = new StructAttribute('Layout');
 *   $attr->entries['Placement'] = new PdfName('Block');
 */
#[RequiresPdfVersion(PdfVersion::V1_3)]
class StructAttribute implements Serializable
{
    public PdfName $o;                // /O - owner (required)

    /** @var array<string, mixed> Additional attribute entries. */
    public array $entries = [];

    public function __construct(string $owner)
    {
        $this->o = new PdfName($owner);
    }

    public function toPdf(): string
    {
        $dict = new PdfDictionary();
        $dict->set('O', $this->o);
        foreach ($this->entries as $key => $value) {
            $dict->set($key, $value);
        }
        return $dict->toPdf();
    }
}
