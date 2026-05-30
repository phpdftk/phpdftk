<?php

declare(strict_types=1);

namespace Phpdftk\Css\Value;

/**
 * The `filter:` value as a typed list of CSS Filter Effects 1 §5
 * primitives. The painter dispatches per
 * {@see FilterFunction::$kind} rather than re-parsing the function
 * name from a generic {@see CssFunction}.
 *
 * `filter: none` (the initial value) is represented by
 * `Keyword('none')` and not by an empty Filter — the cascade keeps
 * the keyword and the painter no-ops without walking a list.
 */
final readonly class Filter extends Value
{
    /**
     * @param list<FilterFunction> $functions
     */
    public function __construct(public array $functions) {}

    public function toCss(): string
    {
        return implode(' ', array_map(
            static fn(FilterFunction $f): string => $f->toCss(),
            $this->functions,
        ));
    }
}
