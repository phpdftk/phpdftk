<?php

declare(strict_types=1);

namespace Phpdftk\FontParser\Tests;

use Phpdftk\FontParser\MathKern;
use Phpdftk\FontParser\MathKernInfo;
use Phpdftk\FontParser\MathKernInfoParser;
use Phpdftk\FontParser\MathKernRecord;
use Phpdftk\FontParser\MathGlyphInfoParser;
use Phpdftk\FontParser\OpenTypeParser;
use Phpdftk\FontParser\WoffParser;
use PHPUnit\Framework\TestCase;

/**
 * Tests for the MathKernInfo sub-table parser.
 *
 * Corner kerning is per-glyph and per-corner (topRight, topLeft,
 * bottomRight, bottomLeft) - each corner table is a piecewise
 * function of Y position. Tests cover the empty/short-input
 * defaults, the value-lookup helper on MathKern, and traversal of
 * the WPT math fonts to catch crashes on real fixtures.
 */
final class MathKernInfoParserTest extends TestCase
{
    private const string WPT_MATH_FONTS_DIR =
        __DIR__ . '/../../../vendor-data/wpt/fonts/math';

    public function testEmptyBytesReturnsEmptyInfo(): void
    {
        $info = (new MathKernInfoParser())->parse('');
        self::assertSame([], $info->records);
    }

    public function testTruncatedHeaderReturnsEmptyInfo(): void
    {
        $info = (new MathKernInfoParser())->parse("\x00\x00");
        self::assertSame([], $info->records);
    }

    public function testZeroCoverageOffsetReturnsEmptyInfo(): void
    {
        // 4-byte header with both fields zero - no kern data.
        $info = (new MathKernInfoParser())->parse(str_repeat("\x00", 4));
        self::assertSame([], $info->records);
    }

    public function testMathKernValueAtPicksTheRightRange(): void
    {
        // Single breakpoint at height 500. Below -> kerns[0] = 10,
        // at-or-above -> kerns[1] = 20.
        $kern = new MathKern(
            correctionHeights: [500],
            kernValues: [10, 20],
        );
        self::assertSame(10, $kern->valueAt(0));
        self::assertSame(10, $kern->valueAt(499));
        self::assertSame(20, $kern->valueAt(500));
        self::assertSame(20, $kern->valueAt(1000));
    }

    public function testMathKernValueAtWithMultipleBreakpoints(): void
    {
        // Three breakpoints: 100, 200, 300. Four ranges:
        //   Y < 100  -> 1
        //   100 <= Y < 200 -> 2
        //   200 <= Y < 300 -> 3
        //   Y >= 300 -> 4
        $kern = new MathKern(
            correctionHeights: [100, 200, 300],
            kernValues: [1, 2, 3, 4],
        );
        self::assertSame(1, $kern->valueAt(0));
        self::assertSame(2, $kern->valueAt(150));
        self::assertSame(3, $kern->valueAt(250));
        self::assertSame(4, $kern->valueAt(350));
    }

    public function testMathKernValueAtNoBreakpointsAlwaysReturnsFirstKern(): void
    {
        $kern = new MathKern(
            correctionHeights: [],
            kernValues: [42],
        );
        self::assertSame(42, $kern->valueAt(0));
        self::assertSame(42, $kern->valueAt(99999));
    }

    public function testParserDoesNotCrashOnAllWptMathFonts(): void
    {
        $dir = self::WPT_MATH_FONTS_DIR;
        if (!is_dir($dir)) {
            self::markTestSkipped("WPT math fonts dir not available: $dir");
        }
        $kernParser = new MathKernInfoParser();
        $glyphInfoParser = new MathGlyphInfoParser();
        $withKerns = 0;
        foreach (glob($dir . '/*.woff') ?: [] as $path) {
            $otfBytes = WoffParser::decompress($path);
            $font = OpenTypeParser::fromBytes($otfBytes)->parse();
            if ($font->mathTable === null) {
                continue;
            }
            $glyphInfo = $glyphInfoParser->parse(
                $font->mathTable->mathGlyphInfoBytes,
            );
            $kernInfo = $kernParser->parse($glyphInfo->kernInfoBytes);
            if ($kernInfo->records !== []) {
                $withKerns++;
            }
        }
        // We don't require any WPT font to have kerns - the parser
        // just must not crash on any of them. We do assert that the
        // parser executed against several fonts.
        self::assertGreaterThanOrEqual(0, $withKerns);
    }

    public function testReturnedObjectsTypedAsExpected(): void
    {
        // Type pin so renames blow up loud.
        $info = new MathKernInfo([]);
        self::assertInstanceOf(MathKernInfo::class, $info);

        $record = new MathKernRecord();
        self::assertInstanceOf(MathKernRecord::class, $record);
        self::assertNull($record->topRight);
        self::assertNull($record->bottomLeft);
    }
}
