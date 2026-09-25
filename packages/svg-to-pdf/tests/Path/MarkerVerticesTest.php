<?php

declare(strict_types=1);

namespace Phpdftk\SvgToPdf\Tests\Path;

use Phpdftk\Svg\Path\PathData;
use Phpdftk\SvgToPdf\Path\MarkerVertices;
use PHPUnit\Framework\TestCase;

/**
 * SVG 2 §11.6.2 marker vertices: where `marker-start` / `marker-mid` /
 * `marker-end` sit on a shape, and which way each one faces.
 *
 * The angles asserted here are the ones WPT's `marker-path-0**`
 * references hand-write as explicit `<use>` transforms, so they are
 * browser behaviour rather than a reading of the prose. The negative
 * cases are the ones that actually shipped wrong: a `moveto` bending a
 * marker toward the next subpath, an arc's internal Bezier lowering
 * sprouting phantom vertices, a zero-length `Z` erasing the tangent it
 * should have left alone, and a 180-degree reversal averaging to a
 * direction the path never travels.
 */
final class MarkerVerticesTest extends TestCase
{
    private const float DELTA = 1.0e-6;

    /** @return list<array{float, float, float}> x, y, angle */
    private static function angles(string $d): array
    {
        $out = [];
        foreach (MarkerVertices::ofPathData(PathData::parse($d)) as $vertex) {
            $out[] = [$vertex->x, $vertex->y, self::normalise($vertex->angle())];
        }
        return $out;
    }

    /** Rotations are mod 360; compare on a single turn. */
    private static function normalise(float $degrees): float
    {
        $wrapped = fmod($degrees, 360.0);
        return $wrapped < 0.0 ? $wrapped + 360.0 : $wrapped;
    }

    /**
     * @param list<array{float, float, float}> $expected
     * @param list<array{float, float, float}> $actual
     */
    private function assertVertices(array $expected, array $actual): void
    {
        self::assertCount(count($expected), $actual, 'vertex count');
        foreach ($expected as $i => [$x, $y, $angle]) {
            self::assertEqualsWithDelta($x, $actual[$i][0], self::DELTA, "vertex $i x");
            self::assertEqualsWithDelta($y, $actual[$i][1], self::DELTA, "vertex $i y");
            self::assertEqualsWithDelta($angle, $actual[$i][2], 1.0e-4, "vertex $i angle");
        }
    }

    // ---------------------------------------------------------------
    // Negative cases — each of these shipped wrong at least once.
    // ---------------------------------------------------------------

    /**
     * A `moveto` is not a segment. The vertex before the gap must keep
     * the direction it arrived on and the vertex after it the one it
     * leaves on; bisecting either against the jump vector rotated both
     * markers off the path (WPT marker-path-011's "step" row).
     */
    public function testMoveToContributesNoDirection(): void
    {
        $this->assertVertices(
            [
                [50.0, 280.0, 0.0],
                [100.0, 280.0, 0.0],
                [100.0, 330.0, 0.0],
                [150.0, 330.0, 0.0],
            ],
            self::angles('m 50,280 h 50 m 0,50 h 50'),
        );
    }

    /**
     * An `A` is ONE segment however many cubics the painter lowers it
     * to. Emitting the lowered pieces planted a spurious mid marker at
     * every quarter-arc boundary (WPT marker-path-003).
     */
    public function testArcIsOneSegmentNotItsBezierLowering(): void
    {
        $vertices = MarkerVertices::ofPathData(
            PathData::parse('m 50,120 a 30,20 90 0 0 50,0 a 30,20 90 0 1 50,0'),
        );
        self::assertCount(3, $vertices);
        // Half-ellipses joining end to end on a horizontal chord: the
        // tangent at every junction is vertical, alternating down/up.
        self::assertEqualsWithDelta(90.0, self::normalise($vertices[0]->angle()), 1.0e-4);
        self::assertEqualsWithDelta(270.0, self::normalise($vertices[1]->angle()), 1.0e-4);
        self::assertEqualsWithDelta(90.0, self::normalise($vertices[2]->angle()), 1.0e-4);
    }

    /**
     * A `Z` that closes onto the point the subpath already sits on
     * draws nothing, so it must not overwrite the tangent the last real
     * segment left. Reading the zero vector as a direction spun every
     * marker on a closed Bezier loop a quarter turn (WPT
     * marker-path-022).
     */
    public function testDegenerateClosePathKeepsTheLastRealTangent(): void
    {
        $vertices = self::angles(
            'm 120,100 c -40,0 -40,0 -40,40 c 0,40 0,40 40,40'
            . ' c 40,0 40,0 40,-40 c 0,-40 0,-40 -40,-40 z',
        );
        self::assertSame(120.0, $vertices[0][0]);
        self::assertSame(100.0, $vertices[0][1]);
        self::assertEqualsWithDelta(180.0, $vertices[0][2], 1.0e-4);
    }

    /**
     * A `Z` onto the point the subpath already ends on adds no vertex.
     * Emitting one put a mid marker on top of the end marker at the
     * closing corner of every curve that landed back on its start
     * (WPT marker-path-022 / -023).
     */
    public function testDegenerateClosePathAddsNoExtraVertex(): void
    {
        // Four cubics round a loop, the last one landing exactly on the
        // start point, then `z`. Start + 3 mids + end, not 6.
        $vertices = self::angles(
            'm 240,100 c -40,0 -40,0 -40,40 c 0,40 0,40 40,40'
            . ' c 40,0 40,0 40,-40 c 0,-40 0,-40 -40,-40 z',
        );
        self::assertCount(5, $vertices);
        self::assertSame([240.0, 100.0], [$vertices[0][0], $vertices[0][1]]);
        self::assertSame([240.0, 100.0], [$vertices[4][0], $vertices[4][1]]);
    }

