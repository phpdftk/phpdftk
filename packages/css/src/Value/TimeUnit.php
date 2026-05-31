<?php

declare(strict_types=1);

namespace Phpdftk\Css\Value;

/**
 * CSS Values 4 §6.6 — time units. `s` (seconds) is the
 * canonical unit; `ms` (milliseconds) converts at 1000:1.
 */
enum TimeUnit: string
{
    case S = 's';
    case Ms = 'ms';

    public function toSeconds(float $value): float
    {
        return match ($this) {
            self::S => $value,
            self::Ms => $value / 1000.0,
        };
    }
}
