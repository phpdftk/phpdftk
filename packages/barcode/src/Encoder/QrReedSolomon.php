<?php

declare(strict_types=1);

namespace Phpdftk\Barcode\Encoder;

/**
 * Reed-Solomon error-correction over GF(256) for QR codes.
 *
 * Uses the QR-specific primitive polynomial x^8 + x^4 + x^3 + x^2 + 1
 * (0x11D) and the standard generator polynomial built by successive
 * multiplication with `(x - α^i)`.
 *
 * @internal
 */
final class QrReedSolomon
{
    /** @var array<int, int> log[i] = power of α giving i (i != 0). */
    private static array $log = [];

    /** @var array<int, int> antilog[i] = α^i mod 0x11D. */
    private static array $antilog = [];

    /** Generator polynomial for this RS configuration. */
    private array $generator;

    public function __construct(int $eccCodewords)
    {
        self::initTables();
        $this->generator = self::buildGenerator($eccCodewords);
    }

    /**
     * Compute the ECC bytes for `$data`.
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
                $x ^= 0x11D;
            }
        }
        // antilog[255] wraps; mirror antilog[0] for convenience.
        self::$antilog[255] = self::$antilog[0];
    }

    private static function gfMul(int $a, int $b): int
    {
        if ($a === 0 || $b === 0) {
            return 0;
        }
        return self::$antilog[(self::$log[$a] + self::$log[$b]) % 255];
    }

    /**
     * Build the RS generator polynomial: product of `(x - α^i)` for
     * `i = 0 … eccLen-1`. Coefficients are returned in descending
     * power, with the leading 1 at index 0.
     *
     * @return list<int>
     */
    private static function buildGenerator(int $eccLen): array
    {
        $poly = [1];
        for ($i = 0; $i < $eccLen; $i++) {
            // Multiply $poly by (x + α^i).
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
