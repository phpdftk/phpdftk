<?php

declare(strict_types=1);

namespace Phpdftk\Pdf\Core\Graphics\Shading;

use Phpdftk\Pdf\Core\Graphics\ColorSpace\ColorSpace;
use Phpdftk\Pdf\Core\PdfArray;
use Phpdftk\Pdf\Core\PdfName;
use Phpdftk\Pdf\Core\PdfNumber;

/**
 * Coons patch mesh (ShadingType 6) — ISO 32000-2 §8.7.4.5.7.
 */
class ShadingType6 extends MeshShading
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
        return 6;
    }

    public function toPdf(): string
    {
        $this->populateCommon();
        $this->dictionary->set('BitsPerFlag', new PdfNumber($this->bitsPerFlag));
        return parent::toPdf();
    }
}
