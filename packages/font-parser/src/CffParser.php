<?php

declare(strict_types=1);

namespace Phpdftk\FontParser;

/**
 * Parses a CFF (Compact Font Format) binary table into a CffData structure.
 *
 * Implements parsing per Adobe Technical Note #5176 (CFF spec) and
 * #5177 (Type 2 Charstring format). Does NOT interpret charstring
 * bytecode — charstrings are stored as opaque byte arrays.
 */
final class CffParser
{
    private string $data;
    private int $length;

    public function parse(string $cffBytes): CffData
    {
        $this->data = $cffBytes;
        $this->length = strlen($cffBytes);

        // Header: major(1) minor(1) hdrSize(1) offSize(1)
        $major = ord($this->data[0]);
        $minor = ord($this->data[1]);
        $hdrSize = ord($this->data[2]);

        $offset = $hdrSize;

        // Name INDEX
        $nameIndexStart = $offset;
        $nameIndex = $this->parseIndex($offset);
        $offset = $nameIndex['nextOffset'];
        $nameIndexData = substr($this->data, $nameIndexStart, $offset - $nameIndexStart);

        // Top DICT INDEX
        $topDictIndex = $this->parseIndex($offset);
        $offset = $topDictIndex['nextOffset'];

        // Parse Top DICT data (first entry only — single-font CFF)
        $topDictOperators = [];
        if (!empty($topDictIndex['entries'])) {
            $topDictOperators = $this->parseDictData($topDictIndex['entries'][0]);
        }

        // String INDEX
        $stringIndexStart = $offset;
        $stringIndex = $this->parseIndex($offset);
        $offset = $stringIndex['nextOffset'];
        $stringIndexData = substr($this->data, $stringIndexStart, $offset - $stringIndexStart);

        // Global Subr INDEX
        $globalSubrStart = $offset;
        $globalSubrIndex = $this->parseIndex($offset);
        $offset = $globalSubrIndex['nextOffset'];
        $globalSubrIndexData = substr($this->data, $globalSubrStart, $offset - $globalSubrStart);

        // CharStrings INDEX (located via Top DICT operator 17)
        $charStringsOffset = $this->getTopDictInt($topDictOperators, 17);
        if ($charStringsOffset === null) {
            throw new \RuntimeException('CFF Top DICT missing CharStrings operator (17)');
        }
        $charStringsIndex = $this->parseIndex($charStringsOffset);
        $charStrings = [];
        foreach ($charStringsIndex['entries'] as $gid => $entry) {
            $charStrings[$gid] = $entry;
        }

        // Private DICT (operator 18 = [size, offset])
        $privateDictData = '';
        $localSubrIndexData = '';
        $privateOp = $topDictOperators[18] ?? null;
        if (is_array($privateOp) && count($privateOp) === 2) {
            $privateSize = (int) $privateOp[0];
            $privateOffset = (int) $privateOp[1];
            $privateDictData = substr($this->data, $privateOffset, $privateSize);

            // Local Subr INDEX (located via Private DICT operator 19)
            $privateDictOps = $this->parseDictData($privateDictData);
            $localSubrOffset = $this->getTopDictInt($privateDictOps, 19);
            if ($localSubrOffset !== null) {
                $absLocalSubrOffset = $privateOffset + $localSubrOffset;
                $localSubrStart = $absLocalSubrOffset;
                $localSubrIndex = $this->parseIndex($absLocalSubrOffset);
                $localSubrIndexData = substr($this->data, $localSubrStart, $localSubrIndex['nextOffset'] - $localSubrStart);
            }
        }

        // Charset (operator 15)
        $charsetOffset = $this->getTopDictInt($topDictOperators, 15);
        $charset = $this->parseCharset($charsetOffset, count($charStrings));

        // FDArray (operator 12 36) and FDSelect (operator 12 37)
        $fdArrayData = null;
        $fdSelectData = null;
        $fdArrayKey = '12.36';
        $fdSelectKey = '12.37';
        if (isset($topDictOperators[$fdArrayKey])) {
            $fdArrayOffset = (int) $topDictOperators[$fdArrayKey];
            $fdArrayIndex = $this->parseIndex($fdArrayOffset);
            $fdArrayData = substr($this->data, $fdArrayOffset, $fdArrayIndex['nextOffset'] - $fdArrayOffset);
        }
        if (isset($topDictOperators[$fdSelectKey])) {
            $fdSelectOffset = (int) $topDictOperators[$fdSelectKey];
            // FDSelect length is hard to determine without format parsing;
            // store from offset to end (subsetter will truncate if needed)
            $fdSelectFormat = ord($this->data[$fdSelectOffset]);
            if ($fdSelectFormat === 0) {
                $fdSelectLen = 1 + count($charStrings);
            } elseif ($fdSelectFormat === 3) {
                $nRanges = $this->readUint16($fdSelectOffset + 1);
                $fdSelectLen = 1 + 2 + $nRanges * 3 + 2; // format + nRanges + ranges + sentinel
            } else {
                $fdSelectLen = $this->length - $fdSelectOffset;
            }
            $fdSelectData = substr($this->data, $fdSelectOffset, $fdSelectLen);
        }

        return new CffData(
            major: $major,
            minor: $minor,
            hdrSize: $hdrSize,
            nameIndexData: $nameIndexData,
            topDictOperators: $topDictOperators,
            stringIndexData: $stringIndexData,
            globalSubrIndexData: $globalSubrIndexData,
            charStrings: $charStrings,
            privateDictData: $privateDictData,
            localSubrIndexData: $localSubrIndexData,
            charset: $charset,
            fdArrayData: $fdArrayData,
            fdSelectData: $fdSelectData,
        );
    }

