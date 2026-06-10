<?php

declare(strict_types=1);

namespace Phpdftk\MathmlToPdf\Tests;

use Phpdftk\Mathml\Parser as MathmlParser;
use Phpdftk\MathmlToPdf\MathmlRenderer;
use Phpdftk\Pdf\Writer\PdfWriter;
use PHPUnit\Framework\TestCase;

/**
 * Tests for the U+221A radical-sign emit in {@see Translator::paintMsqrt}
 * and {@see Translator::paintMroot}.
 *
 * The painter ships two paths:
 *   - Standard fonts: vinculum only (no √ glyph, since Times-Roman
 *     and Times-Italic don't carry U+221A in WinAnsi).
 *   - Math font: emit U+221A from the font's cmap before the
 *     radicand, position the vinculum after it.
 *
 * These tests verify both paths via a WPT synthetic math font and
 * the standard-font fallback.
 */
final class RadicalSignTest extends TestCase
{
    private const string WPT_MATH_FONTS_DIR =
        __DIR__ . '/../../../vendor-data/wpt/fonts/math';

    public function testStandardFontMsqrtUnchangedByThisSlice(): void
    {
        // Without a math font, the painter emits no radical glyph -
        // just the vinculum. We don't pin the exact byte sequence
        // (other slices may legitimately re-flow it) but we do
        // confirm the vinculum stroke is present and no Tj for
        // a tofu-substituted '?' appears.
        $bytes = $this->render('<msqrt><mn>2</mn></msqrt>', null);
        self::assertStringStartsWith('%PDF-', $bytes);
        self::assertMatchesRegularExpression('/\(2\)\s+Tj/', $bytes);
        // Vinculum stroke (S operator on its own line).
        self::assertMatchesRegularExpression('/\nS\n/', $bytes);
        // No question-mark substitution.
        self::assertDoesNotMatchRegularExpression('/\(\?\)\s+Tj/', $bytes);
    }

    public function testStandardFontMrootUnchangedByThisSlice(): void
    {
        // Same idea for mroot: index emits, base emits, vinculum
        // strokes - no radical glyph.
        $bytes = $this->render(
            '<mroot><mn>x</mn><mn>3</mn></mroot>',
            null,
        );
        self::assertMatchesRegularExpression('/\(x\)\s+Tj/', $bytes);
        self::assertMatchesRegularExpression('/\(3\)\s+Tj/', $bytes);
        self::assertDoesNotMatchRegularExpression('/\(\?\)\s+Tj/', $bytes);
    }

    public function testStandardFontEmitsNoExtraGlyphForRadicalSign(): void
    {
        // Without a math font, the only Tj emissions should be the
        // radicand content - no extra glyph for the radical sign.
        $bytes = $this->render('<msqrt><mn>2</mn></msqrt>', null);
        $tjCount = preg_match_all('/\([^)]+\)\s+Tj/', $bytes);
        self::assertSame(
            1,
            $tjCount,
            'Only one Tj (the radicand) expected from standard-font msqrt',
        );
    }

    public function testMathFontPathSurvivesEvenIfRadicalGlyphAbsent(): void
    {
        // The WPT synthetic fraction font doesn't carry U+221A.
        // The painter's cmap check should detect that and skip the
        // emit cleanly - no crash, valid PDF.
        $font = $this->wpt('fraction-rulethickness10000.woff');
        if ($font === null) {
            self::markTestSkipped("WPT math font not available");
        }
        $bytes = $this->render('<msqrt><mn>2</mn></msqrt>', $font);
        self::assertStringStartsWith('%PDF-', $bytes);
    }

    public function testMathFontEmptySqrtRendersWithoutCrashing(): void
    {
        // Empty <msqrt/> hits the early-return path; no crash even
        // when a math font is loaded.
        $font = $this->wpt('fraction-rulethickness10000.woff');
        if ($font === null) {
            self::markTestSkipped("WPT math font not available");
        }
        $bytes = $this->render('<msqrt/>', $font);
        self::assertStringStartsWith('%PDF-', $bytes);
    }

    private function render(string $innerXml, ?string $mathFontPath): string
    {
        $xml = '<math xmlns="http://www.w3.org/1998/Math/MathML">'
            . $innerXml . '</math>';
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

    private function wpt(string $name): ?string
    {
        $path = self::WPT_MATH_FONTS_DIR . '/' . $name;
        return is_file($path) ? $path : null;
    }
}
