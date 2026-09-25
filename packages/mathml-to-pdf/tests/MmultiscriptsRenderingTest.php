<?php

declare(strict_types=1);

namespace Phpdftk\MathmlToPdf\Tests;

use Phpdftk\Mathml\Parser as MathmlParser;
use Phpdftk\MathmlToPdf\MathmlRenderer;
use Phpdftk\Pdf\Writer\PdfWriter;
use PHPUnit\Framework\TestCase;

/**
 * Renderer coverage for `<mmultiscripts>` — arbitrary numbers of
 * pre/post script pairs, `<none/>` placeholders for absent slots,
 * mixed structures.
 *
 * Assertions are structural (Tj presence, Tf count, fallback
 * behaviour) rather than literal coordinates — the positioning math
 * is an implementation detail.
 */
final class MmultiscriptsRenderingTest extends TestCase
{
    private MathmlParser $parser;

    protected function setUp(): void
    {
        $this->parser = new MathmlParser();
    }

    public function testSinglePostScriptPairRendersBothScripts(): void
    {
        // Equivalent to msubsup: X with sub=1, sup=2.
        $bytes = $this->render(
            '<math xmlns="http://www.w3.org/1998/Math/MathML">'
                . '<mmultiscripts>'
                . '<mi>X</mi>'
                . '<mn>1</mn><mn>2</mn>'
                . '</mmultiscripts>'
                . '</math>',
        );
        self::assertStringStartsWith('%PDF-', $bytes);
        self::assertMatchesRegularExpression('/\(X\)\s+Tj/', $bytes);
        self::assertMatchesRegularExpression('/\(1\)\s+Tj/', $bytes);
        self::assertMatchesRegularExpression('/\(2\)\s+Tj/', $bytes);
    }

    public function testMultiplePostScriptPairsStack(): void
    {
        // R^{km}_{jl} — Christoffel-symbol-like with two postscript
        // pairs.
        $bytes = $this->render(
            '<math xmlns="http://www.w3.org/1998/Math/MathML">'
                . '<mmultiscripts>'
                . '<mi>R</mi>'
                . '<mi>j</mi><mi>k</mi>'
                . '<mi>l</mi><mi>m</mi>'
                . '</mmultiscripts>'
                . '</math>',
        );
        self::assertStringStartsWith('%PDF-', $bytes);
        foreach (['R', 'j', 'k', 'l', 'm'] as $glyph) {
            self::assertMatchesRegularExpression(
                '/\(' . preg_quote($glyph, '/') . '\)\s+Tj/',
                $bytes,
                "expected glyph '$glyph' in stream",
            );
        }
    }

    public function testPrescriptOnlyRendersOnLeftOfBase(): void
    {
        // Prescript-only structure: presub=3, presup=4 in front of X.
        $bytes = $this->render(
            '<math xmlns="http://www.w3.org/1998/Math/MathML">'
                . '<mmultiscripts>'
                . '<mi>X</mi>'
                . '<mprescripts/>'
                . '<mn>3</mn><mn>4</mn>'
                . '</mmultiscripts>'
                . '</math>',
        );
        self::assertStringStartsWith('%PDF-', $bytes);
        self::assertMatchesRegularExpression('/\(X\)\s+Tj/', $bytes);
        self::assertMatchesRegularExpression('/\(3\)\s+Tj/', $bytes);
        self::assertMatchesRegularExpression('/\(4\)\s+Tj/', $bytes);
    }

    public function testCombinedPreAndPostScriptsAllAppear(): void
    {
        // Both prescripts and postscripts.
        $bytes = $this->render(
            '<math xmlns="http://www.w3.org/1998/Math/MathML">'
                . '<mmultiscripts>'
                . '<mi>X</mi>'
                . '<mn>1</mn><mn>2</mn>'
                . '<mprescripts/>'
                . '<mn>3</mn><mn>4</mn>'
                . '</mmultiscripts>'
                . '</math>',
        );
        foreach (['X', '1', '2', '3', '4'] as $glyph) {
            self::assertMatchesRegularExpression(
                '/\(' . $glyph . '\)\s+Tj/',
                $bytes,
            );
        }
    }

