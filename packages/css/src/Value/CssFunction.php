<?php

declare(strict_types=1);

namespace Phpdftk\Css\Value;

/**
 * Catch-all for value-position function calls that don't have a typed
 * representation (yet). Calc, transform, gradients, etc. have their own
 * dedicated value subclasses; everything else falls here.
 */
final readonly class CssFunction extends Value
{
    /** @param list<Value> $arguments */
    public function __construct(public string $name, public array $arguments) {}

    public function toCss(): string
    {
        $args = implode(', ', array_map(static fn(Value $v): string => $v->toCss(), $this->arguments));
        return $this->name . '(' . $args . ')';
    }
}
