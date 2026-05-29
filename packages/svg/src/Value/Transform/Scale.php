<?php

declare(strict_types=1);

namespace Phpdftk\Svg\Value\Transform;

use Phpdftk\Svg\Value\TransformFunction;

/**
 * `scale(sx)` or `scale(sx, sy)`. When `sy` is omitted it defaults to `sx`
 * (uniform scale) — distinct from translate's "second arg defaults to 0".
 */
final class Scale implements TransformFunction
{
    public function __construct(
        public readonly float $sx,
        public readonly ?float $sy = null,
    ) {}

    public function toMatrix(): array
    {
        $sy = $this->sy ?? $this->sx;
        return [$this->sx, 0.0, 0.0, $sy, 0.0, 0.0];
    }
}
