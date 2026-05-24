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
    ) {}
}
