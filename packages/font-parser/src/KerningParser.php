<?php

declare(strict_types=1);

namespace Phpdftk\FontParser;

/**
 * Parses kerning data from GPOS table (or legacy kern table).
 *
 * Extracts horizontal kerning pairs for the "kern" feature. Supports:
 * - GPOS PairPosFormat1 (individual pairs)
 * - GPOS PairPosFormat2 (class-based pairs)
 * - GPOS Extension lookups (LookupType 9)
 * - Legacy kern table format 0
 *
 * Returns leftGid => [rightGid => xAdvanceAdjust] in font design units.
 * Negative values = tighten (move glyphs closer).
 */
final class KerningParser
{
    private string $data;

    /**
     * @param string $fontBytes Raw font file bytes
     * @param array<string, array{offset:int, length:int}> $tables Table directory
     * @return array<int, array<int, int>> leftGid => [rightGid => xAdvanceAdjust]
     */
    public function parse(string $fontBytes, array $tables): array
    {
        $this->data = $fontBytes;

        // Try GPOS first
        if (isset($tables['GPOS'])) {
            $result = $this->parseGpos($tables['GPOS']['offset'], $tables['GPOS']['length']);
            if ($result !== []) {
                return $result;
            }
        }

        // Fall back to legacy kern table
        if (isset($tables['kern'])) {
            return $this->parseKernTable($tables['kern']['offset'], $tables['kern']['length']);
        }

        return [];
    }

    /**
     * @return array<int, array<int, int>>
     */
    private function parseGpos(int $offset, int $length): array
    {
        // GPOS header: Version(4) ScriptListOffset(2) FeatureListOffset(2) LookupListOffset(2)
        $scriptListOffset = $offset + $this->readUint16($offset + 4);
        $featureListOffset = $offset + $this->readUint16($offset + 6);
        $lookupListOffset = $offset + $this->readUint16($offset + 8);

        // Find "kern" feature indices via ScriptList
        $kernFeatureIndices = $this->findKernFeatureIndices($scriptListOffset, $featureListOffset);
        if ($kernFeatureIndices === []) {
            return [];
        }

        // Get lookup indices from kern features
        $lookupIndices = $this->getLookupIndicesFromFeatures($featureListOffset, $kernFeatureIndices);
        if ($lookupIndices === []) {
            return [];
        }

        // Parse lookups for PairPos (type 2)
        return $this->parsePairPosLookups($lookupListOffset, $lookupIndices);
    }

    /**
     * Find feature indices for the "kern" feature from ScriptList.
     *
     * @return int[] Feature indices
     */
    private function findKernFeatureIndices(int $scriptListOffset, int $featureListOffset): array
    {
        // Parse FeatureList to find "kern" features by tag
        $featureCount = $this->readUint16($featureListOffset);
        $kernFeatureIndices = [];

        for ($i = 0; $i < $featureCount; $i++) {
            $recOffset = $featureListOffset + 2 + $i * 6;
            $tag = substr($this->data, $recOffset, 4);
            if ($tag === 'kern') {
                $kernFeatureIndices[] = $i;
            }
        }

        return $kernFeatureIndices;
    }

    /**
     * @param int[] $featureIndices
     * @return int[] Lookup list indices
     */
    private function getLookupIndicesFromFeatures(int $featureListOffset, array $featureIndices): array
    {
        $lookupIndices = [];
        $featureCount = $this->readUint16($featureListOffset);

        foreach ($featureIndices as $fi) {
            if ($fi >= $featureCount) {
                continue;
            }
            $recOffset = $featureListOffset + 2 + $fi * 6;
            $featureTableOffset = $featureListOffset + $this->readUint16($recOffset + 4);

            // Feature table: FeatureParams(2) LookupCount(2) LookupListIndex[]
            $lookupCount = $this->readUint16($featureTableOffset + 2);
            for ($j = 0; $j < $lookupCount; $j++) {
                $lookupIndices[] = $this->readUint16($featureTableOffset + 4 + $j * 2);
            }
        }

        return array_unique($lookupIndices);
    }

