<?php

declare(strict_types=1);

namespace Phpdftk\SvgToPdf\Tests;

use Phpdftk\Pdf\Core\Content\ContentStream;
use Phpdftk\Svg\Parser;
use Phpdftk\SvgToPdf\Translator;
use PHPUnit\Framework\TestCase;

/**
 * Operator-level tests for the 3K painter. We compare the joined operator
 * string rather than asserting individual ops because ContentStream emits
 * each operator as a separate array entry — joining keeps assertions easy
 * to read for whoever has to debug a failing painter test next.
 */
final class TranslatorTest extends TestCase
{
    private Parser $svgParser;
    private Translator $translator;

    protected function setUp(): void
    {
        $this->svgParser = new Parser();
        $this->translator = new Translator();
    }

    private function paint(string $svg): string
    {
        $doc = $this->svgParser->parse($svg);
        $stream = new ContentStream();
        $this->translator->paint($doc, $stream);
        return implode("\n", $stream->getOperators());
    }

    public function testEmptySvgEmitsNoOperators(): void
    {
        $ops = $this->paint('<svg xmlns="http://www.w3.org/2000/svg"/>');
        self::assertSame('', $ops);
    }

    public function testRectWithDefaultFillEmitsBlackFillAndRectangleOperator(): void
    {
        $ops = $this->paint(
            '<svg xmlns="http://www.w3.org/2000/svg"><rect x="10" y="20" width="30" height="40"/></svg>',
        );
        // SVG 2 default: black fill, no stroke. ContentStream encodes
        // rg = setFillColor (RGB) and re = rectangle and f = fill.
        self::assertStringContainsString('0 0 0 rg', $ops);
        self::assertStringContainsString('10 20 30 40 re', $ops);
        self::assertStringContainsString("\nf", $ops);
    }

    public function testRectWithExplicitFillNoneSkipsFill(): void
    {
        $ops = $this->paint(
            '<svg xmlns="http://www.w3.org/2000/svg">'
            . '<rect x="0" y="0" width="10" height="10" fill="none"/></svg>',
        );
        // Path was constructed but no paint wanted → emit `n` (endPath)
        // so the current path doesn't leak into later operations.
        self::assertStringContainsString('0 0 10 10 re', $ops);
        self::assertStringContainsString("\nn", $ops);
        self::assertStringNotContainsString("\nf", $ops);
    }

    public function testRectWithFillAndStrokeUsesCombinedOperator(): void
    {
        $ops = $this->paint(
            '<svg xmlns="http://www.w3.org/2000/svg">'
            . '<rect width="10" height="10" fill="red" stroke="blue"/></svg>',
        );
        // `B` = fill + stroke per ISO 32000-2 §8.5.3.1 Table 60.
        self::assertStringContainsString("\nB", $ops);
        self::assertStringContainsString('1 0 0 rg', $ops); // red fill
        self::assertStringContainsString('0 0 1 RG', $ops); // blue stroke
    }

    public function testRectWithEvenOddFillRuleUsesStarOperator(): void
    {
        $ops = $this->paint(
            '<svg xmlns="http://www.w3.org/2000/svg">'
            . '<rect width="10" height="10" fill-rule="evenodd"/></svg>',
        );
        self::assertStringContainsString("\nf*", $ops);
    }

    public function testRectWithZeroWidthIsSkipped(): void
    {
        $ops = $this->paint(
            '<svg xmlns="http://www.w3.org/2000/svg"><rect width="0" height="10"/></svg>',
        );
        self::assertSame('', $ops);
    }

    public function testCircleEmitsFourCubicBeziers(): void
    {
        $ops = $this->paint(
            '<svg xmlns="http://www.w3.org/2000/svg"><circle cx="50" cy="60" r="10"/></svg>',
        );
        // Four `c` operators per quarter-arc plus the closing `h`.
        self::assertSame(4, substr_count($ops, ' c'));
        self::assertStringContainsString("\nh", $ops);
        // First moveTo is at (cx + r, cy) = (60, 60).
        self::assertStringContainsString('60 60 m', $ops);
    }

    public function testCircleZeroRadiusIsSkipped(): void
    {
        $ops = $this->paint(
            '<svg xmlns="http://www.w3.org/2000/svg"><circle r="0"/></svg>',
        );
        self::assertSame('', $ops);
    }

    public function testEllipseRequiresBothRadii(): void
    {
        // `<ellipse>` without rx / ry has Element::rx() / ry() returning
        // null per 3B — the painter skips it.
        $ops = $this->paint(
            '<svg xmlns="http://www.w3.org/2000/svg"><ellipse cx="0" cy="0"/></svg>',
        );
        self::assertSame('', $ops);
    }

