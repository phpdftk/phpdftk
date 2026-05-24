<?php

declare(strict_types=1);

namespace Phpdftk\Barcode\Encoder;

/**
 * Mutable bit-accumulator used by the QR encoder to compose the
 * payload bitstream. Bits are pushed MSB-first into 8-bit codewords.
 *
 * @internal
 */
final class QrBitStream
{
    /** @var list<int> */
    private array $bytes = [];

    private int $current = 0;

    private int $currentBits = 0;

    public function push(int $value, int $bitCount): void
    {
        for ($i = $bitCount - 1; $i >= 0; $i--) {
            $bit = ($value >> $i) & 1;
            $this->current = ($this->current << 1) | $bit;
            $this->currentBits++;
            if ($this->currentBits === 8) {
                $this->bytes[] = $this->current;
                $this->current = 0;
                $this->currentBits = 0;
            }
        }
    }

    public function size(): int
    {
        return count($this->bytes) * 8 + $this->currentBits;
    }

    /** @return list<int> */
    public function toBytes(): array
    {
        if ($this->currentBits !== 0) {
            // Should never happen in QR — the caller pads to a byte
            // boundary before reading.
            throw new \LogicException(
                "QrBitStream::toBytes() called with {$this->currentBits} unfinished bits.",
            );
        }
        return $this->bytes;
    }
}
