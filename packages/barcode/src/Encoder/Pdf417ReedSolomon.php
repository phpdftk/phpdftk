<?php

declare(strict_types=1);

namespace Phpdftk\Barcode\Encoder;

/**
 * Reed-Solomon encoder for PDF417 — operates over GF(929).
 *
 * GF(929) is the prime field of integers mod 929 (929 is itself prime, so
 * the field is just modular arithmetic, no extension polynomial needed).
 * The primitive element is 3.
 *
 * For ECC level L the symbol carries 2^(L+1) error correction codewords;
 * the generator polynomial is the product of (x − 3^i) for i = 1..2^(L+1).
 *
 * @internal
 */
final class Pdf417ReedSolomon
{
    public const FIELD_SIZE = 929;
    public const PRIMITIVE = 3;

    /** Number of ECC codewords for each error-correction level 0..8. */
    public const ECC_COUNT = [2, 4, 8, 16, 32, 64, 128, 256, 512];

    /**
     * Encode a list of data codewords with `2^(level+1)` ECC codewords.
     *
     * @param list<int> $data Data codewords in symbol order (length descriptor first).
     * @return list<int> ECC codewords (length 2^(level+1)) in transmission order.
     */
    public static function generate(array $data, int $level): array
    {
        $eccCount = self::ECC_COUNT[$level];
        $generator = self::generatorPoly($eccCount);

        // Polynomial division: remainder of data * x^eccCount divided by generator.
        $ecc = array_fill(0, $eccCount, 0);
        foreach ($data as $codeword) {
            $t = ($codeword + $ecc[0]) % self::FIELD_SIZE;
            for ($i = $eccCount - 1; $i >= 0; $i--) {
                $prev = $i > 0 ? $ecc[$i - 1] : 0;
                $ecc[$i] = ($prev + self::FIELD_SIZE - ($t * $generator[$i]) % self::FIELD_SIZE) % self::FIELD_SIZE;
            }
        }
        // Negate (standard PDF417 RS quirk: ECC codewords are emitted in reverse with sign flip).
        $result = [];
        for ($i = $eccCount - 1; $i >= 0; $i--) {
            $result[] = $ecc[$i] === 0 ? 0 : self::FIELD_SIZE - $ecc[$i];
        }
        return $result;
    }

    /**
     * Build the generator polynomial of degree `eccCount`:
     * g(x) = (x − 3^1)(x − 3^2)…(x − 3^eccCount).
     *
     * Stored as the `eccCount` coefficients of x^0..x^(eccCount-1) — the leading
     * x^eccCount term is implicitly 1 and is not stored.
     *
     * @return list<int>
     */
    private static function generatorPoly(int $eccCount): array
    {
        $coeffs = [1];
        $alpha = 1;
        for ($i = 1; $i <= $eccCount; $i++) {
            $alpha = ($alpha * self::PRIMITIVE) % self::FIELD_SIZE;
            $newCoeffs = array_fill(0, count($coeffs) + 1, 0);
            foreach ($coeffs as $j => $c) {
                $newCoeffs[$j + 1] = ($newCoeffs[$j + 1] + $c) % self::FIELD_SIZE;
                $newCoeffs[$j] = ($newCoeffs[$j] + self::FIELD_SIZE - ($c * $alpha) % self::FIELD_SIZE) % self::FIELD_SIZE;
            }
            $coeffs = $newCoeffs;
        }
        // Drop the leading (x^eccCount) coefficient — it's always 1 — and return the rest.
        array_pop($coeffs);
        return $coeffs;
    }
}
