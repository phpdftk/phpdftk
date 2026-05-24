<?php

declare(strict_types=1);

namespace Phpdftk\Css\Value;

/**
 * Top-level math value — `calc(...)`, `min(...)`, `max(...)`, etc. Wraps a
 * {@see CalcExpression} tree. The cascade evaluates this lazily; here we
 * just round-trip the parsed structure.
 */
final readonly class Calc extends Value
{
    public function __construct(public CalcExpression $expression) {}

    public function toCss(): string
    {
        // The outermost calc() wrapper is implicit when expression already
        // serialises with parens; otherwise wrap.
        $inner = $this->expression->toCss();
        if ($this->expression instanceof CalcFunc) {
            return $inner;
        }
        return 'calc' . $inner;
    }
}
