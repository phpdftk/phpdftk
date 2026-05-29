<?php

declare(strict_types=1);

namespace Phpdftk\Svg\Value;

/**
 * One function in an SVG 2 `transform` attribute (SVG 2 §8.4). Implementations
 * are the closed set of SVG transform functions: `matrix`, `translate`,
 * `rotate`, `scale`, `skewX`, `skewY`. Each knows how to reduce itself to a
 * 3×2 affine matrix `[a, b, c, d, e, f]` representing
 *
 *     | a  c  e |
 *     | b  d  f |
 *     | 0  0  1 |
 *
 * — the same convention used by PDF's `cm` operator and SVG's `matrix(…)`
 * function, so the painter can pass values straight through without
 * re-ordering.
 */
interface TransformFunction
{
    /**
     * Reduce this function to a single 3×2 affine matrix `[a, b, c, d, e, f]`.
     *
     * @return array{float, float, float, float, float, float}
     */
    public function toMatrix(): array;
}
