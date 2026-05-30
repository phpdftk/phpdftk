<?php

declare(strict_types=1);

namespace Phpdftk\Css\Tests;

use Phpdftk\Css\Value\Filter;
use Phpdftk\Css\Value\FilterFunction;
use Phpdftk\Css\Value\FilterKind;
use Phpdftk\Css\Value\Keyword;
use Phpdftk\Css\Value\Url;
use Phpdftk\Css\ValueParser;
use PHPUnit\Framework\TestCase;

/**
 * CSS Filter Effects 1 §5 — typed filter values. The painter
 * dispatches on FilterKind rather than re-parsing each
 * CssFunction's string name.
 */
final class FilterTest extends TestCase
{
    private ValueParser $parser;

    protected function setUp(): void
    {
        $this->parser = new ValueParser();
    }

    private function parseFilter(string $css): Filter
    {
        $value = $this->parser->parseFilter($css);
        self::assertInstanceOf(Filter::class, $value, "expected Filter, got " . get_debug_type($value));
        return $value;
    }

    public function testSingleBlur(): void
    {
        $f = $this->parseFilter('blur(5px)');
        self::assertCount(1, $f->functions);
        self::assertSame(FilterKind::Blur, $f->functions[0]->kind);
    }

    public function testMultipleFunctions(): void
    {
        $f = $this->parseFilter('blur(5px) brightness(1.2)');
        self::assertCount(2, $f->functions);
        self::assertSame(FilterKind::Blur, $f->functions[0]->kind);
        self::assertSame(FilterKind::Brightness, $f->functions[1]->kind);
    }

    public function testDropShadow(): void
    {
        $f = $this->parseFilter('drop-shadow(0 0 5px red)');
        self::assertCount(1, $f->functions);
        self::assertSame(FilterKind::DropShadow, $f->functions[0]->kind);
    }

    public function testAllStandardPrimitives(): void
    {
        $css = 'blur(5px) brightness(1.2) contrast(0.5) drop-shadow(0 0 5px red) '
            . 'grayscale(50%) hue-rotate(90deg) invert(100%) opacity(0.5) '
            . 'saturate(2) sepia(75%)';
        $f = $this->parseFilter($css);
        self::assertCount(10, $f->functions);

        $kinds = array_map(static fn(FilterFunction $fn) => $fn->kind, $f->functions);
        $expected = [
            FilterKind::Blur,
            FilterKind::Brightness,
            FilterKind::Contrast,
            FilterKind::DropShadow,
            FilterKind::Grayscale,
            FilterKind::HueRotate,
            FilterKind::Invert,
            FilterKind::Opacity,
            FilterKind::Saturate,
            FilterKind::Sepia,
        ];
        self::assertSame($expected, $kinds);
    }

    public function testUrlForSvgFilter(): void
    {
        // `filter: url(#blur-id)` — SVG filter chain reference.
        $f = $this->parseFilter('url(#blur-id)');
        self::assertCount(1, $f->functions);
        self::assertSame(FilterKind::Url, $f->functions[0]->kind);
        self::assertInstanceOf(Url::class, $f->functions[0]->args[0]);
    }

    public function testNoneKeywordPassesThrough(): void
    {
        // `filter: none` (initial) stays a Keyword — Filter([])
        // would be semantically the same but the cascade prefers
        // the bare keyword form.
        $value = $this->parser->parseFilter('none');
        self::assertInstanceOf(Keyword::class, $value);
    }

    public function testUnknownFunctionFallsThrough(): void
    {
        // `flurple(5px)` isn't a standard primitive — the post-
        // processor returns the original value unchanged so the
        // cascade can still carry the declaration.
        $value = $this->parser->parseFilter('flurple(5px)');
        self::assertNotInstanceOf(Filter::class, $value);
    }

    public function testMixedKnownAndUrlPrimitives(): void
    {
        $f = $this->parseFilter('blur(5px) url(#sepia) brightness(0.8)');
        self::assertCount(3, $f->functions);
        self::assertSame(FilterKind::Blur, $f->functions[0]->kind);
        self::assertSame(FilterKind::Url, $f->functions[1]->kind);
        self::assertSame(FilterKind::Brightness, $f->functions[2]->kind);
    }

    public function testToCssRoundTrip(): void
    {
        $f = $this->parseFilter('blur(5px) brightness(1.2)');
        // toCss serialises in order with single-space separation.
        $out = $f->toCss();
        self::assertStringContainsString('blur(', $out);
        self::assertStringContainsString('brightness(', $out);
    }
}
