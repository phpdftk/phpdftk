<?php

declare(strict_types=1);

namespace Phpdftk\Css\Tests;

use Phpdftk\Css\Value\Color;
use Phpdftk\Css\Value\CssFunction;
use Phpdftk\Css\Value\Integer;
use Phpdftk\Css\Value\Keyword;
use Phpdftk\Css\Value\Length;
use Phpdftk\Css\Value\LengthUnit;
use Phpdftk\Css\Value\ListSeparator;
use Phpdftk\Css\Value\Number;
use Phpdftk\Css\Value\Percentage;
use Phpdftk\Css\Value\StringValue;
use Phpdftk\Css\Value\Url;
use Phpdftk\Css\Value\ValueList;
use Phpdftk\Css\ValueParser;
use PHPUnit\Framework\TestCase;

final class ValueParserTest extends TestCase
{
    private ValueParser $parser;

    protected function setUp(): void
    {
        $this->parser = new ValueParser();
    }

    public function testIdentBecomesKeyword(): void
    {
        $v = $this->parser->parseFromString('auto');
        self::assertInstanceOf(Keyword::class, $v);
        self::assertSame('auto', $v->name);
    }

    public function testKeywordIsLowercased(): void
    {
        $v = $this->parser->parseFromString('AUTO');
        self::assertInstanceOf(Keyword::class, $v);
        self::assertSame('auto', $v->name);
    }

    public function testIntegerLiteral(): void
    {
        $v = $this->parser->parseFromString('42');
        self::assertInstanceOf(Integer::class, $v);
        self::assertSame(42, $v->value);
    }

    public function testNumberLiteral(): void
    {
        $v = $this->parser->parseFromString('1.5');
        self::assertInstanceOf(Number::class, $v);
        self::assertSame(1.5, $v->value);
    }

    public function testPercentage(): void
    {
        $v = $this->parser->parseFromString('50%');
        self::assertInstanceOf(Percentage::class, $v);
        self::assertSame(50.0, $v->value);
    }

    public function testLengthPx(): void
    {
        $v = $this->parser->parseFromString('16px');
        self::assertInstanceOf(Length::class, $v);
        self::assertSame(16.0, $v->value);
        self::assertSame(LengthUnit::Px, $v->unit);
    }

    public function testLengthEm(): void
    {
        $v = $this->parser->parseFromString('1.25em');
        self::assertInstanceOf(Length::class, $v);
        self::assertSame(1.25, $v->value);
        self::assertSame(LengthUnit::Em, $v->unit);
    }

    public function testLengthRem(): void
    {
        $v = $this->parser->parseFromString('2rem');
        self::assertInstanceOf(Length::class, $v);
        self::assertSame(LengthUnit::Rem, $v->unit);
    }

    public function testHexColor6(): void
    {
        $v = $this->parser->parseFromString('#FF0000');
        self::assertInstanceOf(Color::class, $v);
        self::assertEqualsWithDelta(1.0, $v->r, 0.001);
        self::assertEqualsWithDelta(0.0, $v->g, 0.001);
        self::assertEqualsWithDelta(0.0, $v->b, 0.001);
        self::assertSame(1.0, $v->a);
    }

    public function testHexColor3(): void
    {
        $v = $this->parser->parseFromString('#f0a');
        self::assertInstanceOf(Color::class, $v);
        // #f0a expands to #ff00aa
        self::assertEqualsWithDelta(1.0, $v->r, 0.001);
        self::assertEqualsWithDelta(0.0, $v->g, 0.001);
        self::assertEqualsWithDelta(170 / 255.0, $v->b, 0.001);
    }

    public function testHexColor8WithAlpha(): void
    {
        $v = $this->parser->parseFromString('#FF000080');
        self::assertInstanceOf(Color::class, $v);
        self::assertEqualsWithDelta(0x80 / 255.0, $v->a, 0.001);
    }

    public function testNamedColorRed(): void
    {
        $v = $this->parser->parseFromString('red');
        self::assertInstanceOf(Color::class, $v);
        self::assertEqualsWithDelta(1.0, $v->r, 0.001);
        self::assertEqualsWithDelta(0.0, $v->g, 0.001);
        self::assertEqualsWithDelta(0.0, $v->b, 0.001);
    }

    public function testNamedColorRebeccaPurple(): void
    {
        $v = $this->parser->parseFromString('rebeccapurple');
        self::assertInstanceOf(Color::class, $v);
        self::assertEqualsWithDelta(102 / 255.0, $v->r, 0.001);
        self::assertEqualsWithDelta(51 / 255.0, $v->g, 0.001);
        self::assertEqualsWithDelta(153 / 255.0, $v->b, 0.001);
    }

