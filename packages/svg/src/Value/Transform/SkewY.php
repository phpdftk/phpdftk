<?php

declare(strict_types=1);

namespace Phpdftk\Svg\Value\Transform;

use Phpdftk\Svg\Value\TransformFunction;

/**
 * `skewY(angle)` — shears along the y-axis by `tan(angle)`. Angle in degrees.
 */
final class SkewY implements TransformFunction
{
    public function __construct(public readonly float $angle) {}

    public function toMatrix(): array
    {
        return [1.0, tan(deg2rad($this->angle)), 0.0, 1.0, 0.0, 0.0];
    }
}
