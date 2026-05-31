<?php

declare(strict_types=1);

namespace Phpdftk\Css\Tests;

use Phpdftk\Css\Value\ColorSpace;
use Phpdftk\Css\Value\ConicGradient;
use Phpdftk\Css\Value\HueInterpolation;
use Phpdftk\Css\Value\LinearGradient;
use Phpdftk\Css\Value\RadialGradient;
use Phpdftk\Css\ValueParser;
use PHPUnit\Framework\TestCase;

/**
 * CSS Images 4 §3.1.2 — gradient interpolation method
 * (`in <colorspace> [<hue-interp> hue]`) plumbed through
 * linear-, radial-, and conic-gradient typed values.
 */
final class GradientInterpolationTest extends TestCase
{
    private ValueParser $parser;

    protected function setUp(): void
    {
        $this->parser = new ValueParser();
    }

    // -----------------------------------------------------------------
    // linear-gradient
    // -----------------------------------------------------------------

    public function testLinearInterpolationOnly(): void
    {
        $g = $this->parser->parseFromString('linear-gradient(in oklch, red, blue)');
        self::assertInstanceOf(LinearGradient::class, $g);
        self::assertSame(180.0, $g->angleDeg);
        self::assertSame(ColorSpace::OKLCH, $g->interpolationSpace);
        self::assertNull($g->hueInterpolation);
    }

    public function testLinearAngleAndInterpolation(): void
    {
        $g = $this->parser->parseFromString('linear-gradient(45deg in oklch, red, blue)');
        self::assertInstanceOf(LinearGradient::class, $g);
        self::assertSame(45.0, $g->angleDeg);
        self::assertSame(ColorSpace::OKLCH, $g->interpolationSpace);
    }

    public function testLinearToSideAndInterpolation(): void
    {
        $g = $this->parser->parseFromString('linear-gradient(to top in srgb-linear, red, blue)');
        self::assertInstanceOf(LinearGradient::class, $g);
        self::assertSame(0.0, $g->angleDeg);
        self::assertSame(ColorSpace::sRGBLinear, $g->interpolationSpace);
    }

    public function testLinearHueInterpolation(): void
    {
        $g = $this->parser->parseFromString('linear-gradient(in oklch longer hue, red, blue)');
        self::assertInstanceOf(LinearGradient::class, $g);
        self::assertSame(ColorSpace::OKLCH, $g->interpolationSpace);
        self::assertSame(HueInterpolation::Longer, $g->hueInterpolation);
    }

    public function testLinearNoInterpolationStillWorks(): void
    {
        $g = $this->parser->parseFromString('linear-gradient(45deg, red, blue)');
        self::assertInstanceOf(LinearGradient::class, $g);
        self::assertNull($g->interpolationSpace);
        self::assertNull($g->hueInterpolation);
    }

    public function testLinearRoundTrip(): void
    {
        $g = $this->parser->parseFromString('linear-gradient(45deg in oklch, red, blue)');
        self::assertInstanceOf(LinearGradient::class, $g);
        $out = $g->toCss();
        self::assertStringContainsString('in oklch', $out);
        self::assertStringStartsWith('linear-gradient(45deg in oklch,', $out);
    }

    // -----------------------------------------------------------------
    // radial-gradient
    // -----------------------------------------------------------------

    public function testRadialInterpolationOnly(): void
    {
        $g = $this->parser->parseFromString('radial-gradient(in oklch, red, blue)');
        self::assertInstanceOf(RadialGradient::class, $g);
        self::assertSame(ColorSpace::OKLCH, $g->interpolationSpace);
    }

    public function testRadialShapeAndInterpolation(): void
    {
        $g = $this->parser->parseFromString('radial-gradient(circle in srgb, red, blue)');
        self::assertInstanceOf(RadialGradient::class, $g);
        self::assertSame(ColorSpace::sRGB, $g->interpolationSpace);
    }

    // -----------------------------------------------------------------
    // conic-gradient
    // -----------------------------------------------------------------

    public function testConicInterpolationOnly(): void
    {
        $g = $this->parser->parseFromString('conic-gradient(in oklch, red, blue)');
        self::assertInstanceOf(ConicGradient::class, $g);
        self::assertSame(ColorSpace::OKLCH, $g->interpolationSpace);
    }

    public function testConicFromAndInterpolation(): void
    {
        $g = $this->parser->parseFromString('conic-gradient(from 45deg in oklch, red, blue)');
        self::assertInstanceOf(ConicGradient::class, $g);
        self::assertSame(45.0, $g->fromAngleDeg);
        self::assertSame(ColorSpace::OKLCH, $g->interpolationSpace);
    }

    public function testConicHueLongerInterpolation(): void
    {
        $g = $this->parser->parseFromString('conic-gradient(in hsl longer hue, red, blue)');
        self::assertInstanceOf(ConicGradient::class, $g);
        self::assertSame(HueInterpolation::Longer, $g->hueInterpolation);
    }

    public function testConicRoundTrip(): void
    {
        $g = $this->parser->parseFromString('conic-gradient(from 45deg in oklch, red, blue)');
        self::assertInstanceOf(ConicGradient::class, $g);
        $out = $g->toCss();
        self::assertStringContainsString('in oklch', $out);
        self::assertStringContainsString('from 45deg', $out);
    }
}
