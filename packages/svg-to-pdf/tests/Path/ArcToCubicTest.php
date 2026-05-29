<?php

declare(strict_types=1);

namespace Phpdftk\SvgToPdf\Tests\Path;

use Phpdftk\SvgToPdf\Path\ArcToCubic;
use PHPUnit\Framework\TestCase;

/**
 * Unit-level tests for the SVG arc → cubic Bézier converter. The painter
 * exercises this end-to-end, but the math is fiddly enough to warrant
 * its own targeted coverage.
 */
final class ArcToCubicTest extends TestCase
{
    private const float DELTA = 1.0e-9;

    public function testQuarterArcEmitsOneSegmentLandingOnEndpoint(): void
    {
        // Quarter arc from (10, 0) to (0, 10) of a unit-scaled
        // (rx = ry = 10) circle centred on origin.
        $segments = ArcToCubic::convert(10.0, 0.0, 10.0, 10.0, 0.0, false, true, 0.0, 10.0);
        self::assertCount(1, $segments);
        self::assertEqualsWithDelta(0.0, $segments[0]['x'], self::DELTA);
        self::assertEqualsWithDelta(10.0, $segments[0]['y'], self::DELTA);
    }

    public function testHalfArcSplitsIntoTwoSegments(): void
    {
        // 180° arc from (10,0) to (-10,0). The painter caps each
        // segment at 90°, so this lands as two cubics.
        $segments = ArcToCubic::convert(10.0, 0.0, 10.0, 10.0, 0.0, true, true, -10.0, 0.0);
        self::assertCount(2, $segments);
        $last = $segments[count($segments) - 1];
        self::assertEqualsWithDelta(-10.0, $last['x'], self::DELTA);
        self::assertEqualsWithDelta(0.0, $last['y'], self::DELTA);
    }

    public function testZeroLengthArcReturnsEmpty(): void
    {
        $segments = ArcToCubic::convert(5.0, 5.0, 10.0, 10.0, 0.0, false, false, 5.0, 5.0);
        self::assertSame([], $segments);
    }

    public function testZeroRadiusReturnsEmpty(): void
    {
        self::assertSame(
            [],
            ArcToCubic::convert(0.0, 0.0, 0.0, 10.0, 0.0, false, true, 100.0, 0.0),
        );
        self::assertSame(
            [],
            ArcToCubic::convert(0.0, 0.0, 10.0, 0.0, 0.0, false, true, 100.0, 0.0),
        );
    }

    public function testRadiusCorrectionScalesUpWhenChordTooLong(): void
    {
        // Chord (0,0)→(100,0) won't fit on a circle of radius 10, so
        // the SVG impl note says: scale rx/ry up by λ until the
        // chord fits. The endpoint must still land at (100, 0).
        $segments = ArcToCubic::convert(0.0, 0.0, 10.0, 10.0, 0.0, false, true, 100.0, 0.0);
        self::assertNotSame([], $segments);
        $last = $segments[count($segments) - 1];
        self::assertEqualsWithDelta(100.0, $last['x'], self::DELTA);
        self::assertEqualsWithDelta(0.0, $last['y'], self::DELTA);
    }

    public function testNegativeRadiiAreNormalisedToAbsolute(): void
    {
        // The SVG impl note explicitly says "If either rx or ry is
        // negative, the absolute value is used instead." Same arc
        // either sign.
        $positive = ArcToCubic::convert(10.0, 0.0, 10.0, 10.0, 0.0, false, true, 0.0, 10.0);
        $negative = ArcToCubic::convert(10.0, 0.0, -10.0, -10.0, 0.0, false, true, 0.0, 10.0);
        self::assertCount(count($positive), $negative);
        foreach ($positive as $i => $p) {
            foreach (['x1', 'y1', 'x2', 'y2', 'x', 'y'] as $key) {
                self::assertEqualsWithDelta($p[$key], $negative[$i][$key], self::DELTA);
            }
        }
    }

    public function testSweepFlagFlipsArcDirection(): void
    {
        // Same endpoints, different sweep flag → mirrored midpoints.
        $sweep0 = ArcToCubic::convert(10.0, 0.0, 10.0, 10.0, 0.0, false, false, 0.0, 10.0);
        $sweep1 = ArcToCubic::convert(10.0, 0.0, 10.0, 10.0, 0.0, false, true, 0.0, 10.0);
        self::assertNotSame([], $sweep0);
        self::assertNotSame([], $sweep1);
        // Different intermediate control points — quick fingerprint.
        self::assertNotEquals($sweep0[0]['x1'], $sweep1[0]['x1']);
    }

    public function testRotatedArcEndpointStillLandsOnTarget(): void
    {
        // Quarter arc rotated 45°. Endpoint must still be the
        // command-stated (100, 100).
        $segments = ArcToCubic::convert(0.0, 0.0, 50.0, 50.0, 45.0, false, true, 100.0, 100.0);
        self::assertNotSame([], $segments);
        $last = $segments[count($segments) - 1];
        self::assertEqualsWithDelta(100.0, $last['x'], 1.0e-6);
        self::assertEqualsWithDelta(100.0, $last['y'], 1.0e-6);
    }
}
