<?php

declare(strict_types=1);

namespace Phpdftk\Pdf\Core\Graphics\ColorSpace;

use Phpdftk\Pdf\Core\PdfArray;
use Phpdftk\Pdf\Core\PdfName;
use Phpdftk\Pdf\Core\PdfReference;
use Phpdftk\Pdf\Core\PdfString;

/**
 * Indexed color space — ISO 32000-2 §8.6.6.3.
 *
 * Serialized as [/Indexed base hival lookup], where `lookup` is either a
 * byte string of (hival+1) * nColorants bytes or a reference to a stream
 * of the same bytes.
 */
class Indexed extends ColorSpace
{
    public ColorSpace|PdfName|PdfArray $base;
    public int $hival;
    public PdfString|PdfReference $lookup;

    public function __construct(
        ColorSpace|PdfName|PdfArray $base,
        int $hival,
        PdfString|PdfReference $lookup,
    ) {
        $this->base = $base;
        $this->hival = $hival;
        $this->lookup = $lookup;
    }

    public function toPdf(): string
    {
        return (new PdfArray([
            new PdfName('Indexed'),
            $this->base,
            $this->hival,
            $this->lookup,
        ]))->toPdf();
    }
}
