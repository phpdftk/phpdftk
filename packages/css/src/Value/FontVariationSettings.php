<?php

declare(strict_types=1);

namespace Phpdftk\Css\Value;

/**
 * CSS Fonts 4 §6.5 — typed `font-variation-settings` value.
 * Comma-separated list of {@see FontVariationValue} entries.
 * The shaper passes these to variable-font fvar lookups to pick
 * the right glyph variant per axis.
 *
 *   font-variation-settings: "wght" 600, "wdth" 95.5;
 */
final readonly class FontVariationSettings extends Value
{
    /**
     * @param list<FontVariationValue> $axes
     */
    public function __construct(public array $axes) {}

    public function toCss(): string
    {
        if ($this->axes === []) {
            return 'normal';
        }
        return implode(', ', array_map(
            static fn(FontVariationValue $v): string => $v->toCss(),
            $this->axes,
        ));
    }
}
