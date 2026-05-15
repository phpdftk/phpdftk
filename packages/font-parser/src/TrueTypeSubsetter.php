<?php

declare(strict_types=1);

namespace Phpdftk\FontParser;

/**
 * Produces a minimal valid TrueType font containing only requested glyphs.
 *
 * Takes raw TTF bytes and a set of glyph IDs, and emits a new TTF with
 * only those glyphs (plus GID 0 / .notdef and any composite components).
 */
class TrueTypeSubsetter
{
    private string $data;

    /** @var array<string, array{offset:int, length:int, checksum:int}> */
    private array $tables = [];

    private int $numGlyphs;
    private int $numberOfHMetrics;
    private int $indexToLocFormat;

    /**
     * Old GID → new GID map, populated by the most recent subset() call.
     * Callers need this to translate any pre-subset GIDs they hold (Unicode
     * → GID maps from the unsubset font, for instance) into the renumbered
     * GIDs that actually live in the emitted subset.
     *
     * @var array<int, int>
     */
    private array $gidMap = [];

    /**
     * @param string   $fontBytes Raw TTF file bytes
     * @param int[]    $glyphIds  GIDs to keep (GID 0 is always included)
     * @param array<int, int> $unicodeToGid Unicode codepoint => GID map (for rebuilding cmap)
     * @return string  Subset TTF bytes
     */
    public function subset(string $fontBytes, array $glyphIds, array $unicodeToGid = []): string
    {
        $this->data = $fontBytes;
        $this->parseTables();

        // Always include GID 0
        $glyphIds = array_unique(array_merge([0], $glyphIds));

        // Resolve composite glyph components recursively
        $glyphIds = $this->resolveComposites($glyphIds);
        sort($glyphIds);

        // Build old GID => new GID map (also kept on $this so callers can
        // retrieve it after subset() via getGidMap()).
        $gidMap = [];
        foreach ($glyphIds as $newGid => $oldGid) {
            $gidMap[$oldGid] = $newGid;
        }
        $this->gidMap = $gidMap;

        // Build subset tables
        $newGlyf = $this->buildGlyf($glyphIds, $gidMap);
        $useShortLoca = $this->canUseShortLoca($newGlyf['offsets']);
        $newLoca = $this->buildLoca($newGlyf['offsets'], $useShortLoca);
        $newHmtx = $this->buildHmtx($glyphIds);
        $newCmap = $this->buildCmap($glyphIds, $gidMap, $unicodeToGid);
        $newMaxp = $this->buildMaxp(count($glyphIds));
        $newHhea = $this->buildHhea(count($glyphIds));
        $newHead = $this->buildHead($useShortLoca ? 0 : 1);
        $newPost = $this->getTableData('post');
        $newName = $this->getTableData('name');
        $newOs2 = $this->getTableData('OS/2');

        // Assemble the new font
        $tableDefs = [
            'head' => $newHead,
            'hhea' => $newHhea,
            'maxp' => $newMaxp,
            'OS/2' => $newOs2,
            'name' => $newName,
            'cmap' => $newCmap,
            'post' => $newPost,
            'hmtx' => $newHmtx,
            'loca' => $newLoca,
            'glyf' => $newGlyf['data'],
        ];

        return $this->assembleFont($tableDefs);
    }

    /**
     * The old → new GID map from the most recent `subset()` call. Returns
     * an empty array if `subset()` has not been called yet. Callers use this
     * to translate any pre-subset GIDs they hold into the renumbered GIDs
     * that live in the emitted subset font.
     *
     * @return array<int, int>
     */
    public function getGidMap(): array
    {
        return $this->gidMap;
    }

