<?php

declare(strict_types=1);

namespace ApprLabs\Pdf\Core\Graphics\ColorSpace;

use ApprLabs\Pdf\Core\PdfArray;
use ApprLabs\Pdf\Core\PdfName;
use ApprLabs\Pdf\Core\PdfVersion;
use ApprLabs\Pdf\Core\RequiresPdfVersion;

/**
 * Pattern color space — ISO 32000-2 §8.6.6.2.
 *
 * Uncolored tiling patterns use [/Pattern <underlyingColorSpace>]; colored
 * tiling patterns and shading patterns use the bare name /Pattern.
 */
#[RequiresPdfVersion(PdfVersion::V1_2)]
class Pattern extends ColorSpace
{
    public ?ColorSpace $underlying;

    public function __construct(?ColorSpace $underlying = null)
    {
        $this->underlying = $underlying;
    }

    public function toPdf(): string
    {
        if ($this->underlying === null) {
            return '/Pattern';
        }
        return (new PdfArray([new PdfName('Pattern'), $this->underlying]))->toPdf();
    }
}
