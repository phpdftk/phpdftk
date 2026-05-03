<?php

declare(strict_types=1);

namespace Phpdftk\Filters;

/**
 * CCITTFaxDecode filter — ITU-T T.4 (Group 3) and T.6 (Group 4) fax codec.
 *
 * Encodes/decodes CCITT fax-compressed bitonal image data to/from raw
 * uncompressed pixel rows (1 bit per pixel, rows padded to byte boundaries).
 *
 * Parameters match PDF spec ISO 32000-2 §7.4.6, Table 11:
 *   K:       <0 = Group 4, 0 = Group 3 (1D), >0 = mixed 1D/2D
 *   Columns: image width in pixels (default 1728)
 *   Rows:    image height (0 = determined by EOB/data)
 *   EndOfLine: require EOL codes (default false)
 *   EncodedByteAlign: align rows to byte boundary (default false)
 *   EndOfBlock: use EOFB/RTC codes (default true)
 *   BlackIs1: if true, 1=black; if false, 0=black (default false)
 *
 * @see https://www.itu.int/rec/T-REC-T.4
 * @see https://www.itu.int/rec/T-REC-T.6
 */
final class CCITTFaxFilter implements FilterInterface
{
    // White run-length Huffman codes (code => run length)
    // Key: binary string of bits, Value: run length
    // Terminating codes (0-63) and make-up codes (64, 128, ..., 2560)
    private const WHITE_TERMINATING = [
        '00110101' => 0, '000111' => 1, '0111' => 2, '1000' => 3,
        '1011' => 4, '1100' => 5, '1110' => 6, '1111' => 7,
        '10011' => 8, '10100' => 9, '00111' => 10, '01000' => 11,
        '001000' => 12, '000011' => 13, '110100' => 14, '110101' => 15,
        '101010' => 16, '101011' => 17, '0100111' => 18, '0001100' => 19,
        '0001000' => 20, '0010111' => 21, '0000011' => 22, '0000100' => 23,
        '0101000' => 24, '0101011' => 25, '0010011' => 26, '0100100' => 27,
        '0011000' => 28, '00000010' => 29, '00000011' => 30, '00011010' => 31,
        '00011011' => 32, '00010010' => 33, '00010011' => 34, '00010100' => 35,
        '00010101' => 36, '00010110' => 37, '00010111' => 38, '00101000' => 39,
        '00101001' => 40, '00101010' => 41, '00101011' => 42, '00101100' => 43,
        '00101101' => 44, '00000100' => 45, '00000101' => 46, '00001010' => 47,
        '00001011' => 48, '01010010' => 49, '01010011' => 50, '01010100' => 51,
        '01010101' => 52, '00100100' => 53, '00100101' => 54, '01011000' => 55,
        '01011001' => 56, '01011010' => 57, '01011011' => 58, '01001010' => 59,
        '01001011' => 60, '00110010' => 61, '00110011' => 62, '00110100' => 63,
    ];

    private const WHITE_MAKEUP = [
        '11011' => 64, '10010' => 128, '010111' => 192, '0110111' => 256,
        '00110110' => 320, '00110111' => 384, '01100100' => 448, '01100101' => 512,
        '01101000' => 576, '01100111' => 640, '011001100' => 704, '011001101' => 768,
        '011010010' => 832, '011010011' => 896, '011010100' => 960, '011010101' => 1024,
        '011010110' => 1088, '011010111' => 1152, '011011000' => 1216, '011011001' => 1280,
        '011011010' => 1344, '011011011' => 1408, '010011000' => 1472, '010011001' => 1536,
        '010011010' => 1600, '011000' => 1664, '010011011' => 1728,
    ];

