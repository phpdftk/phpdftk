<?php

declare(strict_types=1);

namespace Phpdftk\Text\Tests;

use Phpdftk\FontParser\OpenTypeData;
use Phpdftk\Text\Shaper;
use Phpdftk\Text\ShapingContext;
use Phpdftk\Text\ShapingDirection;
use PHPUnit\Framework\TestCase;

/**
 * Tests for the Phase-1 OpenType shaper. Uses hand-crafted `OpenTypeData`
 * instances so the assertions don't depend on a particular real font's
 * glyph IDs / metrics. A separate integration test (where real fonts live)
 * will exercise this against the shared `tests/fixtures/fonts/` corpus
 * once the higher layers are wired in.
 */
final class ShaperTest extends TestCase
{
    private Shaper $shaper;

    protected function setUp(): void
    {
        $this->shaper = new Shaper();
    }

    /**
     * Build a minimal OpenTypeData carrying just the maps the shaper reads.
     *
     * @param array<int, int> $unicodeToGid
     * @param array<int, int> $glyphWidths
     * @param ?array<int, array<int, int>> $kernPairs
     * @param ?array<int, list<array{components: int[], ligature: int}>> $ligatures
     */
    private function makeFont(
        array $unicodeToGid,
        array $glyphWidths,
        ?array $kernPairs = null,
        ?array $ligatures = null,
        int $unitsPerEm = 1000,
    ): OpenTypeData {
        return new OpenTypeData(
            postScriptName: 'TestFont',
            familyName: 'Test',
            ascent: 800,
            descent: -200,
            capHeight: 700,
            xHeight: 500,
            italicAngle: 0.0,
            stemV: 80,
            flags: 0,
            fontBBox: [0, -200, 1000, 800],
            charWidths: [],
            unicodeMap: [],
            cffBytes: '',
            fontBytes: '',
            embeddingAllowed: true,
            unitsPerEm: $unitsPerEm,
            fullUnicodeToGid: $unicodeToGid,
            glyphWidths: $glyphWidths,
            kernPairs: $kernPairs,
            ligatures: $ligatures,
        );
    }

    public function testEmptyTextProducesEmptyRun(): void
    {
        $font = $this->makeFont([0x41 => 5], [5 => 500]);
        $run = $this->shaper->shapeRun('', new ShapingContext($font, 12.0));
        self::assertSame([], $run->glyphs);
        self::assertSame(0.0, $run->totalAdvance);
    }

    public function testSimpleAsciiShape(): void
    {
        // 'A' (0x41) → GID 5 (width 500), 'B' (0x42) → GID 6 (width 600)
        $font = $this->makeFont(
            unicodeToGid: [0x41 => 5, 0x42 => 6],
            glyphWidths: [5 => 500, 6 => 600],
        );
        $run = $this->shaper->shapeRun('AB', new ShapingContext($font, 12.0));
        self::assertCount(2, $run->glyphs);
        self::assertSame(5, $run->glyphs[0]->glyphId);
        self::assertSame(6, $run->glyphs[1]->glyphId);
        // 500 / 1000 × 12 = 6, 600 / 1000 × 12 = 7.2; total = 13.2
        self::assertEqualsWithDelta(6.0, $run->glyphs[0]->advanceX, 0.001);
        self::assertEqualsWithDelta(7.2, $run->glyphs[1]->advanceX, 0.001);
        self::assertEqualsWithDelta(13.2, $run->totalAdvance, 0.001);
    }

    public function testMissingCodepointReturnsNotdef(): void
    {
        $font = $this->makeFont([0x41 => 5], [5 => 500, 0 => 250]);
        $run = $this->shaper->shapeRun('AZ', new ShapingContext($font, 10.0));
        self::assertSame(5, $run->glyphs[0]->glyphId);
        // 'Z' isn't mapped — emits GID 0 (.notdef).
        self::assertSame(0, $run->glyphs[1]->glyphId);
    }

    public function testSourceOffsetsTrackBytes(): void
    {
        $font = $this->makeFont(
            unicodeToGid: [0x41 => 5, 0x42 => 6, 0x43 => 7],
            glyphWidths: [5 => 500, 6 => 600, 7 => 700],
        );
        $run = $this->shaper->shapeRun('ABC', new ShapingContext($font, 12.0));
        self::assertSame(0, $run->glyphs[0]->sourceOffset);
        self::assertSame(1, $run->glyphs[0]->sourceLength);
        self::assertSame(1, $run->glyphs[1]->sourceOffset);
        self::assertSame(2, $run->glyphs[2]->sourceOffset);
    }

