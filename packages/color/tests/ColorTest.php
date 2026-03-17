<?php declare(strict_types=1);

namespace Phpdftk\Color\Tests;

use PHPUnit\Framework\TestCase;
use Phpdftk\Color\RgbColor;
use Phpdftk\Color\CmykColor;
use Phpdftk\Color\GrayColor;
use Phpdftk\Color\ColorConverter;

class ColorTest extends TestCase
{
    public function testRgbToArray(): void
    {
        $c = new RgbColor(0.5, 0.25, 0.75);
        $this->assertSame([0.5, 0.25, 0.75], $c->toArray());
        $this->assertSame('DeviceRGB', $c->getColorSpace());
    }

    public function testRgbOutOfRangeThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new RgbColor(1.5, 0.0, 0.0);
    }

    public function testRgbNegativeThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new RgbColor(0.0, -0.1, 0.0);
    }

    public function testCmykOutOfRangeThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new CmykColor(0.0, 0.0, 0.0, 1.5);
    }

    public function testGrayOutOfRangeThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new GrayColor(-0.1);
    }

    public function testGrayFactories(): void
    {
        $this->assertSame(0.0, GrayColor::black()->gray);
        $this->assertSame(1.0, GrayColor::white()->gray);
        $this->assertSame('DeviceGray', GrayColor::black()->getColorSpace());
    }

    public function testGrayToArray(): void
    {
        $g = new GrayColor(0.5);
        $this->assertSame([0.5], $g->toArray());
    }

    public function testCmykToArray(): void
    {
        $c = new CmykColor(0.1, 0.2, 0.3, 0.4);
        $this->assertSame([0.1, 0.2, 0.3, 0.4], $c->toArray());
        $this->assertSame('DeviceCMYK', $c->getColorSpace());
    }

    public function testFromInt(): void
    {
        $c = RgbColor::fromInt(255, 0, 0);
        $this->assertEqualsWithDelta(1.0, $c->r, 1e-10);
        $this->assertEqualsWithDelta(0.0, $c->g, 1e-10);
        $this->assertEqualsWithDelta(0.0, $c->b, 1e-10);
    }

    public function testFromIntMidRange(): void
    {
        $c = RgbColor::fromInt(128, 64, 192);
        $this->assertEqualsWithDelta(128 / 255.0, $c->r, 1e-6);
        $this->assertEqualsWithDelta(64 / 255.0, $c->g, 1e-6);
        $this->assertEqualsWithDelta(192 / 255.0, $c->b, 1e-6);
    }

    public function testFromHexWithHash(): void
    {
        $c = RgbColor::fromHex('#FF0000');
        $this->assertEqualsWithDelta(1.0, $c->r, 1e-10);
        $this->assertEqualsWithDelta(0.0, $c->g, 1e-10);
        $this->assertEqualsWithDelta(0.0, $c->b, 1e-10);
    }

    public function testFromHexWithoutHash(): void
    {
        $c = RgbColor::fromHex('00FF00');
        $this->assertEqualsWithDelta(0.0, $c->r, 1e-10);
        $this->assertEqualsWithDelta(1.0, $c->g, 1e-10);
        $this->assertEqualsWithDelta(0.0, $c->b, 1e-10);
    }

    public function testFromHexInvalid(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        RgbColor::fromHex('#FFF');
    }

    public function testRgbToCmyk(): void
    {
        // Pure red in RGB → CMYK
        $rgb = new RgbColor(1.0, 0.0, 0.0);
        $cmyk = $rgb->toCmyk();
        $this->assertEqualsWithDelta(0.0, $cmyk->c, 1e-10);
        $this->assertEqualsWithDelta(1.0, $cmyk->m, 1e-10);
        $this->assertEqualsWithDelta(1.0, $cmyk->y, 1e-10);
        $this->assertEqualsWithDelta(0.0, $cmyk->k, 1e-10);
    }

    public function testRgbToCmykBlack(): void
    {
        $rgb = new RgbColor(0.0, 0.0, 0.0);
        $cmyk = $rgb->toCmyk();
        $this->assertEqualsWithDelta(0.0, $cmyk->c, 1e-10);
        $this->assertEqualsWithDelta(0.0, $cmyk->m, 1e-10);
        $this->assertEqualsWithDelta(0.0, $cmyk->y, 1e-10);
        $this->assertEqualsWithDelta(1.0, $cmyk->k, 1e-10);
    }

    public function testRgbToCmykWhite(): void
    {
        $rgb = new RgbColor(1.0, 1.0, 1.0);
        $cmyk = $rgb->toCmyk();
        $this->assertEqualsWithDelta(0.0, $cmyk->c, 1e-10);
        $this->assertEqualsWithDelta(0.0, $cmyk->m, 1e-10);
        $this->assertEqualsWithDelta(0.0, $cmyk->y, 1e-10);
        $this->assertEqualsWithDelta(0.0, $cmyk->k, 1e-10);
    }

    public function testCmykToRgbRoundTrip(): void
    {
        $original = new RgbColor(0.6, 0.3, 0.8);
        $cmyk = ColorConverter::rgbToCmyk($original);
        $back = ColorConverter::cmykToRgb($cmyk);
        $this->assertEqualsWithDelta($original->r, $back->r, 1e-6);
        $this->assertEqualsWithDelta($original->g, $back->g, 1e-6);
        $this->assertEqualsWithDelta($original->b, $back->b, 1e-6);
    }

    public function testRgbToGray(): void
    {
        $rgb = new RgbColor(1.0, 1.0, 1.0);
        $gray = $rgb->toGray();
        $this->assertEqualsWithDelta(1.0, $gray->gray, 1e-6);
    }

    public function testRgbToGrayBlack(): void
    {
        $rgb = new RgbColor(0.0, 0.0, 0.0);
        $gray = $rgb->toGray();
        $this->assertEqualsWithDelta(0.0, $gray->gray, 1e-6);
    }

    public function testRgbToGrayLuminosity(): void
    {
        $rgb = new RgbColor(1.0, 0.0, 0.0);
        $gray = ColorConverter::rgbToGray($rgb);
        $this->assertEqualsWithDelta(0.299, $gray->gray, 1e-6);
    }

    public function testGrayToRgb(): void
    {
        $gray = new GrayColor(0.5);
        $rgb = $gray->toRgb();
        $this->assertEqualsWithDelta(0.5, $rgb->r, 1e-10);
        $this->assertEqualsWithDelta(0.5, $rgb->g, 1e-10);
        $this->assertEqualsWithDelta(0.5, $rgb->b, 1e-10);
    }

    public function testGrayToRgbRoundTrip(): void
    {
        $original = new GrayColor(0.75);
        $rgb = ColorConverter::grayToRgb($original);
        $back = ColorConverter::rgbToGray($rgb);
        $this->assertEqualsWithDelta($original->gray, $back->gray, 1e-6);
    }

    public function testCmykToRgbPureCyan(): void
    {
        $cmyk = new CmykColor(1.0, 0.0, 0.0, 0.0);
        $rgb = $cmyk->toRgb();
        $this->assertEqualsWithDelta(0.0, $rgb->r, 1e-10);
        $this->assertEqualsWithDelta(1.0, $rgb->g, 1e-10);
        $this->assertEqualsWithDelta(1.0, $rgb->b, 1e-10);
    }
}
