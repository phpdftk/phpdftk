<?php

declare(strict_types=1);

namespace Phpdftk\Css\Value;

/**
 * One stop in a {@see LinearEasing}. `output` is the eased value
 * at this position. `inputPercent` is the optional input
 * percentage anchor — null when the position should be auto-
 * distributed between the previous and next anchors per CSS
 * Easing 2 §3.1's interpolation rule.
 */
final readonly class LinearEasingStop
{
    public function __construct(
        public float $output,
        public ?float $inputPercent = null,
    ) {}

    public function toCss(): string
    {
        $out = fmod($this->output, 1.0) === 0.0
            ? (string) (int) $this->output
            : (string) $this->output;
        if ($this->inputPercent === null) {
            return $out;
        }
        $pct = fmod($this->inputPercent, 1.0) === 0.0
            ? (string) (int) $this->inputPercent
            : (string) $this->inputPercent;
        return $out . ' ' . $pct . '%';
    }
}
