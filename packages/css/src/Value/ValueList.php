<?php

declare(strict_types=1);

namespace Phpdftk\Css\Value;

final readonly class ValueList extends Value
{
    /** @param list<Value> $values */
    public function __construct(public array $values, public ListSeparator $separator) {}

    public function toCss(): string
    {
        $sep = match ($this->separator) {
            ListSeparator::Space => ' ',
            ListSeparator::Comma => ', ',
            ListSeparator::Slash => ' / ',
        };
        return implode($sep, array_map(static fn(Value $v): string => $v->toCss(), $this->values));
    }
}
