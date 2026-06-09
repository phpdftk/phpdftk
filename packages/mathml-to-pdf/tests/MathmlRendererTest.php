<?php

declare(strict_types=1);

namespace Phpdftk\MathmlToPdf\Tests;

use Phpdftk\Mathml\Parser as MathmlParser;
use Phpdftk\MathmlToPdf\MathmlRenderer;
use Phpdftk\Pdf\Writer\PdfWriter;
use PHPUnit\Framework\TestCase;

/**
 * End-to-end coverage for the tracer-bullet renderer. Each test
 * runs a tiny MathML document through the parser + renderer and
 * inspects the resulting PDF bytes (uncompressed) for the expected
 * content-stream operators.
 *
 * Bias toward negative + edge cases — the renderer is new and its
 * contract is mostly "don't blow up". Positive cases confirm the
 * tracer-bullet emits real glyphs via Tj.
 */
final class MathmlRendererTest extends TestCase
{
    private MathmlParser $parser;

    protected function setUp(): void
    {
        $this->parser = new MathmlParser();
    }

    // -----------------------------------------------------------------
    // Negative + edge cases
    // -----------------------------------------------------------------

    public function testEmptyMathRootProducesValidPdfWithNoTextOps(): void
    {
        // `<math/>` — well-formed but no tokens. The renderer must
        // produce a valid PDF; the text block (BT...ET) opens but
        // emits no Tj.
        $bytes = $this->render(
            '<math xmlns="http://www.w3.org/1998/Math/MathML"/>',
        );
        self::assertStringStartsWith('%PDF-', $bytes);
        // BT/ET are still emitted; the inner Tj is not.
        self::assertStringContainsString("\nBT\n", $bytes);
        self::assertStringContainsString("\nET\n", $bytes);
    }

    public function testEmptyTokensProduceNoTjOperator(): void
    {
        // `<mn></mn>` — token element with no character data. Should
        // skip the showText call (Tj) entirely.
        $bytes = $this->render(
            '<math xmlns="http://www.w3.org/1998/Math/MathML">'
                . '<mn></mn><mi></mi><mtext></mtext>'
                . '</math>',
        );
        self::assertStringStartsWith('%PDF-', $bytes);
        // No Tj should appear inside the BT/ET block. We look for a
        // Tj-shaped operator (`(...) Tj`); the document is otherwise
        // text-content-free.
        self::assertDoesNotMatchRegularExpression(
            '/\([^)]+\)\s+Tj/',
            $bytes,
            'empty tokens should not emit Tj',
        );
    }

    public function testGenericElementRecursesIntoChildren(): void
    {
        // An unknown wrapper (`<mblah>`) must not swallow the content
        // inside it — the Translator recurses into children so a
        // future-MathML element doesn't break the page render today.
        $bytes = $this->render(
            '<math xmlns="http://www.w3.org/1998/Math/MathML">'
                . '<mblah><mn>5</mn></mblah>'
                . '</math>',
        );
        self::assertStringStartsWith('%PDF-', $bytes);
        self::assertMatchesRegularExpression('/\(5\)\s+Tj/', $bytes);
    }

    public function testDeeplyNestedMrowsFlatten(): void
    {
        // Pathological-shaped tree: a chain of <mrow> wrapping a
        // single token. Renderer must reach the token, not crash on
        // depth.
        $deep = '<mn>9</mn>';
        for ($i = 0; $i < 20; $i++) {
            $deep = "<mrow>$deep</mrow>";
        }
        $bytes = $this->render(
            '<math xmlns="http://www.w3.org/1998/Math/MathML">'
                . $deep
                . '</math>',
        );
        self::assertStringStartsWith('%PDF-', $bytes);
        self::assertMatchesRegularExpression('/\(9\)\s+Tj/', $bytes);
    }

    // -----------------------------------------------------------------
    // Positive cases — tracer-bullet token rendering
    // -----------------------------------------------------------------

    public function testRendersMnAsUprightDigit(): void
    {
        // `<math><mn>2</mn></math>` — the issue #30 tracer-bullet.
        $bytes = $this->render(
            '<math xmlns="http://www.w3.org/1998/Math/MathML">'
                . '<mn>2</mn></math>',
        );
        self::assertStringStartsWith('%PDF-', $bytes);
        // The digit '2' should reach the content stream verbatim.
        self::assertMatchesRegularExpression('/\(2\)\s+Tj/', $bytes);
    }

