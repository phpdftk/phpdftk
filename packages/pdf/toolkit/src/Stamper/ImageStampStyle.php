<?php

declare(strict_types=1);

namespace Phpdftk\Pdf\Toolkit\Stamper;

/**
 * Style configuration for image and PDF stamps.
 *
 * Dimensions are in PDF points (1/72 inch). If only one of width/height
 * is set, the other is calculated from the source aspect ratio.
 * If neither is set, the source dimensions are used at 72 DPI.
 */
final readonly class ImageStampStyle
{
    public function __construct(
        public ?float $width = null,
        public ?float $height = null,
        public float $opacity = 1.0,
    ) {}
}
