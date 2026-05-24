<?php

declare(strict_types=1);

namespace Phpdftk\Css\Value;

final readonly class CalcFunc extends CalcExpression
{
    /** @param list<CalcExpression> $args */
    public function __construct(public CalcFunction $func, public array $args) {}

    public function toCss(): string
    {
        $argStrings = array_map(static fn(CalcExpression $a): string => $a->toCss(), $this->args);
        return $this->func->value . '(' . implode(', ', $argStrings) . ')';
    }
}
