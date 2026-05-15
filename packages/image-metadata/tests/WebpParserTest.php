<?php

declare(strict_types=1);

namespace Phpdftk\ImageMetadata\Tests;

use PHPUnit\Framework\TestCase;
use Phpdftk\ImageMetadata\ImageInfo;
use Phpdftk\ImageMetadata\WebpParser;

class WebpParserTest extends TestCase
{
    private function riffHeader(int $size): string
    {
        return 'RIFF' . pack('V', $size) . 'WEBP';
    }

    private function buildVp8(int $width, int $height): string
    {
        // Header (12) + 'VP8 ' (4) + size (4) + 3 dummy + start code 0x9D 0x01 0x2A at offset 23 + w + h
        $chunk = 'VP8 ' . pack('V', 100) . "\x00\x00\x00";
        $chunk .= "\x9D\x01\x2A";
        $chunk .= pack('v', $width & 0x3FFF);
        $chunk .= pack('v', $height & 0x3FFF);
        $chunk .= str_repeat("\x00", 50);

        return $this->riffHeader(strlen($chunk) + 4) . $chunk;
    }

    private function buildVp8L(int $width, int $height): string
    {
        // Header (12) + 'VP8L' (4) + size (4) + 0x2F + 4-byte packed width/height
        $packed = (($width - 1) & 0x3FFF) | ((($height - 1) & 0x3FFF) << 14);
        $chunk = 'VP8L' . pack('V', 100) . "\x2F" . pack('V', $packed);
        $chunk .= str_repeat("\x00", 50);

        return $this->riffHeader(strlen($chunk) + 4) . $chunk;
    }

    private function buildVp8X(int $width, int $height, int $flags, string $extraChunks = ''): string
    {
        // VP8X chunk: 4 ID + 4 size + 1 flags + 3 reserved + 3 width + 3 height = 18 bytes total
        $chunk = 'VP8X' . pack('V', 10);
        $chunk .= chr($flags) . "\x00\x00\x00";
        $w = $width - 1;
        $h = $height - 1;
        $chunk .= chr($w & 0xFF) . chr(($w >> 8) & 0xFF) . chr(($w >> 16) & 0xFF);
        $chunk .= chr($h & 0xFF) . chr(($h >> 8) & 0xFF) . chr(($h >> 16) & 0xFF);
        $chunk .= $extraChunks;

        return $this->riffHeader(strlen($chunk) + 4) . $chunk;
    }

    public function testParseVp8Lossy(): void
    {
        $data = $this->buildVp8(640, 480);
        $info = WebpParser::parse($data);
        $this->assertInstanceOf(ImageInfo::class, $info);
        $this->assertSame(640, $info->width);
        $this->assertSame(480, $info->height);
        $this->assertSame('DeviceRGB', $info->colorSpace);
        $this->assertSame('webp', $info->format);
        $this->assertSame(8, $info->bitsPerComponent);
        $this->assertFalse($info->hasAlpha);
        $this->assertNull($info->iccProfile);
    }

    public function testParseVp8WithoutStartCodeReturnsZeroDimensions(): void
    {
        // Build VP8 chunk but tamper with the start code so it doesn't match.
        $chunk = 'VP8 ' . pack('V', 100) . "\x00\x00\x00";
        $chunk .= "\x00\x00\x00";
        $chunk .= str_repeat("\x00", 50);
        $data = $this->riffHeader(strlen($chunk) + 4) . $chunk;

        $info = WebpParser::parse($data);
        $this->assertSame(0, $info->width);
        $this->assertSame(0, $info->height);
    }

    public function testParseVp8LLossless(): void
    {
        $data = $this->buildVp8L(800, 600);
        $info = WebpParser::parse($data);
        $this->assertSame(800, $info->width);
        $this->assertSame(600, $info->height);
        $this->assertFalse($info->hasAlpha);
    }

    public function testParseVp8LWithoutSignatureReturnsZeroDimensions(): void
    {
        $chunk = 'VP8L' . pack('V', 100) . "\x00" . str_repeat("\x00", 30);
        $data = $this->riffHeader(strlen($chunk) + 4) . $chunk;

        $info = WebpParser::parse($data);
        $this->assertSame(0, $info->width);
        $this->assertSame(0, $info->height);
    }

    public function testParseVp8XExtendedAlphaFlag(): void
    {
        // Flag 0x10 = alpha
        $data = $this->buildVp8X(1024, 768, 0x10);
        $info = WebpParser::parse($data);
        $this->assertSame(1024, $info->width);
        $this->assertSame(768, $info->height);
        $this->assertTrue($info->hasAlpha);
        $this->assertNull($info->iccProfile);
    }

