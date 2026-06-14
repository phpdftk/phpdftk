<?php

declare(strict_types=1);

namespace Phpdftk\HtmlToPdf\Layout;

use Phpdftk\Css\Cascade\Cascade;
use Phpdftk\Css\Cascade\CascadedValues;
use Phpdftk\Css\Cascade\LengthContext;
use Phpdftk\Css\Value\Keyword;
use Phpdftk\Css\Value\Length;
use Phpdftk\Css\Value\Percentage;
use Phpdftk\HtmlToPdf\Box\Box;
use Phpdftk\HtmlToPdf\Box\BlockBox;
use Phpdftk\HtmlToPdf\Box\AnonymousBlockBox;
use Phpdftk\HtmlToPdf\Box\AtomicInlineBox;
use Phpdftk\HtmlToPdf\Box\InlineBox;
use Phpdftk\HtmlToPdf\Box\TextBox;
use Phpdftk\HtmlToPdf\Layout\MultiColumnLayout;

/**
 * Block formatting context layout — Phase 1F.1 (vertical block stacking).
 *
 * Walks the box tree depth-first and assigns each {@see BlockBox} a position
 * + content-area dimensions. The containing block's width determines `auto`
 * width resolution (`width = containingBlockWidth - margins - borders -
 * padding`); height accumulates from children below.
 *
 * Implemented at this phase:
 *  - Block-level boxes stacked vertically inside their containing block.
 *  - `width`, `margin-*`, `padding-*`, `border-*-width` resolved from
 *    the cascade (lengths and percentages).
 *  - `auto` width fills the containing block (less margins / borders /
 *    padding).
 *  - `height: auto` sums the children's outer heights.
 *
 * **Not yet implemented (later sub-phases)**:
 *  - Margin collapsing (CSS 2.1 §8.3.1) — adjacent sibling margins
 *    currently sum rather than collapse.
 *  - Float and absolute positioning.
 *  - Inline / line-box layout — InlineBox / TextBox children get the
 *    parent's content width but zero height for now; 1F.2 ships shaped
 *    text with line-box construction.
 *  - Table / flex / grid layout — handled by their own dedicated layouts.
 */
final class BlockLayout
{
    /**
     * Active table's effective column count while laying out its rows.
     * Set by `layoutBox`'s `TableBox` branch via `maxColumnsIn`, read by
     * `layoutTableRow` so every row uses the same column grid. Null when
     * no table is currently being laid out.
     */
    private ?int $currentTableColumns = null;

    /**
     * Per-column explicit widths declared via `<col>` / `<colgroup>`
     * `width` attributes (HTML 5 §4.9.4). Each entry is the explicit
     * pixel width for that column, or null when no `<col>` declared
     * one — in which case the auto-distribution path fills it in.
     *
     * @var list<?float>|null
     */
    private ?array $currentColumnWidths = null;

    /**
     * Set to true once the auto-width content-measurement pass has
     * run for the current table. Reset alongside `currentColumnWidths`
     * in the table layout branch so each table re-measures.
     */
    private bool $currentAutoWidthsResolved = false;

    /**
     * Cell-occupancy grid for the active table — HTML 5 §4.9.11
     * rowspan / colspan resolution. Keyed by `spl_object_id($cell)`;
     * the value records the cell's resolved (row, col) plus declared
     * (rowspan, colspan). Built once per TableBox by
     * `precomputeTableCellGrid()`, consulted by `layoutTableRow()` so
     * subsequent rows skip columns covered by prior rowspan cells.
     *
     * @var array<int, array{row: int, col: int, rowspan: int, colspan: int}>|null
     */
    private ?array $currentTableCellGrid = null;

    /**
     * Per-row height tally for the active table. Populated in
     * `layoutTableRow` after each row's max-cell-height resolves, so
     * the post-pass `finalizeRowspanHeights()` can sum heights for
     * any cell that spans multiple rows.
     *
     * @var array<int, float>
     */
    private array $currentTableRowHeights = [];

    public function __construct(
        private readonly Cascade $cascade,
        private readonly InlineLayout $inlineLayout = new InlineLayout(),
    ) {}

    /**
     * Lay out `$root` at the origin from `$context`. Mutates the box tree
     * in place; returns the root's accumulated outer height so callers can
     * size the page box.
     */
    public function layout(Box $root, LayoutContext $context): float
    {
        // Ensure the root's style has lengths resolved against the context.
        $this->cascade->resolveLengths($root->style, $context->lengthContext);
        // Phase 1 simplification: a single FloatContext for the whole
        // document tree (CSS 2.1 §9.5 floats stay inside their BFC;
        // proper BFC scoping is a follow-up). Lazily attach when the
        // caller didn't supply one so floats register against something.
        if ($context->floatContext === null) {
            $context = $context->withFloatContext(new FloatContext());
        }
        $height = $this->layoutBox($root, $context);
        // CSS Inline 3 §6 — `text-box-trim` runs as a post-layout pass:
        // it walks each block container with `text-box-trim != none` and
        // shifts the first / last line of the propagated first-line
        // host (CSS Inline 3 §6.4), blocked by empty block boxes.
        $this->applyTextBoxTrimTree($root);
        return $height;
    }

    /**
     * CSS Inline 3 §6.4 — propagation tree walk for `text-box-trim`.
     * For each block container with a non-`none` `text-box-trim`,
     * locate the first / last "line host" (the deepest in-flow
     * descendant whose own `lineBoxes` carry the trim target) and
     * adjust that line's geometry. The propagation is blocked by
     * empty block boxes (no own lines, no descendant lines).
     */
    private function applyTextBoxTrimTree(Box $box): void
    {
        foreach ($box->children as $child) {
            $this->applyTextBoxTrimTree($child);
        }
        $value = $box->style->get('text-box-trim');
        if (!($value instanceof Keyword)) {
            return;
        }
        $mode = strtolower($value->name);
        if ($mode === 'none') {
            return;
        }
        $trimStart = $mode === 'trim-start' || $mode === 'trim-both' || $mode === 'both';
        $trimEnd = $mode === 'trim-end' || $mode === 'trim-both' || $mode === 'both';
        if ($trimStart) {
            $host = $this->findLineHost($box, last: false);
            if ($host !== null) {
                $this->trimEdgeLineLeading($box, $host, end: false);
            }
        }
        if ($trimEnd) {
            $host = $this->findLineHost($box, last: true);
            if ($host !== null) {
                $this->trimEdgeLineLeading($box, $host, end: true);
            }
        }
    }

    /**
     * Locate the first (or last) "line host" for `text-box-trim`
     * propagation. The host is the in-flow descendant whose own
     * `lineBoxes` carry the line we want to trim. The walk is
     * depth-first along the start / end edge of `$box` and is
     * blocked by an empty block child (a block that itself has no
     * lines and whose descendants also have none) — CSS Inline 3
     * §6.4 propagation rule.
     */
    private function findLineHost(Box $box, bool $last): ?Box
    {
        // If this box has its own line boxes, IT is the host.
        if ($box->lineBoxes !== []) {
            return $box;
        }
        $children = $box->children;
        if ($children === []) {
            return null;
        }
        if ($last) {
            $children = array_reverse($children);
        }
        foreach ($children as $child) {
            // Skip non-block, non-inline-formatting children for the
            // propagation walk. Only block-level descendants
            // participate in the first-/last-line cascade.
            if (!($child instanceof BlockBox)
                && !($child instanceof \Phpdftk\HtmlToPdf\Box\AnonymousBlockBox)
                && !($child instanceof \Phpdftk\HtmlToPdf\Box\TableCellBox)
            ) {
                continue;
            }
            $sub = $this->findLineHost($child, $last);
            if ($sub !== null) {
                return $sub;
            }
            // This block contained no lines anywhere — it's an empty
            // block. Per §6.4 it BLOCKS further propagation along
            // this edge.
            return null;
        }
        return null;
    }

    /**
     * Trim the half-leading from the first / last line of `$host`
     * (its own `lineBoxes`). When `$end` is true, only the line's
     * trailing extent shrinks (the IFC's reported height reduces);
     * when false, the line shifts upward by half-leading and every
     * subsequent line moves with it.
     *
     * Half-leading is computed from the host's resolved font-size
     * and line-height, matching the way InlineLayout sized the
     * line. With `text-box-edge: leading` (default) the trim is
     * `(lineHeight − fontSize) / 2` — bigger when authors crank
     * line-height past 1.
     */
    private function trimEdgeLineLeading(Box $owner, Box $host, bool $end): void
    {
        if ($host->lineBoxes === []) {
            return;
        }
        $fontSize = $this->fontSizePx($host);
        $lineHeight = $this->lineHeightPx($host, $fontSize);
        $halfLeading = max(0.0, ($lineHeight - $fontSize) / 2.0);
        if ($halfLeading <= 0.0) {
            return;
        }
        if ($end) {
            // Shrink the host's reported height by half-leading. The
            // host's geometry.height is what the parent uses to
            // place subsequent siblings, so this is what the spec's
            // "trim-end" rule modifies.
            $host->geometry->height = max(0.0, $host->geometry->height - $halfLeading);
        } else {
            // Shift every line on the host up by half-leading so the
            // first line's top sits at the box's content edge. The
            // host's own height also reduces.
            $shifted = [];
            foreach ($host->lineBoxes as $line) {
                $shifted[] = new LineBox(
                    $line->y - $halfLeading,
                    $line->height,
                    $line->fragments,
                );
            }
            $host->lineBoxes = $shifted;
            $host->geometry->height = max(0.0, $host->geometry->height - $halfLeading);
        }
    }

    /**
     * Resolve the box's font-size to px. Falls back to 16 (the
     * cascade's initial) when the property is missing or a non-
     * Length (e.g. a `medium` keyword the cascade didn't resolve).
     */
    private function fontSizePx(Box $box): float
    {
        $value = $box->style->get('font-size');
        if ($value instanceof Length) {
            return $value->value;
        }
        return 16.0;
    }

    /**
     * Resolve the box's line-height to px against `$fontSize`. The
     * initial `normal` keyword approximates to 1.2 × fontSize per
     * CSS Inline 3 §3 (until OS/2 metrics-driven leading ships).
     */
    private function lineHeightPx(Box $box, float $fontSize): float
    {
        $value = $box->style->get('line-height');
        if ($value instanceof Length) {
            return $value->value;
        }
        if ($value instanceof \Phpdftk\Css\Value\Number) {
            return $fontSize * $value->value;
        }
        if ($value instanceof \Phpdftk\Css\Value\Percentage) {
            return $fontSize * ($value->value / 100.0);
        }
        return $fontSize * 1.2;
    }

    private function layoutBox(Box $box, LayoutContext $context): float
    {
        if ($box instanceof \Phpdftk\HtmlToPdf\Box\TableBox) {
            // CSS 2.1 §17.4.1 — `caption-side: bottom` moves a
            // `<caption>` child to render below the rows instead of
            // above (the default). Reorder children once before the
            // generic block stacker runs so layout sees the caption
            // in the right slot.
            $this->reorderTableCaptions($box);
            // Pre-walk to find the table's max columns so every row uses
            // the same column-width grid (CSS Tables 3 §4: columns are a
            // table-level concept).
            $prev = $this->currentTableColumns;
            $prevWidths = $this->currentColumnWidths;
            $prevAutoResolved = $this->currentAutoWidthsResolved;
            $prevGrid = $this->currentTableCellGrid;
            $prevRowHeights = $this->currentTableRowHeights;
            $prevCellRefs = $this->resolvedCellReferences;
            $this->currentTableCellGrid = $this->precomputeTableCellGrid($box);
            $this->currentTableRowHeights = [];
            $this->currentAutoWidthsResolved = false;
            // The cell grid drives the effective column count — rows
            // with rowspan-pulled-from-prior-row cells contribute to
            // the grid's max width.
            $this->currentTableColumns = max(1, $this->maxColumnsFromGrid($this->currentTableCellGrid));
            // HTML 5 §4.9.4 — explicit column widths from `<col>` /
            // `<colgroup width="N">`. `null` entries fall through to
            // the auto-distribution path in `layoutTableRow`.
            $this->currentColumnWidths = $this->collectColumnWidths($box, $this->currentTableColumns);
            try {
                $height = $this->layoutBlock($box, $context);
                // CSS Tables 3 §11.1 — extend rowspan cells to cover
                // every row they span. Done after rows are positioned
                // so we know each row's height.
                $this->finalizeRowspanHeights();
                // CSS Tables 3 §11.2 `border-collapse: collapse` — Phase-1
                // simplification: suppress every cell's right + bottom
                // border edges except the last column / last row, so
                // adjacent cells share their borders instead of doubling.
                if ($this->isBorderCollapse($box)) {
                    $this->collapseBorders($box);
                }
                return $height;
            } finally {
                $this->currentTableColumns = $prev;
                $this->currentColumnWidths = $prevWidths;
                $this->currentAutoWidthsResolved = $prevAutoResolved;
                $this->currentTableCellGrid = $prevGrid;
                $this->currentTableRowHeights = $prevRowHeights;
                $this->resolvedCellReferences = $prevCellRefs;
            }
        }
        if ($box instanceof \Phpdftk\HtmlToPdf\Box\TableRowBox) {
            return $this->layoutTableRow($box, $context);
        }
        if ($box instanceof \Phpdftk\HtmlToPdf\Box\TableColumnBox) {
            // CSS Tables 3 §4 — `table-column` / `table-column-group`
            // boxes carry cascade for the column-width pass but do not
            // contribute to flow geometry. Zero-height no-op.
            $box->geometry->x = $context->originX;
            $box->geometry->y = $context->originY;
            $box->geometry->width = 0.0;
            return 0.0;
        }
        if ($box instanceof \Phpdftk\HtmlToPdf\Box\FlexBox) {
            return $this->layoutFlexBox($box, $context);
        }
        if ($box instanceof \Phpdftk\HtmlToPdf\Box\GridBox) {
            return $this->layoutGridBox($box, $context);
        }
        if ($box instanceof BlockBox
            || $box instanceof AnonymousBlockBox
            || $box instanceof \Phpdftk\HtmlToPdf\Box\TableCellBox
        ) {
            return $this->layoutBlock($box, $context);
        }
        // Inline / atomic-inline children of a block context — for 1F.1
        // their content height is treated as zero. 1F.2 will replace this
        // with proper line-box construction once Shaper is integrated.
        $box->geometry->x = $context->originX;
        $box->geometry->y = $context->originY;
        $box->geometry->width = $context->containingBlockWidth;
        return 0.0;
    }

    /**
     * Phase-1 table-row layout: position each TableCellBox child
     * horizontally, sharing the parent's width equally. Row height is
     * `max(cell.outerHeight)`. CSS Tables 3 automatic column-width
     * algorithm (with min/max content widths) lands in a follow-up.
     */
    private function layoutTableRow(\Phpdftk\HtmlToPdf\Box\TableRowBox $row, LayoutContext $context): float
    {
        $geo = $row->geometry;
        $geo->x = $context->originX;
        $geo->y = $context->originY;
        $geo->width = $context->containingBlockWidth;

        $cells = array_values(array_filter(
            $row->children,
            static fn($c): bool => $c instanceof \Phpdftk\HtmlToPdf\Box\TableCellBox,
        ));
        if ($cells === []) {
            $geo->height = 0.0;
            return 0.0;
        }
        // HTML 5 `<td colspan="N">` — each cell may span N columns. The
        // total-column count for `colWidth` comes from the enclosing
        // `<table>` (computed in `layoutBox` via `maxColumnsIn`) so all
        // rows align on the same column grid. Falls back to the row's
        // own colspan-sum when no table context is active (test fixtures
        // that lay out a row in isolation).
        $colspans = array_map(fn($c) => $this->cellColspan($c), $cells);
        $totalColumns = $this->currentTableColumns ?? max(1, array_sum($colspans));
        $totalColumns = max(1, $totalColumns);
        // CSS Tables 3 §10.4 auto-width pass: lazily fill in any
        // column widths that weren't set by `<col width>` based on
        // the measured max-content of cells in each column. Cached
        // via `currentAutoWidthsResolved` so multi-row tables only
        // pay the measurement cost once. Triggered on the first
        // row's layout — by then the table's geometry width is set.
        if (!$this->currentAutoWidthsResolved) {
            $this->resolveAutoColumnContentWidths(
                $totalColumns,
                $geo->width,
                $context,
            );
            $this->currentAutoWidthsResolved = true;
        }
        // HTML 5 §4.9.4 — honour explicit `<col width>` declarations.
        // Build a per-column width array: explicit widths from the col
        // declarations are honoured directly; remaining slack divides
        // evenly across the auto-width columns.
        $columnWidths = $this->resolveColumnWidthGrid(
            $totalColumns,
            $geo->width,
            $this->currentColumnWidths,
        );
        // Precomputed running prefix-sum so each cell's X = left edge +
        // sum of widths of preceding columns.
        $colOffsets = [0.0];
        foreach ($columnWidths as $w) {
            $colOffsets[] = $colOffsets[count($colOffsets) - 1] + $w;
        }
        $maxHeight = 0.0;
        $rowIndex = $this->resolveRowIndex($row);
        $cellCursorFallback = 0; // when no precomputed grid is present
        foreach ($cells as $i => $cell) {
            $span = $colspans[$i];
            $col = $this->resolveCellColumn($cell, $cellCursorFallback);
            $cellCursorFallback = max($cellCursorFallback, $col + $span);
            $cellX = $geo->x + ($colOffsets[$col] ?? 0.0);
            $cellWidth = 0.0;
            for ($k = 0; $k < $span && $col + $k < $totalColumns; $k++) {
                $cellWidth += $columnWidths[$col + $k];
            }
            $cellCtx = $context
                ->withContainingBlock($cellWidth, $context->containingBlockHeight)
                ->withOrigin($cellX, $geo->y);
            // Resolve cell-level CSS lengths against the cell's containing
            // block before recursing (mirrors `layoutBlock`'s pre-pass).
            $this->cascade->resolveLengths($cell->style, $cellCtx->lengthContext);
            $h = $this->layoutBlock($cell, $cellCtx);
            // Cells that span multiple rows don't contribute their full
            // height to *this* row's max — the rowspan post-pass extends
            // them across the spanned rows after every row has resolved.
            $rowspan = $this->resolveCellRowspan($cell);
            if ($rowspan <= 1 && $h > $maxHeight) {
                $maxHeight = $h;
            }
        }
        $geo->height = $maxHeight;
        if ($rowIndex >= 0) {
            $this->currentTableRowHeights[$rowIndex] = $maxHeight;
        }
        // Cells smaller than the row get stretched to the row height so
        // borders / backgrounds align flush at the bottom edge — CSS 2.1
        // §17.5.3 "cell percentage" simplification. CSS Tables 3 §6.2
        // `vertical-align: middle | bottom` shifts the cell's children
        // down by half / all of the slack so the content sits centred /
        // at the bottom of the row.
        foreach ($cells as $cell) {
            $contentHeight = $cell->geometry->height;
            $slack = $maxHeight - $contentHeight;
            if ($slack > 0.0) {
                $valign = $cell->style->get('vertical-align');
                $shift = 0.0;
                if ($valign instanceof \Phpdftk\Css\Value\Keyword) {
                    $shift = match (strtolower($valign->name)) {
                        'middle' => $slack / 2.0,
                        'bottom', 'baseline' => $slack,
                        default => 0.0,
                    };
                }
                if ($shift > 0.0) {
                    foreach ($cell->children as $child) {
                        $this->shiftSubtree($child, $shift);
                    }
                }
                $cell->geometry->height = $maxHeight;
            }
        }
        return $maxHeight;
    }

    private function layoutBlock(Box $box, LayoutContext $context): float
    {
        $style = $box->style;
        $cbWidth = $context->containingBlockWidth;
        $geo = $box->geometry;

        // Resolve margin / padding / border edges from the cascade. Margin
        // is allowed to be `auto`; we record that separately and resolve to
        // 0 here, then redistribute slack into auto sides after width is
        // computed (CSS 2.1 §10.3.3).
        $marginTopValue = $style->get('margin-top');
        $marginRightValue = $style->get('margin-right');
        $marginBottomValue = $style->get('margin-bottom');
        $marginLeftValue = $style->get('margin-left');
        $marginLeftAuto = $this->isAuto($marginLeftValue);
        $marginRightAuto = $this->isAuto($marginRightValue);
        $geo->marginTop = $this->isAuto($marginTopValue) ? 0.0 : $this->resolveLength($marginTopValue, $cbWidth);
        $geo->marginRight = $marginRightAuto ? 0.0 : $this->resolveLength($marginRightValue, $cbWidth);
        $geo->marginBottom = $this->isAuto($marginBottomValue) ? 0.0 : $this->resolveLength($marginBottomValue, $cbWidth);
        $geo->marginLeft = $marginLeftAuto ? 0.0 : $this->resolveLength($marginLeftValue, $cbWidth);
        $geo->paddingTop = $this->resolveLength($style->get('padding-top'), $cbWidth);
        $geo->paddingRight = $this->resolveLength($style->get('padding-right'), $cbWidth);
        $geo->paddingBottom = $this->resolveLength($style->get('padding-bottom'), $cbWidth);
        $geo->paddingLeft = $this->resolveLength($style->get('padding-left'), $cbWidth);
        $geo->borderTop = $this->resolveBorderWidth($style, 'top');
        $geo->borderRight = $this->resolveBorderWidth($style, 'right');
        $geo->borderBottom = $this->resolveBorderWidth($style, 'bottom');
        $geo->borderLeft = $this->resolveBorderWidth($style, 'left');

        // Resolve content width: `auto` fills the containing block minus
        // margins / borders / padding. CSS Sizing 3 §6.2: with
        // `box-sizing: border-box`, the declared `width` includes
        // border + padding, so subtract them to get the content width.
        // CSS 2.1 propidx — `width` does not apply to internal table
        // boxes (table-row-group / -header-group / -footer-group /
        // -row / -column / -column-group / -cell). Force `auto` for
        // those display types so authors who declared a `width: 0`
        // override don't collapse the entire row-group.
        $widthValue = $style->get('width');
        if (!$this->widthAppliesToDisplay($style)) {
            $widthValue = new Keyword('auto');
        }
        $widthAuto = $this->isAuto($widthValue);
        $borderBox = $this->isBorderBoxSizing($style);
        if ($widthAuto) {
            $contentWidth = max(
                0.0,
                $cbWidth - $geo->marginLeft - $geo->marginRight
                    - $geo->borderLeft - $geo->borderRight
                    - $geo->paddingLeft - $geo->paddingRight,
            );
        } else {
            $contentWidth = $this->resolveLength($widthValue, $cbWidth);
            if ($borderBox) {
                $contentWidth = max(
                    0.0,
                    $contentWidth
                        - $geo->borderLeft - $geo->borderRight
                        - $geo->paddingLeft - $geo->paddingRight,
                );
            }
        }
        // CSS 2.1 §10.4 — clamp to [min-width, max-width]. min-width wins
        // when min > max (so `min: 100px; max: 50px` resolves to 100px).
        // `max-width: none` keyword leaves the upper bound unbounded;
        // numeric `auto` on min-width resolves to 0. Under
        // `box-sizing: border-box` the min/max values include border +
        // padding too, so we subtract them to compare against the
        // content-box width.
        $horizontalInset = $borderBox
            ? $geo->borderLeft + $geo->borderRight + $geo->paddingLeft + $geo->paddingRight
            : 0.0;
        $maxWidthValue = $style->get('max-width');
        if (!($maxWidthValue instanceof Keyword && strtolower($maxWidthValue->name) === 'none')) {
            $maxWidth = max(0.0, $this->resolveLength($maxWidthValue, $cbWidth) - $horizontalInset);
            if ($maxWidth > 0.0 && $contentWidth > $maxWidth) {
                $contentWidth = $maxWidth;
                // Width fell out of `auto` territory — treat it like an
                // explicit length from here on so auto-margin slack
                // distribution kicks in below.
                $widthAuto = false;
            }
        }
        $minWidthValue = $style->get('min-width');
        $minWidth = max(0.0, $this->resolveLength($minWidthValue, $cbWidth) - $horizontalInset);
        if ($minWidth > 0.0 && $contentWidth < $minWidth) {
            $contentWidth = $minWidth;
            $widthAuto = false;
        }
        $geo->width = $contentWidth;

        // CSS 2.1 §10.3.3 — `auto` margin redistribution. Only applies when
        // width is an explicit length (auto width already greedily fills
        // the available space). The remaining slack is split between the
        // auto-margin sides; `margin: 0 auto` centers a fixed-width box.
        // CSS 2.1 §10.3.3 auto-margin distribution applies to in-flow,
        // non-floated boxes. For `position: absolute` / `fixed`,
        // §10.3.7 defines its own auto-margin rules (resolved later in
        // {@see resolveAbsoluteOffsets}); applying the in-flow rule
        // here would double-shift the abs-pos box. For floats,
        // §9.5.1 says auto margins compute to 0 — let them stay
        // at 0 so the float lands at its containing-block edge.
        if (!$widthAuto
            && ($marginLeftAuto || $marginRightAuto)
            && !$this->isOutOfFlow($box)
            && $this->floatSide($box) === null
        ) {
            $slack = $cbWidth
                - $geo->width
                - $geo->borderLeft - $geo->borderRight
                - $geo->paddingLeft - $geo->paddingRight;
            $slack -= ($marginLeftAuto ? 0.0 : $geo->marginLeft);
            $slack -= ($marginRightAuto ? 0.0 : $geo->marginRight);
            if ($slack > 0.0) {
                if ($marginLeftAuto && $marginRightAuto) {
                    $geo->marginLeft = $slack / 2.0;
                    $geo->marginRight = $slack / 2.0;
                } elseif ($marginLeftAuto) {
                    $geo->marginLeft = $slack;
                } else {
                    $geo->marginRight = $slack;
                }
            }
        }

        // Place this box: top-left of content area is at originX + left
        // margin/border/padding, originY + top margin/border/padding.
        $geo->x = $context->originX + $geo->marginLeft + $geo->borderLeft + $geo->paddingLeft;
        $geo->y = $context->originY + $geo->marginTop + $geo->borderTop + $geo->paddingTop;

        // Lay out children. If all children are inline-level, the box hosts
        // an inline formatting context — defer to InlineLayout for line-box
        // construction. Mixed children get the anonymous-block treatment
        // from BoxGenerator, so we only see homogeneous block-or-inline
        // child sets at this point.
        $childContext = $context
            ->withContainingBlock($geo->width, $context->containingBlockHeight)
            ->withOrigin($geo->x, $geo->y)
            ->withLengthContext($this->lengthContextFor($style, $context->lengthContext));
        $childTotal = 0.0;
        if ($box->children !== [] && $this->isMultiColumnContainer($box)) {
            $childTotal = $this->layoutMultiColumn($box, $childContext);
        } elseif ($box->children !== [] && $this->allInlineLevel($box->children)) {
            $childTotal = $this->layoutInlineChildren($box, $childContext);
        } else {
            // Defer to the shared list-iterator so out-of-flow children
            // (`position: absolute` / `fixed`), page-break logic, margin
            // collapse, and break-inside avoidance all run through one
            // codepath — same one `layoutMultiColumn` reuses per segment.
            $childTotal = $this->stackChildrenList($box->children, $childContext, $geo->x, $geo->y);
        }

        // Parent-child margin collapsing (CSS 2.1 §8.3.1).
        //
        // Top: if the parent has no top border, no top padding, and the
        // first in-flow child is a block, the child's top margin collapses
        // through into the parent. The parent's effective margin-top
        // becomes `max(parent.marginTop, firstChild.marginTop)`, and the
        // child's content shifts up to sit at the parent's content edge.
        //
        // Bottom: symmetric, but only when the parent's height is auto
        // (otherwise the parent's bottom margin is fixed at its border
        // edge regardless of the child).
        // Skip parent-child collapse-through at the document root: there's
        // no ancestor above `<html>` to absorb the propagated margin, so
        // the standard `shiftSubtree(child, -childTopMargin)` would push
        // the body's content above the page top (e.g. a `<p>` with
        // margin-top: 16px ends up at y=-16). Browsers absorb the root's
        // marginTop into the viewport initial containing block; we just
        // drop it on the floor here so the first child stays on-page.
        $isRoot = $box->element !== null
            && strtolower($box->element->localName) === 'html';
        if (!$isRoot
            && $box->children !== []
            && $geo->paddingTop === 0.0
            && $geo->borderTop === 0.0
        ) {
            $first = $box->children[0];
            if ($first instanceof BlockBox && $first->geometry->marginTop > 0.0) {
                $childTopMargin = $first->geometry->marginTop;
                $this->shiftSubtree($first, -$childTopMargin);
                // Cascade the shift across all siblings so spacing between
                // siblings remains unchanged.
                for ($i = 1, $n = count($box->children); $i < $n; $i++) {
                    $this->shiftSubtree($box->children[$i], -$childTopMargin);
                }
                $childTotal -= $childTopMargin;
                $extra = max(0.0, $childTopMargin - $geo->marginTop);
                if ($extra > 0.0) {
                    $geo->marginTop += $extra;
                    $geo->y -= 0.0; // content area already at the right spot
                }
                $first->geometry->marginTop = 0.0;
            }
        }

        // Resolve content height: explicit, percentage of containing
        // block, or auto = children. With `box-sizing: border-box`,
        // the declared height includes border + padding, so subtract
        // them to get the content height. CSS 2.1 propidx — `height`
        // doesn't apply to internal-table boxes (table-row-group /
        // -row / -cell / etc.); force `auto` for those types.
        $heightValue = $style->get('height');
        if (!$this->heightAppliesToDisplay($style)) {
            $heightValue = new Keyword('auto');
        }
        $heightIsAuto = $this->isHeightAutoLike($heightValue);
        if ($heightIsAuto) {
            // CSS Containment 3 §4.5 — `contain: size` (or `strict` /
            // `content` aliases) makes the box's intrinsic size NOT
            // depend on its children. CSS Sizing 4 §6 substitutes the
            // `contain-intrinsic-size` value (or zero when unset /
            // `none` / `auto`).
            $containedHeight = $this->resolveContainIntrinsicHeight($style, $context);
            $geo->height = $containedHeight ?? $childTotal;
        } else {
            $geo->height = $this->resolveLength($heightValue, $context->containingBlockHeight);
            if ($borderBox) {
                $geo->height = max(
                    0.0,
                    $geo->height
                        - $geo->borderTop - $geo->borderBottom
                        - $geo->paddingTop - $geo->paddingBottom,
                );
            }
        }
        // CSS Sizing 4 §4.2 — `aspect-ratio` constrains height (or
        // width) when the other dimension is determined.
        //  - `width: auto; height: explicit` → compute width from
        //    height × ratio (the inverse direction).
        //  - `height: auto; width: explicit` → compute height from
        //    width / ratio.
        // When both are auto, the children-derived height wins and
        // the ratio is ignored. When both are explicit, the ratio
        // is ignored (declared dimensions take precedence).
        $ratio = $this->resolveAspectRatio($style);
        if ($ratio !== null && $ratio > 0.0) {
            // Inputs like `aspect-ratio: 1/0.00000000000001` produce
            // a ~1e14 ratio and a 14-digit pixel dimension that OOMs
            // any subsequent layout / paint sized to it. Clamp the
            // computed side to the same browser-style ceiling we use
            // for resolved Lengths.
            if ($heightIsAuto && !$widthAuto && $geo->width > 0.0) {
                $geo->height = \Phpdftk\Css\Cascade\LengthResolver::clampPx($geo->width / $ratio);
            } elseif ($widthAuto && !$heightIsAuto && $geo->height > 0.0) {
                $geo->width = \Phpdftk\Css\Cascade\LengthResolver::clampPx($geo->height * $ratio);
            } elseif ($heightIsAuto && $geo->width > 0.0) {
                // Both auto path retained for the historic case
                // where height defaults to width / ratio.
                $geo->height = \Phpdftk\Css\Cascade\LengthResolver::clampPx($geo->width / $ratio);
            }
        }
        // CSS 2.1 §10.7 — clamp to [min-height, max-height]. Symmetric
        // with the width clamps above; `max-height: none` leaves the
        // upper bound unbounded. border-box-sized min/max include
        // padding + border, so subtract them off to compare against
        // the content-box height.
        $verticalInset = $borderBox
            ? $geo->borderTop + $geo->borderBottom + $geo->paddingTop + $geo->paddingBottom
            : 0.0;
        $maxHeightValue = $style->get('max-height');
        if (!($maxHeightValue instanceof Keyword && strtolower($maxHeightValue->name) === 'none')) {
            $maxHeight = max(0.0, $this->resolveLength($maxHeightValue, $context->containingBlockHeight) - $verticalInset);
            if ($maxHeight > 0.0 && $geo->height > $maxHeight) {
                $geo->height = $maxHeight;
            }
        }
        $minHeight = max(0.0, $this->resolveLength($style->get('min-height'), $context->containingBlockHeight) - $verticalInset);
        if ($minHeight > 0.0 && $geo->height < $minHeight) {
            $geo->height = $minHeight;
        }

        if ($heightIsAuto
            && $box->children !== []
            && $geo->paddingBottom === 0.0
            && $geo->borderBottom === 0.0
        ) {
            $last = $box->children[count($box->children) - 1];
            if ($last instanceof BlockBox && $last->geometry->marginBottom > 0.0) {
                $childBottomMargin = $last->geometry->marginBottom;
                $extra = max(0.0, $childBottomMargin - $geo->marginBottom);
                if ($extra > 0.0) {
                    $geo->marginBottom += $extra;
                }
                $last->geometry->marginBottom = 0.0;
                $geo->height -= $childBottomMargin;
            }
        }

        // CSS 2.1 §9.4.3 — `position: relative` AND `position: sticky`.
        // The box and its descendants paint at their original layout
        // position plus the resolved offsets; siblings continue to
        // flow against the original position (this function returns
        // `outerHeight()` from the pre-shift geometry, which is what
        // stackChildren uses to advance its cursor — so siblings stay
        // put). Sticky falls back to relative-like behaviour in print
        // since there's no scrolling viewport for the box to stick
        // to (CSS Positioning 3 §6.3 — without a scroll container,
        // sticky degrades to relative offsets at the static position).
        $positionValue = $style->get('position');
        if ($positionValue instanceof Keyword) {
            $posName = strtolower($positionValue->name);
            if ($posName === 'relative' || $posName === 'sticky') {
                $relativeOuterHeight = $geo->outerHeight();
                [$dx, $dy] = $this->resolveRelativeOffsets($style, $context);
                if ($dx !== 0.0 || $dy !== 0.0) {
                    $this->shiftSubtree($box, $dy, $dx);
                }
                return $relativeOuterHeight;
            }
        }

        return $geo->outerHeight();
    }

