<?php

declare(strict_types=1);

namespace Phpdftk\SvgToPdf\Tests;

use Phpdftk\Pdf\Core\Content\ContentStream;
use Phpdftk\Svg\Parser as SvgParser;
use Phpdftk\SvgToPdf\SvgCascadeProjector;
use Phpdftk\SvgToPdf\Translator;
use PHPUnit\Framework\TestCase;

/**
 * SVG 2 §10.1 geometry properties — `x`, `y`, `width`, `height`, `cx`,
 * `cy`, `r`, `rx`, `ry` are CSS properties as well as presentation
 * attributes, so a shape can be sized entirely from a stylesheet.
 */
final class GeometryPropertyTest extends TestCase
{
    /** Paint WITHOUT the cascade projector (inline `style` only). */
    private function paint(string $svg): string
    {
        $doc = (new SvgParser())->parse($svg);
        $stream = new ContentStream();
        (new Translator())->paint($doc, $stream);
        return implode("\n", $stream->getOperators());
    }

    /** Paint WITH the cascade projector, so `<style>` blocks apply. */
    private function paintStyled(string $svg): string
    {
        $doc = (new SvgParser())->parse($svg);
        (new SvgCascadeProjector())->project($doc);
        $stream = new ContentStream();
        (new Translator())->paint($doc, $stream);
        return implode("\n", $stream->getOperators());
    }

    public function testRectSizedByInlineStyleGeometry(): void
    {
        $ops = $this->paint(
            '<svg xmlns="http://www.w3.org/2000/svg" width="300" height="200">'
            . '<rect style="x:30px;y:60px;width:120px;height:100px;fill:blue"/>'
            . '</svg>',
        );
        self::assertStringContainsString('30 60 120 100 re', $ops);
    }

    public function testRectSizedByStyleBlockGeometry(): void
    {
        $ops = $this->paintStyled(
            '<svg xmlns="http://www.w3.org/2000/svg" width="300" height="200">'
            . '<style>rect { x: 30px; y: 60px; width: 120px; height: 100px; fill: blue }</style>'
            . '<rect/>'
            . '</svg>',
        );
        self::assertStringContainsString('30 60 120 100 re', $ops);
    }

    /**
     * A presentation attribute on the element is the author's own value
     * and keeps reaching the painter — the projector must not overwrite
     * it with the cascaded one.
     */
    public function testPresentationAttributeStillWinsOverStyleBlock(): void
    {
        $ops = $this->paintStyled(
            '<svg xmlns="http://www.w3.org/2000/svg" width="300" height="200">'
            . '<style>rect { width: 999px }</style>'
            . '<rect x="0" y="0" width="120" height="100" fill="blue"/>'
            . '</svg>',
        );
        self::assertStringContainsString('0 0 120 100 re', $ops);
        self::assertStringNotContainsString('999', $ops);
    }

    /** Percentage x/width resolve against the viewport WIDTH. */
    public function testRectPercentageGeometryResolvesAgainstViewport(): void
    {
        $ops = $this->paintStyled(
            '<svg xmlns="http://www.w3.org/2000/svg" width="300" height="200">'
            . '<style>rect { x: 10%; y: 30%; width: 40%; height: 50%; fill: blue }</style>'
            . '<rect/>'
            . '</svg>',
        );
        // 10% of 300, 30% of 200, 40% of 300, 50% of 200.
        self::assertStringContainsString('30 60 120 100 re', $ops);
    }

    public function testCircleSizedByStyleBlockGeometry(): void
    {
        $ops = $this->paintStyled(
            '<svg xmlns="http://www.w3.org/2000/svg" width="340" height="140">'
            . '<style>circle { cx: 204px; cy: 56px; r: 65px; fill: blue }</style>'
            . '<circle/>'
            . '</svg>',
        );
        // Rightmost point of the circle: cx + r.
        self::assertStringContainsString('269 56 m', $ops);
    }

    /**
     * SVG 2 §10.1 — a percentage `r` resolves against the NORMALIZED
     * DIAGONAL `sqrt(w² + h²) / sqrt(2)`, not against either axis.
     * For a 340×140 viewport that diagonal is 260, so `r: 25%` is 65.
     */
    public function testCirclePercentageRadiusUsesNormalizedDiagonal(): void
    {
        $ops = $this->paintStyled(
            '<svg xmlns="http://www.w3.org/2000/svg" width="340" height="140">'
            . '<style>circle { cx: 60%; cy: 40%; r: 25%; fill: blue }</style>'
            . '<circle/>'
            . '</svg>',
        );
        // cx = 60% of 340 = 204, r = 25% of 260 = 65 → 204 + 65 = 269.
        self::assertStringContainsString('269 56 m', $ops);
    }

    /**
     * SVG 2 §10.1 — a NEGATIVE `rx` is invalid, so the declaration is
     * ignored and `rx` keeps its initial `auto`, which mirrors `ry`.
     * The ellipse therefore paints as a circle rather than vanishing.
     */
    public function testNegativeEllipseRadiusFallsBackToAuto(): void
    {
        $negativeRx = $this->paint(
            '<svg xmlns="http://www.w3.org/2000/svg">'
            . '<ellipse cx="204" cy="56" rx="-65" ry="65" fill="blue"/>'
            . '</svg>',
        );
        $circle = $this->paint(
            '<svg xmlns="http://www.w3.org/2000/svg">'
            . '<circle cx="204" cy="56" r="65" fill="blue"/>'
            . '</svg>',
        );
        self::assertSame($circle, $negativeRx);
    }

    public function testNegativeEllipseRyFallsBackToAuto(): void
    {
        $negativeRy = $this->paint(
            '<svg xmlns="http://www.w3.org/2000/svg">'
            . '<ellipse cx="204" cy="56" rx="65" ry="-65" fill="blue"/>'
            . '</svg>',
        );
        self::assertStringContainsString('269 56 m', $negativeRy);
    }

    /** Both radii negative leaves both `auto`, so nothing paints. */
    public function testBothRadiiNegativePaintsNothing(): void
    {
        $ops = $this->paint(
            '<svg xmlns="http://www.w3.org/2000/svg">'
            . '<ellipse cx="204" cy="56" rx="-65" ry="-65" fill="blue"/>'
            . '</svg>',
        );
        self::assertSame('', $ops);
    }

    /** A negative rect width is invalid; the rect does not paint. */
    public function testNegativeRectWidthPaintsNothing(): void
    {
        $ops = $this->paint(
            '<svg xmlns="http://www.w3.org/2000/svg">'
            . '<rect x="0" y="0" width="-10" height="10" fill="blue"/>'
            . '</svg>',
        );
        self::assertSame('', $ops);
    }

    /**
     * Geometry reaches the CLIP path builder too — the clip region and
     * the painter share one geometry resolver.
     */
    public function testStyleGeometryAppliesToClipPathChildren(): void
    {
        $ops = $this->paintStyled(
            '<svg xmlns="http://www.w3.org/2000/svg" width="300" height="200">'
            . '<style>clipPath rect { x: 5px; y: 5px; width: 20px; height: 20px }</style>'
            . '<defs><clipPath id="c"><rect/></clipPath></defs>'
            . '<rect width="40" height="40" fill="red" clip-path="url(#c)"/>'
            . '</svg>',
        );
        self::assertStringContainsString('5 5 20 20 re', $ops);
    }
}
