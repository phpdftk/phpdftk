<?php

declare(strict_types=1);

namespace Phpdftk\Pdf\Core\Document;

use Phpdftk\Pdf\Core\PdfBoolean;
use Phpdftk\Pdf\Core\PdfDictionary;
use Phpdftk\Pdf\Core\PdfName;
use Phpdftk\Pdf\Core\PdfVersion;
use Phpdftk\Pdf\Core\RequiresPdfVersion;
use Phpdftk\Pdf\Core\Serializable;

/**
 * Transparency group attributes dictionary (ISO 32000-2).
 *
 * Implements Serializable for inline use within Page or FormXObject.
 */
#[RequiresPdfVersion(PdfVersion::V1_4)]
class GroupAttributes implements Serializable
{
    public PdfName $s;                   // /S - subtype (always /Transparency for transparency groups)
    public ?PdfName $cs = null;          // /CS - color space name
    public ?PdfBoolean $i = null;        // /I - isolated
    public ?PdfBoolean $k = null;        // /K - knockout

    public function __construct(string $subtype = 'Transparency')
    {
        $this->s = new PdfName($subtype);
    }

    public function toPdf(): string
    {
        $dict = new PdfDictionary();
        $dict->set('Type', new PdfName('Group'));
        $dict->set('S', $this->s);

        if ($this->cs !== null) {
            $dict->set('CS', $this->cs);
        }
        if ($this->i !== null) {
            $dict->set('I', $this->i);
        }
        if ($this->k !== null) {
            $dict->set('K', $this->k);
        }

        return $dict->toPdf();
    }
}
