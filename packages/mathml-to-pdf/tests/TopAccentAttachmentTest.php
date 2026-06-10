<?php

declare(strict_types=1);

namespace Phpdftk\MathmlToPdf\Tests;

use Phpdftk\FontParser\MathGlyphInfo;
use Phpdftk\MathmlToPdf\MathmlMathFont;
use Phpdftk\Pdf\Writer\Font;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for {@see MathmlMathFont::topAccentAttachmentFor()}.
 * The painter consults this to centre over-scripts on the font-
 * declared attachment point instead of the glyph's geometric
 * centre (the classic case: a hat over an italic letter `î`).
 */
final class TopAccentAttachmentTest extends TestCase
{
    public function testReturnsValueWhenRegisteredForOldGid(): void
    {
        $font = $this->makeFont(
            unicodeToGid: ['x' => 5],
            oldToNewGid: [42 => 5],
            topAccentAttachments: [42 => 420],
        );
        // post-subset GID 5 -> pre-subset 42 -> attachment 420 FUnits.
        self::assertSame(420, $font->topAccentAttachmentFor(5));
    }

    public function testReturnsNullWhenNoEntry(): void
    {
        $font = $this->makeFont(
            unicodeToGid: ['x' => 5],
            oldToNewGid: [42 => 5],
            topAccentAttachments: [], // empty
        );
        self::assertNull($font->topAccentAttachmentFor(5));
    }

    public function testReturnsNullWhenNoGlyphInfoAtAll(): void
    {
        // Build a MathmlMathFont without MathGlyphInfo (e.g. a math
        // font whose MATH table omits the sub-table).
        $stub = (new \ReflectionClass(Font::class))->newInstanceWithoutConstructor();
        $font = new MathmlMathFont(
            font: $stub,
            unicodeToGid: ['x' => 5],
            oldToNewGid: [42 => 5],
            glyphWidths: [],
            unitsPerEm: 1000,
            glyphInfo: null,
        );
        self::assertNull($font->topAccentAttachmentFor(5));
    }

    public function testReturnsNullForUnmappedPostSubsetGid(): void
    {
        $font = $this->makeFont(
            unicodeToGid: ['x' => 5],
            oldToNewGid: [42 => 5],
            topAccentAttachments: [42 => 420],
        );
        // GID 99 has no inverse mapping.
        self::assertNull($font->topAccentAttachmentFor(99));
    }

    /**
     * @param array<int|string, int> $unicodeToGid
     * @param array<int, int> $oldToNewGid
     * @param array<int, int> $topAccentAttachments
     */
    private function makeFont(
        array $unicodeToGid,
        array $oldToNewGid,
        array $topAccentAttachments,
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
        $glyphInfo = new MathGlyphInfo(
            italicCorrections: [],
            topAccentAttachments: $topAccentAttachments,
            extendedShapes: [],
            kernInfoBytes: '',
        );
        $stub = (new \ReflectionClass(Font::class))->newInstanceWithoutConstructor();
        return new MathmlMathFont(
            font: $stub,
            unicodeToGid: $normalized,
            oldToNewGid: $oldToNewGid,
            glyphWidths: [],
            unitsPerEm: 1000,
            glyphInfo: $glyphInfo,
        );
    }
}