    private const BLACK_TERMINATING = [
        '0000110111' => 0, '010' => 1, '11' => 2, '10' => 3,
        '011' => 4, '0011' => 5, '0010' => 6, '00011' => 7,
        '000101' => 8, '000100' => 9, '0000100' => 10, '0000101' => 11,
        '0000111' => 12, '00000100' => 13, '00000111' => 14, '000011000' => 15,
        '0000010111' => 16, '0000011000' => 17, '0000001000' => 18, '00001100111' => 19,
        '00001101000' => 20, '00001101100' => 21, '00000110111' => 22, '00000101000' => 23,
        '00000010111' => 24, '00000011000' => 25, '000011001010' => 26, '000011001011' => 27,
        '000011001100' => 28, '000011001101' => 29, '000001101000' => 30, '000001101001' => 31,
        '000001101010' => 32, '000001101011' => 33, '000011010010' => 34, '000011010011' => 35,
        '000011010100' => 36, '000011010101' => 37, '000011010110' => 38, '000011010111' => 39,
        '000001101100' => 40, '000001101101' => 41, '000011011010' => 42, '000011011011' => 43,
        '000001010010' => 44, '000001010011' => 45, '000001010100' => 46, '000001010101' => 47,
        '000001011010' => 48, '000001011011' => 49, '000001100100' => 50, '000001100101' => 51,
        '000001010110' => 52, '000001010111' => 53, '000001100110' => 54, '000001100111' => 55,
        '000001101110' => 56, '000001101111' => 57, '000001001000' => 58, '000001001001' => 59,
        '000001001010' => 60, '000001001011' => 61, '000001001100' => 62, '000001001101' => 63,
    ];

    private const BLACK_MAKEUP = [
        '0000001111' => 64, '000011001000' => 128, '000011001001' => 192, '000001011000' => 256,
        '000000110111' => 320, '000000101000' => 384, '000000010111' => 448, '000000011000' => 512,
        '0000001100100' => 576, '0000001100101' => 640, '0000001101100' => 704, '0000001101101' => 768,
        '0000001001010' => 832, '0000001001011' => 896, '0000001001100' => 960, '0000001001101' => 1024,
        '0000001110010' => 1088, '0000001110011' => 1152, '0000001110100' => 1216, '0000001110101' => 1280,
        '0000001110110' => 1344, '0000001110111' => 1408, '0000001010010' => 1472, '0000001010011' => 1536,
        '0000001010100' => 1600, '0000001010101' => 1664, '0000001011010' => 1728,
    ];

    // Extended make-up codes (shared by white and black, 1792-2560)
    private const EXTENDED_MAKEUP = [
        '00000001000' => 1792, '00000001100' => 1856, '00000001101' => 1920,
        '000000010010' => 1984, '000000010011' => 2048, '000000010100' => 2112,
        '000000010101' => 2176, '000000010110' => 2240, '000000010111' => 2304,
        '000000011100' => 2368, '000000011101' => 2432, '000000011110' => 2496,
        '000000011111' => 2560,
    ];

    // Reverse lookup tables for encoding (run length => binary string)
    private const WHITE_TERMINATING_ENC = [
        0 => '00110101', 1 => '000111', 2 => '0111', 3 => '1000',
        4 => '1011', 5 => '1100', 6 => '1110', 7 => '1111',
        8 => '10011', 9 => '10100', 10 => '00111', 11 => '01000',
        12 => '001000', 13 => '000011', 14 => '110100', 15 => '110101',
        16 => '101010', 17 => '101011', 18 => '0100111', 19 => '0001100',
        20 => '0001000', 21 => '0010111', 22 => '0000011', 23 => '0000100',
        24 => '0101000', 25 => '0101011', 26 => '0010011', 27 => '0100100',
        28 => '0011000', 29 => '00000010', 30 => '00000011', 31 => '00011010',
        32 => '00011011', 33 => '00010010', 34 => '00010011', 35 => '00010100',
        36 => '00010101', 37 => '00010110', 38 => '00010111', 39 => '00101000',
        40 => '00101001', 41 => '00101010', 42 => '00101011', 43 => '00101100',
        44 => '00101101', 45 => '00000100', 46 => '00000101', 47 => '00001010',
        48 => '00001011', 49 => '01010010', 50 => '01010011', 51 => '01010100',
        52 => '01010101', 53 => '00100100', 54 => '00100101', 55 => '01011000',
        56 => '01011001', 57 => '01011010', 58 => '01011011', 59 => '01001010',
        60 => '01001011', 61 => '00110010', 62 => '00110011', 63 => '00110100',
    ];

