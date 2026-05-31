<?php

declare(strict_types=1);

namespace Phpdftk\Css\Value;

/**
 * CSS Color 5 §5 — `light-dark(<color>, <color>)`. Selects
 * `light` when the user agent's preferred color scheme is light
 * (or when no `color-scheme` is declared), `dark` otherwise.
 *
 *   color: light-dark(black, white);
 *   background: light-dark(#fff, #111);
 *
 * The renderer picks one side at paint time based on the active
 * scheme — both branches are preserved here so re-rendering for
 * a different scheme is a value-level switch rather than a
 * re-cascade.
 */
final readonly class LightDark extends Value
{
    public function __construct(
        public Value $light,
        public Value $dark,
    ) {}

    public function toCss(): string
    {
        return 'light-dark(' . $this->light->toCss() . ', ' . $this->dark->toCss() . ')';
    }
}
