<?php

declare(strict_types=1);

namespace Phpdftk\Filters;

/**
 * LZW filter — decode/encode per ISO 32000-2 §7.4.4.2.
 *
 * PDF LZW uses MSB-first bit packing, variable code widths from
 * 9 to 12 bits, clear code = 256, EOD code = 257.
 *
 * The "early change" convention is used: code width increases
 * immediately after the entry that causes nextCode to reach 2^codeSize,
 * so the NEXT code read/written uses the wider width.
 */
final class LzwFilter implements FilterInterface
{
    private const CLEAR_CODE = 256;
    private const EOD_CODE = 257;
    private const FIRST_CODE = 258;

    /**
     * @param int $earlyChange When 1 (default), code-size transition uses
     *                         "early change" convention per PDF spec. When 0,
     *                         the transition happens one code later.
     */
    public function __construct(
        private readonly int $earlyChange = 1,
    ) {}

    public function decode(string $data): string
    {
        $reader = new LzwBitReader($data);
        $codeSize = 9;
        $result = '';

        // Initialize table
        $table = [];
        for ($i = 0; $i < 256; $i++) {
            $table[$i] = chr($i);
        }
        $nextCode = self::FIRST_CODE;
        $prevEntry = null;

        while (true) {
            $code = $reader->read($codeSize);
            if ($code === null) {
                break;
            }

            if ($code === self::EOD_CODE) {
                break;
            }

            if ($code === self::CLEAR_CODE) {
                $table = [];
                for ($i = 0; $i < 256; $i++) {
                    $table[$i] = chr($i);
                }
                $nextCode = self::FIRST_CODE;
                $codeSize = 9;
                $prevEntry = null;
                continue;
            }

            if ($prevEntry === null) {
                // First code after clear — no table entry added
                if (!isset($table[$code])) {
                    break;
                }
                $entry = $table[$code];
                $result .= $entry;
                $prevEntry = $entry;

                // Even though we don't add an entry, advance nextCode
                // to stay synchronized with the encoder (which added an
                // entry for the pair that PRODUCED this code).
                // Actually, the encoder hasn't added anything yet for the
                // first code — it just set $w. So no advancement needed.
                continue;
            }

            if (isset($table[$code])) {
                $entry = $table[$code];
            } else {
                // KwKwK case
                $entry = $prevEntry . $prevEntry[0];
            }

            $result .= $entry;

            // Add new entry
            if ($nextCode < 4096) {
                $table[$nextCode] = $prevEntry . $entry[0];
                $nextCode++;
            }

            // Code-size transition. When earlyChange=1 (PDF default), the
            // transition is anticipated one step early. When earlyChange=0,
            // the transition happens when nextCode exceeds 2^codeSize.
            if ($this->earlyChange === 1) {
                if (($nextCode + 1) >= (1 << $codeSize) && $codeSize < 12) {
                    $codeSize++;
                }
            } else {
                if ($nextCode > (1 << $codeSize) && $codeSize < 12) {
                    $codeSize++;
                }
            }

            $prevEntry = $entry;
        }

        return $result;
    }

    public function encode(string $data): string
    {
        $writer = new LzwBitWriter();
        $codeSize = 9;

        // Initialize table
        $table = [];
        for ($i = 0; $i < 256; $i++) {
            $table[chr($i)] = $i;
        }
        $nextCode = self::FIRST_CODE;
        $len = strlen($data);

        // Emit clear code
        $writer->write(self::CLEAR_CODE, $codeSize);

        if ($len === 0) {
            $writer->write(self::EOD_CODE, $codeSize);
            return $writer->finish();
        }

        $w = $data[0];

        for ($i = 1; $i < $len; $i++) {
            $c = $data[$i];
            $wc = $w . $c;

            if (isset($table[$wc])) {
                $w = $wc;
            } else {
                // Emit code for $w
                $writer->write($table[$w], $codeSize);

                // Add $wc to table
                if ($nextCode < 4096) {
                    $table[$wc] = $nextCode;
                    $nextCode++;

                    // Code-size transition — must match decoder timing
                    if ($this->earlyChange === 1) {
                        if ($nextCode >= (1 << $codeSize) && $codeSize < 12) {
                            $codeSize++;
                        }
                    } else {
                        if ($nextCode > (1 << $codeSize) && $codeSize < 12) {
                            $codeSize++;
                        }
                    }
                }

                $w = $c;
            }
        }

        // Emit code for remaining $w
        $writer->write($table[$w], $codeSize);

        // Emit EOD
        $writer->write(self::EOD_CODE, $codeSize);

        return $writer->finish();
    }
}

/**
 * @internal MSB-first bit reader for LZW decode.
 */
final class LzwBitReader
{
    private int $bytePos = 0;
    private int $bitPos = 0;
    private readonly int $len;

    public function __construct(private readonly string $data)
    {
        $this->len = strlen($data);
    }

    public function read(int $bits): ?int
    {
        $result = 0;
        for ($i = 0; $i < $bits; $i++) {
            if ($this->bytePos >= $this->len) {
                return null;
            }
            $byte = ord($this->data[$this->bytePos]);
            $bit = ($byte >> (7 - $this->bitPos)) & 1;
            $result = ($result << 1) | $bit;

            $this->bitPos++;
            if ($this->bitPos >= 8) {
                $this->bitPos = 0;
                $this->bytePos++;
            }
        }
        return $result;
    }
}

/**
 * @internal MSB-first bit writer for LZW encode.
 */
final class LzwBitWriter
{
    private string $buffer = '';
    private int $currentByte = 0;
    private int $bitPos = 0;

    public function write(int $code, int $bits): void
    {
        for ($i = $bits - 1; $i >= 0; $i--) {
            $bit = ($code >> $i) & 1;
            $this->currentByte = ($this->currentByte << 1) | $bit;
            $this->bitPos++;

            if ($this->bitPos >= 8) {
                $this->buffer .= chr($this->currentByte);
                $this->currentByte = 0;
                $this->bitPos = 0;
            }
        }
    }

    public function finish(): string
    {
        if ($this->bitPos > 0) {
            // Pad remaining bits with zeros
            $this->currentByte <<= (8 - $this->bitPos);
            $this->buffer .= chr($this->currentByte);
        }
        return $this->buffer;
    }
}
