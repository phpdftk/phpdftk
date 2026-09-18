<?php

declare(strict_types=1);

namespace Phpdftk\HtmlToPdf\Tests\Box;

use Phpdftk\Css\Cascade\Cascade;
use Phpdftk\Css\Cascade\PropertyRegistry;
use Phpdftk\Css\Parser as CssParser;
use Phpdftk\Css\Value\Length;
use Phpdftk\Css\Value\Percentage;
use Phpdftk\HtmlToPdf\Box\Box;
use Phpdftk\HtmlToPdf\Box\BoxGenerator;
use Phpdftk\Html\Parser as HtmlParser;
use PHPUnit\Framework\TestCase;

/**
 * SVG 2 §8.2 — `width` / `height` on the outermost `<svg>` are
 * presentation attributes mapping onto the CSS `width` / `height`
 * properties, so `<svg width="100" height="60">` is a 100x60 CSS-px
 * replaced box rather than a zero-size one.
 */
final class SvgPresentationalSizeTest extends TestCase
{
    private BoxGenerator $generator;
    private CssParser $css;
    private HtmlParser $html;

    protected function setUp(): void
    {
        $this->css = new CssParser();
        $this->html = new HtmlParser();
        $this->generator = new BoxGenerator(new Cascade(PropertyRegistry::default()));
    }

    private function svgBox(string $body, string $extraCss = ''): ?Box
    {
        $sheet = $this->css->parseStylesheet(
            'html, body, p { display: block; } svg { display: inline-block; } ' . $extraCss,
        );
        $doc = $this->html->parseDocument('<html><body>' . $body . '</body></html>');
        return $this->find($this->generator->generate($doc, [$sheet]), 'svg');
    }

    private function find(Box $root, string $tag): ?Box
    {
        $stack = [$root];
        while ($stack !== []) {
            $node = array_shift($stack);
            if ($node->element !== null && $node->element->localName === $tag) {
                return $node;
            }
            foreach ($node->children as $c) {
                $stack[] = $c;
            }
        }
        return null;
    }

    public function testSvgWithoutDimensionAttributesGetsNoLength(): void
    {
        // The negative case: nothing to map, so the cascade must be left
        // alone rather than seeded with a made-up size.
        $svg = $this->svgBox('<svg></svg>');
        self::assertNotNull($svg);
        self::assertNotInstanceOf(Length::class, $svg->style->get('width'));
        self::assertNotInstanceOf(Length::class, $svg->style->get('height'));
    }

    public function testUnparseableDimensionAttributeIsIgnored(): void
    {
        $svg = $this->svgBox('<svg width="banana" height="12em"></svg>');
        self::assertNotNull($svg);
        self::assertNotInstanceOf(Length::class, $svg->style->get('width'));
        // `12em` is a valid CSS length but not an HTML dimension value;
        // mapping it as raw px would silently mis-size the box, so the
        // attribute is dropped rather than guessed at.
        self::assertNotInstanceOf(Length::class, $svg->style->get('height'));
    }

    public function testAuthorCssWinsOverTheAttribute(): void
    {
        $svg = $this->svgBox(
            '<svg width="300" height="200"></svg>',
            'svg { width: 40px; height: 25px; }',
        );
        self::assertNotNull($svg);
        $w = $svg->style->get('width');
        $h = $svg->style->get('height');
        self::assertInstanceOf(Length::class, $w);
        self::assertInstanceOf(Length::class, $h);
        self::assertSame(40.0, $w->value, 'author CSS beats the presentation attribute');
        self::assertSame(25.0, $h->value);
    }

    public function testOnlyTheMissingAxisIsFilledFromTheAttribute(): void
    {
        $svg = $this->svgBox(
            '<svg width="300" height="200"></svg>',
            'svg { width: 40px; }',
        );
        self::assertNotNull($svg);
        $w = $svg->style->get('width');
        $h = $svg->style->get('height');
        self::assertInstanceOf(Length::class, $w);
        self::assertInstanceOf(Length::class, $h);
        self::assertSame(40.0, $w->value);
        self::assertSame(200.0, $h->value);
    }

    public function testBareNumberAttributesMapToCssPixels(): void
    {
        $svg = $this->svgBox('<svg width="100" height="60"></svg>');
        self::assertNotNull($svg);
        $w = $svg->style->get('width');
        $h = $svg->style->get('height');
        self::assertInstanceOf(Length::class, $w);
        self::assertInstanceOf(Length::class, $h);
        // 1 CSS px is 1 PDF user unit throughout the engine, so no
        // px-to-point conversion happens here.
        self::assertSame(100.0, $w->value);
        self::assertSame(60.0, $h->value);
    }

    public function testPxSuffixedAttributesMapToTheSamePixels(): void
    {
        $svg = $this->svgBox('<svg width="100px" height="60px"></svg>');
        self::assertNotNull($svg);
        $w = $svg->style->get('width');
        self::assertInstanceOf(Length::class, $w);
        self::assertSame(100.0, $w->value);
    }

    public function testPercentageAttributesMapToCssPercentages(): void
    {
        $svg = $this->svgBox('<svg width="50%" height="25%"></svg>');
        self::assertNotNull($svg);
        $w = $svg->style->get('width');
        $h = $svg->style->get('height');
        self::assertInstanceOf(Percentage::class, $w);
        self::assertInstanceOf(Percentage::class, $h);
        self::assertSame(50.0, $w->value);
        self::assertSame(25.0, $h->value);
    }

    public function testBlockLevelSvgAlsoPicksUpTheAttributes(): void
    {
        $svg = $this->svgBox(
            '<svg width="300" height="150"></svg>',
            'svg { display: block; }',
        );
        self::assertNotNull($svg);
        $h = $svg->style->get('height');
        self::assertInstanceOf(Length::class, $h);
        self::assertSame(150.0, $h->value, 'a display:block svg is still a replaced box');
    }
}
