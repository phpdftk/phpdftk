<?php

declare(strict_types=1);

namespace Phpdftk\Raster\Tests;

use Phpdftk\Raster\RgbaPixel;
use PHPUnit\Framework\TestCase;

final class RgbaPixelTest extends TestCase
{
    public function testConstructorAcceptsValidRange(): void
    {
        $pixel = new RgbaPixel(0, 255, 128, 64);
        self::assertSame(0, $pixel->r);
        self::assertSame(255, $pixel->g);
        self::assertSame(128, $pixel->b);
        self::assertSame(64, $pixel->a);
    }

    public function testConstructorDefaultsAlphaToOpaque(): void
    {
        $pixel = new RgbaPixel(100, 100, 100);
        self::assertSame(255, $pixel->a);
    }

    public function testConstructorRejectsOutOfRangeR(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new RgbaPixel(256, 0, 0);
    }

    public function testConstructorRejectsNegative(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new RgbaPixel(-1, 0, 0);
    }

    public function testOfClampedSaturatesAboveRange(): void
    {
        // Filter arithmetic that produces 300 for a red channel
        // should clamp to 255 rather than throw.
        $pixel = RgbaPixel::ofClamped(300, -50, 128, 999);
        self::assertSame(255, $pixel->r);
        self::assertSame(0, $pixel->g);
        self::assertSame(128, $pixel->b);
        self::assertSame(255, $pixel->a);
    }

    public function testTransparentIsAllZeros(): void
    {
        $pixel = RgbaPixel::transparent();
        self::assertSame(0, $pixel->r);
        self::assertSame(0, $pixel->g);
        self::assertSame(0, $pixel->b);
        self::assertSame(0, $pixel->a);
    }
}
