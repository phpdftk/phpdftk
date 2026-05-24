<?php

declare(strict_types=1);

namespace Phpdftk\Barcode\Encoder;

use Phpdftk\Barcode\BarcodeBitmap;
use Phpdftk\Barcode\BarcodeOptions;

/**
 * QR Code encoder — ISO/IEC 18004.
 *
 * Supports versions 1-40 (matrix sizes 21×21 through 177×177), all
 * four error-correction levels (L, M, Q, H), and the three byte-level
 * encoding modes: numeric, alphanumeric, and byte. Kanji mode is not
 * implemented (rarely needed outside Japanese-only payloads).
 *
 * The encoder auto-selects the smallest version that fits the data
 * at the requested ECC level and tries all eight mask patterns,
 * picking the lowest-penalty one per ISO 18004 §7.8.3.
 */
final class Qr
{
    private const MODE_NUMERIC = 0b0001;
    private const MODE_ALPHANUMERIC = 0b0010;
    private const MODE_BYTE = 0b0100;
    private const MODE_TERMINATOR = 0b0000;

    /** Alphanumeric mode character set (45 chars). */
    private const ALPHANUMERIC = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ $%*+-./:';

    /**
     * Encode the given data into a QR code at the given ECC level.
     * `$eccLevel` is a `QrSpec::ECC_*` constant; defaults to M.
     */
    public static function encode(
        string $data,
        BarcodeOptions $options,
        int $eccLevel = QrSpec::ECC_M,
    ): BarcodeBitmap {
        if ($data === '') {
            throw new \InvalidArgumentException('QR input must be non-empty.');
        }
        if ($eccLevel < 0 || $eccLevel > 3) {
            throw new \InvalidArgumentException("QR ECC level must be 0-3, got {$eccLevel}.");
        }

        $mode = self::pickMode($data);
        $version = self::pickVersion($data, $mode, $eccLevel);
        $bits = self::buildBitstream($data, $mode, $version, $eccLevel);
        $codewords = self::interleave($bits, $version, $eccLevel);
        $matrix = self::buildMatrix($codewords, $version, $eccLevel);

        return new BarcodeBitmap(
            modules: $matrix,
            moduleWidth: $options->moduleWidth,
            height: $options->height,
            quietZoneModules: $options->quietZoneModules,
        );
    }

    private static function pickMode(string $data): int
    {
        if (ctype_digit($data)) {
            return self::MODE_NUMERIC;
        }
        $len = strlen($data);
        for ($i = 0; $i < $len; $i++) {
            if (strpos(self::ALPHANUMERIC, $data[$i]) === false) {
                return self::MODE_BYTE;
            }
        }
        return self::MODE_ALPHANUMERIC;
    }

    private static function pickVersion(string $data, int $mode, int $eccLevel): int
    {
        $dataLen = strlen($data);
        for ($v = 1; $v <= 40; $v++) {
            $capacityCw = QrSpec::dataCodewordsCapacity($v, $eccLevel);
            $availableBits = $capacityCw * 8;
            $needed = 4                                          // mode indicator
                + QrSpec::charCountBits($v, $mode)               // char count
                + self::modeBitLength($mode, $dataLen);          // payload
            if ($needed <= $availableBits) {
                return $v;
            }
        }
        throw new \RuntimeException("QR data does not fit in any version 1-40 at the requested ECC level.");
    }

    private static function modeBitLength(int $mode, int $dataLen): int
    {
        return match ($mode) {
            self::MODE_NUMERIC => intdiv($dataLen, 3) * 10
                + (($dataLen % 3 === 1) ? 4 : (($dataLen % 3 === 2) ? 7 : 0)),
            self::MODE_ALPHANUMERIC => intdiv($dataLen, 2) * 11 + ($dataLen % 2) * 6,
            self::MODE_BYTE => $dataLen * 8,
            default => throw new \LogicException(),
        };
    }