    private const WHITE_MAKEUP_ENC = [
        64 => '11011', 128 => '10010', 192 => '010111', 256 => '0110111',
        320 => '00110110', 384 => '00110111', 448 => '01100100', 512 => '01100101',
        576 => '01101000', 640 => '01100111', 704 => '011001100', 768 => '011001101',
        832 => '011010010', 896 => '011010011', 960 => '011010100', 1024 => '011010101',
        1088 => '011010110', 1152 => '011010111', 1216 => '011011000', 1280 => '011011001',
        1344 => '011011010', 1408 => '011011011', 1472 => '010011000', 1536 => '010011001',
        1600 => '010011010', 1664 => '011000', 1728 => '010011011',
    ];

    private const BLACK_TERMINATING_ENC = [
        0 => '0000110111', 1 => '010', 2 => '11', 3 => '10',
        4 => '011', 5 => '0011', 6 => '0010', 7 => '00011',
        8 => '000101', 9 => '000100', 10 => '0000100', 11 => '0000101',
        12 => '0000111', 13 => '00000100', 14 => '00000111', 15 => '000011000',
        16 => '0000010111', 17 => '0000011000', 18 => '0000001000', 19 => '00001100111',
        20 => '00001101000', 21 => '00001101100', 22 => '00000110111', 23 => '00000101000',
        24 => '00000010111', 25 => '00000011000', 26 => '000011001010', 27 => '000011001011',
        28 => '000011001100', 29 => '000011001101', 30 => '000001101000', 31 => '000001101001',
        32 => '000001101010', 33 => '000001101011', 34 => '000011010010', 35 => '000011010011',
        36 => '000011010100', 37 => '000011010101', 38 => '000011010110', 39 => '000011010111',
        40 => '000001101100', 41 => '000001101101', 42 => '000011011010', 43 => '000011011011',
        44 => '000001010010', 45 => '000001010011', 46 => '000001010100', 47 => '000001010101',
        48 => '000001011010', 49 => '000001011011', 50 => '000001100100', 51 => '000001100101',
        52 => '000001010110', 53 => '000001010111', 54 => '000001100110', 55 => '000001100111',
        56 => '000001101110', 57 => '000001101111', 58 => '000001001000', 59 => '000001001001',
        60 => '000001001010', 61 => '000001001011', 62 => '000001001100', 63 => '000001001101',
    ];

    private const BLACK_MAKEUP_ENC = [
        64 => '0000001111', 128 => '000011001000', 192 => '000011001001', 256 => '000001011000',
        320 => '000000110111', 384 => '000000101000', 448 => '000000010111', 512 => '000000011000',
        576 => '0000001100100', 640 => '0000001100101', 704 => '0000001101100', 768 => '0000001101101',
        832 => '0000001001010', 896 => '0000001001011', 960 => '0000001001100', 1024 => '0000001001101',
        1088 => '0000001110010', 1152 => '0000001110011', 1216 => '0000001110100', 1280 => '0000001110101',
        1344 => '0000001110110', 1408 => '0000001110111', 1472 => '0000001010010', 1536 => '0000001010011',
        1600 => '0000001010100', 1664 => '0000001010101', 1728 => '0000001011010',
    ];

    private const EXTENDED_MAKEUP_ENC = [
        1792 => '00000001000', 1856 => '00000001100', 1920 => '00000001101',
        1984 => '000000010010', 2048 => '000000010011', 2112 => '000000010100',
        2176 => '000000010101', 2240 => '000000010110', 2304 => '000000010111',
        2368 => '000000011100', 2432 => '000000011101', 2496 => '000000011110',
        2560 => '000000011111',
    ];

