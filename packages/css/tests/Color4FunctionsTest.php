<?php

declare(strict_types=1);

namespace Phpdftk\Css\Tests;

use Phpdftk\Css\Value\Color;
use Phpdftk\Css\Value\ColorSpace;
use Phpdftk\Css\Value\CssFunction;
use Phpdftk\Css\ValueParser;
use PHPUnit\Framework\TestCase;

/**
 * CSS Color 4 §10 — `lab()`, `lch()`, `oklab()`, `oklch()` functional
 * notation parsing. Stored values map per the spec:
 *
 *   lab    L: 0-100; a, b: ±125
 *   lch    L: 0-100; C: 0-150; H: 0-360 deg
 *   oklab  L: 0-1;   a, b: ±0.4
 *   oklch  L: 0-1;   C: 0-0.4; H: 0-360 deg
 *
 * Color space tag identifies which axes the r/g/b slots represent.
 */
final class Color4FunctionsTest extends TestCase
{
    private ValueParser $parser;

    protected function setUp(): void
    {
        $this->parser = new ValueParser();
    }

    private function parseColor(string $css): Color
    {
        $value = $this->parser->parseFromString($css);
        self::assertInstanceOf(Color::class, $value, "expected Color, got " . get_debug_type($value));
        return $value;
    }

    // -----------------------------------------------------------------------
    // lab()
    // -----------------------------------------------------------------------

    public function testLabBasic(): void
    {
        $color = $this->parseColor('lab(60 20 -30)');
        self::assertSame(ColorSpace::Lab, $color->space);
        self::assertEqualsWithDelta(60.0, $color->r, 1e-9);
        self::assertEqualsWithDelta(20.0, $color->g, 1e-9);
        self::assertEqualsWithDelta(-30.0, $color->b, 1e-9);
        self::assertSame(1.0, $color->a);
    }

    public function testLabWithAlpha(): void
    {
        $color = $this->parseColor('lab(50 10 -15 / 0.5)');
        self::assertSame(ColorSpace::Lab, $color->space);
        self::assertSame(0.5, $color->a);
    }

    public function testLabLightnessPercentageMapsTo100(): void
    {
        // 75% lightness = 75.
        $color = $this->parseColor('lab(75% 30 -20)');
        self::assertEqualsWithDelta(75.0, $color->r, 1e-9);
    }

    public function testLabAxisPercentageMapsToPlusMinus125(): void
    {
        // 100% on the a-axis = +125; -50% on the b-axis = -62.5.
        $color = $this->parseColor('lab(50 100% -50%)');
        self::assertEqualsWithDelta(125.0, $color->g, 1e-9);
        self::assertEqualsWithDelta(-62.5, $color->b, 1e-9);
    }

    public function testLabNoneResolvesToZero(): void
    {
        $color = $this->parseColor('lab(none none none)');
        self::assertSame(0.0, $color->r);
        self::assertSame(0.0, $color->g);
        self::assertSame(0.0, $color->b);
    }

    // -----------------------------------------------------------------------
    // lch()
    // -----------------------------------------------------------------------

    public function testLchBasic(): void
    {
        $color = $this->parseColor('lch(60 80 240)');
        self::assertSame(ColorSpace::Lch, $color->space);
        self::assertEqualsWithDelta(60.0, $color->r, 1e-9);
        self::assertEqualsWithDelta(80.0, $color->g, 1e-9);
        self::assertEqualsWithDelta(240.0, $color->b, 1e-9);
    }

    public function testLchChromaPercentageMapsTo150(): void
    {
        $color = $this->parseColor('lch(50 100% 120)');
        self::assertEqualsWithDelta(150.0, $color->g, 1e-9);
    }

    public function testLchHueAcceptsDegUnit(): void
    {
        $color = $this->parseColor('lch(50 50 180deg)');
        self::assertEqualsWithDelta(180.0, $color->b, 1e-9);
    }

    public function testLchHueAcceptsTurn(): void
    {
        $color = $this->parseColor('lch(50 50 0.5turn)');
        self::assertEqualsWithDelta(180.0, $color->b, 1e-9);
    }

    public function testLchHueAcceptsRad(): void
    {
        // π rad = 180 deg
        $color = $this->parseColor('lch(50 50 3.141592653589793rad)');
        self::assertEqualsWithDelta(180.0, $color->b, 1e-6);
    }

