<?php

declare(strict_types=1);

namespace Phpdftk\MathmlToPdf\Tests;

use Phpdftk\MathmlToPdf\MathmlGlyphMetrics;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for the AFM-driven width measurement. Replacing the
 * fixed 0.5-em-per-char estimate with real Times-Roman widths means
 * regressions show up as cursor drift, which is hard to spot in the
 * higher-level renderer tests. These tests pin a representative
 * sample of glyph widths and the relative ordering between them.
 */
final class MathmlGlyphMetricsTest extends TestCase
{
    public function testEmptyStringReturnsZero(): void
    {
        self::assertSame(0.0, MathmlGlyphMetrics::measure('', 12.0));
    }

    public function testZeroFontSizeReturnsZero(): void
    {
        self::assertSame(0.0, MathmlGlyphMetrics::measure('hello', 0.0));
    }

    public function testWideGlyphMeasuresWiderThanNarrow(): void
    {
        // `W` is one of Times-Roman's widest glyphs; `i` one of the
        // narrowest. The old fixed estimate gave them identical
        // widths.
        $wide = MathmlGlyphMetrics::measure('W', 12.0);
        $narrow = MathmlGlyphMetrics::measure('i', 12.0);
        self::assertGreaterThan($narrow * 2.0, $wide);
    }

    public function testFontSizeScalesLinearly(): void
    {
        $at12 = MathmlGlyphMetrics::measure('hello', 12.0);
        $at24 = MathmlGlyphMetrics::measure('hello', 24.0);
        self::assertEqualsWithDelta($at12 * 2.0, $at24, 0.001);
    }

    public function testItalicAndUprightDifferForSameInput(): void
    {
        // Times-Italic glyph widths differ from Times-Roman - not
        // by a huge amount, but for a long string the cumulative
        // difference should be measurable.
        $upright = MathmlGlyphMetrics::measure(
            'The quick brown fox',
            12.0,
            italic: false,
        );
        $italic = MathmlGlyphMetrics::measure(
            'The quick brown fox',
            12.0,
            italic: true,
        );
        self::assertNotEqualsWithDelta($upright, $italic, 0.1);
    }

    public function testAsciiAdditiveOperatorPositive(): void
    {
        // Plus sign should have a measurable width - not the
        // missingWidth fallback.
        $plus = MathmlGlyphMetrics::measure('+', 12.0);
        self::assertGreaterThan(0.0, $plus);
    }

    public function testWidthsAdditiveOverConcatenation(): void
    {
        $a = MathmlGlyphMetrics::measure('a', 12.0);
        $b = MathmlGlyphMetrics::measure('b', 12.0);
        $ab = MathmlGlyphMetrics::measure('ab', 12.0);
        self::assertEqualsWithDelta($a + $b, $ab, 0.001);
    }

    public function testNonWinAnsiCharacterFallsBackToMissingWidth(): void
    {
        // U+2211 SUMMATION isn't in WinAnsi; the encoder replaces
        // it with `?`. Width should match `?` exactly.
        $summation = MathmlGlyphMetrics::measure("\u{2211}", 12.0);
        $question = MathmlGlyphMetrics::measure('?', 12.0);
        self::assertEqualsWithDelta($question, $summation, 0.001);
    }

    public function testUprightAfmDataAccessible(): void
    {
        $afm = MathmlGlyphMetrics::upright();
        self::assertGreaterThan(0, $afm->ascender);
    }

    public function testItalicAfmDataAccessible(): void
    {
        $afm = MathmlGlyphMetrics::italic();
        self::assertLessThan(0, $afm->italicAngle);
    }

    public function testRealWidthsDiffersFromOldFixedEstimate(): void
    {
        // Sanity that the new measurement isn't returning the old
        // 0.5em-per-char value. 'l' is very narrow (~0.278 em);
        // 'l' * 5 = 'lllll' should be much narrower than 5 chars *
        // 0.5em * 12 = 30 pt.
        $five = MathmlGlyphMetrics::measure('lllll', 12.0);
        self::assertLessThan(30.0, $five);
        self::assertGreaterThan(0.0, $five);
    }
}
