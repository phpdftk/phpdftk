<?php

declare(strict_types=1);

namespace Phpdftk\FontParser\Tests;

use Phpdftk\FontParser\Woff2Parser;
use PHPUnit\Framework\TestCase;

class Woff2ParserTest extends TestCase
{
    public function testIsWoff2DetectsWoff2Signature(): void
    {
        // 'wOF2' signature = 0x774F4632
        $woff2Header = pack('N', 0x774F4632) . str_repeat("\x00", 44);
        $this->assertTrue(Woff2Parser::isWoff2($woff2Header));

        // WOFF 1.0 signature should not match
        $woff1Header = pack('N', 0x774F4646) . str_repeat("\x00", 44);
        $this->assertFalse(Woff2Parser::isWoff2($woff1Header));

        // TrueType signature should not match
        $ttfHeader = pack('N', 0x00010000) . str_repeat("\x00", 44);
        $this->assertFalse(Woff2Parser::isWoff2($ttfHeader));
    }

    public function testDetectFlavorTrueType(): void
    {
        $data = pack('N', 0x774F4632) . pack('N', 0x00010000);
        $this->assertSame('truetype', Woff2Parser::detectFlavor($data));
    }

    public function testDetectFlavorOpenType(): void
    {
        $data = pack('N', 0x774F4632) . pack('N', 0x4F54544F);
        $this->assertSame('opentype', Woff2Parser::detectFlavor($data));
    }

    public function testDetectFlavorTooShort(): void
    {
        $this->assertSame('unknown', Woff2Parser::detectFlavor('abc'));
    }

    public function testDecompressBytesTooShort(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('too short');
        Woff2Parser::decompressBytes('abc');
    }

    public function testDecompressBytesWrongSignature(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Not a WOFF2');
        Woff2Parser::decompressBytes(str_repeat("\x00", 48));
    }

    /**
     * Build a minimal valid-looking WOFF2 header + table directory.
     *
     * @param list<array{tag:int|string, origLen:int, transformVersion?:int, transformLen?:int}> $tables
     */
    private function buildWoff2Header(array $tables, int $compressedSize = 0): string
    {
        // 48-byte header
        $h = pack('N', 0x774F4632);             // signature
        $h .= pack('N', 0x00010000);             // flavor (truetype)
        $h .= pack('N', 0);                      // length
        $h .= pack('n', count($tables));         // numTables
        $h .= pack('n', 0);                      // reserved
        $h .= pack('N', 0);                      // totalSfntSize
        $h .= pack('N', $compressedSize);        // totalCompressedSize
        $h .= pack('n', 1) . pack('n', 0);       // major.minor version
        $h .= pack('N', 0) . pack('N', 0) . pack('N', 0); // meta
        $h .= pack('N', 0) . pack('N', 0);       // priv
        $directory = '';
        foreach ($tables as $t) {
            $tv = $t['transformVersion'] ?? 0;
            if (is_int($t['tag'])) {
                $flags = ($tv << 6) | ($t['tag'] & 0x3F);
                $directory .= chr($flags);
            } else {
                $flags = ($tv << 6) | 0x3F;
                $directory .= chr($flags) . substr($t['tag'] . '    ', 0, 4);
            }
            $directory .= $this->packUIntBase128($t['origLen']);
            if (isset($t['transformLen'])) {
                $directory .= $this->packUIntBase128($t['transformLen']);
            }
        }
        return $h . $directory . str_repeat("\x00", $compressedSize);
    }

    private function packUIntBase128(int $value): string
    {
        if ($value === 0) {
            return "\x00";
        }
        $bytes = [];
        while ($value > 0) {
            $bytes[] = $value & 0x7F;
            $value >>= 7;
        }
        // Reverse and set high bit on all but the last byte
        $bytes = array_reverse($bytes);
        $out = '';
        for ($i = 0; $i < count($bytes); $i++) {
            $b = $bytes[$i];
            if ($i < count($bytes) - 1) {
                $b |= 0x80;
            }
            $out .= chr($b);
        }
        return $out;
    }

    public function testDecompressFromFilePathDelegatesToDecompressBytes(): void
    {
        // The file-based decompress() reads the path then calls decompressBytes.
        // Use a too-short file so decompressBytes' "too short" error surfaces.
        $tmp = tempnam(sys_get_temp_dir(), 'phpdftk_woff2_') . '.woff2';
        file_put_contents($tmp, "abc");
        try {
            $this->expectException(\RuntimeException::class);
            Woff2Parser::decompress($tmp);
        } finally {
            @unlink($tmp);
        }
    }