    /** A `Z` that really does draw a segment still adds its vertex. */
    public function testNonDegenerateClosePathKeepsItsVertex(): void
    {
        $vertices = self::angles('m 120,100 -40,40 40,40 40,-40 z');
        self::assertCount(5, $vertices);
        self::assertSame([160.0, 140.0], [$vertices[3][0], $vertices[3][1]]);
    }

    /**
     * A path that doubles straight back on itself has tangents 180
     * degrees apart. Their arithmetic mean is a direction the path
     * never travels; the swept bisector is the one browsers draw (WPT
     * markers-orient-002).
     */
    public function testExactReversalFacesAlongTheSweptBisector(): void
    {
        $vertices = self::angles('M50,0 v50 z');
        self::assertEqualsWithDelta(180.0, $vertices[0][2], 1.0e-4);

        $vertices = self::angles('M100,50 h-50 z');
        self::assertEqualsWithDelta(270.0, $vertices[0][2], 1.0e-4);
    }

    /**
     * A shallow join either side of the ±180° cut must not flip the
     * marker end-for-end.
     */
    public function testShallowJoinAcrossTheAngleCutDoesNotFlip(): void
    {
        // In at ~+179°, out at ~-179°: the marker points left, not right.
        $vertices = self::angles('M 100,100 L 0,101 L -100,100');
        self::assertEqualsWithDelta(180.0, $vertices[1][2], 1.0);
    }

    /** A lone `moveto` has no direction at all and must not divide by it. */
    public function testLoneMoveToYieldsOneUnrotatedVertex(): void
    {
        $this->assertVertices([[10.0, 10.0, 0.0]], self::angles('M 10 10'));
    }

    public function testEmptyPathHasNoVertices(): void
    {
        self::assertSame([], MarkerVertices::ofPathData(PathData::parse('')));
    }

    // ---------------------------------------------------------------
    // Positive cases.
    // ---------------------------------------------------------------

    /** An open polyline: the ends take their single tangent. */
    public function testOpenPathEndsUseTheirOnlyTangent(): void
    {
        $this->assertVertices(
            [
                [50.0, 140.0, 21.801409],
                [100.0, 160.0, 0.0],
                [150.0, 140.0, 0.0],
                [200.0, 160.0, 21.801409],
            ],
            self::angles('m 50,140 50,20 50,-20 50,20'),
        );
    }

    /**
     * A closed subpath has no ends: its first and last vertex are the
     * same corner, so both face along the bisector of the closing
     * segment and the opening one.
     */
    public function testClosedSubpathStartAndEndShareTheCornerBisector(): void
    {
        $vertices = self::angles('m 120,100 -40,40 40,40 40,-40 z');
        self::assertCount(5, $vertices);
        // First and last are the same point, and the same corner.
        self::assertSame([120.0, 100.0], [$vertices[0][0], $vertices[0][1]]);
        self::assertSame([120.0, 100.0], [$vertices[4][0], $vertices[4][1]]);
        self::assertEqualsWithDelta(180.0, $vertices[0][2], 1.0e-4);
        self::assertEqualsWithDelta(180.0, $vertices[4][2], 1.0e-4);
        // The three mid corners of the diamond.
        self::assertEqualsWithDelta(90.0, $vertices[1][2], 1.0e-4);
        self::assertEqualsWithDelta(0.0, $vertices[2][2], 1.0e-4);
        self::assertEqualsWithDelta(270.0, $vertices[3][2], 1.0e-4);
    }

    /** A cubic's tangents come from its control polygon, not its chord. */
    public function testCurveTangentsComeFromTheControlPolygon(): void
    {
        $vertices = self::angles('M 0,0 C 0,100 100,100 100,0');
        self::assertEqualsWithDelta(90.0, $vertices[0][2], 1.0e-4);
        self::assertEqualsWithDelta(270.0, $vertices[1][2], 1.0e-4);
    }

    /** `<polyline>` points map onto the same walk. */
    public function testPointListShapesShareTheWalk(): void
    {
        $open = MarkerVertices::ofPoints([[0.0, 0.0], [10.0, 0.0], [10.0, 10.0]], closed: false);
        self::assertCount(3, $open);
        self::assertEqualsWithDelta(0.0, self::normalise($open[0]->angle()), 1.0e-4);
        self::assertEqualsWithDelta(45.0, self::normalise($open[1]->angle()), 1.0e-4);
        self::assertEqualsWithDelta(90.0, self::normalise($open[2]->angle()), 1.0e-4);
    }

    /**
     * A `<polygon>` closes implicitly (SVG 2 §10.7), which adds the
     * vertex at the closing corner and makes the opening one a corner
     * too.
     */
    public function testPolygonClosesImplicitly(): void
    {
        $closed = MarkerVertices::ofPoints([[0.0, 0.0], [10.0, 0.0], [10.0, 10.0]], closed: true);
        self::assertCount(4, $closed);
        self::assertSame(0.0, $closed[3]->x);
        self::assertSame(0.0, $closed[3]->y);
    }

    public function testEmptyPointListHasNoVertices(): void
    {
        self::assertSame([], MarkerVertices::ofPoints([], closed: true));
    }
}
