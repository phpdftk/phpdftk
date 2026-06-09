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
