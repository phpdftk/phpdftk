<?php

declare(strict_types=1);

namespace Phpdftk\HtmlToPdf\Box;

/**
 * A box that establishes a flex formatting context — `display: flex`
 * (block-level) per CSS Flexible Box Layout 1 §3.
 *
 * Implemented surface (see `BlockLayout::layoutFlexBox`):
 *
 *  - `flex-direction`: row / row-reverse / column / column-reverse.
 *  - `flex-wrap`: nowrap / wrap / wrap-reverse, including the §9.6
 *    single-line cross-stretch rule.
 *  - `flex-grow` / `flex-shrink` / `flex-basis` resolved via the
 *    §9.7 "Resolve the Flexible Lengths" iterative algorithm with
 *    min/max main clamps.
 *  - `order`-based item reordering (stable on document order).
 *  - `justify-content`: flex-{start,end}, start, end, left, right,
 *    center, space-between, space-around, space-evenly (mirrored
 *    under reverse direction).
 *  - `align-items` / `align-self`: flex-{start,end}, start, end,
 *    center, stretch (cross-axis fills line extent).
 *  - `align-content`: stretch / center / flex-{start,end} /
 *    space-{between,around,evenly} across multiple flex lines.
 *  - `column-gap` / `row-gap` per Box Alignment 3 §8.1 axis pick;
 *    percentage gaps resolve against the container's content box.
 *  - `flex-basis: auto` + `width: auto` → max-content per §9.2.
 *  - `min-{width,height}: auto` floors at min-content per §4.5.
 *  - Absolutely-positioned children are removed from the flex
 *    flow and laid out against the flex container as positioned
 *    ancestor per §3.
 *  - Items resolve percentage heights against the flex container's
 *    declared height (not the outer CB).
 *
 * Deferred: baseline alignment, intrinsic-aspect-ratio transfer.
 */
final class FlexBox extends Box {}
