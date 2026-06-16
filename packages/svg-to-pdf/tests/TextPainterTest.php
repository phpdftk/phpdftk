<?php

declare(strict_types=1);

namespace Phpdftk\SvgToPdf\Tests;

use Phpdftk\Pdf\Core\Content\ContentStream;
use Phpdftk\Pdf\Writer\PdfWriter;
use Phpdftk\Svg\Parser as SvgParser;
use Phpdftk\SvgToPdf\Translator;
use PHPUnit\Framework\TestCase;

/**
 * 3P `<text>` painter. Requires `?Page` + `?PdfWriter` to register
 * standard fonts; without them the text element silently paints
 * nothing (parser-only callers stay working).
 */
final class TextPainterTest extends TestCase
{
    private SvgParser $svgParser;
    private Translator $translator;

    protected function setUp(): void
    {
        $this->svgParser = new SvgParser();
        $this->translator = new Translator();
    }

    /**
     * @return array{ops: string, bytes: string}
     */
    private function paintWithWriter(string $svg): array
    {
        $writer = new PdfWriter();
        $page = $writer->addPage();
        $stream = $writer->addContentStream($page);
        $doc = $this->svgParser->parse($svg);
        $this->translator->paint($doc, $stream, $page, $writer);
        return [
            'ops' => implode("\n", $stream->getOperators()),
            'bytes' => $writer->toBytes(),
        ];
    }

    public function testTextEmitsBeginTextSetFontShowTextEndText(): void
    {
        $result = $this->paintWithWriter(
            '<svg xmlns="http://www.w3.org/2000/svg">'
            . '<text x="50" y="100">Hello</text></svg>',
        );
        // BT … ET frame.
        self::assertSame(1, substr_count($result['ops'], "\nBT\n"));
        self::assertSame(1, substr_count($result['ops'], "\nET"));
        // Tf with font resource name (F1 for first registered) + size 16.
        self::assertMatchesRegularExpression('!/F\d+ 16 Tf!', $result['ops']);
        // Td positions at (50, 100).
        self::assertStringContainsString('50 100 Td', $result['ops']);
        // Tj shows the encoded text (Helvetica uses WinAnsi, ASCII passes through).
        self::assertStringContainsString('(Hello) Tj', $result['ops']);
    }

    public function testEmptyTextEmitsNothing(): void
    {
        $result = $this->paintWithWriter(
            '<svg xmlns="http://www.w3.org/2000/svg"><text x="0" y="0"></text></svg>',
        );
        self::assertStringNotContainsString('BT', $result['ops']);
    }

    public function testTextWithoutXYDefaultsToOriginAtZero(): void
    {
        $result = $this->paintWithWriter(
            '<svg xmlns="http://www.w3.org/2000/svg"><text>X</text></svg>',
        );
        self::assertStringContainsString('0 0 Td', $result['ops']);
    }

    public function testFontSizeAttributeIsHonoured(): void
    {
        $result = $this->paintWithWriter(
            '<svg xmlns="http://www.w3.org/2000/svg">'
            . '<text x="0" y="0" font-size="24">X</text></svg>',
        );
        self::assertMatchesRegularExpression('!/F\d+ 24 Tf!', $result['ops']);
    }

    public function testGenericFamilySerifSelectsTimes(): void
    {
        $result = $this->paintWithWriter(
            '<svg xmlns="http://www.w3.org/2000/svg">'
            . '<text x="0" y="0" font-family="serif">X</text></svg>',
        );
        // PDF body should embed a Type1Font with /BaseFont /Times-Roman.
        self::assertStringContainsString('/BaseFont /Times-Roman', $result['bytes']);
    }

    public function testGenericFamilyMonospaceSelectsCourier(): void
    {
        $result = $this->paintWithWriter(
            '<svg xmlns="http://www.w3.org/2000/svg">'
            . '<text x="0" y="0" font-family="monospace">X</text></svg>',
        );
        self::assertStringContainsString('/BaseFont /Courier', $result['bytes']);
    }

