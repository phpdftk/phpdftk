<?php

declare(strict_types=1);

namespace Phpdftk\FontParser\Tests;

use Phpdftk\FontParser\MathTableData;
use Phpdftk\FontParser\OpenTypeParser;
use Phpdftk\FontParser\WoffParser;
use PHPUnit\Framework\TestCase;

/**
 * MATH-table extraction tests, exercised against the WPT MathML
 * synthetic fonts under vendor-data/wpt/fonts/math/. Each font is a
 * tiny WOFF/CFF with the MATH table populated to specific values so
 * a reftest can verify a particular MathConstants slot drives the
 * rendered output.
 *
 * For this slice the parser only locates the MATH table and slices
 * the three top-level sub-tables (MathConstants, MathGlyphInfo,
 * MathVariants) into raw bytes. Follow-up slices add the parsers
 * for each sub-table.
 *
 * The tests skip themselves when the WPT submodule isn't checked
 * out so the CI environment without submodules doesn't fail hard.
 */
final class MathTableTest extends TestCase
{
    private const string WPT_MATH_FONTS_DIR =
        __DIR__ . '/../../../vendor-data/wpt/fonts/math';

    private function loadFontWithMath(string $woffName): \Phpdftk\FontParser\OpenTypeData
    {
        $path = self::WPT_MATH_FONTS_DIR . '/' . $woffName;
        if (!is_file($path)) {
            self::markTestSkipped(
                "WPT math font fixture not available: $path. "
                . "Run `git submodule update --init vendor-data/wpt`.",
            );
        }
        // WPT math fonts are WOFF; decompress to raw OTF bytes first.
        $otfBytes = WoffParser::decompress($path);
        return OpenTypeParser::fromBytes($otfBytes)->parse();
    }

    public function testMathTableDetectedInFractionFont(): void
    {
        $font = $this->loadFontWithMath('fraction-rulethickness10000.woff');
        self::assertNotNull(
            $font->mathTable,
            'fraction-rulethickness10000.woff should expose a MATH table',
        );
    }

    public function testMathTableVersionIsOnePointZero(): void
    {
        $font = $this->loadFontWithMath('fraction-rulethickness10000.woff');
        self::assertInstanceOf(MathTableData::class, $font->mathTable);
        self::assertSame(1, $font->mathTable->majorVersion);
        self::assertSame(0, $font->mathTable->minorVersion);
    }

    public function testMathConstantsSubTablePresent(): void
    {
        // Every well-formed math font has MathConstants - it carries
        // the layout constants that drive fractions, scripts, etc.
        $font = $this->loadFontWithMath('fraction-rulethickness10000.woff');
        self::assertInstanceOf(MathTableData::class, $font->mathTable);
        self::assertTrue($font->mathTable->hasMathConstants());
        self::assertNotEmpty($font->mathTable->mathConstantsBytes);
    }

    public function testMathTableBytesNonZeroLength(): void
    {
        // Spec mandates the table is at least 10 bytes (header).
        // The sliced sub-tables shouldn't be empty for a math font.
        $font = $this->loadFontWithMath('axisheight5000-verticalarrow14000.woff');
        self::assertInstanceOf(MathTableData::class, $font->mathTable);
        $totalSubTableBytes =
            strlen($font->mathTable->mathConstantsBytes)
            + strlen($font->mathTable->mathGlyphInfoBytes)
            + strlen($font->mathTable->mathVariantsBytes);
        self::assertGreaterThan(0, $totalSubTableBytes);
    }

    public function testNonMathFontReturnsNullMathTable(): void
    {
        // The shared NotoSansMongolian fixture has no MATH table.
        $path = TestFonts::notoSansMongolianOtf();
        $font = OpenTypeParser::fromBytes(file_get_contents($path) ?: '')->parse();
        self::assertNull($font->mathTable);
    }

    public function testFontWithDifferentMathConstantHasDifferentBytes(): void
    {
        // Two WPT fonts that differ only in the MathConstants payload
        // - axisheight5000 vs fraction-axisheight7000. Their constant
        // bytes must differ; the rest of the OTF is identical.
        $a = $this->loadFontWithMath('axisheight5000-verticalarrow14000.woff');
        $b = $this->loadFontWithMath('fraction-axisheight7000-rulethickness1000.woff');
        self::assertInstanceOf(MathTableData::class, $a->mathTable);
        self::assertInstanceOf(MathTableData::class, $b->mathTable);
        self::assertNotSame(
            $a->mathTable->mathConstantsBytes,
            $b->mathTable->mathConstantsBytes,
            'Different MathConstants payloads must produce different bytes',
        );
    }
}