    private function parseTables(): void
    {
        $sfVersion = $this->readUint32(0);
        if ($sfVersion !== 0x00010000) {
            throw new \RuntimeException('Not a TrueType font');
        }

        $numTables = $this->readUint16(4);
        $dirOffset = 12;

        for ($i = 0; $i < $numTables; $i++) {
            $base = $dirOffset + $i * 16;
            $tag = substr($this->data, $base, 4);
            $checksum = $this->readUint32($base + 4);
            $offset = $this->readUint32($base + 8);
            $length = $this->readUint32($base + 12);
            $this->tables[rtrim($tag)] = [
                'offset' => $offset,
                'length' => $length,
                'checksum' => $checksum,
            ];
        }

        // Read key values from existing tables
        $headBase = $this->tables['head']['offset'];
        $this->indexToLocFormat = $this->readInt16($headBase + 50);

        $maxpBase = $this->tables['maxp']['offset'];
        $this->numGlyphs = $this->readUint16($maxpBase + 4);

        $hheaBase = $this->tables['hhea']['offset'];
        $this->numberOfHMetrics = $this->readUint16($hheaBase + 34);
    }

    /**
     * @param int[] $glyphIds
     * @return int[]
     */
    private function resolveComposites(array $glyphIds): array
    {
        $locaOffsets = $this->readLocaTable();
        $glyfBase = $this->tables['glyf']['offset'];
        $resolved = array_flip($glyphIds);
        $queue = $glyphIds;

        while ($queue !== []) {
            $gid = array_pop($queue);
            if ($gid >= $this->numGlyphs) {
                continue;
            }

            $glyphOffset = $locaOffsets[$gid];
            $glyphLength = $locaOffsets[$gid + 1] - $glyphOffset;
            if ($glyphLength === 0) {
                continue;
            }

            $offset = $glyfBase + $glyphOffset;
            $numberOfContours = $this->readInt16($offset);

            if ($numberOfContours >= 0) {
                continue; // Simple glyph
            }

            // Composite glyph - parse components
            $ptr = $offset + 10; // skip header (numberOfContours + bbox)
            do {
                $flags = $this->readUint16($ptr);
                $componentGid = $this->readUint16($ptr + 2);
                $ptr += 4;

                if (!isset($resolved[$componentGid])) {
                    $resolved[$componentGid] = true;
                    $queue[] = $componentGid;
                }

                // Skip arguments based on flags
                if ($flags & 0x0001) { // ARG_1_AND_2_ARE_WORDS
                    $ptr += 4;
                } else {
                    $ptr += 2;
                }

                // Skip transform based on flags
                if ($flags & 0x0008) { // WE_HAVE_A_SCALE
                    $ptr += 2;
                } elseif ($flags & 0x0040) { // WE_HAVE_AN_X_AND_Y_SCALE
                    $ptr += 4;
                } elseif ($flags & 0x0080) { // WE_HAVE_A_TWO_BY_TWO
                    $ptr += 8;
                }
            } while ($flags & 0x0020); // MORE_COMPONENTS
        }

        return array_keys($resolved);
    }

    /**
     * @return int[] GID => offset in glyf table
     */
    private function readLocaTable(): array
    {
        $locaBase = $this->tables['loca']['offset'];
        $offsets = [];

        if ($this->indexToLocFormat === 0) {
            // Short format: uint16 values, multiply by 2
            for ($i = 0; $i <= $this->numGlyphs; $i++) {
                $offsets[$i] = $this->readUint16($locaBase + $i * 2) * 2;
            }
        } else {
            // Long format: uint32 values
            for ($i = 0; $i <= $this->numGlyphs; $i++) {
                $offsets[$i] = $this->readUint32($locaBase + $i * 4);
            }
        }

        return $offsets;
    }