    public function testNoneSlotsSkipScriptEmission(): void
    {
        // Sub-only pair: <mn>1</mn><none/> — the '1' renders but the
        // sup slot contributes nothing. Confirms the painter's
        // NoneElement skip path.
        $bytes = $this->render(
            '<math xmlns="http://www.w3.org/1998/Math/MathML">'
                . '<mmultiscripts>'
                . '<mi>X</mi>'
                . '<mn>1</mn><none/>'
                . '</mmultiscripts>'
                . '</math>',
        );
        self::assertMatchesRegularExpression('/\(X\)\s+Tj/', $bytes);
        self::assertMatchesRegularExpression('/\(1\)\s+Tj/', $bytes);
    }

    public function testBothNoneSlotsCollapsePairToZeroWidth(): void
    {
        // A degenerate pair (<none/><none/>) is a no-op. The base
        // and any non-degenerate pairs still render.
        $bytes = $this->render(
            '<math xmlns="http://www.w3.org/1998/Math/MathML">'
                . '<mmultiscripts>'
                . '<mi>X</mi>'
                . '<none/><none/>'
                . '<mn>1</mn><mn>2</mn>'
                . '</mmultiscripts>'
                . '</math>',
        );
        self::assertMatchesRegularExpression('/\(X\)\s+Tj/', $bytes);
        self::assertMatchesRegularExpression('/\(1\)\s+Tj/', $bytes);
        self::assertMatchesRegularExpression('/\(2\)\s+Tj/', $bytes);
    }

    public function testBaseOnlyMmultiscriptsRendersJustBase(): void
    {
        // No scripts at all — equivalent to just rendering the base
        // inline.
        $bytes = $this->render(
            '<math xmlns="http://www.w3.org/1998/Math/MathML">'
                . '<mmultiscripts><mi>X</mi></mmultiscripts>'
                . '</math>',
        );
        self::assertStringStartsWith('%PDF-', $bytes);
        self::assertMatchesRegularExpression('/\(X\)\s+Tj/', $bytes);
    }

    public function testEmptyMmultiscriptsEmitsNoTokens(): void
    {
        // No children at all — degenerate. The painter returns
        // without emitting glyphs.
        $bytes = $this->render(
            '<math xmlns="http://www.w3.org/1998/Math/MathML">'
                . '<mmultiscripts/>'
                . '</math>',
        );
        self::assertStringStartsWith('%PDF-', $bytes);
        self::assertDoesNotMatchRegularExpression('/\([^)]+\)\s+Tj/', $bytes);
    }

    public function testOddPostScriptCountFallsBackToInlineWalk(): void
    {
        // X plus one orphan postsubscript (no matching sup) — author
        // error per Core §3.3.6.2. Fallback walks children inline so
        // content is recovered, not dropped.
        $bytes = $this->render(
            '<math xmlns="http://www.w3.org/1998/Math/MathML">'
                . '<mmultiscripts>'
                . '<mi>X</mi>'
                . '<mn>1</mn>'
                . '</mmultiscripts>'
                . '</math>',
        );
        self::assertMatchesRegularExpression('/\(X\)\s+Tj/', $bytes);
        self::assertMatchesRegularExpression('/\(1\)\s+Tj/', $bytes);
    }

    public function testOddPreScriptCountFallsBackToInlineWalk(): void
    {
        $bytes = $this->render(
            '<math xmlns="http://www.w3.org/1998/Math/MathML">'
                . '<mmultiscripts>'
                . '<mi>X</mi>'
                . '<mprescripts/>'
                . '<mn>3</mn>'
                . '</mmultiscripts>'
                . '</math>',
        );
        self::assertMatchesRegularExpression('/\(X\)\s+Tj/', $bytes);
        self::assertMatchesRegularExpression('/\(3\)\s+Tj/', $bytes);
    }

