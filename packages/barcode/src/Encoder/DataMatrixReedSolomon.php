<?php

declare(strict_types=1);

namespace Phpdftk\Barcode\Encoder;

/**
 * Reed-Solomon error correction over GF(256) for Data Matrix.
 *
 * Uses primitive polynomial 0x12D (x^8 + x^5 + x^3 + x^2 + 1) per
 * ISO/IEC 16022 Annex E — *different* from QR (which uses 0x11D), so
 * this can't share `QrReedSolomon`'s tables.
 *
 * @internal
 */
final class DataMatrixReedSolomon
{
    /** @var array<int, int> */
    private static array $log = [];

    /** @var array<int, int> */
    private static array $antilog = [];

    /** @var list<int> Generator polynomial in descending power, leading coeff = 1. */
    private array $generator;

    public function __construct(int $eccCodewords)
    {
        self::initTables();
        $this->generator = self::buildGenerator($eccCodewords);
    }

    /**
     * Compute the ECC bytes for `$data` and return them in standard
     * order (highest-degree coefficient first).
     *
     * @param list<int> $data
     * @return list<int>
     */
    public function encode(array $data): array
    {
        $eccLen = count($this->generator) - 1;
        $buffer = array_merge($data, array_fill(0, $eccLen, 0));
        for ($i = 0; $i < count($data); $i++) {
            $factor = $buffer[$i];
            if ($factor === 0) {
                continue;
            }
            for ($j = 0; $j < count($this->generator); $j++) {
                $buffer[$i + $j] ^= self::gfMul($this->generator[$j], $factor);
            }
        }
        return array_slice($buffer, count($data));
    }

    private static function initTables(): void
    {
        if (self::$antilog !== []) {
            return;
        }
        $x = 1;
        for ($i = 0; $i < 255; $i++) {
            self::$antilog[$i] = $x;
            self::$log[$x] = $i;
            $x <<= 1;
            if ($x & 0x100) {
                $x ^= 0x12D;
            }
        }
        self::$antilog[255] = self::$antilog[0];
    }

    private static function gfMul(int $a, int $b): int
    {
        if ($a === 0 || $b === 0) {
            return 0;
        }
        return self::$antilog[(self::$log[$a] + self::$log[$b]) % 255];
    }

    /** @return list<int> */
    private static function buildGenerator(int $eccLen): array
    {
        // Generator = product of (x - α^i) for i = 1 … eccLen
        // Data Matrix uses α^1 as the first root (not α^0 like QR).
        $poly = [1];
        for ($i = 1; $i <= $eccLen; $i++) {
            $alpha = self::$antilog[$i];
            $newPoly = array_fill(0, count($poly) + 1, 0);
            for ($j = 0; $j < count($poly); $j++) {
                $newPoly[$j] ^= $poly[$j];
                $newPoly[$j + 1] ^= self::gfMul($poly[$j], $alpha);
            }
            $poly = $newPoly;
        }
        return $poly;
    }
}
