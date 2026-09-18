<?php

declare(strict_types=1);

namespace Phpdftk\HtmlToPdf\Layout;

use Phpdftk\HtmlToPdf\Box\Box;

/**
 * One fragmented columnar run inside a multi-column container
 * (CSS Multi-column 1 §3.3, §6.2).
 *
 * A container with `column-span: all` children is carved into sequential
 * segments — columnar runs separated by full-width spanners — and EACH
 * run is its own fragmentation context with its own column height and its
 * own band count. A single `columnHeight` on {@see MultiColumnLayout}
 * cannot express that, so the container records a list of these.
 *
 * The run's `$children` were laid out as ONE TALL column starting at
 * layout-Y `$contentTop` at the container's content-left edge. The
 * painter slices that tall column into `$bandCount` bands of
 * `$columnHeight`, drawing band `i` clipped to column `i`
 * ({@see \Phpdftk\HtmlToPdf\Painter\Painter::paintColumnRuns}).
 *
 * `$bandCount` can exceed the container's `column-count`: CSS
 * Multi-column 1 §3.3 creates OVERFLOW COLUMNS when the content does not
 * fit in the specified number of columns at the fragmentainer height.
 */
final readonly class ColumnRun
{
    /** @param list<Box> $children */
    public function __construct(
        public array $children,
        public float $contentTop,
        public float $columnHeight,
        public int $bandCount,
    ) {}
}
