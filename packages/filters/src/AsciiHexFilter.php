<?php

declare(strict_types=1);

namespace Phpdftk\Filters;

/**
 * ASCIIHexDecode — hex encoding for binary data (ISO 32000-2 §7.4.2).
 *
 * Simplest PDF filter: each byte becomes two hex digits. Doubles the
 * size but produces fully human-readable output, useful for debugging.
 * Terminated by '>'.
 */
final class AsciiHexFilter implements FilterInterface
{
    public function encode(string $data): string
    {
        return bin2hex($data) . '>';
    }

    public function decode(string $data): string
    {
        // Strip whitespace
        $data = preg_replace('/\s+/', '', $data);
        // Strip trailing >
        $data = rtrim($data, '>');
        if (!ctype_xdigit($data) && $data !== '') {
            throw new \RuntimeException('AsciiHexFilter: invalid hex data');
        }
        // If odd number of hex chars, pad with trailing zero
        if (strlen($data) % 2 !== 0) {
            $data .= '0';
        }
        return hex2bin($data);
    }
}