    public function testScriptsRenderInSmallerFont(): void
    {
        // Scripts use 0.7× the main font size — confirm via the
        // count of Tf font-set operators. With 2 postscript pairs
        // (each having both sub and sup, four total scripts), expect
        // many Tf swaps.
        $bytes = $this->render(
            '<math xmlns="http://www.w3.org/1998/Math/MathML">'
                . '<mmultiscripts>'
                . '<mi>X</mi>'
                . '<mn>1</mn><mn>2</mn>'
                . '<mn>3</mn><mn>4</mn>'
                . '</mmultiscripts>'
                . '</math>',
        );
        self::assertGreaterThanOrEqual(5, preg_match_all('/\s+Tf\b/', $bytes));
    }

    public function testNestedMmultiscriptsInsideOtherConstructs(): void
    {
        // mmultiscripts inside a fraction. The recursion through
        // paint() composes cleanly.
        $bytes = $this->render(
            '<math xmlns="http://www.w3.org/1998/Math/MathML">'
                . '<mfrac>'
                . '<mmultiscripts><mi>X</mi><mn>1</mn><mn>2</mn></mmultiscripts>'
                . '<mn>3</mn>'
                . '</mfrac>'
                . '</math>',
        );
        self::assertMatchesRegularExpression('/\(X\)\s+Tj/', $bytes);
        self::assertMatchesRegularExpression('/\(1\)\s+Tj/', $bytes);
        self::assertMatchesRegularExpression('/\(2\)\s+Tj/', $bytes);
        self::assertMatchesRegularExpression('/\(3\)\s+Tj/', $bytes);
        // Fraction bar still drawn.
        self::assertMatchesRegularExpression('/\nS\n/', $bytes);
    }

    public function testFollowingTokenFlowsPastConstruct(): void
    {
        // `<mmultiscripts>X¹₂</mmultiscripts>=Y` — confirm the post-
        // construct cursor advances enough that the trailing tokens
        // don't overlap.
        $bytes = $this->render(
            '<math xmlns="http://www.w3.org/1998/Math/MathML">'
                . '<mrow>'
                . '<mmultiscripts><mi>X</mi><mn>1</mn><mn>2</mn></mmultiscripts>'
                . '<mo>=</mo><mi>Y</mi>'
                . '</mrow>'
                . '</math>',
        );
        self::assertMatchesRegularExpression('/\(=\)\s+Tj/', $bytes);
        self::assertMatchesRegularExpression('/\(Y\)\s+Tj/', $bytes);
    }

    // ---------------------------------------------------------------
    // MathML Core §3.4.7 — each script column is
    // max(subWidth, supWidth) wide; POSTscripts align on its
    // inline-start edge, PREscripts on its inline-end edge, so both
    // hug the base (mmultiscript-003).
    // ---------------------------------------------------------------

    /**
     * A narrow script paired with a wide one, so the column has slack
     * to distribute. `<mi>W</mi>` is wider than `<mi>i</mi>` in
     * Times.
     */
    private const string NARROW_OVER_WIDE = '<mi>i</mi><mi>W</mi>';

    public function testPostscriptsAlignOnTheColumnsInlineStartEdge(): void
    {
        $bytes = $this->render(
            '<math xmlns="http://www.w3.org/1998/Math/MathML">'
                . '<mmultiscripts><mi>X</mi>' . self::NARROW_OVER_WIDE
                . '</mmultiscripts></math>',
        );
        // Both scripts start at the same x: the column's left edge.
        self::assertEqualsWithDelta(
            $this->glyphX($bytes, 'i'),
            $this->glyphX($bytes, 'W'),
            0.01,
        );
    }

