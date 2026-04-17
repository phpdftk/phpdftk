<?php

declare(strict_types=1);

namespace ApprLabs\Pdf\Core\ThreeD;

use ApprLabs\Pdf\Core\PdfArray;
use ApprLabs\Pdf\Core\PdfDictionary;
use ApprLabs\Pdf\Core\PdfName;
use ApprLabs\Pdf\Core\PdfObject;
use ApprLabs\Pdf\Core\PdfReference;
use ApprLabs\Pdf\Core\PdfString;

/**
 * 3D measurement dictionary (/Type /3DMeasure) —
 * ISO 32000-2 §13.6.4.6.
 *
 * Describes a linear, radial, angular, perpendicular, or
 * point-coordinate measurement annotation inside a 3D view. The
 * /Subtype selects one of six flavors: 3DC (basic), LD (linear),
 * PD3 (perpendicular), RD3 (radial), AD3 (angular), 3DM (point).
 *
 * Represented here as a single class with `$subtype`; callers choose
 * the subtype and populate the fields appropriate to it.
 */
class ThreeDMeasure extends PdfObject
{
    public const PDF_TYPE = '3DMeasure';

    public PdfName $subtype;                       // /Subtype
    public ?PdfArray $anchors = null;              // /A - anchors
    public ?PdfReference $target = null;           // /TRL
    public ?PdfString $text = null;                // /V - value string
    public ?PdfDictionary $textProperties = null;  // /TP

    public function __construct(string $subtype)
    {
        $this->subtype = new PdfName($subtype);
    }

    public function toPdf(): string
    {
        $dict = new PdfDictionary();
        $dict->set('Type', new PdfName(self::PDF_TYPE));
        $dict->set('Subtype', $this->subtype);
        if ($this->anchors !== null) {
            $dict->set('A', $this->anchors);
        }
        if ($this->target !== null) {
            $dict->set('TRL', $this->target);
        }
        if ($this->text !== null) {
            $dict->set('V', $this->text);
        }
        if ($this->textProperties !== null) {
            $dict->set('TP', $this->textProperties);
        }
        return $dict->toPdf();
    }
}