    public function __construct(
        private int $k = 0,
        private int $columns = 1728,
        private int $rows = 0,
        private bool $endOfLine = false,
        private bool $encodedByteAlign = false,
        private bool $endOfBlock = true,
        private bool $blackIs1 = false,
    ) {}

    public function encode(string $data): string
    {
        if ($data === '') {
            return '';
        }

        $bytesPerRow = (int) ceil($this->columns / 8);
        $rowCount = $this->rows > 0 ? $this->rows : intdiv(strlen($data), $bytesPerRow);
        $bits = '';

        if ($this->k < 0) {
            // Group 4 (2D) — T.6
            $refLine = array_fill(0, $this->columns, 0); // all-white reference

            for ($r = 0; $r < $rowCount; $r++) {
                if ($this->encodedByteAlign && $r > 0) {
                    $rem = strlen($bits) % 8;
                    if ($rem > 0) {
                        $bits .= str_repeat('0', 8 - $rem);
                    }
                }

                $row = $this->unpackRow($data, $r * $bytesPerRow);
                $bits .= $this->encodeGroup4Row($row, $refLine);
                $refLine = $row;
            }

            if ($this->endOfBlock) {
                // EOFB = two consecutive EOL codes
                $bits .= '000000000001000000000001';
            }
        } else {
            // Group 3 (1D) — T.4
            for ($r = 0; $r < $rowCount; $r++) {
                if ($this->encodedByteAlign && $r > 0) {
                    $rem = strlen($bits) % 8;
                    if ($rem > 0) {
                        $bits .= str_repeat('0', 8 - $rem);
                    }
                }

                if ($this->endOfLine) {
                    $bits .= '000000000001'; // EOL
                }

                $row = $this->unpackRow($data, $r * $bytesPerRow);
                $bits .= $this->encodeGroup3Row($row);
            }

            if ($this->endOfBlock) {
                // RTC = 6 consecutive EOL codes
                $bits .= str_repeat('000000000001', 6);
            }
        }

        return $this->bitsToBytes($bits);
    }

    public function decode(string $data): string
    {
        if ($data === '') {
            return '';
        }

        $bitPos = 0;
        $dataLen = strlen($data);
        $output = '';
        $rowCount = 0;
        $maxRows = $this->rows > 0 ? $this->rows : PHP_INT_MAX;

        // When endOfBlock is false and rows > 0, stop at row count rather
        // than scanning for EOFB marker
        if (!$this->endOfBlock && $this->rows > 0) {
            $maxRows = $this->rows;
        }

        if ($this->k < 0) {
            // Group 4 (2D) — T.6
            $refLine = array_fill(0, $this->columns, 0); // all white reference

            while ($rowCount < $maxRows && $bitPos < $dataLen * 8) {
                if ($this->encodedByteAlign) {
                    $bitPos = (int) (ceil($bitPos / 8) * 8);
                }

                $row = $this->decodeGroup4Row($data, $bitPos, $refLine);
                if ($row === null) {
                    break; // EOFB or end of data
                }
                $output .= $this->packRow($row);
                $refLine = $row;
                $rowCount++;
            }
        } else {
            // Group 3 (1D) — T.4
            while ($rowCount < $maxRows && $bitPos < $dataLen * 8) {
                if ($this->encodedByteAlign) {
                    $bitPos = (int) (ceil($bitPos / 8) * 8);
                }

                // Skip EOL if present
                if ($this->endOfLine) {
                    $this->skipEOL($data, $bitPos);
                }

                $row = $this->decodeGroup3Row($data, $bitPos);
                if ($row === null) {
                    break;
                }
                $output .= $this->packRow($row);
                $rowCount++;
            }
        }

        return $output;
    }

