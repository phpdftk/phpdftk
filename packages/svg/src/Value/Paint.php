<?php

declare(strict_types=1);

namespace Phpdftk\Svg\Value;

use Phpdftk\Svg\Value\Paint\ContextPaint;
use Phpdftk\Svg\Value\Paint\CurrentColor;
use Phpdftk\Svg\Value\Paint\None_;
use Phpdftk\Svg\Value\Paint\SolidColor;
use Phpdftk\Svg\Value\Paint\Url;

/**
 * Parsed SVG paint value (SVG 2 §13.2 grammar):
 *
 *     <paint> = none | <color> | <url> [ none | <color> ]?
 *             | context-fill | context-stroke | currentColor
 *
 * The implementation set is closed: `Paint\None_`, `Paint\CurrentColor`,
 * `Paint\SolidColor`, `Paint\Url`, `Paint\ContextPaint`. The painter
 * pattern-matches on the concrete type to choose the right PDF emit
 * path (fill/stroke colour vs gradient/pattern reference).
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
        // SVG 2 §13.2.1 — defer to the element that referenced this
        // subtree (a `<marker>`'s shape, a `<use>`). Resolved by the
        // painter, which is the only place that knows the reference
        // chain.
        if (strcasecmp($trimmed, 'context-fill') === 0) {
            return ContextPaint::fill();
        }
        if (strcasecmp($trimmed, 'context-stroke') === 0) {
            return ContextPaint::stroke();
        }
        // `url(#id) [fallback]` — the fallback is itself a paint, but
        // restricted by SVG 2 to `none | <color>`. We re-enter parse()
        // on the tail and reject anything that comes back as a Url.
        if (preg_match('/^url\(([^)]*)\)\s*(.*)$/is', $trimmed, $m) === 1) {
            $reference = self::urlToken($m[1]);
            if ($reference === null || !str_starts_with($reference, '#')) {
                // Either malformed, or an EXTERNAL reference. Neither
                // is a local paint-server id, and reading one as if it
                // were would resolve it against the wrong document.
                return null;
            }
            $fallback = trim($m[2]);
            $fallbackPaint = $fallback === '' ? null : self::parse($fallback);
            if ($fallbackPaint instanceof Url) {
                // url(#a) url(#b) isn't a legal SVG fallback chain.
                $fallbackPaint = null;
            }
            return new Url(substr($reference, 1), $fallbackPaint);
        }
        $color = Color::parse($trimmed);
        return $color === null ? null : new SolidColor($color);
    }

    /**
     * The URL inside a `url(…)`, with its quotes removed.
     *
     * CSS Values 4 §4.5.1 and SVG 2 §16.2: whitespace is allowed on
     * both sides of the value, and inside a quoted form it is stripped
     * along with the quotes — `url(' #green ')` references `#green`.
     * Only a MATCHED pair of quotes is a string; a lone quote is a
     * parse error, and swallowing it would turn malformed author input
     * into a silently different reference.
     *
     * Whitespace INSIDE the value survives, deliberately. `url(' # red
     * ')` names the fragment `# red`, which matches no element, so the
     * paint falls back — exactly what the spec asks for and what
     * "helpfully" collapsing it to `#red` would break.
     */
    private static function urlToken(string $raw): ?string
    {
        $value = trim($raw);
        $quote = $value[0] ?? '';
        if ($quote === '"' || $quote === "'") {
            if (strlen($value) < 2 || !str_ends_with($value, $quote)) {
                return null;
            }
            $value = substr($value, 1, -1);
        } elseif (str_contains($value, '"') || str_contains($value, "'")) {
            // An unquoted url-token may not contain a quote character.
            return null;
        }
        $value = trim($value);
        return $value === '' ? null : $value;
    }
}
