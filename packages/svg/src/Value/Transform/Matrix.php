<?php

declare(strict_types=1);

namespace Phpdftk\Svg\Value\Transform;

use Phpdftk\Svg\Value\TransformFunction;

/**
 * `matrix(a, b, c, d, e, f)` — the most general form. Coordinates are SVG's
 * column-major affine convention (e/f are the translation column).
 */
final class Matrix implements TransformFunction
{
    public function __construct(
        public readonly float $a,
        public readonly float $b,
        public readonly float $c,
        public readonly float $d,
        public readonly float $e,
        public readonly float $f,
    ) {}

    public function toMatrix(): array
    {
        return [$this->a, $this->b, $this->c, $this->d, $this->e, $this->f];
    }
}