    /**
     * CSS 2.1 §9.4.3 — resolve `top` / `right` / `bottom` / `left` to
     * the (dx, dy) shift for a relative-positioned box.
     *
     *  - If both `top` and `bottom` are set, `top` wins (positive dy
     *    moves the box down).
     *  - If both `left` and `right` are set, `left` wins (positive dx
     *    moves the box right).
     *  - `right`/`bottom` produce a negative shift (move up / left).
     *  - Percentages resolve against the containing block's width
     *    (horizontal axis) or height (vertical axis).
     *
     * @return array{0:float, 1:float} `[dx, dy]`
     */
    /**
     * Return `'left'` / `'right'` per CSS 2.1 §9.5 `float`, or `null`
     * when the box is not floated.
     */
    private function floatSide(Box $box): ?string
    {
        $value = $box->style->get('float');
        if (!($value instanceof Keyword)) {
            return null;
        }
        $lower = strtolower($value->name);
        return $lower === 'left' || $lower === 'right' ? $lower : null;
    }

    /**
     * Return `'left'` / `'right'` / `'both'` per CSS 2.1 §9.5.2 `clear`,
     * or `null` for `none` / `inherit` / unrecognised.
     */
    private function clearSide(Box $box): ?string
    {
        $value = $box->style->get('clear');
        if (!($value instanceof Keyword)) {
            return null;
        }
        $lower = strtolower($value->name);
        return in_array($lower, ['left', 'right', 'both'], true) ? $lower : null;
    }

    /**
     * Lay out a float child, register it in the containing block's
     * FloatContext, and leave the parent's cursor untouched. The float
     * gets its content width from the cascade (auto width becomes
     * shrink-to-fit by reading the cascaded `width` value; Phase-1
     * defaults to the full containing-block width when `width: auto`,
     * which doesn't match the spec's shrink-to-fit but matches the
     * common author pattern of `<img width=...>` or explicit width).
     */
    private function layoutFloat(
        Box $child,
        string $side,
        LayoutContext $childContext,
        float $originX,
        float $cursorY,
    ): void {
        // Lay the float out in a virtual slot at `originX, cursorY` so
        // its inner geometry resolves (width / margins / borders /
        // padding / child block heights).
        $floatCtx = $childContext->floatContext;
        $virtual = $childContext->withOrigin($originX, $cursorY);
        $this->layoutBox($child, $virtual);

        // If no FloatContext exists yet, lazily attach one to the
        // childContext so subsequent siblings see this float.
        if ($floatCtx === null) {
            // Caller's $childContext is readonly; the lazy creation
            // happens at `layoutBlock` level — see `establishFloatContext`.
            // Fallback: skip registration when no context is established.
            return;
        }

        $cbLeft = $originX;
        $cbRight = $originX + $childContext->containingBlockWidth;
        $floatWidth = $child->geometry->outerWidth();
        $floatHeight = $child->geometry->outerHeight();
        $placement = $side === 'left'
            ? $floatCtx->placeLeft($cursorY, $cbLeft, $cbRight, $floatWidth)
            : $floatCtx->placeRight($cursorY, $cbLeft, $cbRight, $floatWidth);
        $targetX = $placement['x'];
        $targetY = $placement['y'];
        // Shift the float's whole subtree from its virtual position
        // (originX, cursorY) to its registered position.
        $dx = $targetX - ($virtual->originX);
        $dy = $targetY - $cursorY;
        if ($dx !== 0.0 || $dy !== 0.0) {
            $this->shiftSubtree($child, $dy, $dx);
        }
        // CSS Shapes 1 §3 — when the float declares
        // `shape-outside: <reference-box>` (a margin-box / border-box /
        // padding-box / content-box keyword), the exclusion region
        // contracts from the float's margin-box (the default) to that
        // reference box. We register the contracted exclusion rect
        // here so inline layout flows text closer to the float.
        $exclusionRect = $this->shapeOutsideExclusionRect(
            $child,
            $targetX,
            $targetY,
            $floatWidth,
            $floatHeight,
        );
        // CSS Shapes 1 §3.1 — `shape-outside: inset(...)` shrinks the
        // reference rect by the per-edge insets. Done before the
        // per-Y shape resolution so circle / ellipse / polygon see
        // the post-inset bounds.
        $exclusionRect = $this->applyInsetShape($child, $exclusionRect);
        // CSS Shapes 1 §3.2 — when the float's `shape-outside` is a
        // function value (`circle()` / `ellipse()` / `polygon()`),
        // resolve it to an item-local exclusion shape so the
        // FloatContext's per-Y queries trace the contour instead of
        // the bounding rect.
        $shape = $this->resolveShapeOutsideShape(
            $child,
            $exclusionRect['width'],
            $exclusionRect['height'],
        );
        if ($side === 'left') {
            $floatCtx->addLeft($exclusionRect['x'], $exclusionRect['y'], $exclusionRect['width'], $exclusionRect['height'], $shape);
        } else {
            $floatCtx->addRight($exclusionRect['x'], $exclusionRect['y'], $exclusionRect['width'], $exclusionRect['height'], $shape);
        }
    }

    /**
     * Resolve a `circle()` shape-outside function value against the
     * reference box's width / height. Returns an associative array
     * the FloatContext understands, or `null` when the value isn't
     * a circle (or has an unresolvable radius / position).
     *
     * Supported radii: explicit Length (px), Percentage (% of the
     * average box dimension per CSS Shapes 1 §3.2 footnote),
     * `closest-side` / `farthest-side` keywords (defaults to
     * `closest-side` when omitted).
     *
     * Position: explicit `at <length> <length>` pairs. Percentage
     * positions resolve against the reference box. Defaults to the
     * box center (50%, 50%).
     *
     * @return ?array{kind: 'circle', cx: float, cy: float, r: float}
     */
    /**
     * CSS Shapes 1 §3.1 — when the float's `shape-outside` is an
     * `inset(<lengths>)` value (or a `ValueList` wrapping one with
     * a reference-box keyword), shrink the supplied exclusion rect
     * by the per-edge insets. Returns the original rect unchanged
     * when no inset value applies.
     *
     * Insets follow CSS TRBL shorthand expansion (1 to 4 values).
     *
     * @param array{x: float, y: float, width: float, height: float} $rect
     * @return array{x: float, y: float, width: float, height: float}
     */
    private function applyInsetShape(Box $float, array $rect): array
    {
        $value = $float->style->get('shape-outside');
        $inset = null;
        if ($value instanceof \Phpdftk\Css\Value\InsetShape) {
            $inset = $value;
        } elseif ($value instanceof \Phpdftk\Css\Value\ValueList) {
            foreach ($value->values as $v) {
                if ($v instanceof \Phpdftk\Css\Value\InsetShape) {
                    $inset = $v;
                    break;
                }
            }
        }
        if ($inset === null) {
            return $rect;
        }
        // TRBL shorthand expansion.
        $insets = $inset->insets;
        $n = count($insets);
        if ($n === 0) {
            return $rect;
        }
        $top = $this->resolveShapePosition($insets[0], $rect['height'], 0.0);
        $right = $n >= 2
            ? $this->resolveShapePosition($insets[1], $rect['width'], 0.0)
            : $top;
        $bottom = $n >= 3
            ? $this->resolveShapePosition($insets[2], $rect['height'], 0.0)
            : $top;
        $left = $n >= 4
            ? $this->resolveShapePosition($insets[3], $rect['width'], 0.0)
            : $right;
        return [
            'x' => $rect['x'] + $left,
            'y' => $rect['y'] + $top,
            'width' => max(0.0, $rect['width'] - $left - $right),
            'height' => max(0.0, $rect['height'] - $top - $bottom),
        ];
    }

    private function resolveShapeOutsideShape(Box $float, float $refWidth, float $refHeight): ?array
    {
        $value = $float->style->get('shape-outside');
        $shape = null;
        if ($value instanceof \Phpdftk\Css\Value\CircleShape
            || $value instanceof \Phpdftk\Css\Value\EllipseShape
            || $value instanceof \Phpdftk\Css\Value\PolygonShape
            || $value instanceof \Phpdftk\Css\Value\PathShape
        ) {
            $shape = $value;
        } elseif ($value instanceof \Phpdftk\Css\Value\ValueList) {
            foreach ($value->values as $v) {
                if ($v instanceof \Phpdftk\Css\Value\CircleShape
                    || $v instanceof \Phpdftk\Css\Value\EllipseShape
                    || $v instanceof \Phpdftk\Css\Value\PolygonShape
                    || $v instanceof \Phpdftk\Css\Value\PathShape
                ) {
                    $shape = $v;
                    break;
                }
            }
        }
        if ($shape === null) {
            return null;
        }
        if ($shape instanceof \Phpdftk\Css\Value\CircleShape) {
            $cx = $this->resolveShapePosition($shape->centerX, $refWidth, $refWidth / 2.0);
            $cy = $this->resolveShapePosition($shape->centerY, $refHeight, $refHeight / 2.0);
            $r = $this->resolveCircleRadius($shape->radius, $cx, $cy, $refWidth, $refHeight);
            if ($r <= 0.0) {
                return null;
            }
            return ['kind' => 'circle', 'cx' => $cx, 'cy' => $cy, 'r' => $r];
        }
        if ($shape instanceof \Phpdftk\Css\Value\EllipseShape) {
            $cx = $this->resolveShapePosition($shape->centerX, $refWidth, $refWidth / 2.0);
            $cy = $this->resolveShapePosition($shape->centerY, $refHeight, $refHeight / 2.0);
            $rx = $this->resolveEllipseRadius($shape->radiusX, $cx, $refWidth);
            $ry = $this->resolveEllipseRadius($shape->radiusY, $cy, $refHeight);
            if ($rx <= 0.0 || $ry <= 0.0) {
                return null;
            }
            return ['kind' => 'ellipse', 'cx' => $cx, 'cy' => $cy, 'rx' => $rx, 'ry' => $ry];
        }
        if ($shape instanceof \Phpdftk\Css\Value\PolygonShape) {
            $vertices = [];
            foreach ($shape->vertices as $vertex) {
                [$xv, $yv] = $vertex;
                $x = $this->resolveShapePosition($xv, $refWidth, 0.0);
                $y = $this->resolveShapePosition($yv, $refHeight, 0.0);
                $vertices[] = [$x, $y];
            }
            if (count($vertices) < 3) {
                return null;
            }
            return ['kind' => 'polygon', 'vertices' => $vertices];
        }
        // path() — CSS Shapes 1 §3.5. Parse the SVG path data via
        // `phpdftk/svg`'s grammar parser and flatten curves into a
        // polygon-style vertex list the FloatContext already
        // understands.
        $vertices = $this->flattenSvgPath($shape->pathData);
        if (count($vertices) < 3) {
            return null;
        }
        return ['kind' => 'polygon', 'vertices' => $vertices];
    }

    /**
     * Flatten an SVG path-data string into a polygon vertex list
     * suitable for the FloatContext polygon evaluator. Cubic /
     * quadratic curves are recursively subdivided until each
     * segment is flat enough; `ArcTo` falls back to a straight
     * line (CSS Shapes path usage rarely needs ellipse-arc
     * support in practice).
     *
     * Multiple subpaths (separated by `MoveTo`) are concatenated;
     * the polygon edge scan walks all edges regardless.
     *
     * @return list<array{float, float}>
     */
    private function flattenSvgPath(string $pathData): array
    {
        $data = \Phpdftk\Svg\Path\PathData::parse($pathData);
        /** @var list<array{float, float}> $vertices */
        $vertices = [];
        $cx = 0.0;
        $cy = 0.0;
        $startX = 0.0;
        $startY = 0.0;
        $lastCubicCtrl = null;
        $lastQuadCtrl = null;
        $emit = static function (float $x, float $y) use (&$vertices): void {
            $n = count($vertices);
            if ($n > 0
                && abs($vertices[$n - 1][0] - $x) < 0.0001
                && abs($vertices[$n - 1][1] - $y) < 0.0001
            ) {
                return;
            }
            $vertices[] = [$x, $y];
        };
        foreach ($data->commands as $cmd) {
            if ($cmd instanceof \Phpdftk\Svg\Path\MoveTo) {
                $cx = $cmd->absolute ? $cmd->x : $cx + $cmd->x;
                $cy = $cmd->absolute ? $cmd->y : $cy + $cmd->y;
                $startX = $cx;
                $startY = $cy;
                $emit($cx, $cy);
                $lastCubicCtrl = null;
                $lastQuadCtrl = null;
                continue;
            }
            if ($cmd instanceof \Phpdftk\Svg\Path\LineTo) {
                $cx = $cmd->absolute ? $cmd->x : $cx + $cmd->x;
                $cy = $cmd->absolute ? $cmd->y : $cy + $cmd->y;
                $emit($cx, $cy);
                $lastCubicCtrl = null;
                $lastQuadCtrl = null;
                continue;
            }
            if ($cmd instanceof \Phpdftk\Svg\Path\HorizontalLineTo) {
                $cx = $cmd->absolute ? $cmd->x : $cx + $cmd->x;
                $emit($cx, $cy);
                $lastCubicCtrl = null;
                $lastQuadCtrl = null;
                continue;
            }
            if ($cmd instanceof \Phpdftk\Svg\Path\VerticalLineTo) {
                $cy = $cmd->absolute ? $cmd->y : $cy + $cmd->y;
                $emit($cx, $cy);
                $lastCubicCtrl = null;
                $lastQuadCtrl = null;
                continue;
            }
            if ($cmd instanceof \Phpdftk\Svg\Path\CurveTo) {
                $c1x = $cmd->absolute ? $cmd->x1 : $cx + $cmd->x1;
                $c1y = $cmd->absolute ? $cmd->y1 : $cy + $cmd->y1;
                $c2x = $cmd->absolute ? $cmd->x2 : $cx + $cmd->x2;
                $c2y = $cmd->absolute ? $cmd->y2 : $cy + $cmd->y2;
                $ex = $cmd->absolute ? $cmd->x : $cx + $cmd->x;
                $ey = $cmd->absolute ? $cmd->y : $cy + $cmd->y;
                $this->flattenCubic($vertices, $cx, $cy, $c1x, $c1y, $c2x, $c2y, $ex, $ey);
                $cx = $ex;
                $cy = $ey;
                $lastCubicCtrl = [$c2x, $c2y];
                $lastQuadCtrl = null;
                continue;
            }
            if ($cmd instanceof \Phpdftk\Svg\Path\SmoothCurveTo) {
                if ($lastCubicCtrl !== null) {
                    $c1x = 2.0 * $cx - $lastCubicCtrl[0];
                    $c1y = 2.0 * $cy - $lastCubicCtrl[1];
                } else {
                    $c1x = $cx;
                    $c1y = $cy;
                }
                $c2x = $cmd->absolute ? $cmd->x2 : $cx + $cmd->x2;
                $c2y = $cmd->absolute ? $cmd->y2 : $cy + $cmd->y2;
                $ex = $cmd->absolute ? $cmd->x : $cx + $cmd->x;
                $ey = $cmd->absolute ? $cmd->y : $cy + $cmd->y;
                $this->flattenCubic($vertices, $cx, $cy, $c1x, $c1y, $c2x, $c2y, $ex, $ey);
                $cx = $ex;
                $cy = $ey;
                $lastCubicCtrl = [$c2x, $c2y];
                $lastQuadCtrl = null;
                continue;
            }
            if ($cmd instanceof \Phpdftk\Svg\Path\QuadraticCurveTo) {
                $c1x = $cmd->absolute ? $cmd->x1 : $cx + $cmd->x1;
                $c1y = $cmd->absolute ? $cmd->y1 : $cy + $cmd->y1;
                $ex = $cmd->absolute ? $cmd->x : $cx + $cmd->x;
                $ey = $cmd->absolute ? $cmd->y : $cy + $cmd->y;
                $this->flattenQuadratic($vertices, $cx, $cy, $c1x, $c1y, $ex, $ey);
                $cx = $ex;
                $cy = $ey;
                $lastQuadCtrl = [$c1x, $c1y];
                $lastCubicCtrl = null;
                continue;
            }
            if ($cmd instanceof \Phpdftk\Svg\Path\SmoothQuadraticCurveTo) {
                if ($lastQuadCtrl !== null) {
                    $c1x = 2.0 * $cx - $lastQuadCtrl[0];
                    $c1y = 2.0 * $cy - $lastQuadCtrl[1];
                } else {
                    $c1x = $cx;
                    $c1y = $cy;
                }
                $ex = $cmd->absolute ? $cmd->x : $cx + $cmd->x;
                $ey = $cmd->absolute ? $cmd->y : $cy + $cmd->y;
                $this->flattenQuadratic($vertices, $cx, $cy, $c1x, $c1y, $ex, $ey);
                $cx = $ex;
                $cy = $ey;
                $lastQuadCtrl = [$c1x, $c1y];
                $lastCubicCtrl = null;
                continue;
            }
            if ($cmd instanceof \Phpdftk\Svg\Path\ArcTo) {
                // Phase-1 simplification: arcs degrade to a straight
                // line to the endpoint. Sufficient for the wrap
                // envelope of common rounded-rect path() shapes.
                $ex = $cmd->absolute ? $cmd->x : $cx + $cmd->x;
                $ey = $cmd->absolute ? $cmd->y : $cy + $cmd->y;
                $emit($ex, $ey);
                $cx = $ex;
                $cy = $ey;
                $lastCubicCtrl = null;
                $lastQuadCtrl = null;
                continue;
            }
            if ($cmd instanceof \Phpdftk\Svg\Path\ClosePath) {
                $emit($startX, $startY);
                $cx = $startX;
                $cy = $startY;
                $lastCubicCtrl = null;
                $lastQuadCtrl = null;
            }
        }
        return $vertices;
    }

    /**
     * Recursive de Casteljau subdivision for a cubic Bezier. Splits
     * the curve at t=0.5 until each segment is flat enough — control
     * points lie within tolerance of the chord. Appends the
     * endpoint to `$vertices`.
     *
     * @param list<array{float, float}> $vertices passed by reference.
     */
    private function flattenCubic(
        array &$vertices,
        float $x0,
        float $y0,
        float $x1,
        float $y1,
        float $x2,
        float $y2,
        float $x3,
        float $y3,
        int $depth = 0,
    ): void {
        $ux = 3.0 * $x1 - 2.0 * $x0 - $x3;
        $uy = 3.0 * $y1 - 2.0 * $y0 - $y3;
        $vx = 3.0 * $x2 - $x0 - 2.0 * $x3;
        $vy = 3.0 * $y2 - $y0 - 2.0 * $y3;
        $flat = max($ux * $ux, $vx * $vx) + max($uy * $uy, $vy * $vy);
        if ($flat <= 0.25 || $depth >= 12) {
            $vertices[] = [$x3, $y3];
            return;
        }
        $x01 = ($x0 + $x1) * 0.5;
        $y01 = ($y0 + $y1) * 0.5;
        $x12 = ($x1 + $x2) * 0.5;
        $y12 = ($y1 + $y2) * 0.5;
        $x23 = ($x2 + $x3) * 0.5;
        $y23 = ($y2 + $y3) * 0.5;
        $x012 = ($x01 + $x12) * 0.5;
        $y012 = ($y01 + $y12) * 0.5;
        $x123 = ($x12 + $x23) * 0.5;
        $y123 = ($y12 + $y23) * 0.5;
        $x0123 = ($x012 + $x123) * 0.5;
        $y0123 = ($y012 + $y123) * 0.5;
        $this->flattenCubic($vertices, $x0, $y0, $x01, $y01, $x012, $y012, $x0123, $y0123, $depth + 1);
        $this->flattenCubic($vertices, $x0123, $y0123, $x123, $y123, $x23, $y23, $x3, $y3, $depth + 1);
    }

    /**
     * Recursive de Casteljau subdivision for a quadratic Bezier.
     *
     * @param list<array{float, float}> $vertices passed by reference.
     */
    private function flattenQuadratic(
        array &$vertices,
        float $x0,
        float $y0,
        float $x1,
        float $y1,
        float $x2,
        float $y2,
        int $depth = 0,
    ): void {
        $dx = $x2 - $x0;
        $dy = $y2 - $y0;
        $len2 = $dx * $dx + $dy * $dy;
        if ($len2 < 0.0001) {
            $vertices[] = [$x2, $y2];
            return;
        }
        $cross = ($x1 - $x0) * $dy - ($y1 - $y0) * $dx;
        if (($cross * $cross) <= 0.25 * $len2 || $depth >= 12) {
            $vertices[] = [$x2, $y2];
            return;
        }
        $x01 = ($x0 + $x1) * 0.5;
        $y01 = ($y0 + $y1) * 0.5;
        $x12 = ($x1 + $x2) * 0.5;
        $y12 = ($y1 + $y2) * 0.5;
        $x012 = ($x01 + $x12) * 0.5;
        $y012 = ($y01 + $y12) * 0.5;
        $this->flattenQuadratic($vertices, $x0, $y0, $x01, $y01, $x012, $y012, $depth + 1);
        $this->flattenQuadratic($vertices, $x012, $y012, $x12, $y12, $x2, $y2, $depth + 1);
    }

    /**
     * Resolve a `<position>` axis component (Length / Percentage /
     * Keyword) against the reference axis size. `null` returns the
     * supplied default (typically the box midpoint).
     */
    private function resolveShapePosition(?\Phpdftk\Css\Value\Value $value, float $refSize, float $default): float
    {
        if ($value === null) {
            return $default;
        }
        if ($value instanceof Length) {
            return $value->value;
        }
        if ($value instanceof \Phpdftk\Css\Value\Percentage) {
            return $refSize * ($value->value / 100.0);
        }
        return $default;
    }

    /**
     * Resolve a `circle()` radius (Length / Percentage / Keyword) at
     * `(cx, cy)` against the reference box `(refW × refH)`. Returns
     * `0` when not resolvable (caller treats that as "no shape").
     */
    private function resolveCircleRadius(
        ?\Phpdftk\Css\Value\Value $value,
        float $cx,
        float $cy,
        float $refW,
        float $refH,
    ): float {
        if ($value instanceof Length) {
            return max(0.0, $value->value);
        }
        if ($value instanceof \Phpdftk\Css\Value\Percentage) {
            // CSS Shapes 1 §3.2 — percentage circle radius resolves
            // against `sqrt(refW² + refH²) / sqrt(2)`.
            $basis = sqrt(($refW * $refW + $refH * $refH) / 2.0);
            return max(0.0, $basis * ($value->value / 100.0));
        }
        $keyword = $value instanceof Keyword ? strtolower($value->name) : 'closest-side';
        $closestSide = min($cx, $refW - $cx, $cy, $refH - $cy);
        $farthestSide = max($cx, $refW - $cx, $cy, $refH - $cy);
        return match ($keyword) {
            'farthest-side' => max(0.0, $farthestSide),
            default => max(0.0, $closestSide),
        };
    }

