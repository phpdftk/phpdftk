<?php

declare(strict_types=1);

namespace Phpdftk\Svg\Value\Paint;

use Phpdftk\Svg\Value\Paint;

/**
 * SVG 2 §13.2.1 — the `context-fill` and `context-stroke` paint
 * keywords.
 *
 * Both defer to the CONTEXT ELEMENT: the element that referenced the
 * subtree this paint appears in. For a `<marker>` that is the shape
 * whose `marker-*` property pulled the marker in; for a `<use>` it is
 * the `<use>` itself. The point is that one arrowhead definition can
 * take the colour of every different line it is attached to instead of
 * needing a copy per colour.
 *
 * Outside any such reference there is no context element and the paint
 * resolves to `none` — the painter's "nothing to paint" branch, not a
 * fall-through to the black default.
 */
final class ContextPaint extends Paint
{
    private function __construct(public readonly bool $stroke) {}

    public static function fill(): self
    {
        return new self(stroke: false);
    }

    public static function stroke(): self
    {
        return new self(stroke: true);
    }
}
