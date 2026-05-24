<?php

declare(strict_types=1);

namespace Phpdftk\Css\Selector;

/**
 * Selector specificity per CSS Selectors 4 §16.
 *
 * Three counters: `a` (ID selectors), `b` (class / attribute / pseudo-class
 * selectors), `c` (type selectors / pseudo-elements). Universal selector
 * doesn't contribute. Treated as a base-256 big-integer for ordering, with
 * a guard against overflow on pathological inputs.
 */
final readonly class Specificity
{
    public function __construct(
        public int $a = 0,
        public int $b = 0,
        public int $c = 0,
    ) {}

    public function add(self $other): self
    {
        return new self($this->a + $other->a, $this->b + $other->b, $this->c + $other->c);
    }

    /** Element-wise max for `:is()` / `:not()` / `:has()` inner selectors. */
    public function max(self $other): self
    {
        return $this->compare($other) >= 0 ? $this : $other;
    }

    /** Compare: -1 if $this is less specific, 0 equal, 1 more specific. */
    public function compare(self $other): int
    {
        return ($this->a <=> $other->a)
            ?: ($this->b <=> $other->b)
            ?: ($this->c <=> $other->c);
    }

    public function __toString(): string
    {
        return "({$this->a}, {$this->b}, {$this->c})";
    }
}