    public function testFontWeightBoldPicksBoldVariant(): void
    {
        $result = $this->paintWithWriter(
            '<svg xmlns="http://www.w3.org/2000/svg">'
            . '<text x="0" y="0" font-weight="bold">X</text></svg>',
        );
        self::assertStringContainsString('/BaseFont /Helvetica-Bold', $result['bytes']);
    }

    public function testNumericFontWeightAtLeast600IsBold(): void
    {
        $result = $this->paintWithWriter(
            '<svg xmlns="http://www.w3.org/2000/svg">'
            . '<text x="0" y="0" font-weight="700">X</text></svg>',
        );
        self::assertStringContainsString('/BaseFont /Helvetica-Bold', $result['bytes']);
    }

    public function testItalicStyleSelectsOblique(): void
    {
        $result = $this->paintWithWriter(
            '<svg xmlns="http://www.w3.org/2000/svg">'
            . '<text x="0" y="0" font-style="italic">X</text></svg>',
        );
        self::assertStringContainsString('/BaseFont /Helvetica-Oblique', $result['bytes']);
    }

    public function testBoldItalicSelectsBoldObliqueVariant(): void
    {
        $result = $this->paintWithWriter(
            '<svg xmlns="http://www.w3.org/2000/svg">'
            . '<text x="0" y="0" font-weight="bold" font-style="italic">X</text></svg>',
        );
        self::assertStringContainsString('/BaseFont /Helvetica-BoldOblique', $result['bytes']);
    }

    public function testFontFamilyListFallsToFirstRecognisedKeyword(): void
    {
        // The author's first family (`Open Sans`) isn't a generic the
        // resolver recognises; it walks the list and picks
        // `sans-serif` → Helvetica.
        $result = $this->paintWithWriter(
            '<svg xmlns="http://www.w3.org/2000/svg">'
            . '<text x="0" y="0" font-family="&quot;Open Sans&quot;, sans-serif">X</text></svg>',
        );
        self::assertStringContainsString('/BaseFont /Helvetica', $result['bytes']);
        self::assertStringNotContainsString('/BaseFont /Times', $result['bytes']);
    }

    public function testTspanTextNodesConcatenateIntoParentTextRun(): void
    {
        // 3P uses a single Tj for the combined content of `<text>` and
        // its tspan children. Per-tspan positioning / styling is
        // deferred to a future sub-phase.
        $result = $this->paintWithWriter(
            '<svg xmlns="http://www.w3.org/2000/svg">'
            . '<text x="10" y="10">Hello <tspan>world</tspan>!</text></svg>',
        );
        self::assertStringContainsString('(Hello world!) Tj', $result['ops']);
    }

    public function testTextWithoutWriterFallsBackToSilentNoOp(): void
    {
        $writer = new PdfWriter();
        $page = $writer->addPage();
        $stream = $writer->addContentStream($page);
        $doc = $this->svgParser->parse(
            '<svg xmlns="http://www.w3.org/2000/svg"><text x="0" y="0">X</text></svg>',
        );
        $this->translator->paint($doc, $stream); // no Page / Writer
        $ops = implode("\n", $stream->getOperators());
        self::assertSame('', $ops);
    }

    public function testTextWithExplicitFillEmitsColorBeforeBT(): void
    {
        $result = $this->paintWithWriter(
            '<svg xmlns="http://www.w3.org/2000/svg">'
            . '<text x="0" y="0" fill="red">X</text></svg>',
        );
        // Find the `rg` line and the `BT` line and confirm `rg` precedes `BT`.
        $rgPos = strpos($result['ops'], '1 0 0 rg');
        $btPos = strpos($result['ops'], 'BT');
        self::assertNotFalse($rgPos);
        self::assertNotFalse($btPos);
        self::assertLessThan($btPos, $rgPos);
    }

    public function testIntegrationProducesValidPdfWithText(): void
    {
        $writer = new PdfWriter();
        $page = $writer->addPage(612, 792);
        $stream = $writer->addContentStream($page);
        $doc = $this->svgParser->parse(<<<'SVG'
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 200 100">
              <text x="10" y="40" font-size="24" fill="blue">Hello, SVG!</text>
            </svg>
            SVG);
        $this->translator->paint($doc, $stream, $page, $writer);
        $bytes = $writer->toBytes();
        self::assertStringStartsWith('%PDF-', $bytes);
        self::assertStringContainsString('%%EOF', $bytes);
        self::assertStringContainsString('/Type /Font', $bytes);
    }

