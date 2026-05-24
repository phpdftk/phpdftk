<?php

declare(strict_types=1);

namespace Phpdftk\Barcode\Encoder;

use Phpdftk\Barcode\BarcodeBitmap;
use Phpdftk\Barcode\BarcodeOptions;

/**
 * Aztec Code encoder — Compact + Full formats (AIM ISO/IEC 24778).
 *
 * Compact format (L=1..4) carries up to 64 data codewords around a 9×9
 * bullseye with a 1-module-thick mode-message ring. Symbol sizes 15×15,
 * 19×19, 23×23, 27×27.
 *
 * Full format (L=1..32) carries up to 2 048 data codewords around a 13×13
 * bullseye with reference-grid lines through the centre (and every 16 modules
 * for L ≥ 5). Symbol sizes 19×19, 23×23, …, 151×151.
 *
 * Word size is 6 bits for L ≤ 2 (GF(64)), 8 bits for L ≤ 8 (GF(256)),
 * 10 bits for L ≤ 22 (GF(1024)), 12 bits for L ≤ 32 (GF(4096)). The mode
 * message is always encoded as 4-bit codewords with GF(16) RS — 2 data + 5
 * ECC = 28 bits for Compact, 4 data + 6 ECC = 40 bits for Full.
 *
 * Data encoding uses the **high-level state-search encoder** that picks
 * the optimal mix of Upper / Lower / Mixed / Punct / Digit sub-alphabets
 * plus Binary Shift for non-mappable bytes — typical ASCII payloads
 * encode at ~30–40 % the size of pure byte-shift encoding.
 *
 * @internal
 */
final class Aztec
{
    /** Default ECC percentage — 23% per AIM spec recommendation. */
    private const DEFAULT_ECC_PERCENT = 23;

    public static function encode(string $data, BarcodeOptions $options): BarcodeBitmap
    {
        if ($data === '') {
            throw new \InvalidArgumentException('Aztec requires non-empty input.');
        }

        // 1. Encode payload as an optimal bit stream via the high-level
        //    state-search encoder (alphabets + binary shift).
        $bits = AztecHighLevelEncoder::encode($data);

        // 2. Pick format (Compact first, fall back to Full) and layer count to
        //    fit payload + 23% ECC, then stuff bits + pad to word boundary.
        [$compact, $layers, $wordSize, $totalCodewords] = self::pickGeometry(count($bits));
        $bits = self::stuffBits($bits, $wordSize);
        while ((count($bits) % $wordSize) !== 0) {
            $bits[] = 1;
        }
        $dataCodewordCount = (int) (count($bits) / $wordSize);
        $eccCodewordCount = $totalCodewords - $dataCodewordCount;
        if ($eccCodewordCount < 1) {
            throw new \InvalidArgumentException(
                "Aztec geometry picker chose L=$layers with insufficient ECC space (need ≥ 1 ECC codeword).",
            );
        }

        $codewords = self::packBitsToCodewords($bits, $wordSize);

        // 3. Reed-Solomon over the data codewords to produce ECC, using the
        //    field that corresponds to this layer's word size.
        $field = AztecGaloisField::from(AztecGaloisField::forWordSize($wordSize));
        $rs = new AztecReedSolomon($field, $eccCodewordCount);
        $ecc = $rs->encode($codewords);
        $allCodewords = array_merge($codewords, $ecc);

        // 4. Mode message.
        //    Compact: 2-bit (L-1) + 6-bit (dataCW-1) → 28 bits with GF(16) RS.
        //    Full:    5-bit (L-1) + 11-bit (dataCW-1) → 40 bits with GF(16) RS.
        $modeBits = self::buildModeMessage($compact, $layers, $dataCodewordCount);

        // 5. Render: allocate grid, draw bullseye + mode message + data layers,
        //    applying the alignment-grid offset map for Full format.
        $size = $compact
            ? AztecSpec::COMPACT_LAYERS[$layers]['size']
            : AztecSpec::fullSize($layers);
        $grid = self::renderSymbol($size, $layers, $wordSize, $compact, $allCodewords, $modeBits);

        return new BarcodeBitmap(
            modules: $grid,
            moduleWidth: $options->moduleWidth,
            height: $options->height,
            quietZoneModules: $options->quietZoneModules,
        );
    }