    public function testMultibyteSourceOffsetsAreByteAccurate(): void
    {
        // U+00E9 'é' is 2 bytes in UTF-8; U+4E2D 中 is 3 bytes.
        $font = $this->makeFont(
            unicodeToGid: [0x00E9 => 10, 0x4E2D => 11],
            glyphWidths: [10 => 500, 11 => 1000],
        );
        $text = "\u{00E9}\u{4E2D}";
        $run = $this->shaper->shapeRun($text, new ShapingContext($font, 12.0));
        self::assertSame(0, $run->glyphs[0]->sourceOffset);
        self::assertSame(2, $run->glyphs[0]->sourceLength);
        self::assertSame(2, $run->glyphs[1]->sourceOffset);
        self::assertSame(3, $run->glyphs[1]->sourceLength);
    }

    public function testKerningAdjustsAdvance(): void
    {
        // 'A'→5, 'V'→6, kern pair (5, 6) = -50 (kern in).
        $font = $this->makeFont(
            unicodeToGid: [0x41 => 5, 0x56 => 6],
            glyphWidths: [5 => 500, 6 => 600],
            kernPairs: [5 => [6 => -50]],
        );
        $run = $this->shaper->shapeRun('AV', new ShapingContext($font, 1000.0));
        // Per-glyph: A → 500 - 50 = 450 design units → 450 * 1000/1000 = 450
        // (because unitsPerEm 1000 and fontSizePt 1000 gives scale 1.0)
        self::assertEqualsWithDelta(450.0, $run->glyphs[0]->advanceX, 0.001);
        self::assertEqualsWithDelta(600.0, $run->glyphs[1]->advanceX, 0.001);
    }

    public function testKerningDisabledWhenFeatureOff(): void
    {
        $font = $this->makeFont(
            unicodeToGid: [0x41 => 5, 0x56 => 6],
            glyphWidths: [5 => 500, 6 => 600],
            kernPairs: [5 => [6 => -50]],
        );
        $ctx = new ShapingContext($font, 1000.0, features: ['liga']);
        $run = $this->shaper->shapeRun('AV', $ctx);
        self::assertSame(500.0, $run->glyphs[0]->advanceX);
    }

    public function testLigatureSubstitutionMergesGlyphs(): void
    {
        // 'f' → 5, 'i' → 6 ; ligature rule: 5 + 6 → 99 (the 'fi' ligature glyph)
        $font = $this->makeFont(
            unicodeToGid: [0x66 => 5, 0x69 => 6],
            glyphWidths: [5 => 500, 6 => 300, 99 => 700],
            ligatures: [5 => [['components' => [6], 'ligature' => 99]]],
        );
        $run = $this->shaper->shapeRun('fi', new ShapingContext($font, 1000.0));
        self::assertCount(1, $run->glyphs, 'two codepoints fuse into one glyph');
        self::assertSame(99, $run->glyphs[0]->glyphId);
        // The merged glyph covers byte range [0, 2)
        self::assertSame(0, $run->glyphs[0]->sourceOffset);
        self::assertSame(2, $run->glyphs[0]->sourceLength);
    }

    public function testLigatureDisabledWhenFeatureOff(): void
    {
        $font = $this->makeFont(
            unicodeToGid: [0x66 => 5, 0x69 => 6],
            glyphWidths: [5 => 500, 6 => 300, 99 => 700],
            ligatures: [5 => [['components' => [6], 'ligature' => 99]]],
        );
        $ctx = new ShapingContext($font, 1000.0, features: ['kern']);
        $run = $this->shaper->shapeRun('fi', $ctx);
        self::assertCount(2, $run->glyphs, 'ligature disabled — both glyphs remain');
    }

    public function testTotalAdvanceMatchesSumOfGlyphs(): void
    {
        $font = $this->makeFont(
            unicodeToGid: [0x41 => 5, 0x42 => 6, 0x43 => 7],
            glyphWidths: [5 => 500, 6 => 600, 7 => 700],
        );
        $run = $this->shaper->shapeRun('ABC', new ShapingContext($font, 12.0));
        $sum = 0.0;
        foreach ($run->glyphs as $g) {
            $sum += $g->advanceX;
        }
        self::assertEqualsWithDelta($sum, $run->totalAdvance, 0.001);
    }

    public function testRtlRunPreservesLogicalOrder(): void
    {
        // Shaper doesn't visually reorder — that's the bidi reorderer's job.
        $font = $this->makeFont(
            unicodeToGid: [0x41 => 5, 0x42 => 6],
            glyphWidths: [5 => 500, 6 => 600],
        );
        $ctx = new ShapingContext($font, 12.0, direction: ShapingDirection::Rtl);
        $run = $this->shaper->shapeRun('AB', $ctx);
        self::assertSame(ShapingDirection::Rtl, $run->direction);
        self::assertSame(5, $run->glyphs[0]->glyphId);
        self::assertSame(6, $run->glyphs[1]->glyphId);
    }
}