    /**
     * Build the padded codeword bytestream (mode header + payload +
     * terminator + pad bytes).
     *
     * @return list<int> Codewords (bytes 0-255).
     */
    private static function buildBitstream(string $data, int $mode, int $version, int $eccLevel): array
    {
        $bits = new QrBitStream();
        $bits->push($mode, 4);
        $bits->push(strlen($data), QrSpec::charCountBits($version, $mode));

        switch ($mode) {
            case self::MODE_NUMERIC:
                self::encodeNumeric($bits, $data);
                break;
            case self::MODE_ALPHANUMERIC:
                self::encodeAlphanumeric($bits, $data);
                break;
            case self::MODE_BYTE:
                self::encodeByte($bits, $data);
                break;
        }

        $capacityBytes = QrSpec::dataCodewordsCapacity($version, $eccLevel);
        $capacityBits = $capacityBytes * 8;
        $remaining = $capacityBits - $bits->size();
        // Terminator (up to 4 zero bits, truncated if short).
        $bits->push(self::MODE_TERMINATOR, min(4, $remaining));
        // Pad to byte boundary with zeros.
        while ($bits->size() % 8 !== 0) {
            $bits->push(0, 1);
        }
        // Pad bytes alternating 0xEC 0x11 until capacity is reached.
        $pad = [0xEC, 0x11];
        $i = 0;
        while ($bits->size() < $capacityBits) {
            $bits->push($pad[$i % 2], 8);
            $i++;
        }

        return $bits->toBytes();
    }

    private static function encodeNumeric(QrBitStream $bits, string $data): void
    {
        $len = strlen($data);
        for ($i = 0; $i + 3 <= $len; $i += 3) {
            $bits->push((int) substr($data, $i, 3), 10);
        }
        $rem = $len % 3;
        if ($rem === 1) {
            $bits->push((int) $data[$len - 1], 4);
        } elseif ($rem === 2) {
            $bits->push((int) substr($data, $len - 2, 2), 7);
        }
    }

    private static function encodeAlphanumeric(QrBitStream $bits, string $data): void
    {
        $len = strlen($data);
        for ($i = 0; $i + 2 <= $len; $i += 2) {
            $a = strpos(self::ALPHANUMERIC, $data[$i]);
            $b = strpos(self::ALPHANUMERIC, $data[$i + 1]);
            $bits->push($a * 45 + $b, 11);
        }
        if ($len % 2 === 1) {
            $bits->push((int) strpos(self::ALPHANUMERIC, $data[$len - 1]), 6);
        }
    }

    private static function encodeByte(QrBitStream $bits, string $data): void
    {
        $len = strlen($data);
        for ($i = 0; $i < $len; $i++) {
            $bits->push(ord($data[$i]), 8);
        }
    }

    /**
     * Split data codewords into blocks, compute Reed-Solomon ECC per
     * block, and interleave back into a single codeword stream.
     *
     * @param list<int> $dataCodewords
     * @return list<int>
     */
    private static function interleave(array $dataCodewords, int $version, int $eccLevel): array
    {
        [$eccPerBlock, $g1n, $g1d, $g2n, $g2d] = QrSpec::ECC_BLOCKS[$version - 1][$eccLevel];
        $blocks = [];
        $eccBlocks = [];
        $offset = 0;
        $rs = new QrReedSolomon($eccPerBlock);

        for ($i = 0; $i < $g1n; $i++) {
            $block = array_slice($dataCodewords, $offset, $g1d);
            $blocks[] = $block;
            $eccBlocks[] = $rs->encode($block);
            $offset += $g1d;
        }
        for ($i = 0; $i < $g2n; $i++) {
            $block = array_slice($dataCodewords, $offset, $g2d);
            $blocks[] = $block;
            $eccBlocks[] = $rs->encode($block);
            $offset += $g2d;
        }

        $interleaved = [];
        $maxDataLen = max($g1d, $g2d ?: 0);
        for ($i = 0; $i < $maxDataLen; $i++) {
            foreach ($blocks as $block) {
                if (isset($block[$i])) {
                    $interleaved[] = $block[$i];
                }
            }
        }
        for ($i = 0; $i < $eccPerBlock; $i++) {
            foreach ($eccBlocks as $block) {
                $interleaved[] = $block[$i];
            }
        }

        return $interleaved;
    }

