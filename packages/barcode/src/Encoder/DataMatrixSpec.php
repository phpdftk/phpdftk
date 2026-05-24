<?php

declare(strict_types=1);

namespace Phpdftk\Barcode\Encoder;

/**
 * Symbol-size table for Data Matrix ECC 200 — ISO/IEC 16022.
 *
 * Each entry is a square symbol size with its region layout, data /
 * ECC codeword counts, and Reed-Solomon interleaving information.
 *
 * Symbol layout: a `regionCount × regionCount` grid of data regions,
 * each `(regionDataSize + 2) × (regionDataSize + 2)` modules including
 * the L-shaped finder pattern (left + bottom) and the alternating
 * timing pattern (top + right). The full symbol size is
 * `regionCount × (regionDataSize + 2)`.
 *
 * For 1×1 region symbols, the entire matrix is one region. For 2×2+
 * symbols, the finder / timing patterns appear *between* regions too,
 * not just around the outer perimeter.
 *
 * @internal
 */
final class DataMatrixSpec
{
    /**
     * @var list<array{
     *   size:int,
     *   regionCount:int,
     *   regionDataSize:int,
     *   dataCodewords:int,
     *   eccCodewords:int,
     *   rsBlocks:int,
     * }>
     */
    public const SQUARE_SIZES = [
        ['size' =>  10, 'regionCount' => 1, 'regionDataSize' =>  8, 'dataCodewords' =>    3, 'eccCodewords' =>   5, 'rsBlocks' =>  1],
        ['size' =>  12, 'regionCount' => 1, 'regionDataSize' => 10, 'dataCodewords' =>    5, 'eccCodewords' =>   7, 'rsBlocks' =>  1],
        ['size' =>  14, 'regionCount' => 1, 'regionDataSize' => 12, 'dataCodewords' =>    8, 'eccCodewords' =>  10, 'rsBlocks' =>  1],
        ['size' =>  16, 'regionCount' => 1, 'regionDataSize' => 14, 'dataCodewords' =>   12, 'eccCodewords' =>  12, 'rsBlocks' =>  1],
        ['size' =>  18, 'regionCount' => 1, 'regionDataSize' => 16, 'dataCodewords' =>   18, 'eccCodewords' =>  14, 'rsBlocks' =>  1],
        ['size' =>  20, 'regionCount' => 1, 'regionDataSize' => 18, 'dataCodewords' =>   22, 'eccCodewords' =>  18, 'rsBlocks' =>  1],
        ['size' =>  22, 'regionCount' => 1, 'regionDataSize' => 20, 'dataCodewords' =>   30, 'eccCodewords' =>  20, 'rsBlocks' =>  1],
        ['size' =>  24, 'regionCount' => 1, 'regionDataSize' => 22, 'dataCodewords' =>   36, 'eccCodewords' =>  24, 'rsBlocks' =>  1],
        ['size' =>  26, 'regionCount' => 1, 'regionDataSize' => 24, 'dataCodewords' =>   44, 'eccCodewords' =>  28, 'rsBlocks' =>  1],
        ['size' =>  32, 'regionCount' => 2, 'regionDataSize' => 14, 'dataCodewords' =>   62, 'eccCodewords' =>  36, 'rsBlocks' =>  1],
        ['size' =>  36, 'regionCount' => 2, 'regionDataSize' => 16, 'dataCodewords' =>   86, 'eccCodewords' =>  42, 'rsBlocks' =>  1],
        ['size' =>  40, 'regionCount' => 2, 'regionDataSize' => 18, 'dataCodewords' =>  114, 'eccCodewords' =>  48, 'rsBlocks' =>  1],
        ['size' =>  44, 'regionCount' => 2, 'regionDataSize' => 20, 'dataCodewords' =>  144, 'eccCodewords' =>  56, 'rsBlocks' =>  1],
        ['size' =>  48, 'regionCount' => 2, 'regionDataSize' => 22, 'dataCodewords' =>  174, 'eccCodewords' =>  68, 'rsBlocks' =>  1],
        ['size' =>  52, 'regionCount' => 2, 'regionDataSize' => 24, 'dataCodewords' =>  204, 'eccCodewords' =>  84, 'rsBlocks' =>  2],
        ['size' =>  64, 'regionCount' => 4, 'regionDataSize' => 14, 'dataCodewords' =>  280, 'eccCodewords' => 112, 'rsBlocks' =>  2],
        ['size' =>  72, 'regionCount' => 4, 'regionDataSize' => 16, 'dataCodewords' =>  368, 'eccCodewords' => 144, 'rsBlocks' =>  4],
        ['size' =>  80, 'regionCount' => 4, 'regionDataSize' => 18, 'dataCodewords' =>  456, 'eccCodewords' => 192, 'rsBlocks' =>  4],
        ['size' =>  88, 'regionCount' => 4, 'regionDataSize' => 20, 'dataCodewords' =>  576, 'eccCodewords' => 224, 'rsBlocks' =>  4],
        ['size' =>  96, 'regionCount' => 4, 'regionDataSize' => 22, 'dataCodewords' =>  696, 'eccCodewords' => 272, 'rsBlocks' =>  4],
        ['size' => 104, 'regionCount' => 4, 'regionDataSize' => 24, 'dataCodewords' =>  816, 'eccCodewords' => 336, 'rsBlocks' =>  6],
        ['size' => 120, 'regionCount' => 6, 'regionDataSize' => 18, 'dataCodewords' => 1050, 'eccCodewords' => 408, 'rsBlocks' =>  6],
        ['size' => 132, 'regionCount' => 6, 'regionDataSize' => 20, 'dataCodewords' => 1304, 'eccCodewords' => 496, 'rsBlocks' =>  8],
        ['size' => 144, 'regionCount' => 6, 'regionDataSize' => 22, 'dataCodewords' => 1558, 'eccCodewords' => 620, 'rsBlocks' => 10],
    ];

    /**
     * Find the smallest symbol size whose data capacity fits the
     * given codeword count.
     *
     * @return array{
     *   size:int,
     *   regionCount:int,
     *   regionDataSize:int,
     *   dataCodewords:int,
     *   eccCodewords:int,
     *   rsBlocks:int,
     * }
     */
    public static function pickSize(int $codewordCount): array
    {
        foreach (self::SQUARE_SIZES as $entry) {
            if ($entry['dataCodewords'] >= $codewordCount) {
                return $entry;
            }
        }
        throw new \RuntimeException(
            "Data Matrix payload requires {$codewordCount} codewords, which exceeds the 1558-codeword 144×144 maximum.",
        );
    }
}