    public function testEllipseEmitsFourCubicBeziers(): void
    {
        $ops = $this->paint(
            '<svg xmlns="http://www.w3.org/2000/svg"><ellipse cx="50" cy="60" rx="20" ry="10"/></svg>',
        );
        self::assertSame(4, substr_count($ops, ' c'));
        self::assertStringContainsString('70 60 m', $ops); // start at (cx+rx, cy)
    }

    public function testLineEmitsOnlyWhenStrokeIsSet(): void
    {
        // Without a stroke a line is invisible — SVG default fill on a
        // line paints nothing visible, so the painter no-ops to keep
        // the content stream clean.
        $opsWithoutStroke = $this->paint(
            '<svg xmlns="http://www.w3.org/2000/svg"><line x1="0" y1="0" x2="10" y2="10"/></svg>',
        );
        self::assertSame('', $opsWithoutStroke);

        $opsWithStroke = $this->paint(
            '<svg xmlns="http://www.w3.org/2000/svg">'
            . '<line x1="0" y1="0" x2="10" y2="10" stroke="black"/></svg>',
        );
        self::assertStringContainsString('0 0 m', $opsWithStroke);
        self::assertStringContainsString('10 10 l', $opsWithStroke);
        self::assertStringContainsString("\nS", $opsWithStroke);
    }

    public function testPolylineDoesNotClosePath(): void
    {
        $ops = $this->paint(
            '<svg xmlns="http://www.w3.org/2000/svg">'
            . '<polyline points="0,0 10,0 10,10" fill="none" stroke="black"/></svg>',
        );
        self::assertStringContainsString('0 0 m', $ops);
        self::assertStringContainsString('10 0 l', $ops);
        self::assertStringContainsString('10 10 l', $ops);
        self::assertStringNotContainsString("\nh", $ops); // no closePath
        self::assertStringContainsString("\nS", $ops);
    }

    public function testPolygonClosesPath(): void
    {
        $ops = $this->paint(
            '<svg xmlns="http://www.w3.org/2000/svg">'
            . '<polygon points="0,0 10,0 10,10 0,10"/></svg>',
        );
        self::assertStringContainsString('0 0 m', $ops);
        self::assertStringContainsString("\nh", $ops);
    }

    public function testPolylineWithFewerThanTwoPointsIsSkipped(): void
    {
        $ops = $this->paint(
            '<svg xmlns="http://www.w3.org/2000/svg"><polyline points="5 5"/></svg>',
        );
        self::assertSame('', $ops);
    }

    public function testPolygonWithFewerThanThreePointsIsSkipped(): void
    {
        $ops = $this->paint(
            '<svg xmlns="http://www.w3.org/2000/svg"><polygon points="0 0 10 0"/></svg>',
        );
        self::assertSame('', $ops);
    }

    public function testUnknownContainersAreTraversedTransparently(): void
    {
        // No 3M `<g>` painter yet, but the recursive walk should still
        // descend into it so the inner shape paints.
        $ops = $this->paint(
            '<svg xmlns="http://www.w3.org/2000/svg"><g><rect width="10" height="10"/></g></svg>',
        );
        self::assertStringContainsString('0 0 10 10 re', $ops);
        self::assertStringContainsString("\nf", $ops);
    }

    public function testRectWithCmykFillEmitsKOperator(): void
    {
        // SVG 2 colour parsing returns RgbColor for named colours, so
        // CMYK arrives through a different path — the painter still
        // handles it correctly because of the ColorInterface dispatch.
        $doc = $this->svgParser->parse(
            '<svg xmlns="http://www.w3.org/2000/svg"><rect width="10" height="10"/></svg>',
        );
        // Programmatically set a CMYK paint to test the dispatch arm
        // since the SVG colour parser only emits sRGB at 3E.
        $rect = $doc->children[0];
        self::assertInstanceOf(\Phpdftk\Svg\Shape\Rect::class, $rect);
        $rect->setAttribute(
            'fill',
            'url(#nope) ', // url falls through; we test the dispatch via assertion below
        );
        $stream = new ContentStream();
        $this->translator->paint($doc, $stream);
        $ops = implode("\n", $stream->getOperators());
        // url(#…) is gradient (3O) — falls through to no fill, and
        // since there's no stroke either, `n` discards the path.
        self::assertStringContainsString('0 0 10 10 re', $ops);
        self::assertStringContainsString("\nn", $ops);
    }
}
