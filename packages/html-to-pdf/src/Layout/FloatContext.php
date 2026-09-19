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

    /**
     * @param array<string, mixed>|null $shape
     * @param array{x: float, y: float, width: float, height: float}|null $marginBox
     *        CSS 2.1 §9.5.1 margin box, when it differs from the
     *        exclusion rect (i.e. `shape-outside` contracted it).
     */
    public function addLeft(
        float $left,
        float $top,
        float $width,
        float $height,
        ?array $shape = null,
        ?array $marginBox = null,
    ): void {
        $this->items[] = new FloatItem(
            'left',
            $left,
            $top,
            $width,
            $height,
            $shape,
            $marginBox['x'] ?? null,
            $marginBox['y'] ?? null,
            $marginBox['width'] ?? null,
            $marginBox['height'] ?? null,
        );
    }

    /**
     * @param array<string, mixed>|null $shape
     * @param array{x: float, y: float, width: float, height: float}|null $marginBox
     */
    public function addRight(
        float $left,
        float $top,
        float $width,
        float $height,
        ?array $shape = null,
        ?array $marginBox = null,
    ): void {
        $this->items[] = new FloatItem(
            'right',
            $left,
            $top,
            $width,
            $height,
            $shape,
            $marginBox['x'] ?? null,
            $marginBox['y'] ?? null,
            $marginBox['width'] ?? null,
            $marginBox['height'] ?? null,
        );
    }

    /**
     * Right edge of the left floats' MARGIN boxes at `$y` — the
     * coordinate a new float must start at. Separate from
     * {@see leftEdgeAt}, which reports the exclusion contour that
     * LINE boxes flow around.
     */
    private function marginLeftEdgeAt(float $y, float $containingLeft): float
    {
        $edge = $containingLeft;
        foreach ($this->items as $item) {
            if ($item->side !== 'left') {
                continue;
            }
            $top = $item->marginBoxTop();
            if ($y + 0.001 >= $top && $y + 0.001 < $top + $item->marginBoxHeight()) {
                $rightEdge = $item->marginBoxLeft() + $item->marginBoxWidth();
                if ($rightEdge > $edge) {
                    $edge = $rightEdge;
                }
            }
        }
        return $edge;
    }

    /** Symmetric to {@see marginLeftEdgeAt} for right floats. */
    private function marginRightEdgeAt(float $y, float $containingRight): float
    {
        $edge = $containingRight;
        foreach ($this->items as $item) {
            if ($item->side !== 'right') {
                continue;
            }
            $top = $item->marginBoxTop();
            if ($y + 0.001 >= $top && $y + 0.001 < $top + $item->marginBoxHeight()) {
                $leftEdge = $item->marginBoxLeft();
                if ($leftEdge < $edge) {
                    $edge = $leftEdge;
                }
            }
        }
        return $edge;
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
        // CSS Shapes 1 — `shape-outside` defines the float area that
        // excludes LINE BOXES; it does not move the float itself or change
        // how floats stack against each other, which CSS 2.1 §9.5.1 settles
        // with margin boxes. So this search is deliberately shape-blind:
        // letting a concave shape report free space here squeezes a second
        // float in beside one it should have dropped below.
        //
        // Iterate over candidate Y positions: every existing float's top
        // and bottom edge is a candidate where availability might change.
        // Bounded loop — at most O(items) iterations.
        $checked = 0;
        $limit = max(1, count($this->items) * 2 + 2);
        while ($checked < $limit) {
            $left = $this->marginLeftEdgeAt($currentY, $containingLeft);
            $right = $this->marginRightEdgeAt($currentY, $containingRight);
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
            'left' => $this->marginLeftEdgeAt($currentY, $containingLeft),
            'right' => $this->marginRightEdgeAt($currentY, $containingRight),
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
            // §9.5.2 — clearance is past the float's MARGIN box.
            $bottom = $item->marginBoxTop() + $item->marginBoxHeight();
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
    public function leftEdgeAt(float $y, float $containingLeft, bool $ignoreShape = false): float
    {
        $edge = $containingLeft;
        foreach ($this->items as $item) {
            if ($item->side !== 'left') {
                continue;
            }
            if ($this->hasEmptyFloatArea($item, $ignoreShape)) {
                continue;
            }
            if ($y + 0.001 >= $item->top && $y + 0.001 < $item->top + $item->height) {
                $rightEdge = $this->itemRightEdgeAt($item, $y, $ignoreShape);
                if ($rightEdge > $edge) {
                    $edge = $rightEdge;
                }
            }
        }
        return $edge;
    }

    /**
     * CSS Shapes 1 §3 — a degenerate basic shape (`circle(0)`,
     * `ellipse(0% 0%)`, a sub-triangular `polygon()`) is well-formed
     * but encloses no area, so it "defines an empty float area": line
     * boxes flow straight through the float as if it declared no
     * exclusion at all. Such an item is skipped entirely rather than
     * contributing its bounding rect.
     */
    private function hasEmptyFloatArea(FloatItem $item, bool $ignoreShape): bool
    {
        return !$ignoreShape && ($item->shape['kind'] ?? null) === 'empty';
    }

    /**
     * Right edge of a left-float's exclusion region at `$y`. When the
     * item carries a `shape` (CSS Shapes 1 §3) the edge tracks the
     * shape's contour; otherwise it's the bounding rect's right edge.
     */
    private function itemRightEdgeAt(FloatItem $item, float $y, bool $ignoreShape = false): float
    {
        if ($ignoreShape || $item->shape === null) {
            return $item->left + $item->width;
        }
        // CSS Shapes 1 §1.1 — the float area is CLIPPED to the float's
        // margin box, so a shape larger than the float itself (say a
        // `circle(150px)` on a 50x50 float) cannot push content past
        // the float's own outer edge.
        return min(
            $item->left + $this->shapeRightEdgeLocal($item, $y),
            $item->marginBoxLeft() + $item->marginBoxWidth(),
        );
    }

    /**
     * Left edge of a right-float's exclusion region at `$y`.
     */
    private function itemLeftEdgeAt(FloatItem $item, float $y, bool $ignoreShape = false): float
    {
        if ($ignoreShape || $item->shape === null) {
            return $item->left;
        }
        // §1.1 clip, mirrored for a right float.
        return max(
            $item->left + $this->shapeLeftEdgeLocal($item, $y),
            $item->marginBoxLeft(),
        );
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
        if ($kind === 'polygon') {
            /** @var list<array{float, float}> $vertices */
            $vertices = $shape['vertices'] ?? [];
            $maxX = $this->polygonEdgesAt($vertices, $yLocal, max: true);
            return $maxX ?? 0.0;
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
        if ($kind === 'polygon') {
            /** @var list<array{float, float}> $vertices */
            $vertices = $shape['vertices'] ?? [];
            $minX = $this->polygonEdgesAt($vertices, $yLocal, max: false);
            return $minX ?? $item->width;
        }
        return 0.0;
    }

    /**
     * Scan a polygon's edges for those that cross the horizontal
     * line `y = $yLocal`. Return the max or min x crossing — for a
     * left float, the right-most crossing pushes inline text away;
     * for a right float, the left-most crossing pulls it back.
     *
     * Returns `null` when no edge crosses (the polygon doesn't
     * intersect this Y row at all). Callers treat `null` as "no
     * exclusion at this Y" — text flows freely.
     *
     * @param list<array{float, float}> $vertices
     */
    private function polygonEdgesAt(array $vertices, float $yLocal, bool $max): ?float
    {
        $n = count($vertices);
        if ($n < 2) {
            return null;
        }
        $best = null;
        for ($i = 0; $i < $n; $i++) {
            [$x1, $y1] = $vertices[$i];
            [$x2, $y2] = $vertices[($i + 1) % $n];
            // Skip horizontal edges — they don't cross a horizontal
            // sample line (infinitely many crossings).
            if (abs($y2 - $y1) < 0.0001) {
                continue;
            }
            $minY = min($y1, $y2);
            $maxY = max($y1, $y2);
            if ($yLocal + 0.0001 < $minY || $yLocal - 0.0001 > $maxY) {
                continue;
            }
            // Linear interpolation: x(y) = x1 + (y - y1) · (x2 - x1) / (y2 - y1)
            $x = $x1 + ($yLocal - $y1) * ($x2 - $x1) / ($y2 - $y1);
            if ($best === null
                || ($max && $x > $best)
                || (!$max && $x < $best)
            ) {
                $best = $x;
            }
        }
        return $best;
    }

    /**
     * Minimum of right-float left edges at `$y` (clamped to ≤
     * `$containingRight`) — where a line of inline content must end.
     */
    public function rightEdgeAt(float $y, float $containingRight, bool $ignoreShape = false): float
    {
        $edge = $containingRight;
        foreach ($this->items as $item) {
            if ($item->side !== 'right') {
                continue;
            }
            if ($this->hasEmptyFloatArea($item, $ignoreShape)) {
                continue;
            }
            if ($y + 0.001 >= $item->top && $y + 0.001 < $item->top + $item->height) {
                $leftEdge = $this->itemLeftEdgeAt($item, $y, $ignoreShape);
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
            $bottom = $item->marginBoxTop() + $item->marginBoxHeight();
            if ($bottom > $y + 0.001) {
                if ($next === null || $bottom < $next) {
                    $next = $bottom;
                }
            }
        }
        return $next;
    }
}