    /**
     * @param int[] $glyphIds
     * @param array<int, int> $gidMap old => new
     * @return array{data: string, offsets: int[]}
     */
    private function buildGlyf(array $glyphIds, array $gidMap): array
    {
        $locaOffsets = $this->readLocaTable();
        $glyfBase = $this->tables['glyf']['offset'];

        $newData = '';
        $newOffsets = [];

        foreach ($glyphIds as $oldGid) {
            $newOffsets[] = strlen($newData);

            if ($oldGid >= $this->numGlyphs) {
                continue;
            }

            $glyphOffset = $locaOffsets[$oldGid];
            $glyphLength = $locaOffsets[$oldGid + 1] - $glyphOffset;

            if ($glyphLength === 0) {
                continue;
            }

            $glyphData = substr($this->data, $glyfBase + $glyphOffset, $glyphLength);
            $numberOfContours = $this->readInt16($glyfBase + $glyphOffset);

            if ($numberOfContours < 0) {
                // Composite glyph - remap component GIDs
                $glyphData = $this->remapCompositeGlyph($glyphData, $gidMap);
            }

            // Pad to 4-byte boundary
            $padding = (4 - (strlen($glyphData) % 4)) % 4;
            $newData .= $glyphData . str_repeat("\0", $padding);
        }

        // Final offset (end of last glyph)
        $newOffsets[] = strlen($newData);

        return ['data' => $newData, 'offsets' => $newOffsets];
    }

    /**
     * @param array<int, int> $gidMap
     */
    private function remapCompositeGlyph(string $glyphData, array $gidMap): string
    {
        $ptr = 10; // skip header

        do {
            $flags = (ord($glyphData[$ptr]) << 8) | ord($glyphData[$ptr + 1]);
            $oldComponentGid = (ord($glyphData[$ptr + 2]) << 8) | ord($glyphData[$ptr + 3]);
            $newComponentGid = $gidMap[$oldComponentGid] ?? 0;

            // Write new GID
            $glyphData[$ptr + 2] = chr(($newComponentGid >> 8) & 0xFF);
            $glyphData[$ptr + 3] = chr($newComponentGid & 0xFF);

            $ptr += 4;

            if ($flags & 0x0001) {
                $ptr += 4;
            } else {
                $ptr += 2;
            }

            if ($flags & 0x0008) {
                $ptr += 2;
            } elseif ($flags & 0x0040) {
                $ptr += 4;
            } elseif ($flags & 0x0080) {
                $ptr += 8;
            }
        } while ($flags & 0x0020);

        return $glyphData;
    }

    /**
     * @param int[] $offsets
     */
    private function canUseShortLoca(array $offsets): bool
    {
        foreach ($offsets as $o) {
            if ($o > 0x1FFFE) { // max uint16 * 2
                return false;
            }
            if ($o % 2 !== 0) {
                return false; // short loca requires even offsets (we pad to 4)
            }
        }
        return true;
    }

    /**
     * @param int[] $offsets
     */
    private function buildLoca(array $offsets, bool $shortFormat): string
    {
        $loca = '';
        foreach ($offsets as $o) {
            if ($shortFormat) {
                $loca .= pack('n', $o / 2);
            } else {
                $loca .= pack('N', $o);
            }
        }
        return $loca;
    }

    /**
     * @param int[] $glyphIds
     */
    private function buildHmtx(array $glyphIds): string
    {
        $hmtxBase = $this->tables['hmtx']['offset'];
        $hmtx = '';

        foreach ($glyphIds as $oldGid) {
            if ($oldGid < $this->numberOfHMetrics) {
                $hmtx .= substr($this->data, $hmtxBase + $oldGid * 4, 4);
            } else {
                // Use last advance width + lsb from monospaced section
                $lastAdvanceOffset = $hmtxBase + ($this->numberOfHMetrics - 1) * 4;
                $advanceWidth = substr($this->data, $lastAdvanceOffset, 2);
                $lsbOffset = $hmtxBase + $this->numberOfHMetrics * 4 + ($oldGid - $this->numberOfHMetrics) * 2;
                if ($lsbOffset + 2 <= strlen($this->data)) {
                    $lsb = substr($this->data, $lsbOffset, 2);
                } else {
                    $lsb = "\0\0";
                }
                $hmtx .= $advanceWidth . $lsb;
            }
        }

        return $hmtx;
    }

