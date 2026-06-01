<?php

declare(strict_types=1);

namespace Phpdftk\Css\Value;

/**
 * One `<axis-tag> <number>` entry inside a CSS Fonts 4 §6.5
 * `font-variation-settings` declaration. The number is a float
 * along the OpenType variation axis range (e.g. `"wght" 600.5`
 * picks a weight halfway between named 600 and 700).
 */
final readonly class FontVariationValue
{
    public function __construct(
        public string $tag,
        public float $value,
    ) {}

    public function toCss(): string
    {
        $v = fmod($this->value, 1.0) === 0.0
            ? (string) (int) $this->value
            : (string) $this->value;
        return sprintf('"%s" %s', $this->tag, $v);
    }
}