    /**
     * Aztec invariant: no data codeword may be all-0s or all-1s.
     *
     * When a window of `wordSize - 1` consecutive bits is all the same value,
     * insert the complementary bit at the next position and shift remaining
     * bits one place right. Repeat until no all-same window remains.
     *
     * @param list<int> $bits
     * @return list<int>
     */
    private static function stuffBits(array $bits, int $wordSize): array
    {
        $stuffed = [];
        $i = 0;
        $n = count($bits);
        while ($i < $n) {
            // Look at the next $wordSize bits; if they're all 0 or all 1, splice.
            $remaining = $n - $i;
            $window = min($wordSize, $remaining);
            $all0 = true;
            $all1 = true;
            for ($j = 0; $j < $window - 1; $j++) {
                if ($bits[$i + $j] !== 0) {
                    $all0 = false;
                }
                if ($bits[$i + $j] !== 1) {
                    $all1 = false;
                }
            }
            if ($window === $wordSize && ($all0 || $all1)) {
                // Take the first $wordSize-1 bits, then insert complementary bit.
                for ($j = 0; $j < $wordSize - 1; $j++) {
                    $stuffed[] = $bits[$i + $j];
                }
                $stuffed[] = $all0 ? 1 : 0;
                $i += $wordSize - 1;
            } else {
                $stuffed[] = $bits[$i];
                $i++;
            }
        }
        return $stuffed;
    }

    /**
     * Pack `$bits` (MSB-first) into `$wordSize`-bit codewords.
     *
     * @param list<int> $bits
     * @return list<int>
     */
    private static function packBitsToCodewords(array $bits, int $wordSize): array
    {
        $codewords = [];
        $n = count($bits);
        for ($i = 0; $i < $n; $i += $wordSize) {
            $cw = 0;
            for ($j = 0; $j < $wordSize; $j++) {
                $cw = ($cw << 1) | $bits[$i + $j];
            }
            $codewords[] = $cw;
        }
        return $codewords;
    }

    /**
     * Try Compact L=1..4 first (smaller symbols), then Full L=1..32. Returns
     * the smallest fitting geometry that holds payload bits + bit-stuff overhead
     * + ≥ 23% ECC.
     *
     * @return array{0:bool, 1:int, 2:int, 3:int} [compact, layers, wordSize, totalCodewords]
     */
    private static function pickGeometry(int $payloadBitCount): array
    {
        foreach ([true, false] as $compact) {
            $maxLayers = $compact ? 4 : 32;
            for ($L = 1; $L <= $maxLayers; $L++) {
                $wordSize = AztecSpec::wordSize($L);
                $totalBits = AztecSpec::totalDataBits($L, $compact);
                $totalCw = intdiv($totalBits, $wordSize);
                $eccTarget = (int) max(1, ceil($totalCw * self::DEFAULT_ECC_PERCENT / 100));
                $dataCapacityBits = ($totalCw - $eccTarget) * $wordSize;
                $maxDataCw = $compact
                    ? AztecSpec::COMPACT_MAX_DATA_CODEWORDS
                    : AztecSpec::FULL_MAX_DATA_CODEWORDS;
                if ($totalCw - $eccTarget > $maxDataCw) {
                    // Cap dataCapacity at the mode-message length-field limit.
                    $dataCapacityBits = $maxDataCw * $wordSize;
                }
                $stuffOverhead = (int) ceil($payloadBitCount / max(1, $wordSize - 1));
                if ($payloadBitCount + $stuffOverhead <= $dataCapacityBits) {
                    return [$compact, $L, $wordSize, $totalCw];
                }
            }
        }
        throw new \InvalidArgumentException(
            'Payload exceeds Aztec Full format capacity (max ~1 900 bytes at byte mode with 23% ECC).',
        );
    }

    /**
     * Build the mode message:
     *   - Compact: 2-bit (L−1) + 6-bit (dataCwCount−1) → 8 bits → 28 bits after GF(16) RS
     *   - Full:    5-bit (L−1) + 11-bit (dataCwCount−1) → 16 bits → 40 bits after GF(16) RS
     *
     * @return list<int>
     */
    private static function buildModeMessage(bool $compact, int $layers, int $dataCodewordCount): array
    {
        $modeBits = [];
        if ($compact) {
            self::appendBits($modeBits, $layers - 1, 2);
            self::appendBits($modeBits, $dataCodewordCount - 1, 6);
            $eccCount = 5;  // 2 data + 5 ECC = 7 four-bit codewords
        } else {
            self::appendBits($modeBits, $layers - 1, 5);
            self::appendBits($modeBits, $dataCodewordCount - 1, 11);
            $eccCount = 6;  // 4 data + 6 ECC = 10 four-bit codewords
        }
        $modeCodewords = self::packBitsToCodewords($modeBits, 4);
        $field = AztecGaloisField::from(AztecGaloisField::GF_16);
        $rs = new AztecReedSolomon($field, $eccCount);
        $modeEcc = $rs->encode($modeCodewords);
        $allMode = array_merge($modeCodewords, $modeEcc);
        $bits = [];
        foreach ($allMode as $cw) {
            self::appendBits($bits, $cw, 4);
        }
        return $bits;
    }

