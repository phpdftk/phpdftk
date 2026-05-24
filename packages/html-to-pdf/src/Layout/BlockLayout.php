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
        return $this->layoutBox($root, $context);
    }

    private function layoutBox(Box $box, LayoutContext $context): float
    {
        if ($box instanceof \Phpdftk\HtmlToPdf\Box\TableBox) {
            // Pre-walk to find the table's max columns so every row uses
            // the same column-width grid (CSS Tables 3 §4: columns are a
            // table-level concept).
            $prev = $this->currentTableColumns;
            $this->currentTableColumns = $this->maxColumnsIn($box);
            try {
                $height = $this->layoutBlock($box, $context);
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
        $colWidth = $geo->width / $totalColumns;
        $maxHeight = 0.0;
        $cellX = $geo->x;
        foreach ($cells as $i => $cell) {
            $cellWidth = $colWidth * $colspans[$i];
            $cellCtx = $context
                ->withContainingBlock($cellWidth, $context->containingBlockHeight)
                ->withOrigin($cellX, $geo->y);
            // Resolve cell-level CSS lengths against the cell's containing
            // block before recursing (mirrors `layoutBlock`'s pre-pass).
            $this->cascade->resolveLengths($cell->style, $cellCtx->lengthContext);
            $h = $this->layoutBlock($cell, $cellCtx);
            if ($h > $maxHeight) {
                $maxHeight = $h;
            }
            $cellX += $cellWidth;
        }
        $geo->height = $maxHeight;
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
        if ($box->children !== [] && $this->allInlineLevel($box->children)) {
            $childTotal = $this->layoutInlineChildren($box, $childContext);
        } else {
            $cursorY = $geo->y;
            $prevBottomMargin = 0.0;
            $hasPrev = false;
            $pageHeight = $context->containingBlockHeight;
            foreach ($box->children as $child) {
                $this->cascade->resolveLengths($child->style, $childContext->lengthContext);
                // CSS Fragmentation 4 §3 + legacy `page-break-before`: when
                // either declares a forced page break, advance the cursor
                // to the next page boundary before laying the child out.
                // The very-first-element-of-the-document case (no previous
                // sibling AND cursorY == 0) skips — otherwise a leading
                // `break-before: page` would synthesise an empty cover page.
                if ($pageHeight > 0.0
                    && $this->forcesPageBreakBefore($child)
                    && ($hasPrev || $cursorY > 0.001)
                ) {
                    $aligned = $this->ceilToPage($cursorY, $pageHeight);
                    if ($aligned > $cursorY) {
                        $delta = $aligned - $cursorY;
                        $cursorY = $aligned;
                        $childTotal += $delta;
                    }
                }
                $childOuterHeight = $this->layoutBox($child, $childContext->withOrigin($geo->x, $cursorY));
                if ($hasPrev) {
                    $collapse = min($prevBottomMargin, $child->geometry->marginTop);
                    if ($collapse > 0.0) {
                        $this->shiftSubtree($child, -$collapse);
                        $cursorY -= $collapse;
                        $childTotal -= $collapse;
                    }
                }
                // CSS Fragmentation 4 §3.2 `break-inside: avoid` /
                // legacy `page-break-inside: avoid`: when the child fits
                // on a single page but currently straddles a page
                // boundary, shift it down to start at the next boundary.
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
                            $childTotal += $shift;
                        }
                    }
                }
                $cursorY += $childOuterHeight;
                $childTotal += $childOuterHeight;
                // `page-break-after` / `break-after` on the child shoves the
                // cursor to the next page so the following sibling starts
                // there.
                if ($pageHeight > 0.0 && $this->forcesPageBreakAfter($child)) {
                    $aligned = $this->ceilToPage($cursorY, $pageHeight);
                    if ($aligned > $cursorY) {
                        $delta = $aligned - $cursorY;
                        $cursorY = $aligned;
                        $childTotal += $delta;
                    }
                }
                $prevBottomMargin = $child->geometry->marginBottom;
                $hasPrev = true;
            }
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

        return $geo->outerHeight();
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

    private function isAuto(?\Phpdftk\Css\Value\Value $value): bool
    {
        return $value instanceof Keyword && strtolower($value->name) === 'auto';
    }

    /**
     * Shift `$box` and every descendant's geometry by `$dy` along Y. Used by
     * margin collapsing to drag an already-placed child upward so its top
     * margin overlaps with its predecessor's bottom margin.
     */
    private function shiftSubtree(Box $box, float $dy): void
    {
        $box->geometry->y += $dy;
        foreach ($box->children as $child) {
            $this->shiftSubtree($child, $dy);
        }
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
        $parent->lineBoxes = $lines;
        return $height;
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
        return in_array(strtolower($value->name), ['page', 'always', 'left', 'right', 'recto', 'verso'], true);
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
     * Find the widest row in the table (by sum of colspans) so all rows
     * share the same column grid. Walks every `TableRowBox` descendant
     * — even ones nested inside an implicit `<tbody>` wrapper.
     */
    private function maxColumnsIn(Box $box): int
    {
        $max = 0;
        $stack = [$box];
        while ($stack !== []) {
            $node = array_pop($stack);
            if ($node instanceof \Phpdftk\HtmlToPdf\Box\TableRowBox) {
                $sum = 0;
                foreach ($node->children as $c) {
                    if ($c instanceof \Phpdftk\HtmlToPdf\Box\TableCellBox) {
                        $sum += $this->cellColspan($c);
                    }
                }
                $max = max($max, $sum);
                continue;
            }
            foreach ($node->children as $c) {
                $stack[] = $c;
            }
        }
        return max(1, $max);
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
