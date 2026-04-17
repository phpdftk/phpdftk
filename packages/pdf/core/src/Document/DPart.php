<?php

declare(strict_types=1);

namespace ApprLabs\Pdf\Core\Document;

use ApprLabs\Pdf\Core\PdfArray;
use ApprLabs\Pdf\Core\PdfDictionary;
use ApprLabs\Pdf\Core\PdfName;
use ApprLabs\Pdf\Core\PdfObject;
use ApprLabs\Pdf\Core\PdfReference;

/**
 * Document part node (/Type /DPart) — ISO 32000-2 §14.12.
 *
 * A node in the document-part tree. Internal nodes carry a /DParts
 * array of child node references; leaves carry a /Start page reference
 * (and optionally /End) plus their record metadata.
 */
class DPart extends PdfObject
{
    public const PDF_TYPE = 'DPart';

    public PdfReference $parent;                // /Parent - required
    public ?PdfArray $dParts = null;             // /DParts - child nodes
    public ?PdfReference $start = null;          // /Start
    public ?PdfReference $end = null;            // /End
    public ?PdfDictionary $dpm = null;           // /DPM - document-part metadata

    public function __construct(PdfReference $parent)
    {
        $this->parent = $parent;
    }

    public function toPdf(): string
    {
        $dict = new PdfDictionary();
        $dict->set('Type', new PdfName(self::PDF_TYPE));
        $dict->set('Parent', $this->parent);
        if ($this->dParts !== null) {
            $dict->set('DParts', $this->dParts);
        }
        if ($this->start !== null) {
            $dict->set('Start', $this->start);
        }
        if ($this->end !== null) {
            $dict->set('End', $this->end);
        }
        if ($this->dpm !== null) {
            $dict->set('DPM', $this->dpm);
        }
        return $dict->toPdf();
    }
}