    /**
     * Decode a single Group 3 (1D) row.
     *
     * @return list<int>|null pixel values (0 or 1), or null at end
     */
    private function decodeGroup3Row(string $data, int &$bitPos): ?array
    {
        $row = [];
        $isWhite = true; // rows always start with white
        $totalPixels = 0;

        while ($totalPixels < $this->columns) {
            $runLen = $this->readRunLength($data, $bitPos, $isWhite);
            if ($runLen === null) {
                if ($totalPixels === 0) {
                    return null; // end of data
                }
                // Pad remaining pixels
                $remaining = $this->columns - $totalPixels;
                for ($i = 0; $i < $remaining; $i++) {
                    $row[] = 0;
                }
                break;
            }

            $pixelValue = $isWhite ? 0 : 1;
            for ($i = 0; $i < $runLen && $totalPixels < $this->columns; $i++) {
                $row[] = $pixelValue;
                $totalPixels++;
            }
            $isWhite = !$isWhite;
        }

        return $row;
    }

    /**
     * Decode a single Group 4 (2D) row using the 2D coding modes.
     *
     * @param list<int> $refLine reference line pixels
     * @return list<int>|null pixel values, or null at EOFB
     */
    private function decodeGroup4Row(string $data, int &$bitPos, array $refLine): ?array
    {
        $row = array_fill(0, $this->columns, 0);
        $a0 = -1; // imaginary white element before position 0
        $isWhite = true;

        while ($a0 < $this->columns) {
            // Read 2D mode code
            $mode = $this->read2DMode($data, $bitPos);
            if ($mode === null) {
                if ($a0 < 0) {
                    return null; // EOFB
                }
                break;
            }

            $a0Pos = max(0, $a0); // effective position for filling

            switch ($mode) {
                case 'pass':
                    // Pass mode: b1 b2 reference — current color continues
                    $b1 = $this->findB1($refLine, $a0, $isWhite);
                    $b2 = $this->findChanging($refLine, $b1, $this->columns);
                    // Fill the passed-over region with current color
                    $pixelValue = $isWhite ? 0 : 1;
                    while ($a0Pos < $b2 && $a0Pos < $this->columns) {
                        $row[$a0Pos++] = $pixelValue;
                    }
                    $a0 = $b2;
                    break;

                case 'horizontal':
                    // Horizontal mode: two 1D run lengths
                    $run1 = $this->readRunLength($data, $bitPos, $isWhite) ?? 0;
                    $run2 = $this->readRunLength($data, $bitPos, !$isWhite) ?? 0;

                    $color1 = $isWhite ? 0 : 1;
                    for ($i = 0; $i < $run1 && $a0Pos < $this->columns; $i++) {
                        $row[$a0Pos++] = $color1;
                    }
                    $color2 = $isWhite ? 1 : 0;
                    for ($i = 0; $i < $run2 && $a0Pos < $this->columns; $i++) {
                        $row[$a0Pos++] = $color2;
                    }
                    $a0 = $a0Pos;
                    break;

                default:
                    // Vertical mode: V(0), VR(1-3), VL(1-3)
                    $b1 = $this->findB1($refLine, $a0, $isWhite);
                    $a1 = $b1 + $mode; // mode is the delta (-3..+3)
                    $a1 = max($a0Pos, min($a1, $this->columns));

                    $pixelValue = $isWhite ? 0 : 1;
                    while ($a0Pos < $a1) {
                        $row[$a0Pos++] = $pixelValue;
                    }
                    $a0 = $a1;
                    $isWhite = !$isWhite;
                    break;
            }
        }

        return $row;
    }

    /**
     * Read a run length (terminating + make-up codes).
     */
    private function readRunLength(string $data, int &$bitPos, bool $isWhite): ?int
    {
        $total = 0;
        $termTable = $isWhite ? self::WHITE_TERMINATING : self::BLACK_TERMINATING;
        $makeupTable = $isWhite ? self::WHITE_MAKEUP : self::BLACK_MAKEUP;

        // Read make-up codes first (if any)
        while (true) {
            $code = $this->matchHuffman($data, $bitPos, $makeupTable);
            if ($code === null) {
                $extCode = $this->matchHuffman($data, $bitPos, self::EXTENDED_MAKEUP);
                if ($extCode !== null) {
                    $total += $extCode;
                    continue;
                }
                break;
            }
            $total += $code;
        }

        // Read terminating code
        $term = $this->matchHuffman($data, $bitPos, $termTable);
        if ($term === null) {
            return $total > 0 ? $total : null;
        }
        $total += $term;

        return $total;
    }

