<?php

declare(strict_types=1);

namespace Phpdftk\HtmlToPdf\Layout;

/**
 * CSS 2.1 §10.1 — the padding-box rectangle of the nearest positioned
 * ancestor. `BlockLayout` threads an instance through `LayoutContext`
 * whenever it enters a `position: relative | absolute | fixed | sticky`
 * box, and reads it back when resolving offsets for an in-flow
 * `position: absolute` / `fixed` descendant.
 *
 * Coordinates are layout-space (page-flow Y grows downward), matching
 * the rest of the layout pipeline.
 */
final readonly class PositionedAncestor
{
    public function __construct(
        /** X of the padding-box top-left corner. */
        public float $originX,
        /** Y of the padding-box top-left corner. */
        public float $originY,
        /** Padding-box width (= content width + padding-left + padding-right). */
        public float $width,
        /** Padding-box height (= content height + padding-top + padding-bottom). */
        public float $height,
    ) {}
}
