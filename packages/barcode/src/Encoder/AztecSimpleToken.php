<?php

declare(strict_types=1);

namespace Phpdftk\Barcode\Encoder;

/**
 * Emits a fixed `(value, bitCount)` pair into the bit stream. Used for
 * mode-latch codes, mode-shift codes, and individual character codewords.
 *
 * @internal
 */
final class AztecSimpleToken implements AztecToken
{
    public function __construct(
        private readonly ?AztecToken $previous,
        private readonly int $value,
        private readonly int $bitCount,
    ) {}

    public function previous(): ?AztecToken
    {
        return $this->previous;
    }

    public function appendTo(array &$bits, string $text): void
    {
        for ($i = $this->bitCount - 1; $i >= 0; $i--) {
            $bits[] = ($this->value >> $i) & 1;
        }
    }
}
