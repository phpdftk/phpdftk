<?php

declare(strict_types=1);

namespace Phpdftk\PagedMedia;

/**
 * CSS Paged Media 3 §5.2 — the 16 page-margin box positions.
 *
 * A `@page` rule may contain up to 16 nested at-rules, one per box
 * position. Each one is a generated-content box positioned in the
 * page margin area; the content is set via the `content:` property.
 *
 * Layout:
 *
 *   ┌─────────┬────────────┬────────────┬────────────┬─────────┐
 *   │ TL-Corner│   T-Left   │   T-Center │   T-Right  │TR-Corner│
 *   ├─────────┼────────────┴────────────┴────────────┼─────────┤
 *   │  L-Top  │                                       │  R-Top  │
 *   ├─────────┤                                       ├─────────┤
 *   │L-Middle │           Page content box            │R-Middle │
 *   ├─────────┤                                       ├─────────┤
 *   │L-Bottom │                                       │R-Bottom │
 *   ├─────────┼────────────┬────────────┬────────────┼─────────┤
 *   │BL-Corner│   B-Left   │  B-Center  │   B-Right  │BR-Corner│
 *   └─────────┴────────────┴────────────┴────────────┴─────────┘
 *
 * The four corners are box positions but typically used for
 * decoration. The four edges hold the running headers and footers
 * (`@top-center`, `@bottom-center` are the most common).
 *
 * Phase 4G.1 (extraction) maps each position to its at-rule prelude
 * keyword (`@top-left`, `@top-center`, …) and its spatial
 * coordinates within the page margin area.
 */
enum MarginBoxPosition: string
{
    case TopLeftCorner = 'top-left-corner';
    case TopLeft = 'top-left';
    case TopCenter = 'top-center';
    case TopRight = 'top-right';
    case TopRightCorner = 'top-right-corner';

    case LeftTop = 'left-top';
    case LeftMiddle = 'left-middle';
    case LeftBottom = 'left-bottom';

    case RightTop = 'right-top';
    case RightMiddle = 'right-middle';
    case RightBottom = 'right-bottom';

    case BottomLeftCorner = 'bottom-left-corner';
    case BottomLeft = 'bottom-left';
    case BottomCenter = 'bottom-center';
    case BottomRight = 'bottom-right';
    case BottomRightCorner = 'bottom-right-corner';

    /**
     * True for the four corner boxes. Corners have a single
     * dimension determined by the adjacent margins (the top margin
     * for top corners, etc.) rather than flowing along an edge.
     */
    public function isCorner(): bool
    {
        return match ($this) {
            self::TopLeftCorner, self::TopRightCorner,
            self::BottomLeftCorner, self::BottomRightCorner => true,
            default => false,
        };
    }

    /**
     * The page edge this box sits along (`top` / `right` / `bottom`
     * / `left`). Corner boxes return `null` (they sit at the
     * intersection of two edges).
     */
    public function edge(): ?string
    {
        return match ($this) {
            self::TopLeft, self::TopCenter, self::TopRight => 'top',
            self::RightTop, self::RightMiddle, self::RightBottom => 'right',
            self::BottomLeft, self::BottomCenter, self::BottomRight => 'bottom',
            self::LeftTop, self::LeftMiddle, self::LeftBottom => 'left',
            default => null,
        };
    }
}
