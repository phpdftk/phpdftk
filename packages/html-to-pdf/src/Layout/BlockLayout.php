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
        return $this->layoutBox($root, $context);
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
            $prevGrid = $this->currentTableCellGrid;
            $prevRowHeights = $this->currentTableRowHeights;
            $prevCellRefs = $this->resolvedCellReferences;
            $this->currentTableCellGrid = $this->precomputeTableCellGrid($box);
            $this->currentTableRowHeights = [];
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
                $this->currentTableCellGrid = $prevGrid;
                $this->currentTableRowHeights = $prevRowHeights;
                $this->resolvedCellReferences = $prevCellRefs;
            }
        }
        if ($box instanceof \Phpdftk\HtmlToPdf\Box\TableRowBox) {
            return $this->layoutTableRow($box, $context);
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
        // margins / borders / padding.
        $widthValue = $style->get('width');
        $widthAuto = $this->isAuto($widthValue);
        $contentWidth = $widthAuto
            ? max(
                0.0,
                $cbWidth - $geo->marginLeft - $geo->marginRight
                    - $geo->borderLeft - $geo->borderRight
                    - $geo->paddingLeft - $geo->paddingRight,
            )
            : $this->resolveLength($widthValue, $cbWidth);
        // CSS 2.1 §10.4 — clamp to [min-width, max-width]. min-width wins
        // when min > max (so `min: 100px; max: 50px` resolves to 100px).
        // `max-width: none` keyword leaves the upper bound unbounded;
        // numeric `auto` on min-width resolves to 0.
        $maxWidthValue = $style->get('max-width');
        if (!($maxWidthValue instanceof Keyword && strtolower($maxWidthValue->name) === 'none')) {
            $maxWidth = $this->resolveLength($maxWidthValue, $cbWidth);
            if ($maxWidth > 0.0 && $contentWidth > $maxWidth) {
                $contentWidth = $maxWidth;
                // Width fell out of `auto` territory — treat it like an
                // explicit length from here on so auto-margin slack
                // distribution kicks in below.
                $widthAuto = false;
            }
        }
        $minWidthValue = $style->get('min-width');
        $minWidth = $this->resolveLength($minWidthValue, $cbWidth);
        if ($minWidth > 0.0 && $contentWidth < $minWidth) {
            $contentWidth = $minWidth;
            $widthAuto = false;
        }
        $geo->width = $contentWidth;

        // CSS 2.1 §10.3.3 — `auto` margin redistribution. Only applies when
        // width is an explicit length (auto width already greedily fills
        // the available space). The remaining slack is split between the
        // auto-margin sides; `margin: 0 auto` centers a fixed-width box.
        if (!$widthAuto && ($marginLeftAuto || $marginRightAuto)) {
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

        // Resolve content height: explicit, percentage of containing block, or auto = children.
        $heightValue = $style->get('height');
        $heightIsAuto = $this->isAuto($heightValue);
        if ($heightIsAuto) {
            $geo->height = $childTotal;
        } else {
            $geo->height = $this->resolveLength($heightValue, $context->containingBlockHeight);
        }
        // CSS Sizing 4 §4.2 — `aspect-ratio` constrains height (or
        // width) when the other dimension is determined. Phase-1:
        // when height was auto AND a numeric ratio is set, override
        // the children-derived height with `width / ratio`. This
        // covers the common case (image / video wrapper sized by
        // explicit width with the ratio dictating the height).
        $ratio = $this->resolveAspectRatio($style);
        if ($ratio !== null && $heightIsAuto && $geo->width > 0.0 && $ratio > 0.0) {
            $geo->height = $geo->width / $ratio;
        }
        // CSS 2.1 §10.7 — clamp to [min-height, max-height]. Symmetric
        // with the width clamps above; `max-height: none` leaves the
        // upper bound unbounded.
        $maxHeightValue = $style->get('max-height');
        if (!($maxHeightValue instanceof Keyword && strtolower($maxHeightValue->name) === 'none')) {
            $maxHeight = $this->resolveLength($maxHeightValue, $context->containingBlockHeight);
            if ($maxHeight > 0.0 && $geo->height > $maxHeight) {
                $geo->height = $maxHeight;
            }
        }
        $minHeight = $this->resolveLength($style->get('min-height'), $context->containingBlockHeight);
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

        // CSS 2.1 §9.4.3 — `position: relative`. The box and its
        // descendants paint at their original layout position plus the
        // resolved offsets; siblings continue to flow against the
        // original position (this function returns `outerHeight()` from
        // the pre-shift geometry, which is what stackChildren uses to
        // advance its cursor — so siblings stay put).
        $positionValue = $style->get('position');
        if ($positionValue instanceof Keyword && strtolower($positionValue->name) === 'relative') {
            $relativeOuterHeight = $geo->outerHeight();
            [$dx, $dy] = $this->resolveRelativeOffsets($style, $context);
            if ($dx !== 0.0 || $dy !== 0.0) {
                $this->shiftSubtree($box, $dy, $dx);
            }
            return $relativeOuterHeight;
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
        if ($side === 'left') {
            $floatCtx->addLeft($targetX, $targetY, $floatWidth, $floatHeight);
        } else {
            $floatCtx->addRight($targetX, $targetY, $floatWidth, $floatHeight);
        }
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

        $dx = 0.0;
        if (!$this->isAuto($left)) {
            $dx = $this->resolveLength($left, $cbWidth);
        } elseif (!$this->isAuto($right)) {
            $rightOffset = $this->resolveLength($right, $cbWidth);
            $dx = $cbWidth - $rightOffset - $child->geometry->outerWidth();
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

    private function resolveLength(?\Phpdftk\Css\Value\Value $value, float $percentageBasis): float
    {
        if ($value === null) {
            return 0.0;
        }
        if ($value instanceof Length) {
            return $value->value;
        }
        if ($value instanceof Percentage) {
            return $value->value / 100.0 * $percentageBasis;
        }
        return 0.0;
    }

    private function resolveBorderWidth(CascadedValues $style, string $side): float
    {
        $styleValue = $style->get("border-$side-style");
        if ($styleValue instanceof Keyword && strtolower($styleValue->name) === 'none') {
            return 0.0;
        }
        $width = $style->get("border-$side-width");
        if ($width instanceof Length) {
            return $width->value;
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
            || $this->declaresForcedBreak($box->style->get('page-break-before'));
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
        $col = 0;
        foreach ($table->element->children() as $child) {
            if ($col >= $totalColumns) {
                break;
            }
            $tag = strtolower($child->localName);
            if ($tag === 'col') {
                $col = $this->applyColWidth($child, $widths, $col);
            } elseif ($tag === 'colgroup') {
                $inner = $child->children();
                $hasNested = false;
                foreach ($inner as $sub) {
                    if (strtolower($sub->localName) === 'col') {
                        $hasNested = true;
                        $col = $this->applyColWidth($sub, $widths, $col);
                        if ($col >= $totalColumns) {
                            break;
                        }
                    }
                }
                if (!$hasNested) {
                    // Group with no nested `<col>` applies its own
                    // span (HTML 5 §4.9.3) — its width attribute (if
                    // any) flows to each spanned column.
                    $col = $this->applyColWidth($child, $widths, $col);
                }
            }
        }
        return $widths;
    }

    /**
     * Apply one `<col>` / `<colgroup>` element's `width` and `span`
     * attributes to the column-width array, starting at `$startCol`.
     * Returns the new cursor position (= startCol + span, clamped).
     *
     * @param list<?float> $widths
     */
    private function applyColWidth(\Phpdftk\Html\Dom\Element $col, array &$widths, int $startCol): int
    {
        $spanAttr = $col->getAttribute('span');
        $span = 1;
        if ($spanAttr !== null && preg_match('/^\d+$/', trim($spanAttr)) === 1) {
            $span = max(1, (int) trim($spanAttr));
        }
        $width = $this->parseLegacyWidth($col->getAttribute('width'));
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
     * Walk the table's rows + cells; zero out border-right widths on cells
     * that aren't the last in their row, and border-bottom widths on cells
     * that aren't in the last row. Net effect: adjacent cells share a
     * single border line instead of doubling — the simple-case
     * approximation of CSS Tables 3 §11.2 "collapsing borders model".
     */
    private function collapseBorders(\Phpdftk\HtmlToPdf\Box\TableBox $table): void
    {
        /** @var list<\Phpdftk\HtmlToPdf\Box\TableRowBox> $rows */
        $rows = [];
        // Pre-order DFS in document order via reversed-child push.
        $stack = [$table];
        while ($stack !== []) {
            $node = array_shift($stack);
            if ($node instanceof \Phpdftk\HtmlToPdf\Box\TableRowBox) {
                $rows[] = $node;
                continue;
            }
            $children = $node->children;
            foreach (array_reverse($children) as $c) {
                array_unshift($stack, $c);
            }
        }
        $rowCount = count($rows);
        foreach ($rows as $rIdx => $row) {
            $cells = array_values(array_filter(
                $row->children,
                static fn($c): bool => $c instanceof \Phpdftk\HtmlToPdf\Box\TableCellBox,
            ));
            $cellCount = count($cells);
            foreach ($cells as $cIdx => $cell) {
                if ($cIdx < $cellCount - 1) {
                    $cell->geometry->borderRight = 0.0;
                }
                if ($rIdx < $rowCount - 1) {
                    $cell->geometry->borderBottom = 0.0;
                }
            }
        }
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
