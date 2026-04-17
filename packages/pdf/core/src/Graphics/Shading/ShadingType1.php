<?php

declare(strict_types=1);

namespace ApprLabs\Pdf\Core\Graphics\Shading;

use ApprLabs\Pdf\Core\Graphics\ColorSpace\ColorSpace;
use ApprLabs\Pdf\Core\PdfArray;
use ApprLabs\Pdf\Core\PdfName;
use ApprLabs\Pdf\Core\PdfReference;

/**
 * Function-based shading (ShadingType 1) — ISO 32000-2 §8.7.4.5.2.
 *
 * Shades the interior of its BBox using a 2-in, N-out function.
 */
class ShadingType1 extends Shading
{
    public ?PdfArray $domain = null;         // /Domain  4-element [xmin xmax ymin ymax]
    public ?PdfArray $matrix = null;         // /Matrix
    public PdfReference|PdfArray $function;  // /Function

    public function __construct(
        ColorSpace|PdfName|PdfArray $colorSpace,
        PdfReference|PdfArray $function
    ) {
        $this->colorSpace = $colorSpace;
        $this->function = $function;
    }

    public function getShadingType(): int
    {
        return 1;
    }

    public function toPdf(): string
    {
        $dict = $this->baseDictionary();
        if ($this->domain !== null) {
            $dict->set('Domain', $this->domain);
        }
        if ($this->matrix !== null) {
            $dict->set('Matrix', $this->matrix);
        }
        $dict->set('Function', $this->function);
        return $dict->toPdf();
    }
}
