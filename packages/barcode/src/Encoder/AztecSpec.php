<?php

declare(strict_types=1);

namespace Phpdftk\Barcode\Encoder;

/**
 * Aztec Code constants and lookup tables.
 *
 * The Aztec spec (AIM ISO/IEC 24778) defines two formats:
 *   - **Compact** — 1 to 4 layers, sizes 15×15 to 27×27, no reference grid.
 *   - **Full**    — 1 to 32 layers, sizes 19×19 to 151×151, reference grid
 *     lines through the centre (and every 16 modules for L ≥ 5) that the
 *     `alignmentMap` translates around during placement.
 *
 * For both formats:
 *   - word size = 6 bits for L ≤ 2 (GF(64)), 8 bits for L = 3..8 (GF(256)),
 *     10 bits for L = 9..22 (GF(1024)), 12 bits for L = 23..32 (GF(4096))
 *   - total data area bits = `(88 + 16L) · L` (Compact) or `(112 + 16L) · L` (Full)
 *   - usable codewords    = floor(totalBits / wordSize)
 *
 * Compact has a hard cap of 64 data codewords (the 6-bit mode-message length
 * field can only encode dataCwCount − 1 ∈ [0, 63]); Full extends this to
 * 2048 via an 11-bit length field.
 *
 * @internal
 */
final class AztecSpec
{
    /**
     * @var array<int, array{size:int, wordSize:int, totalCodewords:int}>
     *      Compact layer parameters, indexed by L (1..4).
     */
    public const COMPACT_LAYERS = [
        1 => ['size' => 15, 'wordSize' => 6, 'totalCodewords' => 17],
        2 => ['size' => 19, 'wordSize' => 6, 'totalCodewords' => 40],
        3 => ['size' => 23, 'wordSize' => 8, 'totalCodewords' => 51],
        4 => ['size' => 27, 'wordSize' => 8, 'totalCodewords' => 76],
    ];

    /**
     * Number of 4-bit codewords in the Compact mode message:
     *   - 2 data (2 layer bits + 6 length bits = 8 bits = 2 × 4)
     *   - 5 ECC
     *   - 7 total → 28 bits placed in the ring around the bullseye.
     */
    public const COMPACT_MODE_MESSAGE_TOTAL_4BIT_CODEWORDS = 7;
    public const COMPACT_MODE_MESSAGE_DATA_4BIT_CODEWORDS = 2;
    public const COMPACT_MODE_MESSAGE_ECC_4BIT_CODEWORDS = 5;

    /** Compact format max data codewords (6-bit length field in mode message). */
    public const COMPACT_MAX_DATA_CODEWORDS = 64;
    /** Full format max data codewords (11-bit length field). */
    public const FULL_MAX_DATA_CODEWORDS = 2048;

    /**
     * Total data-area bits for layer `L` per AIM ISO/IEC 24778 §A.1.
     *
     * Usable bits = floor(total / wordSize) · wordSize — leftover bits at the
     * inner end of layer 1 are unused for L=1 Compact (104 → 102; 2 dropped)
     * and L=1 Full (128 → 126; 2 dropped). For Compact L=1 the surplus is
     * prepended to the codeword stream as a 2-bit zero `startPad` to align
     * placement; same for Full L=1.
     */
    public static function totalDataBits(int $layers, bool $compact): int
    {
        return (($compact ? 88 : 112) + 16 * $layers) * $layers;
    }

    /**
     * Word size in bits for a given layer count — same for Compact and Full.
     *
     *   L ≤ 2 → 6 bits (GF(64))
     *   L ≤ 8 → 8 bits (GF(256))
     *   L ≤ 22 → 10 bits (GF(1024))
     *   else  → 12 bits (GF(4096))
     */
    public static function wordSize(int $layers): int
    {
        return match (true) {
            $layers <= 2 => 6,
            $layers <= 8 => 8,
            $layers <= 22 => 10,
            default => 12,
        };
    }

    /**
     * Full symbol size for layer L, accounting for the centre reference grid
     * line and the additional lines every 16 modules for L ≥ 5.
     *
     *   matrixSize = baseMatrixSize + 1 + 2 · floor((baseMatrixSize/2 − 1) / 15)
     *   baseMatrixSize = 14 + 4L
     */
    public static function fullSize(int $layers): int
    {
        $base = 14 + 4 * $layers;
        return $base + 1 + 2 * intdiv(intdiv($base, 2) - 1, 15);
    }

    /**
     * Build the `alignmentMap` for Full format: a translation from logical
     * data position (0 .. baseMatrixSize-1) to physical matrix position
     * (0 .. matrixSize-1) skipping reference grid lines.
     *
     * For Compact this is the identity — placement passes through directly.
     *
     * @return list<int>
     */
    public static function fullAlignmentMap(int $layers): array
    {
        $base = 14 + 4 * $layers;
        $matrixSize = self::fullSize($layers);
        $origCenter = intdiv($base, 2);
        $center = intdiv($matrixSize, 2);
        $map = array_fill(0, $base, 0);
        for ($i = 0; $i < $origCenter; $i++) {
            $newOffset = $i + intdiv($i, 15);
            $map[$origCenter - $i - 1] = $center - $newOffset - 1;
            $map[$origCenter + $i] = $center + $newOffset + 1;
        }
        return $map;
    }
}
