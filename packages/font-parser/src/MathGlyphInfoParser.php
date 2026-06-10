<?php

declare(strict_types=1);

namespace Phpdftk\FontParser;

/**
 * Parser for the MathGlyphInfo sub-table of an OpenType MATH table.
 *
 * The sub-table is a 4-offset header pointing to four optional
 * inner tables. Each may be absent (offset == 0). Layout:
 *
 *     Offset16 mathItalicsCorrectionInfoOffset
 *     Offset16 mathTopAccentAttachmentOffset
 *     Offset16 extendedShapeCoverageOffset
 *     Offset16 mathKernInfoOffset
 *
 * MathItalicsCorrectionInfo and MathTopAccentAttachment have
 * identical shape:
 *
 *     Offset16 coverageOffset            -- Coverage table
 *     uint16   count
 *     MathValueRecord values[count]      -- FWord + uint16 device offset
 *
 * The Coverage table indexes glyphs in the values array (i-th
 * covered glyph -> values[i]). Format 1 (list) and format 2 (ranges)
 * both produce a glyph-ID list in coverage order.
 *
 * ExtendedShapeCoverage is just a Coverage table - no value array.
 *
 * MathKernInfo is more complex (per-corner kerning records) and
 * lands in its own slice; this parser returns its raw bytes.
 */
final class MathGlyphInfoParser
{
    /**
     * @var string Raw MathGlyphInfo sub-table bytes (kept on the
     *             instance so the offset helpers can read against it).
     */
    private string $data = '';

    public function parse(string $bytes): MathGlyphInfo
    {
        if (strlen($bytes) < 8) {
            // Empty / truncated - return an "empty" struct rather
            // than throw, so a math font with a partial table doesn't
            // crash the whole parse.
            return new MathGlyphInfo([], [], [], '');
        }
        $this->data = $bytes;
        $italicsOffset = $this->u16(0);
        $accentOffset = $this->u16(2);
        $extendedOffset = $this->u16(4);
        $kernInfoOffset = $this->u16(6);

        $italicCorrections = $italicsOffset !== 0
            ? $this->parseValueTable($italicsOffset)
            : [];

        $topAccentAttachments = $accentOffset !== 0
            ? $this->parseValueTable($accentOffset)
            : [];

        $extendedShapes = [];
        if ($extendedOffset !== 0) {
            foreach ($this->parseCoverage($extendedOffset) as $gid) {
                $extendedShapes[$gid] = true;
            }
        }

        $kernBytes = '';
        if ($kernInfoOffset !== 0) {
            $kernBytes = substr($this->data, $kernInfoOffset);
        }

        return new MathGlyphInfo(
            italicCorrections: $italicCorrections,
            topAccentAttachments: $topAccentAttachments,
            extendedShapes: $extendedShapes,
            kernInfoBytes: $kernBytes,
        );
    }

    /**
     * Parse a coverage + value-array sub-table (the shape shared by
     * MathItalicsCorrectionInfo and MathTopAccentAttachment).
     *
     * @return array<int, int> gid -> FUnit value
     */
    private function parseValueTable(int $base): array
    {
        $coverageOffset = $base + $this->u16($base);
        $count = $this->u16($base + 2);
        $valuesBase = $base + 4;
        $glyphs = $this->parseCoverage($coverageOffset);
        $out = [];
        $bound = min($count, count($glyphs));
        for ($i = 0; $i < $bound; $i++) {
            // MathValueRecord = Int16 FWord + uint16 device offset.
            // Skip the device offset; the painter doesn't hint.
            $fword = $this->i16($valuesBase + $i * 4);
            $out[$glyphs[$i]] = $fword;
        }
        return $out;
    }

    /**
     * Parse a Coverage table at the given offset within $this->data.
     * Both format 1 (glyph list) and format 2 (range records) yield
     * the same flat list of glyph IDs in coverage order.
     *
     * @return list<int>
     */
    private function parseCoverage(int $offset): array
    {
        if ($offset < 0 || $offset + 4 > strlen($this->data)) {
            return [];
        }
        $format = $this->u16($offset);
        if ($format === 1) {
            $count = $this->u16($offset + 2);
            $glyphs = [];
            for ($i = 0; $i < $count; $i++) {
                $glyphs[] = $this->u16($offset + 4 + $i * 2);
            }
            return $glyphs;
        }
        if ($format === 2) {
            $rangeCount = $this->u16($offset + 2);
            $glyphs = [];
            for ($i = 0; $i < $rangeCount; $i++) {
                $base = $offset + 4 + $i * 6;
                $start = $this->u16($base);
                $end = $this->u16($base + 2);
                // RangeRecord also carries a startCoverageIndex at
                // base+4 but we don't need it - coverage index is
                // implied by position in the glyphs list.
                for ($g = $start; $g <= $end; $g++) {
                    $glyphs[] = $g;
                }
            }
            return $glyphs;
        }
        return [];
    }

    private function u16(int $offset): int
    {
        if ($offset < 0 || $offset + 1 >= strlen($this->data)) {
            return 0;
        }
        return unpack('n', $this->data, $offset)[1];
    }

    private function i16(int $offset): int
    {
        $v = $this->u16($offset);
        return $v >= 0x8000 ? $v - 0x10000 : $v;
    }
}
