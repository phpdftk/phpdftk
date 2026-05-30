<?php

declare(strict_types=1);

namespace Phpdftk\Raster\Tests;

use Phpdftk\Raster\Exception\RasterException;
use Phpdftk\Raster\RasterSurface;
use Phpdftk\Raster\RgbaPixel;
use PHPUnit\Framework\TestCase;

/**
 * Phase 4C scaffold — RasterSurface is the substrate everything
 * downstream paints into. The pixel-access path is hot, so we
 * exercise both correctness (in / out of bounds, round-tripping)
 * and the byte-level buffer contract that filter primitives will
 * rely on.
 */
final class RasterSurfaceTest extends TestCase
{
    public function testConstructorRejectsZeroOrNegativeDimensions(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new RasterSurface(0, 100);
    }

    public function testConstructorRejectsNegativeWidth(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new RasterSurface(-1, 100);
    }

    public function testFreshSurfaceIsFullyTransparentBlack(): void
    {
        $surface = new RasterSurface(4, 4);
        for ($y = 0; $y < 4; $y++) {
            for ($x = 0; $x < 4; $x++) {
                $pixel = $surface->getPixel($x, $y);
                self::assertSame(0, $pixel->r);
                self::assertSame(0, $pixel->g);
                self::assertSame(0, $pixel->b);
                self::assertSame(0, $pixel->a);
            }
        }
    }

    public function testSetAndGetPixelRoundTrips(): void
    {
        $surface = new RasterSurface(10, 10);
        $surface->setPixel(3, 5, new RgbaPixel(200, 100, 50, 255));
        $pixel = $surface->getPixel(3, 5);
        self::assertSame(200, $pixel->r);
        self::assertSame(100, $pixel->g);
        self::assertSame(50, $pixel->b);
        self::assertSame(255, $pixel->a);
    }

    public function testSetPixelDoesNotAffectNeighbours(): void
    {
        $surface = new RasterSurface(3, 3);
        $surface->setPixel(1, 1, new RgbaPixel(255, 0, 0, 255));

        // All other pixels still transparent black.
        foreach ([[0, 0], [2, 0], [0, 2], [2, 2], [1, 0], [1, 2], [0, 1], [2, 1]] as [$x, $y]) {
            $pixel = $surface->getPixel($x, $y);
            self::assertSame(0, $pixel->r, "expected ($x, $y) to be transparent black");
            self::assertSame(0, $pixel->a);
        }
    }

    public function testGetPixelOutOfBoundsXThrows(): void
    {
        $surface = new RasterSurface(10, 10);
        $this->expectException(RasterException::class);
        $this->expectExceptionMessageMatches('/out of bounds/i');
        $surface->getPixel(10, 5);
    }

    public function testGetPixelOutOfBoundsYThrows(): void
    {
        $surface = new RasterSurface(10, 10);
        $this->expectException(RasterException::class);
        $surface->getPixel(5, 10);
    }

    public function testGetPixelNegativeXThrows(): void
    {
        $surface = new RasterSurface(10, 10);
        $this->expectException(RasterException::class);
        $surface->getPixel(-1, 5);
    }

    public function testSetPixelOutOfBoundsThrows(): void
    {
        $surface = new RasterSurface(10, 10);
        $this->expectException(RasterException::class);
        $surface->setPixel(10, 10, new RgbaPixel(0, 0, 0, 0));
    }

    public function testClearFillsEveryPixel(): void
    {
        $surface = new RasterSurface(5, 4);
        $surface->clear(new RgbaPixel(10, 20, 30, 40));
        for ($y = 0; $y < 4; $y++) {
            for ($x = 0; $x < 5; $x++) {
                $pixel = $surface->getPixel($x, $y);
                self::assertSame(10, $pixel->r);
                self::assertSame(20, $pixel->g);
                self::assertSame(30, $pixel->b);
                self::assertSame(40, $pixel->a);
            }
        }
    }

    public function testByteSizeMatchesRawBufferLength(): void
    {
        $surface = new RasterSurface(13, 7);
        self::assertSame(13 * 7 * 4, $surface->byteSize());
        self::assertSame($surface->byteSize(), strlen($surface->buffer()));
    }

    public function testBufferLayoutIsRowMajorRgba(): void
    {
        // Row-major: pixel (x, y) starts at offset (y * width + x) * 4
        // Order: R, G, B, A
        $surface = new RasterSurface(2, 2);
        $surface->setPixel(0, 0, new RgbaPixel(0xAA, 0xBB, 0xCC, 0xDD));
        $surface->setPixel(1, 0, new RgbaPixel(0x11, 0x22, 0x33, 0x44));
        $surface->setPixel(0, 1, new RgbaPixel(0x55, 0x66, 0x77, 0x88));

        $buffer = $surface->buffer();
        self::assertSame(0xAA, ord($buffer[0]));
        self::assertSame(0xBB, ord($buffer[1]));
        self::assertSame(0xCC, ord($buffer[2]));
        self::assertSame(0xDD, ord($buffer[3]));
        // Next pixel (1, 0):
        self::assertSame(0x11, ord($buffer[4]));
        self::assertSame(0x22, ord($buffer[5]));
        // Second row starts at offset 2 * 4 = 8:
        self::assertSame(0x55, ord($buffer[8]));
        self::assertSame(0x66, ord($buffer[9]));
    }

    public function testSetBufferReplacesWholeImage(): void
    {
        $surface = new RasterSurface(2, 1);
        // Two opaque red pixels.
        $replacement = "\xFF\x00\x00\xFF" . "\xFF\x00\x00\xFF";
        $surface->setBuffer($replacement);

        self::assertSame(0xFF, $surface->getPixel(0, 0)->r);
        self::assertSame(0xFF, $surface->getPixel(1, 0)->a);
    }

    public function testSetBufferRejectsWrongSize(): void
    {
        $surface = new RasterSurface(2, 2);
        $this->expectException(RasterException::class);
        $this->expectExceptionMessageMatches('/expected 16 bytes/');
        // Only 4 bytes instead of 16.
        $surface->setBuffer("\x00\x00\x00\x00");
    }
}
