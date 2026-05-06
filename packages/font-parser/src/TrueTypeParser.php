<?php

declare(strict_types=1);

namespace Phpdftk\FontParser;

class TrueTypeParser
{
    private string $data;

    /** @var array<string, array{offset:int, length:int}> */
    private array $tables = [];

    // Windows-1252 byte => Unicode codepoint for bytes 128-159
    private const WIN1252_MAP = [
        128 => 0x20AC,
        130 => 0x201A,
        131 => 0x0192,
        132 => 0x201E,
        133 => 0x2026,
        134 => 0x2020,
        135 => 0x2021,
        136 => 0x02C6,
        137 => 0x2030,
        138 => 0x0160,
        139 => 0x2039,
        140 => 0x0152,
        142 => 0x017D,
        145 => 0x2018,
        146 => 0x2019,
        147 => 0x201C,
        148 => 0x201D,
        149 => 0x2022,
        150 => 0x2013,
        151 => 0x2014,
        152 => 0x02DC,
        153 => 0x2122,
        154 => 0x0161,
        155 => 0x203A,
        156 => 0x0153,
        158 => 0x017E,
        159 => 0x0178,
    ];

    public function __construct(private readonly string $path) {}

    /**
     * Create a parser from raw font bytes instead of a file path.
     */
    public static function fromBytes(string $fontBytes): self
    {
        $tmp = tempnam(sys_get_temp_dir(), 'phpdftk_ttf_');
        if ($tmp === false) {
            throw new \RuntimeException('Cannot create temp file for font data');
        }
        file_put_contents($tmp, $fontBytes);
        return new self($tmp);
    }

