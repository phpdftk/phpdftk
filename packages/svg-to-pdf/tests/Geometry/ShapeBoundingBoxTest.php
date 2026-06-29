<?php

declare(strict_types=1);

namespace Phpdftk\SvgToPdf\Tests\Geometry;

use Phpdftk\Svg\GenericElement;
use Phpdftk\Svg\Path;
use Phpdftk\Svg\Shape\Circle;
use Phpdftk\Svg\Shape\Ellipse;
use Phpdftk\Svg\Shape\Line;
use Phpdftk\Svg\Shape\Polygon;
use Phpdftk\Svg\Shape\Polyline;
use Phpdftk\Svg\Shape\Rect;
use Phpdftk\SvgToPdf\Geometry\BoundingBox;
use PHPUnit\Framework\TestCase;

/**
 * Covers the shape branches of {@see BoundingBox::compute} (rect / circle
 * / ellipse / line / polyline / polygon) — the path-command walker is
 * exercised separately by PathBoundingBoxTest. Failure modes (degenerate
 * / missing-dimension shapes → null) sit alongside the geometry anchors.
 */
final class ShapeBoundingBoxTest extends TestCase
{
    /** @param array{minX: float, minY: float, width: float, height: float}|null $bbox */
    private static function assertBbox(?array $bbox, float $minX, float $minY, float $w, float $h): void
    {
        self::assertNotNull($bbox);
        self::assertEqualsWithDelta($minX, $bbox['minX'], 1e-9, 'minX');
        self::assertEqualsWithDelta($minY, $bbox['minY'], 1e-9, 'minY');
        self::assertEqualsWithDelta($w, $bbox['width'], 1e-9, 'width');
        self::assertEqualsWithDelta($h, $bbox['height'], 1e-9, 'height');
    }

    private static function shape(string $class, array $attrs): object
    {
        $el = new $class();
        foreach ($attrs as $k => $v) {
            $el->setAttribute($k, (string) $v);
        }
        return $el;
    }

    // ---- Degenerate / missing dimensions → null --------------------------

    public function testZeroSizedRectIsNull(): void
    {
        self::assertNull(BoundingBox::compute(self::shape(Rect::class, ['width' => 0, 'height' => 10])));
        self::assertNull(BoundingBox::compute(self::shape(Rect::class, ['width' => 10, 'height' => 0])));
    }

    public function testNonPositiveCircleRadiusIsNull(): void
    {
        self::assertNull(BoundingBox::compute(self::shape(Circle::class, ['r' => 0])));
    }

    public function testEllipseWithNoRadiiIsNull(): void
    {
        // Neither rx nor ry → both resolve to null → no bbox.
        self::assertNull(BoundingBox::compute(new Ellipse()));
    }

    public function testEmptyPolylineAndPolygonAreNull(): void
    {
        self::assertNull(BoundingBox::compute(new Polyline()));
        self::assertNull(BoundingBox::compute(new Polygon()));
    }

    public function testNonShapeElementIsNull(): void
    {
        self::assertNull(BoundingBox::compute(new GenericElement('g')));
    }

    // ---- Shape geometry anchors ------------------------------------------

    public function testRect(): void
    {
        $bbox = BoundingBox::compute(self::shape(Rect::class, ['x' => 10, 'y' => 20, 'width' => 100, 'height' => 50]));
        self::assertBbox($bbox, 10.0, 20.0, 100.0, 50.0);
    }

    public function testCircle(): void
    {
        $bbox = BoundingBox::compute(self::shape(Circle::class, ['cx' => 50, 'cy' => 60, 'r' => 10]));
        self::assertBbox($bbox, 40.0, 50.0, 20.0, 20.0);
    }

    public function testEllipse(): void
    {
        $bbox = BoundingBox::compute(self::shape(Ellipse::class, ['cx' => 50, 'cy' => 60, 'rx' => 20, 'ry' => 10]));
        self::assertBbox($bbox, 30.0, 50.0, 40.0, 20.0);
    }

    public function testEllipseRyDefaultsToRx(): void
    {
        // SVG2 `auto`: ry absent → equals rx, giving a circle-shaped bbox.
        $bbox = BoundingBox::compute(self::shape(Ellipse::class, ['cx' => 50, 'cy' => 50, 'rx' => 20]));
        self::assertBbox($bbox, 30.0, 30.0, 40.0, 40.0);
    }

    public function testLineNormalisesEndpointOrder(): void
    {
        // y1 > y2 and x1 < x2: bbox must take the min/max of each axis.
        $bbox = BoundingBox::compute(self::shape(Line::class, ['x1' => 10, 'y1' => 80, 'x2' => 30, 'y2' => 5]));
        self::assertBbox($bbox, 10.0, 5.0, 20.0, 75.0);
    }

    public function testPolyline(): void
    {
        $bbox = BoundingBox::compute(self::shape(Polyline::class, ['points' => '0,0 10,30 20,5']));
        self::assertBbox($bbox, 0.0, 0.0, 20.0, 30.0);
    }

    public function testPolygon(): void
    {
        $bbox = BoundingBox::compute(self::shape(Polygon::class, ['points' => '5,5 25,5 15,25']));
        self::assertBbox($bbox, 5.0, 5.0, 20.0, 20.0);
    }

    // ---- Smooth-quadratic (T) path command -------------------------------

    public function testSmoothQuadraticPathContributesReflectedControl(): void
    {
        // Q peaks at y=10 (t=0.5); the T command reflects that control to
        // continue the curve out to (40, 0). The bbox must span both
        // endpoints and stay finite.
        $path = new Path();
        $path->setAttribute('d', 'M 0 0 Q 10 20 20 0 T 40 0');
        $bbox = BoundingBox::compute($path);
        self::assertNotNull($bbox);
        self::assertEqualsWithDelta(0.0, $bbox['minX'], 1e-9);
        self::assertEqualsWithDelta(40.0, $bbox['minX'] + $bbox['width'], 1e-9);
        // The first arch rises to +20-control (peak +10); some excursion
        // above the baseline must be captured.
        self::assertGreaterThan(0.0, $bbox['height']);
    }
}
