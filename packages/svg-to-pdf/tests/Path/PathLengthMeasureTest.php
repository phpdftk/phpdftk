<?php

declare(strict_types=1);

namespace Phpdftk\SvgToPdf\Tests\Path;

use Phpdftk\Svg\Path\PathData;
use Phpdftk\SvgToPdf\Path\PathLengthMeasure;
use PHPUnit\Framework\TestCase;

/**
 * Geometric path length — the denominator of the SVG 2 §9.6
 * `pathLength` scaling factor.
 *
 * The measure has to agree between a shape and the equivalent
 * `<path>`, because WPT's `pathLength` reftests draw the test side as a
 * shape and the reference side as a hand-written path. Measuring the
 * Bézier APPROXIMATION of an arc instead of the true ellipse would put
 * the two sides a few parts in 10^5 apart, which the exact-pixel
 * criterion can still surface once the dash phase accumulates.
 */
final class PathLengthMeasureTest extends TestCase
{
    private const float DELTA = 1.0e-6;

    private static function measure(string $d): float
    {
        return PathLengthMeasure::ofPathData(PathData::parse($d));
    }

    public function testStraightLineIsItsChord(): void
    {
        self::assertEqualsWithDelta(100.0, self::measure('M0,0 L100,0'), self::DELTA);
    }

    public function testClosePathCountsTheClosingSegment(): void
    {
        // Open square: three sides = 300. `Z` adds the fourth.
        self::assertEqualsWithDelta(
            300.0,
            self::measure('M10,10 L110,10 L110,110 L10,110'),
            self::DELTA,
        );
        self::assertEqualsWithDelta(
            400.0,
            self::measure('M10,10 L110,10 L110,110 L10,110 Z'),
            self::DELTA,
        );
    }

    public function testMoveToStartsANewSubpathWithoutAddingLength(): void
    {
        self::assertEqualsWithDelta(
            20.0,
            self::measure('M0,0 L10,0 M50,50 L60,50'),
            self::DELTA,
        );
    }

    public function testClosePathReturnsToTheSubpathStartNotTheOrigin(): void
    {
        // Second subpath closes back to (50,50), not to (0,0).
        self::assertEqualsWithDelta(
            10.0 + 10.0 + 10.0,
            self::measure('M0,0 L10,0 M50,50 L60,50 Z'),
            self::DELTA,
        );
    }

    public function testRelativeAndShorthandCommandsAreResolved(): void
    {
        self::assertEqualsWithDelta(
            800.0,
            self::measure('m 20,140 200,0 0,200 -200,0 z'),
            self::DELTA,
        );
        self::assertEqualsWithDelta(
            30.0,
            self::measure('M0,0 h10 v10 h-10'),
            self::DELTA,
        );
    }

    public function testDegenerateCubicAlongALineMeasuresItsChord(): void
    {
        // Control points on the chord: the curve IS the segment.
        self::assertEqualsWithDelta(
            90.0,
            self::measure('M0,0 C30,0 60,0 90,0'),
            self::DELTA,
        );
    }

    public function testQuadraticIsMeasuredToo(): void
    {
        self::assertEqualsWithDelta(
            50.0,
            self::measure('M0,0 Q25,0 50,0'),
            self::DELTA,
        );
    }

    public function testCircleAsTwoHalfArcsIsTwoPiR(): void
    {
        // The reference side of svg/shapes/reftests/pathlength-002.
        self::assertEqualsWithDelta(
            2.0 * M_PI * 100.0,
            self::measure('m 220,240 a 100,100 0 0 1 -200,0 100,100 0 0 1 200,0 z'),
            1.0e-4,
        );
    }

    public function testQuarterCircleArcIsAQuarterCircumference(): void
    {
        self::assertEqualsWithDelta(
            M_PI * 10.0 / 2.0,
            self::measure('M10,0 A10,10 0 0 1 0,10'),
            1.0e-6,
        );
    }

    public function testZeroRadiusArcDegradesToAStraightLine(): void
    {
        // SVG 2 §9.5.1 — a zero radius renders as a line, so it must
        // measure as one too.
        self::assertEqualsWithDelta(
            100.0,
            self::measure('M0,0 A0,0 0 0 1 100,0'),
            self::DELTA,
        );
    }

    public function testEmptyPathHasZeroLength(): void
    {
        self::assertSame(0.0, self::measure(''));
        self::assertSame(0.0, self::measure('M10,10'));
    }
}