    // ------------------------------------------------------------
    // text-shadow on <text> (#142, CSS Text Decoration 4 §6)
    // ------------------------------------------------------------

    public function testTextShadowAbsentEmitsSingleTextObject(): void
    {
        $result = $this->paintWithWriter(
            '<svg xmlns="http://www.w3.org/2000/svg">'
            . '<text x="20" y="20" fill="blue">A</text></svg>',
        );
        self::assertSame(1, substr_count($result['ops'], "\nBT\n"));
    }

    public function testTextShadowNoneEmitsSingleTextObject(): void
    {
        // `none` is the spec sentinel for "no shadow" and must NOT
        // produce a phantom layer.
        $result = $this->paintWithWriter(
            '<svg xmlns="http://www.w3.org/2000/svg">'
            . '<text x="20" y="20" style="text-shadow: none" fill="blue">A</text></svg>',
        );
        self::assertSame(1, substr_count($result['ops'], "\nBT\n"));
    }

    public function testTextShadowEmptyValueIsIgnored(): void
    {
        $result = $this->paintWithWriter(
            '<svg xmlns="http://www.w3.org/2000/svg">'
            . '<text x="20" y="20" style="text-shadow:   " fill="blue">A</text></svg>',
        );
        self::assertSame(1, substr_count($result['ops'], "\nBT\n"));
    }

    public function testTextShadowSingleLengthIsRejected(): void
    {
        // Spec requires at least two lengths (offset-x, offset-y).
        // A solitary length is a malformed layer and must be dropped.
        $result = $this->paintWithWriter(
            '<svg xmlns="http://www.w3.org/2000/svg">'
            . '<text x="20" y="20" style="text-shadow: 5px green" fill="blue">A</text></svg>',
        );
        self::assertSame(1, substr_count($result['ops'], "\nBT\n"));
    }

    public function testTextShadowNegativeBlurRejected(): void
    {
        // CSS Text Decoration 4: blur-radius must be non-negative.
        $result = $this->paintWithWriter(
            '<svg xmlns="http://www.w3.org/2000/svg">'
            . '<text x="20" y="20" style="text-shadow: 0 0 -5px green" fill="blue">A</text></svg>',
        );
        self::assertSame(1, substr_count($result['ops'], "\nBT\n"));
    }

    public function testTextShadowUnsupportedUnitIsRejected(): void
    {
        // `em` requires a containing-block resolution we don't have
        // at this layer; rather than silently treat it as 0, drop
        // the whole layer so authors notice the omission.
        $result = $this->paintWithWriter(
            '<svg xmlns="http://www.w3.org/2000/svg">'
            . '<text x="20" y="20" style="text-shadow: 1em 1em green" fill="blue">A</text></svg>',
        );
        self::assertSame(1, substr_count($result['ops'], "\nBT\n"));
    }

    public function testTextShadowMalformedColorRejectsLayer(): void
    {
        $result = $this->paintWithWriter(
            '<svg xmlns="http://www.w3.org/2000/svg">'
            . '<text x="20" y="20" style="text-shadow: 1px 1px banana" fill="blue">A</text></svg>',
        );
        self::assertSame(1, substr_count($result['ops'], "\nBT\n"));
    }

    public function testTextShadowOnPerGlyphTextIsSkipped(): void
    {
        // Per-glyph positioning + shadow is intentionally not
        // implemented at v1 (no WPT coverage to validate). The main
        // text still paints; only the shadow layer is omitted.
        $result = $this->paintWithWriter(
            '<svg xmlns="http://www.w3.org/2000/svg">'
            . '<text x="10 20" y="20" style="text-shadow: 2px 2px red" fill="blue">AB</text></svg>',
        );
        self::assertSame(1, substr_count($result['ops'], "\nBT\n"));
    }

