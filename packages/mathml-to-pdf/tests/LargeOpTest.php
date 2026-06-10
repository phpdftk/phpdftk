<?php

declare(strict_types=1);

namespace Phpdftk\MathmlToPdf\Tests;

use Phpdftk\Mathml\Parser as MathmlParser;
use Phpdftk\MathmlToPdf\MathmlMetrics;
use Phpdftk\MathmlToPdf\MathmlRenderer;
use Phpdftk\Pdf\Writer\PdfWriter;
use PHPUnit\Framework\TestCase;

/**
 * Tests for `<mo largeop="true">` — the operator should render at
 * the font's `displayOperatorMinHeight` (per MathConstants) via the
 * MathVariants vertical-construction chain. Typical use: ∑/∏/∫ in
 * display-style equations.
 *
 * Without a math font, largeop falls back to the standard glyph
 * emission — the existing dictionary spacing applies but the glyph
 * doesn't scale (the standard fonts don't carry MATH-tables).
 *
 * The WPT `largeop-displayoperatorminheight*.woff` fixtures embed
 * a known displayOperatorMinHeight value in the font's
 * MathConstants AND register vertical variants for U+2AFF. The
 * tests use them to verify the painter picks a variant when
 * largeop=true and the font has one available.
 */
final class LargeOpTest extends TestCase
{
    private const string WPT_MATH_FONTS_DIR =
        __DIR__ . '/../../../vendor-data/wpt/fonts/math';

    public function testStandardFontLargeOpFallsBackToPlainEmit(): void
    {
        // Without a math font, the painter shouldn't crash; it
        // emits the standard glyph (which renders as '?' in the
        // Type 1 standard font for non-ASCII operators but is
        // still a valid PDF).
        $bytes = $this->render(
            '<mo largeop="true">' . "\u{2211}" . '</mo>',
            null,
        );
        self::assertStringStartsWith('%PDF-', $bytes);
    }

    public function testDefaultDisplayOperatorMinHeightWithoutMathFont(): void
    {
        // Metrics adapter returns a non-zero default even with no
        // math font - the painter relies on this for the picker.
        $m = new MathmlMetrics();
        self::assertSame(
            MathmlMetrics::DEFAULT_DISPLAY_OPERATOR_MIN_HEIGHT_EM,
            $m->displayOperatorMinHeightEm(),
        );
        self::assertGreaterThan(0.0, $m->displayOperatorMinHeightEm());
    }

    public function testLargeOpWithMathFontEmitsHexGid(): void
    {
        // The WPT axisheight font carries U+21A8 (a vertical-arrow
        // glyph) with a vertical construction. largeop on that glyph
        // should drive the picker to pick a variant - we verify by
        // looking for hex Tj emission in the content stream.
        $font = $this->wpt('axisheight5000-verticalarrow14000.woff');
        if ($font === null) {
            self::markTestSkipped("WPT math font not available");
        }
        $bytes = $this->render(
            '<mo largeop="true">' . "\u{21A8}" . '</mo>',
            $font,
        );
        self::assertStringStartsWith('%PDF-', $bytes);
        // Hex GID emission proves the math-font path fired.
        self::assertMatchesRegularExpression(
            '/<[0-9A-F]{4,}>\s+Tj/',
            $bytes,
        );
    }

    public function testLargeOpFalseRendersIdenticallyToAbsent(): void
    {
        // Explicit largeop="false" should produce the same output as
        // omitting the attribute - both end at the standard emit.
        $omit = $this->render('<mo>' . "\u{2211}" . '</mo>', null);
        $explicit = $this->render(
            '<mo largeop="false">' . "\u{2211}" . '</mo>',
            null,
        );
        // Skip the trailing /ID and creation-time bytes that differ
        // between separate writer invocations.
        self::assertSame(
            $this->extractTds($omit),
            $this->extractTds($explicit),
        );
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

    /**
     * @return list<array{float, float}>
     */
    private function extractTds(string $bytes): array
    {
        if (!preg_match_all('/(-?\d+(?:\.\d+)?)\s+(-?\d+(?:\.\d+)?)\s+Td\b/', $bytes, $matches)) {
            return [];
        }
        $out = [];
        foreach ($matches[1] as $i => $dx) {
            $out[] = [(float) $dx, (float) $matches[2][$i]];
        }
        return $out;
    }
}
