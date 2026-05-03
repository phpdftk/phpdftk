<?php

declare(strict_types=1);

namespace Phpdftk\Pdf\Core\Document;

use Phpdftk\Pdf\Core\PdfArray;
use Phpdftk\Pdf\Core\PdfDictionary;
use Phpdftk\Pdf\Core\PdfName;
use Phpdftk\Pdf\Core\PdfObject;
use Phpdftk\Pdf\Core\PdfReference;
use Phpdftk\Pdf\Core\PdfVersion;
use Phpdftk\Pdf\Core\RequiresPdfVersion;

/**
 * Document part node (/Type /DPart) — ISO 32000-2 §14.12.
 *
 * A node in the document-part tree. Internal nodes carry a /DParts
 * array of child node references; leaves carry a /Start page reference
 * (and optionally /End) plus their record metadata.
 */
#[RequiresPdfVersion(PdfVersion::V2_0)]
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
