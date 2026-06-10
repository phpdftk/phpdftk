<?php

declare(strict_types=1);

namespace Phpdftk\FontParser;

/**
 * Parsed MathKernInfo sub-table from an OpenType MATH table.
 *
 * Spec: https://learn.microsoft.com/en-us/typography/opentype/spec/math#mathkerninfo-table
 *
 * Holds per-glyph corner kerning the painter uses to nudge
 * sub/superscripts around italic-shaped bases. Each base glyph can
 * supply up to four corner-specific kern tables:
 *
 *   - topRight       used when a superscript follows the base
 *   - topLeft        used when a presuperscript precedes the base
 *   - bottomRight    used when a subscript follows the base
 *   - bottomLeft     used when a presubscript precedes the base
 *
 * Each corner table is a piecewise function: at increasing
 * "correction heights" (Y offsets from baseline), the painter looks
 * up the corresponding horizontal kern value to add.
 *
 * Keyed by base glyph ID. Glyphs absent from the map carry no
 * corner kerning - the painter applies the default zero.
 */
final readonly class MathKernInfo
{
    /**
     * @param array<int, MathKernRecord> $records Base GID → corner kern table.
     */
    public function __construct(
        public array $records,
    ) {}
}