    public function testLchHueWrapsNegative(): void
    {
        // -90 deg should normalise to 270
        $color = $this->parseColor('lch(50 50 -90)');
        self::assertEqualsWithDelta(270.0, $color->b, 1e-9);
    }

    public function testLchHueWrapsAboveCircle(): void
    {
        // 450 deg should normalise to 90
        $color = $this->parseColor('lch(50 50 450)');
        self::assertEqualsWithDelta(90.0, $color->b, 1e-9);
    }

    public function testLchNegativeChromaClampsToZero(): void
    {
        // Negative chroma is not meaningful — clamps to 0.
        $color = $this->parseColor('lch(50 -10 90)');
        self::assertSame(0.0, $color->g);
    }

    // -----------------------------------------------------------------------
    // oklab()
    // -----------------------------------------------------------------------

    public function testOklabBasic(): void
    {
        $color = $this->parseColor('oklab(0.6 0.1 -0.15)');
        self::assertSame(ColorSpace::OKLab, $color->space);
        self::assertEqualsWithDelta(0.6, $color->r, 1e-9);
        self::assertEqualsWithDelta(0.1, $color->g, 1e-9);
        self::assertEqualsWithDelta(-0.15, $color->b, 1e-9);
    }

    public function testOklabLightnessPercentageMapsToOne(): void
    {
        // 75% lightness in oklab = 0.75 (not 75).
        $color = $this->parseColor('oklab(75% 0.1 -0.05)');
        self::assertEqualsWithDelta(0.75, $color->r, 1e-9);
    }

    public function testOklabAxisPercentageMapsToPlusMinus04(): void
    {
        // 100% a = +0.4; -50% b = -0.2
        $color = $this->parseColor('oklab(0.5 100% -50%)');
        self::assertEqualsWithDelta(0.4, $color->g, 1e-9);
        self::assertEqualsWithDelta(-0.2, $color->b, 1e-9);
    }

    public function testOklabWithAlpha(): void
    {
        $color = $this->parseColor('oklab(0.5 0.1 -0.1 / 0.75)');
        self::assertEqualsWithDelta(0.75, $color->a, 1e-9);
    }

    // -----------------------------------------------------------------------
    // oklch()
    // -----------------------------------------------------------------------

    public function testOklchBasic(): void
    {
        $color = $this->parseColor('oklch(0.6 0.2 200)');
        self::assertSame(ColorSpace::OKLCH, $color->space);
        self::assertEqualsWithDelta(0.6, $color->r, 1e-9);
        self::assertEqualsWithDelta(0.2, $color->g, 1e-9);
        self::assertEqualsWithDelta(200.0, $color->b, 1e-9);
    }

    public function testOklchChromaPercentageMapsTo04(): void
    {
        $color = $this->parseColor('oklch(0.5 50% 90)');
        self::assertEqualsWithDelta(0.2, $color->g, 1e-9);  // 50% of 0.4
    }

    public function testOklchLightnessPercentageMapsToOne(): void
    {
        $color = $this->parseColor('oklch(50% 0.1 90)');
        self::assertEqualsWithDelta(0.5, $color->r, 1e-9);
    }

    public function testOklchWithAlpha(): void
    {
        $color = $this->parseColor('oklch(0.6 0.2 100 / 0.4)');
        self::assertEqualsWithDelta(0.4, $color->a, 1e-9);
    }

    // -----------------------------------------------------------------------
    // Invalid inputs fall through to generic CssFunction (graceful
    // degradation per CSS spec)
    // -----------------------------------------------------------------------

    public function testTooFewArgumentsFallsThrough(): void
    {
        $value = $this->parser->parseFromString('lab(50 20)');
        // Not parseable as Color → CssFunction fallback.
        self::assertNotInstanceOf(Color::class, $value);
        self::assertInstanceOf(CssFunction::class, $value);
    }

    public function testTooManyArgumentsFallsThrough(): void
    {
        $value = $this->parser->parseFromString('lab(50 10 20 30 40)');
        self::assertNotInstanceOf(Color::class, $value);
    }

    public function testInvalidComponentFallsThrough(): void
    {
        // String where a number was expected.
        $value = $this->parser->parseFromString('lab(50 "twenty" 30)');
        self::assertNotInstanceOf(Color::class, $value);
    }
}
