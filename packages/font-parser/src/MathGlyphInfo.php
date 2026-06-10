<?php

declare(strict_types=1);

namespace Phpdftk\FontParser;

/**
 * Parsed MathGlyphInfo sub-table from an OpenType MATH table.
 *
 * Spec: https://learn.microsoft.com/en-us/typography/opentype/spec/math#mathglyphinfo-table
 *
 * Holds per-glyph data the painter consults at layout time:
 *
 *   - Italic-correction values: extra horizontal space to add after
 *     an italic-shaped glyph that an upright follower (subscripts,
 *     superscripts to the right of a slanted base) should bridge.
 *
 *   - Top-accent attachment offsets: where a top accent
 *     (`<mover>` over an `<mi>x</mi>`) should attach horizontally.
 *
 *   - Extended-shape coverage: glyph IDs that should be drawn with
 *     script-script vertical metrics even at script level (used by
 *     spec for very large operators / radical signs).
 *
 *   - Math kern info: per-glyph corner kerning at the four sub/sup
 *     attachment points. Carried as raw bytes pending a dedicated
 *     parser in a follow-up slice - the structure is per-glyph
 *     CorrectionHeight + KernValue arrays at each corner.
 *
 * Each map's keys are glyph IDs (GIDs), values are FUnit shifts.
 */
final readonly class MathGlyphInfo
{
    /**
     * @param array<int, int> $italicCorrections   gid → FUnit italic correction.
     * @param array<int, int> $topAccentAttachments gid → FUnit top-accent attachment offset.
     * @param array<int, true> $extendedShapes     gid set (only keys matter).
     * @param string          $kernInfoBytes       Raw MathKernInfo sub-table bytes (parsed later).
     */
    public function __construct(
        public array $italicCorrections,
        public array $topAccentAttachments,
        public array $extendedShapes,
        public string $kernInfoBytes,
    ) {}
}
