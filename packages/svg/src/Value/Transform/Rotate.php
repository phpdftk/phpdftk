<?php

declare(strict_types=1);

namespace Phpdftk\Svg\Value\Transform;

use Phpdftk\Svg\Value\TransformFunction;

/**
 * `rotate(angle)` or `rotate(angle, cx, cy)`. The angle is in degrees per
 * SVG. The 3-arg form rotates around `(cx, cy)` — equivalent to
 * `translate(cx, cy) rotate(angle) translate(-cx, -cy)`.
 */
final class Rotate implements TransformFunction
{
    public function __construct(
        public readonly float $angle,
        public readonly ?float $cx = null,
        public readonly ?float $cy = null,
    ) {}

    public function toMatrix(): array
    {
        $rad = deg2rad($this->angle);
        $cos = cos($rad);
        $sin = sin($rad);
        if ($this->cx === null && $this->cy === null) {
            return [$cos, $sin, -$sin, $cos, 0.0, 0.0];
        }
        $cx = $this->cx ?? 0.0;
        $cy = $this->cy ?? 0.0;
        // M = T(cx,cy) · R(θ) · T(-cx,-cy); pre-folded so the painter
        // doesn't have to compose three steps every paint call.
        $e = $cx - $cos * $cx + $sin * $cy;
        $f = $cy - $sin * $cx - $cos * $cy;
        return [$cos, $sin, -$sin, $cos, $e, $f];
    }
}
