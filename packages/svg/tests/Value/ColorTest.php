<?php

declare(strict_types=1);

namespace Phpdftk\Svg\Tests\Value;

use Phpdftk\Color\RgbColor;
use Phpdftk\Svg\Value\Color;
use PHPUnit\Framework\TestCase;

final class ColorTest extends TestCase
{
    public function testParsesSixDigitHex(): void
    {
        $c = Color::parse('#ff0000');
        self::assertInstanceOf(RgbColor::class, $c);
        self::assertSame(1.0, $c->r);
        self::assertSame(0.0, $c->g);
        self::assertSame(0.0, $c->b);
    }

    public function testParsesThreeDigitHexByDoublingEachDigit(): void
    {
        $c = Color::parse('#f0a');
        self::assertInstanceOf(RgbColor::class, $c);
        self::assertSame(1.0, $c->r);
        self::assertSame(0.0, $c->g);
        // #aa = 170/255
        self::assertEqualsWithDelta(170.0 / 255.0, $c->b, 1.0e-9);
    }

    public function testHexCaseInsensitive(): void
    {
        $c = Color::parse('#AbCdEf');
        self::assertInstanceOf(RgbColor::class, $c);
    }

    public function testParsesRgbNumericComponents(): void
    {
        $c = Color::parse('rgb(255, 128, 0)');
        self::assertInstanceOf(RgbColor::class, $c);
        self::assertSame(1.0, $c->r);
        self::assertEqualsWithDelta(128.0 / 255.0, $c->g, 1.0e-9);
        self::assertSame(0.0, $c->b);
    }

    public function testParsesRgbPercentageComponents(): void
    {
        $c = Color::parse('rgb(100%, 50%, 0%)');
        self::assertInstanceOf(RgbColor::class, $c);
        self::assertSame(1.0, $c->r);
        self::assertSame(0.5, $c->g);
        self::assertSame(0.0, $c->b);
    }

    public function testParsesRgbWhitespaceSeparated(): void
    {
        // SVG 2 / CSS 4 — `rgb(R G B)` without commas is allowed.
        $c = Color::parse('rgb(255 0 0)');
        self::assertInstanceOf(RgbColor::class, $c);
        self::assertSame(1.0, $c->r);
    }

    public function testRgbClampsOutOfRangeComponents(): void
    {
        // CSS Color 4 §15: out-of-gamut sRGB clamps for `rgb(...)`.
        $c = Color::parse('rgb(300, -50, 128)');
        self::assertInstanceOf(RgbColor::class, $c);
        self::assertSame(1.0, $c->r);
        self::assertSame(0.0, $c->g);
        self::assertEqualsWithDelta(128.0 / 255.0, $c->b, 1.0e-9);
    }

    public function testParsesNamedColorCaseInsensitively(): void
    {
        $c = Color::parse('Red');
        self::assertInstanceOf(RgbColor::class, $c);
        self::assertSame(1.0, $c->r);
        self::assertSame(0.0, $c->g);
        self::assertSame(0.0, $c->b);
    }

    public function testParsesRebeccaPurple(): void
    {
        // CSS Color 4 added `rebeccapurple` = #663399 in memory of
        // Rebecca Meyer. The full table coverage is exercised
        // implicitly; this one is just the canonical assertion that the
        // map is present.
        $c = Color::parse('rebeccapurple');
        self::assertInstanceOf(RgbColor::class, $c);
        self::assertEqualsWithDelta(0x66 / 255.0, $c->r, 1.0e-9);
        self::assertEqualsWithDelta(0x33 / 255.0, $c->g, 1.0e-9);
        self::assertEqualsWithDelta(0x99 / 255.0, $c->b, 1.0e-9);
    }

    public function testEmptyInputReturnsNull(): void
    {
        self::assertNull(Color::parse(''));
        self::assertNull(Color::parse('   '));
    }

    public function testUnknownNameReturnsNull(): void
    {
        self::assertNull(Color::parse('definitely-not-a-color'));
    }

    public function testMalformedHexReturnsNull(): void
    {
        self::assertNull(Color::parse('#ggg'));
        self::assertNull(Color::parse('#12345'));    // 5 digits isn't a valid hex length.
        self::assertNull(Color::parse('#1234567')); // too long
    }

    public function testMalformedRgbReturnsNull(): void
    {
        self::assertNull(Color::parse('rgb(1, 2)'));        // 2 components
        self::assertNull(Color::parse('rgb(1, 2, abc)'));   // non-numeric
    }

    public function testRgbaIsNotSupportedAtThisPhase(): void
    {
        // 3E ships sRGB only; rgba() lands with the cascade work in 3J.
        self::assertNull(Color::parse('rgba(255, 0, 0, 0.5)'));
    }
}
