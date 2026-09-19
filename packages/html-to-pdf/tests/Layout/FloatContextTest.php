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

    public function testPolygonFromFlattenedPathPreservesEnvelope(): void
    {
        // CSS Shapes 1 §3.5 — `path()` shapes flatten down to a
        // polygon vertex list. Here we simulate the flattening
        // output of `path('M 0 0 L 100 0 L 100 100 Z')` (a right
        // triangle) and verify the exclusion query at the apex
        // matches the polygon evaluator. The actual SVG path
        // parsing happens in BlockLayout::flattenSvgPath; this
        // test focuses on the FloatContext consumer.
        $ctx = new FloatContext();
        $ctx->addLeft(0.0, 0.0, 100.0, 100.0, [
            'kind' => 'polygon',
            'vertices' => [[0.0, 0.0], [100.0, 0.0], [100.0, 100.0], [0.0, 0.0]],
        ]);
        // At y=0 (top edge), exclusion = 100.
        self::assertEqualsWithDelta(100.0, $ctx->leftEdgeAt(0.0, 0.0), 1.0);
        // At y=50 (halfway down), the hypotenuse runs from (0,0) to (100,100).
        // Exclusion right-edge at y=50 should be ≈ 100 (right edge),
        // because the polygon includes the right vertical edge.
        self::assertEqualsWithDelta(100.0, $ctx->leftEdgeAt(50.0, 0.0), 1.0);
    }

    // ---- CSS Shapes 1 §1.2: the float area of a LINE BOX ----
    //
    // `leftEdgeInBand` / `rightEdgeInBand` answer "how far does this float
    // intrude anywhere across a line box of this block extent", which is
    // what the spec shortens a line by — not the intrusion at the line's
    // top edge. The expected numbers below are hand-derived from the WPT
    // reference files named in each case, so they double as a check that
    // the maths agrees with the reftests rather than with itself.

    public function testBandEndingAtFloatTopDoesNotNarrowLine(): void
    {
        // Negative — a line box whose BOTTOM edge lands exactly on the
        // float's top edge does not overlap the float at all and keeps its
        // full measure. (Sampling the band's endpoints inclusively got this
        // wrong and narrowed the line immediately above every float.)
        $ctx = new FloatContext();
        $ctx->addLeft(0.0, 0.0, 50.0, 50.0);
        self::assertSame(0.0, $ctx->leftEdgeInBand(-20.0, 0.0, 0.0));
    }

    public function testBandStartingAtFloatBottomDoesNotNarrowLine(): void
    {
        // Negative — mirror of the above at the far edge: the float's
        // extent is the half-open [top, top + height).
        $ctx = new FloatContext();
        $ctx->addLeft(0.0, 0.0, 50.0, 50.0);
        self::assertSame(0.0, $ctx->leftEdgeInBand(50.0, 70.0, 0.0));
    }

    public function testEmptyShapeNeverNarrowsLine(): void
    {
        // Negative — CSS Shapes 1 §3.2/§3.3/§3.4: a degenerate basic shape
        // (`circle(0)`, `ellipse(0% 0%)`, a sub-triangular `polygon()`)
        // defines an EMPTY float area, so line boxes flow straight through
        // the float. Note the float is offset 20px into the container: a
        // regression that fell back to the bounding rect, or one that
        // pinned the line to the float's own left edge, would both show up
        // here as a non-zero result.
        $ctx = new FloatContext();
        $ctx->addLeft(20.0, 0.0, 80.0, 120.0, ['kind' => 'empty']);
        self::assertSame(0.0, $ctx->leftEdgeInBand(0.0, 24.0, 0.0));
        self::assertSame(0.0, $ctx->leftEdgeAt(60.0, 0.0));
    }

    public function testEmptyShapeNeverNarrowsLineForRightFloat(): void
    {
        // Negative — mirror for a right float.
        $ctx = new FloatContext();
        $ctx->addRight(120.0, 0.0, 80.0, 120.0, ['kind' => 'empty']);
        self::assertSame(200.0, $ctx->rightEdgeInBand(0.0, 24.0, 200.0));
        self::assertSame(200.0, $ctx->rightEdgeAt(60.0, 200.0));
    }

    public function testShapeWithNoAreaInBandDoesNotPinLineToFloatLeftEdge(): void
    {
        // Negative — the float's exclusion rect starts 20px into the
        // container but its polygon only occupies local y 20..100. A band
        // above that (local y 0..10) has NO float area, so the line keeps
        // the container's left edge. Reporting "the contour sits at x = 0"
        // instead of "there is no contour here" indented the line to the
        // float's own left edge — 20px of phantom exclusion.
        // From WPT shape-outside-polygon-018: the first `.longbox` line.
        $ctx = new FloatContext();
        $ctx->addLeft(20.0, 20.0, 120.0, 120.0, [
            'kind' => 'polygon',
            'vertices' => [[60.0, 20.0], [100.0, 60.0], [20.0, 60.0], [60.0, 100.0]],
        ], ['x' => 0.0, 'y' => 0.0, 'width' => 160.0, 'height' => 160.0]);
        self::assertSame(0.0, $ctx->leftEdgeInBand(0.0, 30.0, 0.0));
        // ... and likewise for a band below the polygon but still inside
        // the float's own rect (local y 110..120).
        self::assertSame(0.0, $ctx->leftEdgeInBand(130.0, 160.0, 0.0));
    }

    public function testSelfIntersectingPolygonSpikeDoesNotLeakIntoNextLine(): void
    {
        // Negative — the same bowtie polygon crosses itself at local y=60,
        // where its boundary momentarily spans the full width. That spike
        // belongs to the line ABOVE (which ends there); the line below must
        // see only the narrow lower lobe. Treating the band as a closed
        // interval let the spike widen both lines.
        $ctx = new FloatContext();
        $ctx->addLeft(20.0, 20.0, 120.0, 120.0, [
            'kind' => 'polygon',
            'vertices' => [[60.0, 20.0], [100.0, 60.0], [20.0, 60.0], [60.0, 100.0]],
        ], ['x' => 0.0, 'y' => 0.0, 'width' => 160.0, 'height' => 160.0]);
        self::assertEqualsWithDelta(120.0, $ctx->leftEdgeInBand(60.0, 80.0, 0.0), 0.01);
        self::assertEqualsWithDelta(80.0, $ctx->leftEdgeInBand(80.0, 100.0, 0.0), 0.01);
    }

    public function testCircleBandOutsideShapeLeavesLineFullWidth(): void
    {
        // Negative — a circle inscribed in the top half of a tall float
        // leaves the bottom half with no float area, even though the
        // float's own rect still covers it.
        $ctx = new FloatContext();
        $ctx->addLeft(0.0, 0.0, 100.0, 200.0, [
            'kind' => 'circle',
            'cx' => 50.0,
            'cy' => 50.0,
            'r' => 50.0,
        ]);
        self::assertSame(0.0, $ctx->leftEdgeInBand(120.0, 140.0, 0.0));
    }

    public function testZeroHeightBandMatchesLegacyPointQuery(): void
    {
        // Negative — a degenerate band must not drift from the single-point
        // semantics the abs-pos static-position callers still rely on.
        $ctx = new FloatContext();
        $ctx->addLeft(0.0, 0.0, 100.0, 100.0, [
            'kind' => 'circle',
            'cx' => 50.0,
            'cy' => 50.0,
            'r' => 50.0,
        ]);
        // At the float's top the circle has collapsed to its centre; at the
        // equator it spans the full diameter; at y=100 the float's own
        // half-open extent has ended, so there is no exclusion at all.
        foreach ([[0.0, 50.0], [50.0, 100.0], [99.0, 59.95], [100.0, 0.0]] as [$y, $want]) {
            self::assertEqualsWithDelta($want, $ctx->leftEdgeInBand($y, $y, 0.0), 0.01);
            self::assertEqualsWithDelta($want, $ctx->leftEdgeAt($y, 0.0), 0.01);
        }
    }

    // ---- Positive cases ----

    public function testCircleBandTakesWidestPointWhenBandStraddlesCentre(): void
    {
        // A circle r=50 centred at (50,50). A band from y=40 to y=60
        // straddles the equator, so the line is shortened by the FULL
        // diameter even though neither endpoint reaches it:
        // at y=40 and y=60 the contour is only at 50 + sqrt(50² - 10²)
        // = 98.99, but at y=50 it is 100.
        $ctx = new FloatContext();
        $ctx->addLeft(0.0, 0.0, 100.0, 100.0, [
            'kind' => 'circle',
            'cx' => 50.0,
            'cy' => 50.0,
            'r' => 50.0,
        ]);
        self::assertEqualsWithDelta(100.0, $ctx->leftEdgeInBand(40.0, 60.0, 0.0), 0.01);
        // Band entirely above the equator — extremum at its LOWER endpoint.
        self::assertEqualsWithDelta(98.99, $ctx->leftEdgeInBand(20.0, 40.0, 0.0), 0.01);
    }

    public function testEllipseBandReproducesReferenceLineOffsets(): void
    {
        // WPT shape-outside-ellipse-036: `ellipse()` on an 80x120 margin
        // box resolves to rx=40, ry=60 centred at (40,60). The reference
        // places its four boxes at left 72, 80, 80 and 72 for line bands
        // [0,24], [24,60], [60,96] and [96,120] — which is exactly the
        // contour's extremum over each band.
        $ctx = new FloatContext();
        $ctx->addLeft(0.0, 0.0, 80.0, 120.0, [
            'kind' => 'ellipse',
            'cx' => 40.0,
            'cy' => 60.0,
            'rx' => 40.0,
            'ry' => 60.0,
        ], ['x' => 0.0, 'y' => 0.0, 'width' => 80.0, 'height' => 120.0]);
        self::assertEqualsWithDelta(72.0, $ctx->leftEdgeInBand(0.0, 24.0, 0.0), 0.01);
        self::assertEqualsWithDelta(80.0, $ctx->leftEdgeInBand(24.0, 60.0, 0.0), 0.01);
        self::assertEqualsWithDelta(80.0, $ctx->leftEdgeInBand(60.0, 96.0, 0.0), 0.01);
        self::assertEqualsWithDelta(72.0, $ctx->leftEdgeInBand(96.0, 120.0, 0.0), 0.01);
    }

    public function testPolygonBandTakesExtremumAtVertexInsideBand(): void
    {
        // A diamond: widest at its middle vertex, which sits strictly
        // INSIDE the band. Sampling only the band's endpoints (x=50 at
        // both) would miss the 100 entirely.
        $ctx = new FloatContext();
        $ctx->addLeft(0.0, 0.0, 100.0, 100.0, [
            'kind' => 'polygon',
            'vertices' => [[50.0, 0.0], [100.0, 50.0], [50.0, 100.0], [0.0, 50.0]],
        ]);
        self::assertEqualsWithDelta(100.0, $ctx->leftEdgeInBand(20.0, 80.0, 0.0), 0.01);
    }

    public function testRightFloatBandNarrowsFromTheRight(): void
    {
        // Mirror of the circle case for a right float: a circle r=50
        // centred at (50,50) inside a 100x100 float whose left edge is at
        // x=100 pulls the line's end back to x=100 at the equator.
        $ctx = new FloatContext();
        $ctx->addRight(100.0, 0.0, 100.0, 100.0, [
            'kind' => 'circle',
            'cx' => 50.0,
            'cy' => 50.0,
            'r' => 50.0,
        ]);
        self::assertEqualsWithDelta(100.0, $ctx->rightEdgeInBand(40.0, 60.0, 200.0), 0.01);
        self::assertEqualsWithDelta(101.01, $ctx->rightEdgeInBand(20.0, 40.0, 200.0), 0.01);
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
