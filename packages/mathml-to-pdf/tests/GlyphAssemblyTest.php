<?php

declare(strict_types=1);

namespace Phpdftk\MathmlToPdf\Tests;

use Phpdftk\FontParser\MathGlyphAssembly;
use Phpdftk\FontParser\MathGlyphConstruction;
use Phpdftk\FontParser\MathGlyphInfo;
use Phpdftk\FontParser\MathVariants;
use Phpdftk\MathmlToPdf\MathmlMathFont;
use Phpdftk\Pdf\Writer\Font;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for {@see MathmlMathFont::verticalAssemblyFor()},
 * the lookup the painter uses to fall back from pre-drawn variants
 * to a glyph-assembly recipe when no variant covers the required
 * height.
 *
 * The integration test for the full painter assembly emit path
 * is at {@see ItalicCorrectionAndStretchyTest} (which exercises
 * the renderer end-to-end with a WPT synthetic font).
 */
final class GlyphAssemblyTest extends TestCase
{
    public function testReturnsAssemblyWhenRegistered(): void
    {
        $assembly = new MathGlyphAssembly(
            italicsCorrection: 100,
            parts: [
                ['glyphId' => 11, 'startConnector' => 0, 'endConnector' => 50, 'fullAdvance' => 1000, 'extender' => false],
                ['glyphId' => 12, 'startConnector' => 50, 'endConnector' => 50, 'fullAdvance' => 500, 'extender' => true],
                ['glyphId' => 13, 'startConnector' => 50, 'endConnector' => 0, 'fullAdvance' => 1000, 'extender' => false],
            ],
        );
        $font = $this->makeFontWithAssembly(
            unicodeToGid: ['(' => 5],
            oldToNewGid: [42 => 5],
            verticalAssembly: [42 => $assembly],
        );
        self::assertSame($assembly, $font->verticalAssemblyFor(5));
    }

    public function testReturnsNullWhenConstructionHasNoAssembly(): void
    {
        // Construction exists but only has pre-drawn variants, no
        // assembly recipe.
        $font = $this->makeFontWithAssembly(
            unicodeToGid: ['(' => 5],
            oldToNewGid: [42 => 5],
            verticalVariants: [42 => [['glyphId' => 11, 'advance' => 1000]]],
        );
        self::assertNull($font->verticalAssemblyFor(5));
    }

    public function testReturnsNullWhenNoConstruction(): void
    {
        $font = $this->makeFontWithAssembly(
            unicodeToGid: ['(' => 5],
            oldToNewGid: [42 => 5],
            verticalAssembly: [], // no construction for GID 42
        );
        self::assertNull($font->verticalAssemblyFor(5));
    }

    public function testReturnsNullWhenNoVariantsTable(): void
    {
        // Build a math font without MathVariants entirely.
        $stub = (new \ReflectionClass(Font::class))->newInstanceWithoutConstructor();
        $font = new MathmlMathFont(
            font: $stub,
            unicodeToGid: ['(' => 5],
            oldToNewGid: [42 => 5],
            glyphWidths: [],
            unitsPerEm: 1000,
            glyphInfo: null,
            variants: null,
        );
        self::assertNull($font->verticalAssemblyFor(5));
    }

    public function testReturnsNullWhenGidNotInOldToNewMap(): void
    {
        $assembly = new MathGlyphAssembly(
            italicsCorrection: 0,
            parts: [],
        );
        $font = $this->makeFontWithAssembly(
            unicodeToGid: ['(' => 5],
            oldToNewGid: [42 => 5],
            verticalAssembly: [42 => $assembly],
        );
        // Post-subset GID 99 has no inverse.
        self::assertNull($font->verticalAssemblyFor(99));
    }

    /**
     * @param array<int|string, int> $unicodeToGid
     * @param array<int, int> $oldToNewGid
     * @param array<int, MathGlyphAssembly> $verticalAssembly
     * @param array<int, list<array{glyphId: int, advance: int}>> $verticalVariants
     */
    private function makeFontWithAssembly(
        array $unicodeToGid,
        array $oldToNewGid,
        array $verticalAssembly = [],
        array $verticalVariants = [],
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
        $constructions = [];
        $gids = array_unique(array_merge(
            array_keys($verticalAssembly),
            array_keys($verticalVariants),
        ));
        foreach ($gids as $baseGid) {
            $constructions[$baseGid] = new MathGlyphConstruction(
                variants: $verticalVariants[$baseGid] ?? [],
                assembly: $verticalAssembly[$baseGid] ?? null,
            );
        }
        $stub = (new \ReflectionClass(Font::class))->newInstanceWithoutConstructor();
        return new MathmlMathFont(
            font: $stub,
            unicodeToGid: $normalized,
            oldToNewGid: $oldToNewGid,
            glyphWidths: [],
            unitsPerEm: 1000,
            glyphInfo: new MathGlyphInfo([], [], [], ''),
            variants: new MathVariants(
                minConnectorOverlap: 0,
                verticalConstructions: $constructions,
                horizontalConstructions: [],
            ),
        );
    }
}
