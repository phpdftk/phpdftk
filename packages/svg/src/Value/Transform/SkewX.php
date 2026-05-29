<?php

declare(strict_types=1);

namespace Phpdftk\Svg\Value\Transform;

use Phpdftk\Svg\Value\TransformFunction;

/**
 * `skewX(angle)` — shears along the x-axis by `tan(angle)`. Angle in degrees.
 */
final class SkewX implements TransformFunction
{
    public function __construct(public readonly float $angle) {}

    public function toMatrix(): array
    {
        return [1.0, 0.0, tan(deg2rad($this->angle)), 1.0, 0.0, 0.0];
    }
}
