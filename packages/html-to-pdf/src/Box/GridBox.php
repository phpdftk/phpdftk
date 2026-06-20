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
final class GridBox extends Box {}