    public function parse(): TrueTypeData
    {
        $data = file_get_contents($this->path);
        if ($data === false) {
            throw new \RuntimeException("Cannot read font file: {$this->path}");
        }
        $this->data = $data;

        // Parse offset table
        $sfVersion = $this->readUint32(0);
        if ($sfVersion !== 0x00010000) {
            throw new \RuntimeException(sprintf(
                'Not a TrueType font (sfVersion=0x%08X); expected 0x00010000',
                $sfVersion,
            ));
        }

        $numTables = $this->readUint16(4);

        // Parse table directory
        $dirOffset = 12;
        for ($i = 0; $i < $numTables; $i++) {
            $base = $dirOffset + $i * 16;
            $tag = rtrim(substr($this->data, $base, 4));
            $tableOffset = $this->readUint32($base + 8);
            $tableLength = $this->readUint32($base + 12);
            $this->tables[$tag] = ['offset' => $tableOffset, 'length' => $tableLength];
        }

        // Parse head table
        $headBase = $this->tableOffset('head');
        $unitsPerEm = $this->readUint16($headBase + 18);
        $xMin = $this->readInt16($headBase + 36);
        $yMin = $this->readInt16($headBase + 38);
        $xMax = $this->readInt16($headBase + 40);
        $yMax = $this->readInt16($headBase + 42);

        // Parse hhea table
        $hheaBase = $this->tableOffset('hhea');
        $hheaAscender = $this->readInt16($hheaBase + 4);
        $hheaDescender = $this->readInt16($hheaBase + 6);
        $numberOfHMetrics = $this->readUint16($hheaBase + 34);

        // Parse OS/2 table
        $os2Base = $this->tableOffset('OS/2');
        $os2Version = $this->readUint16($os2Base + 0);
        $usWeightClass = $this->readUint16($os2Base + 4);
        $fsType = $this->readUint16($os2Base + 8);
        $fsSelection = $this->readUint16($os2Base + 62);
        $sTypoAscender = $this->readInt16($os2Base + 68);
        $sTypoDescender = $this->readInt16($os2Base + 70);

        $sxHeight = 0;
        $sCapHeight = 0;
        if ($os2Version >= 2) {
            $sxHeight = $this->readInt16($os2Base + 86);
            $sCapHeight = $this->readInt16($os2Base + 88);
        }

        // Parse post table
        $postBase = $this->tableOffset('post');
        // italicAngle is a Fixed (int32 / 65536.0) at offset 4
        $italicAngleFixed = $this->readInt32($postBase + 4);
        $italicAngle = $italicAngleFixed / 65536.0;
        $isFixedPitch = $this->readUint32($postBase + 12);

        // Parse name table
        $nameBase = $this->tableOffset('name');
        $nameCount = $this->readUint16($nameBase + 2);
        $nameStringOffset = $this->readUint16($nameBase + 4);
        $nameStorageBase = $nameBase + $nameStringOffset;

        $familyName = '';
        $postScriptName = '';

        // Collect all name records, prefer platformID=3,encodingID=1, fallback to platformID=1
        $nameRecords = [
            1 => ['win' => null, 'mac' => null],
            6 => ['win' => null, 'mac' => null],
        ];

        for ($i = 0; $i < $nameCount; $i++) {
            $recBase = $nameBase + 6 + $i * 12;
            $platformID = $this->readUint16($recBase + 0);
            $encodingID = $this->readUint16($recBase + 2);
            $nameID = $this->readUint16($recBase + 6);
            $nameLen = $this->readUint16($recBase + 8);
            $nameOff = $this->readUint16($recBase + 10);

            if (!isset($nameRecords[$nameID])) {
                continue;
            }

            $raw = substr($this->data, $nameStorageBase + $nameOff, $nameLen);

            if ($platformID === 3 && $encodingID === 1) {
                $nameRecords[$nameID]['win'] = mb_convert_encoding($raw, 'UTF-8', 'UTF-16BE');
            } elseif ($platformID === 1 && $nameRecords[$nameID]['mac'] === null) {
                $nameRecords[$nameID]['mac'] = $raw;
            }
        }

        $familyName = $nameRecords[1]['win'] ?? $nameRecords[1]['mac'] ?? '';
        $postScriptName = $nameRecords[6]['win'] ?? $nameRecords[6]['mac'] ?? '';

        // Parse maxp table
        $maxpBase = $this->tableOffset('maxp');
        $numGlyphs = $this->readUint16($maxpBase + 4);

        // Parse hmtx table — build GID => advanceWidth map
        $hmtxBase = $this->tableOffset('hmtx');
        $hmtxWidths = [];
        $lastAdvanceWidth = 0;
        for ($gid = 0; $gid < $numberOfHMetrics; $gid++) {
            $lastAdvanceWidth = $this->readUint16($hmtxBase + $gid * 4);
            $hmtxWidths[$gid] = $lastAdvanceWidth;
        }
        // Glyphs >= numberOfHMetrics reuse last advance width
        for ($gid = $numberOfHMetrics; $gid < $numGlyphs; $gid++) {
            $hmtxWidths[$gid] = $lastAdvanceWidth;
        }

        // Parse cmap table — find best Unicode subtable
        $cmapBase = $this->tableOffset('cmap');
        $cmapNumTables = $this->readUint16($cmapBase + 2);

        $bestOffset = null;
        $bestPriority = -1;
        $bestFormat = 0;

        for ($i = 0; $i < $cmapNumTables; $i++) {
            $recBase = $cmapBase + 4 + $i * 8;
            $platID = $this->readUint16($recBase + 0);
            $encID = $this->readUint16($recBase + 2);
            $subtableOffset = $this->readUint32($recBase + 4);
            $subtableFormat = $this->readUint16($cmapBase + $subtableOffset);

            $priority = -1;
            if ($platID === 3 && $encID === 10 && $subtableFormat === 12) {
                $priority = 4; // Best: Windows UCS-4 format 12 (full Unicode)
            } elseif ($platID === 0 && $encID === 4 && $subtableFormat === 12) {
                $priority = 3; // Unicode full repertoire format 12
            } elseif ($platID === 3 && $encID === 1) {
                $priority = 2; // Windows Unicode BMP
            } elseif ($platID === 0 && $encID === 3) {
                $priority = 1; // Unicode BMP
            } elseif ($platID === 0 && $encID === 0) {
                $priority = 0; // Unicode fallback
            }

            if ($priority > $bestPriority) {
                $bestPriority = $priority;
                $bestOffset = $cmapBase + $subtableOffset;
                $bestFormat = $subtableFormat;
            }
        }

        if ($bestOffset === null) {
            throw new \RuntimeException('No suitable cmap subtable found in font');
        }

        if ($bestFormat === 4) {
            $unicodeToGid = $this->parseCmapFormat4($bestOffset);
        } elseif ($bestFormat === 12) {
            $unicodeToGid = $this->parseCmapFormat12($bestOffset);
        } else {
            throw new \RuntimeException("Unsupported cmap format {$bestFormat}; only formats 4 and 12 are supported");
        }

        // Scale helper
        $scale = fn(int $v): int => (int) round($v * 1000 / $unitsPerEm);

        // Build metrics
        $ascent = $scale($sTypoAscender !== 0 ? $sTypoAscender : $hheaAscender);
        $descent = $scale($sTypoDescender !== 0 ? $sTypoDescender : $hheaDescender);
        $capHeight = $os2Version >= 2 ? $scale($sCapHeight) : (int) round($ascent * 0.7);
        $xHeight = $os2Version >= 2 ? $scale($sxHeight) : (int) round($ascent * 0.5);

        $stemV = max(50, min(220, 50 + (int) ($usWeightClass / 65.0)));

        // PDF flags bitmask
        $flags = 0;
        if ($isFixedPitch !== 0) {
            $flags |= 1; // bit 0: FixedPitch
        }
        $flags |= 32; // bit 5: Nonsymbolic (always set for Latin fonts)
        if ($italicAngle !== 0.0 || ($fsSelection & 0x01)) {
            $flags |= 64; // bit 6: Italic
        }
        if ($fsSelection & 0x20) {
            $flags |= 262144; // bit 18: ForceBold
        }

        $fontBBox = [
            $scale($xMin),
            $scale($yMin),
            $scale($xMax),
            $scale($yMax),
        ];

        // Build charWidths and unicodeMap from WinAnsi bytes 32-255
        $charWidths = [];
        $unicodeMap = [];

        for ($byte = 32; $byte <= 255; $byte++) {
            $codepoint = $this->win1252ToUnicode($byte);
            if ($codepoint === null) {
                $charWidths[$byte] = 0;
                continue;
            }

            if (isset($unicodeToGid[$codepoint])) {
                $gid = $unicodeToGid[$codepoint];
                $advance = $hmtxWidths[$gid] ?? 0;
                $charWidths[$byte] = $scale($advance);
                $unicodeMap[$byte] = $codepoint;
            } else {
                $charWidths[$byte] = 0;
            }
        }

        $embeddingAllowed = ($fsType & 0x000E) !== 2;

        // Parse kerning data (GPOS or legacy kern table)
        $kernPairs = null;
        if (isset($this->tables['GPOS']) || isset($this->tables['kern'])) {
            $kernPairs = (new KerningParser())->parse($this->data, $this->tables) ?: null;
        }

        // Parse GSUB ligature data
        $ligatures = null;
        if (isset($this->tables['GSUB'])) {
            $ligatures = (new GsubParser())->parse($this->data, $this->tables) ?: null;
        }

        // Parse fvar table for variable font axes and named instances
        $isVariableFont = isset($this->tables['fvar']);
        $variationAxes = null;
        $namedInstances = null;
        if ($isVariableFont) {
            [$variationAxes, $namedInstances] = $this->parseFvar();
        }

        return new TrueTypeData(
            postScriptName: $postScriptName,
            familyName: $familyName,
            ascent: $ascent,
            descent: $descent,
            capHeight: $capHeight,
            xHeight: $xHeight,
            italicAngle: $italicAngle,
            stemV: $stemV,
            flags: $flags,
            fontBBox: $fontBBox,
            charWidths: $charWidths,
            unicodeMap: $unicodeMap,
            fontBytes: $this->data,
            embeddingAllowed: $embeddingAllowed,
            unitsPerEm: $unitsPerEm,
            fullUnicodeToGid: $unicodeToGid,
            glyphWidths: $hmtxWidths,
            kernPairs: $kernPairs,
            ligatures: $ligatures,
            isVariableFont: $isVariableFont,
            variationAxes: $variationAxes,
            namedInstances: $namedInstances,
        );
    }