    public function testDecompressFailsWithoutBrotli(): void
    {
        // Build a valid-looking WOFF2 with one known tag, expect brotli throw.
        $data = $this->buildWoff2Header([
            ['tag' => 0, 'origLen' => 0],  // 'cmap' (index 0)
        ], compressedSize: 0);

        if (function_exists('brotli_uncompress')) {
            // With brotli, decompression of empty data succeeds; assertion below is met.
            $result = Woff2Parser::decompressBytes($data);
            $this->assertIsString($result);
            return;
        }

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Brotli');
        Woff2Parser::decompressBytes($data);
    }

    public function testDecompressTruncatedTableDirectory(): void
    {
        // Claim 5 tables but provide only 0 bytes after header.
        $data = pack('N', 0x774F4632) . pack('N', 0x00010000)
            . pack('N', 0) . pack('n', 5) . pack('n', 0)
            . pack('N', 0) . pack('N', 0) . pack('n', 1) . pack('n', 0)
            . str_repeat("\x00", 20); // pads out to 48 bytes header
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('table directory');
        Woff2Parser::decompressBytes($data);
    }

    public function testDecompressArbitraryTagViaIndex63(): void
    {
        // Use tag index 63 (custom 4-byte tag); origLen non-zero exercises UIntBase128
        $data = $this->buildWoff2Header([
            ['tag' => 'XYZA', 'origLen' => 100],
        ], compressedSize: 0);

        if (function_exists('brotli_uncompress')) {
            $result = Woff2Parser::decompressBytes($data);
            $this->assertIsString($result);
            return;
        }
        $this->expectException(\RuntimeException::class);
        Woff2Parser::decompressBytes($data);
    }

    public function testDecompressGlyfTransformed(): void
    {
        // glyf has tag index 10. Transform version 0 = transformed → readUIntBase128 for transformLen.
        $data = $this->buildWoff2Header([
            ['tag' => 10, 'origLen' => 200, 'transformVersion' => 0, 'transformLen' => 50],
        ], compressedSize: 0);

        if (function_exists('brotli_uncompress')) {
            $result = Woff2Parser::decompressBytes($data);
            $this->assertIsString($result);
            return;
        }
        $this->expectException(\RuntimeException::class);
        Woff2Parser::decompressBytes($data);
    }

    public function testDecompressNonGlyfNonZeroTransform(): void
    {
        // Tag 'head' (idx 1) with transform version != 0 → transformed branch
        $data = $this->buildWoff2Header([
            ['tag' => 1, 'origLen' => 64, 'transformVersion' => 1, 'transformLen' => 32],
        ], compressedSize: 0);

        if (function_exists('brotli_uncompress')) {
            $result = Woff2Parser::decompressBytes($data);
            $this->assertIsString($result);
            return;
        }
        $this->expectException(\RuntimeException::class);
        Woff2Parser::decompressBytes($data);
    }

    public function testBuildSfntViaReflectionWithSingleTable(): void
    {
        // The private buildSfnt reconstructs the sfnt header + table directory
        // from decompressed tables. Drive it via reflection since the brotli
        // path between decompressBytes and buildSfnt is not available.
        $ref = new \ReflectionClass(Woff2Parser::class);
        $method = $ref->getMethod('buildSfnt');
        $method->setAccessible(true);

        $tables = [
            ['tag' => 'cmap', 'checksum' => 0, 'data' => 'cmap_table_data'],
        ];
        $sfnt = $method->invoke(null, 0x00010000, $tables);
        $this->assertIsString($sfnt);

        // Header: flavor (4) + numTables (2) + searchRange (2) + entrySelector (2) + rangeShift (2) = 12
        $this->assertSame(0x00010000, unpack('N', substr($sfnt, 0, 4))[1]);
        $this->assertSame(1, unpack('n', substr($sfnt, 4, 2))[1]);
        $this->assertStringContainsString('cmap', $sfnt);
    }

    public function testBuildSfntViaReflectionWithMultipleTables(): void
    {
        $ref = new \ReflectionClass(Woff2Parser::class);
        $method = $ref->getMethod('buildSfnt');
        $method->setAccessible(true);

        $tables = [
            ['tag' => 'head', 'checksum' => 12345, 'data' => str_repeat('h', 54)],
            ['tag' => 'cmap', 'checksum' => 67890, 'data' => str_repeat('c', 100)],
            ['tag' => 'glyf', 'checksum' => 11111, 'data' => str_repeat('g', 75)],
            ['tag' => 'loca', 'checksum' => 22222, 'data' => str_repeat('l', 20)],
        ];
        $sfnt = $method->invoke(null, 0x4F54544F, $tables);
        $this->assertIsString($sfnt);
        $this->assertSame(4, unpack('n', substr($sfnt, 4, 2))[1]);
        foreach (['head', 'cmap', 'glyf', 'loca'] as $tag) {
            $this->assertStringContainsString($tag, $sfnt);
        }
    }

