<?php

declare(strict_types=1);

namespace Phpdftk\HtmlToPdf\Tests\Mathml;

use Phpdftk\HtmlToPdf\Renderer;
use Phpdftk\Pdf\Writer\PdfWriter;
use PHPUnit\Framework\TestCase;

/**
 * End-to-end coverage for inline `<math>` rendering inside an HTML
 * document. Drives the full Renderer pipeline (HTML parse → cascade
 * → box tree → layout → paint) with HTML that contains inline
 * MathML, then asserts the resulting PDF actually carries the
 * MathML content. Before the InlineMathmlAdapter + Painter routing
 * landed, the MathML subtree was silently dropped (text leaked
 * through the parser into the DOM but no painter knew what to do
 * with it).
 *
 * Tests use `compressStreams: false` so the assertions can grep the
 * content stream for the PDF operators the MathmlRenderer emits.
 */
final class InlineMathmlIntegrationTest extends TestCase
{
    public function testInlineMnRendersTheDigit(): void
    {
        // Issue #30's tracer-bullet: <math><mn>2</mn></math>. The
        // MathmlRenderer emits one `Tj` with the digit '2' inside.
        $writer = new PdfWriter(compressStreams: false);
        (new Renderer())->renderInto(
            $writer,
            <<<HTML
            <html><body>
              <math xmlns="http://www.w3.org/1998/Math/MathML">
                <mn>2</mn>
              </math>
            </body></html>
            HTML,
        );
        $bytes = $writer->toBytes();

        self::assertStringStartsWith('%PDF-', $bytes);
        // The digit '2' reaches the content stream via Tj.
        self::assertMatchesRegularExpression('/\(2\)\s+Tj/', $bytes);
    }

    public function testInlineMrowRendersAllTokens(): void
    {
        // All five token types in a row — confirms the Translator
        // walks <mrow> children and renders each token.
        $writer = new PdfWriter(compressStreams: false);
        (new Renderer())->renderInto(
            $writer,
            <<<HTML
            <html><body>
              <math xmlns="http://www.w3.org/1998/Math/MathML">
                <mrow>
                  <mn>2</mn>
                  <mo>+</mo>
                  <mi>x</mi>
                  <mtext>where</mtext>
                </mrow>
              </math>
            </body></html>
            HTML,
        );
        $bytes = $writer->toBytes();
        self::assertStringStartsWith('%PDF-', $bytes);
        self::assertMatchesRegularExpression('/\(2\)\s+Tj/', $bytes);
        self::assertMatchesRegularExpression('/\(\+\)\s+Tj/', $bytes);
        self::assertMatchesRegularExpression('/\(x\)\s+Tj/', $bytes);
        self::assertMatchesRegularExpression('/\(where\)\s+Tj/', $bytes);
    }

    public function testSingleCharMiRendersItalic(): void
    {
        // Core §3.2.3 — single-character <mi> switches to italic.
        // The italic Type1 face is registered on the page.
        $writer = new PdfWriter(compressStreams: false);
        (new Renderer())->renderInto(
            $writer,
            <<<HTML
            <html><body>
              <math xmlns="http://www.w3.org/1998/Math/MathML">
                <mi>x</mi>
              </math>
            </body></html>
            HTML,
        );
        $bytes = $writer->toBytes();
        self::assertStringStartsWith('%PDF-', $bytes);
        self::assertStringContainsString('Times-Italic', $bytes);
        self::assertMatchesRegularExpression('/\(x\)\s+Tj/', $bytes);
    }

    public function testInlineMphantomReservesSpaceWithoutGlyphs(): void
    {
        // <mphantom> inside a fraction - classic alignment trick.
        // The phantom XXX reserves bar width without rendering, the
        // visible numerator 1 still emits, and the bar still draws.
        $writer = new PdfWriter(compressStreams: false);
        (new Renderer())->renderInto(
            $writer,
            <<<HTML
            <html><body>
              <math xmlns="http://www.w3.org/1998/Math/MathML">
                <mfrac>
                  <mrow><mphantom><mi>XXX</mi></mphantom><mn>1</mn></mrow>
                  <mn>2</mn>
                </mfrac>
              </math>
            </body></html>
            HTML,
        );
        $bytes = $writer->toBytes();
        self::assertStringStartsWith('%PDF-', $bytes);
        self::assertMatchesRegularExpression('/\(1\)\s+Tj/', $bytes);
        self::assertMatchesRegularExpression('/\(2\)\s+Tj/', $bytes);
        self::assertDoesNotMatchRegularExpression('/\(XXX\)\s+Tj/', $bytes);
    }

    public function testInlineMtableMatrixRenders(): void
    {
        // 2×2 matrix through the full HTML pipeline. All four cells
        // reach the content stream.
        $writer = new PdfWriter(compressStreams: false);
        (new Renderer())->renderInto(
            $writer,
            <<<HTML
            <html><body>
              <math xmlns="http://www.w3.org/1998/Math/MathML">
                <mtable>
                  <mtr><mtd><mn>1</mn></mtd><mtd><mn>2</mn></mtd></mtr>
                  <mtr><mtd><mn>3</mn></mtd><mtd><mn>4</mn></mtd></mtr>
                </mtable>
              </math>
            </body></html>
            HTML,
        );
        $bytes = $writer->toBytes();
        self::assertStringStartsWith('%PDF-', $bytes);
        foreach (['1', '2', '3', '4'] as $glyph) {
            self::assertMatchesRegularExpression(
                '/\(' . $glyph . '\)\s+Tj/',
                $bytes,
                "expected '$glyph' in stream",
            );
        }
    }

