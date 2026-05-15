<?php

declare(strict_types=1);

namespace Phpdftk\FontParser;

use Phpdftk\Filesystem\LocalFilesystem;

/**
 * Parser for OpenType fonts with CFF outlines (sfVersion "OTTO").
 *
 * Reuses the same table-level parsing as TrueType (head, hhea, OS/2,
 * maxp, hmtx, cmap, name, post) since these tables have identical
 * format in both TrueType and OpenType-CFF. The CFF table data is
 * extracted as raw bytes for embedding (we don't parse CFF charstrings).
 *
 * The parsed result is an OpenTypeData object containing metrics,
 * glyph widths, Unicode mappings, and raw CFF bytes.
 */
final class OpenTypeParser
{
    private string $data;

    /** @var array<string, array{offset:int, length:int}> */
    private array $tables = [];

    // Windows-1252 byte => Unicode codepoint for bytes 128-159
    private const WIN1252_MAP = [
        128 => 0x20AC, 130 => 0x201A, 131 => 0x0192, 132 => 0x201E,
        133 => 0x2026, 134 => 0x2020, 135 => 0x2021, 136 => 0x02C6,
        137 => 0x2030, 138 => 0x0160, 139 => 0x2039, 140 => 0x0152,
        142 => 0x017D, 145 => 0x2018, 146 => 0x2019, 147 => 0x201C,
        148 => 0x201D, 149 => 0x2022, 150 => 0x2013, 151 => 0x2014,
        152 => 0x02DC, 153 => 0x2122, 154 => 0x0161, 155 => 0x203A,
        156 => 0x0153, 158 => 0x017E, 159 => 0x0178,
    ];

    public function __construct(private readonly string $path) {}

    /**
     * Create a parser from raw font bytes instead of a file path.
     */
    public static function fromBytes(string $fontBytes): self
    {
        $tmp = tempnam(sys_get_temp_dir(), 'phpdftk_otf_');
        if ($tmp === false) {
            throw new \RuntimeException('Cannot create temp file for font data');
        }
        file_put_contents($tmp, $fontBytes);
        return new self($tmp);
    }