    /**
     * Resolve an `ellipse()` axis radius. Per CSS Shapes 1 §3.3 the
     * two axes are independent: `closest-side` along x is the closer
     * of the left or right edge; along y is the closer of top or
     * bottom. Percentage resolves to a fraction of the reference
     * axis size (not the diagonal, unlike circle).
     */
    private function resolveEllipseRadius(
        ?\Phpdftk\Css\Value\Value $value,
        float $center,
        float $refSize,
    ): float {
        if ($value instanceof Length) {
            return max(0.0, $value->value);
        }
        if ($value instanceof \Phpdftk\Css\Value\Percentage) {
            return max(0.0, $refSize * ($value->value / 100.0));
        }
        $keyword = $value instanceof Keyword ? strtolower($value->name) : 'closest-side';
        $closestSide = min($center, $refSize - $center);
        $farthestSide = max($center, $refSize - $center);
        return match ($keyword) {
            'farthest-side' => max(0.0, $farthestSide),
            default => max(0.0, $closestSide),
        };
    }

    /**
     * CSS Shapes 1 §3 — read the float's `shape-outside` and contract
     * the default margin-box exclusion to the named reference box.
     * Returns the exclusion rectangle the FloatContext should register.
     *
     * Supported values: `margin-box` (default), `border-box`,
     * `padding-box`, `content-box`. Function values (`circle()`,
     * `ellipse()`, `polygon()`, etc.) are accepted but for Phase-1
     * fall through to the float's bounding rect of their reference
     * box — proper per-Y exclusion math is a follow-up.
     *
     * @return array{x: float, y: float, width: float, height: float}
     */
    private function shapeOutsideExclusionRect(
        Box $float,
        float $marginBoxX,
        float $marginBoxY,
        float $marginBoxW,
        float $marginBoxH,
    ): array {
        $shape = $float->style->get('shape-outside');
        $boxKeyword = null;
        if ($shape instanceof Keyword) {
            $boxKeyword = strtolower($shape->name);
        } elseif ($shape instanceof \Phpdftk\Css\Value\ValueList) {
            // `circle(...) margin-box` etc. — pick the trailing
            // reference-box keyword if present.
            foreach ($shape->values as $v) {
                if ($v instanceof Keyword) {
                    $name = strtolower($v->name);
                    if (in_array($name, ['margin-box', 'border-box', 'padding-box', 'content-box'], true)) {
                        $boxKeyword = $name;
                    }
                }
            }
        }
        $geo = $float->geometry;
        return match ($boxKeyword) {
            'border-box' => [
                'x' => $marginBoxX + $geo->marginLeft,
                'y' => $marginBoxY + $geo->marginTop,
                'width' => max(0.0, $marginBoxW - $geo->marginLeft - $geo->marginRight),
                'height' => max(0.0, $marginBoxH - $geo->marginTop - $geo->marginBottom),
            ],
            'padding-box' => [
                'x' => $marginBoxX + $geo->marginLeft + $geo->borderLeft,
                'y' => $marginBoxY + $geo->marginTop + $geo->borderTop,
                'width' => max(0.0, $marginBoxW - $geo->marginLeft - $geo->marginRight - $geo->borderLeft - $geo->borderRight),
                'height' => max(0.0, $marginBoxH - $geo->marginTop - $geo->marginBottom - $geo->borderTop - $geo->borderBottom),
            ],
            'content-box' => [
                'x' => $marginBoxX + $geo->marginLeft + $geo->borderLeft + $geo->paddingLeft,
                'y' => $marginBoxY + $geo->marginTop + $geo->borderTop + $geo->paddingTop,
                'width' => max(0.0, $marginBoxW - $geo->marginLeft - $geo->marginRight - $geo->borderLeft - $geo->borderRight - $geo->paddingLeft - $geo->paddingRight),
                'height' => max(0.0, $marginBoxH - $geo->marginTop - $geo->marginBottom - $geo->borderTop - $geo->borderBottom - $geo->paddingTop - $geo->paddingBottom),
            ],
            default => [
                // `margin-box` (default), `none`, function values, and
                // anything unrecognised fall back to the float's
                // margin-box (the existing behaviour).
                'x' => $marginBoxX,
                'y' => $marginBoxY,
                'width' => $marginBoxW,
                'height' => $marginBoxH,
            ],
        };
    }

    /**
     * `position: absolute` or `fixed` removes a box from normal flow.
     * `fixed` is treated the same as `absolute` in the print context —
     * there's no scrolling viewport so the difference vanishes.
     */
    private function isOutOfFlow(Box $box): bool
    {
        $value = $box->style->get('position');
        if (!($value instanceof Keyword)) {
            return false;
        }
        $lower = strtolower($value->name);
        return $lower === 'absolute' || $lower === 'fixed';
    }

    /**
     * Apply CSS 2.1 §10.3.7 (width) and §10.6.4 (height) corner-anchor
     * resolution to an absolutely-positioned box. When both opposing
     * edge anchors are set AND the corresponding size is `auto`, the
     * size is derived from `containing-block-size - start - end`. We
     * mutate the child's cascaded `width` / `height` so the standard
     * box-layout path picks up the resolved size; the existing
     * `resolveAbsoluteOffsets` then aligns left/top while right/bottom
     * are implicitly satisfied by the size.
     *
     * Phase-1 scope: ignore margins (treat as 0 in the size calculation).
     * Margins on absolute boxes are a rarely-used edge case; the
     * common pattern is `top:0; left:0; right:0; bottom:0` with no
     * margins.
     */
    private function applyAbsoluteCornerAnchorSize(Box $child, LayoutContext $childContext): void
    {
        $style = $child->style;
        $cbWidth = $childContext->containingBlockWidth;
        $cbHeight = $childContext->containingBlockHeight;
        $left = $style->get('left');
        $right = $style->get('right');
        $top = $style->get('top');
        $bottom = $style->get('bottom');
        $borderBox = $this->isBorderBoxSizing($style);
        if ($this->isAuto($style->get('width'))
            && !$this->isAuto($left)
            && !$this->isAuto($right)
        ) {
            $leftPx = $this->resolveLength($left, $cbWidth);
            $rightPx = $this->resolveLength($right, $cbWidth);
            $available = max(0.0, $cbWidth - $leftPx - $rightPx);
            // The `width` property describes the box's content area
            // under content-box sizing (default) or its border-box
            // under border-box sizing. CSS 2.1 §10.3.7 derives the
            // *content* size from the containing block, so under
            // content-box sizing we subtract the box's own borders +
            // padding (margins are 0 in this phase).
            if (!$borderBox) {
                $borderLeft = $this->resolveBorderWidth($style, 'left');
                $borderRight = $this->resolveBorderWidth($style, 'right');
                $paddingLeft = $this->resolveLength($style->get('padding-left'), $cbWidth);
                $paddingRight = $this->resolveLength($style->get('padding-right'), $cbWidth);
                $available = max(0.0, $available - $borderLeft - $borderRight - $paddingLeft - $paddingRight);
            }
            $style->set('width', new Length($available, \Phpdftk\Css\Value\LengthUnit::Px));
        }
        if ($this->isHeightAutoLike($style->get('height'))
            && !$this->isAuto($top)
            && !$this->isAuto($bottom)
        ) {
            $topPx = $this->resolveLength($top, $cbHeight);
            $bottomPx = $this->resolveLength($bottom, $cbHeight);
            $available = max(0.0, $cbHeight - $topPx - $bottomPx);
            if (!$borderBox) {
                $borderTop = $this->resolveBorderWidth($style, 'top');
                $borderBottom = $this->resolveBorderWidth($style, 'bottom');
                // Percentage padding on vertical axis resolves against
                // the containing-block *width* per CSS 2.1 §8.4.
                $paddingTop = $this->resolveLength($style->get('padding-top'), $cbWidth);
                $paddingBottom = $this->resolveLength($style->get('padding-bottom'), $cbWidth);
                $available = max(0.0, $available - $borderTop - $borderBottom - $paddingTop - $paddingBottom);
            }
            $style->set('height', new Length($available, \Phpdftk\Css\Value\LengthUnit::Px));
        }
    }

    /**
     * Resolve the (dx, dy) shift needed to move a freshly-laid-out
     * absolutely-positioned `$child` from its in-flow `cursorY` position
     * to its absolute target position per CSS 2.1 §9.6.
     *
     *  - Phase-1: containing block = the immediate parent's content box
     *    (proper spec: nearest positioned ancestor's padding box). For
     *    most author usage this matches because positioned ancestors
     *    are common.
     *  - `top` wins over `bottom`; `left` wins over `right`.
     *  - With both opposing sides `auto` (the default), the box keeps
     *    its static in-flow position — no shift.
     *  - `right` / `bottom` resolve to "containing block edge − offset
     *    − box outer width / height" so the corresponding margin edge
     *    sits the given distance from the right / bottom edge.
     *
     * @return array{0:float, 1:float} `[dx, dy]`
     */
    private function resolveAbsoluteOffsets(
        Box $child,
        LayoutContext $childContext,
        float $originX,
        float $originY,
        float $cursorY,
    ): array {
        $style = $child->style;
        $cbWidth = $childContext->containingBlockWidth;
        $cbHeight = $childContext->containingBlockHeight;
        $top = $style->get('top');
        $bottom = $style->get('bottom');
        $left = $style->get('left');
        $right = $style->get('right');

        $dy = 0.0;
        if (!$this->isAuto($top)) {
            $topOffset = $this->resolveLength($top, $cbHeight);
            $dy = $originY + $topOffset - $cursorY;
        } elseif (!$this->isAuto($bottom)) {
            $bottomOffset = $this->resolveLength($bottom, $cbHeight);
            $dy = $originY + $cbHeight - $bottomOffset
                - $cursorY - $child->geometry->outerHeight();
        }
        // CSS 2.1 §10.6.5 — when `top`, `height`, and `bottom` are all
        // non-`auto` and both `margin-top` and `margin-bottom` are
        // `auto`, set them to equal halves of the slack. The vertical
        // axis has no direction-conditioned tie-break.
        $marginTopValue = $style->get('margin-top');
        $marginBottomValue = $style->get('margin-bottom');
        if (!$this->isAuto($top)
            && !$this->isAuto($bottom)
            && !$this->isHeightAutoLike($style->get('height'))
            && $this->isAuto($marginTopValue)
            && $this->isAuto($marginBottomValue)
        ) {
            $topPx = $this->resolveLength($top, $cbHeight);
            $bottomPx = $this->resolveLength($bottom, $cbHeight);
            $borderBoxH = $this->isBorderBoxSizing($style);
            $heightPx = $this->resolveLength($style->get('height'), $cbHeight);
            $outerH = $heightPx;
            if (!$borderBoxH) {
                $outerH += $this->resolveBorderWidth($style, 'top')
                    + $this->resolveBorderWidth($style, 'bottom')
                    + $this->resolveLength($style->get('padding-top'), $cbWidth)
                    + $this->resolveLength($style->get('padding-bottom'), $cbWidth);
            }
            $slackH = $cbHeight - $topPx - $bottomPx - $outerH;
            $dy = $originY + $topPx + ($slackH / 2.0) - $cursorY;
        }

        $dx = 0.0;
        if (!$this->isAuto($left)) {
            $dx = $this->resolveLength($left, $cbWidth);
        } elseif (!$this->isAuto($right)) {
            $rightOffset = $this->resolveLength($right, $cbWidth);
            $dx = $cbWidth - $rightOffset - $child->geometry->outerWidth();
        }
        // CSS 2.1 §10.3.7 — when `left`, `width`, and `right` are all
        // non-`auto` and both `margin-left` and `margin-right` are
        // `auto`, distribute the slack `cb - left - right - border -
        // padding - width` evenly across both margins. If the slack is
        // negative (over-constrained), force one margin to 0 by
        // `direction`:
        //   - `ltr`: `margin-left = 0`, `margin-right = -slack`
        //   - `rtl`: `margin-right = 0`, `margin-left = -slack`
        $marginLeft = $style->get('margin-left');
        $marginRight = $style->get('margin-right');
        if (!$this->isAuto($left)
            && !$this->isAuto($right)
            && !$this->isAuto($style->get('width'))
            && $this->isAuto($marginLeft)
            && $this->isAuto($marginRight)
        ) {
            $leftPx = $this->resolveLength($left, $cbWidth);
            $rightPx = $this->resolveLength($right, $cbWidth);
            $borderBox = $this->isBorderBoxSizing($style);
            $widthPx = $this->resolveLength($style->get('width'), $cbWidth);
            // Under content-box sizing, the declared `width` is the
            // content-box; add own borders + padding for the outer.
            $outer = $widthPx;
            if (!$borderBox) {
                $outer += $this->resolveBorderWidth($style, 'left')
                    + $this->resolveBorderWidth($style, 'right')
                    + $this->resolveLength($style->get('padding-left'), $cbWidth)
                    + $this->resolveLength($style->get('padding-right'), $cbWidth);
            }
            $slack = $cbWidth - $leftPx - $rightPx - $outer;
            if ($slack >= 0.0) {
                $resolvedMarginLeft = $slack / 2.0;
            } else {
                $directionValue = $style->get('direction');
                $isRtl = $directionValue instanceof Keyword
                    && strtolower($directionValue->name) === 'rtl';
                $resolvedMarginLeft = $isRtl ? $slack : 0.0;
            }
            $dx = $leftPx + $resolvedMarginLeft;
        }
        return [$dx, $dy];
    }

    /** @return array{0:float, 1:float} `[dx, dy]` */
    private function resolveRelativeOffsets(CascadedValues $style, LayoutContext $context): array
    {
        $cbW = $context->containingBlockWidth;
        $cbH = $context->containingBlockHeight;
        $top = $style->get('top');
        $bottom = $style->get('bottom');
        $left = $style->get('left');
        $right = $style->get('right');
        $dy = 0.0;
        if (!$this->isAuto($top)) {
            $dy = $this->resolveLength($top, $cbH);
        } elseif (!$this->isAuto($bottom)) {
            $dy = -$this->resolveLength($bottom, $cbH);
        }
        $dx = 0.0;
        if (!$this->isAuto($left)) {
            $dx = $this->resolveLength($left, $cbW);
        } elseif (!$this->isAuto($right)) {
            $dx = -$this->resolveLength($right, $cbW);
        }
        return [$dx, $dy];
    }

    /**
     * Lay out a `display: flex` container per CSS Flexible Box
     * Layout 1. Phase-1 subset:
     *
     *  - `flex-direction: row` only (column / reverse → Phase 2).
     *  - Single-line only (`flex-wrap: nowrap`; wrap → Phase 2).
     *  - Items keep their declared `width` (no `flex-grow` /
     *    `flex-shrink` slack distribution yet).
     *  - `justify-content`: `flex-start` (default), `flex-end`,
     *    `center`, `space-between`, `space-around`, `space-evenly`.
     *  - `align-items`: `stretch` (default), `flex-start`,
     *    `flex-end`, `center`.
     *  - `column-gap` (via the existing `column-gap` longhand)
     *    inserts gaps between items.
     *
     * Returns the container's outer height.
     */
    private function layoutFlexBox(\Phpdftk\HtmlToPdf\Box\FlexBox $box, LayoutContext $context): float
    {
        $style = $box->style;
        $cbWidth = $context->containingBlockWidth;
        $cbHeight = $context->containingBlockHeight;
        $geo = $box->geometry;

        // Resolve container's margins / padding / border / width
        // exactly the same way layoutBlock does (we don't need the
        // auto-margin centring math because flex containers
        // typically have explicit dimensions).
        $geo->marginTop = $this->resolveLength($style->get('margin-top'), $cbWidth);
        $geo->marginRight = $this->resolveLength($style->get('margin-right'), $cbWidth);
        $geo->marginBottom = $this->resolveLength($style->get('margin-bottom'), $cbWidth);
        $geo->marginLeft = $this->resolveLength($style->get('margin-left'), $cbWidth);
        $geo->paddingTop = $this->resolveLength($style->get('padding-top'), $cbWidth);
        $geo->paddingRight = $this->resolveLength($style->get('padding-right'), $cbWidth);
        $geo->paddingBottom = $this->resolveLength($style->get('padding-bottom'), $cbWidth);
        $geo->paddingLeft = $this->resolveLength($style->get('padding-left'), $cbWidth);
        $geo->borderTop = $this->resolveBorderWidth($style, 'top');
        $geo->borderRight = $this->resolveBorderWidth($style, 'right');
        $geo->borderBottom = $this->resolveBorderWidth($style, 'bottom');
        $geo->borderLeft = $this->resolveBorderWidth($style, 'left');

        $widthValue = $style->get('width');
        $widthAuto = $this->isAuto($widthValue);
        $geo->width = $widthAuto
            ? max(
                0.0,
                $cbWidth - $geo->marginLeft - $geo->marginRight
                    - $geo->borderLeft - $geo->borderRight
                    - $geo->paddingLeft - $geo->paddingRight,
            )
            : $this->resolveLength($widthValue, $cbWidth);

        $geo->x = $context->originX + $geo->marginLeft + $geo->borderLeft + $geo->paddingLeft;
        $geo->y = $context->originY + $geo->marginTop + $geo->borderTop + $geo->paddingTop;

        // CSS Flexbox 1 §5.1: `flex-direction` picks the main axis.
        // `row` / `row-reverse` use the inline axis (x); `column` /
        // `column-reverse` use the block axis (y). The `*-reverse`
        // variants flip main-start ↔ main-end.
        $direction = $this->flexKeyword($style, 'flex-direction', 'row');
        $isColumn = $direction === 'column' || $direction === 'column-reverse';
        $reverseDirection = $direction === 'row-reverse' || $direction === 'column-reverse';

        $declaredHeight = $this->resolveExplicitHeightOrNull($style, $cbHeight);

        $children = $box->children;
        if ($children === []) {
            $geo->height = $declaredHeight ?? 0.0;
            return $geo->outerHeight();
        }

        // Container main / cross box dimensions. `$crossDefinite`
        // tracks whether the cross size is author-given (so a single
        // line can grow to match per CSS Flexbox 1 §9.6).
        $containerMain = $isColumn ? ($declaredHeight ?? -1.0) : $geo->width;
        $containerCross = $isColumn ? $geo->width : ($declaredHeight ?? -1.0);
        $crossDefinite = $isColumn ? true : ($declaredHeight !== null);

        // CSS Flexbox 1 §5.4: `order` reorders items for layout. Sort
        // is stable on document order for equal `order` values. After
        // the sort, `*-reverse` reverses the array so items appear in
        // reverse layout order; the justify-content swap below mirrors
        // `flex-start` ↔ `flex-end` so packing at main-start still
        // hugs the (now end) edge.
        $children = $this->sortFlexItemsByOrder($children);
        if ($reverseDirection) {
            $children = array_reverse($children);
        }

        // First pass: lay each item out at the container's origin
        // with its declared (or content-derived) size. We use the
        // existing layoutBlock so block-style sizing (margins /
        // padding / borders) works inside items.
        $itemCtx = $context
            ->withContainingBlock($geo->width, $cbHeight)
            ->withOrigin($geo->x, $geo->y)
            ->withLengthContext($this->lengthContextFor($style, $context->lengthContext));
        $itemMains = [];
        $itemCrosses = [];
        $basisCbMain = $isColumn ? $cbHeight : $geo->width;
        foreach ($children as $child) {
            $this->cascade->resolveLengths($child->style, $itemCtx->lengthContext);
            $this->layoutBox($child, $itemCtx);
            // CSS Flexbox 1 §7.2: `flex-basis` overrides the item's
            // hypothetical main size (width for row, height for
            // column). `auto` / `content` keep the layoutBox value;
            // explicit lengths / percentages / unitless 0 replace it.
            $basis = $this->resolveFlexBasis($child->style, $basisCbMain);
            if ($basis !== null) {
                if ($isColumn) {
                    $child->geometry->height = $basis;
                } else {
                    $child->geometry->width = $basis;
                }
            }
            $itemMains[] = $isColumn ? $child->geometry->outerHeight() : $child->geometry->outerWidth();
            $itemCrosses[] = $isColumn ? $child->geometry->outerWidth() : $child->geometry->outerHeight();
        }

        // CSS Box Alignment 3 §8.1: `normal` resolves to `0px` for
        // flex layout. The main-axis gap reads `column-gap` for row
        // direction, `row-gap` for column direction; the cross-axis
        // gap (between flex lines under `flex-wrap: wrap`) reads the
        // opposite.
        $gap = $this->resolveFlexMainGap($style, $itemCtx->lengthContext, $isColumn);
        $crossGap = $this->resolveFlexGapProperty($style, $isColumn ? 'column-gap' : 'row-gap');

        // CSS Flexbox 1 §6.3: `flex-wrap` controls multi-line flow.
        // `nowrap` (default) keeps everything on one line. `wrap` and
        // `wrap-reverse` partition into multiple lines when items
        // would overflow. Per §9.3 step 5, wrap requires a definite
        // main size — auto main (column with no declared height)
        // falls back to single-line.
        $wrap = $this->flexKeyword($style, 'flex-wrap', 'nowrap');
        $canWrap = ($wrap === 'wrap' || $wrap === 'wrap-reverse') && $containerMain >= 0.0;

        if ($canWrap) {
            $lines = $this->partitionFlexLines($itemMains, $gap, $containerMain);
        } else {
            $lines = [array_keys($itemMains)];
        }

        // If the container's main size is still auto (single-line
        // column with no declared height), shrink-to-fit around items.
        if ($containerMain < 0.0) {
            $singleLineMain = array_sum($itemMains) + $gap * max(0, count($children) - 1);
            $containerMain = $singleLineMain;
        }

        $alignItems = $this->flexKeyword($style, 'align-items', 'stretch');
        $justify = $this->flexKeyword($style, 'justify-content', 'flex-start');
        if ($reverseDirection) {
            $justify = match ($justify) {
                'flex-start', 'start' => 'flex-end',
                'flex-end', 'end' => 'flex-start',
                default => $justify,
            };
        }

        // For each line, run the §9.7 "Resolve the Flexible Lengths"
        // algorithm: iteratively distribute free space proportional to
        // flex-grow / scaled flex-shrink (shrink × base size) and
        // freeze items that hit their min/max main size at each step.
        $lineSlacks = [];
        foreach ($lines as $lineIdx => $indices) {
            $finalMains = $this->resolveFlexLineMainSizes(
                $children,
                $indices,
                $itemMains,
                $isColumn,
                $containerMain,
                $gap,
                $cbWidth,
                $cbHeight,
            );

            $lineUsed = 0.0;
            foreach ($indices as $i) {
                $newOuter = $finalMains[$i];
                $oldOuter = $itemMains[$i];
                $delta = $newOuter - $oldOuter;
                if ($delta !== 0.0) {
                    if ($isColumn) {
                        $children[$i]->geometry->height = max(0.0, $children[$i]->geometry->height + $delta);
                    } else {
                        $children[$i]->geometry->width = max(0.0, $children[$i]->geometry->width + $delta);
                    }
                    $itemMains[$i] = $isColumn
                        ? $children[$i]->geometry->outerHeight()
                        : $children[$i]->geometry->outerWidth();
                }
                $lineUsed += $itemMains[$i];
            }
            $lineUsed += $gap * max(0, count($indices) - 1);
            $lineSlacks[$lineIdx] = max(0.0, $containerMain - $lineUsed);
        }

        // Per-line cross extents (max of item cross sizes within line).
        $lineCrosses = [];
        foreach ($lines as $lineIdx => $indices) {
            $maxCross = 0.0;
            foreach ($indices as $i) {
                $maxCross = max($maxCross, $itemCrosses[$i]);
            }
            $lineCrosses[$lineIdx] = $maxCross;
        }

        // Container cross size: declared if available; otherwise sum
        // of line cross extents plus inter-line cross gaps.
        $totalLineCross = array_sum($lineCrosses) + $crossGap * max(0, count($lines) - 1);
        if (!$crossDefinite) {
            $containerCross = $totalLineCross;
        }

        // CSS Flexbox 1 §9.6: a container with a definite cross size
        // and a single flex line grows that line to fill the container's
        // cross extent (so align-items on the only line aligns against
        // the full container, not just the items' natural extent).
        if (count($lines) === 1 && $crossDefinite && $containerCross > $lineCrosses[0]) {
            $lineCrosses[0] = $containerCross;
        }

        // `wrap-reverse` reverses the cross-axis order of lines.
        if ($wrap === 'wrap-reverse') {
            $lines = array_reverse($lines, true);
            $lineCrosses = array_reverse($lineCrosses, true);
            $lineSlacks = array_reverse($lineSlacks, true);
        }

        // CSS Flexbox 1 §8.3: `align-content` distributes cross-axis
        // slack across multiple flex lines. Single-line containers
        // ignore it (§8.3 explicit). `stretch` (initial) grows each
        // line's cross extent to consume the slack; positional values
        // shift the leading/inter-line cross spacing.
        $alignContent = $this->flexKeyword($style, 'align-content', 'stretch');
        $lineCount = count($lines);
        $crossSlackTotal = max(0.0, $containerCross - $totalLineCross);
        $leadingCrossSpace = 0.0;
        $interLineCrossSpace = $crossGap;
        if ($lineCount > 1 && $crossSlackTotal > 0.0) {
            switch ($alignContent) {
                case 'stretch':
                    // Distribute the slack evenly to each line's
                    // cross extent. Items inside still align within
                    // their (now larger) line via align-items.
                    $bonus = $crossSlackTotal / $lineCount;
                    foreach ($lineCrosses as $idx => $cross) {
                        $lineCrosses[$idx] = $cross + $bonus;
                    }
                    break;
                case 'center':
                    $leadingCrossSpace = $crossSlackTotal / 2.0;
                    break;
                case 'flex-end':
                case 'end':
                    $leadingCrossSpace = $crossSlackTotal;
                    break;
                case 'space-between':
                    // Outer guard already proved $lineCount >= 2.
                    $interLineCrossSpace = $crossGap + $crossSlackTotal / ($lineCount - 1);
                    break;
                case 'space-around':
                    $interLineCrossSpace = $crossGap + $crossSlackTotal / $lineCount;
                    $leadingCrossSpace = $interLineCrossSpace / 2.0 - $crossGap / 2.0;
                    break;
                case 'space-evenly':
                    $interLineCrossSpace = $crossGap + $crossSlackTotal / ($lineCount + 1);
                    $leadingCrossSpace = $interLineCrossSpace - $crossGap;
                    break;
                    // 'flex-start' / 'start' / 'normal' → no shift.
            }
        }

        // Place items: per line, run justify-content on main, place
        // at line's cross offset + per-item alignment within line.
        $mainOrigin = $isColumn ? $geo->y : $geo->x;
        $crossOrigin = $isColumn ? $geo->x : $geo->y;
        $lineCrossOffset = $leadingCrossSpace;
        foreach ($lines as $lineIdx => $indices) {
            $lineSlack = $lineSlacks[$lineIdx];
            $lineCross = $lineCrosses[$lineIdx];
            $count = count($indices);

            $leadingSpace = 0.0;
            $itemSpace = $gap;
            switch ($justify) {
                case 'center':
                    $leadingSpace = $lineSlack / 2.0;
                    break;
                case 'flex-end':
                case 'end':
                case 'right':
                    $leadingSpace = $lineSlack;
                    break;
                case 'space-between':
                    if ($count > 1) {
                        $itemSpace = $gap + $lineSlack / ($count - 1);
                    }
                    break;
                case 'space-around':
                    if ($count > 0) {
                        $itemSpace = $gap + $lineSlack / $count;
                        $leadingSpace = $itemSpace / 2.0 - $gap / 2.0;
                    }
                    break;
                case 'space-evenly':
                    if ($count > 0) {
                        $itemSpace = $gap + $lineSlack / ($count + 1);
                        $leadingSpace = $itemSpace - $gap;
                    }
                    break;
                    // 'flex-start' / 'start' / 'left' → no leading space.
            }

            $cursor = $mainOrigin + $leadingSpace;
            foreach ($indices as $i) {
                $child = $children[$i];
                $childGeo = $child->geometry;
                $itemMain = $itemMains[$i];
                $itemCross = $itemCrosses[$i];

                // Current main / cross edges (the layoutBox call placed
                // the item at the container's content-edge origin).
                if ($isColumn) {
                    $currentMainEdge = $childGeo->y - $childGeo->paddingTop - $childGeo->borderTop - $childGeo->marginTop;
                    $currentCrossEdge = $childGeo->x - $childGeo->paddingLeft - $childGeo->borderLeft - $childGeo->marginLeft;
                } else {
                    $currentMainEdge = $childGeo->x - $childGeo->paddingLeft - $childGeo->borderLeft - $childGeo->marginLeft;
                    $currentCrossEdge = $childGeo->y - $childGeo->paddingTop - $childGeo->borderTop - $childGeo->marginTop;
                }
                $mainShift = $cursor - $currentMainEdge;

                // align-items / align-self cross placement *within
                // this line* (not the whole container).
                $alignSelf = $this->flexKeyword($child->style, 'align-self', 'auto');
                $effectiveAlign = $alignSelf === 'auto' ? $alignItems : $alignSelf;
                $crossSlack = $lineCross - $itemCross;
                $alignedCrossInLine = 0.0;
                switch ($effectiveAlign) {
                    case 'center':
                        $alignedCrossInLine = $crossSlack / 2.0;
                        break;
                    case 'flex-end':
                    case 'end':
                        $alignedCrossInLine = $crossSlack;
                        break;
                    case 'stretch':
                        // Stretch to fill the *line's* cross extent
                        // when the item's cross dimension is auto.
                        $crossProp = $isColumn ? 'width' : 'height';
                        if ($this->isAuto($child->style->get($crossProp)) && $crossSlack > 0.0) {
                            if ($isColumn) {
                                $childGeo->width = $lineCross - $childGeo->marginLeft - $childGeo->marginRight
                                    - $childGeo->borderLeft - $childGeo->borderRight
                                    - $childGeo->paddingLeft - $childGeo->paddingRight;
                            } else {
                                $childGeo->height = $lineCross - $childGeo->marginTop - $childGeo->marginBottom
                                    - $childGeo->borderTop - $childGeo->borderBottom
                                    - $childGeo->paddingTop - $childGeo->paddingBottom;
                            }
                        }
                        break;
                        // 'flex-start' / 'start' → no in-line shift.
                }

                $targetCrossEdge = $crossOrigin + $lineCrossOffset + $alignedCrossInLine;
                $crossShift = $targetCrossEdge - $currentCrossEdge;

                // shiftSubtree takes (dy, dx). Map main/cross → y/x
                // per direction.
                if ($mainShift !== 0.0 || $crossShift !== 0.0) {
                    if ($isColumn) {
                        $this->shiftSubtree($child, $mainShift, $crossShift);
                    } else {
                        $this->shiftSubtree($child, $crossShift, $mainShift);
                    }
                }
                $cursor += $itemMain + $itemSpace;
            }

            $lineCrossOffset += $lineCross + $interLineCrossSpace;
        }

        // Container final dimensions. The main-axis size is the
        // declared value (or shrink-to-fit); the cross-axis size is
        // the declared value (or sum of line cross extents).
        if ($isColumn) {
            $geo->height = $declaredHeight ?? $containerMain;
            // Container width was already set above.
        } else {
            $geo->height = $declaredHeight ?? $totalLineCross;
        }

        // Min/max-width and -height clamping (same as layoutBlock).
        $this->clampMinMax($style, $geo, $cbWidth, $cbHeight);

        return $geo->outerHeight();
    }