    /**
     * Place modules into the final matrix, apply the lowest-penalty
     * mask, and write format / version info. Returns a 2D bool array
     * indexed `[row][col]` with row 0 at the top.
     *
     * @param list<int> $codewords
     * @return list<list<bool>>
     */
    private static function buildMatrix(array $codewords, int $version, int $eccLevel): array
    {
        $size = 4 * $version + 17;
        // -1 = empty (still mutable), 0/1 = locked function pattern,
        // 2/3 = mutable data (will be masked).
        $matrix = array_fill(0, $size, array_fill(0, $size, -1));

        // Function patterns.
        self::placeFinder($matrix, 0, 0, $size);
        self::placeFinder($matrix, 0, $size - 7, $size);
        self::placeFinder($matrix, $size - 7, 0, $size);
        self::placeSeparators($matrix, $size);
        self::placeAlignment($matrix, $version, $size);
        self::placeTiming($matrix, $size);
        $matrix[$size - 8][8] = 1; // dark module (always set, ISO 18004 §6.3.4)

        // Reserve format / version info cells so the data-placement
        // routine doesn't overwrite them.
        self::reserveFormatBits($matrix, $size);
        if ($version >= 7) {
            self::reserveVersionBits($matrix, $size);
        }

        self::placeData($matrix, $codewords, $size);

        // Try all 8 masks; keep the one with the lowest penalty.
        $bestMask = 0;
        $bestPenalty = PHP_INT_MAX;
        $bestMatrix = null;
        for ($mask = 0; $mask < 8; $mask++) {
            $candidate = self::applyMask($matrix, $mask, $size);
            self::writeFormat($candidate, $eccLevel, $mask, $size);
            if ($version >= 7) {
                self::writeVersion($candidate, $version, $size);
            }
            $penalty = self::penaltyScore($candidate, $size);
            if ($penalty < $bestPenalty) {
                $bestPenalty = $penalty;
                $bestMask = $mask;
                $bestMatrix = $candidate;
            }
        }

        // Convert the int matrix to bool. Anything non-zero = dark.
        $bool = [];
        foreach ($bestMatrix as $row) {
            $boolRow = [];
            foreach ($row as $cell) {
                $boolRow[] = $cell === 1 || $cell === 3;
            }
            $bool[] = $boolRow;
        }
        return $bool;
    }

    /** @param array<int, array<int, int>> $m */
    private static function placeFinder(array &$m, int $r, int $c, int $size): void
    {
        for ($dr = -1; $dr <= 7; $dr++) {
            for ($dc = -1; $dc <= 7; $dc++) {
                $rr = $r + $dr;
                $cc = $c + $dc;
                if ($rr < 0 || $rr >= $size || $cc < 0 || $cc >= $size) {
                    continue;
                }
                $on = ($dr >= 0 && $dr <= 6 && $dc >= 0 && $dc <= 6) && (
                    $dr === 0 || $dr === 6 ||
                    $dc === 0 || $dc === 6 ||
                    ($dr >= 2 && $dr <= 4 && $dc >= 2 && $dc <= 4)
                );
                $m[$rr][$cc] = $on ? 1 : 0;
            }
        }
    }

    /** @param array<int, array<int, int>> $m */
    private static function placeSeparators(array &$m, int $size): void
    {
        // Separators are the white moats around finders; already 0
        // from placeFinder above (the -1..7 sweep includes them).
        // Nothing further to do.
        unset($m, $size);
    }