    /**
     * @param int[] $glyphIds
     * @param array<int, int> $gidMap old => new
     * @param array<int, int> $unicodeToGid codepoint => old GID
     */
    private function buildCmap(array $glyphIds, array $gidMap, array $unicodeToGid): string
    {
        // Filter unicodeToGid to only kept glyphs and remap to new GIDs
        $mappings = [];
        foreach ($unicodeToGid as $cp => $oldGid) {
            if (isset($gidMap[$oldGid])) {
                $mappings[$cp] = $gidMap[$oldGid];
            }
        }
        ksort($mappings);

        // Check if any codepoint exceeds BMP (U+FFFF)
        $hasSupplementary = false;
        foreach ($mappings as $cp => $_) {
            if ($cp > 0xFFFF) {
                $hasSupplementary = true;
                break;
            }
        }

        if ($hasSupplementary) {
            // Use format 12 for full Unicode range
            $subtable = $this->buildCmapFormat12($mappings);

            // cmap header: version=0, numTables=1
            $header = pack('nn', 0, 1);
            // Encoding record: platformID=3 (Windows), encodingID=10 (UCS-4), offset=12
            $header .= pack('nnN', 3, 10, 12);

            return $header . $subtable;
        }

        // Build format 4 subtable for BMP-only mappings
        $format4 = $this->buildCmapFormat4($mappings);

        // cmap header: version=0, numTables=1
        $header = pack('nn', 0, 1);
        // Encoding record: platformID=3 (Windows), encodingID=1 (Unicode BMP), offset=12
        $header .= pack('nnN', 3, 1, 12);

        return $header . $format4;
    }

    /**
     * @param array<int, int> $mappings Unicode codepoint => new GID (sorted by codepoint)
     */
    private function buildCmapFormat4(array $mappings): string
    {
        // Build segments from mappings
        $segments = [];
        $glyphIdArray = [];

        if ($mappings !== []) {
            $codepoints = array_keys($mappings);
            $segStart = $codepoints[0];
            $segEnd = $codepoints[0];
            $segMappings = [$codepoints[0] => $mappings[$codepoints[0]]];

            for ($i = 1; $i < count($codepoints); $i++) {
                if ($codepoints[$i] === $segEnd + 1) {
                    $segEnd = $codepoints[$i];
                    $segMappings[$codepoints[$i]] = $mappings[$codepoints[$i]];
                } else {
                    $segments[] = ['start' => $segStart, 'end' => $segEnd, 'mappings' => $segMappings];
                    $segStart = $codepoints[$i];
                    $segEnd = $codepoints[$i];
                    $segMappings = [$codepoints[$i] => $mappings[$codepoints[$i]]];
                }
            }
            $segments[] = ['start' => $segStart, 'end' => $segEnd, 'mappings' => $segMappings];
        }

        // Add sentinel segment
        $segments[] = ['start' => 0xFFFF, 'end' => 0xFFFF, 'mappings' => []];

        $segCount = count($segments);
        $segCountX2 = $segCount * 2;
        $searchRange = 1;
        $entrySelector = 0;
        while ($searchRange * 2 <= $segCount) {
            $searchRange *= 2;
            $entrySelector++;
        }
        $searchRange *= 2;
        $rangeShift = $segCountX2 - $searchRange;

        $endCodes = '';
        $startCodes = '';
        $idDeltas = '';
        $idRangeOffsets = '';
        $glyphIdBytes = '';

        foreach ($segments as $idx => $seg) {
            $endCodes .= pack('n', $seg['end']);
            $startCodes .= pack('n', $seg['start']);

            if ($seg['start'] === 0xFFFF) {
                $idDeltas .= pack('n', 1);
                $idRangeOffsets .= pack('n', 0);
                continue;
            }

            // Check if we can use idDelta (contiguous GID mapping)
            $canUseDelta = true;
            $firstCp = $seg['start'];
            $firstGid = $seg['mappings'][$firstCp];
            $delta = $firstGid - $firstCp;

            foreach ($seg['mappings'] as $cp => $gid) {
                if ($gid - $cp !== $delta) {
                    $canUseDelta = false;
                    break;
                }
            }

            if ($canUseDelta) {
                $idDeltas .= pack('n', $delta & 0xFFFF);
                $idRangeOffsets .= pack('n', 0);
            } else {
                $idDeltas .= pack('n', 0);
                // idRangeOffset = byte offset from this position to glyphIdArray entry
                $remainingSegments = $segCount - $idx;
                $currentGlyphIdOffset = strlen($glyphIdBytes) / 2;
                $offset = ($remainingSegments + $currentGlyphIdOffset) * 2;
                $idRangeOffsets .= pack('n', $offset);

                for ($cp = $seg['start']; $cp <= $seg['end']; $cp++) {
                    $gid = $seg['mappings'][$cp] ?? 0;
                    $glyphIdBytes .= pack('n', $gid);
                }
            }
        }

        $subtableLength = 14 + $segCountX2 * 4 + 2 + strlen($glyphIdBytes);

        $header = pack('nnn', 4, $subtableLength, 0); // format, length, language
        $header .= pack('nnnn', $segCountX2, $searchRange, $entrySelector, $rangeShift);

        return $header . $endCodes . pack('n', 0) . $startCodes . $idDeltas . $idRangeOffsets . $glyphIdBytes;
    }