    /**
     * CSS Grid Layout 2 §3 — `display: grid`. Phase-2 MVP:
     *  - `grid-template-columns: <length>+` / `grid-template-rows:
     *    <length>+` define explicit track sizes. The `<length>` list
     *    becomes a per-track size array.
     *  - Item placement via `grid-column-start` / `grid-column-end`
     *    / `grid-row-start` / `grid-row-end` (and the `grid-column`
     *    / `grid-row` shorthands). 1-based line numbers; negative
     *    indices count from the end (`-1` = last line). `auto` on
     *    either end means "auto-flow into the next free cell".
     *  - Auto-flow walks items in document order, placing un-placed
     *    items at the next free cell row-by-row (CSS Grid Layout 2
     *    §6.3 — `grid-auto-flow: row`). Items with at least one
     *    explicit placement value are placed first; un-placed items
     *    fill the remaining slots.
     *  - Each cell's box gets `width = columnTrack[c]`, `height =
     *    rowTrack[r]`. Multi-cell items sum their spanned tracks +
     *    the gaps between.
     *  - `column-gap` / `row-gap` insert spacing between tracks
     *    (consistent with flexbox + multi-column).
     *
     * Deferred (Phase-2 follow-ups): `fr` unit (intrinsic flex
     * sizing), `auto` track sizing (needs min/max-content), `span
     * N`, `repeat()` / `auto-fill` / `auto-fit`, `grid-template-
     * areas`, `grid-auto-{columns,rows}` implicit tracks beyond the
     * declared template, `justify-self` / `align-self`, subgrid.
     */
    private function layoutGridBox(\Phpdftk\HtmlToPdf\Box\GridBox $box, LayoutContext $context): float
    {
        $style = $box->style;
        $cbWidth = $context->containingBlockWidth;
        $cbHeight = $context->containingBlockHeight;
        $geo = $box->geometry;

        // Container box-frame resolution mirrors layoutFlexBox.
        $geo->marginTop = $this->resolveLength($style->get('margin-top'), $cbWidth);
        $geo->marginRight = $this->resolveLength($style->get('margin-right'), $cbWidth);
        $geo->marginBottom = $this->resolveLength($style->get('margin-bottom'), $cbWidth);
        $geo->marginLeft = $this->resolveLength($style->get('margin-left'), $cbWidth);
        $geo->paddingTop = $this->resolveLength($style->get('padding-top'), $cbWidth);
        $geo->paddingRight = $this->resolveLength($style->get('padding-right'), $cbWidth);
        $geo->paddingBottom = $this->resolveLength($style->get('padding-bottom'), $cbWidth);
        $geo->paddingLeft = $this->resolveLength($style->get('padding-left'), $cbWidth);
        $geo->borderTop = $this->resolveBorderWidth($style, 'top');
        $geo->borderRight = $this->resolveBorderWidth($style, 'right');
        $geo->borderBottom = $this->resolveBorderWidth($style, 'bottom');
        $geo->borderLeft = $this->resolveBorderWidth($style, 'left');

        $widthValue = $style->get('width');
        $widthAuto = $this->isAuto($widthValue);
        $geo->width = $widthAuto
            ? max(
                0.0,
                $cbWidth - $geo->marginLeft - $geo->marginRight
                    - $geo->borderLeft - $geo->borderRight
                    - $geo->paddingLeft - $geo->paddingRight,
            )
            : $this->resolveLength($widthValue, $cbWidth);

        $geo->x = $context->originX + $geo->marginLeft + $geo->borderLeft + $geo->paddingLeft;
        $geo->y = $context->originY + $geo->marginTop + $geo->borderTop + $geo->paddingTop;

        $columnGap = $this->resolveGridGap($style->get('column-gap'), $cbWidth);
        $rowGap = $this->resolveGridGap($style->get('row-gap'), $cbHeight);
        // Compute the explicit-height-for-fr early so it's available
        // both for auto-fill row track resolution and the later fr
        // pass.
        $declaredHeightForFr = $this->resolveExplicitHeightOrNull($style, $cbHeight);
        $columnDescriptors = $this->parseGridTrackList(
            $style->get('grid-template-columns'),
            availableSize: max(0.0, $geo->width),
            gap: $columnGap,
        );
        $rowDescriptors = $this->parseGridTrackList(
            $style->get('grid-template-rows'),
            availableSize: max(0.0, $declaredHeightForFr ?? 0.0),
            gap: $rowGap,
        );

        // CSS Grid Layout 2 §7.3 — `grid-template-areas` defines the
        // named-area map. Each area's rectangle drives `grid-area:
        // <name>` placement, and the area-grid dimensions provide
        // implicit row/column counts when grid-template-{rows,
        // columns} aren't supplied.
        $areaMap = $this->parseGridTemplateAreas($style->get('grid-template-areas'));

        // If no explicit tracks but template-areas IS set, derive
        // implicit track counts from the area-grid shape so author
        // CSS that uses only template-areas still lays out.
        $areaRows = $areaMap['rowCount'] ?? 0;
        $areaCols = $areaMap['colCount'] ?? 0;
        if ($columnDescriptors === [] && $areaCols > 0) {
            // Equal-width implicit columns spanning the container.
            $each = max(0.0, ($geo->width - $columnGap * max(0, $areaCols - 1)) / $areaCols);
            $columnDescriptors = array_fill(0, $areaCols, ['type' => 'length', 'value' => $each]);
        }
        if ($rowDescriptors === [] && $areaRows > 0 && $declaredHeightForFr !== null) {
            $each = max(0.0, ($declaredHeightForFr - $rowGap * max(0, $areaRows - 1)) / $areaRows);
            $rowDescriptors = array_fill(0, $areaRows, ['type' => 'length', 'value' => $each]);
        }

        // Resolve fr → pixels against the container's main axis.
        $columnTracks = $this->resolveGridTrackSizes($columnDescriptors, $geo->width, $columnGap);
        $rowTracks = $this->resolveGridTrackSizes(
            $rowDescriptors,
            $declaredHeightForFr ?? 0.0,
            $rowGap,
        );
        // CSS Grid Layout 2 §7.4 — implicit-track sizing via
        // `grid-auto-rows` / `grid-auto-columns`. Phase-2 honours
        // a single `<length>` value; `auto` / `min-content` /
        // `max-content` resolve to zero pending the min/max-content
        // sizing pass. Implicit tracks grow during pass 1 (for
        // explicit placements past the declared grid) and pass 2
        // (for auto-flow that runs past the last row).
        $autoRowSize = $this->resolveGridAutoTrackSize($style->get('grid-auto-rows'));
        $autoColSize = $this->resolveGridAutoTrackSize($style->get('grid-auto-columns'));
        // CSS Grid Layout 2 §6.3 — `grid-auto-flow` direction +
        // `dense` modifier. `row` flows row-major (grow rows for
        // overflow); `column` flows column-major (grow columns).
        // `dense` resets the per-item cursor to 0 before each
        // placement so smaller items can backfill earlier gaps.
        [$flowDirection, $isDense] = $this->resolveGridAutoFlow(
            $style->get('grid-auto-flow'),
        );

        if ($box->children === []) {
            $geo->height = $declaredHeightForFr ?? $this->gridTotalExtent($rowTracks, $rowGap);
            $this->clampMinMax($style, $geo, $cbWidth, $cbHeight);
            return $geo->outerHeight();
        }

        // Default to a single column / row when no template is given
        // so author CSS with placement still has a sensible grid.
        if ($columnTracks === []) {
            $columnTracks = [max(0.0, $geo->width)];
        }
        if ($rowTracks === []) {
            // Implicit row sizing falls back to one row of "auto"
            // height — but auto track sizing isn't shipped yet, so
            // we use the container's declared height or 0.
            $rowTracks = [$declaredHeightForFr ?? 0.0];
        }

        // CSS Grid Layout 1 §6.4 / CSS Box Layout §3.5 — when items
        // declare `order: <int>` the auto-placement pass walks them in
        // (order ASC, DOM-index ASC) order rather than DOM order. The
        // default `order: 0` keeps DOM order intact for items that
        // don't opt in.
        $orderedChildren = [];
        foreach ($box->children as $domIdx => $child) {
            $orderedChildren[] = [
                'box' => $child,
                'order' => $this->resolveGridOrder($child),
                'domIdx' => $domIdx,
            ];
        }
        usort($orderedChildren, function (array $a, array $b): int {
            return $a['order'] <=> $b['order'] ?: $a['domIdx'] <=> $b['domIdx'];
        });
        // Resolve each child's grid placement.
        /** @var list<array{box: Box, row: int, rowSpan: int, col: int, colSpan: int, autoRow: bool, autoCol: bool}> $placements */
        $placements = [];
        foreach ($orderedChildren as $entry) {
            $child = $entry['box'];
            if ($child instanceof \Phpdftk\HtmlToPdf\Box\TextBox
                || $child instanceof \Phpdftk\HtmlToPdf\Box\InlineBox
            ) {
                // Inline / text children of a grid container produce
                // anonymous boxes per CSS Display 3 §3.4; Phase-2
                // skips them rather than synthesising blocks. Author
                // grids virtually always wrap items in block-level
                // children, so this matches the common case.
                continue;
            }
            $placements[] = $this->resolveGridPlacement(
                $child,
                count($columnTracks),
                count($rowTracks),
                $areaMap['areas'] ?? [],
            );
        }

        // Pass 1: mark cells occupied for items with both axes
        // explicitly placed. The flow direction picks which axis
        // grows for out-of-range placements: `row` grows implicit
        // rows when the row position overflows; `column` grows
        // implicit columns when the column position overflows.
        // Overflow on the OTHER axis still drops silently (matches
        // browser behaviour — `grid-auto-flow: row` doesn't grow
        // the explicit-column count).
        /** @var array<int, array<int, true>> $occupied  occupied[row][col] = true */
        $occupied = [];
        foreach ($placements as &$p) {
            if (!$p['autoRow'] && !$p['autoCol']) {
                if ($flowDirection === 'column') {
                    if ($p['row'] >= count($rowTracks)) {
                        continue;
                    }
                    if ($p['col'] + $p['colSpan'] > count($columnTracks)) {
                        $this->growGridRows($columnTracks, $p['col'] + $p['colSpan'], $autoColSize);
                    }
                } else {
                    if ($p['col'] >= count($columnTracks)) {
                        continue;
                    }
                    if ($p['row'] + $p['rowSpan'] > count($rowTracks)) {
                        $this->growGridRows($rowTracks, $p['row'] + $p['rowSpan'], $autoRowSize);
                    }
                }
                $this->markGridOccupied($occupied, $p['row'], $p['col'], $p['rowSpan'], $p['colSpan']);
            }
        }
        unset($p);

        // Pass 2: auto-flow the remaining items (CSS Grid Layout 2
        // §6.3). The flow direction picks the iteration order:
        //   `row` → row-major (outer = row, inner = column)
        //   `column` → column-major (outer = column, inner = row)
        // `dense` resets the cursor before EACH item so a smaller
        // later item can backfill an earlier gap (default sparse
        // mode advances the cursor monotonically). The grid grows
        // implicit tracks on the OUTER axis as needed.
        $cursorRow = 0;
        $cursorCol = 0;
        $maxImplicitTracks = 1024;
        foreach ($placements as &$p) {
            if (!$p['autoRow'] && !$p['autoCol']) {
                continue;
            }
            if ($isDense) {
                // Dense mode resets the cursor for every placement
                // so earlier gaps get backfilled.
                $cursorRow = 0;
                $cursorCol = 0;
            }
            if ($p['autoRow'] && $p['autoCol']) {
                // Fully auto — walk in the flow's outer direction.
                if ($flowDirection === 'column') {
                    [$placedRow, $placedCol] = $this->placeFullyAutoColumnMajor(
                        $p['rowSpan'],
                        $p['colSpan'],
                        $occupied,
                        $rowTracks,
                        $columnTracks,
                        $autoColSize,
                        $cursorRow,
                        $cursorCol,
                        $maxImplicitTracks,
                    );
                } else {
                    [$placedRow, $placedCol] = $this->placeFullyAutoRowMajor(
                        $p['rowSpan'],
                        $p['colSpan'],
                        $occupied,
                        $rowTracks,
                        $columnTracks,
                        $autoRowSize,
                        $cursorRow,
                        $cursorCol,
                        $maxImplicitTracks,
                    );
                }
                if ($placedRow !== null) {
                    $p['row'] = $placedRow;
                    $p['col'] = $placedCol;
                    $this->markGridOccupied($occupied, $placedRow, $placedCol, $p['rowSpan'], $p['colSpan']);
                }
                continue;
            }
            if ($p['autoRow']) {
                // Fixed col, search rows from the top — growing
                // implicit rows when none of the explicit ones fit.
                $c = $p['col'];
                if ($c < 0 || $c >= count($columnTracks)) {
                    continue;
                }
                $r = 0;
                $iter = 0;
                while ($iter++ < $maxImplicitTracks) {
                    if ($r + $p['rowSpan'] > count($rowTracks)) {
                        $this->growGridRows($rowTracks, $r + $p['rowSpan'], $autoRowSize);
                    }
                    if (!$this->isGridRangeOccupied($occupied, $r, $c, $p['rowSpan'], $p['colSpan'])) {
                        $p['row'] = $r;
                        $this->markGridOccupied($occupied, $r, $c, $p['rowSpan'], $p['colSpan']);
                        break;
                    }
                    $r++;
                }
                continue;
            }
            // Auto col only — fixed row, search columns left-to-right.
            $r = $p['row'];
            if ($r < 0) {
                continue;
            }
            if ($r >= count($rowTracks)) {
                $this->growGridRows($rowTracks, $r + 1, $autoRowSize);
            }
            $c = 0;
            $iter = 0;
            while ($iter++ < $maxImplicitTracks) {
                if ($c + $p['colSpan'] > count($columnTracks)) {
                    if ($flowDirection === 'column') {
                        $this->growGridRows($columnTracks, $c + $p['colSpan'], $autoColSize);
                    } else {
                        break;
                    }
                }
                if (!$this->isGridRangeOccupied($occupied, $r, $c, $p['rowSpan'], $p['colSpan'])) {
                    $p['col'] = $c;
                    $this->markGridOccupied($occupied, $r, $c, $p['rowSpan'], $p['colSpan']);
                    break;
                }
                $c++;
            }
        }
        unset($p);

        // Pass 2.5: content-size `auto` / `min-content` / `max-content`
        // tracks per CSS Grid Layout 2 §11. Measure each placed
        // item's max-content; for items spanning one auto track,
        // bump that track to at least the item's max. Items
        // spanning MULTIPLE auto tracks distribute equally as a
        // Phase-2 simplification (the spec prescribes a more
        // intricate distribution but equal-share is correct often
        // enough to be useful).
        $this->resolveGridContentSizedTracks(
            $columnTracks,
            $columnDescriptors,
            $placements,
            $context,
            isColumnAxis: true,
        );
        $this->resolveGridContentSizedTracks(
            $rowTracks,
            $rowDescriptors,
            $placements,
            $context,
            isColumnAxis: false,
        );

        // Pass 3: lay out each child inside its assigned cell.
        // Track-prefix sums let us cheaply compute (x, y, width,
        // height) per cell range.
        $colOffsets = $this->gridTrackOffsets($columnTracks, $columnGap);
        $rowOffsets = $this->gridTrackOffsets($rowTracks, $rowGap);
        foreach ($placements as $p) {
            // Skip items that didn't successfully place.
            if ($p['row'] < 0 || $p['col'] < 0) {
                continue;
            }
            if ($p['row'] >= count($rowTracks) || $p['col'] >= count($columnTracks)) {
                continue;
            }
            $cellX = $geo->x + $colOffsets[$p['col']];
            $cellY = $geo->y + $rowOffsets[$p['row']];
            $cellWidth = $this->gridSpanExtent($columnTracks, $p['col'], $p['colSpan'], $columnGap);
            $cellHeight = $this->gridSpanExtent($rowTracks, $p['row'], $p['rowSpan'], $rowGap);

            // CSS Box Alignment 3 §6.2 — `justify-self` (inline axis)
            // and `align-self` (block axis). `auto` → `stretch` for
            // Grid (matching CSS Grid Layout 2 §11). Other keywords:
            //   `start` — align to cell's main-start
            //   `end`   — align to cell's main-end
            //   `center` — centered
            //   `stretch` — fill the cell
            $justify = $this->gridSelfKeyword($p['box'], 'justify-self');
            $alignS = $this->gridSelfKeyword($p['box'], 'align-self');
            $isStretchX = $justify === 'stretch';
            $isStretchY = $alignS === 'stretch';

            $childCtx = $context
                ->withContainingBlock($cellWidth, $cellHeight)
                ->withOrigin($cellX, $cellY);
            $this->cascade->resolveLengths($p['box']->style, $childCtx->lengthContext);
            $this->layoutBox($p['box'], $childCtx);

            $childGeo = $p['box']->geometry;
            $childOuterWidth = $childGeo->outerWidth();
            $childOuterHeight = $childGeo->outerHeight();
            if ($isStretchX) {
                // Stretch fills the cell minus the child's own
                // surroundings — matches Phase-2 default behaviour.
                $childGeo->width = $cellWidth
                    - $childGeo->marginLeft - $childGeo->marginRight
                    - $childGeo->borderLeft - $childGeo->borderRight
                    - $childGeo->paddingLeft - $childGeo->paddingRight;
                $childOuterWidth = $childGeo->outerWidth();
            }
            if ($isStretchY && $childOuterHeight < $cellHeight) {
                $childGeo->height = $cellHeight
                    - $childGeo->marginTop - $childGeo->marginBottom
                    - $childGeo->borderTop - $childGeo->borderBottom
                    - $childGeo->paddingTop - $childGeo->paddingBottom;
                $childOuterHeight = $childGeo->outerHeight();
            }
            // Reposition for non-stretch alignment. `start` keeps the
            // existing origin; `end` / `center` shift the child within
            // the cell. Since the box uses outer extents for stacking,
            // shift `geometry->x` / `geometry->y` accordingly.
            if (!$isStretchX) {
                $slackX = $cellWidth - $childOuterWidth;
                $shiftX = match ($justify) {
                    'end' => $slackX,
                    'center' => $slackX / 2,
                    default => 0.0, // 'start' or unknown
                };
                if ($shiftX !== 0.0) {
                    $this->shiftSubtree($p['box'], 0.0, $shiftX);
                }
            }
            if (!$isStretchY) {
                $slackY = $cellHeight - $childOuterHeight;
                $shiftY = match ($alignS) {
                    'end' => $slackY,
                    'center' => $slackY / 2,
                    default => 0.0,
                };
                if ($shiftY !== 0.0) {
                    $this->shiftSubtree($p['box'], $shiftY, 0.0);
                }
            }
        }

        $declaredHeight = $this->resolveExplicitHeightOrNull($style, $cbHeight);
        $geo->height = $declaredHeight ?? $this->gridTotalExtent($rowTracks, $rowGap);
        $this->clampMinMax($style, $geo, $cbWidth, $cbHeight);

        return $geo->outerHeight();
    }

    /**
     * Parse a `grid-template-columns` / `grid-template-rows` value
     * into a list of track descriptors per CSS Grid Layout 2 §7.
     * Each descriptor is `['type' => 'length', 'value' => float]`
     * (fixed pixels) or `['type' => 'fr', 'value' => float]`
     * (proportional flex). `repeat(N, <tracks>)` is expanded
     * inline. Returns `[]` for `none` (initial) or empty values.
     * Phase-2 still defers `auto`, `min-content`, `max-content`,
     * `minmax()`, `fit-content()`, `auto-fill` / `auto-fit`.
     *
     * @return list<array{type: string, value: float}>
     */
    /**
     * CSS Grid 1 §7.2.3 — count how many `auto-fill` / `auto-fit`
     * tracks fit inside `$availableSize` given the repeated track
     * pattern in `$repeatTracks`. Each iteration of the pattern uses
     * `sum(track sizes) + (n-1) × gap`, plus one inter-iteration gap.
     *
     * Returns 1 when the pattern's track sizes can't be resolved to a
     * fixed length — at minimum one iteration must be emitted so the
     * grid still has tracks to place items into.
     *
     * @param list<\Phpdftk\Css\Value\Value> $repeatTracks
     */
    private function computeAutoFillCount(array $repeatTracks, float $availableSize, float $gap): int
    {
        if ($availableSize <= 0.0) {
            return 1;
        }
        // Measure one iteration's track sizes.
        $iterTracks = [];
        foreach ($repeatTracks as $r) {
            $this->collectGridTrackDescriptors($r, $iterTracks);
        }
        if ($iterTracks === []) {
            return 1;
        }
        $iterSize = 0.0;
        foreach ($iterTracks as $idx => $t) {
            // Anything non-fixed (`fr`, intrinsic) makes the count
            // indeterminate per §7.2.3; fall back to 1.
            if (($t['type'] ?? null) !== 'length') {
                return 1;
            }
            $iterSize += max(0.0, $t['value']);
            if ($idx > 0) {
                $iterSize += $gap;
            }
        }
        if ($iterSize <= 0.0) {
            return 1;
        }
        // n iterations + (n-1) inter-iteration gaps must fit in
        // availableSize. Solve: n·iterSize + (n−1)·gap ≤ available.
        $count = (int) floor(($availableSize + $gap) / ($iterSize + $gap));
        return max(1, $count);
    }

    private function parseGridTrackList(
        ?\Phpdftk\Css\Value\Value $value,
        float $availableSize = 0.0,
        float $gap = 0.0,
    ): array {
        if ($value === null
            || ($value instanceof Keyword && strtolower($value->name) === 'none')
        ) {
            return [];
        }
        $tracks = [];
        $this->collectGridTrackDescriptors($value, $tracks, $availableSize, $gap);
        return $tracks;
    }

    /**
     * Walk a track-list value recursively, appending descriptors
     * for `<length>` / `<flex>` tracks. `repeat()` is expanded; any
     * other shape is silently skipped (Phase-2 will widen later).
     *
     * @param list<array{type: string, value: float}> $out
     */
    private function collectGridTrackDescriptors(
        \Phpdftk\Css\Value\Value $value,
        array &$out,
        float $availableSize = 0.0,
        float $gap = 0.0,
    ): void {
        if ($value instanceof \Phpdftk\Css\Value\ValueList) {
            foreach ($value->values as $v) {
                $this->collectGridTrackDescriptors($v, $out, $availableSize, $gap);
            }
            return;
        }
        if ($value instanceof Length) {
            $out[] = ['type' => 'length', 'value' => $value->value];
            return;
        }
        if ($value instanceof \Phpdftk\Css\Value\CssFunction) {
            $name = strtolower($value->name);
            if ($name === 'fr') {
                $first = $value->arguments[0] ?? null;
                $count = $this->numericValueOrNull($first);
                if ($count !== null && $count > 0) {
                    $out[] = ['type' => 'fr', 'value' => $count];
                }
                return;
            }
            if ($name === 'repeat') {
                // `repeat(<count>, <track>+)`. The count is either an
                // explicit integer (`repeat(3, ...)`) or one of the
                // `auto-fill` / `auto-fit` keywords (CSS Grid 1 §7.2.3).
                $countVal = $value->arguments[0] ?? null;
                $count = null;
                if ($countVal instanceof \Phpdftk\Css\Value\Integer) {
                    $count = $countVal->value;
                } elseif ($countVal instanceof \Phpdftk\Css\Value\Number) {
                    $count = (int) $countVal->value;
                } elseif ($countVal instanceof Keyword) {
                    $keyword = strtolower($countVal->name);
                    if ($keyword === 'auto-fill' || $keyword === 'auto-fit') {
                        $rest = array_slice($value->arguments, 1);
                        $count = $this->computeAutoFillCount(
                            $rest,
                            $availableSize,
                            $gap,
                        );
                    }
                }
                if ($count === null || $count < 1) {
                    return;
                }
                $rest = array_slice($value->arguments, 1);
                for ($i = 0; $i < $count; $i++) {
                    foreach ($rest as $r) {
                        $this->collectGridTrackDescriptors($r, $out, $availableSize, $gap);
                    }
                }
                return;
            }
            if ($name === 'minmax') {
                // `minmax(<min>, <max>)` — Phase-2 honours the max
                // size when it's a fixed length so auto-fill can
                // count tracks. Falls through to skip otherwise.
                $maxArg = $value->arguments[1] ?? null;
                if ($maxArg instanceof Length) {
                    $out[] = ['type' => 'length', 'value' => $maxArg->value];
                    return;
                }
                $minArg = $value->arguments[0] ?? null;
                if ($minArg instanceof Length) {
                    $out[] = ['type' => 'length', 'value' => $minArg->value];
                    return;
                }
                // Both intrinsic — leave as 0 placeholder.
                $out[] = ['type' => 'length', 'value' => 0.0];
                return;
            }
        }
        // CSS Grid Layout 2 §7.2 — `auto` / `min-content` /
        // `max-content` track sizes resolve via the content-sizing
        // pass below. Recorded as descriptors so `resolveGridTrackSizes`
        // leaves them at zero for the fr pass and the post-placement
        // measurement pass fills them in.
        if ($value instanceof Keyword) {
            $name = strtolower($value->name);
            if ($name === 'auto' || $name === 'min-content' || $name === 'max-content') {
                $out[] = ['type' => $name, 'value' => 0.0];
            }
        }
        // Other shapes (Percentage, etc.) are skipped at MVP rather
        // than guessed.
    }

    private function numericValueOrNull(?\Phpdftk\Css\Value\Value $v): ?float
    {
        if ($v instanceof \Phpdftk\Css\Value\Number || $v instanceof \Phpdftk\Css\Value\Integer) {
            return (float) $v->value;
        }
        return null;
    }