    public function testBuildSfntViaReflectionEmptyTablesThrows(): void
    {
        $ref = new \ReflectionClass(Woff2Parser::class);
        $method = $ref->getMethod('buildSfnt');
        $method->setAccessible(true);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('No tables');
        try {
            $method->invoke(null, 0x00010000, []);
        } catch (\ReflectionException $e) {
            $this->fail($e->getMessage());
        } catch (\Throwable $e) {
            throw $e;
        }
    }

    public function testUIntBase128InvalidLeadingZeroThrows(): void
    {
        // Drive readUIntBase128 via decompressBytes by crafting an origLen with
        // leading-zero high-bit byte.
        $h = pack('N', 0x774F4632) . pack('N', 0x00010000)
            . pack('N', 0) . pack('n', 1) . pack('n', 0)
            . pack('N', 0) . pack('N', 0) . pack('n', 1) . pack('n', 0)
            . pack('N', 0) . pack('N', 0) . pack('N', 0)
            . pack('N', 0) . pack('N', 0);
        // Now table entry: flags=0 (known tag index 0=cmap)
        $directory = "\x00";
        // UIntBase128 with leading 0x80 (invalid) — high bit set but value is 0
        $directory .= "\x80";
        $directory .= "\x01"; // any byte to terminate (won't be reached)

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('leading zero');
        Woff2Parser::decompressBytes($h . $directory);
    }

    public function testUIntBase128ExceedingFiveBytesThrows(): void
    {
        // Read 5 bytes all with high bit set → "exceeds 5 bytes"
        $h = pack('N', 0x774F4632) . pack('N', 0x00010000)
            . pack('N', 0) . pack('n', 1) . pack('n', 0)
            . pack('N', 0) . pack('N', 0) . pack('n', 1) . pack('n', 0)
            . pack('N', 0) . pack('N', 0) . pack('N', 0)
            . pack('N', 0) . pack('N', 0);
        $directory = "\x00"; // table flags
        // 5 consecutive bytes with high-bit set and non-leading-zero (use 0x81 for first)
        $directory .= "\x81\x81\x81\x81\x81";

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('exceeds 5 bytes');
        Woff2Parser::decompressBytes($h . $directory);
    }

    public function testUIntBase128ReadPastEndThrows(): void
    {
        $h = pack('N', 0x774F4632) . pack('N', 0x00010000)
            . pack('N', 0) . pack('n', 1) . pack('n', 0)
            . pack('N', 0) . pack('N', 0) . pack('n', 1) . pack('n', 0)
            . pack('N', 0) . pack('N', 0) . pack('N', 0)
            . pack('N', 0) . pack('N', 0);
        // First continuation byte starts but cuts off — read past end
        $directory = "\x00\x81";

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('past end');
        Woff2Parser::decompressBytes($h . $directory);
    }

    public function testDecompressRealWoff2File(): void
    {
        // Search for a .woff2 file on the system
        $candidates = glob('/System/Library/Fonts/*.woff2') ?: [];
        $candidates = array_merge($candidates, glob('/usr/share/fonts/**/*.woff2') ?: []);

        if (empty($candidates)) {
            $this->markTestSkipped('No WOFF2 font found on system');
        }

        $woff2Path = $candidates[0];
        $data = file_get_contents($woff2Path);

        $this->assertTrue(Woff2Parser::isWoff2($data));

        // Check if brotli is available
        if (!function_exists('brotli_uncompress')) {
            $which = trim(shell_exec('which brotli 2>/dev/null') ?? '');
            if ($which === '') {
                $this->markTestSkipped('No brotli decompression available (ext-brotli or CLI)');
            }
        }

        $sfntBytes = Woff2Parser::decompressBytes($data);
        $this->assertNotEmpty($sfntBytes);

        // Should start with a valid sfnt signature
        $sfVersion = unpack('N', $sfntBytes)[1];
        $this->assertTrue(
            $sfVersion === 0x00010000 || $sfVersion === 0x4F54544F,
            'Decompressed WOFF2 should have a valid sfnt signature',
        );
    }
}
