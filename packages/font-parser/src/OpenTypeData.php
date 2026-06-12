<?php

declare(strict_types=1);

namespace Phpdftk\FontParser;

/**
 * Parsed OpenType CFF font data — metrics, glyph widths, and raw CFF bytes.
 *
 * Mirrors TrueTypeData but adds the CFF table bytes and stores the
 * full raw font file for potential whole-file embedding. Both extend
 * {@see FontFaceData} so consumers that don't care about the outline
 * format (Shaper, FontResolver, layout) can take the base type, and
 * the PdfWriter dispatches CFF vs glyf embedding via `instanceof`.
 */
readonly class OpenTypeData extends FontFaceData
{
    /**
     * @param string $postScriptName  PostScript name (name table ID 6)
     * @param string $familyName      Family name (name table ID 1)
     * @param int    $ascent          Typographic ascender (1000 units/em)
     * @param int    $descent         Typographic descender (negative, 1000 units/em)
     * @param int    $capHeight       Cap height (1000 units/em)
     * @param int    $xHeight         x-height (1000 units/em)
     * @param float  $italicAngle     Italic angle in degrees
     * @param int    $stemV           Estimated vertical stem width
     * @param int    $flags           PDF font flags bitmask
     * @param array<int, int>  $fontBBox        [xMin, yMin, xMax, yMax] (1000 units/em)
     * @param array<int, int>  $charWidths      WinAnsi byte (32-255) → width (1000 units/em)
     * @param array<int, int>  $unicodeMap      WinAnsi byte (32-255) → Unicode codepoint
     * @param string $cffBytes        Raw CFF table bytes (for /FontFile3 /Subtype /CIDFontType0C)
     * @param string $fontBytes       Raw OTF file bytes (for /FontFile3 /Subtype /OpenType)
     * @param bool   $embeddingAllowed fsType restriction check
     * @param int    $unitsPerEm      Font design units per em
     * @param array<int, int>  $fullUnicodeToGid All Unicode → GID mappings
     * @param array<int, int>  $glyphWidths     GID → advance width (design units)
     * @param ?array<int, array<int, int>> $kernPairs leftGid => [rightGid => xAdvanceAdjust] (design units)
     * @param ?array<int, list<array{components: int[], ligature: int}>> $ligatures firstGid => ligature rules
     * @param ?array<int, int> $verticalWidths GID => vertical advance width (design units)
     * @param ?int $underlinePosition `post` table FWord — offset of the underline's top edge from
     *        the baseline (design units; negative = below baseline). Null if the table is missing.
     * @param ?int $underlineThickness `post` table FWord — underline stroke thickness in design units.
     * @param ?MathTableData $mathTable Parsed `MATH` table when the font has one (Latin Modern Math,
     *        STIX Two Math, Cambria Math, ...). Null for non-math fonts.
     */
    public function __construct(
        string $postScriptName,
        string $familyName,
        int $ascent,
        int $descent,
        int $capHeight,
        int $xHeight,
        float $italicAngle,
        int $stemV,
        int $flags,
        array $fontBBox,
        array $charWidths,
        array $unicodeMap,
        public string $cffBytes,
        string $fontBytes,
        bool $embeddingAllowed,
        int $unitsPerEm = 1000,
        array $fullUnicodeToGid = [],
        array $glyphWidths = [],
        ?array $kernPairs = null,
        ?array $ligatures = null,
        ?array $verticalWidths = null,
        ?int $underlinePosition = null,
        ?int $underlineThickness = null,
        ?MathTableData $mathTable = null,
    ) {
        parent::__construct(
            $postScriptName,
            $familyName,
            $ascent,
            $descent,
            $capHeight,
            $xHeight,
            $italicAngle,
            $stemV,
            $flags,
            $fontBBox,
            $charWidths,
            $unicodeMap,
            $fontBytes,
            $embeddingAllowed,
            $unitsPerEm,
            $fullUnicodeToGid,
            $glyphWidths,
            $kernPairs,
            $ligatures,
            $verticalWidths,
            $underlinePosition,
            $underlineThickness,
            $mathTable,
        );
    }
}
