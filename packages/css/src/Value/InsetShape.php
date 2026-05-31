<?php

declare(strict_types=1);

namespace Phpdftk\Css\Value;

/**
 * `inset(<length-percentage>{1,4} [round <border-radius>]?)` per
 * CSS Shapes 1 §3.1. Defines a rectangular shape inset from the
 * reference box by 1-4 lengths (TRBL clockwise) with optional
 * rounded corners.
 */
final readonly class InsetShape extends BasicShape
{
    /**
     * @param list<Value>  $insets  1 to 4 Length / Percentage values
     *                              (top [right [bottom [left]]]).
     * @param ?list<Value> $borderRadius Optional rounded-corner values
     *                                   parsed as a CSS border-radius
     *                                   value list; null when the
     *                                   `round` keyword was absent.
     */
    public function __construct(
        public array $insets,
        public ?array $borderRadius = null,
    ) {}

    public function toCss(): string
    {
        $insets = implode(' ', array_map(
            static fn(Value $v): string => $v->toCss(),
            $this->insets,
        ));
        if ($this->borderRadius === null) {
            return 'inset(' . $insets . ')';
        }
        $radius = implode(' ', array_map(
            static fn(Value $v): string => $v->toCss(),
            $this->borderRadius,
        ));
        return 'inset(' . $insets . ' round ' . $radius . ')';
    }
}
