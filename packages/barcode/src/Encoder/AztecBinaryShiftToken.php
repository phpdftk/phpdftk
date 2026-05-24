<?php

declare(strict_types=1);

namespace Phpdftk\Barcode\Encoder;

/**
 * Emits a Binary Shift (B/S) block referring back into the input text:
 *
 *   - 1 to 31 bytes:   `<5-bit B/S=31><5-bit N><8N>`
 *   - 32 to 62 bytes:  two back-to-back B/S blocks of 31 and (N−31) bytes
 *   - 63+ bytes:       `<5-bit B/S=31><5-bit 0><11-bit (N-31)><8N>`
 *
 * The split between the 31-byte short form and the two-block form is decided
 * at emit time based on the final `binaryShiftByteCount`.
 *
 * @internal
 */
final class AztecBinaryShiftToken implements AztecToken
{
    public function __construct(
        private readonly ?AztecToken $previous,
        private readonly int $start,
        private readonly int $byteCount,
    ) {}

    public function previous(): ?AztecToken
    {
        return $this->previous;
    }

    public function appendTo(array &$bits, string $text): void
    {
        $bsbc = $this->byteCount;
        for ($i = 0; $i < $bsbc; $i++) {
            if ($i === 0 || ($i === 31 && $bsbc <= 62)) {
                // B/S entry code (31 in 5-bit Upper mode)
                self::push($bits, 31, 5);
                if ($bsbc > 62) {
                    // Extended length form: 5-bit 0 + 11-bit (count - 31).
                    self::push($bits, 0, 5);
                    self::push($bits, $bsbc - 31, 11);
                } elseif ($i === 0) {
                    // Short form: 5-bit length, 1 ≤ N ≤ 31. (For N=32..62
                    // we emit 31 here and start the second header at i=31.)
                    self::push($bits, min($bsbc, 31), 5);
                } else {
                    // Second block header, i == 31, 32 ≤ N ≤ 62.
                    self::push($bits, $bsbc - 31, 5);
                }
            }
            self::push($bits, ord($text[$this->start + $i]), 8);
        }
    }

    /**
     * @param list<int> &$bits
     */
    private static function push(array &$bits, int $value, int $width): void
    {
        for ($i = $width - 1; $i >= 0; $i--) {
            $bits[] = ($value >> $i) & 1;
        }
    }
}
