<?php

declare(strict_types=1);

namespace Phpdftk\FontParser\Tests;

use Phpdftk\FontParser\Woff2Parser;
use PHPUnit\Framework\TestCase;

/**
 * WOFF2 §4.1 — a table's `origLength` / `transformLength` are
 * UIntBase128 values read straight out of the file, so a malformed font
 * can declare a table far larger than any real one. The reconstructed
 * sfnt cannot exceed the header's `totalSfntSize`, so that bounds them.
 *
 * Without the bound a declared 0xFFFFFFFF reaches `str_pad()` as a 4 GB
 * allocation and kills the process on untrusted input. The WOFF2
 * conformance suite ships exactly such files and requires them to be
 * REJECTED — an abort is not a rejection.
 */
final class Woff2BoundsTest extends TestCase
{
    /** Build a WOFF2 whose single table declares an absurd origLength. */
    private function woff2WithOversizedTable(int $declaredOrigLength): string
    {
        $payload = brotli_compress(str_repeat("\x00", 64));

        // UIntBase128, big-endian 7-bit groups, high bit set on all but the last.
        $base128 = static function (int $n): string {
            if ($n === 0) {
                return "\x00";
            }
            $groups = [];
            while ($n > 0) {
                $groups[] = $n & 0x7F;
                $n >>= 7;
            }
            $groups = array_reverse($groups);
            $out = '';
            foreach ($groups as $i => $g) {
                $out .= chr($i === count($groups) - 1 ? $g : ($g | 0x80));
            }
            return $out;
        };

        $header = pack('N', 0x774F4632)      // wOF2
            . pack('N', 0x00010000)          // flavor
            . pack('N', 48 + 16 + strlen($payload))
            . pack('n', 1)                   // numTables
            . pack('n', 0)                   // reserved
            . pack('N', 1024)                // totalSfntSize — the bound
            . pack('N', strlen($payload))    // totalCompressedSize
            . pack('n', 1) . pack('n', 0)    // version
            . pack('N', 0) . pack('N', 0) . pack('N', 0)  // meta
            . pack('N', 0) . pack('N', 0);   // priv

        // Table directory entry: flags byte 0x3F = arbitrary tag follows.
        $entry = "\x3F" . 'cmap' . $base128($declaredOrigLength);

        return $header . $entry . $payload;
    }

    public function testATableLargerThanTheReconstructedFontIsRejected(): void
    {
        if (!function_exists('brotli_uncompress')) {
            self::markTestSkipped('ext-brotli not installed');
        }

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/exceeding the \d+-byte reconstructed font/');

        // 0xFFFFFFFF against a declared 1024-byte sfnt.
        Woff2Parser::decompressBytes($this->woff2WithOversizedTable(0xFFFFFFFF));
    }

    public function testRejectionHappensWithoutExhaustingMemory(): void
    {
        if (!function_exists('brotli_uncompress')) {
            self::markTestSkipped('ext-brotli not installed');
        }

        $before = memory_get_usage(true);
        try {
            Woff2Parser::decompressBytes($this->woff2WithOversizedTable(0xFFFFFFFF));
        } catch (\RuntimeException) {
            // expected
        }
        $grew = memory_get_usage(true) - $before;
        // A 4 GB allocation attempt is the bug; anything under a few MB
        // proves we rejected on the declared length, not by trying it.
        self::assertLessThan(8 * 1024 * 1024, $grew);
    }

    public function testIsWoff2RecognisesTheSignature(): void
    {
        self::assertTrue(Woff2Parser::isWoff2("wOF2" . str_repeat("\x00", 44)));
        self::assertFalse(Woff2Parser::isWoff2("wOFF" . str_repeat("\x00", 44)));
        self::assertFalse(Woff2Parser::isWoff2("\x00\x01\x00\x00" . str_repeat("\x00", 44)));
    }
}
