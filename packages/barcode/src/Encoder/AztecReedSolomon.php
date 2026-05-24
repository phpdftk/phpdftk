<?php

declare(strict_types=1);

namespace Phpdftk\Barcode\Encoder;

/**
 * Reed-Solomon encoder for Aztec — operates over a configurable GF(2^k)
 * field via {@see AztecGaloisField}. Used by both the data block (GF(64)
 * or GF(256)) and the mode message (GF(16)).
 *
 * Given `n` data codewords and an ECC count, produces `eccCount`
 * error-correction codewords such that the concatenation is a multiple of
 * the generator polynomial g(x) = (x - α^1)(x - α^2)…(x - α^eccCount).
 *
 * @internal
 */
final class AztecReedSolomon
{
    private readonly AztecGaloisField $field;
    /** @var list<int> Generator polynomial coefficients, low to high (excluding leading 1). */
    private readonly array $generator;

    public function __construct(AztecGaloisField $field, int $eccCount)
    {
        $this->field = $field;
        $this->generator = $this->buildGenerator($eccCount);
    }

    /**
     * @param list<int> $data
     * @return list<int> ECC codewords, length = eccCount, in transmission order
     *                  (highest power of x first).
     */
    public function encode(array $data): array
    {
        $eccCount = count($this->generator);
        $remainder = array_fill(0, $eccCount, 0);
        foreach ($data as $codeword) {
            $factor = $codeword ^ $remainder[0];
            // Shift remainder left and add factor * generator
            for ($i = 0; $i < $eccCount - 1; $i++) {
                $remainder[$i] = $remainder[$i + 1] ^ $this->field->multiply($factor, $this->generator[$eccCount - 1 - $i]);
            }
            $remainder[$eccCount - 1] = $this->field->multiply($factor, $this->generator[0]);
        }
        return $remainder;
    }

    /**
     * @return list<int> g(x) coefficients low → high, excluding the leading 1.
     */
    private function buildGenerator(int $eccCount): array
    {
        // Start with g(x) = 1
        $coeffs = [1];
        for ($i = 1; $i <= $eccCount; $i++) {
            // Multiply g(x) by (x - α^i)
            $alpha = $this->field->antilog[$i % ($this->field->size - 1)];
            $newCoeffs = array_fill(0, count($coeffs) + 1, 0);
            foreach ($coeffs as $j => $c) {
                $newCoeffs[$j + 1] ^= $c;
                $newCoeffs[$j] ^= $this->field->multiply($c, $alpha);
            }
            $coeffs = $newCoeffs;
        }
        // Drop the leading (x^eccCount) coefficient — it's always 1.
        array_pop($coeffs);
        return $coeffs;
    }
}
