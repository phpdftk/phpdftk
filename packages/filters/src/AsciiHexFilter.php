<?php declare(strict_types=1);
namespace Phpdftk\Filters;

final class AsciiHexFilter implements FilterInterface {
    public function encode(string $data): string {
        return bin2hex($data) . '>';
    }

    public function decode(string $data): string {
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
