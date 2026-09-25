<?php

declare(strict_types=1);

namespace Phpdftk\SvgToPdf;

use Phpdftk\Svg\Value\Paint;
use Phpdftk\Svg\Value\Paint\ContextPaint;

/**
 * SVG 2 §13.2.1 — the `fill` and `stroke` of the CONTEXT ELEMENT, which
 * `context-fill` and `context-stroke` inside a referenced subtree
 * resolve to.
 *
 * The context element is the one that pulled the subtree in: the shape
 * whose `marker-*` property referenced a `<marker>`, or a `<use>`. It
 * is captured once, at the point of reference, rather than looked up
 * later — by the time the marker's children are painted the painter is
 * deep inside the marker's own cascade and the referencing shape is no
 * longer reachable from it.
 *
 * A null channel means the context element declared no paint there,
 * which the painter treats as "nothing to paint" (§13.2.1 again: the
 * keyword resolves to `none` when there is nothing to defer to), NOT
 * as a fall-through to the black `fill` default.
 */
final class ContextElementPaint
{
    public function __construct(
        private readonly ?Paint $fill,
        private readonly ?Paint $stroke,
    ) {}

    public function of(ContextPaint $keyword): ?Paint
    {
        return $keyword->stroke ? $this->stroke : $this->fill;
    }
}
