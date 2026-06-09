<?php

declare(strict_types=1);

namespace Phpdftk\MathmlToPdf\Tests;

use Phpdftk\Mathml\Parser as MathmlParser;
use Phpdftk\MathmlToPdf\MathmlRenderer;
use Phpdftk\Pdf\Writer\PdfWriter;
use PHPUnit\Framework\TestCase;

/**
 * Renderer coverage for `<msqrt>` and `<mroot>` plus the fraction
 * bar that {@see MfracRenderingTest} couldn't assert before the
 * Translator gained absolute-coords plumbing.
 *
 * The "path emission inside the BT/ET text block" pattern is shared
 * across mfrac (horizontal bar), msqrt (vinculum), and mroot (vinculum
 * + small index). Tests assert the canonical operators land:
 *
 *   - `ET` ends the text block before path emission.
 *   - `m` (moveto) + `l` (lineto) + `S` (stroke) draw the rule.
 *   - `BT` re-opens the text block.
 *   - `Tm` absolute-matrix reset restores the post-construct cursor.
 */
final class RadicalRenderingTest extends TestCase
{
    private MathmlParser $parser;

    protected function setUp(): void
    {
        $this->parser = new MathmlParser();
    }

    // -----------------------------------------------------------------
    // Fraction bar (the previously-deferred piece of mfrac)
    // -----------------------------------------------------------------

    public function testMfracEmitsHorizontalBar(): void
    {
        $bytes = $this->render(
            '<math xmlns="http://www.w3.org/1998/Math/MathML">'
                . '<mfrac><mn>1</mn><mn>2</mn></mfrac>'
                . '</math>',
        );
        self::assertStringStartsWith('%PDF-', $bytes);
        $this->assertEmitsHorizontalRule($bytes);
    }

    public function testMfracLinethicknessZeroSuppressesBar(): void
    {
        // Binomial coefficient form per Core §3.3.2 — explicit
        // linethickness="0" means no bar.
        $bytes = $this->render(
            '<math xmlns="http://www.w3.org/1998/Math/MathML">'
                . '<mfrac linethickness="0"><mn>1</mn><mn>2</mn></mfrac>'
                . '</math>',
        );
        self::assertStringStartsWith('%PDF-', $bytes);
        // No stroke operator should appear — content is still emitted
        // (numerator + denominator visible), but no bar path.
        self::assertMatchesRegularExpression('/\(1\)\s+Tj/', $bytes);
        self::assertMatchesRegularExpression('/\(2\)\s+Tj/', $bytes);
        self::assertDoesNotMatchRegularExpression(
            '/\nS\n/',
            $bytes,
            'linethickness="0" should suppress the bar stroke',
        );
    }

    // -----------------------------------------------------------------
    // <msqrt>
    // -----------------------------------------------------------------

    public function testMsqrtRendersContent(): void
    {
        $bytes = $this->render(
            '<math xmlns="http://www.w3.org/1998/Math/MathML">'
                . '<msqrt><mn>2</mn></msqrt>'
                . '</math>',
        );
        self::assertStringStartsWith('%PDF-', $bytes);
        self::assertMatchesRegularExpression('/\(2\)\s+Tj/', $bytes);
    }

    public function testMsqrtEmitsVinculum(): void
    {
        // The vinculum is the same horizontal-rule pattern the
        // fraction bar uses.
        $bytes = $this->render(
            '<math xmlns="http://www.w3.org/1998/Math/MathML">'
                . '<msqrt><mn>2</mn></msqrt>'
                . '</math>',
        );
        $this->assertEmitsHorizontalRule($bytes);
    }

    public function testMsqrtWithMultipleChildrenRendersAllUnderVinculum(): void
    {
        $bytes = $this->render(
            '<math xmlns="http://www.w3.org/1998/Math/MathML">'
                . '<msqrt><mn>1</mn><mo>+</mo><mn>2</mn></msqrt>'
                . '</math>',
        );
        self::assertMatchesRegularExpression('/\(1\)\s+Tj/', $bytes);
        self::assertMatchesRegularExpression('/\(\+\)\s+Tj/', $bytes);
        self::assertMatchesRegularExpression('/\(2\)\s+Tj/', $bytes);
        // Still exactly one vinculum stroke (not one per child).
        self::assertSame(1, $this->countStrokes($bytes));
    }

    public function testEmptyMsqrtEmitsNoVinculum(): void
    {
        $bytes = $this->render(
            '<math xmlns="http://www.w3.org/1998/Math/MathML">'
                . '<msqrt/>'
                . '</math>',
        );
        self::assertStringStartsWith('%PDF-', $bytes);
        // Zero-width content → no vinculum to draw.
        self::assertSame(0, $this->countStrokes($bytes));
    }

    // -----------------------------------------------------------------
    // <mroot>
    // -----------------------------------------------------------------

    public function testMrootRendersBaseAndIndex(): void
    {
        // Cube root of 8 — base "8", index "3".
        $bytes = $this->render(
            '<math xmlns="http://www.w3.org/1998/Math/MathML">'
                . '<mroot><mn>8</mn><mn>3</mn></mroot>'
                . '</math>',
        );
        self::assertStringStartsWith('%PDF-', $bytes);
        self::assertMatchesRegularExpression('/\(8\)\s+Tj/', $bytes);
        self::assertMatchesRegularExpression('/\(3\)\s+Tj/', $bytes);
    }

