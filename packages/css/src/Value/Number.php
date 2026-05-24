<?php

declare(strict_types=1);

namespace Phpdftk\Css\Value;

final readonly class Number extends Value
{
    public function __construct(public float $value) {}

    public function toCss(): string
    {
        // Preserve integer-like display when fractional part is zero.
        if (fmod($this->value, 1.0) === 0.0) {
            return (string) (int) $this->value;
        }
        return (string) $this->value;
    }
}
