<?php

declare(strict_types=1);

namespace Phpdftk\Css\Value;

/**
 * CSS Fonts 4 §6.4 — typed `font-feature-settings` value.
 * Comma-separated list of {@see FontFeatureValue} entries. The
 * shaper consumes the list to enable/disable individual OpenType
 * GSUB / GPOS feature tags.
 *
 *   font-feature-settings: "tnum", "liga" off, "ss01" 1;
 */
final readonly class FontFeatureSettings extends Value
{
    /**
     * @param list<FontFeatureValue> $features
     */
    public function __construct(public array $features) {}

    public function toCss(): string
    {
        if ($this->features === []) {
            return 'normal';
        }
        return implode(', ', array_map(
            static fn(FontFeatureValue $f): string => $f->toCss(),
            $this->features,
        ));
    }
}
