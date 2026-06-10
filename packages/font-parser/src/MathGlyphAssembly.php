<?php

declare(strict_types=1);

namespace Phpdftk\FontParser;

/**
 * Recipe for building a stretchy delimiter from glyph parts.
 *
 * Parts stack head-to-tail in coverage order. Each part declares:
 *   - its `glyphId` (the part to draw),
 *   - the `startConnector` length (how many design units the start
 *     edge can overlap with the previous part's end edge),
 *   - the `endConnector` length (matching cap for the next part),
 *   - the `fullAdvance` of the part if drawn alone,
 *   - a bit flag in `extender` indicating whether the part is a
 *     repeatable extender (stretches as needed) or a fixed end-cap.
 *
 * The painter assembles by:
 *   1. Picking the fixed (non-extender) caps in order.
 *   2. Repeating the extender(s) between them as many times as
 *      needed to hit the target advance.
 *   3. Overlapping adjacent parts by at least
 *      `MathVariants::minConnectorOverlap`.
 */
final readonly class MathGlyphAssembly
{
    /**
     * @param int $italicsCorrection FUnit italic correction applied
     *        to the full assembly when it sits next to slanted
     *        content.
     * @param list<array{glyphId: int, startConnector: int, endConnector: int, fullAdvance: int, extender: bool}> $parts
     */
    public function __construct(
        public int $italicsCorrection,
        public array $parts,
    ) {}
}
