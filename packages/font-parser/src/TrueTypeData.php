<?php

declare(strict_types=1);

namespace ApprLabs\FontParser;

readonly class TrueTypeData
{
    /**
     * @param string $postScriptName  PostScript name (name table ID 6)
     * @param string $familyName      Family name (name table ID 1)
     * @param int    $ascent          Typographic ascender scaled to 1000 units/em
     * @param int    $descent         Typographic descender (negative) scaled to 1000 units/em
     * @param int    $capHeight       Cap height scaled to 1000 units/em
     * @param int    $xHeight         x-height scaled to 1000 units/em
     * @param float  $italicAngle     Italic angle in degrees (from post table)
     * @param int    $stemV           Estimated vertical stem width (for PDF FontDescriptor)
     * @param int    $flags           PDF font flags bitmask (ISO 32000-2 Table 123)
     * @param array<int, int>  $fontBBox        [xMin, yMin, xMax, yMax] scaled to 1000 units/em
     * @param array<int, int>  $charWidths      WinAnsi byte (32-255) => advance width (1000 units/em)
     * @param array<int, int>  $unicodeMap      WinAnsi byte (32-255) => Unicode codepoint (only bytes with valid glyphs)
     * @param string $fontBytes       Raw TTF file bytes
     * @param bool   $embeddingAllowed false when fsType bits 1-2 indicate restricted licence (value 2)
     * @param int    $unitsPerEm      Font design units per em (from head table)
     * @param array<int, int>  $fullUnicodeToGid  All Unicode codepoint => GID mappings from cmap
     * @param array<int, int>  $glyphWidths       GID => advance width in font design units (unscaled)
     * @param ?array<int, array<int, int>> $kernPairs leftGid => [rightGid => xAdvanceAdjust] (design units)
     * @param ?array<int, list<array{components: int[], ligature: int}>> $ligatures firstGid => ligature rules
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
    ) {}
}
