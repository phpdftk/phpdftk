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
            glyphWidths: $glyphWidths,
            unitsPerEm: $unitsPerEm,
        );
    }
}