    /**
     * Match a Huffman code from the given table.
     *
     * @param array<string, int> $table
     */
    private function matchHuffman(string $data, int &$bitPos, array $table): ?int
    {
        $savedPos = $bitPos;
        $bits = '';
        $maxLen = 13; // longest code in any table

        for ($i = 0; $i < $maxLen; $i++) {
            $byteIdx = intdiv($bitPos, 8);
            if ($byteIdx >= strlen($data)) {
                $bitPos = $savedPos;
                return null;
            }
            $bitIdx = 7 - ($bitPos % 8);
            $bit = (ord($data[$byteIdx]) >> $bitIdx) & 1;
            $bits .= $bit;
            $bitPos++;

            if (isset($table[$bits])) {
                return $table[$bits];
            }
        }

        $bitPos = $savedPos;
        return null;
    }

    /**
     * Read a 2D mode code for Group 4 encoding.
     *
     * @return string|int|null 'pass', 'horizontal', or vertical delta (-3..+3), or null at EOFB
     */
    private function read2DMode(string $data, int &$bitPos): string|int|null
    {
        $savedPos = $bitPos;
        $bits = '';

        for ($i = 0; $i < 7; $i++) {
            $byteIdx = intdiv($bitPos, 8);
            if ($byteIdx >= strlen($data)) {
                $bitPos = $savedPos;
                return null;
            }
            $bitIdx = 7 - ($bitPos % 8);
            $bit = (ord($data[$byteIdx]) >> $bitIdx) & 1;
            $bits .= $bit;
            $bitPos++;

            $result = match ($bits) {
                '1'       => 0,          // V(0) — vertical, no offset
                '011'     => 1,          // VR(1)
                '010'     => -1,         // VL(1)
                '001'     => 'horizontal',
                '0001'    => 'pass',
                '000011'  => 2,          // VR(2)
                '000010'  => -2,         // VL(2)
                '0000011' => 3,          // VR(3)
                '0000010' => -3,         // VL(3)
                default   => null,
            };

            if ($result !== null) {
                return $result;
            }

            // Check for EOFB (000000000001 000000000001)
            if ($bits === '0000000') {
                // Read more bits to check for EOFB
                $moreBits = '';
                for ($j = 0; $j < 17; $j++) {
                    $byteIdx2 = intdiv($bitPos, 8);
                    if ($byteIdx2 >= strlen($data)) {
                        break;
                    }
                    $bitIdx2 = 7 - ($bitPos % 8);
                    $moreBits .= (string) ((ord($data[$byteIdx2]) >> $bitIdx2) & 1);
                    $bitPos++;
                }
                if (str_starts_with($bits . $moreBits, '000000000001000000000001')) {
                    return null; // EOFB
                }
                // Not EOFB — backtrack
                $bitPos = $savedPos;
                return null;
            }
        }

        $bitPos = $savedPos;
        return null;
    }

    /**
     * Find b1: the first changing element on the reference line to the right
     * of a0 whose color is opposite to the current coding color.
     *
     * Per ITU-T T.6, b1 is a changing element (where pixel color differs
     * from the previous pixel) strictly after a0, with opposite color.
     * Position 0 is always a changing element (transition from imaginary white).
     *
     * @param list<int> $refLine
     */
    private function findB1(array $refLine, int $a0, bool $isWhite): int
    {
        $oppositeColor = $isWhite ? 1 : 0;
        $startPos = max(0, $a0 + 1);

        for ($pos = $startPos; $pos < $this->columns; $pos++) {
            $current = $refLine[$pos] ?? 0;
            // Position 0 is a changing element if it differs from imaginary white (0)
            $prev = ($pos > 0) ? ($refLine[$pos - 1] ?? 0) : 0;

            if ($current !== $prev && $current === $oppositeColor) {
                return $pos;
            }
        }

        return $this->columns;
    }

