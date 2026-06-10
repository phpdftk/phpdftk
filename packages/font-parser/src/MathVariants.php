<?php

declare(strict_types=1);

namespace Phpdftk\FontParser;

/**
 * Parsed MathVariants sub-table from an OpenType MATH table.
 *
 * Spec: https://learn.microsoft.com/en-us/typography/opentype/spec/math#mathvariants-table
 *
 * Holds the data the painter uses to render stretchy delimiters
 * (parentheses, brackets, integral signs, radical signs, arrows) at
 * arbitrary heights / widths:
 *
 *   - A list of pre-drawn glyph variants at fixed sizes, sorted from
 *     smallest to largest. The painter picks the smallest variant
 *     whose advance exceeds the target.
 *
 *   - An optional glyph "assembly" that describes how to build a
 *     delimiter at any size by stacking three or four glyph parts
 *     (top + middle(s) + bottom for vertical; left + middle(s) +
 *     right for horizontal).
 *
 * Vertical stretchy glyphs (parens, brackets) and horizontal
 * stretchy glyphs (arrows, over/underbraces) are tracked in separate
 * maps keyed by the base glyph ID.
 */
final readonly class MathVariants
{
    /**
     * @param int $minConnectorOverlap Minimum overlap (in design
     *        units) required between adjacent parts in a glyph
     *        assembly.
     * @param array<int, MathGlyphConstruction> $verticalConstructions
     *        Base-glyph-ID → vertical construction. Used for tall
     *        delimiters / radicals.
     * @param array<int, MathGlyphConstruction> $horizontalConstructions
     *        Base-glyph-ID → horizontal construction. Used for wide
     *        arrows / over/under-braces.
     */
    public function __construct(
        public int $minConnectorOverlap,
        public array $verticalConstructions,
        public array $horizontalConstructions,
    ) {}
}
