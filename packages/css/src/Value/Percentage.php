<?php

declare(strict_types=1);

namespace Phpdftk\Css\Value;

final readonly class Percentage extends Value
{
    public function __construct(public float $value) {}

    public function toCss(): string
    {
        if (fmod($this->value, 1.0) === 0.0) {
            return ((int) $this->value) . '%';
        }
        return $this->value . '%';
    }
}
