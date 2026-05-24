<?php

declare(strict_types=1);

namespace Phpdftk\Barcode\Encoder;

use Phpdftk\Barcode\BarcodeBitmap;
use Phpdftk\Barcode\BarcodeOptions;

/**
 * Data Matrix encoder — ISO/IEC 16022, ECC 200.
 *
 * Supports all 24 square symbol sizes (10×10 through 144×144), full
 * Reed-Solomon error correction over GF(256) with primitive 0x12D, and
 * ASCII-mode encoding (with the 2-digit compression that fits two
 * decimal digits in one codeword).
 *
 * Multi-region symbols (32×32 and larger) use the standard arrangement
 * of one finder L + timing run per data region. ISO 16022 Annex F
 * "utah" placement is used for the data region, including the four
 * corner-pattern special cases.
 */
final class DataMatrix
{
    /** Pad codeword (PAD). */
    private const PAD = 129;

    /**
     * Encode `$data` to a Data Matrix symbol. Throws if the input
     * doesn't fit in any 24-size square symbol.
     */
    public static function encode(string $data, BarcodeOptions $options): BarcodeBitmap
    {
        if ($data === '') {
            throw new \InvalidArgumentException('Data Matrix input must be non-empty.');
        }

        $codewords = self::encodeAscii($data);
        $spec = DataMatrixSpec::pickSize(count($codewords));
        $codewords = self::pad($codewords, $spec['dataCodewords']);
        $allCodewords = self::interleaveWithEcc($codewords, $spec);
        $matrix = self::buildMatrix($allCodewords, $spec);

        return new BarcodeBitmap(
            modules: $matrix,
            moduleWidth: $options->moduleWidth,
            height: $options->height,
            quietZoneModules: $options->quietZoneModules,
        );
    }

    /**
     * Encode the input string into ASCII-mode codewords. Each
     * printable ASCII char (0-127) is one codeword (char + 1). Two
     * consecutive digits compress to a single codeword. Bytes above
     * 127 are encoded with the upper-shift codeword (235).
     *
     * @return list<int>
     */
    private static function encodeAscii(string $data): array
    {
        $codewords = [];
        $i = 0;
        $len = strlen($data);
        while ($i < $len) {
            $b = ord($data[$i]);
            // Digit-pair compression: 2 digits → 1 codeword (130 + 10*d1 + d2).
            if ($b >= 0x30 && $b <= 0x39 && $i + 1 < $len) {
                $b2 = ord($data[$i + 1]);
                if ($b2 >= 0x30 && $b2 <= 0x39) {
                    $codewords[] = 10 * ($b - 0x30) + ($b2 - 0x30) + 130;
                    $i += 2;
                    continue;
                }
            }
            if ($b < 128) {
                $codewords[] = $b + 1;
                $i++;
            } else {
                // Upper-shift (codeword 235) for bytes 128-255.
                $codewords[] = 235;
                $codewords[] = ($b - 128) + 1;
                $i++;
            }
        }
        return $codewords;
    }

    /**
     * Pad codeword stream to the symbol's data capacity. First pad
     * gets the standard 129; subsequent pads use the 253-state random
     * padding algorithm so identical inputs don't produce identical
     * pad streams across different positions.
     *
     * @param list<int> $codewords
     * @return list<int>
     */
    private static function pad(array $codewords, int $capacity): array
    {
        if (count($codewords) >= $capacity) {
            return array_slice($codewords, 0, $capacity);
        }
        $codewords[] = self::PAD;
        while (count($codewords) < $capacity) {
            $pos = count($codewords) + 1;
            $r = ((149 * $pos) % 253) + 1;
            $pad = (self::PAD + $r) % 254;
            if ($pad === 0) {
                $pad = 254;
            }
            $codewords[] = $pad;
        }
        return $codewords;
    }