    /**
     * Resolve `grid-auto-flow` to a `[direction, isDense]` pair.
     *   - `row` (initial) → `['row', false]`
     *   - `column` → `['column', false]`
     *   - `dense` alone → `['row', true]` (per spec, bare `dense`
     *     keeps the default direction)
     *   - `row dense` / `dense row` → `['row', true]`
     *   - `column dense` / `dense column` → `['column', true]`
     *
     * @return array{0: string, 1: bool}
     */
    private function resolveGridAutoFlow(?\Phpdftk\Css\Value\Value $value): array
    {
        $direction = 'row';
        $dense = false;
        if ($value instanceof Keyword) {
            $name = strtolower($value->name);
            if ($name === 'column') {
                $direction = 'column';
            } elseif ($name === 'dense') {
                $dense = true;
            }
            return [$direction, $dense];
        }
        if ($value instanceof \Phpdftk\Css\Value\ValueList) {
            foreach ($value->values as $v) {
                if (!$v instanceof Keyword) {
                    continue;
                }
                $name = strtolower($v->name);
                if ($name === 'column') {
                    $direction = 'column';
                } elseif ($name === 'row') {
                    $direction = 'row';
                } elseif ($name === 'dense') {
                    $dense = true;
                }
            }
        }
        return [$direction, $dense];
    }

    /**
     * Row-major auto-placement walker. Iterates row-by-row, then
     * column-by-column within each row, growing implicit rows as
     * needed. Returns `[row, col]` of the placement or `[null, null]`
     * when the iteration cap is hit before finding a free slot.
     *
     * @param array<int, array<int, true>> $occupied
     * @param list<float> $rowTracks  Mutated when implicit rows grow.
     * @param list<float> $columnTracks
     * @return array{0: ?int, 1: ?int}
     */
    private function placeFullyAutoRowMajor(
        int $rowSpan,
        int $colSpan,
        array $occupied,
        array &$rowTracks,
        array $columnTracks,
        float $autoRowSize,
        int &$cursorRow,
        int &$cursorCol,
        int $maxIter,
    ): array {
        $colCount = count($columnTracks);
        $iter = 0;
        while ($iter++ < $maxIter) {
            if ($cursorRow >= count($rowTracks)) {
                $this->growGridRows($rowTracks, $cursorRow + 1, $autoRowSize);
            }
            if ($cursorCol + $colSpan > $colCount) {
                $cursorRow++;
                $cursorCol = 0;
                continue;
            }
            if ($cursorRow + $rowSpan > count($rowTracks)) {
                $this->growGridRows($rowTracks, $cursorRow + $rowSpan, $autoRowSize);
            }
            if (!$this->isGridRangeOccupied($occupied, $cursorRow, $cursorCol, $rowSpan, $colSpan)) {
                $row = $cursorRow;
                $col = $cursorCol;
                $cursorCol += $colSpan;
                return [$row, $col];
            }
            $cursorCol++;
        }
        return [null, null];
    }

    /**
     * Column-major auto-placement walker, symmetric to the row-major
     * variant. Iterates column-by-column, then row-by-row within
     * each column, growing implicit columns as needed.
     *
     * @param array<int, array<int, true>> $occupied
     * @param list<float> $rowTracks
     * @param list<float> $columnTracks  Mutated when implicit cols grow.
     * @return array{0: ?int, 1: ?int}
     */
    private function placeFullyAutoColumnMajor(
        int $rowSpan,
        int $colSpan,
        array $occupied,
        array $rowTracks,
        array &$columnTracks,
        float $autoColSize,
        int &$cursorRow,
        int &$cursorCol,
        int $maxIter,
    ): array {
        $rowCount = count($rowTracks);
        $iter = 0;
        while ($iter++ < $maxIter) {
            if ($cursorCol >= count($columnTracks)) {
                $this->growGridRows($columnTracks, $cursorCol + 1, $autoColSize);
            }
            if ($cursorRow + $rowSpan > $rowCount) {
                $cursorCol++;
                $cursorRow = 0;
                continue;
            }
            if ($cursorCol + $colSpan > count($columnTracks)) {
                $this->growGridRows($columnTracks, $cursorCol + $colSpan, $autoColSize);
            }
            if (!$this->isGridRangeOccupied($occupied, $cursorRow, $cursorCol, $rowSpan, $colSpan)) {
                $row = $cursorRow;
                $col = $cursorCol;
                $cursorRow += $rowSpan;
                return [$row, $col];
            }
            $cursorRow++;
        }
        return [null, null];
    }

    /**
     * Fill in `auto` / `min-content` / `max-content` track sizes by
     * measuring placed items. Each placed item contributes its
     * max-content (or min-content for `min-content` tracks) to the
     * tracks it spans. Multi-track spans distribute equally as a
     * Phase-2 simplification.
     *
     * @param array<int, float> $resolved      Mutated track sizes.
     * @param list<array{type: string, value: float}> $descriptors
     * @param list<array{box: Box, row: int, rowSpan: int, col: int, colSpan: int, autoRow: bool, autoCol: bool}> $placements
     */
    private function resolveGridContentSizedTracks(
        array &$resolved,
        array $descriptors,
        array $placements,
        LayoutContext $context,
        bool $isColumnAxis,
    ): void {
        // Bail when there are no content-sized tracks to resolve.
        $hasContentTrack = false;
        foreach ($descriptors as $d) {
            if ($d['type'] === 'auto' || $d['type'] === 'min-content' || $d['type'] === 'max-content') {
                $hasContentTrack = true;
                break;
            }
        }
        if (!$hasContentTrack) {
            return;
        }
        foreach ($placements as $p) {
            if ($p['row'] < 0 || $p['col'] < 0) {
                continue;
            }
            $start = $isColumnAxis ? $p['col'] : $p['row'];
            $span = $isColumnAxis ? $p['colSpan'] : $p['rowSpan'];
            // Find which spanned tracks are content-sized.
            $contentTrackIndices = [];
            for ($i = $start; $i < $start + $span && $i < count($descriptors); $i++) {
                $type = $descriptors[$i]['type'];
                if ($type === 'auto' || $type === 'min-content' || $type === 'max-content') {
                    $contentTrackIndices[] = $i;
                }
            }
            if ($contentTrackIndices === []) {
                continue;
            }
            // Measure item intrinsic sizes. For the column axis we
            // want widths; for the row axis we'd want heights. The
            // measurement function returns widths, so row-axis
            // sizing reuses width as a Phase-2 approximation — row
            // auto sizing typically gets fed item heights via
            // declared CSS, which the post-layout content height
            // override handles. Refining row-axis intrinsic sizing
            // is a follow-up.
            if (!$isColumnAxis) {
                continue;
            }
            $mm = $this->measureMinMaxContent($p['box'], $context);
            $intrinsic = $mm['max'];
            // For the simplest "use min-content" track:
            foreach ($contentTrackIndices as $i) {
                $type = $descriptors[$i]['type'];
                $size = $type === 'min-content' ? $mm['min'] : $intrinsic;
                // Distribute the item's intrinsic size equally
                // across its content-sized tracks. The track's
                // current size only grows; it never shrinks under
                // a smaller item.
                $share = $size / max(1, count($contentTrackIndices));
                if (($resolved[$i] ?? 0.0) < $share) {
                    $resolved[$i] = $share;
                }
            }
        }
    }

    /**
     * Intrinsic-size measurement for a box's content. Returns
     * `['min' => <min-content>, 'max' => <max-content>]` per CSS
     * Sizing 3 §5.1. Phase-2 implementation:
     *   - `TextBox`: min = widest single word (whitespace-tokenised);
     *     max = full text on one line. Uses the cascade-resolved
     *     font for the closest ancestor with a `font-family` /
     *     `font-size`; falls back to the layout context's default
     *     font.
     *   - `BlockBox` / `AnonymousBlockBox` / `TableCellBox`: children
     *     stack vertically so the container can be as narrow as its
     *     widest child's min, and wants its widest child's max.
     *   - `InlineBox`: children flow on one line; min = max-of-mins,
     *     max = sum-of-maxes.
     *   - `AtomicInlineBox`: declared `width` (or 0 fallback).
     *   - Other boxes: descend into children using the block rule.
     *
     * The measurement is intentionally simple and doesn't account
     * for borders / padding / margins on the box itself — callers
     * (e.g. Grid `auto` track sizing) layer those on if needed.
     *
     * @return array{min: float, max: float}
     */
    public function measureMinMaxContent(Box $box, LayoutContext $context): array
    {
        if ($box instanceof TextBox) {
            return $this->measureTextBoxMinMax($box, $context);
        }
        // Any non-text box with an explicit `width` reports that
        // width as both min and max-content. Authors who declared a
        // size for a box have stated its intrinsic preference; the
        // intrinsic-sizing pass shouldn't second-guess it.
        $explicit = $box->style->get('width');
        if ($explicit instanceof Length && $explicit->value > 0.0) {
            return ['min' => $explicit->value, 'max' => $explicit->value];
        }
        if ($box instanceof AtomicInlineBox) {
            return $this->aggregateChildrenMinMax($box, $context, inline: true);
        }
        if ($box instanceof InlineBox) {
            return $this->aggregateChildrenMinMax($box, $context, inline: true);
        }
        // Block / anonymous-block / table cell — children stack
        // vertically, so each child gets its full max-content extent
        // and the container needs the widest of them.
        return $this->aggregateChildrenMinMax($box, $context, inline: false);
    }

    /**
     * @return array{min: float, max: float}
     */
    private function aggregateChildrenMinMax(Box $box, LayoutContext $context, bool $inline): array
    {
        $maxOfMins = 0.0;
        $maxOfMaxes = 0.0;
        $sumOfMaxes = 0.0;
        foreach ($box->children as $child) {
            $cm = $this->measureMinMaxContent($child, $context);
            $maxOfMins = max($maxOfMins, $cm['min']);
            $maxOfMaxes = max($maxOfMaxes, $cm['max']);
            $sumOfMaxes += $cm['max'];
        }
        if ($inline) {
            return ['min' => $maxOfMins, 'max' => $sumOfMaxes];
        }
        return ['min' => $maxOfMins, 'max' => $maxOfMaxes];
    }

    /**
     * Shape the text and report `(widest word, total advance)`.
     * Words split on Unicode whitespace; empty or whitespace-only
     * text returns 0/0.
     *
     * @return array{min: float, max: float}
     */
    private function measureTextBoxMinMax(TextBox $box, LayoutContext $context): array
    {
        $text = $box->text;
        if ($text === '' || trim($text) === '') {
            return ['min' => 0.0, 'max' => 0.0];
        }
        $font = $context->defaultFont;
        if ($font === null) {
            // No font registered — fall back to a character-count
            // heuristic so Grid `auto` tracks still get a usable
            // (if coarse) sizing instead of zero.
            $words = preg_split('/\s+/', trim($text)) ?: [];
            $widest = 0;
            foreach ($words as $w) {
                $widest = max($widest, mb_strlen($w));
            }
            $totalChars = mb_strlen(trim($text));
            // ~6 user units per character is a sane sans-serif average.
            return [
                'min' => (float) $widest * 6.0,
                'max' => (float) $totalChars * 6.0,
            ];
        }
        $fontSizeValue = $box->style->get('font-size');
        $fontSize = $fontSizeValue instanceof Length && $fontSizeValue->value > 0.0
            ? $fontSizeValue->value
            : 12.0;
        $ctx = new \Phpdftk\Text\ShapingContext($font, $fontSize);
        $shaper = new \Phpdftk\Text\Shaper();
        $full = $shaper->shapeRun($text, $ctx);
        $maxAdvance = $full->totalAdvance;
        // CSS Text 3 §6 / Sizing 3 §5.2 — under `overflow-wrap:
        // anywhere`, `line-break: anywhere`, or `word-break: break-all`,
        // soft-wrap opportunities exist between every typographic
        // character, so the min-content size is the widest *grapheme*
        // not the widest word. The cascade is inherited so the parent
        // box's value applies to its TextBox children too — check the
        // text box's resolved style.
        $breakAtGrapheme = $this->intrinsicBreaksAnywhere($box);
        if ($breakAtGrapheme && $full->glyphs !== []) {
            $minAdvance = 0.0;
            foreach ($full->glyphs as $g) {
                if ($g->advanceX > $minAdvance) {
                    $minAdvance = $g->advanceX;
                }
            }
            return ['min' => $minAdvance, 'max' => $maxAdvance];
        }
        $words = preg_split('/\s+/', trim($text)) ?: [];
        $minAdvance = 0.0;
        foreach ($words as $w) {
            if ($w === '') {
                continue;
            }
            $shaped = $shaper->shapeRun($w, $ctx);
            $minAdvance = max($minAdvance, $shaped->totalAdvance);
        }
        return ['min' => $minAdvance, 'max' => $maxAdvance];
    }

    /**
     * Read the box's cascaded `overflow-wrap` / `word-break` /
     * `line-break` and return `true` when soft-wrap opportunities
     * exist between every codepoint (min-content collapses to the
     * widest single glyph advance).
     */
    private function intrinsicBreaksAnywhere(Box $box): bool
    {
        $overflow = $box->style->get('overflow-wrap');
        if ($overflow instanceof \Phpdftk\Css\Value\Keyword
            && strtolower($overflow->name) === 'anywhere'
        ) {
            return true;
        }
        $wordBreak = $box->style->get('word-break');
        if ($wordBreak instanceof \Phpdftk\Css\Value\Keyword
            && strtolower($wordBreak->name) === 'break-all'
        ) {
            return true;
        }
        $lineBreak = $box->style->get('line-break');
        if ($lineBreak instanceof \Phpdftk\Css\Value\Keyword
            && strtolower($lineBreak->name) === 'anywhere'
        ) {
            return true;
        }
        return false;
    }

    /**
     * Resolve `grid-auto-rows` / `grid-auto-columns` to a single
     * pixel size used for every implicit track. Phase-2 honours
     * `<length>` directly; `auto`, `min-content`, `max-content`,
     * and `minmax()` resolve to 0 pending the min/max-content
     * sizing pass. Negative widths clamp to 0.
     */
    private function resolveGridAutoTrackSize(?\Phpdftk\Css\Value\Value $value): float
    {
        if ($value instanceof Length) {
            return max(0.0, $value->value);
        }
        return 0.0;
    }

    /**
     * Append implicit tracks to `$tracks` until it reaches the
     * requested length. Each new track gets the configured
     * `grid-auto-*` size. No-op when `$tracks` is already long
     * enough.
     *
     * @param list<float> $tracks Mutated in place.
     */
    private function growGridRows(array &$tracks, int $requiredCount, float $autoTrackSize): void
    {
        for ($i = count($tracks); $i < $requiredCount; $i++) {
            $tracks[] = $autoTrackSize;
        }
    }

    /**
     * Resolve track descriptors to a list of pixel sizes. Fixed
     * tracks pass through; `fr` tracks divide the remaining space
     * after fixed widths + gaps are subtracted.
     *
     * @param list<array{type: string, value: float}> $tracks
     * @return list<float>
     */
    private function resolveGridTrackSizes(array $tracks, float $containerExtent, float $gap): array
    {
        if ($tracks === []) {
            return [];
        }
        $fixedTotal = 0.0;
        $frTotal = 0.0;
        foreach ($tracks as $t) {
            if ($t['type'] === 'length') {
                $fixedTotal += $t['value'];
            } elseif ($t['type'] === 'fr') {
                $frTotal += $t['value'];
            }
            // `auto` / `min-content` / `max-content` tracks contribute
            // nothing at this stage; the content-sizing pass fills
            // them in after placement is known.
        }
        $gapTotal = $gap * max(0, count($tracks) - 1);
        $frSpace = max(0.0, $containerExtent - $fixedTotal - $gapTotal);
        $resolved = [];
        foreach ($tracks as $t) {
            if ($t['type'] === 'length') {
                $resolved[] = $t['value'];
            } elseif ($t['type'] === 'fr') {
                $share = $frTotal > 0.0 ? $frSpace * ($t['value'] / $frTotal) : 0.0;
                $resolved[] = $share;
            } else {
                // Auto / min-content / max-content — leave at 0; the
                // content-sizing pass overwrites this with the
                // measured max-content of items spanning the track.
                $resolved[] = 0.0;
            }
        }
        return $resolved;
    }

    /**
     * Resolve a `column-gap` / `row-gap` value to user-space units.
     * `normal` (the initial value per CSS Box Alignment 3 §8.1)
     * resolves to 0 for grids — symmetric with flexbox.
     */
    private function resolveGridGap(?\Phpdftk\Css\Value\Value $value, float $containingExtent): float
    {
        if ($value instanceof Keyword && strtolower($value->name) === 'normal') {
            return 0.0;
        }
        return $this->resolveLength($value, $containingExtent);
    }

    /**
     * Resolve a single child's grid placement to `(row, col, rowSpan,
     * colSpan)` in 0-based indices. Returns auto-flags so the
     * auto-flow pass can fill in missing axes.
     *
     * When the area map is supplied and ANY of the child's four
     * grid-{column,row}-{start,end} values references a named area,
     * the area's rectangle wins for that axis. Mixed forms (e.g. a
     * name for start + an integer for end) honour each independently.
     *
     * @param array<string, array{rowStart: int, colStart: int, rowEnd: int, colEnd: int}> $areaMap
     * @return array{box: Box, row: int, rowSpan: int, col: int, colSpan: int, autoRow: bool, autoCol: bool}
     */
    /**
     * Read the child's cascaded `order` (initial: 0). Accepts Integer
     * and Number values; anything else falls back to 0 so unordered
     * items keep DOM order via the secondary sort key.
     */
    private function resolveGridOrder(Box $child): int
    {
        $value = $child->style->get('order');
        if ($value instanceof \Phpdftk\Css\Value\Integer) {
            return $value->value;
        }
        if ($value instanceof \Phpdftk\Css\Value\Number) {
            return (int) round($value->value);
        }
        return 0;
    }

    private function resolveGridPlacement(
        Box $child,
        int $columnCount,
        int $rowCount,
        array $areaMap = [],
    ): array {
        $cs = $child->style->get('grid-column-start');
        $ce = $child->style->get('grid-column-end');
        $rs = $child->style->get('grid-row-start');
        $re = $child->style->get('grid-row-end');
        // CSS Grid Layout 2 §8.5 — if BOTH axes' start values are
        // the same named area, use the area's full rect.
        $name = $this->matchedAreaName($cs, $rs, $ce, $re, $areaMap);
        if ($name !== null) {
            $rect = $areaMap[$name];
            return [
                'box' => $child,
                'row' => $rect['rowStart'],
                'rowSpan' => max(1, $rect['rowEnd'] - $rect['rowStart']),
                'col' => $rect['colStart'],
                'colSpan' => max(1, $rect['colEnd'] - $rect['colStart']),
                'autoRow' => false,
                'autoCol' => false,
            ];
        }
        // Mixed / single-axis name references: convert any
        // name-keyword line value into the area's matching line
        // before normal placement resolution.
        $cs = $this->areaNameToLine($cs, 'colStart', $areaMap);
        $ce = $this->areaNameToLine($ce, 'colEnd', $areaMap);
        $rs = $this->areaNameToLine($rs, 'rowStart', $areaMap);
        $re = $this->areaNameToLine($re, 'rowEnd', $areaMap);
        [$colStart, $colEnd, $autoCol] = $this->resolveGridLine($cs, $ce, $columnCount);
        [$rowStart, $rowEnd, $autoRow] = $this->resolveGridLine($rs, $re, $rowCount);
        return [
            'box' => $child,
            'row' => $rowStart,
            'rowSpan' => max(1, $rowEnd - $rowStart),
            'col' => $colStart,
            'colSpan' => max(1, $colEnd - $colStart),
            'autoRow' => $autoRow,
            'autoCol' => $autoCol,
        ];
    }

    /**
     * When all four grid-{row,col}-{start,end} values reference the
     * SAME named area, return that name. Used to short-circuit the
     * standard placement resolution and use the area's rectangle.
     *
     * @param array<string, array{rowStart: int, colStart: int, rowEnd: int, colEnd: int}> $areaMap
     */
    private function matchedAreaName(
        ?\Phpdftk\Css\Value\Value $cs,
        ?\Phpdftk\Css\Value\Value $rs,
        ?\Phpdftk\Css\Value\Value $ce,
        ?\Phpdftk\Css\Value\Value $re,
        array $areaMap,
    ): ?string {
        $name = $this->lineValueAreaName($rs);
        if ($name === null
            || $this->lineValueAreaName($cs) !== $name
            || $this->lineValueAreaName($re) !== $name
            || $this->lineValueAreaName($ce) !== $name
        ) {
            return null;
        }
        return isset($areaMap[$name]) ? $name : null;
    }

    private function lineValueAreaName(?\Phpdftk\Css\Value\Value $value): ?string
    {
        if (!$value instanceof Keyword) {
            return null;
        }
        $name = strtolower($value->name);
        if ($name === 'auto') {
            return null;
        }
        return $name;
    }

    /**
     * Convert a grid line value that references a named area into
     * an Integer line for the matching axis side. Returns the value
     * unchanged when it's not a name reference.
     *
     * @param array<string, array{rowStart: int, colStart: int, rowEnd: int, colEnd: int}> $areaMap
     */
    private function areaNameToLine(
        ?\Phpdftk\Css\Value\Value $value,
        string $side,
        array $areaMap,
    ): ?\Phpdftk\Css\Value\Value {
        $name = $this->lineValueAreaName($value);
        if ($name === null || !isset($areaMap[$name])) {
            return $value;
        }
        $line = $areaMap[$name][$side];
        // resolveGridLine consumes 1-based line values when fed an
        // Integer; the area map stores 0-based start (inclusive)
        // and 0-based end (exclusive). Convert: start lines map to
        // (index + 1), end lines map to (index + 1) as well (since
        // the end line index IS the inclusive line number).
        return new \Phpdftk\Css\Value\Integer($line + 1);
    }

    /**
     * Parse `grid-template-areas` into a name → rectangle map.
     * Returns `null` (or an empty stub) when the value is `none` or
     * malformed. Each row in the value list is a string whose
     * whitespace-separated tokens name areas (`.` is a null cell).
     *
     * Validation: each name must form a contiguous rectangle; names
     * that don't (L-shape, scattered) drop entirely (their cells
     * become null cells). Rows with mismatched column counts drop
     * the whole template.
     *
     * @return array{rowCount: int, colCount: int, areas: array<string, array{rowStart: int, colStart: int, rowEnd: int, colEnd: int}>}|array{}
     */
    private function parseGridTemplateAreas(?\Phpdftk\Css\Value\Value $value): array
    {
        if ($value === null
            || ($value instanceof Keyword && strtolower($value->name) === 'none')
        ) {
            return [];
        }
        $rowStrings = [];
        if ($value instanceof \Phpdftk\Css\Value\StringValue) {
            $rowStrings = [$value->value];
        } elseif ($value instanceof \Phpdftk\Css\Value\ValueList) {
            foreach ($value->values as $v) {
                if (!$v instanceof \Phpdftk\Css\Value\StringValue) {
                    return [];
                }
                $rowStrings[] = $v->value;
            }
        } else {
            return [];
        }
        if ($rowStrings === []) {
            return [];
        }
        // Tokenise each row into whitespace-separated cells. Empty
        // strings (multiple `.` runs as a single null cell) are
        // collapsed to the `.` token.
        $grid = [];
        $colCount = -1;
        foreach ($rowStrings as $row) {
            $cells = preg_split('/\s+/', trim($row)) ?: [];
            if ($cells === [] || $cells === ['']) {
                return [];
            }
            if ($colCount === -1) {
                $colCount = count($cells);
            } elseif (count($cells) !== $colCount) {
                // Per spec, rows with mismatched column counts drop
                // the whole template.
                return [];
            }
            $grid[] = $cells;
        }
        $rowCount = count($grid);
        // Walk every cell, collecting name → min/max row/col.
        /** @var array<string, array{rowStart: int, colStart: int, rowEnd: int, colEnd: int}> $extents */
        $extents = [];
        for ($r = 0; $r < $rowCount; $r++) {
            for ($c = 0; $c < $colCount; $c++) {
                $name = strtolower($grid[$r][$c]);
                if ($name === '.' || $name === '') {
                    continue;
                }
                if (!isset($extents[$name])) {
                    $extents[$name] = [
                        'rowStart' => $r,
                        'colStart' => $c,
                        'rowEnd' => $r + 1,
                        'colEnd' => $c + 1,
                    ];
                    continue;
                }
                $extents[$name]['rowStart'] = min($extents[$name]['rowStart'], $r);
                $extents[$name]['colStart'] = min($extents[$name]['colStart'], $c);
                $extents[$name]['rowEnd'] = max($extents[$name]['rowEnd'], $r + 1);
                $extents[$name]['colEnd'] = max($extents[$name]['colEnd'], $c + 1);
            }
        }
        // Validate: each named area must form a contiguous rectangle
        // — every cell within its computed extent must carry the
        // name. Otherwise drop the area.
        $areas = [];
        foreach ($extents as $name => $rect) {
            $isRect = true;
            for ($r = $rect['rowStart']; $r < $rect['rowEnd']; $r++) {
                for ($c = $rect['colStart']; $c < $rect['colEnd']; $c++) {
                    if (strtolower($grid[$r][$c]) !== $name) {
                        $isRect = false;
                        break 2;
                    }
                }
            }
            if ($isRect) {
                $areas[$name] = $rect;
            }
        }
        return [
            'rowCount' => $rowCount,
            'colCount' => $colCount,
            'areas' => $areas,
        ];
    }

    /**
     * Resolve a single axis's start / end. Returns `[startIdx,
     * endIdx, isAuto]` — 0-based, exclusive end (so a 1-cell item
     * at line 1 returns `[0, 1, false]`). When BOTH ends are `auto`,
     * the `isAuto` flag is true so the auto-flow pass picks the slot.
     * `span N` propagates as an N-cell span anchored on whichever
     * side has an explicit line, or N cells auto-placed when both
     * sides are span/auto. Negative indices count from the end.
     *
     * @return array{0: int, 1: int, 2: bool}
     */
    private function resolveGridLine(
        ?\Phpdftk\Css\Value\Value $startVal,
        ?\Phpdftk\Css\Value\Value $endVal,
        int $trackCount,
    ): array {
        $totalLines = max(1, $trackCount) + 1; // line N+1 = end of grid
        $start = $this->parseGridLineValue($startVal, $totalLines);
        $end = $this->parseGridLineValue($endVal, $totalLines);
        // CSS Grid Layout 2 §8.3 — if both sides are `auto` (or span)
        // without an anchor line, auto-place with the requested span.
        $startIsLine = $start !== null && $start['type'] === 'line';
        $endIsLine = $end !== null && $end['type'] === 'line';
        $startSpan = $start !== null && $start['type'] === 'span' ? $start['count'] : null;
        $endSpan = $end !== null && $end['type'] === 'span' ? $end['count'] : null;

        if (!$startIsLine && !$endIsLine) {
            // No anchor — fully auto-placed.
            $span = $startSpan ?? $endSpan ?? 1;
            $autoLen = max(1, $span);
            // Mark "auto" by returning negative placeholders; the
            // auto-flow pass fills in the real position. We encode
            // the span length in the (endIdx - startIdx) gap so the
            // placement record's `colSpan` / `rowSpan` math works.
            return [-1, -1 + $autoLen, true];
        }
        if (!$startIsLine) {
            // `<span N> / N` — count back from `end`.
            $endIdx = $end['value'];
            $span = $startSpan ?? 1;
            return [max(0, $endIdx - $span), max(1, $endIdx), false];
        }
        if (!$endIsLine) {
            // `N / <span N>` (or `N / auto`) — count forward from start.
            $startIdx = $start['value'];
            $span = $endSpan ?? 1;
            return [max(0, $startIdx), $startIdx + max(1, $span), false];
        }
        // Two explicit lines.
        $startIdx = $start['value'];
        $endIdx = $end['value'];
        if ($endIdx <= $startIdx) {
            // CSS Grid Layout 2 §8.3.1 — when end ≤ start, swap them
            // so the span is at least 1 cell.
            [$startIdx, $endIdx] = [$endIdx, $startIdx + 1];
        }
        $startIdx = max(0, $startIdx);
        $endIdx = min(max($startIdx + 1, $endIdx), $trackCount);
        return [$startIdx, $endIdx, false];
    }

    /**
     * Parse a single grid-line value into a discriminated descriptor:
     *   - `null` for `auto` or unrecognised input
     *   - `['type' => 'line', 'value' => int]` for a 0-based line index
     *   - `['type' => 'span', 'count' => int]` for `span N`
     * Negative integers count from the explicit-grid end edge.
     *
     * @return array{type: string, value?: int, count?: int}|null
     */
    private function parseGridLineValue(?\Phpdftk\Css\Value\Value $value, int $totalLines): ?array
    {
        if ($value === null
            || ($value instanceof Keyword && strtolower($value->name) === 'auto')
        ) {
            return null;
        }
        // `span N` — Space-separated ValueList of [Keyword(span), Integer(N)].
        if ($value instanceof \Phpdftk\Css\Value\ValueList
            && $value->separator === \Phpdftk\Css\Value\ListSeparator::Space
        ) {
            $first = $value->values[0] ?? null;
            $second = $value->values[1] ?? null;
            if ($first instanceof Keyword && strtolower($first->name) === 'span') {
                $count = $this->numericValueOrNull($second);
                if ($count !== null && $count >= 1) {
                    return ['type' => 'span', 'count' => (int) $count];
                }
                // `span auto` falls through — treat as span 1.
                return ['type' => 'span', 'count' => 1];
            }
        }
        if ($value instanceof \Phpdftk\Css\Value\Integer) {
            $n = $value->value;
            if ($n > 0) {
                return ['type' => 'line', 'value' => $n - 1];
            }
            if ($n < 0) {
                return ['type' => 'line', 'value' => max(0, $totalLines + $n)];
            }
        }
        return null;
    }

