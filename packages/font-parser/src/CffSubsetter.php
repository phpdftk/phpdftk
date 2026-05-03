<?php

declare(strict_types=1);

namespace Phpdftk\FontParser;

/**
 * Produces a minimal CFF table containing only requested glyphs.
 *
 * Takes raw CFF table bytes and a set of glyph IDs, and emits a new
 * CFF with only those glyph charstrings and charset entries.
 *
 * All subroutines (Global + Local) are preserved intact to avoid
 * charstring bytecode analysis. Uses 5-byte integer encoding for
 * offset operands in the Top DICT to guarantee stable sizing.
 */
final class CffSubsetter
{
    /**
     * @param string $cffBytes Raw CFF table bytes
     * @param int[]  $glyphIds GIDs to keep (GID 0 is always included)
     * @return string Subset CFF bytes
     */
    public function subset(string $cffBytes, array $glyphIds): string
    {
        $parser = new CffParser();
        $cffData = $parser->parse($cffBytes);

        // Always include GID 0, deduplicate and sort
        $glyphIds = array_unique(array_merge([0], $glyphIds));
        sort($glyphIds);

        // Filter to valid GIDs
        $nOrigGlyphs = count($cffData->charStrings);
        $glyphIds = array_filter($glyphIds, fn(int $gid): bool => $gid < $nOrigGlyphs);
        $glyphIds = array_values($glyphIds);

        // Build subset charstrings
        $subsetCharStrings = [];
        foreach ($glyphIds as $oldGid) {
            $subsetCharStrings[] = $cffData->charStrings[$oldGid];
        }

        // Build subset charset (format 0: simple SID list)
        $subsetCharset = [];
        foreach ($glyphIds as $newGid => $oldGid) {
            $subsetCharset[$newGid] = $cffData->charset[$oldGid] ?? $oldGid;
        }

        // Build the output CFF
        // Structure: Header + Name INDEX + Top DICT INDEX + String INDEX +
        //            Global Subr INDEX + Charset + CharStrings INDEX +
        //            Private DICT + Local Subr INDEX

        // Header (4 bytes)
        $header = chr($cffData->major) . chr($cffData->minor) . chr(4) . chr(1);

        // Name INDEX (preserved as-is)
        $nameIndex = $cffData->nameIndexData;

        // String INDEX (preserved as-is)
        $stringIndex = $cffData->stringIndexData;

        // Global Subr INDEX (preserved as-is)
        $globalSubrIndex = $cffData->globalSubrIndexData;

        // Build Charset (format 0)
        $charsetData = $this->buildCharset($subsetCharset);

        // Build CharStrings INDEX
        $charStringsIndex = $this->buildIndex($subsetCharStrings);

        // Private DICT + Local Subr INDEX (preserved as-is)
        $privateDict = $cffData->privateDictData;
        $localSubrIndex = $cffData->localSubrIndexData;

        // Patch the Private DICT's local subr offset BEFORE measuring sizes,
        // since patching can change the Private DICT length.
        if ($localSubrIndex !== '') {
            $privateDict = $this->patchPrivateDictLocalSubr($privateDict, strlen($privateDict));
        }

        // Layout: Header + Name INDEX + Top DICT INDEX + String INDEX +
        //         Global Subr INDEX + Charset + CharStrings INDEX +
        //         Private DICT + Local Subr INDEX
        //
        // We use 5-byte integers for all offset operands so the Top DICT
        // has a deterministic size.

        // Private size in Top DICT op 18 is ONLY the Private DICT size
        // (local subrs are located via op 19 inside Private DICT)
        $privateSize = strlen($privateDict);

        $topDictContent = $this->buildTopDict(
            $cffData->topDictOperators,
            charsetOffset: 0,         // placeholder
            charStringsOffset: 0,     // placeholder
            privateSize: $privateSize,
            privateOffset: 0,         // placeholder
            nGlyphs: count($subsetCharStrings),
        );

        $topDictIndex = $this->buildIndex([$topDictContent]);

        // Calculate actual offsets
        $afterTopDict = strlen($header) + strlen($nameIndex) + strlen($topDictIndex);
        $afterStringIndex = $afterTopDict + strlen($stringIndex);
        $afterGlobalSubr = $afterStringIndex + strlen($globalSubrIndex);
        $charsetOffset = $afterGlobalSubr;
        $charStringsOffset = $charsetOffset + strlen($charsetData);
        $privateOffset = $charStringsOffset + strlen($charStringsIndex);

        // Rebuild Top DICT with real offsets
        $topDictContent = $this->buildTopDict(
            $cffData->topDictOperators,
            charsetOffset: $charsetOffset,
            charStringsOffset: $charStringsOffset,
            privateSize: $privateSize,
            privateOffset: $privateOffset,
            nGlyphs: count($subsetCharStrings),
        );
        $topDictIndex = $this->buildIndex([$topDictContent]);

        // Recalculate offsets — Top DICT INDEX size should be stable since
        // we use 5-byte integers, but verify and recalculate if needed
        $newAfterTopDict = strlen($header) + strlen($nameIndex) + strlen($topDictIndex);
        if ($newAfterTopDict !== $afterTopDict) {
            $afterTopDict = $newAfterTopDict;
            $afterStringIndex = $afterTopDict + strlen($stringIndex);
            $afterGlobalSubr = $afterStringIndex + strlen($globalSubrIndex);
            $charsetOffset = $afterGlobalSubr;
            $charStringsOffset = $charsetOffset + strlen($charsetData);
            $privateOffset = $charStringsOffset + strlen($charStringsIndex);

            $topDictContent = $this->buildTopDict(
                $cffData->topDictOperators,
                charsetOffset: $charsetOffset,
                charStringsOffset: $charStringsOffset,
                privateSize: $privateSize,
                privateOffset: $privateOffset,
                nGlyphs: count($subsetCharStrings),
            );
            $topDictIndex = $this->buildIndex([$topDictContent]);
        }

        return $header . $nameIndex . $topDictIndex . $stringIndex
            . $globalSubrIndex . $charsetData . $charStringsIndex
            . $privateDict . $localSubrIndex;
    }