    /** @param array<int, array<int, int>> $m */
    private static function placeAlignment(array &$m, int $version, int $size): void
    {
        $coords = QrSpec::ALIGNMENT_POSITIONS[$version - 1];
        foreach ($coords as $r) {
            foreach ($coords as $c) {
                // Skip the three corner alignment positions that
                // overlap finder patterns.
                if (($r === 6 && $c === 6) ||
                    ($r === 6 && $c === $size - 7) ||
                    ($r === $size - 7 && $c === 6)) {
                    continue;
                }
                for ($dr = -2; $dr <= 2; $dr++) {
                    for ($dc = -2; $dc <= 2; $dc++) {
                        $on = $dr === -2 || $dr === 2 || $dc === -2 || $dc === 2 || ($dr === 0 && $dc === 0);
                        $m[$r + $dr][$c + $dc] = $on ? 1 : 0;
                    }
                }
            }
        }
    }

    /** @param array<int, array<int, int>> $m */
    private static function placeTiming(array &$m, int $size): void
    {
        for ($i = 8; $i < $size - 8; $i++) {
            $on = $i % 2 === 0 ? 1 : 0;
            if ($m[6][$i] === -1) {
                $m[6][$i] = $on;
            }
            if ($m[$i][6] === -1) {
                $m[$i][6] = $on;
            }
        }
    }

    /** @param array<int, array<int, int>> $m */
    private static function reserveFormatBits(array &$m, int $size): void
    {
        // Row 8: 9 cells on left, 8 on right
        for ($c = 0; $c <= 8; $c++) {
            if ($m[8][$c] === -1) {
                $m[8][$c] = 0;
            }
        }
        for ($c = $size - 8; $c < $size; $c++) {
            $m[8][$c] = 0;
        }
        // Column 8 (excluding row 8 itself).
        for ($r = 0; $r <= 8; $r++) {
            if ($m[$r][8] === -1) {
                $m[$r][8] = 0;
            }
        }
        for ($r = $size - 7; $r < $size; $r++) {
            $m[$r][8] = 0;
        }
    }

    /** @param array<int, array<int, int>> $m */
    private static function reserveVersionBits(array &$m, int $size): void
    {
        // 3×6 block above bottom-left finder and 6×3 block left of top-right finder.
        for ($r = 0; $r < 6; $r++) {
            for ($c = $size - 11; $c < $size - 8; $c++) {
                $m[$r][$c] = 0;
            }
        }
        for ($r = $size - 11; $r < $size - 8; $r++) {
            for ($c = 0; $c < 6; $c++) {
                $m[$r][$c] = 0;
            }
        }
    }

    /**
     * @param array<int, array<int, int>> $m
     * @param list<int> $codewords
     */
    private static function placeData(array &$m, array $codewords, int $size): void
    {
        $bitIdx = 0;
        $totalBits = count($codewords) * 8;
        $upward = true;
        for ($colRight = $size - 1; $colRight > 0; $colRight -= 2) {
            // The vertical timing line is at column 6 — skip it so the
            // walker doesn't double up on x=6.
            if ($colRight === 6) {
                $colRight--;
            }
            for ($i = 0; $i < $size; $i++) {
                $row = $upward ? $size - 1 - $i : $i;
                for ($dc = 0; $dc < 2; $dc++) {
                    $col = $colRight - $dc;
                    if ($m[$row][$col] !== -1) {
                        continue;
                    }
                    if ($bitIdx < $totalBits) {
                        $byte = $codewords[intdiv($bitIdx, 8)];
                        $bit = ($byte >> (7 - $bitIdx % 8)) & 1;
                        // Mutable cells use 2 (light data) / 3 (dark data).
                        $m[$row][$col] = $bit === 1 ? 3 : 2;
                        $bitIdx++;
                    } else {
                        // Remainder bits (always 0).
                        $m[$row][$col] = 2;
                    }
                }
            }
            $upward = !$upward;
        }
    }

    /**
     * @param array<int, array<int, int>> $m
     * @return array<int, array<int, int>>
     */
    private static function applyMask(array $m, int $mask, int $size): array
    {
        $out = $m;
        for ($r = 0; $r < $size; $r++) {
            for ($c = 0; $c < $size; $c++) {
                $cell = $out[$r][$c];
                if ($cell !== 2 && $cell !== 3) {
                    continue; // function module — don't mask
                }
                if (self::maskBit($mask, $r, $c)) {
                    $out[$r][$c] = $cell === 2 ? 3 : 2;
                }
            }
        }
        return $out;
    }