    public function testInlineMmultiscriptsChristoffelStructureRenders(): void
    {
        // ᵏΓᵢⱼ-style multi-script structure through the full HTML
        // pipeline. Prescripts on the left of base, postscripts on
        // the right. With NoneElement for absent slots.
        $writer = new PdfWriter(compressStreams: false);
        (new Renderer())->renderInto(
            $writer,
            <<<HTML
            <html><body>
              <math xmlns="http://www.w3.org/1998/Math/MathML">
                <mmultiscripts>
                  <mi>R</mi>
                  <mi>j</mi><mi>k</mi>
                  <mprescripts/>
                  <none/><mi>l</mi>
                </mmultiscripts>
              </math>
            </body></html>
            HTML,
        );
        $bytes = $writer->toBytes();
        self::assertStringStartsWith('%PDF-', $bytes);
        // Base + post-sub + post-sup + pre-sup all reach the stream.
        foreach (['R', 'j', 'k', 'l'] as $glyph) {
            self::assertMatchesRegularExpression(
                '/\(' . $glyph . '\)\s+Tj/',
                $bytes,
                "expected '$glyph' in stream",
            );
        }
    }

    public function testInlineMsupRendersXSquared(): void
    {
        // x² — the canonical superscript case, end-to-end through
        // the HTML pipeline.
        $writer = new PdfWriter(compressStreams: false);
        (new Renderer())->renderInto(
            $writer,
            <<<HTML
            <html><body>
              <math xmlns="http://www.w3.org/1998/Math/MathML">
                <msup><mi>x</mi><mn>2</mn></msup>
              </math>
            </body></html>
            HTML,
        );
        $bytes = $writer->toBytes();
        self::assertStringStartsWith('%PDF-', $bytes);
        self::assertMatchesRegularExpression('/\(x\)\s+Tj/', $bytes);
        self::assertMatchesRegularExpression('/\(2\)\s+Tj/', $bytes);
        // The superscript uses a smaller font — confirm via multiple
        // Tf calls.
        self::assertGreaterThanOrEqual(3, preg_match_all('/\s+Tf\b/', $bytes));
    }

    public function testInlineMfracRendersNumeratorAndDenominator(): void
    {
        // <mfrac> is now a typed class with vertical-stacking paint.
        // Numerator + denominator both reach the content stream and
        // the Translator emits >= 3 Td operations (centred numerator,
        // shift to centred denominator, advance to fraction right).
        $writer = new PdfWriter(compressStreams: false);
        (new Renderer())->renderInto(
            $writer,
            <<<HTML
            <html><body>
              <math xmlns="http://www.w3.org/1998/Math/MathML">
                <mfrac>
                  <mn>1</mn>
                  <mn>2</mn>
                </mfrac>
              </math>
            </body></html>
            HTML,
        );
        $bytes = $writer->toBytes();
        self::assertStringStartsWith('%PDF-', $bytes);
        self::assertMatchesRegularExpression('/\(1\)\s+Tj/', $bytes);
        self::assertMatchesRegularExpression('/\(2\)\s+Tj/', $bytes);
        // Confirm vertical stacking happened — the Td operation count
        // is the canary that the Translator did its mfrac-specific
        // repositioning rather than walking children inline.
        $tdCount = preg_match_all('/\s+Td\b/', $bytes);
        self::assertGreaterThanOrEqual(3, $tdCount);
    }

    public function testMultipleInlineMathsRenderIndependently(): void
    {
        // Two distinct <math> elements in one document. The adapter's
        // identity cache should NOT conflate them.
        $writer = new PdfWriter(compressStreams: false);
        (new Renderer())->renderInto(
            $writer,
            <<<HTML
            <html><body>
              <math xmlns="http://www.w3.org/1998/Math/MathML">
                <mn>1</mn>
              </math>
              <math xmlns="http://www.w3.org/1998/Math/MathML">
                <mn>2</mn>
              </math>
            </body></html>
            HTML,
        );
        $bytes = $writer->toBytes();
        self::assertStringStartsWith('%PDF-', $bytes);
        self::assertMatchesRegularExpression('/\(1\)\s+Tj/', $bytes);
        self::assertMatchesRegularExpression('/\(2\)\s+Tj/', $bytes);
    }

    public function testInlineSvgAndInlineMathmlCoexistInOneDocument(): void
    {
        // Sanity: SVG and MathML both render in the same document.
        // The Painter's namespace dispatch routes each to the right
        // adapter; the adapters have separate identity caches so
        // they don't collide.
        $writer = new PdfWriter(compressStreams: false);
        (new Renderer())->renderInto(
            $writer,
            <<<HTML
            <html><body>
              <svg width="40" height="40" xmlns="http://www.w3.org/2000/svg">
                <rect width="40" height="40" fill="#ff0000"/>
              </svg>
              <math xmlns="http://www.w3.org/1998/Math/MathML">
                <mn>3</mn>
              </math>
            </body></html>
            HTML,
        );
        $bytes = $writer->toBytes();
        self::assertStringStartsWith('%PDF-', $bytes);
        // Red fill from the SVG rect.
        self::assertMatchesRegularExpression(
            '/\b1(?:\.0+)?\s+0(?:\.0+)?\s+0(?:\.0+)?\s+rg\b/',
            $bytes,
        );
        // The digit '3' from the MathML <mn>.
        self::assertMatchesRegularExpression('/\(3\)\s+Tj/', $bytes);
    }
}