    /**
     * @param list<int> &$bits
     */
    private static function appendBits(array &$bits, int $value, int $width): void
    {
        for ($i = $width - 1; $i >= 0; $i--) {
            $bits[] = ($value >> $i) & 1;
        }
    }

    /**
     * Build the size × size module grid: bullseye + 6 asymmetric orientation
     * pixels + mode-message ring + layer data rings + (Full only) reference
     * grid lines at every 16th physical column/row.
     *
     * @param list<int> $allCodewords  Data + ECC codewords.
     * @param list<int> $modeBits      28 bits (Compact) or 40 bits (Full).
     * @return list<list<bool>>
     */
    private static function renderSymbol(
        int $size,
        int $layers,
        int $wordSize,
        bool $compact,
        array $allCodewords,
        array $modeBits,
    ): array {
        $grid = [];
        for ($y = 0; $y < $size; $y++) {
            $grid[] = array_fill(0, $size, false);
        }

        $center = intdiv($size, 2);
        $baseMatrixSize = ($compact ? 11 : 14) + $layers * 4;

        // Compact = identity, Full inserts a 1-module gap at the centre (and
        // additional gaps every 15 logical positions for L ≥ 5).
        $alignmentMap = $compact
            ? range(0, $baseMatrixSize - 1)
            : AztecSpec::fullAlignmentMap($layers);

        // Reference grid lines (Full only): every other physical module on
        // each reference row/column is a bar — these guide scanners through
        // larger symbols. The lines pass through the centre of the symbol.
        if (!$compact) {
            self::drawReferenceGrid($grid, $size, $layers, $baseMatrixSize);
        }

        // Bullseye + asymmetric orientation pixels.
        // Compact uses size=5 (9×9 bullseye); Full uses size=7 (13×13).
        self::drawBullseye($grid, $center, $compact ? 5 : 7);

        // Mode-message ring.
        self::drawModeMessage($grid, $center, $compact, $modeBits);

        // Data placement — per ZXing reference (Encoder.cs lines 252–279).
        // Layer i = 0 is OUTERMOST. Each j step places a 2-module domino on
        // each of 4 sides. For L=1 the (totalBits % wordSize) surplus bits at
        // the inner end of the layer are filled with a zero `startPad` so
        // placement aligns to the codeword stream.
        $totalBitsInLayer = AztecSpec::totalDataBits($layers, $compact);
        $startPad = $totalBitsInLayer % $wordSize;
        $dataBits = array_fill(0, $startPad, 0);
        foreach ($allCodewords as $cw) {
            self::appendBits($dataBits, $cw, $wordSize);
        }
        $rowOffset = 0;
        for ($i = 0; $i < $layers; $i++) {
            // Compact: 9, 13, 17, 21 for L=1..4. Full: 12, 16, 20, …
            $rowSize = ($layers - $i) * 4 + ($compact ? 9 : 12);
            for ($j = 0; $j < $rowSize; $j++) {
                $columnOffset = $j * 2;
                for ($k = 0; $k < 2; $k++) {
                    // Side A — left edge:    matrix[α(i*2+k), α(i*2+j)]
                    $idx = $rowOffset + $columnOffset + $k;
                    if (($dataBits[$idx] ?? 0) === 1) {
                        $grid[$alignmentMap[$i * 2 + $j]][$alignmentMap[$i * 2 + $k]] = true;
                    }
                    // Side B — bottom edge:  matrix[α(i*2+j), α(BM-1-i*2-k)]
                    $idx = $rowOffset + $rowSize * 2 + $columnOffset + $k;
                    if (($dataBits[$idx] ?? 0) === 1) {
                        $grid[$alignmentMap[$baseMatrixSize - 1 - $i * 2 - $k]][$alignmentMap[$i * 2 + $j]] = true;
                    }
                    // Side C — right edge:   matrix[α(BM-1-i*2-k), α(BM-1-i*2-j)]
                    $idx = $rowOffset + $rowSize * 4 + $columnOffset + $k;
                    if (($dataBits[$idx] ?? 0) === 1) {
                        $grid[$alignmentMap[$baseMatrixSize - 1 - $i * 2 - $j]][$alignmentMap[$baseMatrixSize - 1 - $i * 2 - $k]] = true;
                    }
                    // Side D — top edge:     matrix[α(BM-1-i*2-j), α(i*2+k)]
                    $idx = $rowOffset + $rowSize * 6 + $columnOffset + $k;
                    if (($dataBits[$idx] ?? 0) === 1) {
                        $grid[$alignmentMap[$i * 2 + $k]][$alignmentMap[$baseMatrixSize - 1 - $i * 2 - $j]] = true;
                    }
                }
            }
            $rowOffset += $rowSize * 8;
        }

        return $grid;
    }

