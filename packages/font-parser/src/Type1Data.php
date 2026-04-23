<?php

declare(strict_types=1);

namespace ApprLabs\FontParser;

/**
 * Parsed data from a Type 1 font (PFB/PFA).
 *
 * Mirrors TrueTypeData but carries Type 1–specific segment lengths
 * needed for the /FontFile stream dictionary (/Length1, /Length2, /Length3).
 */
readonly class Type1Data
{
    /**
     * @param string $postScriptName  PostScript font name (from /FontName)
     * @param string $familyName      Family name (from /FullName or /FamilyName)
     * @param int    $ascent          Typographic ascender scaled to 1000 units/em
     * @param int    $descent         Typographic descender (negative) scaled to 1000 units/em
     * @param int    $capHeight       Cap height scaled to 1000 units/em
     * @param int    $xHeight         x-height scaled to 1000 units/em (0 if unavailable)
     * @param float  $italicAngle     Italic angle in degrees
     * @param int    $stemV           Vertical stem width estimate
     * @param int    $flags           PDF font flags bitmask (ISO 32000-2 Table 123)
     * @param array<int, int>  $fontBBox       [xMin, yMin, xMax, yMax] (1000 units/em)
     * @param array<int, int>  $charWidths     byte (0-255) => advance width (1000 units/em)
     * @param array<int, int>  $unicodeMap     byte (0-255) => Unicode codepoint
     * @param string $fontBytes       Raw font file bytes (PFB format for embedding)
     * @param int    $length1         ASCII header segment length
     * @param int    $length2         Encrypted (binary) segment length
     * @param int    $length3         Trailer (zeros) segment length
     * @param array<string, int> $glyphWidths  glyphName => advance width (1000 units/em)
     * @param array<int, string> $encoding     byte (0-255) => glyph name
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
        public int $length1,
        public int $length2,
        public int $length3,
        public array $glyphWidths = [],
        public array $encoding = [],
    ) {}
}
