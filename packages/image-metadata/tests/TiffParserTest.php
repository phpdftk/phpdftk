<?php

declare(strict_types=1);

namespace Phpdftk\ImageMetadata\Tests;

use PHPUnit\Framework\TestCase;
use Phpdftk\ImageMetadata\ImageInfo;
use Phpdftk\ImageMetadata\TiffParser;

class TiffParserTest extends TestCase
{
    /**
     * Build a minimal TIFF in II (little-endian) byte order with the requested IFD entries.
     *
     * Each entry is a triplet [tag, type, value]. Type 3 = SHORT (uint16), 4 = LONG (uint32).
     * The value (for inline values) is packed into a 4-byte slot — uint16 occupies the first 2 bytes.
     *
     * @param list<array{int, int, int}> $entries
     */
    private function buildTiffLe(array $entries, int $ifdOffset = 8): string
    {
        // Header: II + magic 42 + IFD offset
        $header = 'II' . pack('v', 42) . pack('V', $ifdOffset);
        $padding = str_repeat("\x00", max(0, $ifdOffset - strlen($header)));

        $body = pack('v', count($entries));
        foreach ($entries as [$tag, $type, $value]) {
            $body .= pack('v', $tag) . pack('v', $type) . pack('V', 1);
            if ($type === 3) {
                $body .= pack('v', $value) . "\x00\x00";
            } else {
                $body .= pack('V', $value);
            }
        }
        $body .= pack('V', 0); // next IFD offset

        return $header . $padding . $body;
    }

    /**
     * Build a minimal TIFF in MM (big-endian) byte order.
     *
     * @param list<array{int, int, int}> $entries
     */
    private function buildTiffBe(array $entries, int $ifdOffset = 8): string
    {
        $header = 'MM' . pack('n', 42) . pack('N', $ifdOffset);
        $padding = str_repeat("\x00", max(0, $ifdOffset - strlen($header)));

        $body = pack('n', count($entries));
        foreach ($entries as [$tag, $type, $value]) {
            $body .= pack('n', $tag) . pack('n', $type) . pack('N', 1);
            if ($type === 3) {
                $body .= pack('n', $value) . "\x00\x00";
            } else {
                $body .= pack('N', $value);
            }
        }
        $body .= pack('N', 0);

        return $header . $padding . $body;
    }

    public function testParseLittleEndianRgb(): void
    {
        $data = $this->buildTiffLe([
            [256, 3, 640],   // ImageWidth
            [257, 3, 480],   // ImageLength
            [258, 3, 8],     // BitsPerSample
            [262, 3, 2],     // Photometric: RGB
            [277, 3, 3],     // SamplesPerPixel
        ]);

        $info = TiffParser::parse($data);
        $this->assertInstanceOf(ImageInfo::class, $info);
        $this->assertSame(640, $info->width);
        $this->assertSame(480, $info->height);
        $this->assertSame('DeviceRGB', $info->colorSpace);
        $this->assertSame(8, $info->bitsPerComponent);
        $this->assertSame('tiff', $info->format);
        $this->assertFalse($info->hasAlpha);
        $this->assertNull($info->iccProfile);
    }

    public function testParseBigEndianGrayscale(): void
    {
        $data = $this->buildTiffBe([
            [256, 4, 100],   // ImageWidth (LONG)
            [257, 4, 200],   // ImageLength (LONG)
            [258, 3, 16],    // BitsPerSample
            [262, 3, 1],     // Photometric: BlackIsZero (grayscale)
            [277, 3, 1],     // SamplesPerPixel
        ]);

        $info = TiffParser::parse($data);
        $this->assertSame(100, $info->width);
        $this->assertSame(200, $info->height);
        $this->assertSame('DeviceGray', $info->colorSpace);
        $this->assertSame(16, $info->bitsPerComponent);
    }

    public function testParseWhiteIsZeroAlsoGray(): void
    {
        $data = $this->buildTiffLe([
            [256, 3, 10],
            [257, 3, 10],
            [262, 3, 0],    // WhiteIsZero
        ]);

        $info = TiffParser::parse($data);
        $this->assertSame('DeviceGray', $info->colorSpace);
    }

