<?php

declare(strict_types=1);

namespace Phpdftk\Svg\Path;

/** `Q x1 y1 x y` — quadratic Bézier with a single control point. */
final class QuadraticCurveTo implements PathCommand
{
    public function __construct(
        public readonly bool $absolute,
        public readonly float $x1,
        public readonly float $y1,
        public readonly float $x,
        public readonly float $y,
    ) {}
}
