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
}
