<?php

declare(strict_types=1);

namespace Phpdftk\Barcode\Encoder;

/**
 * Immutable encoder state for the Aztec high-level state search.
 *
 * Each state captures:
 *   - the current mode (Upper/Lower/Digit/Mixed/Punct),
 *   - a singly-linked list of emitted tokens (most recent first),
 *   - how many bytes have been accumulated in a pending Binary Shift block,
 *   - the total bit count of the encoded stream so far (including the cost
 *     of finalising any pending Binary Shift, so two states are always
 *     comparable by raw `bitCount`).
 *
 * All mutation operations return a new state.
 *
 * @internal
 */
final class AztecState
{
    public function __construct(
        public readonly ?AztecToken $token,
        public readonly int $mode,
        public readonly int $binaryShiftByteCount,
        public readonly int $bitCount,
    ) {}

    public static function initial(): self
    {
        return new self(null, AztecHighLevelEncoder::MODE_UPPER, 0, 0);
    }

    /**
     * Latch to (possibly the same) mode and emit `$value` in that mode's
     * bit width. Latch transitions are looked up in `LATCH_TABLE` and may
     * span multiple intermediate modes (e.g. PUNCT → UPPER → DIGIT).
     */
    public function latchAndAppend(int $mode, int $value): self
    {
        $token = $this->token;
        $bitCount = $this->bitCount;
        if ($mode !== $this->mode) {
            $latch = AztecHighLevelEncoder::LATCH_TABLE[$this->mode][$mode];
            $latchValue = $latch & 0xFFFF;
            $latchBits = $latch >> 16;
            $token = new AztecSimpleToken($token, $latchValue, $latchBits);
            $bitCount += $latchBits;
        }
        $width = AztecHighLevelEncoder::modeBitCount($mode);
        $token = new AztecSimpleToken($token, $value, $width);
        return new self($token, $mode, 0, $bitCount + $width);
    }

    /**
     * Shift to another mode for exactly one character, then implicitly return
     * to the original mode. Costs `currentModeBitCount + 5` bits.
     */
    public function shiftAndAppend(int $mode, int $value): self
    {
        $width = AztecHighLevelEncoder::modeBitCount($this->mode);
        $shiftCode = AztecHighLevelEncoder::shift($this->mode, $mode);
        $token = new AztecSimpleToken($this->token, $shiftCode, $width);
        $token = new AztecSimpleToken($token, $value, 5);
        return new self($token, $this->mode, 0, $this->bitCount + $width + 5);
    }

    /**
     * Append one byte to a pending Binary Shift block.
     *
     * Cost depends on which byte we are within the block:
     *   - byte 0:       18 bits (5-bit B/S entry + 5-bit length + 8-bit byte)
     *   - bytes 1..30:   8 bits each (just the byte)
     *   - byte 31:      18 bits (start of a second 1–31-byte chunk)
     *   - byte 62:       9 bits (transition from two-chunk to extended 16-bit
     *                   length: previous 10 bits of headers become 5+16=21)
     *   - else:          8 bits each.
     *
     * Caller may not be in PUNCT/DIGIT (those can't enter B/S directly) —
     * we auto-latch to UPPER first if so.
     */
    public function addBinaryShiftChar(int $index): self
    {
        $token = $this->token;
        $mode = $this->mode;
        $bitCount = $this->bitCount;
        if ($mode === AztecHighLevelEncoder::MODE_PUNCT || $mode === AztecHighLevelEncoder::MODE_DIGIT) {
            $latch = AztecHighLevelEncoder::LATCH_TABLE[$mode][AztecHighLevelEncoder::MODE_UPPER];
            $token = new AztecSimpleToken($token, $latch & 0xFFFF, $latch >> 16);
            $bitCount += $latch >> 16;
            $mode = AztecHighLevelEncoder::MODE_UPPER;
        }
        $delta = match (true) {
            $this->binaryShiftByteCount === 0 => 18,
            $this->binaryShiftByteCount === 31 => 18,
            $this->binaryShiftByteCount === 62 => 9,
            default => 8,
        };
        $result = new self($token, $mode, $this->binaryShiftByteCount + 1, $bitCount + $delta);
        if ($result->binaryShiftByteCount === 2078) {
            // Hit the extended-length cap; flush the block now.
            $result = $result->endBinaryShift($index + 1);
        }
        return $result;
    }

    /**
     * Finalise a pending Binary Shift block by emitting the BinaryShiftToken
     * (which renders 5-bit / 16-bit length headers as needed). No-op when
     * `binaryShiftByteCount == 0`.
     */
    public function endBinaryShift(int $index): self
    {
        if ($this->binaryShiftByteCount === 0) {
            return $this;
        }
        $token = new AztecBinaryShiftToken($this->token, $index - $this->binaryShiftByteCount, $this->binaryShiftByteCount);
        return new self($token, $this->mode, 0, $this->bitCount);
    }

    /**
     * Dominance check: would `$this` produce a state at least as cheap as
     * `$other` after both finished encoding? If yes, `$other` can be pruned.
     */
    public function isBetterThanOrEqualTo(self $other): bool
    {
        $latch = AztecHighLevelEncoder::LATCH_TABLE[$this->mode][$other->mode] >> 16;
        $newBitCount = $this->bitCount + $latch;
        if ($this->binaryShiftByteCount < $other->binaryShiftByteCount) {
            $newBitCount += self::binaryShiftCost($other->binaryShiftByteCount)
                - self::binaryShiftCost($this->binaryShiftByteCount);
        } elseif ($this->binaryShiftByteCount > $other->binaryShiftByteCount && $other->binaryShiftByteCount > 0) {
            $newBitCount += 10;
        }
        return $newBitCount <= $other->bitCount;
    }

    /**
     * Materialise this state's token list into a bit stream.
     *
     * @return list<int>
     */
    public function toBits(string $text): array
    {
        // Walk back through the chain (newest first) and reverse to get
        // build order (oldest first). Then emit each token's bits.
        $tokens = [];
        for ($t = $this->endBinaryShift(strlen($text))->token; $t !== null; $t = $t->previous()) {
            $tokens[] = $t;
        }
        $bits = [];
        for ($i = count($tokens) - 1; $i >= 0; $i--) {
            $tokens[$i]->appendTo($bits, $text);
        }
        return $bits;
    }

    /** Cost (in bits) of all current B/S overhead for the given pending count. */
    private static function binaryShiftCost(int $byteCount): int
    {
        return match (true) {
            $byteCount > 62 => 21,
            $byteCount > 31 => 20,
            $byteCount > 0 => 10,
            default => 0,
        };
    }
}