    /**
     * Find the next changing element after position $start in the reference line.
     *
     * @param list<int> $refLine
     */
    private function findChanging(array $refLine, int $start, int $columns): int
    {
        if ($start >= $columns) {
            return $columns;
        }
        $currentColor = $refLine[$start] ?? 0;
        for ($i = $start + 1; $i < $columns; $i++) {
            if (($refLine[$i] ?? 0) !== $currentColor) {
                return $i;
            }
        }
        return $columns;
    }

    /**
     * Skip EOL code (000000000001) if present.
     */
    private function skipEOL(string $data, int &$bitPos): void
    {
        $savedPos = $bitPos;
        $bits = '';
        for ($i = 0; $i < 12; $i++) {
            $byteIdx = intdiv($bitPos, 8);
            if ($byteIdx >= strlen($data)) {
                $bitPos = $savedPos;
                return;
            }
            $bitIdx = 7 - ($bitPos % 8);
            $bits .= (string) ((ord($data[$byteIdx]) >> $bitIdx) & 1);
            $bitPos++;
        }
        if ($bits !== '000000000001') {
            $bitPos = $savedPos; // not EOL, put bits back
        }
    }

    /**
     * Pack a row of pixel values (0/1) into bytes, with rows padded to byte boundaries.
     *
     * @param list<int> $row
     */
    private function packRow(array $row): string
    {
        $byte = 0;
        $bitCount = 0;
        $result = '';

        foreach ($row as $pixel) {
            $value = $this->blackIs1 ? $pixel : (1 - $pixel);
            $byte = ($byte << 1) | $value;
            $bitCount++;
            if ($bitCount === 8) {
                $result .= chr($byte);
                $byte = 0;
                $bitCount = 0;
            }
        }

        // Pad last byte
        if ($bitCount > 0) {
            $byte <<= (8 - $bitCount);
            $result .= chr($byte);
        }

        return $result;
    }

    // -----------------------------------------------------------------------
    // Encode helpers
    // -----------------------------------------------------------------------

    /**
     * Unpack raw bytes into pixel values (0/1) — inverse of packRow.
     *
     * @return list<int> pixel values (0 = white, 1 = black)
     */
    private function unpackRow(string $data, int $offset): array
    {
        $row = [];
        $bytesPerRow = (int) ceil($this->columns / 8);

        for ($i = 0; $i < $this->columns; $i++) {
            $byteIdx = $offset + intdiv($i, 8);
            $bitIdx = 7 - ($i % 8);
            $rawBit = ($byteIdx < strlen($data))
                ? (ord($data[$byteIdx]) >> $bitIdx) & 1
                : 0;

            // Invert the packRow transform: packRow does blackIs1 ? pixel : (1-pixel)
            $row[] = $this->blackIs1 ? $rawBit : (1 - $rawBit);
        }

        return $row;
    }

    /**
     * Encode a run length as Huffman bit codes.
     */
    private function encodeRunLength(int $runLength, bool $isWhite): string
    {
        $bits = '';
        $makeupTable = $isWhite ? self::WHITE_MAKEUP_ENC : self::BLACK_MAKEUP_ENC;
        $termTable = $isWhite ? self::WHITE_TERMINATING_ENC : self::BLACK_TERMINATING_ENC;

        // Extended make-up codes for runs >= 1792
        while ($runLength >= 1792) {
            // Find largest extended makeup that fits
            $best = 0;
            foreach (self::EXTENDED_MAKEUP_ENC as $len => $code) {
                if ($len <= $runLength && $len > $best) {
                    $best = $len;
                }
            }
            if ($best > 0) {
                $bits .= self::EXTENDED_MAKEUP_ENC[$best];
                $runLength -= $best;
            } else {
                break;
            }
        }

        // Standard make-up codes for runs >= 64
        if ($runLength >= 64) {
            // Find largest makeup code that fits
            $best = 0;
            foreach ($makeupTable as $len => $code) {
                if ($len <= $runLength && $len > $best) {
                    $best = $len;
                }
            }
            if ($best > 0) {
                $bits .= $makeupTable[$best];
                $runLength -= $best;
            }
        }

        // Terminating code (0-63)
        $bits .= $termTable[$runLength];

        return $bits;
    }

