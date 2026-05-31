<?php

declare(strict_types=1);

namespace Phpdftk\Css\Tests;

use Phpdftk\Css\Value\Color;
use Phpdftk\Css\Value\CssFunction;
use Phpdftk\Css\Value\Keyword;
use Phpdftk\Css\Value\LightDark;
use Phpdftk\Css\ValueParser;
use PHPUnit\Framework\TestCase;

/**
 * CSS Color 5 §5 — `light-dark(<color>, <color>)` typed
 * parser. Both branches are preserved so the renderer can pick
 * at paint time based on the active color-scheme.
 */
final class LightDarkTest extends TestCase
{
    private ValueParser $parser;

    protected function setUp(): void
    {
        $this->parser = new ValueParser();
    }

    public function testKeywordColors(): void
    {
        $v = $this->parser->parseFromString('light-dark(black, white)');
        self::assertInstanceOf(LightDark::class, $v);
        self::assertInstanceOf(Color::class, $v->light);
        self::assertInstanceOf(Color::class, $v->dark);
    }

    public function testHexColors(): void
    {
        $v = $this->parser->parseFromString('light-dark(#fff, #111)');
        self::assertInstanceOf(LightDark::class, $v);
        self::assertInstanceOf(Color::class, $v->light);
        self::assertInstanceOf(Color::class, $v->dark);
    }

    public function testRgbFunctions(): void
    {
        $v = $this->parser->parseFromString('light-dark(rgb(255, 255, 255), rgb(0, 0, 0))');
        self::assertInstanceOf(LightDark::class, $v);
        self::assertInstanceOf(Color::class, $v->light);
        self::assertInstanceOf(Color::class, $v->dark);
    }

    public function testKeywordCurrentcolorIsKept(): void
    {
        $v = $this->parser->parseFromString('light-dark(currentcolor, white)');
        self::assertInstanceOf(LightDark::class, $v);
        self::assertInstanceOf(Keyword::class, $v->light);
        self::assertSame('currentcolor', $v->light->name);
    }

    public function testSingleArgRejected(): void
    {
        $v = $this->parser->parseFromString('light-dark(black)');
        self::assertNotInstanceOf(LightDark::class, $v);
        self::assertInstanceOf(CssFunction::class, $v);
    }

    public function testThreeArgsRejected(): void
    {
        $v = $this->parser->parseFromString('light-dark(black, white, red)');
        self::assertNotInstanceOf(LightDark::class, $v);
    }

    public function testRoundTripPreservesShape(): void
    {
        $v = $this->parser->parseFromString('light-dark(currentcolor, currentcolor)');
        self::assertInstanceOf(LightDark::class, $v);
        self::assertSame('light-dark(currentcolor, currentcolor)', $v->toCss());
    }
}
