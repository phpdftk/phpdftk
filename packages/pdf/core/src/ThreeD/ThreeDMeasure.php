<?php

declare(strict_types=1);

namespace Phpdftk\Pdf\Core\ThreeD;

use Phpdftk\Pdf\Core\PdfArray;
use Phpdftk\Pdf\Core\PdfDictionary;
use Phpdftk\Pdf\Core\PdfName;
use Phpdftk\Pdf\Core\PdfObject;
use Phpdftk\Pdf\Core\PdfReference;
use Phpdftk\Pdf\Core\PdfString;
use Phpdftk\Pdf\Core\PdfVersion;
use Phpdftk\Pdf\Core\RequiresPdfVersion;

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
#[RequiresPdfVersion(PdfVersion::V1_6)]
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
