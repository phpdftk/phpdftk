<?php

declare(strict_types=1);

namespace Phpdftk\HtmlToPdf\Tests\Svg;

use Phpdftk\Css\Cascade\Cascade;
use Phpdftk\Css\Cascade\PropertyRegistry;
use Phpdftk\Css\Parser as CssParser;
use Phpdftk\Css\Sheet\Origin;
use Phpdftk\Css\Value\Length;
use Phpdftk\Html\Dom\Document as HtmlDocument;
use Phpdftk\Html\Dom\Element as HtmlElement;
use Phpdftk\Html\Parser as HtmlParser;
use Phpdftk\HtmlToPdf\Renderer;
use Phpdftk\Pdf\Writer\PdfWriter;
use PHPUnit\Framework\TestCase;

/**
 * Diagnostic + regression suite for the CSS dimensions follow-up
 * from PR #38. Until this lands, an `<svg id="s">` element with
 * `#s { width: 50pt; height: 50pt; }` rendered as a zero-sized box
 * — the cascade resolved the values on the element but the inline
 * layout pass didn't pull them onto the AtomicInlineBox's geometry.
 *
 * Each test isolates a layer to pin down where the breakdown
 * happens before the integration assertion:
 *
 *   1. Selector matching: does `#s` match `<svg id="s">`?
 *   2. Cascade: does the cascade produce a Length(50pt) for width?
 *   3. Layout: does the AtomicInlineBox end up with width=50pt?
 *   4. Render: does the painter emit the SVG content?
 */
final class InlineSvgCssDimensionsTest extends TestCase
{
    public function testIdSelectorMatchesSvgElement(): void
    {
        $html = '<html><body><svg id="s" xmlns="http://www.w3.org/2000/svg"></svg></body></html>';
        $doc = (new HtmlParser())->parseDocument($html);
        $svg = $doc->getElementsByTagName('svg')[0] ?? null;
        self::assertNotNull($svg);
        self::assertSame('s', $svg->elementId());
        self::assertSame(HtmlDocument::SVG_NS, $svg->namespaceUri());
    }

    public function testCascadeResolvesCssWidthOnSvg(): void
    {
        $html = '<html><body><svg id="s" xmlns="http://www.w3.org/2000/svg"></svg></body></html>';
        $css = '#s { width: 50pt; height: 50pt; display: inline-block; }';

        $doc = (new HtmlParser())->parseDocument($html);
        $svg = $doc->getElementsByTagName('svg')[0];

        $registry = new PropertyRegistry();
        $sheet = (new CssParser())->parseStylesheet($css, Origin::Author);
        $cascade = new Cascade($registry);
        $cascaded = $cascade->computeFor([$sheet], $svg);

        $width = $cascaded->get('width');
        self::assertInstanceOf(
            Length::class,
            $width,
            'cascade should resolve `width: 50pt` to a Length on the svg element',
        );
        self::assertSame(50.0, $width->value);
    }

    public function testCssSizedSvgRendersGreenFill(): void
    {
        $writer = new PdfWriter(compressStreams: false);
        (new Renderer())->renderInto(
            $writer,
            '<html><body>'
                . '<svg id="s" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100">'
                . '<rect width="100" height="100" fill="#00ff00"/>'
                . '</svg>'
                . '</body></html>',
            '#s { display: inline-block; width: 50pt; height: 50pt; }',
        );
        $bytes = $writer->toBytes();
        self::assertStringStartsWith('%PDF-', $bytes);
        // Green fill from the rect — proves the SVG paint reached the
        // content stream.
        self::assertMatchesRegularExpression(
            '/\b0(?:\.0+)?\s+1(?:\.0+)?\s+0(?:\.0+)?\s+rg\b/',
            $bytes,
            'green fill not emitted for CSS-sized inline SVG',
        );
    }

    public function testIntrinsicallyOnlyViewBoxSizedSvgRenders(): void
    {
        // No CSS width/height, no `width`/`height` attrs — only the
        // viewBox declares dimensions. The painter must fall back to
        // viewBox's width/height columns (treated as CSS pixels) so
        // SVG-2-conformant "intrinsic-only" inline SVGs render at
        // their natural size.
        $writer = new PdfWriter(compressStreams: false);
        (new Renderer())->renderInto(
            $writer,
            '<html><body>'
                . '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 60 40">'
                . '<rect width="60" height="40" fill="#0000ff"/>'
                . '</svg>'
                . '</body></html>',
        );
        $bytes = $writer->toBytes();
        self::assertStringStartsWith('%PDF-', $bytes);
        // Blue fill from the rect — proves the viewBox fallback
        // sized the box and the renderer paints into it.
        self::assertMatchesRegularExpression(
            '/\b0(?:\.0+)?\s+0(?:\.0+)?\s+1(?:\.0+)?\s+rg\b/',
            $bytes,
            'blue fill not emitted for viewBox-intrinsic-sized SVG',
        );
    }

    public function testMalformedViewBoxFallsThroughToSkip(): void
    {
        // Viewport with a malformed viewBox AND no other size info —
        // should produce a valid PDF that contains no SVG payload.
        $writer = new PdfWriter(compressStreams: false);
        (new Renderer())->renderInto(
            $writer,
            '<html><body>'
                . '<svg xmlns="http://www.w3.org/2000/svg" viewBox="not a viewBox">'
                . '<rect width="10" height="10" fill="#fff"/>'
                . '</svg>'
                . '</body></html>',
        );
        $bytes = $writer->toBytes();
        self::assertStringStartsWith('%PDF-', $bytes);
        self::assertStringEndsWith('%%EOF', trim($bytes));
    }

    public function testNegativeViewBoxFallsThroughToSkip(): void
    {
        // Negative width/height in viewBox — the spec invalidates these.
        // Painter must skip rather than emit a degenerate path.
        $writer = new PdfWriter(compressStreams: false);
        (new Renderer())->renderInto(
            $writer,
            '<html><body>'
                . '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 -100 -100">'
                . '<rect width="10" height="10" fill="#fff"/>'
                . '</svg>'
                . '</body></html>',
        );
        $bytes = $writer->toBytes();
        self::assertStringStartsWith('%PDF-', $bytes);
    }
}
