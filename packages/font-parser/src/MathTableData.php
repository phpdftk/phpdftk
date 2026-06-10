<?php

declare(strict_types=1);

namespace Phpdftk\FontParser;

/**
 * Parsed `MATH` table from an OpenType math font (Latin Modern Math,
 * STIX Two Math, Cambria Math, ...). Defined in the OpenType spec
 * https://learn.microsoft.com/en-us/typography/opentype/spec/math.
 *
 * The MATH table holds the per-font constants and glyph metadata
 * MathML and TeX-style typesetting need to lay out fractions,
 * scripts, radicals, large operators, and stretchy delimiters with
 * font-correct proportions instead of guessed em-fractions.
 *
 * v1 of this value object captures just the table header and the
 * three sub-table byte ranges. Each sub-table parses on demand in
 * a follow-up slice:
 *
 *   - `MathConstants`   - ~50 layout constants (script percent,
 *                         axis height, fraction shape, etc.).
 *   - `MathGlyphInfo`   - per-glyph italic correction, top-accent
 *                         attachment, extended-shape membership,
 *                         glyph-script percent overrides.
 *   - `MathVariants`    - stretchy delimiter chains + glyph
 *                         assembly recipes.
 *
 * Storing the raw bytes for each sub-table keeps this slice tight:
 * the OpenTypeParser only locates them; the parsers for each
 * sub-table live in their own classes that consume these byte
 * ranges and return strongly-typed values.
 */
final readonly class MathTableData
{
    /**
     * @param int    $majorVersion          MATH table major version (1).
     * @param int    $minorVersion          MATH table minor version (0).
     * @param string $mathConstantsBytes    Raw bytes of the MathConstants sub-table.
     *                                      Empty when the offset is zero (sub-table absent).
     * @param string $mathGlyphInfoBytes    Raw bytes of the MathGlyphInfo sub-table.
     * @param string $mathVariantsBytes     Raw bytes of the MathVariants sub-table.
     */
    public function __construct(
        public int $majorVersion,
        public int $minorVersion,
        public string $mathConstantsBytes,
        public string $mathGlyphInfoBytes,
        public string $mathVariantsBytes,
    ) {}

    public function hasMathConstants(): bool
    {
        return $this->mathConstantsBytes !== '';
    }

    public function hasMathGlyphInfo(): bool
    {
        return $this->mathGlyphInfoBytes !== '';
    }

    public function hasMathVariants(): bool
    {
        return $this->mathVariantsBytes !== '';
    }
}
