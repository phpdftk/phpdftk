<?php

declare(strict_types=1);

namespace Phpdftk\ImageMetadata\Tests;

use PHPUnit\Framework\TestCase;
use Phpdftk\ImageMetadata\PngParser;

/**
 * Sub-byte indexed PNG (color type 3, bit depths 1/2/4) decode.
 * Browsers handle these for legacy art (1-bit b/w swatches in WPT, 4-bit
 * UI icons); without the unpacking pass the IDAT bytes get interpreted as
 * raw RGB samples and the embedded XObject looks like noise.
 */
class PngParserSubByteIndexedTest extends TestCase
{
    public function testDecodes1BitIndexedPng(): void
    {
        // 2x2 image, palette [#ff7f00 (orange), #000000], every pixel = index 1.
        // 1-bit packed row: 0b1100_0000 → 0xC0 (both pixels select index 1; trailing
        // pad bits ignored). With width 2, stride is 1 byte/row.
        $palette = "\xFF\x7F\x00\x00\x00\x00";
        $raw = "\x00\xC0" . "\x00\xC0"; // filter=0, packed_row, x2
        $png = $this->buildPng(width: 2, height: 2, bitDepth: 1, palette: $palette, raw: $raw);

        $decoded = PngParser::decodeIndexedPng($png);

        self::assertNotNull($decoded);
        self::assertSame(2, $decoded['width']);
        self::assertSame(2, $decoded['height']);
        self::assertNull($decoded['alpha']);
        // 4 pixels × 3 bytes = 12 bytes, all zero (palette[1]).
        self::assertSame(str_repeat("\x00\x00\x00", 4), $decoded['colour']);
    }

    public function testDecodes1BitIndexedPngWithMixedPixels(): void
    {
        // 4x1 image, palette [white, orange]. Pixel indices: 0, 1, 0, 1 →
        // packed MSB-first as 0b0101_0000 = 0x50.
        $palette = "\xFF\xFF\xFF\xFF\x7F\x00";
        $raw = "\x00\x50";
        $png = $this->buildPng(width: 4, height: 1, bitDepth: 1, palette: $palette, raw: $raw);

        $decoded = PngParser::decodeIndexedPng($png);

        self::assertNotNull($decoded);
        self::assertSame("\xFF\xFF\xFF" . "\xFF\x7F\x00" . "\xFF\xFF\xFF" . "\xFF\x7F\x00", $decoded['colour']);
    }

    public function testDecodes4BitIndexedPng(): void
    {
        // 2x1 image, palette [white, red, green, ... eight entries].
        // 4-bit indices: 0x01, 0x02 → packed as 0x12 (one byte).
        $palette = "\xFF\xFF\xFF\xFF\x00\x00\x00\xFF\x00" . str_repeat("\x00", 15);
        $raw = "\x00\x12";
        $png = $this->buildPng(width: 2, height: 1, bitDepth: 4, palette: $palette, raw: $raw);

        $decoded = PngParser::decodeIndexedPng($png);

        self::assertNotNull($decoded);
        self::assertSame("\xFF\x00\x00" . "\x00\xFF\x00", $decoded['colour']);
    }

    public function testRejectsUnsupportedBitDepth(): void
    {
        // 16-bit indexed isn't valid per spec (max 8), but assert null on a
        // contrived depth so the guard's semantics are pinned.
        $palette = "\xFF\xFF\xFF";
        $raw = "\x00\x00\x00";
        $png = $this->buildPng(width: 1, height: 1, bitDepth: 16, palette: $palette, raw: $raw);

        self::assertNull(PngParser::decodeIndexedPng($png));
    }

    /**
     * Assemble a minimal indexed PNG: signature + IHDR + PLTE + IDAT + IEND.
     * `$raw` is the per-row (filter byte + packed bytes) sequence the IDAT
     * carries after zlib-deflating; we compress it here so the parser sees
     * a real PNG stream.
     */
    private function buildPng(int $width, int $height, int $bitDepth, string $palette, string $raw): string
    {
        $signature = "\x89PNG\r\n\x1A\n";
        $ihdrPayload = pack('N2', $width, $height) . chr($bitDepth) . chr(3) . "\x00\x00\x00";
        $idatPayload = gzcompress($raw);
        return $signature
            . $this->chunk('IHDR', $ihdrPayload)
            . $this->chunk('PLTE', $palette)
            . $this->chunk('IDAT', $idatPayload)
            . $this->chunk('IEND', '');
    }

    private function chunk(string $type, string $payload): string
    {
        $crc = crc32($type . $payload);
        return pack('N', strlen($payload)) . $type . $payload . pack('N', $crc);
    }
}