    /**
     * Parse a CFF INDEX structure.
     *
     * @return array{entries: string[], nextOffset: int}
     */
    private function parseIndex(int $offset): array
    {
        $count = $this->readUint16($offset);
        $offset += 2;

        if ($count === 0) {
            return ['entries' => [], 'nextOffset' => $offset];
        }

        $offSize = ord($this->data[$offset]);
        $offset += 1;

        // Read count+1 offsets
        $offsets = [];
        for ($i = 0; $i <= $count; $i++) {
            $offsets[] = $this->readOffset($offset, $offSize);
            $offset += $offSize;
        }

        $dataBase = $offset - 1; // offsets are 1-based

        $entries = [];
        for ($i = 0; $i < $count; $i++) {
            $start = $dataBase + $offsets[$i];
            $end = $dataBase + $offsets[$i + 1];
            $entries[] = substr($this->data, $start, $end - $start);
        }

        $nextOffset = $dataBase + $offsets[$count];
        return ['entries' => $entries, 'nextOffset' => $nextOffset];
    }

    /**
     * Parse DICT binary data into operator => operand(s) map.
     *
     * @return array<int|string, int|float|array<int, int|float>>
     */
    private function parseDictData(string $data): array
    {
        $operators = [];
        $operandStack = [];
        $pos = 0;
        $len = strlen($data);

        while ($pos < $len) {
            $b0 = ord($data[$pos]);

            if ($b0 >= 32 && $b0 <= 246) {
                // Single-byte integer: value = b0 - 139
                $operandStack[] = $b0 - 139;
                $pos++;
            } elseif ($b0 >= 247 && $b0 <= 250) {
                // Two-byte positive: (b0-247)*256 + b1 + 108
                $b1 = ord($data[$pos + 1]);
                $operandStack[] = ($b0 - 247) * 256 + $b1 + 108;
                $pos += 2;
            } elseif ($b0 >= 251 && $b0 <= 254) {
                // Two-byte negative: -(b0-251)*256 - b1 - 108
                $b1 = ord($data[$pos + 1]);
                $operandStack[] = -($b0 - 251) * 256 - $b1 - 108;
                $pos += 2;
            } elseif ($b0 === 28) {
                // 3-byte integer
                $val = (ord($data[$pos + 1]) << 8) | ord($data[$pos + 2]);
                if ($val >= 0x8000) {
                    $val -= 0x10000;
                }
                $operandStack[] = $val;
                $pos += 3;
            } elseif ($b0 === 29) {
                // 5-byte integer
                $val = (ord($data[$pos + 1]) << 24) | (ord($data[$pos + 2]) << 16)
                    | (ord($data[$pos + 3]) << 8) | ord($data[$pos + 4]);
                if ($val >= 0x80000000) {
                    $val = (int) ($val - 0x100000000);
                }
                $operandStack[] = $val;
                $pos += 5;
            } elseif ($b0 === 30) {
                // Real number
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
                            case 0x0D: break; // reserved
                            case 0x0E: $realStr .= '-'; break;
                            case 0x0F: $done = true; break;
                        }
                        if ($done) break;
                    }
                }
                $operandStack[] = (float) $realStr;
            } elseif ($b0 === 12) {
                // Two-byte operator
                $pos++;
                $b1 = ord($data[$pos]);
                $pos++;
                $key = '12.' . $b1;
                if (count($operandStack) === 1) {
                    $operators[$key] = $operandStack[0];
                } else {
                    $operators[$key] = $operandStack;
                }
                $operandStack = [];
            } elseif ($b0 <= 21) {
                // One-byte operator
                $pos++;
                if (count($operandStack) === 1) {
                    $operators[$b0] = $operandStack[0];
                } else {
                    $operators[$b0] = $operandStack;
                }
                $operandStack = [];
            } else {
                // Unknown byte — skip
                $pos++;
            }
        }

        return $operators;
    }

    /**
     * Parse Charset structure.
     *
     * @return array<int, int> GID => SID/CID
     */
    private function parseCharset(?int $offset, int $nGlyphs): array
    {
        $charset = [0 => 0]; // GID 0 is always .notdef (SID 0)

        if ($offset === null || $offset <= 2) {
            // Predefined charsets: 0=ISOAdobe, 1=Expert, 2=ExpertSubset
            // For subsetting purposes, generate sequential SIDs
            for ($gid = 1; $gid < $nGlyphs; $gid++) {
                $charset[$gid] = $gid;
            }
            return $charset;
        }

        $format = ord($this->data[$offset]);
        $pos = $offset + 1;

        if ($format === 0) {
            // Format 0: array of SIDs
            for ($gid = 1; $gid < $nGlyphs; $gid++) {
                $charset[$gid] = $this->readUint16($pos);
                $pos += 2;
            }
        } elseif ($format === 1) {
            // Format 1: ranges with 1-byte count
            $gid = 1;
            while ($gid < $nGlyphs) {
                $first = $this->readUint16($pos);
                $nLeft = ord($this->data[$pos + 2]);
                $pos += 3;
                for ($i = 0; $i <= $nLeft && $gid < $nGlyphs; $i++) {
                    $charset[$gid] = $first + $i;
                    $gid++;
                }
            }
        } elseif ($format === 2) {
            // Format 2: ranges with 2-byte count
            $gid = 1;
            while ($gid < $nGlyphs) {
                $first = $this->readUint16($pos);
                $nLeft = $this->readUint16($pos + 2);
                $pos += 4;
                for ($i = 0; $i <= $nLeft && $gid < $nGlyphs; $i++) {
                    $charset[$gid] = $first + $i;
                    $gid++;
                }
            }
        }

        return $charset;
    }

    private function readOffset(int $offset, int $offSize): int
    {
        $val = 0;
        for ($i = 0; $i < $offSize; $i++) {
            $val = ($val << 8) | ord($this->data[$offset + $i]);
        }
        return $val;
    }

    private function readUint16(int $offset): int
    {
        if ($offset + 1 >= $this->length) {
            return 0;
        }
        return (ord($this->data[$offset]) << 8) | ord($this->data[$offset + 1]);
    }

    /**
     * @param array<int|string, int|float|array<int, int|float>> $operators
     */
    private function getTopDictInt(array $operators, int $key): ?int
    {
        if (!isset($operators[$key])) {
            return null;
        }
        $val = $operators[$key];
        return is_array($val) ? (int) $val[0] : (int) $val;
    }
}