    /**
     * Build a CFF INDEX from an array of byte entries.
     *
     * @param string[] $entries
     */
    private function buildIndex(array $entries): string
    {
        $count = count($entries);
        if ($count === 0) {
            return pack('n', 0); // count=0, no further data
        }

        // Calculate offsets (1-based)
        $offsets = [1]; // first entry starts at offset 1
        foreach ($entries as $entry) {
            $offsets[] = end($offsets) + strlen($entry);
        }

        // Determine offSize
        $maxOffset = end($offsets);
        if ($maxOffset <= 0xFF) {
            $offSize = 1;
        } elseif ($maxOffset <= 0xFFFF) {
            $offSize = 2;
        } elseif ($maxOffset <= 0xFFFFFF) {
            $offSize = 3;
        } else {
            $offSize = 4;
        }

        // Header: count(2) + offSize(1)
        $result = pack('n', $count) . chr($offSize);

        // Offsets
        foreach ($offsets as $off) {
            $result .= $this->encodeOffset($off, $offSize);
        }

        // Data
        foreach ($entries as $entry) {
            $result .= $entry;
        }

        return $result;
    }

    private function encodeOffset(int $offset, int $offSize): string
    {
        return match ($offSize) {
            1 => chr($offset),
            2 => pack('n', $offset),
            3 => chr(($offset >> 16) & 0xFF) . chr(($offset >> 8) & 0xFF) . chr($offset & 0xFF),
            default => pack('N', $offset),
        };
    }

    /**
     * Build Top DICT with patched offset operands.
     *
     * Preserves all original operators except those that reference
     * offsets into the CFF data (charset, CharStrings, Private).
     * Uses 5-byte integer encoding for offset values.
     *
     * @param array<int|string, int|float|array<int, int|float>> $originalOps
     */
    private function buildTopDict(
        array $originalOps,
        int $charsetOffset,
        int $charStringsOffset,
        int $privateSize,
        int $privateOffset,
        int $nGlyphs,
    ): string {
        $result = '';

        // Operators to skip (we'll emit them with patched values)
        $patchedOps = [15, 16, 17, 18, '12.36', '12.37'];

        foreach ($originalOps as $op => $operands) {
            if (in_array($op, $patchedOps, true)) {
                continue;
            }
            $result .= $this->encodeDictEntry($op, $operands);
        }

        // Emit patched offset operators with 5-byte integers
        // Charset (15)
        $result .= $this->encode5ByteInt($charsetOffset) . chr(15);

        // CharStrings (17)
        $result .= $this->encode5ByteInt($charStringsOffset) . chr(17);

        // Private (18) — [size, offset]
        $result .= $this->encode5ByteInt($privateSize) . $this->encode5ByteInt($privateOffset) . chr(18);

        // Skip Encoding (16), FDArray (12.36), FDSelect (12.37) — not needed for CIDFontType0C subsets

        return $result;
    }

