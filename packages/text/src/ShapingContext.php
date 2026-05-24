<?php

declare(strict_types=1);

namespace Phpdftk\Text;

use Phpdftk\FontParser\OpenTypeData;

/**
 * Per-run context for the shaper: the font, size, script, language, and
 * direction. Higher-level callers split mixed input into runs (one font +
 * one direction + one script each) before calling {@see Shaper::shapeRun}.
 *
 * `features` is the list of OpenType feature tags to enable — at MVP the
 * shaper applies `kern` and `liga` if the font carries them. Other tags
 * (small caps, lining figures, etc.) are recognised by the API but treated
 * as no-ops until the broader GSUB / GPOS work in Phase 2.
 */
final readonly class ShapingContext
{
    /** @param list<string> $features */
    public function __construct(
        public OpenTypeData $font,
        public float $fontSizePt,
        public string $script = 'Latn',
        public string $language = 'en',
        public ShapingDirection $direction = ShapingDirection::Ltr,
        public array $features = ['kern', 'liga'],
    ) {}
}
