<?php

declare(strict_types=1);

namespace Phpdftk\Geometry\Tests;

use PHPUnit\Framework\TestCase;
use Phpdftk\Geometry\Matrix;
use Phpdftk\Geometry\Point;

class MatrixTest extends TestCase
{
    public function testIdentity(): void
    {
        $m = Matrix::identity();
        $this->assertSame(1.0, $m->a);
        $this->assertSame(0.0, $m->b);
        $this->assertSame(0.0, $m->c);
        $this->assertSame(1.0, $m->d);
        $this->assertSame(0.0, $m->e);
        $this->assertSame(0.0, $m->f);
    }

    public function testIdentityToArray(): void
    {
        $m = Matrix::identity();
        $this->assertSame([1.0, 0.0, 0.0, 1.0, 0.0, 0.0], $m->toArray());
    }

    public function testIdentityTransformPoint(): void
    {
        $m = Matrix::identity();
        $p = new Point(10.0, 20.0);
        $result = $m->transformPoint($p);
        $this->assertEqualsWithDelta(10.0, $result->x, 1e-10);
        $this->assertEqualsWithDelta(20.0, $result->y, 1e-10);
    }

    public function testTranslate(): void
    {
        $m = Matrix::identity()->translate(5.0, 10.0);
        $this->assertEqualsWithDelta(5.0, $m->e, 1e-10);
        $this->assertEqualsWithDelta(10.0, $m->f, 1e-10);
        $p = new Point(0.0, 0.0);
        $result = $m->transformPoint($p);
        $this->assertEqualsWithDelta(5.0, $result->x, 1e-10);
        $this->assertEqualsWithDelta(10.0, $result->y, 1e-10);
    }

    public function testTranslatePoint(): void
    {
        $m = Matrix::identity()->translate(100.0, 200.0);
        $p = new Point(50.0, 75.0);
        $result = $m->transformPoint($p);
        $this->assertEqualsWithDelta(150.0, $result->x, 1e-10);
        $this->assertEqualsWithDelta(275.0, $result->y, 1e-10);
    }

    public function testScale(): void
    {
        $m = Matrix::identity()->scale(2.0, 3.0);
        $this->assertEqualsWithDelta(2.0, $m->a, 1e-10);
        $this->assertEqualsWithDelta(3.0, $m->d, 1e-10);
        $p = new Point(5.0, 10.0);
        $result = $m->transformPoint($p);
        $this->assertEqualsWithDelta(10.0, $result->x, 1e-10);
        $this->assertEqualsWithDelta(30.0, $result->y, 1e-10);
    }

    public function testRotate90Degrees(): void
    {
        $m = Matrix::identity()->rotate(90.0);
        $p = new Point(1.0, 0.0);
        $result = $m->transformPoint($p);
        // Rotating (1,0) by 90 degrees gives approximately (0,1)
        $this->assertEqualsWithDelta(0.0, $result->x, 1e-10);
        $this->assertEqualsWithDelta(1.0, $result->y, 1e-10);
    }

    public function testRotate180Degrees(): void
    {
        $m = Matrix::identity()->rotate(180.0);
        $p = new Point(1.0, 0.0);
        $result = $m->transformPoint($p);
        $this->assertEqualsWithDelta(-1.0, $result->x, 1e-10);
        $this->assertEqualsWithDelta(0.0, $result->y, 1e-10);
    }

    public function testMultiply(): void
    {
        // translate then scale
        $translate = new Matrix(1, 0, 0, 1, 10, 20);
        $scale = new Matrix(2, 0, 0, 2, 0, 0);
        $combined = $translate->multiply($scale);
        $p = new Point(0.0, 0.0);
        $result = $combined->transformPoint($p);
        // (0+10)*2=20, (0+20)*2=40
        $this->assertEqualsWithDelta(20.0, $result->x, 1e-10);
        $this->assertEqualsWithDelta(40.0, $result->y, 1e-10);
    }

    public function testMultiplyIdentity(): void
    {
        $m = new Matrix(2, 3, 4, 5, 6, 7);
        $result = $m->multiply(Matrix::identity());
        $this->assertEqualsWithDelta($m->a, $result->a, 1e-10);
        $this->assertEqualsWithDelta($m->b, $result->b, 1e-10);
        $this->assertEqualsWithDelta($m->c, $result->c, 1e-10);
        $this->assertEqualsWithDelta($m->d, $result->d, 1e-10);
        $this->assertEqualsWithDelta($m->e, $result->e, 1e-10);
        $this->assertEqualsWithDelta($m->f, $result->f, 1e-10);
    }
}