    /**
     * Split data codewords into RS blocks, compute ECC per block, and
     * interleave back to a single stream.
     *
     * @param list<int> $dataCodewords
     * @param array{rsBlocks:int, dataCodewords:int, eccCodewords:int, regionCount:int, regionDataSize:int, size:int} $spec
     * @return list<int>
     */
    private static function interleaveWithEcc(array $dataCodewords, array $spec): array
    {
        $blocks = $spec['rsBlocks'];
        $dataPerBlock = (int) floor($spec['dataCodewords'] / $blocks);
        $dataRemainder = $spec['dataCodewords'] - $dataPerBlock * $blocks;
        $eccPerBlock = (int) floor($spec['eccCodewords'] / $blocks);

        // The 144×144 size has a special split: one block carries an
        // extra data codeword. ISO 16022 Table 7 footnote: the 156-th
        // block of size 144 is "62-codeword" instead of "61-codeword".
        // Handled implicitly by dataRemainder.
        $rs = new DataMatrixReedSolomon($eccPerBlock);
        $dataBlocks = [];
        $eccBlocks = [];
        $offset = 0;
        for ($b = 0; $b < $blocks; $b++) {
            $size = $dataPerBlock + ($b < $dataRemainder ? 1 : 0);
            $block = array_slice($dataCodewords, $offset, $size);
            $dataBlocks[] = $block;
            $eccBlocks[] = $rs->encode($block);
            $offset += $size;
        }

        // Interleave data: round-robin across blocks.
        $interleavedData = [];
        $maxData = max(array_map('count', $dataBlocks));
        for ($i = 0; $i < $maxData; $i++) {
            foreach ($dataBlocks as $block) {
                if (isset($block[$i])) {
                    $interleavedData[] = $block[$i];
                }
            }
        }
        // Interleave ECC: round-robin too.
        $interleavedEcc = [];
        for ($i = 0; $i < $eccPerBlock; $i++) {
            foreach ($eccBlocks as $block) {
                $interleavedEcc[] = $block[$i];
            }
        }

        return array_merge($interleavedData, $interleavedEcc);
    }

    /**
     * Build the final module matrix: place data modules via utah
     * algorithm, fill the unused corner with the standard checkerboard
     * pattern, then wrap each data region with its finder + timing.
     *
     * @param list<int> $codewords
     * @param array{rsBlocks:int, dataCodewords:int, eccCodewords:int, regionCount:int, regionDataSize:int, size:int} $spec
     * @return list<list<bool>>
     */
    private static function buildMatrix(array $codewords, array $spec): array
    {
        $regionCount = $spec['regionCount'];
        $regionDataSize = $spec['regionDataSize'];
        $numRows = $regionCount * $regionDataSize;
        $numCols = $regionCount * $regionDataSize;
        $symbolSize = $regionCount * ($regionDataSize + 2);

        // Logical data grid: -1 = unfilled, 0 = light, 1 = dark.
        $logical = array_fill(0, $numRows, array_fill(0, $numCols, -1));
        self::placeData($logical, $codewords, $numRows, $numCols);

        // Fill any remaining cells per ISO 16022 §F.5 (corner pattern
        // for symbols where the algorithm leaves the lower-right two
        // diagonal cells unset).
        if ($logical[$numRows - 1][$numCols - 1] === -1) {
            $logical[$numRows - 1][$numCols - 1] = 0;
            $logical[$numRows - 2][$numCols - 2] = 0;
            $logical[$numRows - 1][$numCols - 2] = 1;
            $logical[$numRows - 2][$numCols - 1] = 1;
        }

        // Build physical symbol with finder + timing per region.
        $matrix = array_fill(0, $symbolSize, array_fill(0, $symbolSize, false));
        for ($rr = 0; $rr < $regionCount; $rr++) {
            for ($rc = 0; $rc < $regionCount; $rc++) {
                $r0 = $rr * ($regionDataSize + 2);
                $c0 = $rc * ($regionDataSize + 2);
                // Top row (timing): alternating starting dark at col 0.
                for ($c = 0; $c < $regionDataSize + 2; $c++) {
                    $matrix[$r0][$c0 + $c] = ($c % 2 === 0);
                }
                // Bottom row (finder): solid dark.
                for ($c = 0; $c < $regionDataSize + 2; $c++) {
                    $matrix[$r0 + $regionDataSize + 1][$c0 + $c] = true;
                }
                // Left column (finder): solid dark.
                for ($r = 0; $r < $regionDataSize + 2; $r++) {
                    $matrix[$r0 + $r][$c0] = true;
                }
                // Right column (timing): alternating ending dark at bottom.
                for ($r = 0; $r < $regionDataSize + 2; $r++) {
                    $matrix[$r0 + $r][$c0 + $regionDataSize + 1] = (($regionDataSize + 1 - $r) % 2 === 0);
                }
                // Copy data modules into the interior.
                for ($r = 0; $r < $regionDataSize; $r++) {
                    for ($c = 0; $c < $regionDataSize; $c++) {
                        $logicalR = $rr * $regionDataSize + $r;
                        $logicalC = $rc * $regionDataSize + $c;
                        $matrix[$r0 + 1 + $r][$c0 + 1 + $c] = $logical[$logicalR][$logicalC] === 1;
                    }
                }
            }
        }

        return $matrix;
    }

