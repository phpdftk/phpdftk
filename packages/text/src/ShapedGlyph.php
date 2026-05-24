<?php

declare(strict_types=1);

namespace Phpdftk\Text;

/**
 * One positioned glyph in a {@see ShapedRun}.
 *
 * `glyphId` is the font's internal index; `sourceOffset` / `sourceLength`
 * point back into the original UTF-8 string so callers can round-trip
 * between glyphs and source characters (needed for selection, hit testing,
 * and copy-paste extraction).
 *
 * `advanceX` / `advanceY` are the glyph's contribution to the line's
 * advance after kerning is applied; offsets are positional tweaks from
 * GPOS mark / attachment data (zero for the simple shaper).
 *
 * All distances are in PDF user-space units (1pt = 1/72in) — i.e. already
 * scaled by `fontSizePt / unitsPerEm`. Layout consumes them directly without
 * needing the original font metrics.
 */
final readonly class ShapedGlyph
{
    public function __construct(
        public int $glyphId,
        public int $sourceOffset,
        public int $sourceLength,
        public float $advanceX,
        public float $advanceY = 0.0,
        public float $offsetX = 0.0,
        public float $offsetY = 0.0,
    ) {}
}
