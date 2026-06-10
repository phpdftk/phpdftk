<?php

declare(strict_types=1);

namespace Phpdftk\FontParser;

/**
 * Parser for the MathVariants sub-table of an OpenType MATH table.
 *
 * MathVariants layout:
 *
 *     uint16   minConnectorOverlap
 *     Offset16 vertGlyphCoverageOffset
 *     Offset16 horizGlyphCoverageOffset
 *     uint16   vertGlyphCount
 *     uint16   horizGlyphCount
 *     Offset16 vertGlyphConstructionOffsets[vertGlyphCount]
 *     Offset16 horizGlyphConstructionOffsets[horizGlyphCount]
 *
 * Each MathGlyphConstruction:
 *
 *     Offset16 glyphAssemblyOffset (0 if absent)
 *     uint16   variantCount
 *     MathGlyphVariantRecord variants[variantCount]:
 *         uint16 variantGlyph
 *         uint16 advanceMeasurement
 *
 * Each MathGlyphAssembly:
 *
 *     MathValueRecord italicsCorrection
 *     uint16 partCount
 *     GlyphPartRecord parts[partCount]:
 *         uint16 glyphID
 *         uint16 startConnectorLength
 *         uint16 endConnectorLength
 *         uint16 fullAdvance
 *         uint16 partFlags (bit 0 = extender)
 *
 * Coverage tables follow standard OpenType format 1 or 2.
 */
final class MathVariantsParser
{
    private string $data = '';

    public function parse(string $bytes): MathVariants
    {
        if (strlen($bytes) < 10) {
            return new MathVariants(0, [], []);
        }
        $this->data = $bytes;
        $minConnectorOverlap = $this->u16(0);
        $vertCoverageOffset = $this->u16(2);
        $horizCoverageOffset = $this->u16(4);
        $vertCount = $this->u16(6);
        $horizCount = $this->u16(8);

        $vertGlyphs = $vertCoverageOffset !== 0
            ? $this->parseCoverage($vertCoverageOffset)
            : [];
        $horizGlyphs = $horizCoverageOffset !== 0
            ? $this->parseCoverage($horizCoverageOffset)
            : [];

        $vertOffsetsBase = 10;
        $horizOffsetsBase = 10 + $vertCount * 2;

        $vertConstructions = [];
        $vertBound = min($vertCount, count($vertGlyphs));
        for ($i = 0; $i < $vertBound; $i++) {
            $offset = $this->u16($vertOffsetsBase + $i * 2);
            if ($offset === 0) {
                continue;
            }
            $vertConstructions[$vertGlyphs[$i]] = $this->parseConstruction($offset);
        }

        $horizConstructions = [];
        $horizBound = min($horizCount, count($horizGlyphs));
        for ($i = 0; $i < $horizBound; $i++) {
            $offset = $this->u16($horizOffsetsBase + $i * 2);
            if ($offset === 0) {
                continue;
            }
            $horizConstructions[$horizGlyphs[$i]] = $this->parseConstruction($offset);
        }

        return new MathVariants(
            minConnectorOverlap: $minConnectorOverlap,
            verticalConstructions: $vertConstructions,
            horizontalConstructions: $horizConstructions,
        );
    }

    private function parseConstruction(int $base): MathGlyphConstruction
    {
        $assemblyOffset = $this->u16($base);
        $variantCount = $this->u16($base + 2);
        $variants = [];
        for ($i = 0; $i < $variantCount; $i++) {
            $variants[] = [
                'glyphId' => $this->u16($base + 4 + $i * 4),
                'advance' => $this->u16($base + 4 + $i * 4 + 2),
            ];
        }

        $assembly = null;
        if ($assemblyOffset !== 0) {
            // The assembly offset is relative to the construction base.
            $assembly = $this->parseAssembly($base + $assemblyOffset);
        }

        return new MathGlyphConstruction($variants, $assembly);
    }

    private function parseAssembly(int $base): MathGlyphAssembly
    {
        // MathValueRecord italicsCorrection: Int16 FWord + uint16 device.
        $italics = $this->i16($base);
        $partCount = $this->u16($base + 4);
        $parts = [];
        for ($i = 0; $i < $partCount; $i++) {
            $rb = $base + 6 + $i * 10;
            $parts[] = [
                'glyphId' => $this->u16($rb),
                'startConnector' => $this->u16($rb + 2),
                'endConnector' => $this->u16($rb + 4),
                'fullAdvance' => $this->u16($rb + 6),
                'extender' => ($this->u16($rb + 8) & 0x0001) !== 0,
            ];
        }
        return new MathGlyphAssembly(italicsCorrection: $italics, parts: $parts);
    }

    /**
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
                $rb = $offset + 4 + $i * 6;
                $start = $this->u16($rb);
                $end = $this->u16($rb + 2);
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
