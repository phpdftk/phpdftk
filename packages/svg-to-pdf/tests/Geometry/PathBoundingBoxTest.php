<?php

declare(strict_types=1);

namespace Phpdftk\SvgToPdf\Tests\Geometry;

use Phpdftk\Svg\Parser as SvgParser;
use Phpdftk\Svg\Path;
use Phpdftk\SvgToPdf\Geometry\BoundingBox;
use PHPUnit\Framework\TestCase;

/**
 * 3R+9 — `<path>` bbox via command walk + cubic / quadratic extrema.
 *
 * The tests probe both the endpoints (straightforward) and the curve
 * interior extrema (where the painter would otherwise miss the actual
 * geometric bounds and underestimate the bbox).
 */
final class PathBoundingBoxTest extends TestCase
{
    private const float DELTA = 1.0e-9;

    private SvgParser $svgParser;

    protected function setUp(): void
    {
        $this->svgParser = new SvgParser();
    }

    private function path(string $d): Path
    {
        $doc = $this->svgParser->parse(
            sprintf('<svg xmlns="http://www.w3.org/2000/svg"><path d="%s"/></svg>', $d),
        );
        $path = $doc->children[0];
        self::assertInstanceOf(Path::class, $path);
        return $path;
    }

    /**
     * @param array{minX: float, minY: float, width: float, height: float} $bbox
     */
    private static function assertBBoxEquals(
        array $bbox,
        float $minX,
        float $minY,
        float $width,
        float $height,
    ): void {
        self::assertEqualsWithDelta($minX, $bbox['minX'], self::DELTA, 'minX');
        self::assertEqualsWithDelta($minY, $bbox['minY'], self::DELTA, 'minY');
        self::assertEqualsWithDelta($width, $bbox['width'], self::DELTA, 'width');
        self::assertEqualsWithDelta($height, $bbox['height'], self::DELTA, 'height');
    }

    public function testEmptyPathReturnsNull(): void
    {
        self::assertNull(BoundingBox::compute($this->path('')));
    }

    public function testSimpleLineToBoundingBox(): void
    {
        $bbox = BoundingBox::compute($this->path('M 10 20 L 50 80'));
        self::assertNotNull($bbox);
        self::assertBBoxEquals($bbox, 10.0, 20.0, 40.0, 60.0);
    }

    public function testRelativeLineToIsResolvedAgainstCurrentPoint(): void
    {
        // M 10 20 l 30 40 → endpoint (40, 60). Bbox = (10, 20) - (40, 60).
        $bbox = BoundingBox::compute($this->path('M 10 20 l 30 40'));
        self::assertNotNull($bbox);
        self::assertBBoxEquals($bbox, 10.0, 20.0, 30.0, 40.0);
    }

    public function testHorizontalAndVerticalLineTo(): void
    {
        $bbox = BoundingBox::compute($this->path('M 0 0 H 50 V 100'));
        self::assertNotNull($bbox);
        self::assertBBoxEquals($bbox, 0.0, 0.0, 50.0, 100.0);
    }

    public function testClosePathReturnsToSubpathStart(): void
    {
        // Closing back to (0, 0) doesn't grow the bbox here, but it
        // does reset the current point for any subsequent commands.
        $bbox = BoundingBox::compute($this->path('M 10 20 L 50 80 Z'));
        self::assertNotNull($bbox);
        self::assertBBoxEquals($bbox, 10.0, 20.0, 40.0, 60.0);
    }

    public function testMultipleSubpathsBothContribute(): void
    {
        $bbox = BoundingBox::compute($this->path('M 0 0 L 10 10 Z M 100 100 L 200 200'));
        self::assertNotNull($bbox);
        self::assertBBoxEquals($bbox, 0.0, 0.0, 200.0, 200.0);
    }

