<?php

declare(strict_types=1);

namespace Phpdftk\MathmlToPdf\Tests;

use Phpdftk\MathmlToPdf\MathmlColor;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for the MathmlColor CSS color parser.
 */
final class MathmlColorTest extends TestCase
{
    public function testHexShorthand(): void
    {
        $color = MathmlColor::parse('#f00');
        self::assertNotNull($color);
        self::assertEqualsWithDelta(1.0, $color->r, 0.001);
        self::assertEqualsWithDelta(0.0, $color->g, 0.001);
        self::assertEqualsWithDelta(0.0, $color->b, 0.001);
    }

    public function testHexFull(): void
    {
        $color = MathmlColor::parse('#0080ff');
        self::assertNotNull($color);
        self::assertEqualsWithDelta(0.0, $color->r, 0.001);
        self::assertEqualsWithDelta(128 / 255.0, $color->g, 0.005);
        self::assertEqualsWithDelta(1.0, $color->b, 0.001);
    }

    public function testNamedColors(): void
    {
        foreach ([
            ['red', 1.0, 0.0, 0.0],
            ['black', 0.0, 0.0, 0.0],
            ['white', 1.0, 1.0, 1.0],
            ['blue', 0.0, 0.0, 1.0],
            ['salmon', 250 / 255.0, 128 / 255.0, 114 / 255.0],
        ] as [$name, $r, $g, $b]) {
            $color = MathmlColor::parse($name);
            self::assertNotNull($color, "$name should parse");
            self::assertEqualsWithDelta($r, $color->r, 0.005, "$name red channel");
            self::assertEqualsWithDelta($g, $color->g, 0.005, "$name green channel");
            self::assertEqualsWithDelta($b, $color->b, 0.005, "$name blue channel");
        }
    }

    public function testNamedColorCaseInsensitive(): void
    {
        $color = MathmlColor::parse('RED');
        self::assertNotNull($color);
        self::assertEqualsWithDelta(1.0, $color->r, 0.001);
    }

    public function testRgbFunctionInteger(): void
    {
        $color = MathmlColor::parse('rgb(255, 128, 0)');
        self::assertNotNull($color);
        self::assertEqualsWithDelta(1.0, $color->r, 0.001);
        self::assertEqualsWithDelta(128 / 255.0, $color->g, 0.005);
        self::assertEqualsWithDelta(0.0, $color->b, 0.001);
    }

    public function testRgbFunctionPercent(): void
    {
        $color = MathmlColor::parse('rgb(100%, 50%, 0%)');
        self::assertNotNull($color);
        self::assertEqualsWithDelta(1.0, $color->r, 0.001);
        self::assertEqualsWithDelta(0.5, $color->g, 0.005);
        self::assertEqualsWithDelta(0.0, $color->b, 0.001);
    }

    public function testInvalidInputsReturnNull(): void
    {
        self::assertNull(MathmlColor::parse(''));
        self::assertNull(MathmlColor::parse('banana'));
        self::assertNull(MathmlColor::parse('#xyz'));
        self::assertNull(MathmlColor::parse('#1234'));
        self::assertNull(MathmlColor::parse('rgb(256, 0, 0)'));
        self::assertNull(MathmlColor::parse('rgb(-1, 0, 0)'));
        self::assertNull(MathmlColor::parse('rgb(0, 0)'));
        self::assertNull(MathmlColor::parse('hsl(0, 100%, 50%)'));  // out of scope
    }

    public function testWhitespaceTolerance(): void
    {
        self::assertNotNull(MathmlColor::parse('  red  '));
        self::assertNotNull(MathmlColor::parse('rgb( 0 , 0 , 0 )'));
    }
}
