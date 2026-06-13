<?php

declare(strict_types=1);

namespace Phpdftk\HtmlToPdf\Layout;

/**
 * Tracks the active floats inside a single block formatting context per
 * CSS 2.1 §9.5. Floats are added as they're laid out; subsequent inline
 * content queries `availableSlotAt` to learn how much horizontal space
 * is free at a given line Y, and `clearTo` is used by block layout to
 * skip a child past floats on the indicated side.
 *
 * Coordinates are layout-space (top-down, content-box of the containing
 * block as origin) — same as the rest of {@see BlockLayout}.
 */
final class FloatContext
{
    /** @var list<FloatItem> */
    private array $items = [];

    public function addLeft(float $left, float $top, float $width, float $height, ?array $shape = null): void
    {
        $this->items[] = new FloatItem('left', $left, $top, $width, $height, $shape);
    }

    public function addRight(float $left, float $top, float $width, float $height, ?array $shape = null): void
    {
        $this->items[] = new FloatItem('right', $left, $top, $width, $height, $shape);
    }

    /**
     * Find the next free horizontal slot wide enough for `$desiredWidth`
     * starting at `$y`, considering all active floats. Returns the
     * (lineLeft, lineRight, lineY) tuple where lineY may have been
     * shifted downward to skip past floats that would have made the
     * available width insufficient.
     *
     * `$containingLeft` and `$containingRight` are the parent block's
     * content-edge X bounds.
     *
     * @return array{left: float, right: float, y: float}
     */
    public function fitSlot(
        float $y,
        float $containingLeft,
        float $containingRight,
        float $desiredWidth,
    ): array {
        $currentY = $y;
        // Iterate over candidate Y positions: every existing float's top
        // and bottom edge is a candidate where availability might change.
        // Bounded loop — at most O(items) iterations.
        $checked = 0;
        $limit = max(1, count($this->items) * 2 + 2);
        while ($checked < $limit) {
            $left = $this->leftEdgeAt($currentY, $containingLeft);
            $right = $this->rightEdgeAt($currentY, $containingRight);
            $available = $right - $left;
            if ($available + 0.001 >= $desiredWidth) {
                return ['left' => $left, 'right' => $right, 'y' => $currentY];
            }
            $nextY = $this->nextFloatBottomBelow($currentY);
            if ($nextY === null) {
                // No more floats to skip past — return whatever slot is
                // available even if narrower than desired (the caller
                // accepts narrower lines; word wrap deals with overflow).
                return ['left' => $left, 'right' => $right, 'y' => $currentY];
            }
            $currentY = $nextY;
            $checked++;
        }
        return [
            'left' => $this->leftEdgeAt($currentY, $containingLeft),
            'right' => $this->rightEdgeAt($currentY, $containingRight),
            'y' => $currentY,
        ];
    }

    /**
     * Y position past every float on `$side` (or both sides for
     * `clear: both`) that intersects the half-open range `[$minY, ∞)`.
     * Used by `clear: left | right | both` to advance the cursor past
     * the appropriate floats.
     */
    public function clearTo(string $side, float $minY): float
    {
        $y = $minY;
        foreach ($this->items as $item) {
            if ($side !== 'both' && $item->side !== $side) {
                continue;
            }
            $bottom = $item->top + $item->height;
            if ($bottom > $y) {
                $y = $bottom;
            }
        }
        return $y;
    }

    /**
     * Pick the X coordinate where a new left float of `$width × $height`
     * should be placed at flow position `$y` inside container bounds
     * [containingLeft, containingRight]. Returns the (left-edge X, Y)
     * — the float may need to drop below existing floats to find a
     * wide-enough slot.
     *
     * @return array{x: float, y: float}
     */
    public function placeLeft(
        float $y,
        float $containingLeft,
        float $containingRight,
        float $width,
    ): array {
        $slot = $this->fitSlot($y, $containingLeft, $containingRight, $width);
        return ['x' => $slot['left'], 'y' => $slot['y']];
    }

    /**
     * Symmetric to `placeLeft` for right floats. Returns the float's
     * left-edge X (= right edge − width).
     *
     * @return array{x: float, y: float}
     */
    public function placeRight(
        float $y,
        float $containingLeft,
        float $containingRight,
        float $width,
    ): array {
        $slot = $this->fitSlot($y, $containingLeft, $containingRight, $width);
        return ['x' => $slot['right'] - $width, 'y' => $slot['y']];
    }

    /**
     * Sum of left-float right edges at `$y` (clamped to ≥ `$containingLeft`)
     * — i.e. the X coordinate where a line of inline content should start.
     */
    public function leftEdgeAt(float $y, float $containingLeft): float
    {
        $edge = $containingLeft;
        foreach ($this->items as $item) {
            if ($item->side !== 'left') {
                continue;
            }
            if ($y + 0.001 >= $item->top && $y + 0.001 < $item->top + $item->height) {
                $rightEdge = $this->itemRightEdgeAt($item, $y);
                if ($rightEdge > $edge) {
                    $edge = $rightEdge;
                }
            }
        }
        return $edge;
    }

