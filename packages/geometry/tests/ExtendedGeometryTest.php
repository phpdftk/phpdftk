<?php declare(strict_types=1);

namespace Phpdftk\Geometry\Tests;

use PHPUnit\Framework\TestCase;
use Phpdftk\Geometry\BezierCurve;
use Phpdftk\Geometry\PageSize;
use Phpdftk\Geometry\Point;
use Phpdftk\Geometry\Rectangle;

class ExtendedGeometryTest extends TestCase
{
    // -----------------------------------------------------------------------
    // Point
    // -----------------------------------------------------------------------

    public function testPointConstruction(): void
    {
        $p = new Point(3.0, 4.0);
        self::assertSame(3.0, $p->x);
        self::assertSame(4.0, $p->y);
    }

    public function testPointZero(): void
    {
        $p = new Point(0.0, 0.0);
        self::assertSame(0.0, $p->x);
        self::assertSame(0.0, $p->y);
    }

    public function testPointNegative(): void
    {
        $p = new Point(-10.5, -20.25);
        self::assertSame(-10.5, $p->x);
        self::assertSame(-20.25, $p->y);
    }

    // -----------------------------------------------------------------------
    // PageSize
    // -----------------------------------------------------------------------

    public function testPageSizeLetter(): void
    {
        $r = PageSize::letter();
        self::assertSame(612.0, $r->width);
        self::assertSame(792.0, $r->height);
    }

    public function testPageSizeLegal(): void
    {
        $r = PageSize::legal();
        self::assertSame(612.0, $r->width);
        self::assertSame(1008.0, $r->height);
    }

    public function testPageSizeTabloid(): void
    {
        $r = PageSize::tabloid();
        self::assertSame(792.0, $r->width);
        self::assertSame(1224.0, $r->height);
    }

    public function testPageSizeA0(): void
    {
        $r = PageSize::a0();
        self::assertSame(2384.0, $r->width);
        self::assertSame(3370.0, $r->height);
    }

    public function testPageSizeA1(): void
    {
        $r = PageSize::a1();
        self::assertSame(1684.0, $r->width);
        self::assertSame(2384.0, $r->height);
    }

    public function testPageSizeA2(): void
    {
        $r = PageSize::a2();
        self::assertSame(1191.0, $r->width);
        self::assertSame(1684.0, $r->height);
    }

    public function testPageSizeA3(): void
    {
        $r = PageSize::a3();
        self::assertSame(842.0, $r->width);
        self::assertSame(1191.0, $r->height);
    }

    public function testPageSizeA4(): void
    {
        $r = PageSize::a4();
        self::assertSame(595.0, $r->width);
        self::assertSame(842.0, $r->height);
    }

    public function testPageSizeA5(): void
    {
        $r = PageSize::a5();
        self::assertSame(420.0, $r->width);
        self::assertSame(595.0, $r->height);
    }

    public function testPageSizeA6(): void
    {
        $r = PageSize::a6();
        self::assertSame(298.0, $r->width);
        self::assertSame(420.0, $r->height);
    }

    public function testPageSizeB4(): void
    {
        $r = PageSize::b4();
        self::assertSame(709.0, $r->width);
        self::assertSame(1001.0, $r->height);
    }

    public function testPageSizeB5(): void
    {
        $r = PageSize::b5();
        self::assertSame(499.0, $r->width);
        self::assertSame(709.0, $r->height);
    }

    public function testPageSizeLandscape(): void
    {
        $portrait = PageSize::a4();
        $landscape = PageSize::landscape($portrait);
        self::assertSame($portrait->height, $landscape->width);
        self::assertSame($portrait->width, $landscape->height);
    }

    public function testPageSizeLandscapeLetter(): void
    {
        $portrait = PageSize::letter();
        $landscape = PageSize::landscape($portrait);
        self::assertSame(792.0, $landscape->width);
        self::assertSame(612.0, $landscape->height);
    }

    public function testPageSizeOriginIsZero(): void
    {
        foreach ([PageSize::letter(), PageSize::a4(), PageSize::a3()] as $size) {
            self::assertSame(0.0, $size->x);
            self::assertSame(0.0, $size->y);
        }
    }