    /**
     * @param int[] $lookupIndices
     * @return array<int, array<int, int>>
     */
    private function parsePairPosLookups(int $lookupListOffset, array $lookupIndices): array
    {
        $kernPairs = [];
        $lookupCount = $this->readUint16($lookupListOffset);

        foreach ($lookupIndices as $li) {
            if ($li >= $lookupCount) {
                continue;
            }
            $lookupOffset = $lookupListOffset + $this->readUint16($lookupListOffset + 2 + $li * 2);
            $lookupType = $this->readUint16($lookupOffset);
            $subtableCount = $this->readUint16($lookupOffset + 4);

            for ($s = 0; $s < $subtableCount; $s++) {
                $subtableOffset = $lookupOffset + $this->readUint16($lookupOffset + 6 + $s * 2);

                if ($lookupType === 2) {
                    // PairPos
                    $this->parsePairPosSubtable($subtableOffset, $kernPairs);
                } elseif ($lookupType === 9) {
                    // Extension
                    $extType = $this->readUint16($subtableOffset + 2);
                    $extOffset = $subtableOffset + $this->readUint32($subtableOffset + 4);
                    if ($extType === 2) {
                        $this->parsePairPosSubtable($extOffset, $kernPairs);
                    }
                }
            }
        }

        return $kernPairs;
    }

    /**
     * @param array<int, array<int, int>> &$kernPairs
     */
    private function parsePairPosSubtable(int $offset, array &$kernPairs): void
    {
        $format = $this->readUint16($offset);

        if ($format === 1) {
            $this->parsePairPosFormat1($offset, $kernPairs);
        } elseif ($format === 2) {
            $this->parsePairPosFormat2($offset, $kernPairs);
        }
    }

    /**
     * PairPosFormat1: individual glyph pairs.
     *
     * @param array<int, array<int, int>> &$kernPairs
     */
    private function parsePairPosFormat1(int $offset, array &$kernPairs): void
    {
        $coverageOffset = $offset + $this->readUint16($offset + 2);
        $valueFormat1 = $this->readUint16($offset + 4);
        $valueFormat2 = $this->readUint16($offset + 6);
        $pairSetCount = $this->readUint16($offset + 8);

        // We only care about xAdvance in value1 (bit 2 = 0x0004)
        if (($valueFormat1 & 0x0004) === 0) {
            return; // No xAdvance in value1
        }

        $valueSize1 = $this->valueRecordSize($valueFormat1);
        $valueSize2 = $this->valueRecordSize($valueFormat2);
        $xAdvanceOffset1 = $this->xAdvanceOffsetInValueRecord($valueFormat1);

        $coveredGlyphs = $this->parseCoverage($coverageOffset);

        for ($i = 0; $i < $pairSetCount; $i++) {
            if ($i >= count($coveredGlyphs)) {
                break;
            }
            $leftGid = $coveredGlyphs[$i];
            $pairSetOffset = $offset + $this->readUint16($offset + 10 + $i * 2);
            $pairCount = $this->readUint16($pairSetOffset);

            for ($j = 0; $j < $pairCount; $j++) {
                $pairBase = $pairSetOffset + 2 + $j * (2 + $valueSize1 + $valueSize2);
                $rightGid = $this->readUint16($pairBase);
                $xAdvance = $this->readInt16($pairBase + 2 + $xAdvanceOffset1);

                if ($xAdvance !== 0) {
                    $kernPairs[$leftGid][$rightGid] = $xAdvance;
                }
            }
        }
    }

