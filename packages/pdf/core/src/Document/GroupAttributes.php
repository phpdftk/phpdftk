<?php

declare(strict_types=1);

namespace ApprLabs\Pdf\Core\Document;

use ApprLabs\Pdf\Core\PdfBoolean;
use ApprLabs\Pdf\Core\PdfDictionary;
use ApprLabs\Pdf\Core\PdfName;
use ApprLabs\Pdf\Core\PdfVersion;
use ApprLabs\Pdf\Core\RequiresPdfVersion;
use ApprLabs\Pdf\Core\Serializable;

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