    /**
     * Encode a DICT entry (operands + operator).
     *
     * @param int|string $op
     * @param int|float|array<int, int|float> $operands
     */
    private function encodeDictEntry(int|string $op, int|float|array $operands): string
    {
        $result = '';
        $operandList = is_array($operands) ? $operands : [$operands];

        foreach ($operandList as $val) {
            if (is_float($val)) {
                $result .= $this->encodeDictReal($val);
            } else {
                $result .= $this->encodeDictInteger((int) $val);
            }
        }

        // Encode operator
        if (is_string($op) && str_starts_with($op, '12.')) {
            $b1 = (int) substr($op, 3);
            $result .= chr(12) . chr($b1);
        } else {
            $result .= chr((int) $op);
        }

        return $result;
    }

    /**
     * Encode a DICT integer using the most compact representation.
     */
    private function encodeDictInteger(int $value): string
    {
        if ($value >= -107 && $value <= 107) {
            return chr($value + 139);
        }
        if ($value >= 108 && $value <= 1131) {
            $value -= 108;
            return chr(247 + ($value >> 8)) . chr($value & 0xFF);
        }
        if ($value >= -1131 && $value <= -108) {
            $value = -$value - 108;
            return chr(251 + ($value >> 8)) . chr($value & 0xFF);
        }
        if ($value >= -32768 && $value <= 32767) {
            if ($value < 0) {
                $value += 0x10000;
            }
            return chr(28) . chr(($value >> 8) & 0xFF) . chr($value & 0xFF);
        }
        // 5-byte integer
        return $this->encode5ByteInt($value);
    }

    /**
     * Encode a 5-byte DICT integer (operator 29 + 4 bytes big-endian).
     */
    private function encode5ByteInt(int $value): string
    {
        if ($value < 0) {
            $value = (int) ($value + 0x100000000);
        }
        return chr(29)
            . chr(($value >> 24) & 0xFF)
            . chr(($value >> 16) & 0xFF)
            . chr(($value >> 8) & 0xFF)
            . chr($value & 0xFF);
    }

    /**
     * Encode a DICT real number.
     */
    private function encodeDictReal(float $value): string
    {
        $str = rtrim(rtrim(sprintf('%.10f', $value), '0'), '.');
        if ($str === '') {
            $str = '0';
        }

        $nibbles = [];
        for ($i = 0; $i < strlen($str); $i++) {
            $ch = $str[$i];
            $nibbles[] = match ($ch) {
                '0','1','2','3','4','5','6','7','8','9' => (int) $ch,
                '.' => 0x0A,
                '-' => 0x0E,
                'E','e' => 0x0B,
                default => 0x0D, // reserved
            };
        }
        $nibbles[] = 0x0F; // end

        // Pad to even number of nibbles
        if (count($nibbles) % 2 !== 0) {
            $nibbles[] = 0x0F;
        }

        $result = chr(30);
        for ($i = 0; $i < count($nibbles); $i += 2) {
            $result .= chr(($nibbles[$i] << 4) | $nibbles[$i + 1]);
        }
        return $result;
    }

    /**
     * Build Charset in format 0 (simple SID list).
     *
     * @param array<int, int> $charset GID => SID
     */
    private function buildCharset(array $charset): string
    {
        $nGlyphs = count($charset);
        if ($nGlyphs <= 1) {
            // Only .notdef — use predefined charset 0
            return '';
        }

        $data = chr(0); // format 0
        // Skip GID 0 (.notdef) — always SID 0, not stored
        for ($gid = 1; $gid < $nGlyphs; $gid++) {
            $sid = $charset[$gid] ?? 0;
            $data .= pack('n', $sid);
        }
        return $data;
    }