    /**
     * PairPosFormat2: class-based pairs.
     *
     * @param array<int, array<int, int>> &$kernPairs
     */
    private function parsePairPosFormat2(int $offset, array &$kernPairs): void
    {
        $coverageOffset = $offset + $this->readUint16($offset + 2);
        $valueFormat1 = $this->readUint16($offset + 4);
        $valueFormat2 = $this->readUint16($offset + 6);
        $classDef1Offset = $offset + $this->readUint16($offset + 8);
        $classDef2Offset = $offset + $this->readUint16($offset + 10);
        $class1Count = $this->readUint16($offset + 12);
        $class2Count = $this->readUint16($offset + 14);

        if (($valueFormat1 & 0x0004) === 0) {
            return;
        }

        $valueSize1 = $this->valueRecordSize($valueFormat1);
        $valueSize2 = $this->valueRecordSize($valueFormat2);
        $xAdvanceOffset1 = $this->xAdvanceOffsetInValueRecord($valueFormat1);
        $recordSize = $valueSize1 + $valueSize2;

        $coveredGlyphs = $this->parseCoverage($coverageOffset);
        $classDef1 = $this->parseClassDef($classDef1Offset);
        $classDef2 = $this->parseClassDef($classDef2Offset);

        // Build reverse map: class => [gid, gid, ...]
        $class2Glyphs = [];
        foreach ($classDef2 as $gid => $cls) {
            $class2Glyphs[$cls][] = $gid;
        }

        // Read the Class1Record × Class2Record matrix
        $matrixBase = $offset + 16;

        foreach ($coveredGlyphs as $leftGid) {
            $class1 = $classDef1[$leftGid] ?? 0;
            if ($class1 >= $class1Count) {
                continue;
            }

            $class1Base = $matrixBase + $class1 * $class2Count * $recordSize;

            for ($c2 = 0; $c2 < $class2Count; $c2++) {
                $recBase = $class1Base + $c2 * $recordSize;
                $xAdvance = $this->readInt16($recBase + $xAdvanceOffset1);

                if ($xAdvance === 0) {
                    continue;
                }

                // Get all glyphs in class2
                if ($c2 === 0) {
                    // Class 0 = all glyphs not explicitly assigned to a class.
                    // Skip — too many glyphs, and class 0 pairs are rarely meaningful.
                    continue;
                }

                if (!isset($class2Glyphs[$c2])) {
                    continue;
                }

                foreach ($class2Glyphs[$c2] as $rightGid) {
                    $kernPairs[$leftGid][$rightGid] = $xAdvance;
                }
            }
        }
    }

    /**
     * @return int[] Covered glyph IDs (ordered by coverage index)
     */
    private function parseCoverage(int $offset): array
    {
        $format = $this->readUint16($offset);

        if ($format === 1) {
            // Format 1: list of glyph IDs
            $count = $this->readUint16($offset + 2);
            $glyphs = [];
            for ($i = 0; $i < $count; $i++) {
                $glyphs[] = $this->readUint16($offset + 4 + $i * 2);
            }
            return $glyphs;
        }

        if ($format === 2) {
            // Format 2: ranges
            $rangeCount = $this->readUint16($offset + 2);
            $glyphs = [];
            for ($i = 0; $i < $rangeCount; $i++) {
                $rangeBase = $offset + 4 + $i * 6;
                $startGid = $this->readUint16($rangeBase);
                $endGid = $this->readUint16($rangeBase + 2);
                for ($gid = $startGid; $gid <= $endGid; $gid++) {
                    $glyphs[] = $gid;
                }
            }
            return $glyphs;
        }

        return [];
    }

    /**
     * @return array<int, int> glyph ID => class value
     */
    private function parseClassDef(int $offset): array
    {
        $format = $this->readUint16($offset);

        if ($format === 1) {
            // Format 1: array starting at startGlyphID
            $startGid = $this->readUint16($offset + 2);
            $glyphCount = $this->readUint16($offset + 4);
            $classes = [];
            for ($i = 0; $i < $glyphCount; $i++) {
                $classes[$startGid + $i] = $this->readUint16($offset + 6 + $i * 2);
            }
            return $classes;
        }

        if ($format === 2) {
            // Format 2: ranges
            $rangeCount = $this->readUint16($offset + 2);
            $classes = [];
            for ($i = 0; $i < $rangeCount; $i++) {
                $rangeBase = $offset + 4 + $i * 6;
                $startGid = $this->readUint16($rangeBase);
                $endGid = $this->readUint16($rangeBase + 2);
                $classValue = $this->readUint16($rangeBase + 4);
                for ($gid = $startGid; $gid <= $endGid; $gid++) {
                    $classes[$gid] = $classValue;
                }
            }
            return $classes;
        }

        return [];
    }

