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

    public function testMalformedInlineMathSkipsRatherThanCrashes(): void
    {
        // An <math> with a child that the MathML parser would still
        // accept (parser falls back to GenericElement) — the render
        // must produce a valid PDF either way.
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
        // mfrac isn't a typed class yet — round-trips through
        // GenericElement, whose paint walks children. So we still
        // see the '1' and '2' tokens emitted.
        self::assertMatchesRegularExpression('/\(1\)\s+Tj/', $bytes);
        self::assertMatchesRegularExpression('/\(2\)\s+Tj/', $bytes);
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
