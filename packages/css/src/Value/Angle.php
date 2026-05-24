<?php

declare(strict_types=1);

namespace Phpdftk\Css\Value;

final readonly class Angle extends Value
{
    public function __construct(public float $value, public AngleUnit $unit) {}

    public function toDegrees(): float
    {
        return $this->unit->toDegrees($this->value);
    }

    public function toCss(): string
    {
        $val = fmod($this->value, 1.0) === 0.0 ? (string) (int) $this->value : (string) $this->value;
        return $val . $this->unit->value;
    }
}
