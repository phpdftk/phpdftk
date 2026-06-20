<?php

declare(strict_types=1);

namespace Phpdftk\HtmlToPdf\Box;

/**
 * A box that establishes a grid formatting context — `display: grid`
 * (block-level) per CSS Grid Layout 2 §3.
 *
 * Implemented surface (see `BlockLayout::layoutGridBox`):
 *
 *  - `grid-template-{columns,rows}` with `<length>`, `fr`,
 *    `auto` / `min-content` / `max-content` content-sized tracks,
 *    `repeat(N, ...)`, `repeat(auto-fill, ...)`, `repeat(auto-fit, ...)`,
 *    `minmax(<min>, <max>)`, and `fit-content(<length>)`.
 *  - `grid-template-areas` named-area map with `grid-area: <name>`
 *    placement; implicit track counts when only areas are supplied.
 *  - `grid-{column,row}-{start,end}` with line numbers (positive
 *    and negative-from-end indices) and `span N` syntax.
 *  - `grid-auto-flow` row / column / dense with implicit-track
 *    growth via `grid-auto-{rows,columns}`.
 *  - `order`-based auto-placement walk order.
 *  - `column-gap` / `row-gap`.
 *  - `justify-self` / `align-self`: start / end / center / stretch,
 *    with the container's `justify-items` / `align-items` providing
 *    the default for items whose `*-self` is `auto`.
 *  - `justify-content` / `align-content` on the container
 *    distributing track slack: start / end / center / space-{between,
 *    around,evenly}.
 *  - Absolutely-positioned children are removed from the grid flow
 *    and laid out against the grid container as positioned ancestor.
 *
 * Deferred: subgrid, baseline alignment, `fit-content()` with
 * intrinsic-aware min-content / max-content distribution across
 * multi-span items, named grid lines.
 */
final class GridBox extends Box {}
