<?php

declare(strict_types=1);

namespace Phpdftk\HtmlToPdf\Tests\Layout;

use Phpdftk\HtmlToPdf\Layout\FloatContext;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for {@see FloatContext}. The float context is a small,
 * mathematically-defined data structure; testing it independently lets
 * the layout-level tests assume correct float math.
 *
 * Coverage is intentionally negative-biased (≥ 2:1) per the project's
 * negative-first testing skill: edge functions like `leftEdgeAt` /
 * `clearTo` have many ways to silently return the wrong number.
 */
final class FloatContextTest extends TestCase
{
    // ---- Negative cases ----

    public function testEmptyLeftEdgeIsContainingLeft(): void
    {
        $ctx = new FloatContext();
        self::assertSame(0.0, $ctx->leftEdgeAt(50.0, 0.0));
    }

    public function testEmptyRightEdgeIsContainingRight(): void
    {
        $ctx = new FloatContext();
        self::assertSame(600.0, $ctx->rightEdgeAt(50.0, 600.0));
    }

    public function testEmptyClearReturnsMinY(): void
    {
        $ctx = new FloatContext();
        foreach (['left', 'right', 'both'] as $side) {
            self::assertSame(100.0, $ctx->clearTo($side, 100.0));
        }
    }

    public function testFloatAboveQueryYHasNoEffect(): void
    {
        // Float at y=0..50; query at y=60 — past the float's bottom.
        $ctx = new FloatContext();
        $ctx->addLeft(0.0, 0.0, 100.0, 50.0);
        self::assertSame(0.0, $ctx->leftEdgeAt(60.0, 0.0));
    }

    public function testFloatBelowQueryYHasNoEffect(): void
    {
        // Float at y=100..150; query at y=50 — above the float's top.
        $ctx = new FloatContext();
        $ctx->addLeft(0.0, 100.0, 100.0, 50.0);
        self::assertSame(0.0, $ctx->leftEdgeAt(50.0, 0.0));
    }

    public function testRightFloatDoesNotShiftLeftEdge(): void
    {
        $ctx = new FloatContext();
        $ctx->addRight(500.0, 0.0, 100.0, 50.0);
        self::assertSame(0.0, $ctx->leftEdgeAt(25.0, 0.0));
    }

    public function testLeftFloatDoesNotShiftRightEdge(): void
    {
        $ctx = new FloatContext();
        $ctx->addLeft(0.0, 0.0, 100.0, 50.0);
        self::assertSame(600.0, $ctx->rightEdgeAt(25.0, 600.0));
    }

    public function testClearWrongSideIsNoOp(): void
    {
        // Only left floats — `clear: right` does nothing.
        $ctx = new FloatContext();
        $ctx->addLeft(0.0, 0.0, 100.0, 80.0);
        self::assertSame(0.0, $ctx->clearTo('right', 0.0));
    }

    public function testLeftEdgeAtFloatBottomExclusive(): void
    {
        // The half-open interval [top, top+height) means a query at
        // exactly `top + height` is past the float — back to container
        // left.
        $ctx = new FloatContext();
        $ctx->addLeft(0.0, 0.0, 100.0, 50.0);
        self::assertSame(0.0, $ctx->leftEdgeAt(50.0, 0.0));
    }

    public function testFitSlotNoOpWithoutFloats(): void
    {
        $ctx = new FloatContext();
        $slot = $ctx->fitSlot(10.0, 0.0, 600.0, 200.0);
        self::assertSame(0.0, $slot['left']);
        self::assertSame(600.0, $slot['right']);
        self::assertSame(10.0, $slot['y']);
    }

    public function testPlaceLeftWithoutFloatsLandsAtContainerLeft(): void
    {
        $ctx = new FloatContext();
        $placement = $ctx->placeLeft(0.0, 0.0, 600.0, 100.0);
        self::assertSame(0.0, $placement['x']);
        self::assertSame(0.0, $placement['y']);
    }

    // ---- Positive cases ----

    public function testLeftEdgeShiftedByActiveLeftFloat(): void
    {
        $ctx = new FloatContext();
        $ctx->addLeft(0.0, 0.0, 100.0, 50.0);
        self::assertSame(100.0, $ctx->leftEdgeAt(25.0, 0.0));
    }

    public function testRightEdgeShiftedByActiveRightFloat(): void
    {
        $ctx = new FloatContext();
        $ctx->addRight(500.0, 0.0, 100.0, 50.0);
        self::assertSame(500.0, $ctx->rightEdgeAt(25.0, 600.0));
    }

    public function testClearBothPastBothFloats(): void
    {
        // Left float 0..80, right float 0..120 → clear: both → 120.
        $ctx = new FloatContext();
        $ctx->addLeft(0.0, 0.0, 100.0, 80.0);
        $ctx->addRight(500.0, 0.0, 100.0, 120.0);
        self::assertSame(120.0, $ctx->clearTo('both', 0.0));
    }

