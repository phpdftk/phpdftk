<?php

declare(strict_types=1);

namespace ApprLabs\FontParser;

/**
 * Parses GSUB (Glyph Substitution) table for ligature features.
 *
 * Extracts ligature substitution rules from the "liga" (standard ligatures)
 * and "clig" (contextual ligatures) features. Supports:
 * - GSUB LigatureSubst (LookupType 4)
 * - Extension lookups (LookupType 7)
 *
 * Returns a map of: firstGid => [[[componentGid2, ...], ligatureGid], ...]
 * sorted by component count descending (longest match first).
 */
final class GsubParser
{
    private string $data;

    /**
     * @param string $fontBytes Raw font file bytes
     * @param array<string, array{offset:int, length:int}> $tables Table directory
     * @return array<int, list<array{components: int[], ligature: int}>> firstGid => ligature rules
     */
    public function parse(string $fontBytes, array $tables): array
    {
        if (!isset($tables['GSUB'])) {
            return [];
        }

        $this->data = $fontBytes;
        return $this->parseGsub($tables['GSUB']['offset']);
    }

    /**
     * @return array<int, list<array{components: int[], ligature: int}>>
     */
    private function parseGsub(int $offset): array
    {
        $scriptListOffset = $offset + $this->readUint16($offset + 4);
        $featureListOffset = $offset + $this->readUint16($offset + 6);
        $lookupListOffset = $offset + $this->readUint16($offset + 8);

        // Find "liga" and "clig" feature indices
        $featureIndices = $this->findLigatureFeatureIndices($scriptListOffset, $featureListOffset);
        if ($featureIndices === []) {
            return [];
        }

        $lookupIndices = $this->getLookupIndicesFromFeatures($featureListOffset, $featureIndices);
        if ($lookupIndices === []) {
            return [];
        }

        return $this->parseLigatureSubstLookups($lookupListOffset, $lookupIndices);
    }

    /**
     * Find feature indices for "liga" and "clig" features.
     *
     * @return int[]
     */
    private function findLigatureFeatureIndices(int $scriptListOffset, int $featureListOffset): array
    {
        $featureCount = $this->readUint16($featureListOffset);
        $ligaTags = ['liga', 'clig'];

        $indices = [];
        for ($i = 0; $i < $featureCount; $i++) {
            $recOffset = $featureListOffset + 2 + $i * 6;
            $tag = substr($this->data, $recOffset, 4);
            if (in_array($tag, $ligaTags, true)) {
                $indices[] = $i;
            }
        }

        return $indices;
    }

    /**
     * @param int[] $featureIndices
     * @return int[]
     */
    private function getLookupIndicesFromFeatures(int $featureListOffset, array $featureIndices): array
    {
        $lookupIndices = [];

        foreach ($featureIndices as $fi) {
            $recOffset = $featureListOffset + 2 + $fi * 6;
            $featureTableOffset = $featureListOffset + $this->readUint16($recOffset + 4);

            $lookupCount = $this->readUint16($featureTableOffset + 2);
            for ($j = 0; $j < $lookupCount; $j++) {
                $lookupIndices[] = $this->readUint16($featureTableOffset + 4 + $j * 2);
            }
        }

        return array_unique($lookupIndices);
    }

    /**
     * @param int[] $lookupIndices
     * @return array<int, list<array{components: int[], ligature: int}>>
     */
    private function parseLigatureSubstLookups(int $lookupListOffset, array $lookupIndices): array
    {
        $result = [];
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

                // Handle extension lookups (type 7)
                if ($lookupType === 7) {
                    $extFormat = $this->readUint16($subtableOffset);
                    if ($extFormat === 1) {
                        $extType = $this->readUint16($subtableOffset + 2);
                        $extOffset = $this->readUint32($subtableOffset + 4);
                        if ($extType === 4) {
                            $this->parseLigatureSubst($subtableOffset + $extOffset, $result);
                        }
                    }
                    continue;
                }

                // LigatureSubst (type 4)
                if ($lookupType === 4) {
                    $this->parseLigatureSubst($subtableOffset, $result);
                }
            }
        }

        // Sort each GID's ligatures by component count descending (longest match first)
        foreach ($result as $gid => &$rules) {
            usort($rules, fn($a, $b) => count($b['components']) <=> count($a['components']));
        }

        return $result;
    }

    /**
     * Parse a LigatureSubst subtable (format 1).
     *
     * @param array<int, list<array{components: int[], ligature: int}>> &$result
     */
    private function parseLigatureSubst(int $offset, array &$result): void
    {
        $format = $this->readUint16($offset);
        if ($format !== 1) {
            return;
        }

        $coverageOffset = $offset + $this->readUint16($offset + 2);
        $ligatureSetCount = $this->readUint16($offset + 4);
        $coveredGlyphs = $this->parseCoverage($coverageOffset);

        for ($i = 0; $i < $ligatureSetCount && $i < count($coveredGlyphs); $i++) {
            $firstGid = $coveredGlyphs[$i];
            $ligatureSetOffset = $offset + $this->readUint16($offset + 6 + $i * 2);
            $ligatureCount = $this->readUint16($ligatureSetOffset);

            for ($j = 0; $j < $ligatureCount; $j++) {
                $ligatureTableOffset = $ligatureSetOffset + $this->readUint16($ligatureSetOffset + 2 + $j * 2);
                $ligatureGlyph = $this->readUint16($ligatureTableOffset);
                $componentCount = $this->readUint16($ligatureTableOffset + 2);

                $components = [];
                for ($k = 0; $k < $componentCount - 1; $k++) {
                    $components[] = $this->readUint16($ligatureTableOffset + 4 + $k * 2);
                }

                if (!isset($result[$firstGid])) {
                    $result[$firstGid] = [];
                }
                $result[$firstGid][] = [
                    'components' => $components,
                    'ligature' => $ligatureGlyph,
                ];
            }
        }
    }

    /**
     * @return int[] Covered glyph IDs
     */
    private function parseCoverage(int $offset): array
    {
        $format = $this->readUint16($offset);

        if ($format === 1) {
            $count = $this->readUint16($offset + 2);
            $glyphs = [];
            for ($i = 0; $i < $count; $i++) {
                $glyphs[] = $this->readUint16($offset + 4 + $i * 2);
            }
            return $glyphs;
        }

        if ($format === 2) {
            $rangeCount = $this->readUint16($offset + 2);
            $glyphs = [];
            for ($i = 0; $i < $rangeCount; $i++) {
                $rangeBase = $offset + 4 + $i * 6;
                $startGid = $this->readUint16($rangeBase);
                $endGid = $this->readUint16($rangeBase + 2);
                for ($g = $startGid; $g <= $endGid; $g++) {
                    $glyphs[] = $g;
                }
            }
            return $glyphs;
        }

        return [];
    }

    private function readUint16(int $offset): int
    {
        if ($offset + 1 >= strlen($this->data)) {
            return 0;
        }
        return unpack('n', $this->data, $offset)[1];
    }

    private function readUint32(int $offset): int
    {
        if ($offset + 3 >= strlen($this->data)) {
            return 0;
        }
        return unpack('N', $this->data, $offset)[1];
    }
}
