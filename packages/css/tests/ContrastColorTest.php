<?php

declare(strict_types=1);

namespace Phpdftk\Css\Tests;

use Phpdftk\Css\Cascade\CascadedValues;
use Phpdftk\Css\Cascade\ComputedStyle;
use Phpdftk\Css\Cascade\PropertyRegistry;
use Phpdftk\Css\Value\Color;
use Phpdftk\Css\Value\ContrastColor;
use Phpdftk\Css\ValueParser;
use PHPUnit\Framework\TestCase;

/**
 * CSS Color 7 §4 — `contrast-color()` typed parser and
 * computed-value resolver (black-vs-white pick per WCAG 2.x
 * relative luminance).
 */
final class ContrastColorTest extends TestCase
{
    private ValueParser $parser;

    protected function setUp(): void
    {
        $this->parser = new ValueParser();
    }

    public function testParsesContrastColorOfHex(): void
    {
        $v = $this->parser->parseFromString('contrast-color(#000)');
        self::assertInstanceOf(ContrastColor::class, $v);
        self::assertInstanceOf(Color::class, $v->base);
    }

    public function testParsesContrastColorOfRgb(): void
    {
        $v = $this->parser->parseFromString('contrast-color(rgb(255, 0, 0))');
        self::assertInstanceOf(ContrastColor::class, $v);
        self::assertInstanceOf(Color::class, $v->base);
        self::assertSame(1.0, $v->base->r);
    }

    public function testComputedValueResolvesDarkBaseToWhite(): void
    {
        $values = new CascadedValues(PropertyRegistry::default());
        $values->set('color', new ContrastColor(new Color(0.0, 0.0, 0.0, 1.0)));
        $style = new ComputedStyle($values);
        $c = $style->getColor();
        self::assertSame(1.0, $c->r);
        self::assertSame(1.0, $c->g);
        self::assertSame(1.0, $c->b);
    }

    public function testComputedValueResolvesLightBaseToBlack(): void
    {
        $values = new CascadedValues(PropertyRegistry::default());
        $values->set('color', new ContrastColor(new Color(1.0, 1.0, 1.0, 1.0)));
        $style = new ComputedStyle($values);
        $c = $style->getColor();
        self::assertSame(0.0, $c->r);
    }

    public function testComputedValueOnMidGrayPicksBlack(): void
    {
        $values = new CascadedValues(PropertyRegistry::default());
        // 50% gray sRGB → linear ~0.215, threshold is 0.5 → picks black.
        $values->set('color', new ContrastColor(new Color(0.5, 0.5, 0.5, 1.0)));
        $style = new ComputedStyle($values);
        $c = $style->getColor();
        self::assertSame(1.0, $c->r);
    }

    public function testRoundTrip(): void
    {
        $v = $this->parser->parseFromString('contrast-color(currentcolor)');
        self::assertInstanceOf(ContrastColor::class, $v);
        self::assertSame('contrast-color(currentcolor)', $v->toCss());
    }
}
