<?php

declare(strict_types=1);

namespace Phpdftk\Css\Tests;

use Phpdftk\Css\Value\AnchorFunction;
use Phpdftk\Css\Value\AnchorSizeFunction;
use Phpdftk\Css\Value\CssFunction;
use Phpdftk\Css\Value\Keyword;
use Phpdftk\Css\Value\Length;
use Phpdftk\Css\Value\Percentage;
use Phpdftk\Css\ValueParser;
use PHPUnit\Framework\TestCase;

/**
 * CSS Anchor Positioning 1 §6 `anchor()` + §7 `anchor-size()`
 * parser. Storage layer for the declarative anchor reference;
 * actual layout resolution lands when the layout engine can
 * consult the anchor's rect.
 */
final class AnchorFunctionTest extends TestCase
{
    private ValueParser $parser;

    protected function setUp(): void
    {
        $this->parser = new ValueParser();
    }

    private function parseAnchor(string $css): AnchorFunction
    {
        $value = $this->parser->parseFromString($css);
        self::assertInstanceOf(AnchorFunction::class, $value, "expected AnchorFunction, got " . get_debug_type($value));
        return $value;
    }

    private function parseAnchorSize(string $css): AnchorSizeFunction
    {
        $value = $this->parser->parseFromString($css);
        self::assertInstanceOf(AnchorSizeFunction::class, $value, "expected AnchorSizeFunction, got " . get_debug_type($value));
        return $value;
    }

    // -----------------------------------------------------------------------
    // anchor()
    // -----------------------------------------------------------------------

    public function testAnchorWithNameAndSide(): void
    {
        $a = $this->parseAnchor('anchor(--my-anchor bottom)');
        self::assertSame('--my-anchor', $a->anchorName);
        self::assertInstanceOf(Keyword::class, $a->side);
        self::assertSame('bottom', $a->side->name);
        self::assertNull($a->fallback);
    }

    public function testAnchorWithImplicitName(): void
    {
        // `anchor(top)` — author omitted the anchor name; the
        // engine fills in via position-anchor.
        $a = $this->parseAnchor('anchor(top)');
        self::assertNull($a->anchorName);
        self::assertSame('top', $a->side->name);
    }

    public function testAnchorWithFallback(): void
    {
        $a = $this->parseAnchor('anchor(--my right, 50%)');
        self::assertSame('--my', $a->anchorName);
        self::assertSame('right', $a->side->name);
        self::assertInstanceOf(Percentage::class, $a->fallback);
        self::assertSame(50.0, $a->fallback->value);
    }

    public function testAnchorWithPercentageSide(): void
    {
        $a = $this->parseAnchor('anchor(--my 25%)');
        self::assertInstanceOf(Percentage::class, $a->side);
        self::assertSame(25.0, $a->side->value);
    }

    public function testAnchorWithLogicalKeyword(): void
    {
        $a = $this->parseAnchor('anchor(--my self-start)');
        self::assertSame('self-start', $a->side->name);
    }

    public function testAnchorUnknownKeywordFallsThrough(): void
    {
        $value = $this->parser->parseFromString('anchor(--my fancy)');
        self::assertNotInstanceOf(AnchorFunction::class, $value);
        self::assertInstanceOf(CssFunction::class, $value);
    }

    public function testAnchorWithFallbackLength(): void
    {
        $a = $this->parseAnchor('anchor(--my bottom, 10px)');
        self::assertInstanceOf(Length::class, $a->fallback);
        self::assertSame(10.0, $a->fallback->value);
    }

    public function testAnchorMissingSideFallsThrough(): void
    {
        $value = $this->parser->parseFromString('anchor(--my)');
        self::assertNotInstanceOf(AnchorFunction::class, $value);
    }

    // -----------------------------------------------------------------------
    // anchor-size()
    // -----------------------------------------------------------------------

    public function testAnchorSizeWithNameAndDimension(): void
    {
        $a = $this->parseAnchorSize('anchor-size(--card width)');
        self::assertSame('--card', $a->anchorName);
        self::assertSame('width', $a->dimension->name);
    }

    public function testAnchorSizeWithImplicitName(): void
    {
        $a = $this->parseAnchorSize('anchor-size(height)');
        self::assertNull($a->anchorName);
        self::assertSame('height', $a->dimension->name);
    }

    public function testAnchorSizeWithFallback(): void
    {
        $a = $this->parseAnchorSize('anchor-size(--card width, 200px)');
        self::assertInstanceOf(Length::class, $a->fallback);
        self::assertSame(200.0, $a->fallback->value);
    }

    public function testAnchorSizeLogicalDimensions(): void
    {
        $a = $this->parseAnchorSize('anchor-size(--card block)');
        self::assertSame('block', $a->dimension->name);

        $a = $this->parseAnchorSize('anchor-size(--card self-inline)');
        self::assertSame('self-inline', $a->dimension->name);
    }

    public function testAnchorSizeRejectsPercentageAsDimension(): void
    {
        // Only width / height / block / inline / self-block /
        // self-inline are valid; percentages aren't.
        $value = $this->parser->parseFromString('anchor-size(--card 50%)');
        self::assertNotInstanceOf(AnchorSizeFunction::class, $value);
    }

    // -----------------------------------------------------------------------
    // Round-trip
    // -----------------------------------------------------------------------

    public function testToCssRoundTripsExplicit(): void
    {
        $a = $this->parseAnchor('anchor(--my bottom)');
        self::assertSame('anchor(--my bottom)', $a->toCss());
    }

    public function testToCssRoundTripsImplicit(): void
    {
        $a = $this->parseAnchor('anchor(top)');
        self::assertSame('anchor(top)', $a->toCss());
    }

    public function testToCssRoundTripsWithFallback(): void
    {
        $a = $this->parseAnchor('anchor(--my right, 50%)');
        self::assertSame('anchor(--my right, 50%)', $a->toCss());
    }
}
