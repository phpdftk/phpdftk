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
     *                                   These are the HORIZONTAL radii
     *                                   when the two-axis `/` form is
     *                                   used.
     * @param ?list<Value> $borderRadiusVertical The VERTICAL radii of
     *                                   the `a / b` two-axis form
     *                                   (CSS Backgrounds 3 §5.5), or
     *                                   null when the author wrote a
     *                                   single list and each value
     *                                   applies to both axes. Kept
     *                                   separate rather than
     *                                   concatenated because the two
     *                                   groups are independently 1-4
     *                                   values long and a flat list
     *                                   cannot say where the split was.
     */
    public function __construct(
        public array $insets,
        public ?array $borderRadius = null,
        public ?array $borderRadiusVertical = null,
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
        if ($this->borderRadiusVertical !== null) {
            $radius .= ' / ' . implode(' ', array_map(
                static fn(Value $v): string => $v->toCss(),
                $this->borderRadiusVertical,
            ));
        }
        return 'inset(' . $insets . ' round ' . $radius . ')';
    }
}
