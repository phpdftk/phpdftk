<?php

declare(strict_types=1);

namespace Phpdftk\MathmlToPdf\Tests;

use Phpdftk\FontParser\MathGlyphConstruction;
use Phpdftk\FontParser\MathGlyphInfo;
use Phpdftk\FontParser\MathVariants;
use Phpdftk\MathmlToPdf\MathmlMathFont;
use Phpdftk\Pdf\Writer\Font;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for height-aware variant selection. The painter's
 * walkChildren sets ctx->stretchTargetEm to the max non-stretchy
 * child height before painting; tryStretchyEmit then converts that
 * em target to FUnits and picks the smallest variant >= the target.
 *
 * These tests exercise the verticalVariantFor selection logic
 * directly so a regression in the picker shows up loud, even when
 * no WPT fixture or production font exercises a wide enough
 * variant range.
 */
final class StretchyHeightAwareTest extends TestCase
{
    public function testPicksSmallestVariantAboveTarget(): void
    {
        $font = $this->makeFontWithVariants(['(' => 5], oldToNewGid: [42 => 5], variants: [
            42 => [
                ['glyphId' => 11, 'advance' => 1000],
                ['glyphId' => 12, 'advance' => 1500],
                ['glyphId' => 13, 'advance' => 2000],
                ['glyphId' => 14, 'advance' => 3000],
            ],
        ]);
        // Target 1700 -> first variant >= 1700 is advance 2000.
        $variant = $font->verticalVariantFor(5, 1700);
        self::assertSame(['glyphId' => 13, 'advance' => 2000], $variant);
    }

    public function testPicksBaseSizeVariantWhenTargetIsSmall(): void
    {
        $font = $this->makeFontWithVariants(['(' => 5], oldToNewGid: [42 => 5], variants: [
            42 => [
                ['glyphId' => 11, 'advance' => 1000],
                ['glyphId' => 12, 'advance' => 2000],
            ],
        ]);
        // Target 500 -> first variant 1000 is large enough.
        $variant = $font->verticalVariantFor(5, 500);
        self::assertSame(['glyphId' => 11, 'advance' => 1000], $variant);
    }

    public function testPicksLargestVariantWhenTargetIsHuge(): void
    {
        // Target 99999, no variant covers it - picker returns the
        // largest variant as best-effort. (Real assembly is a follow-up.)
        $font = $this->makeFontWithVariants(['(' => 5], oldToNewGid: [42 => 5], variants: [
            42 => [
                ['glyphId' => 11, 'advance' => 1000],
                ['glyphId' => 12, 'advance' => 2000],
                ['glyphId' => 13, 'advance' => 3000],
            ],
        ]);
        $variant = $font->verticalVariantFor(5, 99999);
        self::assertSame(['glyphId' => 13, 'advance' => 3000], $variant);
    }

    /**
     * @param array<int|string, int> $unicodeToGid
     * @param array<int, int> $oldToNewGid
     * @param array<int, list<array{glyphId: int, advance: int}>> $variants
     */
    private function makeFontWithVariants(
        array $unicodeToGid,
        array $oldToNewGid,
        array $variants,
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
        foreach ($variants as $baseGid => $varList) {
            $constructions[$baseGid] = new MathGlyphConstruction(
                variants: $varList,
                assembly: null,
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