    /**
     * ISO 16022 Annex F placement: walks the codeword stream through
     * the logical grid using the utah-shaped pattern, with 4 corner
     * cases for matrix dimensions that don't divide evenly.
     *
     * @param array<int, array<int, int>> $m
     * @param list<int>                   $codewords
     */
    private static function placeData(array &$m, array $codewords, int $numRows, int $numCols): void
    {
        $charPos = 0;
        $row = 4;
        $col = 0;
        do {
            // Corner cases when the utah pattern would wrap off the edge.
            if ($row === $numRows && $col === 0) {
                self::corner1($m, $codewords[$charPos++], $numRows, $numCols);
            }
            if ($row === $numRows - 2 && $col === 0 && $numCols % 4 !== 0) {
                self::corner2($m, $codewords[$charPos++], $numRows, $numCols);
            }
            if ($row === $numRows - 2 && $col === 0 && $numCols % 8 === 4) {
                self::corner3($m, $codewords[$charPos++], $numRows, $numCols);
            }
            if ($row === $numRows + 4 && $col === 2 && $numCols % 8 === 0) {
                self::corner4($m, $codewords[$charPos++], $numRows, $numCols);
            }

            // Sweep up-right.
            do {
                if ($row < $numRows && $col >= 0 && $m[$row][$col] === -1) {
                    self::utah($m, $row, $col, $codewords[$charPos++], $numRows, $numCols);
                }
                $row -= 2;
                $col += 2;
            } while ($row >= 0 && $col < $numCols);
            $row += 1;
            $col += 3;

            // Sweep down-left.
            do {
                if ($row >= 0 && $col < $numCols && $m[$row][$col] === -1) {
                    self::utah($m, $row, $col, $codewords[$charPos++], $numRows, $numCols);
                }
                $row += 2;
                $col -= 2;
            } while ($row < $numRows && $col >= 0);
            $row += 3;
            $col += 1;
        } while ($row < $numRows || $col < $numCols);
    }

    /**
     * Place one codeword's 8 bits in the utah L-shape around (row, col).
     *
     * @param array<int, array<int, int>> $m
     */
    private static function utah(array &$m, int $row, int $col, int $byte, int $numRows, int $numCols): void
    {
        self::placeBit($m, $row - 2, $col - 2, $byte >> 7 & 1, $numRows, $numCols);
        self::placeBit($m, $row - 2, $col - 1, $byte >> 6 & 1, $numRows, $numCols);
        self::placeBit($m, $row - 1, $col - 2, $byte >> 5 & 1, $numRows, $numCols);
        self::placeBit($m, $row - 1, $col - 1, $byte >> 4 & 1, $numRows, $numCols);
        self::placeBit($m, $row - 1, $col, $byte >> 3 & 1, $numRows, $numCols);
        self::placeBit($m, $row, $col - 2, $byte >> 2 & 1, $numRows, $numCols);
        self::placeBit($m, $row, $col - 1, $byte >> 1 & 1, $numRows, $numCols);
        self::placeBit($m, $row, $col, $byte >> 0 & 1, $numRows, $numCols);
    }

    /**
     * Place a single bit, wrapping coordinates that fall off the top
     * or left edges per the standard wrap formulas.
     *
     * @param array<int, array<int, int>> $m
     */
    private static function placeBit(array &$m, int $row, int $col, int $bit, int $numRows, int $numCols): void
    {
        if ($row < 0) {
            $row += $numRows;
            $col += 4 - (($numRows + 4) % 8);
        }
        if ($col < 0) {
            $col += $numCols;
            $row += 4 - (($numCols + 4) % 8);
        }
        $m[$row][$col] = $bit;
    }

