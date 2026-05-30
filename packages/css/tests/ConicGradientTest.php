<?php

declare(strict_types=1);

namespace Phpdftk\Css\Tests;

use Phpdftk\Css\Value\ConicGradient;
use Phpdftk\Css\Value\CssFunction;
use Phpdftk\Css\ValueParser;
use PHPUnit\Framework\TestCase;

/**
 * CSS Backgrounds 4 / Images 4 §3.5 — conic-gradient() parsing.
 * Storage shape verified; angular stop positioning + the painter
 * land with the 4C raster compositor.
 */
final class ConicGradientTest extends TestCase
{
    private ValueParser $parser;

    protected function setUp(): void
    {
        $this->parser = new ValueParser();
    }

    private function parseConic(string $css): ConicGradient
    {
        $value = $this->parser->parseFromString($css);
        self::assertInstanceOf(ConicGradient::class, $value, "expected ConicGradient, got " . get_debug_type($value));
        return $value;
    }

    // -----------------------------------------------------------------------
    // Basic parsing
    // -----------------------------------------------------------------------

    public function testTwoColorStopsBasic(): void
    {
        $g = $this->parseConic('conic-gradient(red, blue)');
        self::assertSame(0.0, $g->fromAngleDeg);
        self::assertNull($g->centerX);
        self::assertNull($g->centerY);
        self::assertCount(2, $g->stops);
        self::assertFalse($g->repeating);
    }

    public function testRepeatingVariant(): void
    {
        $g = $this->parseConic('repeating-conic-gradient(red, blue)');
        self::assertTrue($g->repeating);
    }

    public function testThreeStops(): void
    {
        $g = $this->parseConic('conic-gradient(red, yellow, blue)');
        self::assertCount(3, $g->stops);
    }

    public function testSingleStopIsInvalid(): void
    {
        $value = $this->parser->parseFromString('conic-gradient(red)');
        self::assertInstanceOf(CssFunction::class, $value);
    }

    // -----------------------------------------------------------------------
    // `from <angle>` header
    // -----------------------------------------------------------------------

    public function testFromAngleDegrees(): void
    {
        $g = $this->parseConic('conic-gradient(from 90deg, red, blue)');
        self::assertSame(90.0, $g->fromAngleDeg);
    }

    public function testFromAngleTurn(): void
    {
        $g = $this->parseConic('conic-gradient(from 0.25turn, red, blue)');
        self::assertEqualsWithDelta(90.0, $g->fromAngleDeg, 1e-9);
    }

    public function testFromAngleRadians(): void
    {
        $g = $this->parseConic('conic-gradient(from 3.141592653589793rad, red, blue)');
        self::assertEqualsWithDelta(180.0, $g->fromAngleDeg, 1e-6);
    }

    public function testNegativeFromAngleNormalises(): void
    {
        $g = $this->parseConic('conic-gradient(from -90deg, red, blue)');
        self::assertSame(270.0, $g->fromAngleDeg);
    }

    public function testFromAngleAboveFullCircleWraps(): void
    {
        $g = $this->parseConic('conic-gradient(from 450deg, red, blue)');
        self::assertSame(90.0, $g->fromAngleDeg);
    }

    // -----------------------------------------------------------------------
    // `at <position>` header
    // -----------------------------------------------------------------------

    public function testAtPercentageCenter(): void
    {
        $g = $this->parseConic('conic-gradient(at 25% 75%, red, blue)');
        self::assertEqualsWithDelta(0.25, $g->centerX, 1e-9);
        self::assertEqualsWithDelta(0.75, $g->centerY, 1e-9);
    }

    public function testAtKeywordsResolveToPositions(): void
    {
        $g = $this->parseConic('conic-gradient(at left top, red, blue)');
        self::assertSame(0.0, $g->centerX);
        self::assertSame(0.0, $g->centerY);
    }

    public function testAtCenterIsHalfHalf(): void
    {
        $g = $this->parseConic('conic-gradient(at center, red, blue)');
        // Single position keyword applies to both axes.
        self::assertSame(0.5, $g->centerX);
        self::assertSame(0.5, $g->centerY);
    }

    public function testAtRightBottom(): void
    {
        $g = $this->parseConic('conic-gradient(at right bottom, red, blue)');
        self::assertSame(1.0, $g->centerX);
        self::assertSame(1.0, $g->centerY);
    }

    public function testFromAndAtTogether(): void
    {
        $g = $this->parseConic('conic-gradient(from 45deg at 30% 70%, red, yellow, blue)');
        self::assertSame(45.0, $g->fromAngleDeg);
        self::assertEqualsWithDelta(0.3, $g->centerX, 1e-9);
        self::assertEqualsWithDelta(0.7, $g->centerY, 1e-9);
    }

    // -----------------------------------------------------------------------
    // Round-trip via toCss()
    // -----------------------------------------------------------------------

    public function testToCssRoundTripsBareForm(): void
    {
        $g = $this->parseConic('conic-gradient(red, blue)');
        // bare form has no header.
        self::assertStringStartsWith('conic-gradient(', $g->toCss());
        self::assertStringEndsWith(')', $g->toCss());
    }

    public function testToCssIncludesFromAngle(): void
    {
        $g = $this->parseConic('conic-gradient(from 45deg, red, blue)');
        self::assertStringContainsString('from 45deg', $g->toCss());
    }

    public function testToCssIncludesAtPosition(): void
    {
        $g = $this->parseConic('conic-gradient(at 25% 75%, red, blue)');
        self::assertStringContainsString('at 25% 75%', $g->toCss());
    }

    public function testToCssOnRepeatingUsesRepeatingPrefix(): void
    {
        $g = $this->parseConic('repeating-conic-gradient(red, blue)');
        self::assertStringStartsWith('repeating-conic-gradient(', $g->toCss());
    }
}
