<?php

declare(strict_types=1);

namespace Phpdftk\Css\Value;

final readonly class Length extends Value
{
    public function __construct(public float $value, public LengthUnit $unit) {}

    public function toCss(): string
    {
        $val = fmod($this->value, 1.0) === 0.0 ? (string) (int) $this->value : (string) $this->value;
        return $val . $this->unit->value;
    }
}
