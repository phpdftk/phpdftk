<?php

declare(strict_types=1);

namespace ApprLabs\Pdf\Core\Graphics\Shading;

use ApprLabs\Pdf\Core\Graphics\ColorSpace\ColorSpace;
use ApprLabs\Pdf\Core\PdfArray;
use ApprLabs\Pdf\Core\PdfName;
use ApprLabs\Pdf\Core\PdfReference;

/**
 * Axial (linear gradient) shading (ShadingType 2) — ISO 32000-2 §8.7.4.5.3.
 */
class ShadingType2 extends Shading
{
    public PdfArray $coords;                 // /Coords [x0 y0 x1 y1]
    public ?PdfArray $domain = null;         // /Domain [t0 t1] default [0 1]
    public PdfReference|PdfArray $function;  // /Function
    public ?PdfArray $extend = null;         // /Extend [bool bool]

    public function __construct(
        ColorSpace|PdfName|PdfArray $colorSpace,
        PdfArray $coords,
        PdfReference|PdfArray $function
    ) {
        $this->colorSpace = $colorSpace;
        $this->coords = $coords;
        $this->function = $function;
    }

    public function getShadingType(): int
    {
        return 2;
    }

    public function toPdf(): string
    {
        $dict = $this->baseDictionary();
        $dict->set('Coords', $this->coords);
        if ($this->domain !== null) {
            $dict->set('Domain', $this->domain);
        }
        $dict->set('Function', $this->function);
        if ($this->extend !== null) {
            $dict->set('Extend', $this->extend);
        }
        return $dict->toPdf();
    }
}