    public function testFitSlotDropsBelowFloatWhenSpaceInsufficient(): void
    {
        // 100-wide left float + 100-wide right float in a 250-wide
        // container leaves 50px between them. Ask for a 200-wide slot
        // and the context drops Y past the floats' bottoms.
        $ctx = new FloatContext();
        $ctx->addLeft(0.0, 0.0, 100.0, 60.0);
        $ctx->addRight(150.0, 0.0, 100.0, 100.0);
        $slot = $ctx->fitSlot(0.0, 0.0, 250.0, 200.0);
        self::assertGreaterThanOrEqual(60.0, $slot['y']);
    }

    public function testCircleShapeContractsExclusionAtTopAndBottom(): void
    {
        // CSS Shapes 1 §3.2 — a circle of radius 50 centred at (50,50)
        // inside a 100×100 left float. At the float's vertical centre
        // (y=50) the exclusion right-edge is at x=100 (full radius);
        // at y=0 (the top) the edge collapses to x=50 (just the
        // center), letting text flow tight up against the float's
        // top-left corner.
        $ctx = new FloatContext();
        $ctx->addLeft(0.0, 0.0, 100.0, 100.0, [
            'kind' => 'circle',
            'cx' => 50.0,
            'cy' => 50.0,
            'r' => 50.0,
        ]);
        // Equator → full diameter as exclusion.
        self::assertEqualsWithDelta(100.0, $ctx->leftEdgeAt(50.0, 0.0), 0.001);
        // Just below the top (y=10) → exclusion narrower.
        self::assertLessThan(100.0, $ctx->leftEdgeAt(10.0, 0.0));
    }

    public function testEllipseShapeContractsExclusion(): void
    {
        // CSS Shapes 1 §3.3 — an ellipse with rx=80, ry=40 centred at
        // (80, 40) in a 160×80 left float. At y=40 (equator) the
        // exclusion edge is at x=160 (full rx + cx). At y=0 or y=80
        // the edge collapses to cx=80.
        $ctx = new FloatContext();
        $ctx->addLeft(0.0, 0.0, 160.0, 80.0, [
            'kind' => 'ellipse',
            'cx' => 80.0,
            'cy' => 40.0,
            'rx' => 80.0,
            'ry' => 40.0,
        ]);
        self::assertEqualsWithDelta(160.0, $ctx->leftEdgeAt(40.0, 0.0), 0.001);
        self::assertEqualsWithDelta(80.0, $ctx->leftEdgeAt(0.0, 0.0), 1.0);
    }

    public function testInsetEquivalentRectShrinksExclusion(): void
    {
        // CSS Shapes 1 §3.1 — `inset()` shrinks the float's exclusion
        // rect by the per-edge insets. We test the shrunk rect by
        // registering a smaller bounding rect directly; the layout
        // code in BlockLayout applies the inset before reaching
        // FloatContext, so this is the post-inset state.
        $ctx = new FloatContext();
        $ctx->addLeft(20.0, 20.0, 80.0, 60.0); // 100×100 outer, inset by 20px top+left
        // At y inside the inset area, exclusion right edge is 100.
        self::assertEqualsWithDelta(100.0, $ctx->leftEdgeAt(30.0, 0.0), 0.001);
        // Above the inset area (y=10), the float doesn't apply.
        self::assertSame(0.0, $ctx->leftEdgeAt(10.0, 0.0));
    }

    public function testPolygonShapeTriangleContractsExclusion(): void
    {
        // CSS Shapes 1 §3.4 — a triangle with vertices (0,0), (100,0),
        // (50,100) inside a 100×100 left float. At y=0 (top) the
        // exclusion spans the full width: right edge = 100. At y=50
        // (halfway down) the triangle has narrowed: right edge ≈ 75.
        // At y=100 (apex) the exclusion collapses to x=50.
        $ctx = new FloatContext();
        $ctx->addLeft(0.0, 0.0, 100.0, 100.0, [
            'kind' => 'polygon',
            'vertices' => [[0.0, 0.0], [100.0, 0.0], [50.0, 100.0]],
        ]);
        self::assertEqualsWithDelta(100.0, $ctx->leftEdgeAt(0.0, 0.0), 1.0);
        // The right edge of the triangle at y=50 lies along
        // the (100,0)-(50,100) edge: x = 100 - 50·(50/100) = 75.
        self::assertEqualsWithDelta(75.0, $ctx->leftEdgeAt(50.0, 0.0), 0.5);
        // Near the apex (y=99) the exclusion has narrowed to ≈ x=50.5
        // (the float's range is the half-open [top, bottom) interval,
        // so y=100 itself is outside).
        self::assertEqualsWithDelta(50.5, $ctx->leftEdgeAt(99.0, 0.0), 1.0);
    }

    public function testRectFloatStillUsesBoundingEdges(): void
    {
        // Negative test — `shape: null` keeps the legacy bounding-rect
        // behaviour. A 100×100 left float pushes line content all the
        // way to x=100 at every Y in its range.
        $ctx = new FloatContext();
        $ctx->addLeft(0.0, 0.0, 100.0, 100.0);
        self::assertSame(100.0, $ctx->leftEdgeAt(0.0, 0.0));
        self::assertSame(100.0, $ctx->leftEdgeAt(50.0, 0.0));
        self::assertSame(100.0, $ctx->leftEdgeAt(99.0, 0.0));
    }
}
