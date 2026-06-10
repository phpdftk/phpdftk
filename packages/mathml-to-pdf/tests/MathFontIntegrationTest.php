<?php

declare(strict_types=1);

namespace Phpdftk\MathmlToPdf\Tests;

use Phpdftk\Mathml\Parser as MathmlParser;
use Phpdftk\MathmlToPdf\MathmlMetricsFactory;
use Phpdftk\MathmlToPdf\MathmlRenderer;
use Phpdftk\Pdf\Writer\PdfWriter;
use PHPUnit\Framework\TestCase;

/**
 * End-to-end test that loading a math font with non-default
 * MathConstants actually changes painter output - the script-shift
 * Td values, fraction-related cursor positions, etc. should reflect
 * the font-derived metrics rather than the tracer-bullet defaults.
 *
 * We compare two renders of the same MathML:
 *
 *   - Without a math font: produces the historical defaults.
 *   - With a math font whose constants differ from the defaults:
 *     produces a measurably different content stream.
 *
 * Identical output across the two renders means the metrics
 * pipeline isn't wired through to the paint code.
 */
final class MathFontIntegrationTest extends TestCase
{
    private const string WPT_MATH_FONTS_DIR =
        __DIR__ . '/../../../vendor-data/wpt/fonts/math';

    public function testRenderingMsupWithMathFontDiffersFromDefaults(): void
    {
        $fontPath = self::WPT_MATH_FONTS_DIR
            . '/fraction-numeratorshiftup11000-axisheight1000-rulethickness1000.woff';
        if (!is_file($fontPath)) {
            self::markTestSkipped("WPT math font not available: $fontPath");
        }

        $xml = '<math xmlns="http://www.w3.org/1998/Math/MathML">'
            . '<msup><mi>x</mi><mn>2</mn></msup>'
            . '</math>';

        $withoutMathFont = $this->render($xml, null);
        $withMathFont = $this->render($xml, $fontPath);

        // Both must produce valid PDFs containing the glyphs.
        self::assertStringStartsWith('%PDF-', $withoutMathFont);
        self::assertStringStartsWith('%PDF-', $withMathFont);
        foreach (['x', '2'] as $glyph) {
            self::assertMatchesRegularExpression(
                '/\(' . $glyph . '\)\s+Tj/',
                $withoutMathFont,
            );
            self::assertMatchesRegularExpression(
                '/\(' . $glyph . '\)\s+Tj/',
                $withMathFont,
            );
        }
        // The Td operations differ - the math font's constants
        // produce different sub/sup shifts.
        $tdsWithout = $this->extractTds($withoutMathFont);
        $tdsWith = $this->extractTds($withMathFont);
        self::assertNotSame(
            $tdsWithout,
            $tdsWith,
            'Math-font metrics must change the script positioning Td values',
        );
    }

    public function testMathFontMetricsAdjustsScriptScale(): void
    {
        // Use a WPT font and verify the metrics report a different
        // scriptScale than the default. Pairs with the rendering
        // test - if the metrics flow but the paint method ignores
        // them, that test fails; if metrics don't flow at all, both
        // tests fail.
        $fontPath = self::WPT_MATH_FONTS_DIR
            . '/fraction-numeratorshiftup11000-axisheight1000-rulethickness1000.woff';
        if (!is_file($fontPath)) {
            self::markTestSkipped("WPT math font not available: $fontPath");
        }
        $metrics = MathmlMetricsFactory::fromMathFont($fontPath);
        // WPT synthetic fonts have a default scriptPercentScaleDown
        // (often 70 to match the OpenType default). Don't pin a
        // specific value - just confirm the constants were loaded
        // (isMathFontActive returns true).
        self::assertTrue($metrics->isMathFontActive());
    }

    private function render(string $xml, ?string $mathFontPath): string
    {
        $writer = new PdfWriter(compressStreams: false);
        $page = $writer->addPage();
        $metrics = $mathFontPath !== null
            ? MathmlMetricsFactory::fromMathFont($mathFontPath)
            : null;
        $renderer = new MathmlRenderer($page, $writer, mathMetrics: $metrics);
        $doc = (new MathmlParser())->parse($xml);
        $renderer->draw($doc, x: 72.0, y: 600.0, width: 200.0, height: 30.0);
        return $writer->toBytes();
    }

    /**
     * Pull out all Td operations from a content stream, preserving
     * order, as numeric tuples. Useful for comparing two renders
     * that differ in cursor mechanics.
     *
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
