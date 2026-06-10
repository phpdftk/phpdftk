<?php

declare(strict_types=1);

namespace Phpdftk\FontParser;

/**
 * One stretchy delimiter recipe from a {@see MathVariants} table.
 *
 * Two pieces of data per base glyph:
 *
 *   - `variants`: a list of pre-drawn alternate glyphs at fixed
 *     sizes. The painter scans this list smallest-first and uses
 *     the first whose `advance` (in design units) is >= the
 *     required size.
 *
 *   - `assembly`: optional fall-back when no variant is large
 *     enough - a recipe for stacking glyph parts to any height. Null
 *     if the font doesn't supply one for this glyph (most
 *     production fonts do).
 */
final readonly class MathGlyphConstruction
{
    /**
     * @param list<array{glyphId: int, advance: int}> $variants
     *        Pre-drawn stretchy variants, sorted smallest first per
     *        spec.
     * @param ?MathGlyphAssembly $assembly
     *        Multi-part glyph assembly for arbitrary sizes, or null
     *        when the font supplies no assembly.
     */
    public function __construct(
        public array $variants,
        public ?MathGlyphAssembly $assembly,
    ) {}
}
