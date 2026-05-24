<?php

declare(strict_types=1);

namespace Phpdftk\Barcode\Encoder;

/**
 * Galois field GF(2^k) helper for Aztec — log/antilog tables for fast
 * multiplication, parameterised by primitive polynomial.
 *
 * Aztec uses three concrete fields depending on the word size:
 *   - 4-bit codewords (mode message): GF(16)  with polynomial 0x13.
 *   - 6-bit codewords (Compact L=1–2, Full L=1–2): GF(64)  with polynomial 0x43.
 *   - 8-bit codewords (Compact L=3–4, Full L=3–8): GF(256) with polynomial 0x12D.
 *
 * Each instance precomputes log[α^i] = i and antilog[i] = α^i for fast
 * multiplication: a·b = antilog[(log[a] + log[b]) mod (size − 1)] when both
 * a and b are non-zero, 0 otherwise.
 *
 * @internal
 */
final class AztecGaloisField
{
    public const GF_16 = ['size' => 16, 'poly' => 0x13];
    public const GF_64 = ['size' => 64, 'poly' => 0x43];
    public const GF_256 = ['size' => 256, 'poly' => 0x12D];
    public const GF_1024 = ['size' => 1024, 'poly' => 0x409];
    public const GF_4096 = ['size' => 4096, 'poly' => 0x1069];

    /**
     * Pick the GF configuration for a given Aztec word size.
     *
     * @return array{size:int, poly:int}
     */
    public static function forWordSize(int $wordSize): array
    {
        return match ($wordSize) {
            4 => self::GF_16,
            6 => self::GF_64,
            8 => self::GF_256,
            10 => self::GF_1024,
            12 => self::GF_4096,
            default => throw new \InvalidArgumentException("Unsupported Aztec word size: $wordSize"),
        };
    }

    public readonly int $size;
    /** @var list<int> antilog[i] = α^i (length = size). */
    public readonly array $antilog;
    /** @var list<int> log[v] = i such that α^i = v (length = size, log[0] is unused). */
    public readonly array $log;

    public function __construct(int $size, int $primitivePolynomial)
    {
        $this->size = $size;
        $antilog = array_fill(0, $size, 0);
        $log = array_fill(0, $size, 0);
        $x = 1;
        for ($i = 0; $i < $size - 1; $i++) {
            $antilog[$i] = $x;
            $log[$x] = $i;
            $x <<= 1;
            if ($x >= $size) {
                $x ^= $primitivePolynomial;
            }
        }
        // antilog[size-1] = antilog[0] (α^(size-1) = α^0 = 1) — pad so multiply
        // can use modular index without an extra branch.
        $antilog[$size - 1] = $antilog[0];
        $this->antilog = $antilog;
        $this->log = $log;
    }

    public function multiply(int $a, int $b): int
    {
        if ($a === 0 || $b === 0) {
            return 0;
        }
        return $this->antilog[($this->log[$a] + $this->log[$b]) % ($this->size - 1)];
    }

    /** XOR is addition and subtraction in GF(2^k). */
    public function add(int $a, int $b): int
    {
        return $a ^ $b;
    }

    /**
     * @param array{size:int, poly:int} $config
     */
    public static function from(array $config): self
    {
        return new self($config['size'], $config['poly']);
    }
}