    public function testCubicCurveInteriorExtremaIncluded(): void
    {
        // Cubic from (0, 0) via control (50, 100) and (50, 100) to
        // (100, 0). The interior y-extremum is reached at t = 0.5
        // where B(0.5) = 0.125·0 + 3·0.125·100 + 3·0.125·100 + 0.125·0 = 75.
        // Endpoints are at y = 0, so the cubic's bbox max y is 75 —
        // missing the extremum would underestimate by exactly that.
        $bbox = BoundingBox::compute($this->path('M 0 0 C 50 100 50 100 100 0'));
        self::assertNotNull($bbox);
        self::assertBBoxEquals($bbox, 0.0, 0.0, 100.0, 75.0);
    }

    public function testCubicWithSymmetricControlsHasMidpointExtremum(): void
    {
        // Classic dome — controls at (0, 200) and (100, 200), endpoints
        // at (0, 0) and (100, 0). The y-axis extremum is at the
        // midpoint t = 0.5: B(0.5) = 0.25·0 + 3·0.125·200 + 3·0.125·200
        //   + 0.125·0 = 150.
        $bbox = BoundingBox::compute($this->path('M 0 0 C 0 200 100 200 100 0'));
        self::assertNotNull($bbox);
        self::assertBBoxEquals($bbox, 0.0, 0.0, 100.0, 150.0);
    }

    public function testQuadraticCurveInteriorExtremumIncluded(): void
    {
        // Quadratic from (0, 0) via (50, 100) to (100, 0).
        // y-extremum at t = (0 - 100) / (0 - 2·100 + 0) = 0.5.
        // Q(0.5) = 0.25·0 + 2·0.5·0.5·100 + 0.25·0 = 50.
        $bbox = BoundingBox::compute($this->path('M 0 0 Q 50 100 100 0'));
        self::assertNotNull($bbox);
        self::assertBBoxEquals($bbox, 0.0, 0.0, 100.0, 50.0);
    }

    public function testSmoothCubicReflectsPreviousControl(): void
    {
        // After `C 10 0 0 10 10 10` the last cubic control is (0, 10).
        // `S 20 0 30 0` reflects about (10, 10) → first control = (20, 10).
        // The resulting cubic from (10, 10) via (20, 10) and (20, 0)
        // to (30, 0) stays within the box (0, 0) - (30, 10) — the
        // first segment already pushed the bbox out to x = 30 / y = 10
        // via the endpoints alone.
        $bbox = BoundingBox::compute($this->path('M 0 0 C 10 0 0 10 10 10 S 20 0 30 0'));
        self::assertNotNull($bbox);
        // Allow some slack — control-point reflection results land
        // exactly within (0, 0) - (30, 10).
        self::assertGreaterThanOrEqual(0.0, $bbox['minX']);
        self::assertGreaterThanOrEqual(0.0, $bbox['minY']);
        self::assertEqualsWithDelta(30.0, $bbox['minX'] + $bbox['width'], self::DELTA);
        self::assertEqualsWithDelta(10.0, $bbox['minY'] + $bbox['height'], self::DELTA);
    }

    public function testArcSweepGoesThroughIntermediateExtremum(): void
    {
        // Half circle from (100, 0) up over the top to (-100, 0) via a
        // 180° arc with radius 100. The interior y-max is 100 (the top
        // of the circle), achieved between the two endpoints.
        $bbox = BoundingBox::compute($this->path('M 100 0 A 100 100 0 1 1 -100 0'));
        self::assertNotNull($bbox);
        // The arc converter routes via 90°-clamped cubic segments;
        // each segment's extrema add up to the half-circle's true
        // bbox.
        self::assertEqualsWithDelta(-100.0, $bbox['minX'], 1.0e-6);
        self::assertEqualsWithDelta(0.0, $bbox['minY'], 1.0e-6);
        self::assertEqualsWithDelta(200.0, $bbox['width'], 1.0e-6);
        self::assertEqualsWithDelta(100.0, $bbox['height'], 1.0e-6);
    }

    public function testZeroLengthArcStillContributesEndpoint(): void
    {
        // M 5 5 A 25 25 0 0 0 5 5 — zero-length arc (start == end).
        // The bbox is just the starting point (degenerate).
        $bbox = BoundingBox::compute($this->path('M 5 5 A 25 25 0 0 0 5 5'));
        self::assertNotNull($bbox);
        self::assertBBoxEquals($bbox, 5.0, 5.0, 0.0, 0.0);
    }
}
