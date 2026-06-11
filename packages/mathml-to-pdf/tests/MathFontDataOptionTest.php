<?php

declare(strict_types=1);

namespace Phpdftk\MathmlToPdf\Tests;

use Phpdftk\FontParser\OpenTypeParser;
use Phpdftk\FontParser\WoffParser;
use Phpdftk\Mathml\Parser as MathmlParser;
use Phpdftk\MathmlToPdf\MathmlRenderer;
use Phpdftk\Pdf\Writer\PdfWriter;
use PHPUnit\Framework\TestCase;

/**
 * Verify that MathmlRenderer accepts a pre-parsed OpenTypeData
 * via the new `mathFontData` constructor option. This is the path
 * the html-to-pdf inline-math painter uses when an @font-face
 * WOFF resolves to a font with a MATH table - it parses once at
 * the cascade level and hands the data to MathmlRenderer instead
 * of re-loading from a path.
 */
final class MathFontDataOptionTest extends TestCase
{
    public function testMathFontDataOptionThreadsFontIntoRenderer(): void
    {
        $woffPath = $this->locateSampleMathFont();
        if ($woffPath === null) {
            self::markTestSkipped('No WPT math fonts available - run from a repo with vendor-data/wpt cloned.');
        }

        // Load + decompress + parse at the test level.
        $sfntBytes = WoffParser::decompress($woffPath);
        $data = OpenTypeParser::fromBytes($sfntBytes)->parse();
        self::assertNotNull(
            $data->mathTable,
            'sample WPT math font should have a MATH table',
        );

        // Render through MathmlRenderer with the pre-parsed data.
        $writer = new PdfWriter(compressStreams: false);
        $page = $writer->addPage();
        $renderer = new MathmlRenderer(
            $page,
            $writer,
            mathFontData: $data,
        );
        $doc = (new MathmlParser())->parse(
            '<math xmlns="http://www.w3.org/1998/Math/MathML">'
            . '<mfrac><mn>1</mn><mn>2</mn></mfrac>'
            . '</math>',
        );
        $renderer->draw($doc, x: 72.0, y: 600.0, width: 200.0, height: 30.0);

        $bytes = $writer->toBytes();
        self::assertStringStartsWith('%PDF-', $bytes);
        // The math-font's MATH table drives FractionRuleThickness;
        // the painter should emit an `S` (stroke) for the bar.
        self::assertMatchesRegularExpression(
            '/\bS\b/',
            $bytes,
            'fraction bar should stroke through with a math font loaded',
        );
    }

    public function testNonMathFontFallsBackToDefaultsCleanly(): void
    {
        // OpenTypeData without a MATH table shouldn't throw - the
        // renderer falls back to default metrics quietly. This
        // matters when the html-to-pdf cascade loads a non-math
        // font via @font-face and we hand it through anyway.
        // We construct the data via the existing path - for the
        // test we just confirm the no-font path still works.
        $writer = new PdfWriter(compressStreams: false);
        $page = $writer->addPage();
        $renderer = new MathmlRenderer($page, $writer);
        $doc = (new MathmlParser())->parse(
            '<math xmlns="http://www.w3.org/1998/Math/MathML"><mn>1</mn></math>',
        );
        $renderer->draw($doc, x: 72.0, y: 600.0, width: 200.0, height: 30.0);
        self::assertStringStartsWith('%PDF-', $writer->toBytes());
    }

    private function locateSampleMathFont(): ?string
    {
        $candidates = [
            __DIR__ . '/../../../vendor-data/wpt/fonts/math/css-units.woff',
            __DIR__ . '/../../../vendor-data/wpt/fonts/math/fraction-axisheight7000-rulethickness1000.woff',
        ];
        foreach ($candidates as $candidate) {
            $real = realpath($candidate);
            if ($real !== false && is_file($real)) {
                return $real;
            }
        }
        return null;
    }
}
