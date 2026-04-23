<?php

declare(strict_types=1);

namespace ApprLabs\Pdf\Core\File;

/**
 * Writes variable-width bit fields to a binary buffer.
 *
 * Inverse of the reader's BitReader — used to encode hint table
 * entries for linearized PDF output per ISO 32000-2 Annex F.
 */
final class BitWriter
{
    /** @var list<int> accumulated bytes */
    private array $bytes = [];
    private int $currentByte = 0;
    private int $bitsInCurrent = 0;

    /**
     * Write an unsigned integer of the given bit width.
     *
     * @param int $value Value to write
     * @param int $count Number of bits to write (0–32), MSB first
     */
    public function writeBits(int $value, int $count): void
    {
        if ($count === 0) {
            return;
        }

        for ($i = $count - 1; $i >= 0; $i--) {
            $bit = ($value >> $i) & 1;
            $this->currentByte = ($this->currentByte << 1) | $bit;
            $this->bitsInCurrent++;

            if ($this->bitsInCurrent === 8) {
                $this->bytes[] = $this->currentByte;
                $this->currentByte = 0;
                $this->bitsInCurrent = 0;
            }
        }
    }

    /**
     * Write a 32-bit big-endian unsigned integer (for hint table headers).
     */
    public function writeUint32(int $value): void
    {
        $this->writeBits(($value >> 24) & 0xFF, 8);
        $this->writeBits(($value >> 16) & 0xFF, 8);
        $this->writeBits(($value >> 8) & 0xFF, 8);
        $this->writeBits($value & 0xFF, 8);
    }

    /** Pad remaining bits in the current byte with zeros and advance. */
    public function alignToByte(): void
    {
        if ($this->bitsInCurrent > 0) {
            $this->currentByte <<= (8 - $this->bitsInCurrent);
            $this->bytes[] = $this->currentByte;
            $this->currentByte = 0;
            $this->bitsInCurrent = 0;
        }
    }

    /** Return the accumulated binary data as a string. */
    public function getData(): string
    {
        // Flush any partial byte
        $copy = clone $this;
        $copy->alignToByte();
        $result = '';
        foreach ($copy->bytes as $byte) {
            $result .= chr($byte);
        }
        return $result;
    }

    /** Current position in bits. */
    public function getBitPosition(): int
    {
        return (count($this->bytes) * 8) + $this->bitsInCurrent;
    }
}
