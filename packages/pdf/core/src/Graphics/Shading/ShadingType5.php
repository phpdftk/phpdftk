<?php

declare(strict_types=1);

namespace Phpdftk\Pdf\Core\Graphics\Shading;

use Phpdftk\Pdf\Core\Graphics\ColorSpace\ColorSpace;
use Phpdftk\Pdf\Core\PdfArray;
use Phpdftk\Pdf\Core\PdfName;
use Phpdftk\Pdf\Core\PdfNumber;

/**
 * Lattice-form Gouraud-shaded triangle mesh (ShadingType 5) —
 * ISO 32000-2 §8.7.4.5.6.
 */
class ShadingType5 extends MeshShading
{
    public int $verticesPerRow;   // /VerticesPerRow  (required, >= 2)

    public function __construct(
        ColorSpace|PdfName|PdfArray $colorSpace,
        int $bitsPerCoordinate,
        int $bitsPerComponent,
        int $verticesPerRow,
        PdfArray $decode,
        string $meshData = ''
    ) {
        parent::__construct(
            $colorSpace,
            $bitsPerCoordinate,
            $bitsPerComponent,
            $decode,
            $meshData
        );
        $this->verticesPerRow = $verticesPerRow;
    }

    public function getShadingType(): int
    {
        return 5;
    }

    public function toPdf(): string
    {
        $this->populateCommon();
        $this->dictionary->set('VerticesPerRow', new PdfNumber($this->verticesPerRow));
        return parent::toPdf();
    }
}
