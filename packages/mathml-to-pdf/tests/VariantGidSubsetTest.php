<?php

declare(strict_types=1);

namespace Phpdftk\MathmlToPdf\Tests;

use Phpdftk\FontParser\MathVariantsParser;
use Phpdftk\FontParser\OpenTypeParser;
use Phpdftk\FontParser\WoffParser;
use Phpdftk\Mathml\Parser as MathmlParser;
use Phpdftk\MathmlToPdf\MathmlRenderer;
use Phpdftk\Pdf\Writer\PdfWriter;
use PHPUnit\Framework\TestCase;

/**
 * Verifies that when a math font is loaded for a document that
 * contains stretchy operators, the renderer collects the
 * MathVariants glyph IDs ahead of font registration so the CFF
 * subsetter keeps them in the embedded program.
 *
 * Uses the WPT `axisheight5000-verticalarrow14000.woff` fixture
 * which has a vertical construction for U+21A8 with a variant at
 * advance 14001 - we verify the variant GID survives subsetting
 * by inspecting the rendered PDF's /W widths array (which is
 * indexed by post-subset GID).
 */
final class VariantGidSubsetTest extends TestCase
{
    private const string WPT_MATH_FONTS_DIR =
        __DIR__ . '/../../../vendor-data/wpt/fonts/math';

    public function testVariantGidsAreCollectedForStretchyBaseGlyphs(): void
    {
        $path = self::WPT_MATH_FONTS_DIR
            . '/axisheight5000-verticalarrow14000.woff';
        if (!is_file($path)) {
            self::markTestSkipped("WPT math font not available: $path");
        }

        // Parse the font to figure out which variant GIDs we expect
        // to find in the subset.
        $fontBytes = WoffParser::decompress($path);
        $data = OpenTypeParser::fromBytes($fontBytes)->parse();
        self::assertNotNull($data->mathTable);
        $variants = (new MathVariantsParser())
            ->parse($data->mathTable->mathVariantsBytes);

        // The fixture maps U+21A8 to a base GID with a vertical
        // construction. Pull out the expected variant GIDs.
        $baseGid = $data->fullUnicodeToGid[0x21A8] ?? null;
        self::assertNotNull(
            $baseGid,
            'WPT fixture should map U+21A8 to a base GID',
        );
        $construction = $variants->verticalConstructions[$baseGid] ?? null;
        self::assertNotNull(
            $construction,
            'WPT fixture should have a vertical construction for U+21A8',
        );
        self::assertNotEmpty(
            $construction->variants,
            'Expected at least one variant for U+21A8',
        );

        // Render a document that uses U+21A8 marked stretchy. The
        // renderer should subset the font with the variant GIDs
        // included; we assert by checking the PDF carries width
        // information for more glyphs than just the base.
        $writer = new PdfWriter(compressStreams: false);
        $page = $writer->addPage();
        $renderer = new MathmlRenderer(
            $page,
            $writer,
            mathFontPath: $path,
        );
        $doc = (new MathmlParser())->parse(
            '<math xmlns="http://www.w3.org/1998/Math/MathML">'
                . '<mo stretchy="true">' . "\u{21A8}" . '</mo>'
                . '</math>',
        );
        $renderer->draw($doc, x: 72.0, y: 600.0, width: 200.0, height: 30.0);
        $bytes = $writer->toBytes();

        self::assertStringStartsWith('%PDF-', $bytes);
        // The /W array carries an entry per post-subset GID; without
        // variant collection it'd hold just one entry. With variant
        // collection it should hold more.
        self::assertMatchesRegularExpression(
            '/\/W \[\s*\d+(?:\s+\[(?:\s*\d+){2,})/',
            $bytes,
            'Expected /W array to carry more than one post-subset GID',
        );
    }

    public function testRendererProducesValidPdfEvenWithNoVariantsAvailable(): void
    {
        // A WPT font with no MathVariants (or with variants for
        // codepoints the document doesn't use) should still produce
        // a valid PDF. Confirms the collection step doesn't crash
        // on the no-variant path.
        $path = self::WPT_MATH_FONTS_DIR . '/fraction-rulethickness10000.woff';
        if (!is_file($path)) {
            self::markTestSkipped("WPT math font not available: $path");
        }
        $writer = new PdfWriter(compressStreams: false);
        $page = $writer->addPage();
        $renderer = new MathmlRenderer($page, $writer, mathFontPath: $path);
        $doc = (new MathmlParser())->parse(
            '<math xmlns="http://www.w3.org/1998/Math/MathML">'
                . '<mfrac><mn>1</mn><mn>2</mn></mfrac>'
                . '</math>',
        );
        $renderer->draw($doc, x: 72.0, y: 600.0, width: 200.0, height: 30.0);
        self::assertStringStartsWith('%PDF-', $writer->toBytes());
    }
}