    // -----------------------------------------------------------------------
    // BezierCurve
    // -----------------------------------------------------------------------

    public function testBezierPointAtZeroIsP0(): void
    {
        $p0 = new Point(0, 0);
        $p1 = new Point(1, 3);
        $p2 = new Point(2, 3);
        $p3 = new Point(3, 0);
        $curve = new BezierCurve($p0, $p1, $p2, $p3);
        $pt = $curve->pointAt(0.0);
        self::assertEqualsWithDelta(0.0, $pt->x, 1e-10);
        self::assertEqualsWithDelta(0.0, $pt->y, 1e-10);
    }

    public function testBezierPointAtOneIsP3(): void
    {
        $p0 = new Point(0, 0);
        $p1 = new Point(1, 3);
        $p2 = new Point(2, 3);
        $p3 = new Point(3, 0);
        $curve = new BezierCurve($p0, $p1, $p2, $p3);
        $pt = $curve->pointAt(1.0);
        self::assertEqualsWithDelta(3.0, $pt->x, 1e-10);
        self::assertEqualsWithDelta(0.0, $pt->y, 1e-10);
    }

    public function testBezierPointAtMidpoint(): void
    {
        // Symmetric curve: midpoint should be at x=1.5
        $p0 = new Point(0, 0);
        $p1 = new Point(1, 2);
        $p2 = new Point(2, 2);
        $p3 = new Point(3, 0);
        $curve = new BezierCurve($p0, $p1, $p2, $p3);
        $pt = $curve->pointAt(0.5);
        self::assertEqualsWithDelta(1.5, $pt->x, 1e-10);
    }

    public function testBezierBoundsDimensions(): void
    {
        $p0 = new Point(0, 0);
        $p1 = new Point(1, 3);
        $p2 = new Point(2, 3);
        $p3 = new Point(3, 0);
        $curve = new BezierCurve($p0, $p1, $p2, $p3);
        $bounds = $curve->bounds();
        self::assertGreaterThanOrEqual(0.0, $bounds->x);
        self::assertGreaterThanOrEqual(0.0, $bounds->y);
        self::assertGreaterThan(0.0, $bounds->width);
        self::assertGreaterThan(0.0, $bounds->height);
    }

    public function testBezierBoundsContainsEndpoints(): void
    {
        $p0 = new Point(10, 20);
        $p1 = new Point(50, 100);
        $p2 = new Point(150, 100);
        $p3 = new Point(200, 20);
        $curve = new BezierCurve($p0, $p1, $p2, $p3);
        $bounds = $curve->bounds();
        // Both endpoints should be within the bounding box
        self::assertGreaterThanOrEqual($p0->x, $bounds->x);
        self::assertGreaterThanOrEqual($p0->y, $bounds->y);
        $boundRight = $bounds->x + $bounds->width;
        $boundTop = $bounds->y + $bounds->height;
        self::assertLessThanOrEqual($p3->x, $boundRight);
    }

    public function testBezierStraightLineIsRectangle(): void
    {
        // Straight horizontal line
        $p0 = new Point(0, 0);
        $p1 = new Point(1, 0);
        $p2 = new Point(2, 0);
        $p3 = new Point(3, 0);
        $curve = new BezierCurve($p0, $p1, $p2, $p3);
        $bounds = $curve->bounds();
        self::assertEqualsWithDelta(0.0, $bounds->y, 1e-6);
        self::assertEqualsWithDelta(0.0, $bounds->height, 1e-6);
        self::assertEqualsWithDelta(3.0, $bounds->width, 1e-6);
    }

    public function testBezierProperties(): void
    {
        $p0 = new Point(0, 0);
        $p1 = new Point(1, 2);
        $p2 = new Point(2, 2);
        $p3 = new Point(3, 0);
        $curve = new BezierCurve($p0, $p1, $p2, $p3);
        self::assertSame($p0, $curve->p0);
        self::assertSame($p1, $curve->p1);
        self::assertSame($p2, $curve->p2);
        self::assertSame($p3, $curve->p3);
    }
}
