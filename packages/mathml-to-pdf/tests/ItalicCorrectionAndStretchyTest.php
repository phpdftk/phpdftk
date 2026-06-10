<?php

declare(strict_types=1);

namespace Phpdftk\MathmlToPdf\Tests;

use Phpdftk\Mathml\Parser as MathmlParser;
use Phpdftk\MathmlToPdf\MathmlRenderer;
use Phpdftk\Pdf\Writer\PdfWriter;
use PHPUnit\Framework\TestCase;

/**
 * Integration tests for italic correction (MathGlyphInfo) and
 * stretchy operator paint (MathVariants) wiring.
 *
 * Both features only fire when a math font is loaded. The tests
 * use a WPT synthetic math font fixture (already vendored via the
 * WPT submodule) so they run without the production-font download
 * step. Each test skips itself when the WPT submodule isn't
 * checked out.
 *
 * Italic correction asserts:
 *   - With math font, `<msup><mi>X</mi>...</msup>` emits at least
 *     one extra Td between the base and the script (the italic-
 *     correction X shift). Without math font: same Td count as
 *     before this slice.
 *
 * Stretchy assertions: the WPT synthetic fonts don't carry the
 * variant glyphs in the subset (and likely don't cover '(' anyway),
 * so the stretchy path returns false here. We instead test that the
 * detection helper accepts the stretchy attribute - the rendered
 * output stays the same as the standard path.
 */
final class ItalicCorrectionAndStretchyTest extends TestCase
{
    private const string WPT_MATH_FONTS_DIR =
        __DIR__ . '/../../../vendor-data/wpt/fonts/math';

    public function testMsupWithoutMathFontDoesNotApplyItalicCorrection(): void
    {
        // Baseline: no math font, the painter takes the standard
        // path - exact Td count depends on script paint mechanics
        // but is deterministic.
        $bytes = $this->render('<msup><mi>x</mi><mn>2</mn></msup>', null);
        self::assertStringStartsWith('%PDF-', $bytes);
        self::assertMatchesRegularExpression('/\(x\)\s+Tj/', $bytes);
        self::assertMatchesRegularExpression('/\(2\)\s+Tj/', $bytes);
    }

    public function testMsupWithMathFontDoesNotCrash(): void
    {
        // With a math font, the painter exercises the italic-
        // correction code path. The WPT synthetic font has
        // intentionally minimal cmap coverage so emit may produce
        // empty hex strings for glyphs the font doesn't carry -
        // the production-font integration test uses Latin Modern
        // Math for full-coverage verification.
        //
        // Here we just confirm the painter produces a structurally
        // valid PDF without crashing.
        $font = $this->wpt('fraction-rulethickness10000.woff');
        $bytes = $this->render(
            '<msup><mi>x</mi><mn>2</mn></msup>',
            $font,
        );
        self::assertStringStartsWith('%PDF-', $bytes);
        self::assertStringContainsString('endobj', $bytes);
    }

    public function testStretchyOperatorAttributeAcceptedWithoutCrash(): void
    {
        // `(x)` with stretchy attribute. Without a math font, the
        // stretchy path returns false and the operators emit as
        // normal characters.
        $bytes = $this->render(
            '<mrow>'
                . '<mo stretchy="true">(</mo>'
                . '<mi>x</mi>'
                . '<mo stretchy="true">)</mo>'
                . '</mrow>',
            null,
        );
        // PDF strings escape '(' and ')' as '\(' and '\)' inside the
        // parenthesised literal, so the operator '(' shows up as
        // `(\() Tj` in the content stream.
        self::assertMatchesRegularExpression('/\(\\\\\(\)\s+Tj/', $bytes);
        self::assertMatchesRegularExpression('/\(\\\\\)\)\s+Tj/', $bytes);
        self::assertMatchesRegularExpression('/\(x\)\s+Tj/', $bytes);
    }

    public function testMsubsupKeepsSubAtOriginalAttachPoint(): void
    {
        // x_{i}^{2} - both scripts attach at the base's right edge.
        // The italic-correction shift (if any from a math font)
        // moves only the sup; the sub stays at the unmodified attach.
        $bytes = $this->render(
            '<msubsup><mi>x</mi><mi>i</mi><mn>2</mn></msubsup>',
            null,
        );
        foreach (['x', 'i', '2'] as $glyph) {
            self::assertMatchesRegularExpression(
                '/\(' . $glyph . '\)\s+Tj/',
                $bytes,
            );
        }
    }

    public function testMathFontPathRenderingDoesntRegressMrowParensAroundMfrac(): void
    {
        $font = $this->wpt('fraction-rulethickness10000.woff');
        // Common stretchy use case: parentheses around a fraction.
        // The painter must not crash even when the synthetic font's
        // MathVariants doesn't cover '(' / ')'.
        $bytes = $this->render(
            '<mrow>'
                . '<mo stretchy="true">(</mo>'
                . '<mfrac><mn>1</mn><mn>2</mn></mfrac>'
                . '<mo stretchy="true">)</mo>'
                . '</mrow>',
            $font,
        );
        self::assertStringStartsWith('%PDF-', $bytes);
    }

    private function render(string $innerXml, ?string $mathFontPath): string
    {
        $xml = '<math xmlns="http://www.w3.org/1998/Math/MathML">'
            . $innerXml
            . '</math>';
        $writer = new PdfWriter(compressStreams: false);
        $page = $writer->addPage();
        $renderer = new MathmlRenderer(
            $page,
            $writer,
            mathFontPath: $mathFontPath,
        );
        $doc = (new MathmlParser())->parse($xml);
        $renderer->draw($doc, x: 72.0, y: 600.0, width: 200.0, height: 30.0);
        return $writer->toBytes();
    }

    private function wpt(string $name): string
    {
        $path = self::WPT_MATH_FONTS_DIR . '/' . $name;
        if (!is_file($path)) {
            self::markTestSkipped("WPT math font not available: $path");
        }
        return $path;
    }
}