    /**
     * @param array<int, int> $mappings Unicode codepoint => new GID (sorted by codepoint)
     */
    private function buildCmapFormat12(array $mappings): string
    {
        // Build sequential groups: merge consecutive codepoints with sequential GIDs
        $groups = [];
        $codepoints = array_keys($mappings);

        if ($codepoints !== []) {
            $groupStart = $codepoints[0];
            $groupEnd = $codepoints[0];
            $groupStartGid = $mappings[$codepoints[0]];

            for ($i = 1; $i < count($codepoints); $i++) {
                $cp = $codepoints[$i];
                $expectedGid = $groupStartGid + ($cp - $groupStart);

                if ($cp === $groupEnd + 1 && $mappings[$cp] === $expectedGid) {
                    $groupEnd = $cp;
                } else {
                    $groups[] = [$groupStart, $groupEnd, $groupStartGid];
                    $groupStart = $cp;
                    $groupEnd = $cp;
                    $groupStartGid = $mappings[$cp];
                }
            }
            $groups[] = [$groupStart, $groupEnd, $groupStartGid];
        }

        $nGroups = count($groups);
        // Header: format(2) + reserved(2) + length(4) + language(4) + nGroups(4) = 16 bytes
        $length = 16 + $nGroups * 12;

        $header = pack('nnNN', 12, 0, $length, 0); // format=12, reserved=0, length, language=0
        $header .= pack('N', $nGroups);

        $body = '';
        foreach ($groups as [$startCharCode, $endCharCode, $startGlyphID]) {
            $body .= pack('NNN', $startCharCode, $endCharCode, $startGlyphID);
        }

        return $header . $body;
    }

    private function buildMaxp(int $numGlyphs): string
    {
        $maxpData = $this->getTableData('maxp');
        // Overwrite numGlyphs at offset 4
        $maxpData[4] = chr(($numGlyphs >> 8) & 0xFF);
        $maxpData[5] = chr($numGlyphs & 0xFF);
        return $maxpData;
    }

    private function buildHhea(int $numberOfHMetrics): string
    {
        $hheaData = $this->getTableData('hhea');
        // Overwrite numberOfHMetrics at offset 34
        $hheaData[34] = chr(($numberOfHMetrics >> 8) & 0xFF);
        $hheaData[35] = chr($numberOfHMetrics & 0xFF);
        return $hheaData;
    }

    private function buildHead(int $indexToLocFormat): string
    {
        $headData = $this->getTableData('head');
        // Set checkSumAdjustment to 0 at offset 8
        $headData[8] = "\0";
        $headData[9] = "\0";
        $headData[10] = "\0";
        $headData[11] = "\0";
        // Set indexToLocFormat at offset 50
        $headData[50] = chr(($indexToLocFormat >> 8) & 0xFF);
        $headData[51] = chr($indexToLocFormat & 0xFF);
        return $headData;
    }

    private function getTableData(string $tag): string
    {
        if (!isset($this->tables[$tag])) {
            throw new \RuntimeException("Table '{$tag}' not found");
        }
        $t = $this->tables[$tag];
        return substr($this->data, $t['offset'], $t['length']);
    }

