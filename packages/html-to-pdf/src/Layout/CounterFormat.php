<?php

declare(strict_types=1);

namespace Phpdftk\HtmlToPdf\Layout;

/**
 * Static facade over the predefined CSS Counter Styles 3 catalogue, used by
 * list-marker painting (`<ol type="i">`, `list-style-type: lower-roman`),
 * `content: counter(n, <style>)` and `@page` `counter(page, <style>)`.
 *
 * The algorithms and the style table live in {@see CounterStyleRegistry};
 * this is the no-author-`@counter-style` entry point, so that call sites
 * which never see a stylesheet (the page-margin painter) still format
 * every predefined style correctly.
 */
final class CounterFormat
{
    private static ?CounterStyleRegistry $registry = null;

    private static function registry(): CounterStyleRegistry
    {
        return self::$registry ??= new CounterStyleRegistry();
    }

    /**
     * The counter REPRESENTATION for `$value` — symbols and negative sign,
     * with no `prefix` / `suffix`. This is what `counter()` substitutes.
     *
     * Returns the decimal string when `$style` names no known counter style,
     * the same fallback browsers apply to an unknown `list-style-type`.
     */
    public static function format(int $value, string $style): string
    {
        return self::registry()->representation($value, $style);
    }

    /**
     * The full MARKER string for `$value`: `prefix` + representation +
     * `suffix`. `suffix` defaults to `". "` but is `"、"` for the CJK styles
     * and `" "` for the bullet families, which is why markers cannot simply
     * be {@see format()} with a full stop stuck on the end.
     */
    public static function marker(int $value, string $style): string
    {
        return self::registry()->marker($value, $style);
    }

    /** Is `$style` a counter style this build knows how to format? */
    public static function isKnown(string $style): bool
    {
        return self::registry()->has($style);
    }
}
