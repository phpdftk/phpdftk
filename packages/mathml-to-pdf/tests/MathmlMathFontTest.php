<?php

declare(strict_types=1);

namespace Phpdftk\MathmlToPdf\Tests;

use Phpdftk\MathmlToPdf\MathmlMathFont;
use Phpdftk\Pdf\Writer\Font;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for {@see MathmlMathFont}, the value object the painter
 * uses to bridge a Unicode token into a Type 0 / Identity-H hex
 * stream + a real-width cursor advance.
 *
 * We don't need a real font for these tests - the value object
 * accepts a Unicode -> GID map and a GID -> width map directly so
 * we exercise the helpers without spinning up an OpenType parser.
 */
final class MathmlMathFontTest extends TestCase
{
    public function testUtf8ToHexGidsMapsAsciiSequence(): void
    {
        $font = $this->makeFont(
            // Use codepoint integers directly because PHP auto-
            // converts numeric string keys to ints in array literals.
            unicodeToGid: [ord('x') => 5, ord('2') => 17],
            glyphWidths: [],
        );
        // 'x' = 5, '2' = 17. Hex emission: 0005 0011.
        self::assertSame('00050011', $font->utf8ToHexGids('x2'));
    }

    public function testUtf8ToHexGidsSkipsUnmappedCharacters(): void
    {
        $font = $this->makeFont(
            unicodeToGid: ['x' => 5],
            glyphWidths: [],
        );
        // 'z' isn't in the map; only 'x' surfaces.
        self::assertSame('0005', $font->utf8ToHexGids('xz'));
    }

    public function testUtf8ToHexGidsHandlesUnicodeOperators(): void
    {
        $font = $this->makeFont(
            unicodeToGid: [0x2211 => 42], // Summation sign -> GID 42
            glyphWidths: [],
        );
        self::assertSame('002A', $font->utf8ToHexGids("\u{2211}"));
    }

    public function testMeasureUsesGlyphWidths(): void
    {
        $font = $this->makeFont(
            unicodeToGid: ['x' => 5, 'y' => 6],
            glyphWidths: [5 => 600, 6 => 400],
            unitsPerEm: 1000,
        );
        // 600 + 400 = 1000 units = 1.0 em. At 12 pt -> 12 pt.
        self::assertEqualsWithDelta(12.0, $font->measure('xy', 12.0), 0.0001);
    }

    public function testMeasureFallsBackToDefaultForUnmappedChar(): void
    {
        $font = $this->makeFont(
            unicodeToGid: ['x' => 5],
            glyphWidths: [5 => 500],
            unitsPerEm: 1000,
        );
        // 'z' has no GID -> contributes the DEFAULT_WIDTH (500).
        self::assertEqualsWithDelta(
            (500 + 500) / 1000.0 * 12.0,
            $font->measure('xz', 12.0),
            0.0001,
        );
    }

    public function testMeasureFallsBackToDefaultForUnmappedGidWidth(): void
    {
        // GID is mapped but width isn't in the widths map.
        $font = $this->makeFont(
            unicodeToGid: ['x' => 5],
            glyphWidths: [],
            unitsPerEm: 1000,
        );
        self::assertEqualsWithDelta(
            500 / 1000.0 * 12.0,
            $font->measure('x', 12.0),
            0.0001,
        );
    }

    public function testEmptyStringReturnsZero(): void
    {
        $font = $this->makeFont(unicodeToGid: [], glyphWidths: []);
        self::assertSame('', $font->utf8ToHexGids(''));
        self::assertSame(0.0, $font->measure('', 12.0));
    }

    public function testZeroFontSizeReturnsZeroFromMeasure(): void
    {
        $font = $this->makeFont(
            unicodeToGid: ['x' => 5],
            glyphWidths: [5 => 500],
        );
        self::assertSame(0.0, $font->measure('x', 0.0));
    }

    public function testItalicCorrectionForLooksUpByOldGid(): void
    {
        // post-subset GID 5 maps to pre-subset 42; MathGlyphInfo
        // carries italicCorrection[42] = 120.
        $font = $this->makeFontWithMathTables(
            unicodeToGid: ['x' => 5],
            oldToNewGid: [42 => 5],
            italicCorrections: [42 => 120],
        );
        self::assertSame(120, $font->italicCorrectionFor(5));
    }

    public function testItalicCorrectionForReturnsZeroWhenNoGlyphInfo(): void
    {
        $font = $this->makeFont(
            unicodeToGid: ['x' => 5],
            glyphWidths: [],
        );
        self::assertSame(0, $font->italicCorrectionFor(5));
    }

    public function testItalicCorrectionForReturnsZeroWhenGidNotInMap(): void
    {
        $font = $this->makeFontWithMathTables(
            unicodeToGid: ['x' => 5],
            oldToNewGid: [42 => 5],
            italicCorrections: [], // empty - no italic correction
        );
        self::assertSame(0, $font->italicCorrectionFor(5));
    }