    private static function maskBit(int $mask, int $r, int $c): bool
    {
        return match ($mask) {
            0 => ($r + $c) % 2 === 0,
            1 => $r % 2 === 0,
            2 => $c % 3 === 0,
            3 => ($r + $c) % 3 === 0,
            4 => (intdiv($r, 2) + intdiv($c, 3)) % 2 === 0,
            5 => ($r * $c) % 2 + ($r * $c) % 3 === 0,
            6 => ((($r * $c) % 2) + (($r * $c) % 3)) % 2 === 0,
            7 => ((($r + $c) % 2) + (($r * $c) % 3)) % 2 === 0,
            default => false,
        };
    }

    /**
     * Compute the ISO 18004 §7.8.3 penalty score (lower = better).
     *
     * @param array<int, array<int, int>> $m
     */
    private static function penaltyScore(array $m, int $size): int
    {
        $score = 0;
        // N1: adjacent modules of the same colour, runs of 5+.
        for ($r = 0; $r < $size; $r++) {
            for ($axis = 0; $axis < 2; $axis++) {
                $runLen = 1;
                $prev = -1;
                for ($i = 0; $i < $size; $i++) {
                    $cell = $axis === 0 ? $m[$r][$i] : $m[$i][$r];
                    $isDark = $cell === 1 || $cell === 3 ? 1 : 0;
                    if ($isDark === $prev) {
                        $runLen++;
                        if ($runLen === 5) {
                            $score += 3;
                        } elseif ($runLen > 5) {
                            $score += 1;
                        }
                    } else {
                        $runLen = 1;
                    }
                    $prev = $isDark;
                }
            }
        }
        // N2: 2×2 blocks of the same colour.
        for ($r = 0; $r < $size - 1; $r++) {
            for ($c = 0; $c < $size - 1; $c++) {
                $a = self::darkAt($m, $r, $c);
                if ($a === self::darkAt($m, $r, $c + 1)
                    && $a === self::darkAt($m, $r + 1, $c)
                    && $a === self::darkAt($m, $r + 1, $c + 1)) {
                    $score += 3;
                }
            }
        }
        // N3: finder-like patterns (1011101 with 4 light modules adjacent).
        for ($r = 0; $r < $size; $r++) {
            for ($c = 0; $c < $size - 10; $c++) {
                if (self::isFinderLike($m, $r, $c, true)) {
                    $score += 40;
                }
            }
        }
        for ($c = 0; $c < $size; $c++) {
            for ($r = 0; $r < $size - 10; $r++) {
                if (self::isFinderLike($m, $r, $c, false)) {
                    $score += 40;
                }
            }
        }
        // N4: dark-module ratio penalty.
        $dark = 0;
        for ($r = 0; $r < $size; $r++) {
            for ($c = 0; $c < $size; $c++) {
                if (self::darkAt($m, $r, $c)) {
                    $dark++;
                }
            }
        }
        $ratio = (int) round(($dark * 100) / ($size * $size));
        $deviation = abs($ratio - 50);
        $score += intdiv($deviation, 5) * 10;
        return $score;
    }

    /** @param array<int, array<int, int>> $m */
    private static function darkAt(array $m, int $r, int $c): bool
    {
        $cell = $m[$r][$c];
        return $cell === 1 || $cell === 3;
    }

