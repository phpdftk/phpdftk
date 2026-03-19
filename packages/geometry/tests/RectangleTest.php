<?php declare(strict_types=1);

namespace ApprLabs\Geometry\Tests;

use PHPUnit\Framework\TestCase;
use ApprLabs\Geometry\Rectangle;

class RectangleTest extends TestCase
{
    public function testToArray(): void
    {
        $r = new Rectangle(10, 20, 100, 200);
        $this->assertSame([10.0, 20.0, 110.0, 220.0], $r->toArray());
    }

    public function testContainsTrue(): void
    {
        $outer = new Rectangle(0, 0, 200, 200);
        $inner = new Rectangle(10, 10, 50, 50);
        $this->assertTrue($outer->contains($inner));
    }

    public function testContainsFalse(): void
    {
        $a = new Rectangle(0, 0, 100, 100);
        $b = new Rectangle(50, 50, 100, 100);
        $this->assertFalse($a->contains($b));
    }

    public function testContainsSelf(): void
    {
        $r = new Rectangle(0, 0, 100, 100);
        $this->assertTrue($r->contains($r));
    }

    public function testIntersectOverlapping(): void
    {
        $a = new Rectangle(0, 0, 100, 100);
        $b = new Rectangle(50, 50, 100, 100);
        $result = $a->intersect($b);
        $this->assertNotNull($result);
        $this->assertSame(50.0, $result->x);
        $this->assertSame(50.0, $result->y);
        $this->assertSame(50.0, $result->width);
        $this->assertSame(50.0, $result->height);
    }

    public function testIntersectNonOverlapping(): void
    {
        $a = new Rectangle(0, 0, 50, 50);
        $b = new Rectangle(100, 100, 50, 50);
        $this->assertNull($a->intersect($b));
    }

    public function testIntersectTouching(): void
    {
        $a = new Rectangle(0, 0, 50, 50);
        $b = new Rectangle(50, 0, 50, 50);
        // Touching (not overlapping) should return null
        $this->assertNull($a->intersect($b));
    }

    public function testUnion(): void
    {
        $a = new Rectangle(0, 0, 50, 50);
        $b = new Rectangle(50, 50, 50, 50);
        $result = $a->union($b);
        $this->assertSame(0.0, $result->x);
        $this->assertSame(0.0, $result->y);
        $this->assertSame(100.0, $result->width);
        $this->assertSame(100.0, $result->height);
    }

    public function testUnionWithOverlap(): void
    {
        $a = new Rectangle(10, 10, 80, 80);
        $b = new Rectangle(0, 0, 100, 100);
        $result = $a->union($b);
        $this->assertSame(0.0, $result->x);
        $this->assertSame(0.0, $result->y);
        $this->assertSame(100.0, $result->width);
        $this->assertSame(100.0, $result->height);
    }

    public function testScale(): void
    {
        $r = new Rectangle(10, 20, 100, 200);
        $scaled = $r->scale(2.0);
        $this->assertSame(20.0, $scaled->x);
        $this->assertSame(40.0, $scaled->y);
        $this->assertSame(200.0, $scaled->width);
        $this->assertSame(400.0, $scaled->height);
    }

    public function testScaleHalf(): void
    {
        $r = new Rectangle(0, 0, 200, 400);
        $scaled = $r->scale(0.5);
        $this->assertSame(0.0, $scaled->x);
        $this->assertSame(0.0, $scaled->y);
        $this->assertSame(100.0, $scaled->width);
        $this->assertSame(200.0, $scaled->height);
    }

    public function testExpand(): void
    {
        $r = new Rectangle(10, 10, 100, 100);
        $expanded = $r->expand(5.0);
        $this->assertSame(5.0, $expanded->x);
        $this->assertSame(5.0, $expanded->y);
        $this->assertSame(110.0, $expanded->width);
        $this->assertSame(110.0, $expanded->height);
    }

    public function testExpandNegativeMargin(): void
    {
        $r = new Rectangle(0, 0, 100, 100);
        $shrunk = $r->expand(-10.0);
        $this->assertSame(10.0, $shrunk->x);
        $this->assertSame(10.0, $shrunk->y);
        $this->assertSame(80.0, $shrunk->width);
        $this->assertSame(80.0, $shrunk->height);
    }
}
