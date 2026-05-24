<?php

declare(strict_types=1);

namespace Phpdftk\Text;

use Phpdftk\FontParser\OpenTypeData;

/**
 * Output of {@see Shaper::shapeRun} — a sequence of positioned glyphs all
 * sharing the same font, size, direction, and script.
 *
 * `totalAdvance` is the sum of `advanceX` across glyphs (or `advanceY` for
 * vertical runs), cached so layout doesn't have to re-sum on every line-
 * fitting iteration.
 */
final readonly class ShapedRun
{
    /** @param list<ShapedGlyph> $glyphs */
    public function __construct(
        public OpenTypeData $font,
        public float $fontSizePt,
        public ShapingDirection $direction,
        public array $glyphs,
        public float $totalAdvance,
    ) {}
}
