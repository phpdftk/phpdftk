<?php

declare(strict_types=1);

namespace Phpdftk\Css\Value;

/**
 * One stop in a gradient: a colour plus an optional position. When the
 * position is null the renderer interpolates based on neighbour stops.
 */
final readonly class GradientStop
{
    public function __construct(public Color $color, public Length|Percentage|null $position) {}

    public function toCss(): string
    {
        $out = $this->color->toCss();
        if ($this->position !== null) {
            $out .= ' ' . $this->position->toCss();
        }
        return $out;
    }
}
