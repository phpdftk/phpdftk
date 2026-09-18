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
        /**
         * CSS Multi-column 1 §3.3 — `column-fill: auto` fragmentation. When
         * true the container's content was laid out in a SINGLE tall column
         * (children keep their column-0 positions) and the painter must
         * SLICE it into `columnCount` bands of `columnHeight`, translating
         * band `i` to column `i`. `contentTop` is the layout-Y of the top of
         * the tall column (band 0's origin). False (the default) = the
         * classic balance path, where children were already moved to their
         * columns and paint once.
         */
        public bool $fragmented = false,
        public float $columnHeight = 0.0,
        public float $contentTop = 0.0,
        /**
         * CSS Multi-column 2 §3 — `column-wrap: wrap`. When true the tall
         * content is sliced into bands of `columnHeight` and wrapped into a
         * `columnCount × rowCount` grid (filling a row of columns left→right,
         * then the next row down), rather than a single row of `columnCount`
         * columns. `contentHeight` is the tall content's total block size (to
         * derive the band/row count); `rowGap` separates the rows.
         */
        public bool $columnWrap = false,
        public float $contentHeight = 0.0,
        public float $rowGap = 0.0,
        /**
         * CSS Multi-column 1 §3.3 + §6.2 — the container's FRAGMENTED
         * columnar runs, in document order. `$fragmented` above can only
         * describe ONE tall column; a container carved up by
         * `column-span: all` has several independent runs, each with its
         * own column height, its own top and its own band count, so those
         * are recorded here instead. Empty on every container that does
         * not need slicing (the classic balance path) — the painter falls
         * back to painting children once.
         *
         * @var list<ColumnRun>
         */
        public array $runs = [],
    ) {}

    /**
     * Append one fragmented columnar run. `MultiColumnLayout` is readonly
     * and is built before the runs are known, so layout rebuilds it as
     * each run resolves.
     */
    public function withRun(ColumnRun $run): self
    {
        return new self(
            $this->columnCount,
            $this->columnWidth,
            $this->columnGap,
            $this->ruleWidth,
            $this->ruleStyle,
            $this->ruleColor,
            $this->fragmented,
            $this->columnHeight,
            $this->contentTop,
            $this->columnWrap,
            $this->contentHeight,
            $this->rowGap,
            [...$this->runs, $run],
        );
    }
}
