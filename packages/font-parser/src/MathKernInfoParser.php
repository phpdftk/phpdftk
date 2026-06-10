<?php

declare(strict_types=1);

namespace Phpdftk\FontParser;

/**
 * Parser for the MathKernInfo sub-table of an OpenType MATH table.
 *
 * Layout:
 *
 *     Offset16 mathKernCoverageOffset
 *     uint16   mathKernCount
 *     MathKernInfoRecord records[count]:
 *         Offset16 topRightMathKernOffset
 *         Offset16 topLeftMathKernOffset
 *         Offset16 bottomRightMathKernOffset
 *         Offset16 bottomLeftMathKernOffset
 *
 * Each MathKern (per-corner) layout:
 *
 *     uint16 heightCount
 *     MathValueRecord correctionHeights[heightCount]
 *     MathValueRecord kernValues[heightCount + 1]
 *
 * All offsets are relative to the start of MathKernInfo (the
 * sub-table this parser receives as bytes).
 *
 * Empty / truncated input returns an empty {@see MathKernInfo}
 * rather than throwing - fonts in the wild ship partial tables.
 */
final class MathKernInfoParser
{
    private string $data = '';

    public function parse(string $bytes): MathKernInfo
    {
        if (strlen($bytes) < 4) {
            return new MathKernInfo([]);
        }
        $this->data = $bytes;
        $coverageOffset = $this->u16(0);
        $count = $this->u16(2);
        if ($coverageOffset === 0 || $count === 0) {
            return new MathKernInfo([]);
        }
        $glyphs = $this->parseCoverage($coverageOffset);
        $records = [];
        $recordsBase = 4;
        $bound = min($count, count($glyphs));
        for ($i = 0; $i < $bound; $i++) {
            $recBase = $recordsBase + $i * 8;
            $tr = $this->u16($recBase);
            $tl = $this->u16($recBase + 2);
            $br = $this->u16($recBase + 4);
            $bl = $this->u16($recBase + 6);
            $records[$glyphs[$i]] = new MathKernRecord(
                topRight: $tr !== 0 ? $this->parseKern($tr) : null,
                topLeft: $tl !== 0 ? $this->parseKern($tl) : null,
                bottomRight: $br !== 0 ? $this->parseKern($br) : null,
                bottomLeft: $bl !== 0 ? $this->parseKern($bl) : null,
            );
        }
        return new MathKernInfo($records);
    }

    private function parseKern(int $offset): MathKern
    {
        $heightCount = $this->u16($offset);
        $heights = [];
        $heightBase = $offset + 2;
        for ($i = 0; $i < $heightCount; $i++) {
            // MathValueRecord = Int16 FWord + uint16 device offset.
            $heights[] = $this->i16($heightBase + $i * 4);
        }
        $valuesBase = $heightBase + $heightCount * 4;
        $kerns = [];
        $kernCount = $heightCount + 1;
        for ($i = 0; $i < $kernCount; $i++) {
            $kerns[] = $this->i16($valuesBase + $i * 4);
        }
        return new MathKern(correctionHeights: $heights, kernValues: $kerns);
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
