<?php

declare(strict_types=1);

namespace Phpdftk\Css\Value;

/**
 * CSS Values 4 §6.6 — a time value. Used by transitions /
 * animations for durations and delays.
 */
final readonly class Time extends Value
{
    public function __construct(public float $value, public TimeUnit $unit) {}

    public function toSeconds(): float
    {
        return $this->unit->toSeconds($this->value);
    }

    public function toCss(): string
    {
        $v = fmod($this->value, 1.0) === 0.0 ? (string) (int) $this->value : (string) $this->value;
        return $v . $this->unit->value;
    }
}
