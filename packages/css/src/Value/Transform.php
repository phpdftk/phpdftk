<?php

declare(strict_types=1);

namespace Phpdftk\Css\Value;

/**
 * A value of the `transform` property: a list of transform functions to
 * compose in source order (left-to-right is innermost-to-outermost per CSS
 * Transforms 2 §3).
 */
final readonly class Transform extends Value
{
    /** @param list<TransformFunction> $functions */
    public function __construct(public array $functions) {}

    public function toCss(): string
    {
        return implode(' ', array_map(static fn(TransformFunction $f): string => $f->toCss(), $this->functions));
    }
}
