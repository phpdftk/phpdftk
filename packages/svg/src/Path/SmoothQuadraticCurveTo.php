<?php

declare(strict_types=1);

namespace Phpdftk\Svg\Path;

/**
 * `T x y` — smooth quadratic Bézier. The control point is the reflection of
 * the previous quadratic command's control point; the painter reconstructs it.
 */
final class SmoothQuadraticCurveTo implements PathCommand
{
    public function __construct(
        public readonly bool $absolute,
        public readonly float $x,
        public readonly float $y,
    ) {}
}
