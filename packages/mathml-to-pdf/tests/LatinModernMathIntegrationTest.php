<?php

declare(strict_types=1);

namespace Phpdftk\MathmlToPdf\Tests;

use Phpdftk\Mathml\Parser as MathmlParser;
use Phpdftk\MathmlToPdf\MathmlMetricsFactory;
use Phpdftk\MathmlToPdf\MathmlRenderer;
use Phpdftk\Pdf\Writer\PdfWriter;
use PHPUnit\Framework\TestCase;

/**
 * Integration tests that load the production math font (Latin
 * Modern Math) via {@see MathmlMetricsFactory} and confirm the
 * metrics flow through the painter end-to-end.
 *
 * The font is downloaded on demand by
 * `composer fetch:math-font` (or `php scripts/fetch-math-font.php`)
 * and lands at packages/mathml-to-pdf/tests/fixtures/fonts/
 * latinmodern-math.otf. The path is git-ignored so the binary
 * doesn't sneak into the repo.
 *
 * Every test in this file skips itself when the font is absent so
 * the standard development flow (without the bootstrap step) stays
 * green.
 */
final class LatinModernMathIntegrationTest extends TestCase
{
    private const string FONT_PATH = __DIR__ . '/fixtures/fonts/latinmodern-math.otf';

    public function testProductionMathFontLoadsAndExposesMetrics(): void
    {
        $this->skipIfMissing();
        $metrics = MathmlMetricsFactory::fromMathFont(self::FONT_PATH);
        self::assertTrue($metrics->isMathFontActive());
        // Latin Modern Math uses unitsPerEm = 1000. The constants
        // come from the font; we don't pin specific values (each
        // version of the font may tune them) but they must produce
        // non-zero, sensible em-fractions.
        self::assertGreaterThan(0.0, $metrics->scriptScale());
        self::assertGreaterThan(0.0, $metrics->superscriptShiftUpEm());
        self::assertGreaterThan(0.0, $metrics->subscriptShiftDownEm());
        self::assertGreaterThan(0.0, $metrics->fractionRuleThicknessEm());
        self::assertGreaterThan(0.0, $metrics->axisHeightEm());
    }

    public function testRenderingWithProductionFontDiffersFromDefaults(): void
    {
        $this->skipIfMissing();
        $xml = '<math xmlns="http://www.w3.org/1998/Math/MathML">'
            . '<msup><mi>x</mi><mn>2</mn></msup>'
            . '</math>';

        $without = $this->render($xml, null);
        $with = $this->render($xml, self::FONT_PATH);

        self::assertStringStartsWith('%PDF-', $without);
        self::assertStringStartsWith('%PDF-', $with);
        // The metrics-aware render produces different Td values for
        // the same input - proves the font's MathConstants reach
        // the paint code.
        self::assertNotSame(
            $this->extractTds($without),
            $this->extractTds($with),
            'Production-font metrics must alter the script-shift Td values',
        );
    }

    public function testRenderingWithMathFontEmitsHexGidsNotPlainText(): void
    {
        // When the math font is configured via the path constructor
        // arg, the painter switches to Type 0 / Identity-H emission
        // - Tj should carry hex-encoded GIDs (<HEX> Tj) instead of
        // parenthesised text (`(x) Tj`).
        $this->skipIfMissing();
        $xml = '<math xmlns="http://www.w3.org/1998/Math/MathML">'
            . '<mn>2</mn>'
            . '</math>';
        $bytes = $this->renderWithMathFontPath($xml);
        self::assertStringStartsWith('%PDF-', $bytes);
        // At least one hex-GID Tj appears.
        self::assertMatchesRegularExpression(
            '/<[0-9A-F]{4,}>\s+Tj/',
            $bytes,
            'Math-font render should emit hex GID strings via Tj',
        );
    }

    public function testFractionWithProductionFontUsesFontRuleThickness(): void
    {
        $this->skipIfMissing();
        $xml = '<math xmlns="http://www.w3.org/1998/Math/MathML">'
            . '<mfrac><mn>1</mn><mn>2</mn></mfrac>'
            . '</math>';
        $bytes = $this->render($xml, self::FONT_PATH);
        // Both numerator and denominator glyphs reach the stream.
        self::assertMatchesRegularExpression('/\(1\)\s+Tj/', $bytes);
        self::assertMatchesRegularExpression('/\(2\)\s+Tj/', $bytes);
        // A fraction-bar stroke is emitted - the rule-thickness
        // pathway uses the font's value, so it shouldn't disappear.
        self::assertMatchesRegularExpression('/\nS\n/', $bytes);
    }

    private function skipIfMissing(): void
    {
        if (!is_file(self::FONT_PATH)) {
            self::markTestSkipped(
                "Latin Modern Math not downloaded. "
                . "Run `composer fetch:math-font` to enable this test.",
            );
        }
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

    private function renderWithMathFontPath(string $xml): string
    {
        $writer = new PdfWriter(compressStreams: false);
        $page = $writer->addPage();
        $renderer = new MathmlRenderer(
            $page,
            $writer,
            mathFontPath: self::FONT_PATH,
        );
        $doc = (new MathmlParser())->parse($xml);
        $renderer->draw($doc, x: 72.0, y: 600.0, width: 200.0, height: 30.0);
        return $writer->toBytes();
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
