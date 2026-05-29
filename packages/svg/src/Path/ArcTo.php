<?php

declare(strict_types=1);

namespace Phpdftk\Svg\Path;

/**
 * `A rx ry x-axis-rotation large-arc-flag sweep-flag x y` — elliptical arc.
 * `xAxisRotation` is in degrees. The flags are SVG path-data "flag" tokens —
 * single-digit `0` or `1` per SVG 2 §9.5.4, parsed into bools here.
 */
final class ArcTo implements PathCommand
{
    public function __construct(
        public readonly bool $absolute,
        public readonly float $rx,
        public readonly float $ry,
        public readonly float $xAxisRotation,
        public readonly bool $largeArc,
        public readonly bool $sweep,
        public readonly float $x,
        public readonly float $y,
    ) {}
}
