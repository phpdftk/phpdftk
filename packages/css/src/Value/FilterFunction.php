<?php

declare(strict_types=1);

namespace Phpdftk\Css\Value;

/**
 * One CSS Filter Effects 1 §5 filter primitive. The {@see FilterKind}
 * tag identifies which primitive; `args` holds the parsed arguments
 * in their declared order. For most primitives the args are a
 * single numeric / percentage / length / color value; `drop-shadow`
 * has up to four.
 */
final readonly class FilterFunction extends Value
{
    /**
     * @param list<Value> $args
     */
    public function __construct(
        public FilterKind $kind,
        public array $args,
    ) {}

    public function toCss(): string
    {
        return $this->kind->value . '(' . implode(' ', array_map(
            static fn(Value $v): string => $v->toCss(),
            $this->args,
        )) . ')';
    }
}
