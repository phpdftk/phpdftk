<?php

declare(strict_types=1);

namespace Phpdftk\Css\Value;

/**
 * One `<feature-tag> <integer-or-on-off>?` entry inside a
 * CSS Fonts 4 §6.4 `font-feature-settings` declaration.
 *
 *   "tnum" 1     → tag="tnum", value=1   (enable)
 *   "liga" off   → tag="liga", value=0
 *   "ss01"       → tag="ss01", value=1   (default on)
 */
final readonly class FontFeatureValue
{
    public function __construct(
        public string $tag,
        public int $value = 1,
    ) {}

    public function toCss(): string
    {
        return sprintf('"%s" %d', $this->tag, $this->value);
    }
}
