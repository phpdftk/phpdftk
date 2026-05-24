<?php

declare(strict_types=1);

namespace Phpdftk\Barcode\Encoder;

/**
 * Linked-list node in the Aztec encoder's token chain. Each token knows its
 * predecessor (or null for the first emitted) and knows how to append its bits
 * to a flat output stream given the original text (needed for Binary Shift
 * tokens that reference back into the input).
 *
 * @internal
 */
interface AztecToken
{
    public function previous(): ?self;

    /**
     * @param list<int> &$bits Output bit stream — append MSB first.
     */
    public function appendTo(array &$bits, string $text): void;
}
