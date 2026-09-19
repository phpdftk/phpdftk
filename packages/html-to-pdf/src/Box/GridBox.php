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
     * CSS Gaps 1 — absolute layout-space centre coordinates of each
     * column gap (for vertical `column-rule` decorations) and row gap
     * (for horizontal `row-rule` decorations). Populated by
     * {@see \Phpdftk\HtmlToPdf\Layout\BlockLayout::layoutGridBox()} and
     * consumed by the painter. Empty when there are no gaps.
     *
     * @var list<float>
     */
    public array $columnGapCenters = [];

    /** @var list<float> */
    public array $rowGapCenters = [];

    /** Grid track area bounds in layout space — the rule span extents. */
    public float $gridContentLeft = 0.0;
    public float $gridContentRight = 0.0;
    public float $gridContentTop = 0.0;
    public float $gridContentBottom = 0.0;
}
