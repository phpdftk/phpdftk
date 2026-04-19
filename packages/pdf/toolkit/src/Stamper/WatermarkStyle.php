<?php

declare(strict_types=1);

namespace ApprLabs\Pdf\Toolkit\Stamper;

/**
 * Style configuration for watermark text.
 */
final readonly class WatermarkStyle
{
    public function __construct(
        public float $fontSize = 60.0,
        public float $r = 0.8,
        public float $g = 0.8,
        public float $b = 0.8,
        public float $opacity = 0.3,
        public float $rotation = 45.0,
    ) {}
}
