<?php

declare(strict_types=1);

namespace Phpdftk\HtmlToPdf\Tests\Mathml;

use Phpdftk\FontParser\TrueTypeParser;
use Phpdftk\HtmlToPdf\Renderer;
use Phpdftk\HtmlToPdf\RendererOptions;
use Phpdftk\Pdf\Writer\PdfWriter;
use PHPUnit\Framework\TestCase;

/**
 * Inline `<math>` has to occupy space during LAYOUT, not just at
 * paint time.
 *
 * `MathmlRenderer::intrinsicSize()` has always been able to measure a
 * MathML document, but its only caller was the painter, which runs
 * after layout has placed everything. Layout therefore sized the
 * `<math>` atomic inline box at 0x0: the element contributed no
 * inline advance, so content after it overprinted the equation, and a
 * line holding nothing but math collapsed to the height of an empty
 * text line.
 *
 * The probes are 10x10 `inline-block` markers rather than text. Their
 * backgrounds are emitted as `x y w h re` rectangles in absolute user
 * space, so a failure names a coordinate — and, unlike glyphs, they
 * are measurable without decoding a CID font's ToUnicode table (the
 * default font here embeds as Type 0, which would otherwise coalesce
 * neighbouring letters into one unreadable hex run).
 */
final class InlineMathIntrinsicSizeTest extends TestCase
{
    private const string MARKER_CSS =
        '.m{display:inline-block;width:10px;height:10px;background:red;vertical-align:baseline}';

    /** `<mspace>`: an exactly-sized box with no font metrics in play. */
    private const string MATH_60x40 =
        '<math xmlns="http://www.w3.org/1998/Math/MathML">'
        . '<mspace width="60px" height="40px" depth="0px"/>'
        . '</math>';

    // -----------------------------------------------------------------
    // Inline advance.
    // -----------------------------------------------------------------

    public function testInlineMathAdvancesTheContentAfterIt(): void
    {
        $markers = $this->markers($this->render(
            '<span class=m></span>' . self::MATH_60x40 . '<span class=m></span>',
        ));
        self::assertCount(2, $markers);
        self::assertEqualsWithDelta(
            0.0 + 10.0 + 60.0,
            $markers[1][0],
            0.5,
            'content after inline math must start past the math, not on top of it',
        );
    }

    public function testInlineMathAdvanceScalesWithItsContent(): void
    {
        // Two documents differing only in the equation's width: the
        // trailing marker has to move by exactly that difference.
        $narrow = $this->markers($this->render(
            '<span class=m></span><math xmlns="http://www.w3.org/1998/Math/MathML">'
            . '<mspace width="20px" height="40px" depth="0px"/></math><span class=m></span>',
        ));
        $wide = $this->markers($this->render(
            '<span class=m></span><math xmlns="http://www.w3.org/1998/Math/MathML">'
            . '<mspace width="120px" height="40px" depth="0px"/></math><span class=m></span>',
        ));
        self::assertEqualsWithDelta(
            100.0,
            $wide[1][0] - $narrow[1][0],
            0.5,
            'a 100px wider equation must push the following content 100px further',
        );
    }

    public function testDeclaredCssWidthStillWinsOverTheIntrinsicSize(): void
    {
        // The intrinsic size is a FALLBACK, so the hook must not
        // override an author's declared width.
        $markers = $this->markers($this->render(
            '<span class=m></span>'
            . '<math xmlns="http://www.w3.org/1998/Math/MathML" style="width:200px">'
            . '<mspace width="60px" height="40px" depth="0px"/></math>'
            . '<span class=m></span>',
        ));
        self::assertEqualsWithDelta(10.0 + 200.0, $markers[1][0], 0.5);
    }

    // -----------------------------------------------------------------
    // Block-axis footprint.
    // -----------------------------------------------------------------

    public function testLineHoldingOnlyMathIsTallEnoughForIt(): void
    {
        // A marker on the following block must clear a 40px-tall
        // equation. With a zero-height math box the two overlap.
        $markers = $this->markers($this->render(
            '<div>' . self::MATH_60x40 . '</div><div><span class=m></span></div>',
        ));
        self::assertCount(1, $markers);
        // PDF Y grows upward from the page bottom; the page is 792
        // tall, so the marker's TOP edge is 792 - (y + height).
        $markerTop = 792.0 - ($markers[0][1] + $markers[0][3]);
        self::assertGreaterThanOrEqual(
            40.0,
            $markerTop,
            'a block after a 40px equation must start below it',
        );
    }

    // -----------------------------------------------------------------
    // Guards.
    // -----------------------------------------------------------------

    public function testMathStillPaintsItsGlyphs(): void
    {
        // The sizing hook must not disturb what actually gets drawn.
        $bytes = $this->render(
            '<math xmlns="http://www.w3.org/1998/Math/MathML"><mn>2</mn></math>',
        );
        self::assertMatchesRegularExpression('/\(2\)\s+Tj/', $bytes);
    }

    public function testEmptyMathDoesNotReserveAnArbitraryBand(): void
    {
        // `<math></math>` measures to nothing, so the following
        // content must not be pushed out by a placeholder width.
        $markers = $this->markers($this->render(
            '<span class=m></span>'
            . '<math xmlns="http://www.w3.org/1998/Math/MathML"></math>'
            . '<span class=m></span>',
        ));
        self::assertEqualsWithDelta(10.0, $markers[1][0], 0.5);
    }

    public function testMarkersWithoutMathAreAdjacent(): void
    {
        // Calibration: without the equation the two markers touch, so
        // every offset above is attributable to the math box.
        $markers = $this->markers($this->render(
            '<span class=m></span><span class=m></span>',
        ));
        self::assertEqualsWithDelta(0.0, $markers[0][0], 0.5);
        self::assertEqualsWithDelta(10.0, $markers[1][0], 0.5);
    }

    // -----------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------

    /**
     * The 10x10 marker rectangles, in stream order. The page's own
     * full-bleed clip rect is filtered out by size.
     *
     * @return list<array{0: float, 1: float, 2: float, 3: float}>
     */
    private function markers(string $bytes): array
    {
        preg_match_all(
            '/(-?\d+\.?\d*)\s+(-?\d+\.?\d*)\s+(-?\d+\.?\d*)\s+(-?\d+\.?\d*)\s+re\b/',
            $bytes,
            $matches,
            PREG_SET_ORDER,
        );
        $rects = [];
        foreach ($matches as $m) {
            $rect = [(float) $m[1], (float) $m[2], (float) $m[3], (float) $m[4]];
            if (abs($rect[2] - 10.0) < 0.01 && abs($rect[3] - 10.0) < 0.01) {
                $rects[] = $rect;
            }
        }

        return $rects;
    }

    private function render(string $bodyHtml): string
    {
        $writer = new PdfWriter(compressStreams: false);
        $options = new RendererOptions();
        $fontPath = __DIR__ . '/../../../wpt-harness/resources/fonts/DejaVuSerif.ttf';
        if (is_file($fontPath)) {
            $options = $options->withDefaultFont((new TrueTypeParser($fontPath))->parse());
        }
        (new Renderer($options))->renderInto(
            $writer,
            '<html><head><style>' . self::MARKER_CSS . '</style></head>'
            . '<body style="margin:0">' . $bodyHtml . '</body></html>',
        );

        return $writer->toBytes();
    }
}
