<?php

declare(strict_types=1);

namespace Phpdftk\Pdf\Core\Graphics\Shading;

use Phpdftk\Pdf\Core\Graphics\ColorSpace\ColorSpace;
use Phpdftk\Pdf\Core\PdfArray;
use Phpdftk\Pdf\Core\PdfName;
use Phpdftk\Pdf\Core\PdfReference;

/**
 * Radial gradient shading (ShadingType 3) — ISO 32000-2 §8.7.4.5.4.
 */
class ShadingType3 extends Shading
{
    public PdfArray $coords;                 // /Coords [x0 y0 r0 x1 y1 r1]
    public ?PdfArray $domain = null;         // /Domain
    public PdfReference|PdfArray $function;  // /Function
    public ?PdfArray $extend = null;         // /Extend

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
        return 3;
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
