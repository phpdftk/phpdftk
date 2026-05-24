<?php

declare(strict_types=1);

namespace Phpdftk\Css\Value;

/**
 * AST node inside a calc() / min() / max() / clamp() / sin() / etc. tree.
 *
 * Three concrete subclasses:
 *  - {@see CalcLeaf}: a primitive value (Number, Length, Percentage, ...)
 *  - {@see CalcBinary}: an addition / subtraction / multiplication / division
 *    of two sub-expressions
 *  - {@see CalcFunc}: a named math function applied to one or more arguments
 *    (min, max, clamp, sin, ...)
 */
abstract readonly class CalcExpression
{
    abstract public function toCss(): string;
}
