<?php

declare(strict_types=1);

namespace ApprLabs\Pdf\Toolkit\Stamper;

/**
 * Style configuration for text stamps.
 */
final readonly class StampStyle
{
    public function __construct(
        public float $fontSize = 12.0,
        public string $fontName = 'Helvetica',
        public float $r = 0.0,
        public float $g = 0.0,
        public float $b = 0.0,
        public float $opacity = 1.0,
        public float $rotation = 0.0,
    ) {}
}
