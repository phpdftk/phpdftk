<?php

declare(strict_types=1);

namespace Phpdftk\Pdf\Core\Graphics\Shading;

use Phpdftk\Pdf\Core\Graphics\ColorSpace\ColorSpace;
use Phpdftk\Pdf\Core\PdfArray;
use Phpdftk\Pdf\Core\PdfName;
use Phpdftk\Pdf\Core\PdfNumber;

/**
 * Tensor-product patch mesh (ShadingType 7) — ISO 32000-2 §8.7.4.5.8.
 */
class ShadingType7 extends MeshShading
{
    public int $bitsPerFlag;

    public function __construct(
        ColorSpace|PdfName|PdfArray $colorSpace,
        int $bitsPerCoordinate,
        int $bitsPerComponent,
        int $bitsPerFlag,
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
        $this->bitsPerFlag = $bitsPerFlag;
    }

    public function getShadingType(): int
    {
        return 7;
    }

    public function toPdf(): string
    {
        $this->populateCommon();
        $this->dictionary->set('BitsPerFlag', new PdfNumber($this->bitsPerFlag));
        return parent::toPdf();
    }
}