    /**
     * @return array<int, int> Unicode codepoint => GID
     */
    private function parseCmapFormat4(int $offset): array
    {
        $segCountX2 = $this->readUint16($offset + 6);
        $segCount = $segCountX2 / 2;

        $endCodesBase = $offset + 14;
        $startCodesBase = $offset + 16 + $segCountX2;
        $idDeltaBase = $offset + 16 + 2 * $segCountX2;
        $idRangeOffsetBase = $offset + 16 + 3 * $segCountX2;
        $glyphIdArrayBase = $offset + 16 + 4 * $segCountX2;

        $endCodes = [];
        $startCodes = [];
        $idDelta = [];
        $idRangeOffset = [];

        for ($i = 0; $i < $segCount; $i++) {
            $endCodes[$i] = $this->readUint16($endCodesBase + $i * 2);
            $startCodes[$i] = $this->readUint16($startCodesBase + $i * 2);
            $idDelta[$i] = $this->readInt16($idDeltaBase + $i * 2);
            $idRangeOffset[$i] = $this->readUint16($idRangeOffsetBase + $i * 2);
        }

        $subtableLength = $this->readUint16($offset + 2);
        $glyphIdArrayLen = ($subtableLength - (16 + 4 * $segCountX2)) / 2;

        $glyphIdArray = [];
        for ($j = 0; $j < $glyphIdArrayLen; $j++) {
            $glyphIdArray[$j] = $this->readUint16($glyphIdArrayBase + $j * 2);
        }

        $unicodeToGid = [];
        for ($i = 0; $i < $segCount; $i++) {
            if ($startCodes[$i] === 0xFFFF) {
                continue;
            }
            for ($cp = $startCodes[$i]; $cp <= $endCodes[$i]; $cp++) {
                if ($idRangeOffset[$i] === 0) {
                    $gid = ($cp + $idDelta[$i]) & 0xFFFF;
                } else {
                    $idx = $idRangeOffset[$i] / 2 + ($cp - $startCodes[$i]) + $i - $segCount;
                    if ($idx < 0 || $idx >= count($glyphIdArray)) {
                        $gid = 0;
                    } else {
                        $gid = $glyphIdArray[$idx];
                        if ($gid !== 0) {
                            $gid = ($gid + $idDelta[$i]) & 0xFFFF;
                        }
                    }
                }
                if ($gid !== 0) {
                    $unicodeToGid[$cp] = $gid;
                }
            }
        }

        return $unicodeToGid;
    }