    /**
     * Compute the byte size of a ValueRecord from its ValueFormat bitmask.
     * Each set bit contributes 2 bytes.
     */
    private function valueRecordSize(int $valueFormat): int
    {
        $size = 0;
        for ($bit = 0; $bit < 8; $bit++) {
            if ($valueFormat & (1 << $bit)) {
                $size += 2;
            }
        }
        return $size;
    }

    /**
     * Compute the byte offset of xAdvance within a ValueRecord.
     * xAdvance is bit 2. We count how many bits before it are set.
     */
    private function xAdvanceOffsetInValueRecord(int $valueFormat): int
    {
        $offset = 0;
        // bit 0 = xPlacement, bit 1 = yPlacement, bit 2 = xAdvance
        if ($valueFormat & 0x0001) {
            $offset += 2; // xPlacement
        }
        if ($valueFormat & 0x0002) {
            $offset += 2; // yPlacement
        }
        // xAdvance is at this offset
        return $offset;
    }

    // --- Legacy kern table ---

    /**
     * @return array<int, array<int, int>>
     */
    private function parseKernTable(int $offset, int $length): array
    {
        $kernPairs = [];
        $version = $this->readUint16($offset);

        if ($version === 0) {
            // Microsoft kern table format
            $nTables = $this->readUint16($offset + 2);
            $pos = $offset + 4;

            for ($t = 0; $t < $nTables; $t++) {
                $subtableVersion = $this->readUint16($pos);
                $subtableLength = $this->readUint16($pos + 2);
                $coverage = $this->readUint16($pos + 4);
                $format = $coverage >> 8;

                // Only format 0 (ordered list of kern pairs), horizontal, not cross-stream
                if ($format === 0 && ($coverage & 0x0001) !== 0 && ($coverage & 0x0004) === 0) {
                    $this->parseKernFormat0($pos + 6, $kernPairs);
                }

                $pos += $subtableLength;
            }
        } elseif ($version === 1) {
            // Apple kern table format (big-endian version as uint32)
            $nTables = $this->readUint32($offset + 4);
            $pos = $offset + 8;

            for ($t = 0; $t < $nTables; $t++) {
                $subtableLength = $this->readUint32($pos);
                $coverage = $this->readUint16($pos + 4);
                $format = $this->readUint16($pos + 6);

                if ($format === 0 && ($coverage & 0x0002) === 0) { // horizontal
                    $this->parseKernFormat0($pos + 8, $kernPairs);
                }

                $pos += $subtableLength;
            }
        }

        return $kernPairs;
    }

    /**
     * @param array<int, array<int, int>> &$kernPairs
     */
    private function parseKernFormat0(int $offset, array &$kernPairs): void
    {
        $nPairs = $this->readUint16($offset);

        for ($i = 0; $i < $nPairs; $i++) {
            $pairBase = $offset + 8 + $i * 6; // 8 = nPairs(2) + searchRange(2) + entrySelector(2) + rangeShift(2)
            $leftGid = $this->readUint16($pairBase);
            $rightGid = $this->readUint16($pairBase + 2);
            $value = $this->readInt16($pairBase + 4);

            if ($value !== 0) {
                $kernPairs[$leftGid][$rightGid] = $value;
            }
        }
    }

    // --- Binary readers ---

    private function readUint16(int $offset): int
    {
        return (ord($this->data[$offset]) << 8) | ord($this->data[$offset + 1]);
    }

    private function readInt16(int $offset): int
    {
        $v = $this->readUint16($offset);
        return $v >= 0x8000 ? $v - 0x10000 : $v;
    }

    private function readUint32(int $offset): int
    {
        return ((ord($this->data[$offset]) << 24)
            | (ord($this->data[$offset + 1]) << 16)
            | (ord($this->data[$offset + 2]) << 8)
            | ord($this->data[$offset + 3])) & 0xFFFFFFFF;
    }
}
