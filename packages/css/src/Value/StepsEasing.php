<?php

declare(strict_types=1);

namespace Phpdftk\Css\Value;

/**
 * `steps(<int>, <jump-term>?)` per CSS Easing 1 §3.5. Discrete
 * easing function that snaps to the nearest of N evenly-spaced
 * stops. The optional {@see StepsJumpTerm} selects which stops
 * "jump" (move from input to output instantaneously).
 */
final readonly class StepsEasing extends Value
{
    public function __construct(
        public int $count,
        public StepsJumpTerm $jumpTerm = StepsJumpTerm::End,
    ) {}

    public function toCss(): string
    {
        if ($this->jumpTerm === StepsJumpTerm::End) {
            return 'steps(' . $this->count . ')';
        }
        return 'steps(' . $this->count . ', ' . $this->jumpTerm->value . ')';
    }
}
