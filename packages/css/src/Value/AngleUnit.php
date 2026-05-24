<?php

declare(strict_types=1);

namespace Phpdftk\Css\Value;

enum AngleUnit: string
{
    case Deg = 'deg';
    case Rad = 'rad';
    case Grad = 'grad';
    case Turn = 'turn';

    /** Convert this value-in-this-unit to degrees. */
    public function toDegrees(float $value): float
    {
        return match ($this) {
            self::Deg => $value,
            self::Rad => $value * 180 / M_PI,
            self::Grad => $value * 0.9,
            self::Turn => $value * 360,
        };
    }
}
