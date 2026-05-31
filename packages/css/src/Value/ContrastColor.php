<?php

declare(strict_types=1);

namespace Phpdftk\Css\Value;

/**
 * CSS Color 7 §4 — `contrast-color(<color>)`. Returns the
 * highest-contrast color (UA picks from black / white in the
 * baseline form) against the input. Useful for `color: contrast-
 * color(background-color)` where the foreground should always
 * be legible regardless of the background's exact hue.
 *
 * Stored as the input color; the renderer picks black vs white
 * at paint time by computing relative luminance and returning
 * the one farther from the input on the 0..1 axis.
 */
final readonly class ContrastColor extends Value
{
    public function __construct(public Value $base) {}

    public function toCss(): string
    {
        return 'contrast-color(' . $this->base->toCss() . ')';
    }
}
