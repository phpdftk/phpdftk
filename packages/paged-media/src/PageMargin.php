<?php

declare(strict_types=1);

namespace Phpdftk\PagedMedia;

/**
 * The four edges of the page margin area in PDF points.
 *
 * CSS Paged Media 3 §6.2 declares page margins via the `margin`
 * shorthand inside an `@page` rule. The shorthand follows CSS
 * Box 3 §4 conventions (1 / 2 / 3 / 4 values → top / right /
 * bottom / left).
 *
 * Immutable; build via `new PageMargin(...)` or the convenience
 * factories `uniform()` (single value), `horizontal()` /
 * `vertical()` (two values).
 */
final readonly class PageMargin
{
    public function __construct(
        public float $top,
        public float $right,
        public float $bottom,
        public float $left,
    ) {
        if ($top < 0 || $right < 0 || $bottom < 0 || $left < 0) {
            throw new \InvalidArgumentException(sprintf(
                'PageMargin values must be non-negative; got (%g, %g, %g, %g)',
                $top,
                $right,
                $bottom,
                $left,
            ));
        }
    }

    /**
     * `margin: 72pt` → 72-point margin on every edge.
     */
    public static function uniform(float $value): self
    {
        return new self($value, $value, $value, $value);
    }

    /**
     * `margin: 72pt 36pt` → vertical 72 / horizontal 36.
     */
    public static function symmetric(float $vertical, float $horizontal): self
    {
        return new self($vertical, $horizontal, $vertical, $horizontal);
    }
}