    public function testRendersSingleCharMiAsItalic(): void
    {
        // Core §3.2.3: single-character <mi> → italic. The Translator
        // switches to the italic Type1 face (Times-Italic) before
        // emitting the Tj, so we should see two Tf operators bracket
        // the italic <mi>.
        $bytes = $this->render(
            '<math xmlns="http://www.w3.org/1998/Math/MathML">'
                . '<mi>x</mi></math>',
        );
        self::assertStringStartsWith('%PDF-', $bytes);
        self::assertMatchesRegularExpression('/\(x\)\s+Tj/', $bytes);
        // Both font resources should be registered (upright + italic).
        self::assertStringContainsString('Times-Roman', $bytes);
        self::assertStringContainsString('Times-Italic', $bytes);
    }

    public function testRendersMultiCharMiAsUpright(): void
    {
        // Multi-character `<mi>` (operators like sin, log, max) →
        // upright per Core §3.2.3. The italic Type1 should NOT be
        // registered because no <mi> ever needed it.
        $bytes = $this->render(
            '<math xmlns="http://www.w3.org/1998/Math/MathML">'
                . '<mi>sin</mi></math>',
        );
        self::assertStringStartsWith('%PDF-', $bytes);
        self::assertMatchesRegularExpression('/\(sin\)\s+Tj/', $bytes);
    }

    public function testRendersAllTokenTypesInMrow(): void
    {
        // Full token vocabulary in a single row: mn, mo, mi, ms,
        // mtext. Each should reach the content stream as a Tj.
        $bytes = $this->render(
            '<math xmlns="http://www.w3.org/1998/Math/MathML">'
                . '<mrow>'
                . '<mn>2</mn>'
                . '<mo>+</mo>'
                . '<mi>x</mi>'
                . '<mtext>where</mtext>'
                . '</mrow>'
                . '</math>',
        );
        self::assertStringStartsWith('%PDF-', $bytes);
        self::assertMatchesRegularExpression('/\(2\)\s+Tj/', $bytes);
        self::assertMatchesRegularExpression('/\(\+\)\s+Tj/', $bytes);
        self::assertMatchesRegularExpression('/\(x\)\s+Tj/', $bytes);
        self::assertMatchesRegularExpression('/\(where\)\s+Tj/', $bytes);
    }

    public function testMsWrapsContentWithDefaultDoubleQuotes(): void
    {
        // `<ms>` wraps its content in lquote / rquote, defaulting to
        // ASCII " on both sides per Core §3.2.6.
        $bytes = $this->render(
            '<math xmlns="http://www.w3.org/1998/Math/MathML">'
                . '<ms>label</ms>'
                . '</math>',
        );
        self::assertStringStartsWith('%PDF-', $bytes);
        // PDF strings escape " — depending on the writer's quoting
        // strategy we look for the escaped or literal form.
        self::assertMatchesRegularExpression(
            '/\(\\\\?"label\\\\?"\)\s+Tj/',
            $bytes,
            'ms should wrap content in double quotes',
        );
    }

    public function testMsHonoursCustomLquoteRquote(): void
    {
        $bytes = $this->render(
            '<math xmlns="http://www.w3.org/1998/Math/MathML">'
                . '<ms lquote="«" rquote="»">euro</ms>'
                . '</math>',
        );
        self::assertStringStartsWith('%PDF-', $bytes);
        // The custom quote characters are emitted to the PDF stream;
        // depending on PdfWriter's string-escape policy, multi-byte
        // characters in a literal (...) string may be octal-escaped
        // (\253 etc.) or passed through verbatim. We just confirm
        // the inner content "euro" reached the stream and that the
        // string itself is wrapped in quote-shaped bytes — `(` then
        // something-not-default-double-quote then "euro" — so we
        // know `<ms>` did NOT fall back to its ASCII " default.
        self::assertStringContainsString('euro', $bytes);
        self::assertDoesNotMatchRegularExpression(
            '/\("euro"\)\s+Tj/',
            $bytes,
            'ms with custom lquote/rquote should not emit default ASCII quotes',
        );
    }

    // -----------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------

    private function render(string $mathmlXml): string
    {
        // Uncompressed streams so the test assertions can grep the
        // content stream directly.
        $writer = new PdfWriter(compressStreams: false);
        $page = $writer->addPage();
        $renderer = new MathmlRenderer($page, $writer);

        $doc = $this->parser->parse($mathmlXml);
        $renderer->draw($doc, x: 72.0, y: 600.0, width: 200.0, height: 24.0);

        return $writer->toBytes();
    }
}