    /**
     * @return array<int, int> Unicode codepoint => GID
     */
    private function parseCmapFormat12(int $offset): array
    {
        $nGroups = $this->readUint32($offset + 12);
        $map = [];
        for ($i = 0; $i < $nGroups; $i++) {
            $base = $offset + 16 + $i * 12;
            $startCharCode = $this->readUint32($base);
            $endCharCode = $this->readUint32($base + 4);
            $startGlyphID = $this->readUint32($base + 8);
            for ($cp = $startCharCode; $cp <= $endCharCode; $cp++) {
                $map[$cp] = $startGlyphID + ($cp - $startCharCode);
            }
        }
        return $map;
    }

    private function win1252ToUnicode(int $byte): ?int
    {
        if ($byte >= 32 && $byte <= 127) {
            return $byte;
        }
        if ($byte >= 160 && $byte <= 255) {
            return $byte;
        }
        // bytes 128-159 special mapping
        return self::WIN1252_MAP[$byte] ?? null;
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

    private function readInt32(int $offset): int
    {
        $v = $this->readUint32($offset);
        if ($v >= 0x80000000) {
            $v -= 0x100000000;
        }
        return (int) $v;
    }

    /**
     * Parse the fvar table for variable font axes and named instances.
     *
     * fvar table structure (OpenType spec §7.3.3):
     *   - majorVersion (uint16), minorVersion (uint16)
     *   - axesArrayOffset (uint16) — offset to the axis array
     *   - reserved (uint16)
     *   - axisCount (uint16)
     *   - axisSize (uint16) — bytes per axis record (20)
     *   - instanceCount (uint16)
     *   - instanceSize (uint16) — bytes per instance record
     *
     * Each axis record (20 bytes):
     *   - tag (4 bytes), minValue (Fixed), defaultValue (Fixed),
     *     maxValue (Fixed), flags (uint16), nameId (uint16)
     *
     * Each instance record (variable):
     *   - subfamilyNameId (uint16), flags (uint16),
     *     coordinates (Fixed × axisCount), [postScriptNameId (uint16)]
     *
     * @return array{list<array{tag: string, minValue: float, defaultValue: float, maxValue: float, nameId: int}>, list<array{subfamilyNameId: int, coordinates: array<string, float>}>}
     */
    private function parseFvar(): array
    {
        $base = $this->tableOffset('fvar');

        // $majorVersion = $this->readUint16($base);
        // $minorVersion = $this->readUint16($base + 2);
        $axesArrayOffset = $this->readUint16($base + 4);
        // $reserved = $this->readUint16($base + 6);
        $axisCount = $this->readUint16($base + 8);
        $axisSize = $this->readUint16($base + 10);
        $instanceCount = $this->readUint16($base + 12);
        $instanceSize = $this->readUint16($base + 14);

        // Parse axes
        $axes = [];
        $axisTags = [];
        $axisBase = $base + $axesArrayOffset;
        for ($i = 0; $i < $axisCount; $i++) {
            $axisOffset = $axisBase + $i * $axisSize;
            $tag = substr($this->data, $axisOffset, 4);
            $minValue = $this->readFixed($axisOffset + 4);
            $defaultValue = $this->readFixed($axisOffset + 8);
            $maxValue = $this->readFixed($axisOffset + 12);
            // $flags = $this->readUint16($axisOffset + 16);
            $nameId = $this->readUint16($axisOffset + 18);

            $axisTags[] = $tag;
            $axes[] = [
                'tag' => $tag,
                'minValue' => $minValue,
                'defaultValue' => $defaultValue,
                'maxValue' => $maxValue,
                'nameId' => $nameId,
            ];
        }

        // Parse named instances
        $instances = [];
        $instanceBase = $axisBase + $axisCount * $axisSize;
        for ($i = 0; $i < $instanceCount; $i++) {
            $instOffset = $instanceBase + $i * $instanceSize;
            $subfamilyNameId = $this->readUint16($instOffset);
            // $flags = $this->readUint16($instOffset + 2);

            $coordinates = [];
            for ($a = 0; $a < $axisCount; $a++) {
                $coordinates[$axisTags[$a]] = $this->readFixed($instOffset + 4 + $a * 4);
            }

            $instances[] = [
                'subfamilyNameId' => $subfamilyNameId,
                'coordinates' => $coordinates,
            ];
        }

        return [$axes, $instances];
    }

    /**
     * Read a Fixed (16.16) value as a float.
     */
    private function readFixed(int $offset): float
    {
        $raw = $this->readInt32($offset);
        return $raw / 65536.0;
    }

    private function tableOffset(string $tag): int
    {
        if (!isset($this->tables[$tag])) {
            throw new \RuntimeException("Required table '{$tag}' not found in font");
        }
        return $this->tables[$tag]['offset'];
    }
}
