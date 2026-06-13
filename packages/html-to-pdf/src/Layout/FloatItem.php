<?php

declare(strict_types=1);

namespace Phpdftk\HtmlToPdf\Layout;

/**
 * One active float inside a {@see FloatContext}. Stored as a value
 * object so {@see FloatContext} stays free of mutation accidents.
 */
final readonly class FloatItem
{
    public function __construct(
        /** `'left'` or `'right'` per CSS 2.1 §9.5. */
        public string $side,
        public float $left,
        public float $top,
        public float $width,
        public float $height,
        /**
         * CSS Shapes 1 §3 — when set, describes a per-Y exclusion
         * shape carved out of the float's box. The default `null`
         * means "axis-aligned rectangle of width × height" (the
         * pre-shapes behaviour). When non-null, `FloatContext`'s
         * leftEdgeAt / rightEdgeAt evaluate the shape at the line's
         * Y instead of using the bounding rect.
         *
         * `kind: 'circle'` carries center coordinates `(cx, cy)`
         * relative to the FloatItem's top-left, plus radius `r`.
         */
        public ?array $shape = null,
    ) {}
}
