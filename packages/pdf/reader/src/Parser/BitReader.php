<?php

declare(strict_types=1);

namespace Phpdftk\Pdf\Reader\Parser;

/**
 * Reads variable-width bit fields from a binary buffer.
 *
 * Used by the hint table parser to decode the bit-packed per-page
 * and shared-object entries defined in ISO 32000-2 Annex F.
 */
final class BitReader
{
    private int $bitPos = 0;

    public function __construct(private readonly string $data)
    {
    }

    /**
     * Read an unsigned integer of the given bit width.
     *
     * @param int $count Number of bits to read (0–32)
     */
    public function readBits(int $count): int
    {
        if ($count === 0) {
            return 0;
        }

        $result = 0;
        for ($i = 0; $i < $count; $i++) {
            $byteIndex = intdiv($this->bitPos, 8);
            $bitIndex = 7 - ($this->bitPos % 8); // MSB first

            if ($byteIndex >= strlen($this->data)) {
                throw new \RuntimeException('BitReader: read past end of data');
            }

            $bit = (ord($this->data[$byteIndex]) >> $bitIndex) & 1;
            $result = ($result << 1) | $bit;
            $this->bitPos++;
        }

        return $result;
    }

    /** Advance to the next byte boundary. */
    public function alignToByte(): void
    {
        $remainder = $this->bitPos % 8;
        if ($remainder !== 0) {
            $this->bitPos += (8 - $remainder);
        }
    }

    /** Current position in bits. */
    public function getBitPosition(): int
    {
        return $this->bitPos;
    }

    /** Current position in bytes (rounded down). */
    public function getBytePosition(): int
    {
        return intdiv($this->bitPos, 8);
    }
}