    /**
     * Right edge of a left-float's exclusion region at `$y`. When the
     * item carries a `shape` (CSS Shapes 1 §3) the edge tracks the
     * shape's contour; otherwise it's the bounding rect's right edge.
     */
    private function itemRightEdgeAt(FloatItem $item, float $y): float
    {
        if ($item->shape === null) {
            return $item->left + $item->width;
        }
        return $item->left + $this->shapeRightEdgeLocal($item, $y);
    }

    /**
     * Left edge of a right-float's exclusion region at `$y`.
     */
    private function itemLeftEdgeAt(FloatItem $item, float $y): float
    {
        if ($item->shape === null) {
            return $item->left;
        }
        return $item->left + $this->shapeLeftEdgeLocal($item, $y);
    }

    /**
     * Right edge of the shape (in item-local coords) at `$y`. For a
     * left-float, this is the X past which inline content can flow.
     * Returns `width` (full bounding-rect edge) when the shape doesn't
     * intersect this Y, so the float still pushes text down past its
     * bottom edge as in the rect case.
     */
    private function shapeRightEdgeLocal(FloatItem $item, float $y): float
    {
        $yLocal = $y - $item->top;
        $shape = $item->shape;
        if ($shape === null) {
            return $item->width;
        }
        $kind = $shape['kind'] ?? null;
        if ($kind === 'circle') {
            $cx = (float) ($shape['cx'] ?? 0.0);
            $cy = (float) ($shape['cy'] ?? 0.0);
            $r = (float) ($shape['r'] ?? 0.0);
            $dy = $yLocal - $cy;
            if (abs($dy) > $r) {
                return 0.0;
            }
            $dx = sqrt(max(0.0, $r * $r - $dy * $dy));
            return $cx + $dx;
        }
        if ($kind === 'ellipse') {
            $cx = (float) ($shape['cx'] ?? 0.0);
            $cy = (float) ($shape['cy'] ?? 0.0);
            $rx = (float) ($shape['rx'] ?? 0.0);
            $ry = (float) ($shape['ry'] ?? 0.0);
            if ($rx <= 0.0 || $ry <= 0.0) {
                return $item->width;
            }
            $dy = $yLocal - $cy;
            if (abs($dy) > $ry) {
                return 0.0;
            }
            // x = rx · sqrt(1 - (dy/ry)²)
            $factor = sqrt(max(0.0, 1.0 - ($dy * $dy) / ($ry * $ry)));
            $dx = $rx * $factor;
            return $cx + $dx;
        }
        return $item->width;
    }

    /**
     * Left edge of the shape (in item-local coords) at `$y`, used by
     * right-floats. Returns 0 when the shape doesn't intersect this Y.
     */
    private function shapeLeftEdgeLocal(FloatItem $item, float $y): float
    {
        $yLocal = $y - $item->top;
        $shape = $item->shape;
        if ($shape === null) {
            return 0.0;
        }
        $kind = $shape['kind'] ?? null;
        if ($kind === 'circle') {
            $cx = (float) ($shape['cx'] ?? 0.0);
            $cy = (float) ($shape['cy'] ?? 0.0);
            $r = (float) ($shape['r'] ?? 0.0);
            $dy = $yLocal - $cy;
            if (abs($dy) > $r) {
                return $item->width;
            }
            $dx = sqrt(max(0.0, $r * $r - $dy * $dy));
            return $cx - $dx;
        }
        if ($kind === 'ellipse') {
            $cx = (float) ($shape['cx'] ?? 0.0);
            $cy = (float) ($shape['cy'] ?? 0.0);
            $rx = (float) ($shape['rx'] ?? 0.0);
            $ry = (float) ($shape['ry'] ?? 0.0);
            if ($rx <= 0.0 || $ry <= 0.0) {
                return 0.0;
            }
            $dy = $yLocal - $cy;
            if (abs($dy) > $ry) {
                return $item->width;
            }
            $factor = sqrt(max(0.0, 1.0 - ($dy * $dy) / ($ry * $ry)));
            $dx = $rx * $factor;
            return $cx - $dx;
        }
        return 0.0;
    }

    /**
     * Minimum of right-float left edges at `$y` (clamped to ≤
     * `$containingRight`) — where a line of inline content must end.
     */
    public function rightEdgeAt(float $y, float $containingRight): float
    {
        $edge = $containingRight;
        foreach ($this->items as $item) {
            if ($item->side !== 'right') {
                continue;
            }
            if ($y + 0.001 >= $item->top && $y + 0.001 < $item->top + $item->height) {
                $leftEdge = $this->itemLeftEdgeAt($item, $y);
                if ($leftEdge < $edge) {
                    $edge = $leftEdge;
                }
            }
        }
        return $edge;
    }

    /**
     * Smallest float-bottom that is strictly greater than `$y`. Returns
     * null when no active float ends below `$y`.
     */
    private function nextFloatBottomBelow(float $y): ?float
    {
        $next = null;
        foreach ($this->items as $item) {
            $bottom = $item->top + $item->height;
            if ($bottom > $y + 0.001) {
                if ($next === null || $bottom < $next) {
                    $next = $bottom;
                }
            }
        }
        return $next;
    }
}