    /**
     * Resolve a `justify-self` / `align-self` keyword on a grid item.
     * `auto` resolves to `stretch` (the Grid spec default); unknown
     * keywords fall through to `stretch` too rather than dropping
     * silently. Other keywords (`start` / `end` / `center` / `stretch`)
     * pass through; baseline and `*-self: <other>` keywords aren't
     * modelled at MVP and resolve to `stretch`.
     */
    private function gridSelfKeyword(Box $box, string $property): string
    {
        $value = $box->style->get($property);
        if (!$value instanceof Keyword) {
            return 'stretch';
        }
        $name = strtolower($value->name);
        // `flex-start` / `flex-end` alias for compatibility with
        // flex shorthand authors mixing the two layout modes.
        return match ($name) {
            'start', 'flex-start', 'self-start' => 'start',
            'end', 'flex-end', 'self-end' => 'end',
            'center' => 'center',
            'stretch' => 'stretch',
            default => 'stretch',
        };
    }

    /**
     * Mark a (row, col, rowSpan, colSpan) range as occupied.
     *
     * @param array<int, array<int, true>> $occupied  Mutated in place.
     */
    private function markGridOccupied(array &$occupied, int $row, int $col, int $rowSpan, int $colSpan): void
    {
        for ($r = $row; $r < $row + $rowSpan; $r++) {
            for ($c = $col; $c < $col + $colSpan; $c++) {
                $occupied[$r][$c] = true;
            }
        }
    }

    /**
     * Return true when any cell in the (row, col, rowSpan, colSpan)
     * range is already occupied.
     *
     * @param array<int, array<int, true>> $occupied
     */
    private function isGridRangeOccupied(array $occupied, int $row, int $col, int $rowSpan, int $colSpan): bool
    {
        for ($r = $row; $r < $row + $rowSpan; $r++) {
            for ($c = $col; $c < $col + $colSpan; $c++) {
                if (isset($occupied[$r][$c])) {
                    return true;
                }
            }
        }
        return false;
    }

    /**
     * Prefix-sum offsets for a track list — `result[i]` = position
     * of track `i`'s start edge, taking gaps into account.
     *
     * @param list<float> $tracks
     * @return list<float>
     */
    private function gridTrackOffsets(array $tracks, float $gap): array
    {
        $offsets = [0.0];
        $running = 0.0;
        for ($i = 0; $i < count($tracks); $i++) {
            $running += $tracks[$i];
            if ($i < count($tracks) - 1) {
                $running += $gap;
            }
            $offsets[] = $running;
        }
        return $offsets;
    }

    /**
     * Sum the span from `start` for `count` tracks, including the
     * gaps between them but excluding any trailing gap.
     *
     * @param list<float> $tracks
     */
    private function gridSpanExtent(array $tracks, int $start, int $count, float $gap): float
    {
        $total = 0.0;
        for ($i = 0; $i < $count; $i++) {
            $idx = $start + $i;
            if (!isset($tracks[$idx])) {
                break;
            }
            $total += $tracks[$idx];
            if ($i < $count - 1) {
                $total += $gap;
            }
        }
        return $total;
    }

    /**
     * Total extent of the track list including inter-track gaps.
     *
     * @param list<float> $tracks
     */
    private function gridTotalExtent(array $tracks, float $gap): float
    {
        if ($tracks === []) {
            return 0.0;
        }
        $total = array_sum($tracks);
        $total += $gap * max(0, count($tracks) - 1);
        return $total;
    }

    /**
     * Partition flex items into lines per CSS Flexbox 1 §9.3 step 5:
     * each line packs as many consecutive items as fit in the
     * container's main size; if a single item alone doesn't fit it
     * still lives on its own line (so `flex-shrink` can handle it).
     *
     * @param list<float> $itemMains  item main-axis outer sizes.
     * @return list<list<int>>        per-line lists of item indices.
     */
    private function partitionFlexLines(array $itemMains, float $gap, float $containerMain): array
    {
        $lines = [];
        $current = [];
        $currentUsed = 0.0;
        foreach ($itemMains as $i => $main) {
            $needed = $main + ($current === [] ? 0.0 : $gap);
            if ($current !== [] && $currentUsed + $needed > $containerMain) {
                $lines[] = $current;
                $current = [];
                $currentUsed = 0.0;
                $needed = $main;
            }
            $current[] = $i;
            $currentUsed += $needed;
        }
        if ($current !== []) {
            $lines[] = $current;
        }
        return $lines;
    }

    /**
     * Stable sort of flex items by their `order` value (CSS Flexbox 1
     * §5.4). PHP's `usort` isn't guaranteed stable, so we attach the
     * document index as a tie-breaker.
     *
     * @param list<\Phpdftk\HtmlToPdf\Box\Box> $children
     * @return list<\Phpdftk\HtmlToPdf\Box\Box>
     */
    private function sortFlexItemsByOrder(array $children): array
    {
        $indexed = [];
        foreach ($children as $i => $child) {
            $indexed[] = [$this->resolveFlexOrder($child->style), $i, $child];
        }
        $needsSort = false;
        foreach ($indexed as $entry) {
            if ($entry[0] !== 0) {
                $needsSort = true;
                break;
            }
        }
        if (!$needsSort) {
            return $children;
        }
        usort($indexed, static function (array $a, array $b): int {
            if ($a[0] !== $b[0]) {
                return $a[0] <=> $b[0];
            }
            return $a[1] <=> $b[1];
        });
        $sorted = [];
        foreach ($indexed as $entry) {
            $sorted[] = $entry[2];
        }
        return $sorted;
    }

    private function resolveFlexOrder(CascadedValues $style): int
    {
        $value = $style->get('order');
        if ($value instanceof \Phpdftk\Css\Value\Integer) {
            return $value->value;
        }
        if ($value instanceof \Phpdftk\Css\Value\Number) {
            return (int) $value->value;
        }
        return 0;
    }

    /**
     * CSS Flexbox 1 §9.7 — "Resolve the Flexible Lengths". Iteratively
     * distribute the line's free space (positive → grow proportional
     * to `flex-grow`; negative → shrink proportional to `flex-shrink
     * × base size`) and freeze items that hit their min / max main
     * size. Returns the final OUTER main size per item index, keyed
     * by the same `$indices` passed in.
     *
     * The "base size" of each item is its current outer main size
     * before resolution (post `flex-basis` substitution, pre
     * grow/shrink). Min and max main are resolved from `min-{width,
     * height}` and `max-{width,height}` on the item and converted to
     * outer-size space by adding the item's border + padding + margin
     * adornment, since the algorithm works on outer sizes throughout.
     *
     * @param list<Box> $children
     * @param list<int> $indices
     * @param list<float> $itemMains  Per-index OUTER main size (in/out).
     * @return array<int, float>  Final outer main size, keyed by index.
     */
    private function resolveFlexLineMainSizes(
        array $children,
        array $indices,
        array $itemMains,
        bool $isColumn,
        float $containerMain,
        float $gap,
        float $cbWidth,
        float $cbHeight,
    ): array {
        if ($indices === []) {
            return [];
        }
        // Capture baseline arrays for each item: base outer size,
        // grow / shrink ratios, and min/max main bounds.
        $baseOuter = [];
        $grows = [];
        $shrinks = [];
        $minOuter = [];
        $maxOuter = [];
        $usedFixed = 0.0;
        foreach ($indices as $i) {
            $baseOuter[$i] = $itemMains[$i];
            $grows[$i] = $this->resolveFlexGrow($children[$i]->style);
            $shrinks[$i] = $this->resolveFlexShrink($children[$i]->style);
            $g = $children[$i]->geometry;
            if ($isColumn) {
                $adornment = $g->marginTop + $g->borderTop + $g->paddingTop
                    + $g->paddingBottom + $g->borderBottom + $g->marginBottom;
                $minInner = $this->resolveLength(
                    $children[$i]->style->get('min-height'),
                    $cbHeight,
                );
                $maxInnerVal = $children[$i]->style->get('max-height');
                $maxInner = ($maxInnerVal instanceof Keyword && strtolower($maxInnerVal->name) === 'none')
                    ? null
                    : $this->resolveLength($maxInnerVal, $cbHeight);
            } else {
                $adornment = $g->marginLeft + $g->borderLeft + $g->paddingLeft
                    + $g->paddingRight + $g->borderRight + $g->marginRight;
                $minInner = $this->resolveLength(
                    $children[$i]->style->get('min-width'),
                    $cbWidth,
                );
                $maxInnerVal = $children[$i]->style->get('max-width');
                $maxInner = ($maxInnerVal instanceof Keyword && strtolower($maxInnerVal->name) === 'none')
                    ? null
                    : $this->resolveLength($maxInnerVal, $cbWidth);
            }
            $minOuter[$i] = max(0.0, ($minInner > 0.0 ? $minInner : 0.0) + $adornment);
            $maxOuter[$i] = $maxInner !== null && $maxInner > 0.0
                ? $maxInner + $adornment
                : INF;
        }

        $gapTotal = $gap * max(0, count($indices) - 1);
        $available = $containerMain - $gapTotal;

        // Pick sign of flex (grow vs shrink) based on hypothetical
        // sum: if items overflow → shrink; if underflow → grow.
        $hypothetical = 0.0;
        foreach ($indices as $i) {
            $hypothetical += $baseOuter[$i];
        }
        $isGrow = $hypothetical < $available;

        // Initial freeze pass: zero grow/shrink, or already past
        // the relevant clamp boundary.
        $frozen = [];
        foreach ($indices as $i) {
            if ($isGrow && $grows[$i] <= 0.0) {
                $frozen[$i] = $baseOuter[$i];
            } elseif (!$isGrow && $shrinks[$i] <= 0.0) {
                $frozen[$i] = $baseOuter[$i];
            } elseif ($isGrow && $baseOuter[$i] >= $maxOuter[$i]) {
                $frozen[$i] = $maxOuter[$i];
            } elseif (!$isGrow && $baseOuter[$i] <= $minOuter[$i]) {
                $frozen[$i] = $minOuter[$i];
            }
        }

        // Iterate freeze-and-distribute. Worst case: each iteration
        // freezes one item, so an upper bound of count + 1 iterations
        // is safe — if nothing freezes the loop exits via the
        // !clampedAny break below.
        $iter = 0;
        $iterCap = count($indices) + 1;
        while ($iter++ < $iterCap) {
            $unfrozen = [];
            $consumedFrozen = 0.0;
            $consumedUnfrozenBase = 0.0;
            foreach ($indices as $i) {
                if (isset($frozen[$i])) {
                    $consumedFrozen += $frozen[$i];
                } else {
                    $unfrozen[] = $i;
                    $consumedUnfrozenBase += $baseOuter[$i];
                }
            }
            if ($unfrozen === []) {
                break;
            }
            $free = $available - $consumedFrozen - $consumedUnfrozenBase;
            // Stop early if we've reached equilibrium.
            if ($isGrow && $free <= 0.0) {
                break;
            }
            if (!$isGrow && $free >= 0.0) {
                break;
            }

            if ($isGrow) {
                $totalRatio = 0.0;
                foreach ($unfrozen as $i) {
                    $totalRatio += $grows[$i];
                }
                if ($totalRatio <= 0.0) {
                    break;
                }
                $tentatives = [];
                $clampedAny = false;
                foreach ($unfrozen as $i) {
                    $share = $free * ($grows[$i] / $totalRatio);
                    $candidate = $baseOuter[$i] + $share;
                    if ($candidate > $maxOuter[$i]) {
                        $frozen[$i] = $maxOuter[$i];
                        $clampedAny = true;
                    } elseif ($candidate < $minOuter[$i]) {
                        // Growing into something below its own min —
                        // can happen when base < min and grow pushed
                        // it just a hair; clamp up.
                        $frozen[$i] = $minOuter[$i];
                        $clampedAny = true;
                    } else {
                        $tentatives[$i] = $candidate;
                    }
                }
                if (!$clampedAny) {
                    foreach ($tentatives as $i => $size) {
                        $frozen[$i] = $size;
                    }
                    break;
                }
            } else {
                // Shrink with SCALED ratio: shrink × base size.
                $totalScaled = 0.0;
                foreach ($unfrozen as $i) {
                    $totalScaled += $shrinks[$i] * $baseOuter[$i];
                }
                if ($totalScaled <= 0.0) {
                    break;
                }
                $deficit = -$free; // positive
                $tentatives = [];
                $clampedAny = false;
                foreach ($unfrozen as $i) {
                    $share = $deficit * (($shrinks[$i] * $baseOuter[$i]) / $totalScaled);
                    $candidate = $baseOuter[$i] - $share;
                    if ($candidate < $minOuter[$i]) {
                        $frozen[$i] = $minOuter[$i];
                        $clampedAny = true;
                    } elseif ($candidate > $maxOuter[$i]) {
                        $frozen[$i] = $maxOuter[$i];
                        $clampedAny = true;
                    } else {
                        $tentatives[$i] = $candidate;
                    }
                }
                if (!$clampedAny) {
                    foreach ($tentatives as $i => $size) {
                        $frozen[$i] = $size;
                    }
                    break;
                }
            }
        }

        // Any item still unfrozen at this point (defensive — should
        // not happen if the iteration cap is right) keeps its base.
        $result = [];
        foreach ($indices as $i) {
            $result[$i] = $frozen[$i] ?? $baseOuter[$i];
        }
        return $result;
    }

    /**
     * Resolve `flex-basis` per CSS Flexbox 1 §7.2. Returns `null` for
     * `auto` / `content` / unrecognised values (the caller keeps the
     * layoutBox-derived width); a non-negative float for explicit
     * lengths, percentages (against `$cbWidth`), and unitless zero.
     */
    private function resolveFlexBasis(CascadedValues $style, float $cbWidth): ?float
    {
        $value = $style->get('flex-basis');
        if ($value === null) {
            return null;
        }
        if ($value instanceof Keyword) {
            return null;
        }
        if ($value instanceof Length) {
            return max(0.0, $value->value);
        }
        if ($value instanceof Percentage) {
            return max(0.0, $value->value / 100.0 * $cbWidth);
        }
        if ($value instanceof \Phpdftk\Css\Value\Integer
            || $value instanceof \Phpdftk\Css\Value\Number
        ) {
            // Unitless zero per CSS Values 4 §6.2.
            if ((float) $value->value === 0.0) {
                return 0.0;
            }
        }
        return null;
    }

    /**
     * Resolve `flex-grow` per CSS Flexbox 1 §7.1: a non-negative
     * `<number>`. Negative values are invalid and treated as 0.
     */
    private function resolveFlexGrow(CascadedValues $style): float
    {
        $value = $style->get('flex-grow');
        if ($value instanceof \Phpdftk\Css\Value\Number) {
            return max(0.0, (float) $value->value);
        }
        if ($value instanceof \Phpdftk\Css\Value\Integer) {
            return max(0.0, (float) $value->value);
        }
        return 0.0;
    }

    /**
     * Resolve `flex-shrink` per CSS Flexbox 1 §7.1: a non-negative
     * `<number>` (initial value `1`). Negative values are invalid
     * and treated as 0 (no shrink).
     */
    private function resolveFlexShrink(CascadedValues $style): float
    {
        $value = $style->get('flex-shrink');
        if ($value instanceof \Phpdftk\Css\Value\Number) {
            return max(0.0, (float) $value->value);
        }
        if ($value instanceof \Phpdftk\Css\Value\Integer) {
            return max(0.0, (float) $value->value);
        }
        return 1.0;
    }

    private function flexKeyword(CascadedValues $style, string $prop, string $default): string
    {
        $value = $style->get($prop);
        if ($value instanceof Keyword) {
            return strtolower($value->name);
        }
        return $default;
    }

    /**
     * CSS Sizing 3 §6.2 — `true` when `box-sizing` is `border-box`.
     * Under border-box, the declared `width` / `height` (and the
     * min/max variants) include the border + padding so the caller
     * subtracts them to get the content-box dimension.
     */
    private function isBorderBoxSizing(CascadedValues $style): bool
    {
        $value = $style->get('box-sizing');
        return $value instanceof \Phpdftk\Css\Value\Keyword
            && strtolower($value->name) === 'border-box';
    }

    /**
     * Resolve the `height` property. Returns `null` for `auto` so
     * callers can distinguish "explicitly sized" from "size-to-fit".
     */
    private function resolveExplicitHeightOrNull(CascadedValues $style, float $cbHeight): ?float
    {
        $value = $style->get('height');
        if ($this->isAuto($value)) {
            return null;
        }
        return $this->resolveLength($value, $cbHeight);
    }

    /**
     * Apply min/max-width and min/max-height clamping to `$geo`
     * mirroring `layoutBlock`'s clamp pass — extracted so flex
     * containers honour the same constraints.
     */
    private function clampMinMax(CascadedValues $style, \Phpdftk\HtmlToPdf\Layout\BoxGeometry $geo, float $cbWidth, float $cbHeight): void
    {
        $maxWidthValue = $style->get('max-width');
        if (!($maxWidthValue instanceof Keyword && strtolower($maxWidthValue->name) === 'none')) {
            $maxWidth = $this->resolveLength($maxWidthValue, $cbWidth);
            if ($maxWidth > 0.0 && $geo->width > $maxWidth) {
                $geo->width = $maxWidth;
            }
        }
        $minWidth = $this->resolveLength($style->get('min-width'), $cbWidth);
        if ($minWidth > 0.0 && $geo->width < $minWidth) {
            $geo->width = $minWidth;
        }
        $maxHeightValue = $style->get('max-height');
        if (!($maxHeightValue instanceof Keyword && strtolower($maxHeightValue->name) === 'none')) {
            $maxHeight = $this->resolveLength($maxHeightValue, $cbHeight);
            if ($maxHeight > 0.0 && $geo->height > $maxHeight) {
                $geo->height = $maxHeight;
            }
        }
        $minHeight = $this->resolveLength($style->get('min-height'), $cbHeight);
        if ($minHeight > 0.0 && $geo->height < $minHeight) {
            $geo->height = $minHeight;
        }
    }

    private function resolveLength(?\Phpdftk\Css\Value\Value $value, float $percentageBasis): float
    {
        if ($value === null) {
            return 0.0;
        }
        if ($value instanceof Length) {
            // Already-resolved Lengths went through LengthResolver::toPx
            // upstream so they're clamped, but Percentages computed
            // here haven't been — clamp on the way out so adversarial
            // CSS (`margin: 5307% 18446744073709551526px`) can't push
            // layout into multi-terabyte dimensions. See
            // {@see \Phpdftk\Css\Cascade\LengthResolver::MAX_PX}.
            return \Phpdftk\Css\Cascade\LengthResolver::clampPx($value->value);
        }
        if ($value instanceof Percentage) {
            return \Phpdftk\Css\Cascade\LengthResolver::clampPx(
                $value->value / 100.0 * $percentageBasis,
            );
        }
        return 0.0;
    }

    private function resolveBorderWidth(CascadedValues $style, string $side): float
    {
        $styleValue = $style->get("border-$side-style");
        if ($styleValue instanceof Keyword && strtolower($styleValue->name) === 'none') {
            return 0.0;
        }
        return $this->resolveBorderWidthValue($style->get("border-$side-width"));
    }

    /**
     * CSS Backgrounds 3 §4.4 — `border-width` keywords:
     *   thin   = 1px
     *   medium = 3px
     *   thick  = 5px
     * Length values pass through; other types resolve to 0.
     */
    private function resolveBorderWidthValue(?\Phpdftk\Css\Value\Value $v): float
    {
        if ($v instanceof Length) {
            return \Phpdftk\Css\Cascade\LengthResolver::clampPx($v->value);
        }
        if ($v instanceof Keyword) {
            return match (strtolower($v->name)) {
                'thin' => 1.0,
                'medium' => 3.0,
                'thick' => 5.0,
                default => 0.0,
            };
        }
        return 0.0;
    }

    private function lengthContextFor(CascadedValues $style, LengthContext $parent): LengthContext
    {
        $fontSize = $style->get('font-size');
        if ($fontSize instanceof Length) {
            return $parent->withCurrentFontSize($fontSize->value);
        }
        return $parent;
    }

    /**
     * Resolve CSS Sizing 4 §4.2 `aspect-ratio` to a width/height
     * ratio. Accepts:
     *  - `<number>` (e.g. `1.5`) → ratio.
     *  - `<number> / <number>` (e.g. `16/9`) → first divided by
     *    second (parses as ValueList with Slash separator).
     * Returns null on `auto` / unknown / zero denominator.
     */
    private function resolveAspectRatio(CascadedValues $style): ?float
    {
        $value = $style->get('aspect-ratio');
        if ($value instanceof \Phpdftk\Css\Value\ValueList
            && $value->separator === \Phpdftk\Css\Value\ListSeparator::Slash
            && count($value->values) >= 2
        ) {
            $w = $this->numericValue($value->values[0]);
            $h = $this->numericValue($value->values[1]);
            if ($w !== null && $h !== null && $h > 0.0) {
                return $w / $h;
            }
        }
        $direct = $this->numericValue($value);
        if ($direct !== null && $direct > 0.0) {
            return $direct;
        }
        return null;
    }

    private function numericValue(?\Phpdftk\Css\Value\Value $v): ?float
    {
        if ($v instanceof \Phpdftk\Css\Value\Integer) {
            return (float) $v->value;
        }
        if ($v instanceof \Phpdftk\Css\Value\Number) {
            return $v->value;
        }
        return null;
    }

    private function isAuto(?\Phpdftk\Css\Value\Value $value): bool
    {
        return $value instanceof Keyword && strtolower($value->name) === 'auto';
    }

    /**
     * CSS 2.1 propidx — `width` does NOT apply to non-replaced
     * inline elements, table rows, table row-groups, or table
     * column/column-groups. It DOES apply to table cells and tables.
     */
    private function widthAppliesToDisplay(\Phpdftk\Css\Cascade\CascadedValues $style): bool
    {
        $display = $style->get('display');
        if (!($display instanceof Keyword)) {
            return true;
        }
        return match (strtolower($display->name)) {
            'table-row-group',
            'table-header-group',
            'table-footer-group',
            'table-row',
            'table-column',
            'table-column-group' => false,
            default => true,
        };
    }

    /**
     * CSS 2.1 propidx — `height` does NOT apply to non-replaced
     * inline elements, table columns, or table column-groups. It DOES
     * apply to table cells, rows, row-groups, and tables themselves.
     */
    private function heightAppliesToDisplay(\Phpdftk\Css\Cascade\CascadedValues $style): bool
    {
        $display = $style->get('display');
        if (!($display instanceof Keyword)) {
            return true;
        }
        return match (strtolower($display->name)) {
            'table-column',
            'table-column-group' => false,
            default => true,
        };
    }

    /**
     * CSS Sizing 4 §3 — `max-content`, `min-content`, `fit-content`,
     * and `stretch` are sizing keywords that, for the block-axis in
     * block-flow layout, all reduce to the children-derived size.
     * Treating them as "auto-like" here lets boxes that opt into
     * those keywords lay out at the children-summed height rather
     * than the `0` the unknown-keyword path produces. The width
     * axis keeps strict `auto` semantics — width keywords have
     * spec-distinct behaviour (min-content shrink-wrap, etc.) and
     * widening them blindly regresses min/max-content shrink-wrap
     * tests. Width-side refinement is tracked in #17 follow-ups.
     */
    private function isHeightAutoLike(?\Phpdftk\Css\Value\Value $value): bool
    {
        if (!$value instanceof Keyword) {
            return false;
        }
        return match (strtolower($value->name)) {
            'auto', 'max-content', 'min-content', 'fit-content', 'stretch' => true,
            default => false,
        };
    }

    /**
     * CSS Containment 3 §2.4 — returns true when `contain` enables
     * size containment on this axis. `size` covers both axes;
     * `inline-size` covers the inline axis only; `strict` (=
     * layout|paint|style|size) and `content` (= layout|paint|style)
     * imply the broader set. Phase-1 assumes horizontal writing
     * mode so inline-axis == width-axis.
     *
     * @param 'block'|'inline' $axis
     */
    private function containsSize(CascadedValues $style, string $axis): bool
    {
        $value = $style->get('contain');
        if (!$value instanceof Keyword && !($value instanceof \Phpdftk\Css\Value\ValueList)) {
            return false;
        }
        $keywords = $value instanceof Keyword
            ? [strtolower($value->name)]
            : array_filter(
                array_map(
                    static fn($v) => $v instanceof Keyword ? strtolower($v->name) : null,
                    $value->values,
                ),
                static fn($v) => $v !== null,
            );
        foreach ($keywords as $kw) {
            if ($kw === 'strict') {
                return true;
            }
            if ($kw === 'size') {
                return true;
            }
            // CSS Containment 3 §4.6 — `inline-size` contains the
            // inline axis only. Block axis containment isn't
            // toggled by `inline-size` alone.
            if ($kw === 'inline-size' && $axis === 'inline') {
                return true;
            }
            // `content` doesn't include `size` per §2.4 (only
            // layout+paint+style), so it does NOT trigger size
            // containment. (Common confusion — keep this branch
            // explicit so a future reader doesn't add it.)
        }
        return false;
    }

    /**
     * CSS Sizing 4 §6.1 — resolve `contain-intrinsic-height` (or
     * the second component of the `contain-intrinsic-size`
     * shorthand) to a concrete pixel height, when size
     * containment applies. Returns null when the box isn't
     * size-contained on the block axis or the value resolves to
     * `none` / `auto` (per §6.1, `auto` defers to a last-known
     * size which the static print medium can't supply, so it
     * collapses to zero).
     */
    private function resolveContainIntrinsicHeight(CascadedValues $style, LayoutContext $context): ?float
    {
        if (!$this->containsSize($style, 'block')) {
            return null;
        }
        $heightLonghand = $style->get('contain-intrinsic-height');
        if ($heightLonghand instanceof Length) {
            return $heightLonghand->value;
        }
        $blockSize = $style->get('contain-intrinsic-block-size');
        if ($blockSize instanceof Length) {
            return $blockSize->value;
        }
        // Phase-1 shorthand inline-parse: `contain-intrinsic-size:
        // <l1>` → both axes; `<l1> <l2>` → l1 = width, l2 = height.
        $shorthand = $style->get('contain-intrinsic-size');
        if ($shorthand instanceof Length) {
            return $shorthand->value;
        }
        if ($shorthand instanceof \Phpdftk\Css\Value\ValueList && count($shorthand->values) >= 2) {
            $second = $shorthand->values[1];
            if ($second instanceof Length) {
                return $second->value;
            }
        }
        return 0.0;
    }


    /**
     * Shift `$box` and every descendant's geometry by `$dy` along Y, and
     * optionally `$dx` along X. Used by margin collapsing (Y only) and
     * by multi-column re-distribution (both axes).
     *
     * Line boxes and inline fragments are positioned relative to their
     * containing block's geometry, so they ride along automatically when
     * the parent's geometry shifts — no extra walk required.
     */
    private function shiftSubtree(Box $box, float $dy, float $dx = 0.0): void
    {
        $box->geometry->y += $dy;
        $box->geometry->x += $dx;
        foreach ($box->children as $child) {
            $this->shiftSubtree($child, $dy, $dx);
        }
    }

    /**
     * `column-count` or `column-width` (or both) non-`auto` → this box
     * establishes a multi-column formatting context (CSS Multi-column 1 §2).
     * Honoured only on block-container parents whose children are all
     * block-level: tables, inline-only blocks, and replaced elements are
     * skipped at Phase 1.
     */
    private function isMultiColumnContainer(Box $box): bool
    {
        if ($box instanceof \Phpdftk\HtmlToPdf\Box\TableBox
            || $box instanceof \Phpdftk\HtmlToPdf\Box\TableRowBox
            || $box instanceof \Phpdftk\HtmlToPdf\Box\TableCellBox
        ) {
            return false;
        }
        if ($this->allInlineLevel($box->children)) {
            return false;
        }
        $count = $box->style->get('column-count');
        $width = $box->style->get('column-width');
        $countSet = !$this->isAuto($count);
        $widthSet = $width instanceof Length;
        return $countSet || $widthSet;
    }

