<?php

declare(strict_types=1);

namespace Phpdftk\FontParser;

/**
 * Common shape for a parsed font face — the fields any downstream
 * consumer needs to lay out, shape, and embed text.
 *
 * Subclassed by {@see OpenTypeData} (CFF outlines, sfVersion `OTTO`)
 * and {@see TrueTypeData} (glyf/loca outlines, sfVersion `0x00010000`).
 * Both carry the same metric and glyph-mapping fields; the format-
 * specific parts (CFF table bytes vs raw TTF glyf data) live on the
 * subclass.
 *
 * The base class itself is `abstract readonly` so the field set is
 * fixed at construction and the type hierarchy stays narrow: code
 * that hits a metric or a glyph lookup uses `FontFaceData`, and code
 * that has to embed the bytes into a PDF stream does an `instanceof`
 * check on the subclass to pick the CFF or TrueType embed path.
 *
 * Optional fields on the base — `verticalWidths`, `underlinePosition`,
 * `underlineThickness`, `mathTable` — live here so any code that
 * reads them via the polymorphic type can do so safely. Subclasses
 * that don't carry the source table just leave the field null at
 * construction.
 */
abstract readonly class FontFaceData
{
    /**
     * @param array<int, int>  $fontBBox        [xMin, yMin, xMax, yMax] in 1000 units/em
     * @param array<int, int>  $charWidths      WinAnsi byte (32-255) → advance (1000 units/em)
     * @param array<int, int>  $unicodeMap      WinAnsi byte → Unicode codepoint
     * @param array<int, int>  $fullUnicodeToGid  Unicode codepoint → GID
     * @param array<int, int>  $glyphWidths       GID → advance (design units, unscaled)
     * @param ?array<int, array<int, int>> $kernPairs  leftGid → [rightGid → xAdvanceAdjust]
     * @param ?array<int, list<array{components: int[], ligature: int}>> $ligatures  firstGid → ligature rules
     * @param ?array<int, int> $verticalWidths  GID → vertical advance (design units)
     * @param ?int $underlinePosition `post` table FWord — offset of the underline's top edge from
     *        the baseline (design units; negative = below baseline). Null if the table is missing.
     * @param ?int $underlineThickness `post` table FWord — underline stroke thickness in design units.
     * @param ?MathTableData $mathTable  Parsed `MATH` table when the font has one.
     */
    public function __construct(
        public string $postScriptName,
        public string $familyName,
        public int $ascent,
        public int $descent,
        public int $capHeight,
        public int $xHeight,
        public float $italicAngle,
        public int $stemV,
        public int $flags,
        public array $fontBBox,
        public array $charWidths,
        public array $unicodeMap,
        public string $fontBytes,
        public bool $embeddingAllowed,
        public int $unitsPerEm = 1000,
        public array $fullUnicodeToGid = [],
        public array $glyphWidths = [],
        public ?array $kernPairs = null,
        public ?array $ligatures = null,
        public ?array $verticalWidths = null,
        public ?int $underlinePosition = null,
        public ?int $underlineThickness = null,
        public ?MathTableData $mathTable = null,
    ) {}
}
