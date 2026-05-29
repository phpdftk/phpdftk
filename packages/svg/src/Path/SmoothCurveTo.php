<?php

declare(strict_types=1);

namespace Phpdftk\Svg\Path;

/**
 * `S x2 y2 x y` — smooth cubic Bézier. The first control point is the
 * reflection of the previous command's second control point; the painter
 * reconstructs it.
 */
final class SmoothCurveTo implements PathCommand
{
    public function __construct(
        public readonly bool $absolute,
        public readonly float $x2,
        public readonly float $y2,
        public readonly float $x,
        public readonly float $y,
    ) {}
}
