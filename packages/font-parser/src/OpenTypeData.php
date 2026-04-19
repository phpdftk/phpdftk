<?php

declare(strict_types=1);

namespace ApprLabs\FontParser;

/**
 * Parsed OpenType CFF font data — metrics, glyph widths, and raw CFF bytes.
 *
 * Mirrors TrueTypeData but adds the CFF table bytes and stores the
 * full raw font file for potential whole-file embedding.
 */
readonly class OpenTypeData
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
        public string $cffBytes,
        public string $fontBytes,
        public bool $embeddingAllowed,
        public int $unitsPerEm = 1000,
        public array $fullUnicodeToGid = [],
        public array $glyphWidths = [],
        public ?array $kernPairs = null,
        public ?array $ligatures = null,
        public ?array $verticalWidths = null,
    ) {}
}
