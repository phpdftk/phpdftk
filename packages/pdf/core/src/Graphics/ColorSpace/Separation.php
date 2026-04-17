<?php

declare(strict_types=1);

namespace ApprLabs\Pdf\Core\Graphics\ColorSpace;

use ApprLabs\Pdf\Core\PdfArray;
use ApprLabs\Pdf\Core\PdfName;
use ApprLabs\Pdf\Core\PdfReference;

/**
 * Separation color space — ISO 32000-2 §8.6.6.4.
 *
 * Serialized as [/Separation <name> <alternateSpace> <tintTransform>].
 * Used for spot colors; the tint transform maps the single tint value
 * into the alternate space.
 */
class Separation extends ColorSpace
{
    public PdfName $name;                                 // colorant name
    public ColorSpace|PdfName|PdfArray $alternateSpace;
    public PdfReference $tintTransform;                   // function reference

    public function __construct(
        PdfName $name,
        ColorSpace|PdfName|PdfArray $alternateSpace,
        PdfReference $tintTransform
    ) {
        $this->name = $name;
        $this->alternateSpace = $alternateSpace;
        $this->tintTransform = $tintTransform;
    }

    public function toPdf(): string
    {
        return (new PdfArray([
            new PdfName('Separation'),
            $this->name,
            $this->alternateSpace,
            $this->tintTransform,
        ]))->toPdf();
    }
}