    public function testParseCmyk(): void
    {
        $data = $this->buildTiffLe([
            [256, 3, 50],
            [257, 3, 60],
            [262, 3, 5],    // CMYK
            [277, 3, 4],
        ]);

        $info = TiffParser::parse($data);
        $this->assertSame('DeviceCMYK', $info->colorSpace);
    }

    public function testParseRgbDefaultWhenPhotometricUnknown(): void
    {
        $data = $this->buildTiffLe([
            [256, 3, 1],
            [257, 3, 1],
            [262, 3, 99],   // unknown — falls to default RGB
        ]);

        $info = TiffParser::parse($data);
        $this->assertSame('DeviceRGB', $info->colorSpace);
    }

    public function testParseIccProfileEmbedded(): void
    {
        $iccBytes = 'ICC_PROFILE_DATA_FAKE';
        $iccOffset = 8;
        $iccLen = strlen($iccBytes);

        $ifdOffset = 100;
        $header = 'II' . pack('v', 42) . pack('V', $ifdOffset);
        // Manual layout: header (8) + icc + zero-pad + IFD
        $buffer = $header . $iccBytes;
        $buffer .= str_repeat("\x00", $ifdOffset - strlen($buffer));

        $body = pack('v', 4);
        $body .= pack('v', 256) . pack('v', 3) . pack('V', 1) . pack('v', 10) . "\x00\x00";
        $body .= pack('v', 257) . pack('v', 3) . pack('V', 1) . pack('v', 10) . "\x00\x00";
        $body .= pack('v', 262) . pack('v', 3) . pack('V', 1) . pack('v', 2) . "\x00\x00";
        $body .= pack('v', 34675) . pack('v', 7) . pack('V', $iccLen) . pack('V', $iccOffset);
        $body .= pack('V', 0);

        $data = $buffer . $body;

        $info = TiffParser::parse($data);
        $this->assertSame($iccBytes, $info->iccProfile);
    }

    public function testParseIccProfileSkippedWhenOutOfRange(): void
    {
        $data = $this->buildTiffLe([
            [256, 3, 1],
            [257, 3, 1],
            [262, 3, 2],
            // ICC tag with count claiming way more bytes than the file holds — skipped silently.
            [34675, 7, 999999],
        ]);

        $info = TiffParser::parse($data);
        $this->assertNull($info->iccProfile);
    }

    public function testParseTruncatedIfdStopsEarly(): void
    {
        // Build a TIFF claiming 5 entries but include only 2 entry-worths of bytes.
        $header = 'II' . pack('v', 42) . pack('V', 8);
        $body = pack('v', 5);
        // Two real entries
        $body .= pack('v', 256) . pack('v', 3) . pack('V', 1) . pack('v', 42) . "\x00\x00";
        $body .= pack('v', 257) . pack('v', 3) . pack('V', 1) . pack('v', 21) . "\x00\x00";
        // Then chop off without writing the remaining 3
        $data = $header . $body;

        $info = TiffParser::parse($data);
        $this->assertSame(42, $info->width);
        $this->assertSame(21, $info->height);
    }

    public function testParseRejectsTooSmallInput(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Not enough data');
        TiffParser::parse("II\x2A\x00");
    }

    public function testParseRejectsUnknownByteOrder(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Not a valid TIFF');
        TiffParser::parse('XX' . pack('v', 42) . pack('V', 8));
    }

    public function testParseRejectsBadMagic(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('bad magic');
        TiffParser::parse('II' . pack('v', 43) . pack('V', 8));
    }

    public function testParseRejectsIfdOffsetOutOfRange(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('IFD offset out of range');
        TiffParser::parse('II' . pack('v', 42) . pack('V', 9999));
    }

    public function testParseFileReadsFromDisk(): void
    {
        $data = $this->buildTiffLe([
            [256, 3, 11],
            [257, 3, 22],
            [262, 3, 2],
        ]);

        $tmp = tempnam(sys_get_temp_dir(), 'tiff_');
        file_put_contents($tmp, $data);
        try {
            $info = TiffParser::parseFile($tmp);
            $this->assertSame(11, $info->width);
            $this->assertSame(22, $info->height);
        } finally {
            @unlink($tmp);
        }
    }

    public function testParseFileThrowsOnMissingPath(): void
    {
        $this->expectException(\RuntimeException::class);
        @TiffParser::parseFile('/nonexistent/path/that/does/not/exist.tiff');
    }
}