    public function testTextShadowBasicEmitsExtraTextObjectAtOffset(): void
    {
        // Sanity: the simple case from svg/painting/reftests/text-shadow-01.
        // One shadow layer should produce a second BT...ET block before
        // the main text. The standalone painter (no outer Y-flip)
        // uses `Td` to position text; the compensateTextFlip=true path
        // uses `Tm` instead - both flows are covered by the renderer
        // tests in SvgRendererTest.
        $result = $this->paintWithWriter(
            '<svg xmlns="http://www.w3.org/2000/svg">'
            . '<text x="20" y="20" style="text-shadow: 0 0 5px green" fill="darkgreen">A</text></svg>',
        );
        self::assertSame(2, substr_count($result['ops'], "\nBT\n"));
        // Two paints of "A" - shadow first, then main on top.
        self::assertSame(2, substr_count($result['ops'], '(A) Tj'));
    }

    public function testTextShadowOffsetIsAddedToTextOrigin(): void
    {
        // Shadow at (3px, 4px) offset against text origin (20, 20)
        // must paint at (23, 24).
        $result = $this->paintWithWriter(
            '<svg xmlns="http://www.w3.org/2000/svg">'
            . '<text x="20" y="20" style="text-shadow: 3px 4px green" fill="darkgreen">A</text></svg>',
        );
        self::assertStringContainsString('23 24 Td', $result['ops']);
        // Main text still paints at the un-offset origin (20, 20).
        self::assertStringContainsString('20 20 Td', $result['ops']);
    }

    public function testTextShadowMultipleLayersPaintFirstListedOnTop(): void
    {
        // CSS Text Decoration 4 §6: first listed shadow sits on top
        // (painted LAST). With "1px 1px red, 5px 5px blue", blue
        // paints first (back), then red (on top of blue), then the
        // text (top of all).
        $result = $this->paintWithWriter(
            '<svg xmlns="http://www.w3.org/2000/svg">'
            . '<text x="0" y="0" style="text-shadow: 1px 1px red, 5px 5px blue" fill="black">A</text></svg>',
        );
        self::assertSame(3, substr_count($result['ops'], "\nBT\n"));
        // Order check: blue offset paints before red offset.
        $bluePos = strpos($result['ops'], '5 5 Td');
        $redPos = strpos($result['ops'], '1 1 Td');
        self::assertNotFalse($bluePos);
        self::assertNotFalse($redPos);
        self::assertLessThan($redPos, $bluePos);
    }

    public function testTextShadowDefaultColorFallsBackToFillColor(): void
    {
        // No explicit shadow colour → use the text's fill colour.
        // The fixture uses fill=red (1, 0, 0), so the shadow's fill-
        // colour `rg` op should be `1 0 0 rg` and emitted twice
        // (once for shadow, once for main text).
        $result = $this->paintWithWriter(
            '<svg xmlns="http://www.w3.org/2000/svg">'
            . '<text x="0" y="0" style="text-shadow: 2px 2px" fill="red">A</text></svg>',
        );
        // Two rg ops: shadow + main, both red.
        preg_match_all('/1 0 0 rg/', $result['ops'], $m);
        self::assertGreaterThanOrEqual(2, count($m[0]));
    }

    public function testTextShadowColorBeforeLengthsAlsoParses(): void
    {
        // CSS Text Decoration 4 lets the colour appear before or
        // after the lengths. `green 2px 2px` is a single valid layer.
        $result = $this->paintWithWriter(
            '<svg xmlns="http://www.w3.org/2000/svg">'
            . '<text x="0" y="0" style="text-shadow: green 2px 2px" fill="black">A</text></svg>',
        );
        self::assertSame(2, substr_count($result['ops'], "\nBT\n"));
    }

    public function testTextShadowFunctionalColorWithCommasParses(): void
    {
        // The shadow splitter must keep commas inside `rgb(...)`
        // (or `rgba(...)`) as part of the same shadow layer rather
        // than splitting it into "0 0 5px rgb(0", "128", "0)".
        $result = $this->paintWithWriter(
            '<svg xmlns="http://www.w3.org/2000/svg">'
            . '<text x="0" y="0" style="text-shadow: 0 0 5px rgb(0, 128, 0)" fill="black">A</text></svg>',
        );
        self::assertSame(2, substr_count($result['ops'], "\nBT\n"));
    }
}