    /**
     * Draw the Aztec bullseye with `2·size + 1` module diameter, including
     * the 6 asymmetric orientation modules just outside the outer ring.
     *
     * @param list<list<bool>> &$grid
     */
    private static function drawBullseye(array &$grid, int $center, int $size): void
    {
        for ($i = 0; $i < $size; $i += 2) {
            for ($j = $center - $i; $j <= $center + $i; $j++) {
                $grid[$center - $i][$j] = true;
                $grid[$center + $i][$j] = true;
                $grid[$j][$center - $i] = true;
                $grid[$j][$center + $i] = true;
            }
        }
        // Asymmetric orientation pixels: 3 at TL, 2 at TR, 1 at BR, 0 at BL.
        $grid[$center - $size][$center - $size] = true;
        $grid[$center - $size][$center - $size + 1] = true;
        $grid[$center - $size + 1][$center - $size] = true;
        $grid[$center - $size][$center + $size] = true;
        $grid[$center - $size + 1][$center + $size] = true;
        $grid[$center + $size - 1][$center + $size] = true;
    }

    /**
     * Draw the mode-message ring around the bullseye.
     *
     * Compact (28 bits, 7 per side): positions span [center−3, center+3] on
     * each side at distance 5 from centre.
     * Full (40 bits, 10 per side): positions span [center−5, center+5]
     * skipping the centre column/row (which is a reference grid line);
     * effective `offset = center − 5 + i + ⌊i/5⌋`.
     *
     * @param list<list<bool>> &$grid
     * @param list<int> $modeBits
     */
    private static function drawModeMessage(array &$grid, int $center, bool $compact, array $modeBits): void
    {
        if ($compact) {
            for ($i = 0; $i < 7; $i++) {
                $offset = $center - 3 + $i;
                if ($modeBits[$i] === 1) {
                    $grid[$center - 5][$offset] = true;
                }
                if ($modeBits[$i + 7] === 1) {
                    $grid[$offset][$center + 5] = true;
                }
                if ($modeBits[20 - $i] === 1) {
                    $grid[$center + 5][$offset] = true;
                }
                if ($modeBits[27 - $i] === 1) {
                    $grid[$offset][$center - 5] = true;
                }
            }
            return;
        }
        for ($i = 0; $i < 10; $i++) {
            $offset = $center - 5 + $i + intdiv($i, 5);
            if ($modeBits[$i] === 1) {
                $grid[$center - 7][$offset] = true;
            }
            if ($modeBits[$i + 10] === 1) {
                $grid[$offset][$center + 7] = true;
            }
            if ($modeBits[29 - $i] === 1) {
                $grid[$center + 7][$offset] = true;
            }
            if ($modeBits[39 - $i] === 1) {
                $grid[$offset][$center - 7] = true;
            }
        }
    }

    /**
     * Full format reference grid: alignment modules on rows/columns that lie
     * between the data layers (where the alignment map inserts a gap).
     *
     * For each reference row r ∈ {center, center ± 16, center ± 32, …} every
     * other module across the symbol is a bar — same for reference columns.
     * The pattern is `matrix[r, c] = ((r + c) is even)`.
     *
     * @param list<list<bool>> &$grid
     */
    private static function drawReferenceGrid(array &$grid, int $size, int $layers, int $baseMatrixSize): void
    {
        $halfBase = intdiv($baseMatrixSize, 2);
        $center = intdiv($size, 2);
        // Iterate "reference offsets" from the centre: j=0 (centre line) plus
        // j=16, 32, … for L ≥ 5 (each marking an additional alignment line).
        for ($i = 0, $j = 0; $i < $halfBase - 1; $i += 15, $j += 16) {
            for ($k = ($center & 1); $k < $size; $k += 2) {
                $grid[$center - $j][$k] = true;
                $grid[$center + $j][$k] = true;
                $grid[$k][$center - $j] = true;
                $grid[$k][$center + $j] = true;
            }
        }
    }
}