    public function testVerticalVariantForReturnsFirstLargeEnough(): void
    {
        // Construction with variants at 500, 1000, 1500. Required
        // 800 -> picks the 1000 variant.
        $construction = new \Phpdftk\FontParser\MathGlyphConstruction(
            variants: [
                ['glyphId' => 11, 'advance' => 500],
                ['glyphId' => 12, 'advance' => 1000],
                ['glyphId' => 13, 'advance' => 1500],
            ],
            assembly: null,
        );
        $font = $this->makeFontWithMathTables(
            unicodeToGid: ['(' => 5],
            oldToNewGid: [42 => 5],
            verticalConstructions: [42 => $construction],
        );
        $variant = $font->verticalVariantFor(5, 800);
        self::assertSame(['glyphId' => 12, 'advance' => 1000], $variant);
    }

    public function testVerticalVariantForReturnsLargestWhenNoneFit(): void
    {
        $construction = new \Phpdftk\FontParser\MathGlyphConstruction(
            variants: [
                ['glyphId' => 11, 'advance' => 500],
                ['glyphId' => 12, 'advance' => 1000],
            ],
            assembly: null,
        );
        $font = $this->makeFontWithMathTables(
            unicodeToGid: ['(' => 5],
            oldToNewGid: [42 => 5],
            verticalConstructions: [42 => $construction],
        );
        $variant = $font->verticalVariantFor(5, 99999);
        self::assertSame(['glyphId' => 12, 'advance' => 1000], $variant);
    }

    public function testVerticalVariantForReturnsNullWhenNoConstruction(): void
    {
        $font = $this->makeFontWithMathTables(
            unicodeToGid: ['x' => 5],
            oldToNewGid: [42 => 5],
            verticalConstructions: [], // no entry for GID 42
        );
        self::assertNull($font->verticalVariantFor(5, 1000));
    }

    public function testPreSubsetToPostSubsetTranslation(): void
    {
        $font = $this->makeFontWithMathTables(
            unicodeToGid: [],
            oldToNewGid: [42 => 5, 99 => 10],
        );
        self::assertSame(5, $font->preSubsetToPostSubset(42));
        self::assertSame(10, $font->preSubsetToPostSubset(99));
        self::assertNull($font->preSubsetToPostSubset(123));
    }

    /**
     * Factory with optional MathGlyphInfo / MathVariants population.
     *
     * @param array<string|int, int> $unicodeToGid
     * @param array<int, int> $oldToNewGid
     * @param array<int, int> $italicCorrections
     * @param array<int, \Phpdftk\FontParser\MathGlyphConstruction> $verticalConstructions
     */
    private function makeFontWithMathTables(
        array $unicodeToGid,
        array $oldToNewGid,
        array $italicCorrections = [],
        array $verticalConstructions = [],
    ): MathmlMathFont {
        $normalized = [];
        foreach ($unicodeToGid as $key => $gid) {
            if (is_string($key)) {
                $cp = mb_ord($key, 'UTF-8');
                if ($cp !== false) {
                    $normalized[$cp] = $gid;
                }
            } else {
                $normalized[$key] = $gid;
            }
        }
        $glyphInfo = new \Phpdftk\FontParser\MathGlyphInfo(
            italicCorrections: $italicCorrections,
            topAccentAttachments: [],
            extendedShapes: [],
            kernInfoBytes: '',
        );
        $variants = new \Phpdftk\FontParser\MathVariants(
            minConnectorOverlap: 0,
            verticalConstructions: $verticalConstructions,
            horizontalConstructions: [],
        );
        $stub = (new \ReflectionClass(Font::class))->newInstanceWithoutConstructor();
        return new MathmlMathFont(
            font: $stub,
            unicodeToGid: $normalized,
            oldToNewGid: $oldToNewGid,
            glyphWidths: [],
            unitsPerEm: 1000,
            glyphInfo: $glyphInfo,
            variants: $variants,
        );
    }

    /**
     * @param array<int|string, int> $unicodeToGid map keyed by either
     *        codepoint (int) or single-char string (auto-converted).
     * @param array<int, int> $glyphWidths
     */
    private function makeFont(
        array $unicodeToGid,
        array $glyphWidths,
        int $unitsPerEm = 1000,
    ): MathmlMathFont {
        $normalized = [];
        foreach ($unicodeToGid as $key => $gid) {
            if (is_string($key)) {
                $cp = mb_ord($key, 'UTF-8');
                if ($cp !== false) {
                    $normalized[$cp] = $gid;
                }
            } else {
                $normalized[$key] = $gid;
            }
        }
        // The Font handle isn't exercised by the methods under test;
        // pass a minimal stub via reflection.
        $stub = (new \ReflectionClass(Font::class))->newInstanceWithoutConstructor();
        return new MathmlMathFont(
            font: $stub,
            unicodeToGid: $normalized,
            oldToNewGid: [],
            glyphWidths: $glyphWidths,
            unitsPerEm: $unitsPerEm,
        );
    }
}