    /**
     * Patch the Private DICT to update the Local Subr offset (operator 19).
     *
     * The relative offset must equal the final Private DICT size, since
     * local subrs are laid out immediately after it. We strip op 19,
     * measure, then append it with a 5-byte encoded value.
     */
    private function patchPrivateDictLocalSubr(string $privateDict, int $_unused): string
    {
        $ops = $this->parseDictDataDirect($privateDict);

        // Rebuild WITHOUT operator 19
        $result = '';
        foreach ($ops as $op => $operands) {
            if ($op === 19) {
                continue; // skip — we'll add it at the end
            }
            $result .= $this->encodeDictEntry($op, $operands);
        }

        // Op 19 entry = 5-byte int + 1-byte operator = 6 bytes
        // Relative offset = size of everything before local subrs = len(result) + 6
        $relativeOffset = strlen($result) + 6;
        $result .= $this->encode5ByteInt($relativeOffset) . chr(19);

        return $result;
    }

    /**
     * Parse DICT data directly (duplicated from CffParser for self-containment).
     *
     * @return array<int|string, int|float|array<int, int|float>>
     */
    private function parseDictDataDirect(string $data): array
    {
        $operators = [];
        $operandStack = [];
        $pos = 0;
        $len = strlen($data);

        while ($pos < $len) {
            $b0 = ord($data[$pos]);

            if ($b0 >= 32 && $b0 <= 246) {
                $operandStack[] = $b0 - 139;
                $pos++;
            } elseif ($b0 >= 247 && $b0 <= 250) {
                $b1 = ord($data[$pos + 1]);
                $operandStack[] = ($b0 - 247) * 256 + $b1 + 108;
                $pos += 2;
            } elseif ($b0 >= 251 && $b0 <= 254) {
                $b1 = ord($data[$pos + 1]);
                $operandStack[] = -($b0 - 251) * 256 - $b1 - 108;
                $pos += 2;
            } elseif ($b0 === 28) {
                $val = (ord($data[$pos + 1]) << 8) | ord($data[$pos + 2]);
                if ($val >= 0x8000) {
                    $val -= 0x10000;
                }
                $operandStack[] = $val;
                $pos += 3;
            } elseif ($b0 === 29) {
                $val = (ord($data[$pos + 1]) << 24) | (ord($data[$pos + 2]) << 16)
                    | (ord($data[$pos + 3]) << 8) | ord($data[$pos + 4]);
                if ($val >= 0x80000000) {
                    $val = (int) ($val - 0x100000000);
                }
                $operandStack[] = $val;
                $pos += 5;
            } elseif ($b0 === 30) {
                $realStr = '';
                $pos++;
                $done = false;
                while (!$done && $pos < $len) {
                    $byte = ord($data[$pos]);
                    $pos++;
                    for ($nib = 0; $nib < 2; $nib++) {
                        $nibble = ($nib === 0) ? ($byte >> 4) : ($byte & 0x0F);
                        switch ($nibble) {
                            case 0: case 1: case 2: case 3: case 4:
                            case 5: case 6: case 7: case 8: case 9:
                                $realStr .= (string) $nibble;
                                break;
                            case 0x0A: $realStr .= '.'; break;
                            case 0x0B: $realStr .= 'E'; break;
                            case 0x0C: $realStr .= 'E-'; break;
                            case 0x0E: $realStr .= '-'; break;
                            case 0x0F: $done = true; break;
                        }
                        if ($done) break;
                    }
                }
                $operandStack[] = (float) $realStr;
            } elseif ($b0 === 12) {
                $pos++;
                $b1 = ord($data[$pos]);
                $pos++;
                $key = '12.' . $b1;
                $operators[$key] = count($operandStack) === 1 ? $operandStack[0] : $operandStack;
                $operandStack = [];
            } elseif ($b0 <= 21) {
                $pos++;
                $operators[$b0] = count($operandStack) === 1 ? $operandStack[0] : $operandStack;
                $operandStack = [];
            } else {
                $pos++;
            }
        }

        return $operators;
    }
}
