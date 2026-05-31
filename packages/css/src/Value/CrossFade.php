<?php

declare(strict_types=1);

namespace Phpdftk\Css\Value;

/**
 * CSS Images 4 §4 — `cross-fade()` blends two or more images at
 * a specified mixing percentage. The full grammar accepts a list
 * of `<percentage>? <image>` entries (the unlabeled cross-fade
 * sums to 100% over the unlabeled images).
 *
 *   background: cross-fade(url(a.png), url(b.png));
 *   background: cross-fade(25% url(a.png), 75% url(b.png));
 *   background: cross-fade(50% url(a.png), 50% url(b.png) color(srgb 0 0 0));
 *
 * Each option carries a typed Value for the image and an optional
 * percentage `0..100`. The renderer interpolates pixel data at
 * paint time once raster sources resolve.
 */
final readonly class CrossFade extends Value
{
    /**
     * @param list<CrossFadeOption> $options
     */
    public function __construct(public array $options) {}

    public function toCss(): string
    {
        $parts = array_map(static fn(CrossFadeOption $o): string => $o->toCss(), $this->options);
        return 'cross-fade(' . implode(', ', $parts) . ')';
    }
}