    public function testPrescriptsAlignOnTheColumnsInlineEndEdge(): void
    {
        $bytes = $this->render(
            '<math xmlns="http://www.w3.org/1998/Math/MathML">'
                . '<mmultiscripts><mi>X</mi><mprescripts/>'
                . self::NARROW_OVER_WIDE
                . '</mmultiscripts></math>',
        );
        // The narrow script is pushed right by the column slack, so
        // it starts LATER than the wide one — and both END together.
        self::assertGreaterThan(
            $this->glyphX($bytes, 'W'),
            $this->glyphX($bytes, 'i'),
            'the narrow prescript must be pushed toward the base',
        );
    }

    /**
     * Negative guard: when the two scripts are the SAME width there
     * is no column slack, so the inline-end alignment must be a
     * no-op — sub and sup still share an x in both modes. An
     * alignment that shifted equal-width columns would show here.
     */
    public function testEqualWidthScriptsAreUnaffectedByTheAlignment(): void
    {
        foreach (['<mi>W</mi><mi>W</mi>', '<mprescripts/><mi>W</mi><mi>W</mi>'] as $scripts) {
            $bytes = $this->render(
                '<math xmlns="http://www.w3.org/1998/Math/MathML">'
                    . '<mmultiscripts><mi>X</mi>' . $scripts
                    . '</mmultiscripts></math>',
            );
            $positions = $this->glyphXs($bytes, 'W');
            self::assertCount(2, $positions);
            self::assertEqualsWithDelta($positions[0], $positions[1], 0.01);
        }
    }

    /**
     * Prescripts must still leave the cursor at the column's right
     * edge, so the base lands after the full column width whichever
     * script is the wide one.
     */
    public function testPrescriptColumnStillAdvancesByTheFullColumnWidth(): void
    {
        $wideSub = $this->render(
            '<math xmlns="http://www.w3.org/1998/Math/MathML">'
                . '<mmultiscripts><mi>X</mi><mprescripts/><mi>W</mi><mi>i</mi>'
                . '</mmultiscripts></math>',
        );
        $wideSup = $this->render(
            '<math xmlns="http://www.w3.org/1998/Math/MathML">'
                . '<mmultiscripts><mi>X</mi><mprescripts/><mi>i</mi><mi>W</mi>'
                . '</mmultiscripts></math>',
        );
        self::assertEqualsWithDelta(
            $this->glyphX($wideSub, 'X'),
            $this->glyphX($wideSup, 'X'),
            0.01,
            'the base must sit after one full column either way',
        );
    }

    private function glyphX(string $bytes, string $glyph): float
    {
        $pattern = '/1 0 0 1 (-?[\d.]+) -?[\d.]+ Tm\s*(?:\/F\d+ [\d.]+ Tf\s*)*\('
            . preg_quote($glyph, '/') . '\)\s+Tj/';
        self::assertMatchesRegularExpression($pattern, $bytes);
        preg_match($pattern, $bytes, $m);
        return (float) $m[1];
    }

    /**
     * Every x at which `$glyph` is shown, in stream order.
     *
     * @return list<float>
     */
    private function glyphXs(string $bytes, string $glyph): array
    {
        $pattern = '/1 0 0 1 (-?[\d.]+) -?[\d.]+ Tm\s*(?:\/F\d+ [\d.]+ Tf\s*)*\('
            . preg_quote($glyph, '/') . '\)\s+Tj/';
        preg_match_all($pattern, $bytes, $matches);
        return array_map(static fn(string $x): float => (float) $x, $matches[1]);
    }

    private function render(string $mathmlXml): string
    {
        $writer = new PdfWriter(compressStreams: false);
        $page = $writer->addPage();
        $renderer = new MathmlRenderer($page, $writer);
        $doc = $this->parser->parse($mathmlXml);
        $renderer->draw($doc, x: 72.0, y: 600.0, width: 200.0, height: 30.0);
        return $writer->toBytes();
    }
}
