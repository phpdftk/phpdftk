<?php

declare(strict_types=1);

namespace Phpdftk\Css\Tests;

use Phpdftk\Css\Value\Color;
use Phpdftk\Css\Value\ColorSpace;
use Phpdftk\Css\Value\CssFunction;
use Phpdftk\Css\Value\Keyword;
use Phpdftk\Css\Value\Number;
use Phpdftk\Css\Value\RelativeColor;
use Phpdftk\Css\ValueParser;
use PHPUnit\Framework\TestCase;

/**
 * CSS Color 5 §4 — relative color syntax. Component refs (r/g/b/
 * h/s/l/etc.) are stored as Keyword Value objects; the 4E color
 * engine resolves them to source-color components at evaluation
 * time.
 */
final class RelativeColorTest extends TestCase
{
    private ValueParser $parser;

    protected function setUp(): void
    {
        $this->parser = new ValueParser();
    }

    private function parseRelative(string $css): RelativeColor
    {
        $value = $this->parser->parseFromString($css);
        self::assertInstanceOf(RelativeColor::class, $value, "expected RelativeColor, got " . get_debug_type($value));
        return $value;
    }

    // -----------------------------------------------------------------------
    // Basic
    // -----------------------------------------------------------------------

    public function testRgbFromHexColor(): void
    {
        $r = $this->parseRelative('rgb(from #ff0000 r g b)');
        self::assertSame(ColorSpace::sRGB, $r->space);
        self::assertInstanceOf(Color::class, $r->source);
        self::assertSame(1.0, $r->source->r);
        self::assertInstanceOf(Keyword::class, $r->component1);
        self::assertSame('r', $r->component1->name);
        self::assertInstanceOf(Keyword::class, $r->component2);
        self::assertSame('g', $r->component2->name);
        self::assertInstanceOf(Keyword::class, $r->component3);
        self::assertSame('b', $r->component3->name);
    }

    public function testRgbFromNamedColor(): void
    {
        $r = $this->parseRelative('rgb(from red r g b)');
        self::assertSame(1.0, $r->source->r);
        self::assertSame(0.0, $r->source->g);
    }

    public function testRgbFromColorWithAlphaChannel(): void
    {
        $r = $this->parseRelative('rgb(from red r g b / 0.5)');
        self::assertInstanceOf(Number::class, $r->alpha);
        self::assertSame(0.5, $r->alpha->value);
    }

    public function testRgbWithReplacedComponent(): void
    {
        // Replace red component with 0; keep g, b.
        $r = $this->parseRelative('rgb(from red 0 g b)');
        // Bare integer literal may parse as Integer or Number per
        // CSS Values 4 §3.1; both are numeric.
        self::assertTrue(
            $r->component1 instanceof Number || $r->component1 instanceof \Phpdftk\Css\Value\Integer,
            sprintf('expected Number or Integer, got %s', get_debug_type($r->component1)),
        );
        self::assertInstanceOf(Keyword::class, $r->component2);
    }

    public function testRgbWithCalcComponent(): void
    {
        $r = $this->parseRelative('rgb(from red calc(r + 10) g b)');
        // calc(r + 10) parses as Calc; we just verify it's not a
        // bare ident.
        self::assertNotInstanceOf(Keyword::class, $r->component1);
    }

    public function testRgbWithNoneComponent(): void
    {
        $r = $this->parseRelative('rgb(from red none none none)');
        self::assertInstanceOf(Keyword::class, $r->component1);
        self::assertSame('none', $r->component1->name);
    }

    // -----------------------------------------------------------------------
    // hsl / hwb (polar in sRGB family)
    // -----------------------------------------------------------------------

    public function testHslFromSource(): void
    {
        $r = $this->parseRelative('hsl(from red h s l)');
        self::assertSame(ColorSpace::sRGB, $r->space);
        self::assertInstanceOf(Keyword::class, $r->component1);
        self::assertSame('h', $r->component1->name);
    }

    public function testHwbFromSource(): void
    {
        $r = $this->parseRelative('hwb(from red h w b)');
        self::assertSame(ColorSpace::sRGB, $r->space);
    }

    // -----------------------------------------------------------------------
    // lab / lch / oklab / oklch
    // -----------------------------------------------------------------------

    public function testLabFromSource(): void
    {
        $r = $this->parseRelative('lab(from red l a b)');
        self::assertSame(ColorSpace::Lab, $r->space);
    }

    public function testLchFromSource(): void
    {
        $r = $this->parseRelative('lch(from red l c h)');
        self::assertSame(ColorSpace::Lch, $r->space);
    }

    public function testOklabFromSource(): void
    {
        $r = $this->parseRelative('oklab(from red l a b)');
        self::assertSame(ColorSpace::OKLab, $r->space);
    }

    public function testOklchFromSource(): void
    {
        $r = $this->parseRelative('oklch(from red l c h)');
        self::assertSame(ColorSpace::OKLCH, $r->space);
    }

    // -----------------------------------------------------------------------
    // color(from <color> <space> ...)
    // -----------------------------------------------------------------------

    public function testColorFromWithSrgbSpace(): void
    {
        $r = $this->parseRelative('color(from red srgb r g b)');
        self::assertSame(ColorSpace::sRGB, $r->space);
        self::assertInstanceOf(Keyword::class, $r->component1);
    }

    public function testColorFromWithDisplayP3Space(): void
    {
        $r = $this->parseRelative('color(from red display-p3 r g b)');
        self::assertSame(ColorSpace::DisplayP3, $r->space);
    }

    public function testColorFromWithXyz(): void
    {
        $r = $this->parseRelative('color(from red xyz x y z)');
        self::assertSame(ColorSpace::XYZD65, $r->space);
    }

    public function testColorFromWithUnknownSpaceFallsThrough(): void
    {
        $value = $this->parser->parseFromString('color(from red fakespace r g b)');
        self::assertNotInstanceOf(RelativeColor::class, $value);
        self::assertInstanceOf(CssFunction::class, $value);
    }

    // -----------------------------------------------------------------------
    // Nested
    // -----------------------------------------------------------------------

    public function testNestedRelativeColorIsNotYetSupported(): void
    {
        // CSS Color 5 §4.5 allows nested `from`. We currently
        // require the source to resolve to a typed Color (not a
        // RelativeColor), so nested-from falls through. Documented
        // here as a known limitation that lifts with the 4E
        // engine.
        $value = $this->parser->parseFromString('rgb(from rgb(from red r g b) r g b)');
        self::assertNotInstanceOf(RelativeColor::class, $value);
    }

    // -----------------------------------------------------------------------
    // Malformed
    // -----------------------------------------------------------------------

    public function testMissingFromKeywordFallsThroughToRegularRgb(): void
    {
        // `rgb(red, 0.5)` — no `from`, parses as the regular rgb()
        // function (or falls through if invalid).
        $value = $this->parser->parseFromString('rgb(255 0 0)');
        self::assertNotInstanceOf(RelativeColor::class, $value);
        self::assertInstanceOf(Color::class, $value);
    }

    public function testFromWithoutSourceFallsThrough(): void
    {
        $value = $this->parser->parseFromString('rgb(from)');
        self::assertNotInstanceOf(RelativeColor::class, $value);
    }

    public function testFromWithTooFewComponentsFallsThrough(): void
    {
        $value = $this->parser->parseFromString('rgb(from red r g)');
        self::assertNotInstanceOf(RelativeColor::class, $value);
    }
}