    public function testParseVp8XWithIccChunk(): void
    {
        $iccBytes = 'FAKE_ICC_BYTES_FOR_TEST';
        $iccLen = strlen($iccBytes);
        // ICCP chunk: 'ICCP' + 4-byte size + data (+ pad if odd)
        $iccChunk = 'ICCP' . pack('V', $iccLen) . $iccBytes;
        if ($iccLen % 2 !== 0) {
            $iccChunk .= "\x00";
        }

        // Flag 0x20 = ICC present
        $data = $this->buildVp8X(320, 240, 0x20, $iccChunk);
        $info = WebpParser::parse($data);
        $this->assertSame(320, $info->width);
        $this->assertSame(240, $info->height);
        $this->assertFalse($info->hasAlpha);
        $this->assertSame($iccBytes, $info->iccProfile);
    }

    public function testParseVp8XIccFlagWithoutChunkReturnsNullProfile(): void
    {
        // Flag indicates ICC but no ICCP chunk present.
        $data = $this->buildVp8X(50, 50, 0x20);
        $info = WebpParser::parse($data);
        $this->assertNull($info->iccProfile);
    }

    public function testParseVp8XSkipsUnknownChunksBeforeIcc(): void
    {
        $iccBytes = 'BIG_ICC';
        $iccChunk = 'ICCP' . pack('V', strlen($iccBytes)) . $iccBytes . "\x00"; // pad
        $unknown = 'XYZW' . pack('V', 3) . "abc\x00";
        $data = $this->buildVp8X(10, 10, 0x20, $unknown . $iccChunk);

        $info = WebpParser::parse($data);
        $this->assertSame($iccBytes, $info->iccProfile);
    }

    public function testParseVp8XRejectsOversizedIcc(): void
    {
        // ICCP chunk claims more bytes than the file actually contains.
        $iccChunk = 'ICCP' . pack('V', 9999999) . 'X';
        $data = $this->buildVp8X(10, 10, 0x20, $iccChunk);

        $info = WebpParser::parse($data);
        $this->assertNull($info->iccProfile);
    }

    public function testParseUnknownChunkTypeReturnsZeroDimensions(): void
    {
        $chunk = 'ABCD' . pack('V', 0);
        $data = $this->riffHeader(strlen($chunk) + 4) . $chunk;

        $info = WebpParser::parse($data);
        $this->assertSame(0, $info->width);
        $this->assertSame(0, $info->height);
        $this->assertSame('DeviceRGB', $info->colorSpace);
    }

    public function testParseRejectsShortInput(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Not enough data');
        WebpParser::parse('RIFF');
    }

    public function testParseRejectsMissingRiffMagic(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Not a valid WebP');
        WebpParser::parse('PNGX' . pack('V', 100) . 'WEBP');
    }

    public function testParseRejectsMissingWebpMagic(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Not a valid WebP');
        WebpParser::parse('RIFF' . pack('V', 100) . 'NOTW');
    }

    public function testParseRejectsTooShortAfterHeader(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('too short');
        WebpParser::parse('RIFF' . pack('V', 100) . 'WEBP');
    }

    public function testParseRejectsTooShortVp8Chunk(): void
    {
        // Has chunk header VP8 ' but file ends before 30 bytes.
        $chunk = 'VP8 ' . pack('V', 100);
        $data = $this->riffHeader(strlen($chunk) + 4) . $chunk;
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('VP8 data too short');
        WebpParser::parse($data);
    }

    public function testParseRejectsTooShortVp8LChunk(): void
    {
        $chunk = 'VP8L' . pack('V', 100);
        $data = $this->riffHeader(strlen($chunk) + 4) . $chunk;
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('VP8L data too short');
        WebpParser::parse($data);
    }

    public function testParseRejectsTooShortVp8XChunk(): void
    {
        $chunk = 'VP8X' . pack('V', 100) . "\x00";
        $data = $this->riffHeader(strlen($chunk) + 4) . $chunk;
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('VP8X data too short');
        WebpParser::parse($data);
    }

    public function testParseFileReadsFromDisk(): void
    {
        $data = $this->buildVp8L(64, 32);
        $tmp = tempnam(sys_get_temp_dir(), 'webp_');
        file_put_contents($tmp, $data);
        try {
            $info = WebpParser::parseFile($tmp);
            $this->assertSame(64, $info->width);
            $this->assertSame(32, $info->height);
        } finally {
            @unlink($tmp);
        }
    }

    public function testParseFileThrowsWhenUnreadable(): void
    {
        $this->expectException(\RuntimeException::class);
        @WebpParser::parseFile('/nonexistent/path/to/file.webp');
    }
}
