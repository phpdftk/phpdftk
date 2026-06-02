<?php

declare(strict_types=1);

namespace Phpdftk\Svg\Filter;

use Phpdftk\Svg\Element;

/**
 * SVG 2 Filter Effects §6.2 — common base for the `<fe*>` filter
 * primitives (`<feGaussianBlur>`, `<feColorMatrix>`, etc.). Each
 * primitive sits inside a `<filter>` element and consumes one or
 * two inputs (`in` / `in2`) producing one named output (`result`).
 *
 * The "primitive subregion" attributes (`x`, `y`, `width`, `height`)
 * crop the primitive's effect rectangle inside the filter region.
 * Defaults come from the parent `<filter>`'s `primitiveUnits` — for
 * `userSpaceOnUse` the defaults are the filter region; for
 * `objectBoundingBox` the defaults are 0/0/100%/100% of the
 * referencing shape's bbox.
 *
 * Standard input names (used by the `in` / `in2` attributes):
 *
 *   SourceGraphic       — the referencing element's rendering
 *   SourceAlpha         — the alpha channel of SourceGraphic
 *   BackgroundImage     — what's behind the element (gated by
 *                         `enable-background` on an ancestor)
 *   BackgroundAlpha     — the alpha channel of BackgroundImage
 *   FillPaint           — the element's resolved fill paint
 *   StrokePaint         — the element's resolved stroke paint
 *
 *   <result-name>       — a previously-emitted result name from
 *                         a sibling primitive
 */
abstract class FilterPrimitive extends Element
{
    public function x(): ?float
    {
        $v = $this->getAttribute('x');
        return $v !== null ? (float) $v : null;
    }

    public function y(): ?float
    {
        $v = $this->getAttribute('y');
        return $v !== null ? (float) $v : null;
    }

    public function width(): ?float
    {
        $v = $this->getAttribute('width');
        return $v !== null ? (float) $v : null;
    }

    public function height(): ?float
    {
        $v = $this->getAttribute('height');
        return $v !== null ? (float) $v : null;
    }

    /**
     * Input source name. `null` (= attribute absent) means the
     * primitive consumes whatever the previous sibling produced
     * (or `SourceGraphic` if it's the first child).
     */
    public function in(): ?string
    {
        return $this->getAttribute('in');
    }

    /**
     * Result name. `null` means the primitive doesn't expose a
     * named result; it can still be consumed by the next sibling
     * as the implicit input.
     */
    public function result(): ?string
    {
        return $this->getAttribute('result');
    }
}