    public function parse(): OpenTypeData
    {
        $this->data = LocalFilesystem::readFile($this->path, "font file");

        // Validate OpenType CFF signature
        $sfVersion = $this->readUint32(0);
        if ($sfVersion !== 0x4F54544F) {
            throw new \RuntimeException(sprintf(
                'Not an OpenType CFF font (sfVersion=0x%08X); expected 0x4F54544F ("OTTO")',
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

        // Extract raw CFF table
        if (!isset($this->tables['CFF'])) {
            throw new \RuntimeException('OpenType font has no CFF table');
        }
        $cffOffset = $this->tables['CFF']['offset'];
        $cffLength = $this->tables['CFF']['length'];
        $cffBytes = substr($this->data, $cffOffset, $cffLength);

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
        $os2Version = $this->readUint16($os2Base);
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
        $italicAngleFixed = $this->readInt32($postBase + 4);
        $italicAngle = $italicAngleFixed / 65536.0;
        $isFixedPitch = $this->readUint32($postBase + 12);

        // Parse name table
        $nameBase = $this->tableOffset('name');
        $nameCount = $this->readUint16($nameBase + 2);
        $nameStringOffset = $this->readUint16($nameBase + 4);
        $nameStorageBase = $nameBase + $nameStringOffset;

        $nameRecords = [1 => ['win' => null, 'mac' => null], 6 => ['win' => null, 'mac' => null]];

        for ($i = 0; $i < $nameCount; $i++) {
            $recBase = $nameBase + 6 + $i * 12;
            $platformID = $this->readUint16($recBase);
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

        // Parse maxp
        $maxpBase = $this->tableOffset('maxp');
        $numGlyphs = $this->readUint16($maxpBase + 4);

        // Parse hmtx
        $hmtxBase = $this->tableOffset('hmtx');
        $hmtxWidths = [];
        $lastAdvanceWidth = 0;
        for ($gid = 0; $gid < $numberOfHMetrics; $gid++) {
            $lastAdvanceWidth = $this->readUint16($hmtxBase + $gid * 4);
            $hmtxWidths[$gid] = $lastAdvanceWidth;
        }
        for ($gid = $numberOfHMetrics; $gid < $numGlyphs; $gid++) {
            $hmtxWidths[$gid] = $lastAdvanceWidth;
        }

        // Parse cmap — find best Unicode subtable
        $cmapBase = $this->tableOffset('cmap');
        $cmapNumTables = $this->readUint16($cmapBase + 2);

        $bestOffset = null;
        $bestPriority = -1;
        $bestFormat = 0;

        for ($i = 0; $i < $cmapNumTables; $i++) {
            $recBase = $cmapBase + 4 + $i * 8;
            $platID = $this->readUint16($recBase);
            $encID = $this->readUint16($recBase + 2);
            $subtableOffset = $this->readUint32($recBase + 4);

            $priority = -1;
            if ($platID === 3 && $encID === 1) {
                $priority = 2;
            } elseif ($platID === 0 && $encID === 3) {
                $priority = 1;
            } elseif ($platID === 0 && $encID === 0) {
                $priority = 0;
            }

            if ($priority > $bestPriority) {
                $bestPriority = $priority;
                $bestOffset = $cmapBase + $subtableOffset;
                $bestFormat = $this->readUint16($cmapBase + $subtableOffset);
            }
        }

        $unicodeToGid = [];
        if ($bestOffset !== null) {
            if ($bestFormat === 4) {
                $unicodeToGid = $this->parseCmapFormat4($bestOffset);
            } elseif ($bestFormat === 12) {
                $unicodeToGid = $this->parseCmapFormat12($bestOffset);
            }
        }

        // Scale helper
        $scale = fn(int $v): int => (int) round($v * 1000 / $unitsPerEm);

        $ascent = $scale($sTypoAscender !== 0 ? $sTypoAscender : $hheaAscender);
        $descent = $scale($sTypoDescender !== 0 ? $sTypoDescender : $hheaDescender);
        $capHeight = $os2Version >= 2 ? $scale($sCapHeight) : (int) round($ascent * 0.7);
        $xHeight = $os2Version >= 2 ? $scale($sxHeight) : (int) round($ascent * 0.5);
        $stemV = max(50, min(220, 50 + (int) ($usWeightClass / 65.0)));

        $flags = 0;
        if ($isFixedPitch !== 0) {
            $flags |= 1;
        }
        $flags |= 32; // Nonsymbolic
        if ($italicAngle !== 0.0 || ($fsSelection & 0x01)) {
            $flags |= 64;
        }
        if ($fsSelection & 0x20) {
            $flags |= 262144;
        }

        $fontBBox = [$scale($xMin), $scale($yMin), $scale($xMax), $scale($yMax)];

        // Build WinAnsi charWidths and unicodeMap
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
                $charWidths[$byte] = $scale($hmtxWidths[$gid] ?? 0);
                $unicodeMap[$byte] = $codepoint;
            } else {
                $charWidths[$byte] = 0;
            }
        }

        $embeddingAllowed = ($fsType & 0x000E) !== 2;

        // Parse vertical metrics (vhea + vmtx tables) if present
        $verticalWidths = null;
        if (isset($this->tables['vhea']) && isset($this->tables['vmtx'])) {
            $vheaBase = $this->tables['vhea']['offset'];
            $numOfLongVerMetrics = $this->readUint16($vheaBase + 34);

            $vmtxBase = $this->tables['vmtx']['offset'];
            $verticalWidths = [];
            $lastAdvanceHeight = 0;
            for ($gid = 0; $gid < $numOfLongVerMetrics; $gid++) {
                $lastAdvanceHeight = $this->readUint16($vmtxBase + $gid * 4);
                $verticalWidths[$gid] = $lastAdvanceHeight;
            }
            // Remaining GIDs use the last advance height
            for ($gid = $numOfLongVerMetrics; $gid < $numGlyphs; $gid++) {
                $verticalWidths[$gid] = $lastAdvanceHeight;
            }
        }

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

        return new OpenTypeData(
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
            cffBytes: $cffBytes,
            fontBytes: $this->data,
            embeddingAllowed: $embeddingAllowed,
            unitsPerEm: $unitsPerEm,
            fullUnicodeToGid: $unicodeToGid,
            glyphWidths: $hmtxWidths,
            kernPairs: $kernPairs,
            ligatures: $ligatures,
            verticalWidths: $verticalWidths,
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

        $subtableLength = $this->readUint16($offset + 2);
        $glyphIdArrayBase = $offset + 16 + 4 * $segCountX2;
        $glyphIdArrayLen = ($subtableLength - (16 + 4 * $segCountX2)) / 2;

        $endCodes = [];
        $startCodes = [];
        $idDelta = [];
        $idRangeOffset = [];
        $glyphIdArray = [];

        for ($i = 0; $i < $segCount; $i++) {
            $endCodes[$i] = $this->readUint16($endCodesBase + $i * 2);
            $startCodes[$i] = $this->readUint16($startCodesBase + $i * 2);
            $idDelta[$i] = $this->readInt16($idDeltaBase + $i * 2);
            $idRangeOffset[$i] = $this->readUint16($idRangeOffsetBase + $i * 2);
        }

        for ($j = 0; $j < $glyphIdArrayLen; $j++) {
            $glyphIdArray[$j] = $this->readUint16($glyphIdArrayBase + $j * 2);
        }

        $map = [];
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
                    $map[$cp] = $gid;
                }
            }
        }
        return $map;
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
        return self::WIN1252_MAP[$byte] ?? null;
    }

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

    private function readInt32(int $offset): int
    {
        $v = $this->readUint32($offset);
        return $v >= 0x80000000 ? (int) ($v - 0x100000000) : (int) $v;
    }

    private function tableOffset(string $tag): int
    {
        if (!isset($this->tables[$tag])) {
            throw new \RuntimeException("Required table '{$tag}' not found in font");
        }
        return $this->tables[$tag]['offset'];
    }
}
