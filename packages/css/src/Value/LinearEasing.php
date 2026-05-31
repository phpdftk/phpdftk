<?php

declare(strict_types=1);

namespace Phpdftk\Css\Value;

/**
 * `linear(<linear-stop-list>)` per CSS Easing 2 §3.1. Defines a
 * piecewise-linear easing function as a sequence of `(output,
 * input?)` pairs. Used in `animation-timing-function`,
 * `transition-timing-function`, and `offset-rotate` interpolations.
 *
 *   linear(0, 0.5 50%, 1)          three-stop linear
 *   linear(0 0%, 1 100%)           explicit anchors
 *   linear(0, 0.25 25% 50%, 1)     range form (CSS Easing 2 §3.2)
 *
 * For the range form (`<output> <input-from>% <input-to>%`), we
 * store each instance as two LinearEasingStop entries sharing the
 * same output — equivalent to a horizontal segment of the
 * piecewise function.
 */
final readonly class LinearEasing extends Value
{
    /**
     * @param list<LinearEasingStop> $stops
     */
    public function __construct(public array $stops) {}

    public function toCss(): string
    {
        $parts = [];
        foreach ($this->stops as $stop) {
            $parts[] = $stop->toCss();
        }
        return 'linear(' . implode(', ', $parts) . ')';
    }
}