    /**
     * Match the 1011101 finder pattern (dark-light-dark-light-dark-light-dark, sized 1:1:3:1:1)
     * preceded or followed by 4 light modules.
     *
     * @param array<int, array<int, int>> $m
     */
    private static function isFinderLike(array $m, int $r, int $c, bool $horizontal): bool
    {
        $pattern = [true, false, true, true, true, false, true];
        // Match the 7-module finder run plus 4 light modules on one
        // side (either before or after).
        for ($side = 0; $side < 2; $side++) {
            $offset = $side === 0 ? 0 : 4;
            $ok = true;
            for ($i = 0; $i < 7; $i++) {
                $rr = $horizontal ? $r : $r + $offset + $i;
                $cc = $horizontal ? $c + $offset + $i : $c;
                if (self::darkAt($m, $rr, $cc) !== $pattern[$i]) {
                    $ok = false;
                    break;
                }
            }
            if (!$ok) {
                continue;
            }
            // Four light modules on the opposite side.
            for ($i = 0; $i < 4; $i++) {
                $rr = $horizontal ? $r : $r + ($side === 0 ? 7 + $i : $i);
                $cc = $horizontal ? $c + ($side === 0 ? 7 + $i : $i) : $c;
                if (self::darkAt($m, $rr, $cc)) {
                    $ok = false;
                    break;
                }
            }
            if ($ok) {
                return true;
            }
        }
        return false;
    }

    /** Format info: 5 bits (2 ECC level + 3 mask), BCH-encoded, XOR with mask 0x5412.
     * @param array<int, array<int, int>> $m
     */
    private static function writeFormat(array &$m, int $eccLevel, int $mask, int $size): void
    {
        // ECC level remapped: L=01, M=00, Q=11, H=10.
        $levelBits = [1 => 0, 0 => 1, 3 => 2, 2 => 3][$eccLevel];
        $format = ($levelBits << 3) | $mask;
        $bits = $format << 10;
        $g = 0b10100110111;
        for ($i = 14; $i >= 10; $i--) {
            if (($bits >> $i) & 1) {
                $bits ^= $g << ($i - 10);
            }
        }
        $bits = ($format << 10) | ($bits & 0x3FF);
        $bits ^= 0x5412;

        // Top-left placement: bits 0..5 along row 8, cols 0..5;
        // bit 6 skips the timing col (8,6), goes to (8,7); bit 7 → (8,8);
        // bit 8 → (7,8); bit 9 skips timing row (6,8) and goes to (5,8);
        // bits 10..14 climb up the column from row 5 to row 0.
        for ($i = 0; $i <= 5; $i++) {
            $m[8][$i] = ($bits >> $i) & 1;
        }
        $m[8][7] = ($bits >> 6) & 1;
        $m[8][8] = ($bits >> 7) & 1;
        $m[7][8] = ($bits >> 8) & 1;
        for ($i = 9; $i <= 14; $i++) {
            // bit 9 → row 5, bit 10 → row 4, …, bit 14 → row 0.
            $m[14 - $i][8] = ($bits >> $i) & 1;
        }

        // Bottom + top-right placement: bits 0..6 at column 8, rows
        // (size-1) down to (size-7) — careful not to touch the dark
        // module at (size-8, 8) which sits between this run and the
        // top-left format bits on the same column.
        for ($i = 0; $i <= 6; $i++) {
            $m[$size - 1 - $i][8] = ($bits >> $i) & 1;
        }
        // Bits 7..14 at row 8, columns (size-8) through (size-1).
        for ($i = 7; $i <= 14; $i++) {
            $m[8][$size - 15 + $i] = ($bits >> $i) & 1;
        }
    }

    /** Version info (V7+): 6-bit version + 12 BCH ECC bits.
     * @param array<int, array<int, int>> $m
     */
    private static function writeVersion(array &$m, int $version, int $size): void
    {
        $bits = $version << 12;
        $g = 0b1111100100101;
        for ($i = 17; $i >= 12; $i--) {
            if (($bits >> $i) & 1) {
                $bits ^= $g << ($i - 12);
            }
        }
        $bits = ($version << 12) | ($bits & 0xFFF);

        for ($i = 0; $i < 18; $i++) {
            $b = ($bits >> $i) & 1;
            $r = intdiv($i, 3);
            $c = ($size - 11) + ($i % 3);
            $m[$r][$c] = $b;
            $m[$c][$r] = $b;
        }
    }
}
