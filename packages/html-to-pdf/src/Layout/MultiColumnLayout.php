<?php

declare(strict_types=1);

namespace Phpdftk\HtmlToPdf\Layout;

use Phpdftk\Css\Value\Color;

/**
 * Resolved multi-column container layout — populated by
 * {@see BlockLayout::layoutMultiColumn()} when a box's cascade declares
 * `column-count` and/or `column-width` non-`auto`.
 *
 * The painter reads this to draw `column-rule` strokes between adjacent
 * columns (CSS Multi-column 1 §3). The geometry is recorded relative to
 * the container's content edge: column 0 starts at `box.geometry.x`, each
 * subsequent column at `x + i * (columnWidth + columnGap)`.
 */
final readonly class MultiColumnLayout
{
    public function __construct(
        public int $columnCount,
        public float $columnWidth,
        public float $columnGap,
        public float $ruleWidth,
        public string $ruleStyle,
        public ?Color $ruleColor,
    ) {}
}
