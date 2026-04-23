<?php

declare(strict_types=1);

namespace ApprLabs\Filters;

/**
 * CCITTFaxDecode filter — ITU-T T.4 (Group 3) and T.6 (Group 4) fax decoder.
 *
 * Decodes CCITT fax-compressed bitonal image data to raw uncompressed
 * pixel rows (1 bit per pixel, rows padded to byte boundaries).
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
        throw new \RuntimeException('CCITTFaxFilter encoding is not supported');
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
        $a0 = 0; // current position
        $isWhite = true;

        while ($a0 < $this->columns) {
            // Read 2D mode code
            $mode = $this->read2DMode($data, $bitPos);
            if ($mode === null) {
                if ($a0 === 0) {
                    return null; // EOFB
                }
                break;
            }

            switch ($mode) {
                case 'pass':
                    // Pass mode: b1 b2 reference
                    $b1 = $this->findB1($refLine, $a0, $isWhite);
                    $b2 = $this->findChanging($refLine, $b1, $this->columns);
                    $a0 = $b2;
                    break;

                case 'horizontal':
                    // Horizontal mode: two 1D run lengths
                    $run1 = $this->readRunLength($data, $bitPos, $isWhite) ?? 0;
                    $run2 = $this->readRunLength($data, $bitPos, !$isWhite) ?? 0;

                    $color1 = $isWhite ? 0 : 1;
                    for ($i = 0; $i < $run1 && $a0 < $this->columns; $i++) {
                        $row[$a0++] = $color1;
                    }
                    $color2 = $isWhite ? 1 : 0;
                    for ($i = 0; $i < $run2 && $a0 < $this->columns; $i++) {
                        $row[$a0++] = $color2;
                    }
                    break;

                default:
                    // Vertical mode: V(0), VR(1-3), VL(1-3)
                    $b1 = $this->findB1($refLine, $a0, $isWhite);
                    $a1 = $b1 + $mode; // mode is the delta (-3..+3)
                    $a1 = max($a0, min($a1, $this->columns));

                    $pixelValue = $isWhite ? 0 : 1;
                    while ($a0 < $a1) {
                        $row[$a0++] = $pixelValue;
                    }
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
                '011'     => 'horizontal',
                '0001'    => 'pass',
                '110'     => 1,          // VR(1)
                '010'     => -1,         // VL(1)
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
     * Find b1: the first changing element on the reference line to the right of a0
     * whose color is opposite to the current color.
     *
     * @param list<int> $refLine
     */
    private function findB1(array $refLine, int $a0, bool $isWhite): int
    {
        $searchColor = $isWhite ? 1 : 0; // looking for opposite color
        $pos = max(0, $a0);

        // Skip current-color pixels to find first opposite
        while ($pos < $this->columns && ($refLine[$pos] ?? 0) !== $searchColor) {
            $pos++;
        }

        // Now find the next changing element (back to current color or end)
        // Actually b1 is the first changing element to the right of a0 that has opposite color
        // Re-read: b1 is first ref pixel right of a0 with opposite color
        return $pos;
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
}
