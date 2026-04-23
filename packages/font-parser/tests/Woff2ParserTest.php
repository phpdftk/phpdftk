<?php

declare(strict_types=1);

namespace ApprLabs\FontParser\Tests;

use ApprLabs\FontParser\Woff2Parser;
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
            'Decompressed WOFF2 should have a valid sfnt signature'
        );
    }
}