    /**
     * Resolve the used column-count + column-width per CSS Multi-column 1
     * §7.1, then stack children into N fragmentainers side-by-side.
     *
     * Phase-1 simplifications:
     *  - `column-fill: balance` only (initial value). Heights are equalised
     *    using `ceil(totalChildOuterHeight / N)`; the true browser
     *    algorithm iterates to minimise stragglers but the ceil
     *    approximation matches within one line for typical content.
     *  - No mid-child fragmentation. A child taller than the balanced
     *    height stays whole in its column; the column simply overflows.
     *    Mid-child splitting lands with 1I.2.
     *  - `column-span: all` carves the container into sequential
     *    columnar segments separated by full-width spanners; the
     *    column rule still paints across the full container height
     *    (drawing through spanner backgrounds — a Phase-2 follow-up
     *    will clip it per-segment).
     */
    private function layoutMultiColumn(Box $box, LayoutContext $childContext): float
    {
        $geo = $box->geometry;
        $available = $geo->width;
        if ($available <= 0.0) {
            $box->multiColumn = null;
            return 0.0;
        }

        $gap = $this->resolveColumnGap($box->style, $childContext->lengthContext);
        [$count, $columnWidth] = $this->resolveColumns($box->style, $available, $gap);
        if ($count < 1) {
            $count = 1;
        }
        $box->multiColumn = new MultiColumnLayout(
            columnCount: $count,
            columnWidth: $columnWidth,
            columnGap: $gap,
            ruleWidth: $this->resolveColumnRuleWidth($box->style),
            ruleStyle: $this->resolveColumnRuleStyle($box->style),
            ruleColor: $this->resolveColumnRuleColor($box->style),
        );

        // Single-column degenerate case — fall back to normal block stacking.
        if ($count === 1) {
            return $this->stackChildren($box, $childContext, $childContext->originX, $childContext->originY);
        }

        // CSS Multi-column 1 §6.2 — `column-span: all` carves the
        // container into vertical segments: columnar runs above and
        // below a span-all child, with the spanner taking the full
        // container width between them. Walk the children once and
        // dispatch each segment to the appropriate layout path.
        $segments = $this->splitByColumnSpan($box->children);
        if (count($segments) > 1) {
            $cursorY = $geo->y;
            foreach ($segments as $segment) {
                if ($segment['span']) {
                    // Single column-span: all child — lay out full-width.
                    $spanChild = $segment['children'][0];
                    $spanCtx = $childContext
                        ->withContainingBlock($geo->width, $childContext->containingBlockHeight)
                        ->withOrigin($geo->x, $cursorY);
                    $h = $this->layoutBox($spanChild, $spanCtx);
                    $cursorY += $h;
                } else {
                    $cursorY += $this->layoutColumnarRun(
                        $box,
                        $segment['children'],
                        $childContext,
                        $columnWidth,
                        $gap,
                        $count,
                        $cursorY,
                    );
                }
            }
            return $cursorY - $geo->y;
        }

        // No span-all children — fall through to the original two-pass
        // codepath that operates on `$box->children` directly.
        return $this->layoutColumnarRun($box, $box->children, $childContext, $columnWidth, $gap, $count, $geo->y);
    }

    /**
     * Split a child list into vertical segments at `column-span: all`
     * boundaries. Each segment is either a columnar run of regular
     * children OR a single span-all child. Order is preserved.
     *
     * @param list<Box> $children
     * @return list<array{span: bool, children: list<Box>}>
     */
    private function splitByColumnSpan(array $children): array
    {
        /** @var list<array{span: bool, children: list<Box>}> $segments */
        $segments = [];
        /** @var list<Box> $run */
        $run = [];
        foreach ($children as $child) {
            if ($this->isColumnSpanAll($child)) {
                if ($run !== []) {
                    $segments[] = ['span' => false, 'children' => $run];
                    $run = [];
                }
                $segments[] = ['span' => true, 'children' => [$child]];
                continue;
            }
            $run[] = $child;
        }
        if ($run !== []) {
            $segments[] = ['span' => false, 'children' => $run];
        }
        return $segments;
    }

    private function isColumnSpanAll(Box $child): bool
    {
        $value = $child->style->get('column-span');
        return $value instanceof Keyword && strtolower($value->name) === 'all';
    }

    /**
     * Two-pass columnar layout over a subset of `$box`'s children
     * starting at `$originY`. Returns the total vertical space consumed
     * (max of the column heights) so the caller can advance its cursor
     * past the run. Pulled out of `layoutMultiColumn` so segmented runs
     * (around `column-span: all` spanners) can reuse it.
     *
     * @param list<Box> $children
     */
    private function layoutColumnarRun(
        Box $box,
        array $children,
        LayoutContext $childContext,
        float $columnWidth,
        float $gap,
        int $count,
        float $originY,
    ): float {
        $geo = $box->geometry;
        if ($children === []) {
            return 0.0;
        }
        $columnCtx = $childContext->withContainingBlock(
            $columnWidth,
            $childContext->containingBlockHeight,
        );
        $childTotal = $this->stackChildrenList(
            $children,
            $columnCtx,
            $geo->x,
            $originY,
        );

        $balanced = $count > 0 ? ceil($childTotal / $count) : $childTotal;

        $currentCol = 0;
        $colY = 0.0;
        $columnHeights = array_fill(0, $count, 0.0);
        $prevChild = null;
        foreach ($children as $child) {
            $h = $child->geometry->outerHeight();
            // CSS Multi-column 1 §6.1 — author-controlled column breaks.
            // `break-before: column` on this child or `break-after: column`
            // on the previous child forces a new column. Bounded at the
            // last column: once we've consumed N-1 columns, additional
            // forced breaks fall through to the existing overflow
            // semantics on the final column.
            $forceNewColumn = $colY > 0.0 && $currentCol < $count - 1 && (
                $this->forcesColumnBreakBefore($child)
                || ($prevChild !== null && $this->forcesColumnBreakAfter($prevChild))
            );
            if ($forceNewColumn) {
                $currentCol++;
                $colY = 0.0;
            } elseif ($colY > 0.0
                && $currentCol < $count - 1
                && $colY + $h > $balanced + 0.001
            ) {
                $currentCol++;
                $colY = 0.0;
            }
            $targetX = $geo->x + $currentCol * ($columnWidth + $gap);
            $targetY = $originY + $colY;
            // Reset the child's top margin so the first child in each
            // column doesn't carry collapsed-margin leftovers from the
            // first-pass stacking.
            if ($colY === 0.0) {
                $existingMargin = $child->geometry->marginTop;
                if ($existingMargin !== 0.0) {
                    // Pull the child up by its margin so it sits flush at
                    // the column's top edge.
                    $child->geometry->marginTop = 0.0;
                    $targetY -= $existingMargin;
                    $h = $child->geometry->outerHeight();
                }
            }
            $dx = $targetX - $child->geometry->x;
            $dy = $targetY - $child->geometry->y;
            if ($dx !== 0.0 || $dy !== 0.0) {
                $this->shiftSubtree($child, $dy, $dx);
            }
            $colY += $h;
            $columnHeights[$currentCol] = $colY;
            $prevChild = $child;
        }
        return max($columnHeights);
    }

    /**
     * Standard block-stacking pass extracted so `layoutMultiColumn`'s first
     * pass can reuse it. Lays out every child at the supplied origin in
     * `$context`'s containing block, applying margin collapsing and the
     * existing page-break logic.
     */
    private function stackChildren(Box $box, LayoutContext $childContext, float $originX, float $originY): float
    {
        return $this->stackChildrenList($box->children, $childContext, $originX, $originY);
    }

    /**
     * Same algorithm as `stackChildren` but iterating an explicit child
     * list instead of `$box->children`. Used by `layoutColumnarRun` so
     * a column-span: all segment can lay out just the columnar slice of
     * a multi-column container's children.
     *
     * @param list<Box> $children
     */
    private function stackChildrenList(array $children, LayoutContext $childContext, float $originX, float $originY): float
    {
        $cursorY = $originY;
        $prevBottomMargin = 0.0;
        $hasPrev = false;
        $pageHeight = $childContext->containingBlockHeight;
        $total = 0.0;
        foreach ($children as $child) {
            $this->cascade->resolveLengths($child->style, $childContext->lengthContext);
            // CSS 2.1 §9.6 — `position: absolute` (and `fixed`, which
            // behaves identically in a print context with no scrolling)
            // removes the box from normal flow. Lay it out at the
            // parent's content origin + (left, top) offsets, then skip
            // the cursor advancement so siblings stack as if it weren't
            // here.
            if ($this->isOutOfFlow($child)) {
                // CSS 2.1 §10.3.7 / §10.6.4 — when both opposing edge
                // anchors are set (left+right or top+bottom) AND the
                // corresponding size property is `auto`, the size is
                // derived from the containing block minus both
                // anchors. Resolve here BEFORE laying out so the
                // child's geometry reflects the corner-anchored size.
                $this->applyAbsoluteCornerAnchorSize($child, $childContext);
                $absCtx = $childContext->withOrigin($originX, $cursorY);
                $this->layoutBox($child, $absCtx);
                [$dx, $dy] = $this->resolveAbsoluteOffsets(
                    $child,
                    $childContext,
                    $originX,
                    $originY,
                    $cursorY,
                );
                if ($dx !== 0.0 || $dy !== 0.0) {
                    $this->shiftSubtree($child, $dy, $dx);
                }
                $prevBottomMargin = 0.0;
                continue;
            }
            // CSS 2.1 §9.5.2 — `clear` shifts an in-flow block past
            // floats on the specified side(s). Apply before laying the
            // child out so its geometry reflects the cleared cursor.
            $clearSide = $this->clearSide($child);
            if ($clearSide !== null && $childContext->floatContext !== null) {
                $cleared = $childContext->floatContext->clearTo($clearSide, $cursorY);
                if ($cleared > $cursorY) {
                    $delta = $cleared - $cursorY;
                    $cursorY = $cleared;
                    $total += $delta;
                }
            }
            // CSS 2.1 §9.5.1 — floats are taken out of normal flow. Lay
            // the float at the L/R edge of the containing block at the
            // current cursor Y (or below, when prior floats consume the
            // available width), register in FloatContext, then continue
            // without advancing the cursor.
            $floatSide = $this->floatSide($child);
            if ($floatSide !== null) {
                $this->layoutFloat(
                    $child,
                    $floatSide,
                    $childContext,
                    $originX,
                    $cursorY,
                );
                continue;
            }
            if ($pageHeight > 0.0
                && $this->forcesPageBreakBefore($child)
                && ($hasPrev || $cursorY > 0.001)
            ) {
                $aligned = $this->ceilToPage($cursorY, $pageHeight);
                if ($aligned > $cursorY) {
                    $delta = $aligned - $cursorY;
                    $cursorY = $aligned;
                    $total += $delta;
                }
            }
            $childOuterHeight = $this->layoutBox($child, $childContext->withOrigin($originX, $cursorY));
            if ($hasPrev) {
                $collapse = min($prevBottomMargin, $child->geometry->marginTop);
                if ($collapse > 0.0) {
                    $this->shiftSubtree($child, -$collapse);
                    $cursorY -= $collapse;
                    $total -= $collapse;
                }
            }
            if ($pageHeight > 0.0
                && $childOuterHeight > 0.0
                && $childOuterHeight <= $pageHeight
                && $this->avoidsBreakInside($child)
            ) {
                $childTop = $child->geometry->y;
                $childBottom = $childTop + $childOuterHeight;
                $startPage = (int) floor($childTop / $pageHeight);
                $endPage = (int) floor(($childBottom - 0.001) / $pageHeight);
                if ($endPage > $startPage) {
                    $aligned = ($startPage + 1) * $pageHeight;
                    $shift = $aligned - $childTop;
                    if ($shift > 0.0) {
                        $this->shiftSubtree($child, $shift);
                        $cursorY += $shift;
                        $total += $shift;
                    }
                }
            }
            $cursorY += $childOuterHeight;
            $total += $childOuterHeight;
            if ($pageHeight > 0.0 && $this->forcesPageBreakAfter($child)) {
                $aligned = $this->ceilToPage($cursorY, $pageHeight);
                if ($aligned > $cursorY) {
                    $delta = $aligned - $cursorY;
                    $cursorY = $aligned;
                    $total += $delta;
                }
            }
            $prevBottomMargin = $child->geometry->marginBottom;
            $hasPrev = true;
        }
        return $total;
    }

    /**
     * @return array{0:int, 1:float} `[columnCount, columnWidth]`
     */
    private function resolveColumns(CascadedValues $style, float $available, float $gap): array
    {
        $countValue = $style->get('column-count');
        $widthValue = $style->get('column-width');
        $countSet = !$this->isAuto($countValue);
        $widthSet = $widthValue instanceof Length;

        // Pull the integer count from `Integer` / `Number` (cascade may store
        // either). Clamp ≥ 1 — `column-count: 0` is invalid per spec.
        $count = 1;
        if ($countSet) {
            if ($countValue instanceof \Phpdftk\Css\Value\Integer) {
                $count = max(1, $countValue->value);
            } elseif ($countValue instanceof \Phpdftk\Css\Value\Number) {
                $count = max(1, (int) round($countValue->value));
            }
        }
        $width = $widthSet ? max(0.0, $widthValue->value) : 0.0;

        if ($countSet && !$widthSet) {
            $usedWidth = max(0.0, ($available - ($count - 1) * $gap) / $count);
            return [$count, $usedWidth];
        }
        if (!$countSet && $widthSet && $width > 0.0) {
            // CSS Multi-column 1 §7.1 step 4: N = max(1, floor((available + gap) / (width + gap))).
            $usedCount = max(1, (int) floor(($available + $gap) / ($width + $gap)));
            $usedWidth = max(0.0, ($available - ($usedCount - 1) * $gap) / $usedCount);
            return [$usedCount, $usedWidth];
        }
        if ($countSet && $widthSet) {
            // Both set: column-count wins; column-width becomes a minimum
            // (Phase 1 just uses count's distribution).
            $usedWidth = max(0.0, ($available - ($count - 1) * $gap) / $count);
            return [$count, $usedWidth];
        }
        return [1, $available];
    }

    /**
     * `column-gap: normal` resolves to `1em` per CSS Multi-column 1 §3.1.
     * CSS Values 4 §6.2 lets `0` appear as a unitless length, so accept
     * `Integer` / `Number` whose value is zero too.
     */
    private function resolveColumnGap(CascadedValues $style, LengthContext $lc): float
    {
        $value = $style->get('column-gap');
        if ($value instanceof Length) {
            return max(0.0, $value->value);
        }
        if ($value instanceof Percentage) {
            return 0.0; // Phase 1: no percentage basis defined.
        }
        if ($value instanceof \Phpdftk\Css\Value\Integer
            || $value instanceof \Phpdftk\Css\Value\Number
        ) {
            return max(0.0, (float) $value->value);
        }
        return max(0.0, $lc->currentFontSize);
    }

    /**
     * Resolve the main-axis gap for a flex container: `column-gap`
     * for row direction, `row-gap` for column direction. Both fall
     * back to `0px` for flex per CSS Box Alignment 3 §8.1 (only
     * multi-column resolves `normal` to `1em`).
     */
    private function resolveFlexMainGap(CascadedValues $style, LengthContext $lc, bool $isColumn): float
    {
        return $this->resolveFlexGapProperty($style, $isColumn ? 'row-gap' : 'column-gap');
    }

    private function resolveFlexGapProperty(CascadedValues $style, string $prop): float
    {
        $value = $style->get($prop);
        if ($value instanceof Length) {
            return max(0.0, $value->value);
        }
        if ($value instanceof Percentage) {
            return 0.0;
        }
        if ($value instanceof \Phpdftk\Css\Value\Integer
            || $value instanceof \Phpdftk\Css\Value\Number
        ) {
            return max(0.0, (float) $value->value);
        }
        return 0.0;
    }

    private function resolveColumnRuleWidth(CascadedValues $style): float
    {
        $styleValue = $style->get('column-rule-style');
        if ($styleValue instanceof Keyword
            && in_array(strtolower($styleValue->name), ['none', 'hidden'], true)
        ) {
            return 0.0;
        }
        $width = $style->get('column-rule-width');
        if ($width instanceof Length) {
            return max(0.0, $width->value);
        }
        return 0.0;
    }

    private function resolveColumnRuleStyle(CascadedValues $style): string
    {
        $value = $style->get('column-rule-style');
        if ($value instanceof Keyword) {
            return strtolower($value->name);
        }
        return 'none';
    }

    private function resolveColumnRuleColor(CascadedValues $style): ?\Phpdftk\Css\Value\Color
    {
        $value = $style->get('column-rule-color');
        if ($value instanceof \Phpdftk\Css\Value\Color) {
            return $value;
        }
        // `currentcolor` resolves against the cascaded `color` per CSS Color 3.
        if ($value instanceof Keyword && strtolower($value->name) === 'currentcolor') {
            $color = $style->get('color');
            return $color instanceof \Phpdftk\Css\Value\Color ? $color : null;
        }
        return null;
    }

    /** @param list<Box> $children */
    private function allInlineLevel(array $children): bool
    {
        foreach ($children as $c) {
            if (!(
                $c instanceof InlineBox
                || $c instanceof TextBox
                || $c instanceof AtomicInlineBox
                || $c instanceof \Phpdftk\HtmlToPdf\Box\LineBreakBox
            )) {
                return false;
            }
        }
        return true;
    }

    /**
     * Hand the inline children to {@see InlineLayout}, record the
     * resulting line boxes on the parent, and return the total height
     * consumed.
     *
     * After laying the lines out, run a fragmentation pass that shifts
     * any line straddling a page boundary down to the next page —
     * orphans/widows aware. Without this, viewers would render the
     * straddling line cut horizontally through the glyph mid-stroke.
     */
    private function layoutInlineChildren(Box $parent, LayoutContext $childContext): float
    {
        // Resolve every inline descendant's cascaded lengths so `font-size:
        // 0.83em` on `<sup>` / `<small>` etc. shapes at the right px size.
        // Block descendants already had this done in `layoutBlock`.
        $this->resolveInlineLengths($parent, $childContext->lengthContext);
        [$lines, $height] = $this->inlineLayout->layout(
            $parent,
            $parent->geometry->width,
            $childContext,
        );
        $height = $this->avoidLineSplitsAcrossPages(
            $lines,
            $parent,
            $childContext->containingBlockHeight,
            $height,
        );
        $parent->lineBoxes = $lines;
        return $height;
    }

    /**
     * CSS Fragmentation 4 §4.1 — line boxes must not be split between
     * pages. When a line's vertical extent crosses a page boundary, push
     * the line down to start exactly at the next boundary; subsequent
     * lines shift by the same amount so they keep their relative spacing.
     *
     * Also honours `orphans` / `widows` (default 2 each): when a paragraph
     * splits across pages, hold back enough trailing lines to fill the
     * widow count, and pull enough leading lines forward to satisfy the
     * orphan count. If the paragraph is too short to honour both (e.g.
     * 3 lines with orphans=2 widows=2), shift the whole paragraph to the
     * next page so it stays together.
     *
     * The `$lines` array is mutated in place; the parent's content height
     * is returned so the caller can record the new (possibly larger) box
     * height accounting for the inserted gaps.
     *
     * @param list<LineBox> $lines
     */
    private function avoidLineSplitsAcrossPages(
        array $lines,
        Box $parent,
        float $pageHeight,
        float $initialHeight,
    ): float {
        if ($pageHeight <= 0.0 || $lines === []) {
            return $initialHeight;
        }
        $orphans = max(1, $this->intStyle($parent->style, 'orphans', 2));
        $widows = max(1, $this->intStyle($parent->style, 'widows', 2));
        $parentTop = $parent->geometry->y;
        $count = count($lines);
        // Walk the lines in document order. The fragment boundary is the
        // first line whose page index differs from the previous line's, OR
        // a line that straddles the page boundary mid-glyph. At either
        // event, decide an actual `breakAt` index honouring orphans /
        // widows, then shift `[$breakAt..$count)` down by enough to start
        // the chosen line at the next page boundary.
        $prevStartPage = -1;
        for ($i = 0; $i < $count; $i++) {
            $line = $lines[$i];
            $absTop = $parentTop + $line->y;
            $absBot = $absTop + $line->height;
            $startPage = (int) floor($absTop / $pageHeight);
            $endPage = (int) floor(($absBot - 0.001) / $pageHeight);
            $straddles = $endPage > $startPage;
            $crossesBoundary = $prevStartPage >= 0 && $startPage > $prevStartPage;
            if (!$straddles && !$crossesBoundary) {
                $prevStartPage = $startPage;
                continue;
            }
            // Tentative break: at this line.
            $breakAt = $i;
            // Widows: hold back enough trailing lines so $count - $breakAt
            // ≥ $widows. Pull earlier lines forward into the next page.
            $remaining = $count - $breakAt;
            if ($remaining < $widows) {
                $breakAt = max(0, $count - $widows);
            }
            // Orphans: ensure at least $orphans lines stay on the previous
            // page (i.e. $breakAt ≥ $orphans). If we can't, push the
            // entire paragraph onto the next page.
            if ($breakAt > 0 && $breakAt < $orphans) {
                $breakAt = 0;
            }
            // The target page for $lines[$breakAt] is the page that the
            // *current* line should land on. For a straddling line, that's
            // (startPage + 1); for a clean cross, it's startPage. Compute
            // the boundary at the top of that page and shift breakAt to
            // sit on it. Lines pulled back by widows / orphans already
            // sit on an earlier page and get shifted onto the new page;
            // breakAt == i lines already on the new page get delta=0.
            $targetPage = $straddles ? $startPage + 1 : $startPage;
            $targetBoundary = $targetPage * $pageHeight;
            $absBreakTop = $parentTop + $lines[$breakAt]->y;
            $delta = $targetBoundary - $absBreakTop;
            if ($delta <= 0.0) {
                $prevStartPage = $startPage;
                continue;
            }
            for ($j = $breakAt; $j < $count; $j++) {
                $lines[$j]->y += $delta;
            }
            // Re-read $line's post-shift page so the next iteration
            // sees the correct previous-page state.
            $absTopShifted = $parentTop + $lines[$i]->y;
            $prevStartPage = (int) floor($absTopShifted / $pageHeight);
        }
        $last = $lines[$count - 1];
        return $last->y + $last->height;
    }

    private function intStyle(CascadedValues $style, string $name, int $default): int
    {
        $v = $style->get($name);
        if ($v instanceof \Phpdftk\Css\Value\Integer) {
            return $v->value;
        }
        if ($v instanceof \Phpdftk\Css\Value\Number) {
            return (int) round($v->value);
        }
        return $default;
    }

    /**
     * Advance `$y` to the next page boundary. CSS Fragmentation 4 §3.1
     * requires forced breaks to advance to the next fragmentainer even
     * when the box already sits at a page start; the caller is
     * responsible for not invoking this on the very first element of
     * the document (where it'd produce an empty leading page).
     */
    private function ceilToPage(float $y, float $pageHeight): float
    {
        if ($pageHeight <= 0.0) {
            return $y;
        }
        $page = (int) floor($y / $pageHeight);
        $start = $page * $pageHeight;
        if (abs($y - $start) < 0.001) {
            return $y + $pageHeight;
        }
        return ($page + 1) * $pageHeight;
    }

    private function forcesPageBreakBefore(Box $box): bool
    {
        return $this->declaresForcedBreak($box->style->get('break-before'))
            || $this->declaresForcedBreak($box->style->get('page-break-before'))
            || $this->declaresNamedPage($box->style->get('page'));
    }

    /**
     * CSS Paged Media 3 §3.4: when `page` is a non-auto identifier,
     * it implicitly forces a page break before the box so the
     * declared page type can apply to a fresh page.
     */
    private function declaresNamedPage(?\Phpdftk\Css\Value\Value $value): bool
    {
        if (!($value instanceof \Phpdftk\Css\Value\Keyword)) {
            return false;
        }
        return strtolower($value->name) !== 'auto';
    }

    private function forcesPageBreakAfter(Box $box): bool
    {
        return $this->declaresForcedBreak($box->style->get('break-after'))
            || $this->declaresForcedBreak($box->style->get('page-break-after'));
    }

    /**
     * `break-before: page` / `always` / `left` / `right` / `recto` / `verso`
     * all force a page break in CSS Fragmentation 4 §3.1. The legacy
     * `page-break-*` aliases accept `always` (mapped to `page`) and the
     * left/right variants.
     */
    private function avoidsBreakInside(Box $box): bool
    {
        return $this->declaresBreakInsideAvoid($box->style->get('break-inside'))
            || $this->declaresBreakInsideAvoid($box->style->get('page-break-inside'));
    }

    private function declaresBreakInsideAvoid(?\Phpdftk\Css\Value\Value $value): bool
    {
        return $value instanceof \Phpdftk\Css\Value\Keyword
            && in_array(strtolower($value->name), ['avoid', 'avoid-page', 'avoid-column'], true);
    }

    private function declaresForcedBreak(?\Phpdftk\Css\Value\Value $value): bool
    {
        if (!($value instanceof \Phpdftk\Css\Value\Keyword)) {
            return false;
        }
        return in_array(strtolower($value->name), ['page', 'always', 'all', 'left', 'right', 'recto', 'verso'], true);
    }

    private function forcesColumnBreakBefore(Box $box): bool
    {
        return $this->declaresForcedColumnBreak($box->style->get('break-before'));
    }

    private function forcesColumnBreakAfter(Box $box): bool
    {
        return $this->declaresForcedColumnBreak($box->style->get('break-after'));
    }

    /**
     * CSS Fragmentation 4 §3.1 — `break-before/after: column` forces a
     * column break. `always` / `all` force a break of any type, so they
     * count too. There is no legacy `page-break-*` alias for `column`;
     * authors use the modern `break-*` properties exclusively.
     */
    private function declaresForcedColumnBreak(?\Phpdftk\Css\Value\Value $value): bool
    {
        if (!($value instanceof \Phpdftk\Css\Value\Keyword)) {
            return false;
        }
        return in_array(strtolower($value->name), ['column', 'always', 'all'], true);
    }

    /**
     * Walk a `<table>`'s `<col>` and `<colgroup>` DOM children to
     * extract explicit per-column widths (HTML 5 §4.9.4). The legacy
     * `width="N"` attribute is honoured as pixel widths; CSS `width:`
     * on `<col>` (Phase 2) and percentage / `*` proportional widths
     * (Phase 2) are not yet supported.
     *
     * Returns a fixed-size array of length `$totalColumns` with `null`
     * entries for columns that didn't get an explicit width.
     *
     * @return list<?float>
     */
    private function collectColumnWidths(\Phpdftk\HtmlToPdf\Box\TableBox $table, int $totalColumns): array
    {
        /** @var list<?float> $widths */
        $widths = array_fill(0, $totalColumns, null);
        if ($table->element === null || $totalColumns === 0) {
            return $widths;
        }
        // Build an element→box index so we can read each `<col>`'s
        // cascade — width as Length / Percentage on the cascaded box,
        // not just the legacy HTML `width="N"` attribute. Walk the
        // table's box-tree children (and one-deep into TableColumnBox
        // groups) since `<col>` boxes nest inside `<colgroup>` boxes
        // in the DOM mirror.
        //
        // collectColumnWidths runs BEFORE layoutBlock sets the table's
        // geometry.width, so prefer the cascaded `width` (the explicit
        // declared value resolved by the cascade) — that's the basis
        // CSS Tables 3 §4.4 uses for percentage column widths anyway.
        $tableWidthValue = $table->style->get('width');
        $tableContentWidth = $tableWidthValue instanceof Length
            ? $tableWidthValue->value
            : max(0.0, $table->geometry->width);
        /** @var array<int, Box> $colBoxByElementId */
        $colBoxByElementId = [];
        foreach ($table->children as $tc) {
            $tcEl = $tc->element;
            if ($tcEl !== null) {
                $colBoxByElementId[spl_object_id($tcEl)] = $tc;
            }
            foreach ($tc->children as $sub) {
                $subEl = $sub->element;
                if ($subEl !== null) {
                    $colBoxByElementId[spl_object_id($subEl)] = $sub;
                }
            }
        }
        $col = 0;
        foreach ($table->element->children() as $child) {
            if ($col >= $totalColumns) {
                break;
            }
            $tag = strtolower($child->localName);
            if ($tag === 'col') {
                $box = $colBoxByElementId[spl_object_id($child)] ?? null;
                $col = $this->applyColWidth($child, $box, $tableContentWidth, $widths, $col);
            } elseif ($tag === 'colgroup') {
                $inner = $child->children();
                $hasNested = false;
                foreach ($inner as $sub) {
                    if (strtolower($sub->localName) === 'col') {
                        $hasNested = true;
                        $subBox = $colBoxByElementId[spl_object_id($sub)] ?? null;
                        $col = $this->applyColWidth($sub, $subBox, $tableContentWidth, $widths, $col);
                        if ($col >= $totalColumns) {
                            break;
                        }
                    }
                }
                if (!$hasNested) {
                    // Group with no nested `<col>` applies its own
                    // span (HTML 5 §4.9.3) — its width attribute (if
                    // any) flows to each spanned column.
                    $box = $colBoxByElementId[spl_object_id($child)] ?? null;
                    $col = $this->applyColWidth($child, $box, $tableContentWidth, $widths, $col);
                }
            }
        }
        return $widths;
    }