    public function testMrootEmitsVinculumOverBaseOnly(): void
    {
        $bytes = $this->render(
            '<math xmlns="http://www.w3.org/1998/Math/MathML">'
                . '<mroot><mn>8</mn><mn>3</mn></mroot>'
                . '</math>',
        );
        // Exactly one vinculum stroke — the one over the base.
        self::assertSame(1, $this->countStrokes($bytes));
    }

    public function testMrootSwitchesFontSizeForIndex(): void
    {
        // The index renders at 0.7 × main font size. We expect at
        // least three Tf operations:
        //   1. Initial setFont (TimesRoman, 12pt) from MathmlRenderer.
        //   2. setFont(TimesRoman, 8.4) for the index.
        //   3. setFont(TimesRoman, 12pt) restore after the index.
        $bytes = $this->render(
            '<math xmlns="http://www.w3.org/1998/Math/MathML">'
                . '<mroot><mn>8</mn><mn>3</mn></mroot>'
                . '</math>',
        );
        $tfCount = preg_match_all('/\s+Tf\b/', $bytes);
        self::assertGreaterThanOrEqual(3, $tfCount);
    }

    public function testInvalidMrootFallsBackToInlineWalk(): void
    {
        // Three children — author error per Core §3.3.5 (exactly two
        // expected). Fallback walks inline; content is recovered.
        $bytes = $this->render(
            '<math xmlns="http://www.w3.org/1998/Math/MathML">'
                . '<mroot><mn>1</mn><mn>2</mn><mn>3</mn></mroot>'
                . '</math>',
        );
        self::assertStringStartsWith('%PDF-', $bytes);
        self::assertMatchesRegularExpression('/\(1\)\s+Tj/', $bytes);
        self::assertMatchesRegularExpression('/\(2\)\s+Tj/', $bytes);
        self::assertMatchesRegularExpression('/\(3\)\s+Tj/', $bytes);
    }

    public function testEmptyMrootEmitsNoVinculum(): void
    {
        $bytes = $this->render(
            '<math xmlns="http://www.w3.org/1998/Math/MathML">'
                . '<mroot/>'
                . '</math>',
        );
        self::assertStringStartsWith('%PDF-', $bytes);
        self::assertSame(0, $this->countStrokes($bytes));
    }

    // -----------------------------------------------------------------
    // Cross-cutting: nested fractions + radicals
    // -----------------------------------------------------------------

    public function testNestedMfracInMsqrtEmitsBothRules(): void
    {
        // √(1/2) — fraction inside a square root. Expect:
        //   - One vinculum stroke (from msqrt).
        //   - One fraction-bar stroke (from mfrac).
        $bytes = $this->render(
            '<math xmlns="http://www.w3.org/1998/Math/MathML">'
                . '<msqrt><mfrac><mn>1</mn><mn>2</mn></mfrac></msqrt>'
                . '</math>',
        );
        self::assertSame(2, $this->countStrokes($bytes));
    }

    public function testTextMatrixRestoredAfterBarSoNextTokenFlows(): void
    {
        // <mfrac>...</mfrac><mo>=</mo><mn>5</mn> — confirm the path
        // detour for the bar doesn't leave the pen in the wrong
        // place. Subsequent tokens should still emit at the correct
        // baseline.
        $bytes = $this->render(
            '<math xmlns="http://www.w3.org/1998/Math/MathML">'
                . '<mrow>'
                . '<mfrac><mn>1</mn><mn>2</mn></mfrac>'
                . '<mo>=</mo><mn>5</mn>'
                . '</mrow>'
                . '</math>',
        );
        self::assertMatchesRegularExpression('/\(=\)\s+Tj/', $bytes);
        self::assertMatchesRegularExpression('/\(5\)\s+Tj/', $bytes);
        // The path detour emits Tm (absolute text matrix reset) to
        // restore the cursor — confirms our escape hatch returned the
        // pen to where the next token expects it.
        self::assertMatchesRegularExpression('/\s+Tm\b/', $bytes);
    }

    // -----------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------

    private function render(string $mathmlXml): string
    {
        $writer = new PdfWriter(compressStreams: false);
        $page = $writer->addPage();
        $renderer = new MathmlRenderer($page, $writer);
        $doc = $this->parser->parse($mathmlXml);
        $renderer->draw($doc, x: 72.0, y: 600.0, width: 200.0, height: 30.0);
        return $writer->toBytes();
    }

    private function assertEmitsHorizontalRule(string $bytes): void
    {
        // Canonical pattern emitted by drawHorizontalRule:
        // ET → q → w → m → l → S → Q → BT → Tf → Tm.
        self::assertMatchesRegularExpression(
            '/\nET\n/',
            $bytes,
            'expected ET to close text block before path emission',
        );
        self::assertMatchesRegularExpression(
            '/\s+w\b/',
            $bytes,
            'expected setLineWidth before stroke',
        );
        self::assertMatchesRegularExpression(
            '/\s+m\b/',
            $bytes,
            'expected moveto before lineto',
        );
        self::assertMatchesRegularExpression(
            '/\s+l\b/',
            $bytes,
            'expected lineto for the rule',
        );
        self::assertMatchesRegularExpression(
            '/\nS\n/',
            $bytes,
            'expected stroke to draw the rule',
        );
        self::assertMatchesRegularExpression(
            '/\nBT\n/',
            $bytes,
            'expected BT to restart the text block',
        );
    }

    private function countStrokes(string $bytes): int
    {
        $count = preg_match_all('/\nS\n/', $bytes);
        return $count === false ? 0 : $count;
    }
}
