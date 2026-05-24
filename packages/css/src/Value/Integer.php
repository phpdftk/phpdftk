<?php

declare(strict_types=1);

namespace Phpdftk\Css\Value;

final readonly class Integer extends Value
{
    public function __construct(public int $value) {}

    public function toCss(): string
    {
        return (string) $this->value;
    }
}