    /**
     * CSS Tables 3 §10.4 — for each `null` (auto) entry in
     * `currentColumnWidths`, measure the max-content of cells
     * anchored in that column via `measureMinMaxContent`. Auto
     * columns then scale proportionally to fill (or shrink to fit)
     * the remaining table width after explicit widths are
     * subtracted.
     *
     * Multi-column-spanning cells distribute their max-content
     * equally across the spanned columns as a Phase-2
     * simplification — the spec prescribes a more involved
     * distribution that takes the min/max-content envelope of
     * non-spanning cells into account.
     *
     * When ALL columns have explicit widths, this is a no-op.
     */
    private function resolveAutoColumnContentWidths(
        int $totalColumns,
        float $tableContentWidth,
        LayoutContext $context,
    ): void {
        if ($this->currentColumnWidths === null || $totalColumns <= 0) {
            return;
        }
        // Check if any column is auto (null).
        $hasAuto = false;
        foreach ($this->currentColumnWidths as $w) {
            if ($w === null) {
                $hasAuto = true;
                break;
            }
        }
        if (!$hasAuto) {
            return;
        }
        // Per-column max-content accumulation.
        $colMax = array_fill(0, $totalColumns, 0.0);
        $grid = $this->currentTableCellGrid ?? [];
        foreach ($this->resolvedCellReferences as $cellId => $cell) {
            $info = $grid[$cellId] ?? null;
            if ($info === null) {
                continue;
            }
            $mm = $this->measureMinMaxContent($cell, $context);
            $share = $mm['max'] / max(1, $info['colspan']);
            for (
                $c = $info['col'];
                $c < $info['col'] + $info['colspan'] && $c < $totalColumns;
                $c++
            ) {
                if ($colMax[$c] < $share) {
                    $colMax[$c] = $share;
                }
            }
        }
        // Sum explicit widths + auto max-content widths.
        $explicitSum = 0.0;
        $autoMaxSum = 0.0;
        foreach ($this->currentColumnWidths as $i => $w) {
            if ($w !== null) {
                $explicitSum += $w;
            } else {
                $autoMaxSum += $colMax[$i];
            }
        }
        // When every auto column has zero measured content (empty
        // cells), leave the nulls so the existing equal-share
        // distribution in `resolveColumnWidthGrid` takes over —
        // that matches what authors expect when laying out an
        // empty grid scaffold for borders / scaffolding.
        if ($autoMaxSum <= 0.0) {
            return;
        }
        $availableForAuto = max(0.0, $tableContentWidth - $explicitSum);
        $scale = $availableForAuto / $autoMaxSum;
        foreach ($this->currentColumnWidths as $i => $w) {
            if ($w === null) {
                $this->currentColumnWidths[$i] = $colMax[$i] * $scale;
            }
        }
    }

    /**
     * Apply one `<col>` / `<colgroup>` element's `width` and `span`
     * attributes to the column-width array, starting at `$startCol`.
     * Returns the new cursor position (= startCol + span, clamped).
     *
     * @param list<?float> $widths
     */
    private function applyColWidth(
        \Phpdftk\Html\Dom\Element $col,
        ?Box $colBox,
        float $tableContentWidth,
        array &$widths,
        int $startCol,
    ): int {
        $spanAttr = $col->getAttribute('span');
        $span = 1;
        if ($spanAttr !== null && preg_match('/^\d+$/', trim($spanAttr)) === 1) {
            $span = max(1, (int) trim($spanAttr));
        }
        // Prefer the cascaded CSS `width` (Length pixels or Percentage
        // of the table content width) over the legacy HTML attribute,
        // matching browser priority for `<col>` / `<colgroup>`.
        $width = null;
        if ($colBox !== null) {
            $cssWidth = $colBox->style->get('width');
            if ($cssWidth instanceof Length) {
                $width = $cssWidth->value;
            } elseif ($cssWidth instanceof \Phpdftk\Css\Value\Percentage) {
                $width = $tableContentWidth * ($cssWidth->value / 100.0);
            }
        }
        if ($width === null) {
            $width = $this->parseLegacyWidth($col->getAttribute('width'));
        }
        $end = min($startCol + $span, count($widths));
        for ($i = $startCol; $i < $end; $i++) {
            if ($widths[$i] === null) {
                $widths[$i] = $width;
            }
        }
        return $end;
    }

    /**
     * HTML 5 legacy `width="N"` attribute parsing: plain integer
     * means pixels; trailing `%` (percentage) and `*` (proportional)
     * forms are Phase 2.
     */
    private function parseLegacyWidth(?string $raw): ?float
    {
        if ($raw === null) {
            return null;
        }
        $trim = trim($raw);
        if (preg_match('/^(\d+)$/', $trim, $m) === 1) {
            return (float) $m[1];
        }
        return null;
    }

    /**
     * Build the per-column width array used by `layoutTableRow`. When
     * `$explicit` is null (no `<col>` info) every column gets an equal
     * share of the row width. Otherwise: explicit widths come through
     * directly; the remaining slack divides evenly across the auto
     * columns. When the explicit widths overflow the row width the
     * auto columns get 0 and the overflow is absorbed (no negative
     * widths).
     *
     * @param ?list<?float> $explicit
     * @return list<float>
     */
    private function resolveColumnWidthGrid(int $totalColumns, float $rowWidth, ?array $explicit): array
    {
        if ($totalColumns <= 0) {
            return [];
        }
        if ($explicit === null) {
            $share = $rowWidth / $totalColumns;
            return array_fill(0, $totalColumns, $share);
        }
        $explicitSum = 0.0;
        $autoCount = 0;
        foreach ($explicit as $w) {
            if ($w === null) {
                $autoCount++;
                continue;
            }
            $explicitSum += $w;
        }
        $autoShare = $autoCount > 0
            ? max(0.0, ($rowWidth - $explicitSum) / $autoCount)
            : 0.0;
        $out = [];
        foreach ($explicit as $w) {
            $out[] = $w ?? $autoShare;
        }
        return $out;
    }

    /**
     * Pre-walk every `<tr>` descendant of a `<table>` to assign each
     * cell its resolved (row, col) coordinate and (colspan, rowspan)
     * extent, taking prior rows' rowspan-occupied columns into
     * account. HTML 5 §11.1.4 has the full algorithm; this is a
     * straightforward implementation of "growing a grid" — for each
     * row, walk cells in order, find the first free column from the
     * current cursor, claim the cells at (col..col+colspan-1) ×
     * (row..row+rowspan-1).
     *
     * @return array<int, array{row: int, col: int, rowspan: int, colspan: int}>
     */
    private function precomputeTableCellGrid(\Phpdftk\HtmlToPdf\Box\TableBox $table): array
    {
        /** @var array<int, array{row: int, col: int, rowspan: int, colspan: int}> $grid */
        $grid = [];
        $this->resolvedCellReferences = [];
        /** @var list<array<int, bool>> $occupancy occupancy[row][col] */
        $occupancy = [];
        $rowIndex = 0;
        $rows = $this->collectTableRows($table);
        foreach ($rows as $row) {
            if (!isset($occupancy[$rowIndex])) {
                $occupancy[$rowIndex] = [];
            }
            $col = 0;
            foreach ($row->children as $cell) {
                if (!($cell instanceof \Phpdftk\HtmlToPdf\Box\TableCellBox)) {
                    continue;
                }
                $colspan = max(1, $this->cellColspan($cell));
                $rowspan = max(1, $this->resolveCellRowspan($cell));
                // Advance cursor past any column already claimed by a
                // prior row's rowspan.
                while (!empty($occupancy[$rowIndex][$col])) {
                    $col++;
                }
                $cellId = spl_object_id($cell);
                $grid[$cellId] = [
                    'row' => $rowIndex,
                    'col' => $col,
                    'rowspan' => $rowspan,
                    'colspan' => $colspan,
                ];
                $this->resolvedCellReferences[$cellId] = $cell;
                // Mark every covered (row, col) as occupied.
                for ($r = 0; $r < $rowspan; $r++) {
                    $absRow = $rowIndex + $r;
                    if (!isset($occupancy[$absRow])) {
                        $occupancy[$absRow] = [];
                    }
                    for ($c = 0; $c < $colspan; $c++) {
                        $occupancy[$absRow][$col + $c] = true;
                    }
                }
                $col += $colspan;
            }
            $rowIndex++;
        }
        return $grid;
    }

    /**
     * Collect every TableRowBox descendant of `$table` in document
     * order (handles implicit `<tbody>` / explicit `<thead>` /
     * `<tfoot>` wrappers).
     *
     * @return list<\Phpdftk\HtmlToPdf\Box\TableRowBox>
     */
    private function collectTableRows(Box $table): array
    {
        $rows = [];
        $stack = [$table];
        $seenTable = false;
        while ($stack !== []) {
            $node = array_shift($stack);
            if ($node instanceof \Phpdftk\HtmlToPdf\Box\TableRowBox) {
                $rows[] = $node;
                continue;
            }
            if ($seenTable && $node instanceof \Phpdftk\HtmlToPdf\Box\TableBox) {
                // Skip nested tables — their rows belong to themselves.
                continue;
            }
            if ($node instanceof \Phpdftk\HtmlToPdf\Box\TableBox) {
                $seenTable = true;
            }
            $children = $node->children;
            foreach (array_reverse($children) as $c) {
                array_unshift($stack, $c);
            }
        }
        return $rows;
    }

    /** @param array<int, array{row: int, col: int, rowspan: int, colspan: int}> $grid */
    private function maxColumnsFromGrid(array $grid): int
    {
        $max = 0;
        foreach ($grid as $entry) {
            $end = $entry['col'] + $entry['colspan'];
            if ($end > $max) {
                $max = $end;
            }
        }
        return $max;
    }

    /**
     * Look up a cell's resolved column index from the precomputed
     * cell grid; falls back to `$cursorFallback` when no grid is
     * available (test fixtures laying out a row in isolation).
     */
    private function resolveCellColumn(\Phpdftk\HtmlToPdf\Box\TableCellBox $cell, int $cursorFallback): int
    {
        $grid = $this->currentTableCellGrid;
        if ($grid === null) {
            return $cursorFallback;
        }
        $id = spl_object_id($cell);
        return $grid[$id]['col'] ?? $cursorFallback;
    }

    private function resolveRowIndex(\Phpdftk\HtmlToPdf\Box\TableRowBox $row): int
    {
        $grid = $this->currentTableCellGrid;
        if ($grid === null) {
            return -1;
        }
        // Find the row index from any of the row's cells.
        foreach ($row->children as $c) {
            if ($c instanceof \Phpdftk\HtmlToPdf\Box\TableCellBox) {
                $entry = $grid[spl_object_id($c)] ?? null;
                if ($entry !== null) {
                    return $entry['row'];
                }
            }
        }
        return -1;
    }

    /**
     * HTML 5 `<td rowspan="N">` / `<th rowspan="N">` — defaults to 1,
     * clamps to ≥ 1 even when the attribute is missing / non-numeric.
     */
    private function resolveCellRowspan(\Phpdftk\HtmlToPdf\Box\TableCellBox $cell): int
    {
        if ($cell->element === null) {
            return 1;
        }
        $raw = $cell->element->getAttribute('rowspan');
        if ($raw === null || preg_match('/^\d+$/', trim($raw)) !== 1) {
            return 1;
        }
        return max(1, (int) trim($raw));
    }

    /**
     * Post-pass after every row in a table has been laid out — extend
     * each rowspan-spanning cell's `geometry->height` to cover every
     * row it spans, using `$currentTableRowHeights` populated in
     * `layoutTableRow`. Subsequent rows have already positioned in
     * their natural Y slots so the spanning cell just visually
     * stretches downward.
     */
    private function finalizeRowspanHeights(): void
    {
        $grid = $this->currentTableCellGrid;
        if ($grid === null) {
            return;
        }
        // We need the cell object to mutate its geometry — keep a
        // map of cellId → cell while walking via spl_object_id.
        $byId = [];
        $stack = [];
        // Walk via the row-collection helper would require a table
        // ref — instead iterate the grid + look up cells from a fresh
        // walk. Simpler: walk the heights and mutate cells we have a
        // reference to (we already have them through the table's
        // child traversal).
        // The grid only stored metadata; mutate by finding cells via
        // the captured table reference. To avoid threading the table
        // through, store cell references in the grid entries too —
        // see below. For now the grid carries the metadata and we
        // expect the caller to have refreshed currentTableCellGrid
        // with cell references via storeCellReferences.
        foreach ($this->resolvedCellReferences as $id => $cell) {
            if (!isset($grid[$id])) {
                continue;
            }
            $entry = $grid[$id];
            if ($entry['rowspan'] <= 1) {
                continue;
            }
            $sum = 0.0;
            for ($r = 0; $r < $entry['rowspan']; $r++) {
                $sum += $this->currentTableRowHeights[$entry['row'] + $r] ?? 0.0;
            }
            // Only extend; never shrink below content height.
            if ($sum > $cell->geometry->height) {
                $cell->geometry->height = $sum;
            }
        }
    }

    /**
     * Resolved cell references keyed by `spl_object_id`. Populated by
     * `precomputeTableCellGrid` so `finalizeRowspanHeights` can
     * mutate cells without re-walking the tree.
     *
     * @var array<int, \Phpdftk\HtmlToPdf\Box\TableCellBox>
     */
    private array $resolvedCellReferences = [];

    /**
     * Reorder a `<table>`'s direct children so that `<caption>` boxes
     * with `caption-side: bottom` sit AFTER the rest of the table
     * content. Top-side captions (the default) stay in their original
     * relative position. Non-caption children keep document order
     * among themselves. CSS 2.1 §17.4.1.
     */
    private function reorderTableCaptions(\Phpdftk\HtmlToPdf\Box\TableBox $table): void
    {
        $top = [];
        $middle = [];
        $bottom = [];
        $hasBottomCaption = false;
        foreach ($table->children as $child) {
            if ($child->element !== null
                && strtolower($child->element->localName) === 'caption'
            ) {
                $side = $child->style->get('caption-side');
                if ($side instanceof Keyword && strtolower($side->name) === 'bottom') {
                    $bottom[] = $child;
                    $hasBottomCaption = true;
                    continue;
                }
                $top[] = $child;
                continue;
            }
            $middle[] = $child;
        }
        if (!$hasBottomCaption) {
            // Default top-only or no captions — children already in
            // the right order; skip the rebuild.
            return;
        }
        $table->children = array_merge($top, $middle, $bottom);
    }

    private function isBorderCollapse(\Phpdftk\HtmlToPdf\Box\TableBox $table): bool
    {
        $v = $table->style->get('border-collapse');
        return $v instanceof \Phpdftk\Css\Value\Keyword
            && strtolower($v->name) === 'collapse';
    }

    /**
     * CSS Tables 3 §11.2 "collapsing borders model" — for every joint
     * between two adjacent anchor cells, run border-conflict
     * resolution and zero the losing side. The winning side keeps
     * the resolved width so the painter draws one shared line.
     *
     * Resolution order: `hidden` on either side suppresses entirely;
     * otherwise the thicker border wins; on a width tie the style
     * precedence (double > solid > dashed > dotted > ridge > outset
     * > groove > inset > none) breaks it; on a style tie the right /
     * bottom neighbour wins (matching the Phase-1 direction bias).
     *
     * Outermost cell edges also conflict-resolve against the table's
     * own border on the corresponding side — the loser side zeroes
     * out so the painter never doubles a thick table border against
     * the outer cells.
     */
    private function collapseBorders(\Phpdftk\HtmlToPdf\Box\TableBox $table): void
    {
        $grid = $this->currentTableCellGrid ?? [];
        if ($grid === [] || $this->resolvedCellReferences === []) {
            return;
        }
        // Anchor-position lookup: only positions where a cell starts.
        /** @var array<int, array<int, \Phpdftk\HtmlToPdf\Box\TableCellBox>> $anchorAt */
        $anchorAt = [];
        foreach ($this->resolvedCellReferences as $cellId => $cell) {
            $info = $grid[$cellId] ?? null;
            if ($info === null) {
                continue;
            }
            $anchorAt[$info['row']][$info['col']] = $cell;
        }
        // Per-cell-side resolved widths. Tracked separately so a span
        // touching multiple neighbours keeps the max winning width.
        /** @var array<int, array{right?: float, left?: float, bottom?: float, top?: float}> $resolved */
        $resolved = [];
        /** @var array<int, array{right?: true, left?: true, bottom?: true, top?: true}> $touched */
        $touched = [];

        foreach ($this->resolvedCellReferences as $cellId => $cell) {
            $info = $grid[$cellId];
            $endCol = $info['col'] + $info['colspan'];
            $endRow = $info['row'] + $info['rowspan'];

            // RIGHT edge — resolve against every right neighbour
            // anchored at column $endCol within the spanned rows.
            for ($r = $info['row']; $r < $endRow; $r++) {
                $neighbor = $anchorAt[$r][$endCol] ?? null;
                if ($neighbor === null) {
                    continue;
                }
                [$aWidth, $bWidth] = $this->resolveCollapsedJoint($cell, 'right', $neighbor, 'left');
                $nid = spl_object_id($neighbor);
                $resolved[$cellId]['right'] = max($resolved[$cellId]['right'] ?? 0.0, $aWidth);
                $resolved[$nid]['left'] = max($resolved[$nid]['left'] ?? 0.0, $bWidth);
                $touched[$cellId]['right'] = true;
                $touched[$nid]['left'] = true;
            }

            // BOTTOM edge — resolve against every bottom neighbour
            // anchored at row $endRow within the spanned columns.
            for ($c = $info['col']; $c < $endCol; $c++) {
                $neighbor = $anchorAt[$endRow][$c] ?? null;
                if ($neighbor === null) {
                    continue;
                }
                [$aWidth, $bWidth] = $this->resolveCollapsedJoint($cell, 'bottom', $neighbor, 'top');
                $nid = spl_object_id($neighbor);
                $resolved[$cellId]['bottom'] = max($resolved[$cellId]['bottom'] ?? 0.0, $aWidth);
                $resolved[$nid]['top'] = max($resolved[$nid]['top'] ?? 0.0, $bWidth);
                $touched[$cellId]['bottom'] = true;
                $touched[$nid]['top'] = true;
            }
        }

        // Apply resolved widths. Sides not touched at this point are
        // still candidates for the outer-vs-table-border collapse
        // pass below.
        foreach ($this->resolvedCellReferences as $cellId => $cell) {
            if (isset($touched[$cellId]['right'])) {
                $cell->geometry->borderRight = $resolved[$cellId]['right'] ?? 0.0;
            }
            if (isset($touched[$cellId]['left'])) {
                $cell->geometry->borderLeft = $resolved[$cellId]['left'] ?? 0.0;
            }
            if (isset($touched[$cellId]['bottom'])) {
                $cell->geometry->borderBottom = $resolved[$cellId]['bottom'] ?? 0.0;
            }
            if (isset($touched[$cellId]['top'])) {
                $cell->geometry->borderTop = $resolved[$cellId]['top'] ?? 0.0;
            }
        }

        // Phase-2 step: collapse outermost cell edges against the
        // table's own declared border. For each side, the table's
        // border participates as one of the two combatants in
        // conflict resolution; the loser zeroes out so the painter
        // draws a single shared line. Cells inside spans on the
        // outer rim share the table's outer-side decision.
        $this->collapseOuterCellsAgainstTableBorder($table, $grid, $anchorAt);
    }

    /**
     * Walk the rim of the cell grid, conflict-resolving each outer
     * cell side against the table's matching side. The winner keeps
     * the resolved width on its geometry; the loser zeroes. When the
     * table side wins for at least one rim cell, the table's own
     * geometry stays as-is so it paints; when the cell side wins on
     * every spanned position, the table's matching geometry side
     * zeroes so it's not double-painted.
     *
     * @param array<int, array{row: int, col: int, rowspan: int, colspan: int}> $grid
     * @param array<int, array<int, \Phpdftk\HtmlToPdf\Box\TableCellBox>> $anchorAt
     */
    private function collapseOuterCellsAgainstTableBorder(
        \Phpdftk\HtmlToPdf\Box\TableBox $table,
        array $grid,
        array $anchorAt,
    ): void {
        // Discover the rim coordinates.
        $maxRow = -1;
        $maxCol = -1;
        foreach ($grid as $info) {
            $maxRow = max($maxRow, $info['row'] + $info['rowspan'] - 1);
            $maxCol = max($maxCol, $info['col'] + $info['colspan'] - 1);
        }
        if ($maxRow < 0 || $maxCol < 0) {
            return;
        }
        // Track whether the table's own side survives anywhere on
        // the rim — when a single outer cell wins the side, the
        // table border still paints at that segment, so we leave the
        // table side untouched. When the cell side loses everywhere,
        // we can zero it; when it wins everywhere, we zero the
        // table side. The conservative choice (preserve a side that
        // paints somewhere) keeps the rim visually correct without
        // per-segment painting.
        $tableSurvives = [
            'top' => false,
            'right' => false,
            'bottom' => false,
            'left' => false,
        ];
        $cellLosesEverywhere = [
            'top' => true,
            'right' => true,
            'bottom' => true,
            'left' => true,
        ];
        // Top + bottom rows.
        for ($c = 0; $c <= $maxCol; $c++) {
            $top = $anchorAt[0][$c] ?? null;
            if ($top !== null) {
                $this->rimResolve($table, $top, 'top', $tableSurvives, $cellLosesEverywhere);
            }
            $bottomCell = $this->cellOccupying($maxRow, $c, $grid, $anchorAt);
            if ($bottomCell !== null) {
                $this->rimResolve($table, $bottomCell, 'bottom', $tableSurvives, $cellLosesEverywhere);
            }
        }
        // Left + right columns.
        for ($r = 0; $r <= $maxRow; $r++) {
            $left = $anchorAt[$r][0] ?? null;
            if ($left !== null) {
                $this->rimResolve($table, $left, 'left', $tableSurvives, $cellLosesEverywhere);
            }
            $rightCell = $this->cellOccupying($r, $maxCol, $grid, $anchorAt);
            if ($rightCell !== null) {
                $this->rimResolve($table, $rightCell, 'right', $tableSurvives, $cellLosesEverywhere);
            }
        }
        // Zero the table's side when cell sides win on the whole rim.
        if (!$tableSurvives['top']) {
            $table->geometry->borderTop = 0.0;
        }
        if (!$tableSurvives['right']) {
            $table->geometry->borderRight = 0.0;
        }
        if (!$tableSurvives['bottom']) {
            $table->geometry->borderBottom = 0.0;
        }
        if (!$tableSurvives['left']) {
            $table->geometry->borderLeft = 0.0;
        }
    }

    /**
     * Find the cell occupying grid position (row, col) — either the
     * anchor cell at that position or a cell whose span covers it.
     *
     * @param array<int, array{row: int, col: int, rowspan: int, colspan: int}> $grid
     * @param array<int, array<int, \Phpdftk\HtmlToPdf\Box\TableCellBox>> $anchorAt
     */
    private function cellOccupying(int $row, int $col, array $grid, array $anchorAt): ?\Phpdftk\HtmlToPdf\Box\TableCellBox
    {
        if (isset($anchorAt[$row][$col])) {
            return $anchorAt[$row][$col];
        }
        foreach ($this->resolvedCellReferences as $cell) {
            $info = $grid[spl_object_id($cell)] ?? null;
            if ($info === null) {
                continue;
            }
            if ($row >= $info['row'] && $row < $info['row'] + $info['rowspan']
                && $col >= $info['col'] && $col < $info['col'] + $info['colspan']
            ) {
                return $cell;
            }
        }
        return null;
    }

    /**
     * Resolve one rim segment between a cell's outer side and the
     * table's matching side; apply the result to both geometries and
     * update the per-side survival flags.
     *
     * @param array<string, bool> $tableSurvives  Mutated in place.
     * @param array<string, bool> $cellLosesEverywhere Mutated in place.
     */
    private function rimResolve(
        \Phpdftk\HtmlToPdf\Box\TableBox $table,
        \Phpdftk\HtmlToPdf\Box\TableCellBox $cell,
        string $side,
        array &$tableSurvives,
        array &$cellLosesEverywhere,
    ): void {
        // The cell's outer side joins with the table's matching
        // side (cell-top↔table-top, cell-left↔table-left, etc.).
        [$cellWidth, $tableWidth] = $this->resolveCollapsedJoint($cell, $side, $table, $side);
        if ($cellWidth > 0.0) {
            // Cell side wins this segment — apply.
            $cell->geometry->{'border' . ucfirst($side)} = $cellWidth;
            $cellLosesEverywhere[$side] = false;
        } else {
            // Cell side loses (or `hidden` zeroes both). Zero the
            // cell side; the table side, if non-zero, paints across.
            $cell->geometry->{'border' . ucfirst($side)} = 0.0;
        }
        if ($tableWidth > 0.0) {
            $tableSurvives[$side] = true;
        }
    }

    /**
     * Resolve a single border joint between cell $a's $aSide and
     * cell $b's $bSide. Returns `[aWidth, bWidth]` — one of the two
     * is the winning width, the other is zero (or both are zero for
     * a `hidden` joint).
     *
     * @return array{0: float, 1: float}
     */
    private function resolveCollapsedJoint(Box $a, string $aSide, Box $b, string $bSide): array
    {
        $aStyle = $this->borderStyleName($a, $aSide);
        $bStyle = $this->borderStyleName($b, $bSide);
        // `hidden` on either side suppresses the joint entirely.
        if ($aStyle === 'hidden' || $bStyle === 'hidden') {
            return [0.0, 0.0];
        }
        // `none` contributes width 0 — the other side wins by default
        // (its own non-zero width survives; on tied zero both stay 0).
        $aWidth = ($aStyle === 'none') ? 0.0 : $this->borderDeclaredWidth($a, $aSide);
        $bWidth = ($bStyle === 'none') ? 0.0 : $this->borderDeclaredWidth($b, $bSide);
        if ($aWidth > $bWidth) {
            return [$aWidth, 0.0];
        }
        if ($bWidth > $aWidth) {
            return [0.0, $bWidth];
        }
        $aRank = $this->borderStylePrecedence($aStyle);
        $bRank = $this->borderStylePrecedence($bStyle);
        if ($aRank > $bRank) {
            return [$aWidth, 0.0];
        }
        if ($bRank > $aRank) {
            return [0.0, $bWidth];
        }
        // Tie on style — direction bias: B (the right/bottom
        // neighbour) wins so the visible line lives on B's side.
        return [0.0, $bWidth];
    }

    private function borderStyleName(Box $box, string $side): string
    {
        $v = $box->style->get("border-$side-style");
        if (!$v instanceof \Phpdftk\Css\Value\Keyword) {
            return 'none';
        }
        return strtolower($v->name);
    }

    private function borderDeclaredWidth(Box $box, string $side): float
    {
        return $this->resolveBorderWidthValue($box->style->get("border-$side-width"));
    }

    private function borderStylePrecedence(string $style): int
    {
        return match ($style) {
            'double' => 7,
            'solid' => 6,
            'dashed' => 5,
            'dotted' => 4,
            'ridge' => 3,
            'outset' => 2,
            'groove' => 1,
            'inset' => 0,
            default => -1,
        };
    }

    /**
     * HTML 5 `<td colspan>` / `<th colspan>` — defaults to 1, clamps to
     * ≥ 1 even when the attribute is missing / non-numeric.
     */
    private function cellColspan(\Phpdftk\HtmlToPdf\Box\TableCellBox $cell): int
    {
        if ($cell->element === null) {
            return 1;
        }
        $raw = $cell->element->getAttribute('colspan');
        if ($raw === null || preg_match('/^\d+$/', trim($raw)) !== 1) {
            return 1;
        }
        return max(1, (int) trim($raw));
    }

    private function resolveInlineLengths(Box $box, LengthContext $context): void
    {
        // Walk every inline descendant and resolve its lengths against an
        // updated context. Each inline level may change `currentFontSize`
        // for its descendants (so `1em` on a grandchild reflects the
        // grandparent's resolved font-size).
        $this->cascade->resolveLengths($box->style, $context);
        $fontSizeValue = $box->style->get('font-size');
        $childContext = $context;
        if ($fontSizeValue instanceof Length) {
            $childContext = $context->withCurrentFontSize($fontSizeValue->value);
        }
        foreach ($box->children as $child) {
            $this->resolveInlineLengths($child, $childContext);
        }
    }
}