    /**
     * Encode a single row using Group 3 (1D) coding.
     *
     * @param list<int> $row pixel values (0 = white, 1 = black)
     */
    private function encodeGroup3Row(array $row): string
    {
        $bits = '';
        $isWhite = true;
        $pos = 0;
        $len = count($row);

        while ($pos < $len) {
            // Count run of same-color pixels
            $runStart = $pos;
            $currentColor = $isWhite ? 0 : 1;
            while ($pos < $len && $row[$pos] === $currentColor) {
                $pos++;
            }
            $runLen = $pos - $runStart;
            $bits .= $this->encodeRunLength($runLen, $isWhite);
            $isWhite = !$isWhite;
        }

        // If the row ended on a non-white run, we're done.
        // If it ended on white and we still need a terminating black run of 0:
        // Actually, rows just alternate starting from white. If all pixels are white,
        // we emit white run = columns. No trailing black run needed.

        return $bits;
    }

    /**
     * Encode a single row using Group 4 (2D) coding against a reference line.
     *
     * @param list<int> $row current row pixel values
     * @param list<int> $refLine reference line pixel values
     */
    private function encodeGroup4Row(array $row, array $refLine): string
    {
        $bits = '';
        $a0 = -1; // imaginary white element before position 0
        $isWhite = true;
        $cols = $this->columns;

        while ($a0 < $cols) {
            $a0Pos = max(0, $a0); // effective position for pixel access

            // a1: first position >= a0Pos where pixel differs from current coding color
            $currentColor = $isWhite ? 0 : 1;
            $a1 = $a0Pos;
            while ($a1 < $cols && $row[$a1] === $currentColor) {
                $a1++;
            }

            // a2: next changing element after a1
            $a2 = $a1;
            if ($a2 < $cols) {
                $a2Color = $row[$a2];
                $a2++;
                while ($a2 < $cols && $row[$a2] === $a2Color) {
                    $a2++;
                }
            }

            // b1: first changing element on reference line to the right of a0
            // with opposite color to current coding color
            $b1 = $this->findB1($refLine, $a0, $isWhite);

            // b2: next changing element after b1
            $b2 = $this->findChanging($refLine, $b1, $cols);

            if ($b2 < $a1) {
                // Pass mode
                $bits .= '0001';
                $a0 = $b2;
            } elseif (abs($a1 - $b1) <= 3) {
                // Vertical mode
                $delta = $a1 - $b1;
                $bits .= match ($delta) {
                    0 => '1',
                    1 => '011',
                    -1 => '010',
                    2 => '000011',
                    -2 => '000010',
                    3 => '0000011',
                    -3 => '0000010',
                    default => throw new \RuntimeException("Invalid vertical delta: $delta"),
                };
                $a0 = $a1;
                $isWhite = !$isWhite;
            } else {
                // Horizontal mode
                $bits .= '001';

                $run1 = $a1 - $a0Pos;
                $run2 = $a2 - $a1;
                $bits .= $this->encodeRunLength($run1, $isWhite);
                $bits .= $this->encodeRunLength($run2, !$isWhite);

                $a0 = $a2;
            }
        }

        return $bits;
    }

    /**
     * Convert a binary bit string to bytes (MSB-first, zero-padded).
     */
    private function bitsToBytes(string $bits): string
    {
        $result = '';
        $len = strlen($bits);
        for ($i = 0; $i < $len; $i += 8) {
            $chunk = substr($bits, $i, 8);
            if (strlen($chunk) < 8) {
                $chunk = str_pad($chunk, 8, '0');
            }
            $result .= chr((int) bindec($chunk));
        }
        return $result;
    }
}