    /**
     * @param array<string, string> $tableDefs tag => data
     */
    private function assembleFont(array $tableDefs): string
    {
        $numTables = count($tableDefs);
        $searchRange = 1;
        $entrySelector = 0;
        while ($searchRange * 2 <= $numTables) {
            $searchRange *= 2;
            $entrySelector++;
        }
        $searchRange *= 16;
        $rangeShift = $numTables * 16 - $searchRange;

        // Offset table (12 bytes)
        $header = pack('Nnnnn', 0x00010000, $numTables, $searchRange, $entrySelector, $rangeShift);

        // Calculate data start offset
        $dataOffset = 12 + $numTables * 16;

        // Build table directory and data
        $directory = '';
        $tableData = '';

        foreach ($tableDefs as $tag => $data) {
            // Pad tag to 4 bytes
            $paddedTag = str_pad($tag, 4, ' ');

            $checksum = $this->calculateChecksum($data);
            $offset = $dataOffset + strlen($tableData);
            $length = strlen($data);

            $directory .= $paddedTag;
            $directory .= pack('NNN', $checksum, $offset, $length);

            // Pad data to 4-byte boundary
            $padding = (4 - ($length % 4)) % 4;
            $tableData .= $data . str_repeat("\0", $padding);
        }

        $output = $header . $directory . $tableData;

        // Compute and patch checkSumAdjustment in head table.
        // Per spec: checkSumAdjustment = 0xB1B0AFBA - checksum_of_entire_file
        $fileChecksum = 0;
        $padded = $output . str_repeat("\0", (4 - (strlen($output) % 4)) % 4);
        $padLen = strlen($padded);
        for ($i = 0; $i < $padLen; $i += 4) {
            $fileChecksum = ($fileChecksum + $this->readUint32FromString($padded, $i)) & 0xFFFFFFFF;
        }
        $adjustment = (0xB1B0AFBA - $fileChecksum) & 0xFFFFFFFF;

        // Find head table offset in the assembled font
        for ($t = 0; $t < $numTables; $t++) {
            $dirBase = 12 + $t * 16;
            $tag = substr($output, $dirBase, 4);
            if (rtrim($tag) === 'head') {
                $headOffset = $this->readUint32FromString($output, $dirBase + 8);
                // checkSumAdjustment is at offset 8 within head table
                $pos = $headOffset + 8;
                $output[$pos]     = chr(($adjustment >> 24) & 0xFF);
                $output[$pos + 1] = chr(($adjustment >> 16) & 0xFF);
                $output[$pos + 2] = chr(($adjustment >> 8) & 0xFF);
                $output[$pos + 3] = chr($adjustment & 0xFF);
                break;
            }
        }

        return $output;
    }

    private function calculateChecksum(string $data): int
    {
        // Pad to 4-byte boundary
        $padding = (4 - (strlen($data) % 4)) % 4;
        $data .= str_repeat("\0", $padding);

        $sum = 0;
        $len = strlen($data);
        for ($i = 0; $i < $len; $i += 4) {
            $sum = ($sum + $this->readUint32FromString($data, $i)) & 0xFFFFFFFF;
        }
        return $sum;
    }

    private function readUint32FromString(string $data, int $offset): int
    {
        return ((ord($data[$offset]) << 24)
            | (ord($data[$offset + 1]) << 16)
            | (ord($data[$offset + 2]) << 8)
            | ord($data[$offset + 3])) & 0xFFFFFFFF;
    }

    private function readUint16(int $offset): int
    {
        return (ord($this->data[$offset]) << 8) | ord($this->data[$offset + 1]);
    }

    private function readInt16(int $offset): int
    {
        $v = $this->readUint16($offset);
        if ($v >= 0x8000) {
            $v -= 0x10000;
        }
        return $v;
    }

    private function readUint32(int $offset): int
    {
        return ((ord($this->data[$offset]) << 24)
            | (ord($this->data[$offset + 1]) << 16)
            | (ord($this->data[$offset + 2]) << 8)
            | ord($this->data[$offset + 3])) & 0xFFFFFFFF;
    }
}