    public function testSystemColorsResolveToConcreteRgb(): void
    {
        // CSS Color 4 §9.2 — Canvas/CanvasText resolve at parse time
        // so the cascade can apply them like any other color.
        $canvas = $this->parser->parseFromString('Canvas');
        self::assertInstanceOf(Color::class, $canvas);
        self::assertSame(1.0, $canvas->r);
        self::assertSame(1.0, $canvas->g);
        self::assertSame(1.0, $canvas->b);

        $canvasText = $this->parser->parseFromString('CanvasText');
        self::assertInstanceOf(Color::class, $canvasText);
        self::assertSame(0.0, $canvasText->r);
        self::assertSame(0.0, $canvasText->g);
        self::assertSame(0.0, $canvasText->b);
    }

    public function testDeprecatedSystemColorsAliasModernEquivalents(): void
    {
        // CSS Color 4 §9.3 — deprecated system colors are required to
        // resolve to the SAME RGB as the modern color they alias.
        // WPT css-color/deprecated-sameas-* asserts this pairing in
        // pixel comparisons. Hard-code the spec's mapping table so
        // any drift surfaces at the unit-test layer.
        $aliases = [
            'ActiveCaption' => 'Canvas',
            'AppWorkspace' => 'Canvas',
            'Background' => 'Canvas',
            'CaptionText' => 'CanvasText',
            'InactiveCaption' => 'Canvas',
            'InfoBackground' => 'Canvas',
            'InfoText' => 'CanvasText',
            'Menu' => 'Canvas',
            'MenuText' => 'CanvasText',
            'Scrollbar' => 'Canvas',
            'ThreeDFace' => 'ButtonFace',
            'ThreeDHighlight' => 'ButtonBorder',
            'ThreeDLightShadow' => 'ButtonBorder',
            'ThreeDDarkShadow' => 'ButtonBorder',
            'ThreeDShadow' => 'ButtonBorder',
            'Window' => 'Canvas',
            'WindowFrame' => 'ButtonBorder',
            'WindowText' => 'CanvasText',
            'ButtonHighlight' => 'ButtonFace',
            'ButtonShadow' => 'ButtonFace',
            'InactiveBorder' => 'ButtonBorder',
            'InactiveCaptionText' => 'GrayText',
        ];
        foreach ($aliases as $deprecated => $modern) {
            $a = $this->parser->parseFromString($deprecated);
            $b = $this->parser->parseFromString($modern);
            self::assertInstanceOf(Color::class, $a, "{$deprecated} must parse as a color");
            self::assertInstanceOf(Color::class, $b, "{$modern} must parse as a color");
            self::assertSame($b->r, $a->r, "{$deprecated} red must match {$modern}");
            self::assertSame($b->g, $a->g, "{$deprecated} green must match {$modern}");
            self::assertSame($b->b, $a->b, "{$deprecated} blue must match {$modern}");
        }
    }

    public function testTransparentKeywordBecomesColor(): void
    {
        $v = $this->parser->parseFromString('transparent');
        self::assertInstanceOf(Color::class, $v);
        self::assertSame(0.0, $v->a);
    }

    public function testRgbLegacyCommaForm(): void
    {
        $v = $this->parser->parseFromString('rgb(255, 0, 128)');
        self::assertInstanceOf(Color::class, $v);
        self::assertEqualsWithDelta(1.0, $v->r, 0.001);
        self::assertEqualsWithDelta(0.0, $v->g, 0.001);
        self::assertEqualsWithDelta(128 / 255.0, $v->b, 0.001);
    }

    public function testRgbModernSpaceForm(): void
    {
        $v = $this->parser->parseFromString('rgb(255 0 128)');
        self::assertInstanceOf(Color::class, $v);
        self::assertEqualsWithDelta(1.0, $v->r, 0.001);
        self::assertEqualsWithDelta(128 / 255.0, $v->b, 0.001);
    }

    public function testRgbaWithAlpha(): void
    {
        $v = $this->parser->parseFromString('rgba(255, 0, 0, 0.5)');
        self::assertInstanceOf(Color::class, $v);
        self::assertEqualsWithDelta(0.5, $v->a, 0.001);
    }

