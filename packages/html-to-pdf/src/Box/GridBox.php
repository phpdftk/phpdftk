<?php

declare(strict_types=1);

namespace Phpdftk\HtmlToPdf\Box;

/**
 * A box that establishes a grid formatting context — `display: grid`
 * (block-level) per CSS Grid Layout 2 §3.
 *
 * Phase-2 MVP: explicit-placement layout with `<length>` track lists.
 * `fr` units, `auto` track sizing, `repeat()`, `auto-fill` /
 * `auto-fit`, `grid-template-areas`, `grid-auto-{columns,rows}`,
 * `span N` syntax, subgrid, and `justify-self` / `align-self` are
 * intentional follow-ups.
 */
final class GridBox extends Box
{
    /**
     * CSS Grid Layout 3 — this box is a GRID LANES container
     * (`display: grid-lanes`), i.e. it establishes tracks in one axis
     * only and packs items freely in the other.
     */
    public bool $lanes = false;

    /**
     * CSS Grid Layout 3 §2.3 — which axis carries the tracks. `true`
     * (the default orientation) means the inline axis is the grid axis
     * and the lanes are columns; `false` means the block axis is, and
     * the lanes are rows. Only meaningful when {@see $lanes} is set.
     */
    public bool $lanesGridAxisIsInline = true;

    /**
     * CSS Grid Layout 3 — `grid-lanes-direction: … fill-reverse`. Items
     * fill each lane from the END of the stacking axis rather than its
     * start. Only meaningful when {@see $lanes} is set.
     */
    public bool $lanesFillReverse = false;

    /**
     * CSS Grid Layout 3 — `grid-lanes-direction: … track-reverse`. The
     * grid axis runs in the reverse direction, so the first lane the
     * placement algorithm fills sits at the grid axis' END edge — the
     * same relationship `direction: rtl` has with a column grid axis.
     * Only meaningful when {@see $lanes} is set.
     */
    public bool $lanesTrackReverse = false;

    /**
     * CSS Gaps 1 §2–§4 — resolved `column-rule` / `row-rule` gap-decoration
     * segments in top-down layout coordinates. Each entry carries the rule
     * prefix (`'column-rule'` or `'row-rule'`, which selects the `-width` /
     * `-style` / `-color` longhands) and the two endpoints of the
     * centre-line to stroke. Breaks (`*-rule-break`), per-item visibility
     * (`*-rule-visibility-items`) and endpoint insets (`*-rule-inset-*`)
     * are already applied. Populated by
     * {@see \Phpdftk\HtmlToPdf\Layout\BlockLayout::computeGridGapRuleSegments()}
     * and consumed by
     * {@see \Phpdftk\HtmlToPdf\Painter\Painter::paintGridGapRules()}.
     * Empty when the grid paints no gap decorations.
     *
     * @var list<array{prefix: string, x1: float, y1: float, x2: float, y2: float}>
     */
    public array $gapRuleSegments = [];
}
