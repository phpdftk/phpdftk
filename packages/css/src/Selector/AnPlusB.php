<?php

declare(strict_types=1);

namespace Phpdftk\Css\Selector;

/**
 * An+B notation per CSS Syntax 3 §6 / Selectors 4 §11.1, used by
 * `:nth-child(...)` and friends.
 *
 * Indices are 1-based per spec — `:nth-child(1)` matches the first child.
 */
final readonly class AnPlusB
{
    public function __construct(public int $a, public int $b) {}

    public static function odd(): self
    {
        return new self(2, 1);
    }

    public static function even(): self
    {
        return new self(2, 0);
    }

    /**
     * Does the (1-based) index match this An+B expression? Per spec:
     * exists integer n ≥ 0 such that a·n + b = index.
     */
    public function matches(int $index): bool
    {
        if ($this->a === 0) {
            return $index === $this->b;
        }
        $diff = $index - $this->b;
        if (intdiv($diff, $this->a) * $this->a !== $diff) {
            return false;
        }
        $n = intdiv($diff, $this->a);
        return $n >= 0;
    }

    public function toString(): string
    {
        if ($this->a === 0) {
            return (string) $this->b;
        }
        $sign = $this->b >= 0 ? '+' : '-';
        $coefficient = match (true) {
            $this->a === 1 => '',
            $this->a === -1 => '-',
            default => (string) $this->a,
        };
        return $coefficient . 'n' . $sign . abs($this->b);
    }
}