    public function testRgbModernSlashAlpha(): void
    {
        $v = $this->parser->parseFromString('rgb(255 0 0 / 0.25)');
        self::assertInstanceOf(Color::class, $v);
        self::assertEqualsWithDelta(0.25, $v->a, 0.001);
    }

    public function testRgbPercentageComponents(): void
    {
        $v = $this->parser->parseFromString('rgb(100%, 0%, 50%)');
        self::assertInstanceOf(Color::class, $v);
        self::assertEqualsWithDelta(1.0, $v->r, 0.001);
        self::assertEqualsWithDelta(0.0, $v->g, 0.001);
        self::assertEqualsWithDelta(0.5, $v->b, 0.001);
    }

    public function testHslPureRed(): void
    {
        $v = $this->parser->parseFromString('hsl(0, 100%, 50%)');
        self::assertInstanceOf(Color::class, $v);
        self::assertEqualsWithDelta(1.0, $v->r, 0.01);
        self::assertEqualsWithDelta(0.0, $v->g, 0.01);
        self::assertEqualsWithDelta(0.0, $v->b, 0.01);
    }

    public function testHslPureBlue(): void
    {
        $v = $this->parser->parseFromString('hsl(240, 100%, 50%)');
        self::assertInstanceOf(Color::class, $v);
        self::assertEqualsWithDelta(0.0, $v->r, 0.01);
        self::assertEqualsWithDelta(0.0, $v->g, 0.01);
        self::assertEqualsWithDelta(1.0, $v->b, 0.01);
    }

    public function testHslWithDegUnit(): void
    {
        $v = $this->parser->parseFromString('hsl(120deg, 100%, 50%)');
        self::assertInstanceOf(Color::class, $v);
        // pure green
        self::assertEqualsWithDelta(0.0, $v->r, 0.01);
        self::assertEqualsWithDelta(1.0, $v->g, 0.01);
    }

    public function testHslaModernForm(): void
    {
        $v = $this->parser->parseFromString('hsl(0 100% 50% / 0.5)');
        self::assertInstanceOf(Color::class, $v);
        self::assertEqualsWithDelta(0.5, $v->a, 0.001);
    }

    public function testString(): void
    {
        $v = $this->parser->parseFromString('"hello"');
        self::assertInstanceOf(StringValue::class, $v);
        self::assertSame('hello', $v->value);
    }

    public function testUrlUnquoted(): void
    {
        $v = $this->parser->parseFromString('url(image.png)');
        self::assertInstanceOf(Url::class, $v);
        self::assertSame('image.png', $v->url);
    }

    public function testUrlQuoted(): void
    {
        $v = $this->parser->parseFromString('url("https://example.com/a.svg")');
        self::assertInstanceOf(Url::class, $v);
        self::assertSame('https://example.com/a.svg', $v->url);
    }

    public function testSpaceSeparatedList(): void
    {
        $v = $this->parser->parseFromString('10px 20px 30px 40px');
        self::assertInstanceOf(ValueList::class, $v);
        self::assertSame(ListSeparator::Space, $v->separator);
        self::assertCount(4, $v->values);
        self::assertInstanceOf(Length::class, $v->values[0]);
    }

    public function testCommaSeparatedList(): void
    {
        $v = $this->parser->parseFromString('Arial, "Helvetica Neue", sans-serif');
        self::assertInstanceOf(ValueList::class, $v);
        self::assertSame(ListSeparator::Comma, $v->separator);
        self::assertCount(3, $v->values);
    }

    public function testMixedListBackground(): void
    {
        // "1px solid red" is space-separated.
        $v = $this->parser->parseFromString('1px solid red');
        self::assertInstanceOf(ValueList::class, $v);
        self::assertCount(3, $v->values);
        self::assertInstanceOf(Length::class, $v->values[0]);
        self::assertInstanceOf(Keyword::class, $v->values[1]);
        self::assertInstanceOf(Color::class, $v->values[2]);
    }

    public function testUnknownFunctionFallsBackToCssFunction(): void
    {
        $v = $this->parser->parseFromString('rotate(45deg)');
        self::assertInstanceOf(CssFunction::class, $v);
        self::assertSame('rotate', $v->name);
        self::assertCount(1, $v->arguments);
    }

    public function testHexColorRoundTrip(): void
    {
        $v = $this->parser->parseFromString('#ff8000');
        self::assertSame('#ff8000', $v->toCss());
    }

    public function testNegativeLength(): void
    {
        $v = $this->parser->parseFromString('-1.5em');
        self::assertInstanceOf(Length::class, $v);
        self::assertSame(-1.5, $v->value);
    }
}
