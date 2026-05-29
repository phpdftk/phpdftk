<?php

declare(strict_types=1);

namespace Phpdftk\Svg\Value;

use Phpdftk\Svg\Value\Paint\CurrentColor;
use Phpdftk\Svg\Value\Paint\None_;
use Phpdftk\Svg\Value\Paint\SolidColor;
use Phpdftk\Svg\Value\Paint\Url;

/**
 * Parsed SVG paint value (SVG 2 §13.2 grammar):
 *
 *     <paint> = none | currentColor | <color> | <url> [ none | <color> ]?
 *
 * The implementation set is closed: `Paint\None_`, `Paint\CurrentColor`,
 * `Paint\SolidColor`, `Paint\Url`. The painter pattern-matches on the
 * concrete type to choose the right PDF emit path (fill/stroke colour vs
 * gradient/pattern reference).
 *
 * Color parsing delegates to `Phpdftk\Svg\Value\Color::parse()` which in
 * turn produces a `Phpdftk\Color\ColorInterface` instance — the SVG package
 * depends on `phpdftk/color` for the typed colour model.
 */
abstract class Paint
{
    /**
     * Parse an SVG paint attribute value. Returns null on absent / empty /
     * malformed input — SVG 2's "invalid → ignored" semantics.
     */
    public static function parse(string $raw): ?self
    {
        $trimmed = trim($raw);
        if ($trimmed === '') {
            return null;
        }
        if (strcasecmp($trimmed, 'none') === 0) {
            return new None_();
        }
        if (strcasecmp($trimmed, 'currentColor') === 0) {
            return new CurrentColor();
        }
        // `url(#id) [fallback]` — the fallback is itself a paint, but
        // restricted by SVG 2 to `none | <color>`. We re-enter parse()
        // on the tail and reject anything that comes back as a Url.
        if (preg_match('/^url\(\s*#([^)\s]+)\s*\)\s*(.*)$/i', $trimmed, $m) === 1) {
            $fallback = trim($m[2]);
            $fallbackPaint = $fallback === '' ? null : self::parse($fallback);
            if ($fallbackPaint instanceof Url) {
                // url(#a) url(#b) isn't a legal SVG fallback chain.
                $fallbackPaint = null;
            }
            return new Url($m[1], $fallbackPaint);
        }
        $color = Color::parse($trimmed);
        return $color === null ? null : new SolidColor($color);
    }
}
