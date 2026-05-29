<?php

declare(strict_types=1);

namespace Phpdftk\Svg\Path;

/** `C x1 y1 x2 y2 x y` — cubic Bézier from current point to (x, y). */
final class CurveTo implements PathCommand
{
    public function __construct(
        public readonly bool $absolute,
        public readonly float $x1,
        public readonly float $y1,
        public readonly float $x2,
        public readonly float $y2,
        public readonly float $x,
        public readonly float $y,
    ) {}
}