    /** @param array<int, array<int, int>> $m */
    private static function corner1(array &$m, int $byte, int $numRows, int $numCols): void
    {
        self::placeBit($m, $numRows - 1, 0, $byte >> 7 & 1, $numRows, $numCols);
        self::placeBit($m, $numRows - 1, 1, $byte >> 6 & 1, $numRows, $numCols);
        self::placeBit($m, $numRows - 1, 2, $byte >> 5 & 1, $numRows, $numCols);
        self::placeBit($m, 0, $numCols - 2, $byte >> 4 & 1, $numRows, $numCols);
        self::placeBit($m, 0, $numCols - 1, $byte >> 3 & 1, $numRows, $numCols);
        self::placeBit($m, 1, $numCols - 1, $byte >> 2 & 1, $numRows, $numCols);
        self::placeBit($m, 2, $numCols - 1, $byte >> 1 & 1, $numRows, $numCols);
        self::placeBit($m, 3, $numCols - 1, $byte >> 0 & 1, $numRows, $numCols);
    }

    /** @param array<int, array<int, int>> $m */
    private static function corner2(array &$m, int $byte, int $numRows, int $numCols): void
    {
        self::placeBit($m, $numRows - 3, 0, $byte >> 7 & 1, $numRows, $numCols);
        self::placeBit($m, $numRows - 2, 0, $byte >> 6 & 1, $numRows, $numCols);
        self::placeBit($m, $numRows - 1, 0, $byte >> 5 & 1, $numRows, $numCols);
        self::placeBit($m, 0, $numCols - 4, $byte >> 4 & 1, $numRows, $numCols);
        self::placeBit($m, 0, $numCols - 3, $byte >> 3 & 1, $numRows, $numCols);
        self::placeBit($m, 0, $numCols - 2, $byte >> 2 & 1, $numRows, $numCols);
        self::placeBit($m, 0, $numCols - 1, $byte >> 1 & 1, $numRows, $numCols);
        self::placeBit($m, 1, $numCols - 1, $byte >> 0 & 1, $numRows, $numCols);
    }

    /** @param array<int, array<int, int>> $m */
    private static function corner3(array &$m, int $byte, int $numRows, int $numCols): void
    {
        self::placeBit($m, $numRows - 3, 0, $byte >> 7 & 1, $numRows, $numCols);
        self::placeBit($m, $numRows - 2, 0, $byte >> 6 & 1, $numRows, $numCols);
        self::placeBit($m, $numRows - 1, 0, $byte >> 5 & 1, $numRows, $numCols);
        self::placeBit($m, 0, $numCols - 2, $byte >> 4 & 1, $numRows, $numCols);
        self::placeBit($m, 0, $numCols - 1, $byte >> 3 & 1, $numRows, $numCols);
        self::placeBit($m, 1, $numCols - 1, $byte >> 2 & 1, $numRows, $numCols);
        self::placeBit($m, 2, $numCols - 1, $byte >> 1 & 1, $numRows, $numCols);
        self::placeBit($m, 3, $numCols - 1, $byte >> 0 & 1, $numRows, $numCols);
    }

    /** @param array<int, array<int, int>> $m */
    private static function corner4(array &$m, int $byte, int $numRows, int $numCols): void
    {
        self::placeBit($m, $numRows - 1, 0, $byte >> 7 & 1, $numRows, $numCols);
        self::placeBit($m, $numRows - 1, $numCols - 1, $byte >> 6 & 1, $numRows, $numCols);
        self::placeBit($m, 0, $numCols - 3, $byte >> 5 & 1, $numRows, $numCols);
        self::placeBit($m, 0, $numCols - 2, $byte >> 4 & 1, $numRows, $numCols);
        self::placeBit($m, 0, $numCols - 1, $byte >> 3 & 1, $numRows, $numCols);
        self::placeBit($m, 1, $numCols - 3, $byte >> 2 & 1, $numRows, $numCols);
        self::placeBit($m, 1, $numCols - 2, $byte >> 1 & 1, $numRows, $numCols);
        self::placeBit($m, 1, $numCols - 1, $byte >> 0 & 1, $numRows, $numCols);
    }
}
