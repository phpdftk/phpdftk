<?php

declare(strict_types=1);

namespace Phpdftk\ImageMetadata\Tests;

use Phpdftk\ImageMetadata\ImageInfo;
use Phpdftk\ImageMetadata\JpegParser;
use Phpdftk\ImageMetadata\PngParser;
use PHPUnit\Framework\TestCase;

class IccProfileTest extends TestCase
{
    public function testJpegWithoutIccProfileReturnsNull(): void
    {
        // Create a simple JPEG without any ICC profile
        $img = imagecreatetruecolor(4, 4);
        ob_start();
        imagejpeg($img);
        $data = ob_get_clean();
        imagedestroy($img);

        $info = JpegParser::parse($data);
        $this->assertNull($info->iccProfile);
    }

    public function testJpegWithIccProfileExtractsIt(): void
    {
        // Build a synthetic JPEG with an APP2 ICC_PROFILE marker
        // Start with SOI
        $jpeg = "\xFF\xD8";

        // Create a fake ICC profile (just recognizable bytes)
        $fakeProfile = str_repeat("\x42", 100);

        // APP2 marker with ICC_PROFILE identifier
        // identifier (12 bytes) + seq num (1) + total chunks (1) + profile data
        $iccPayload = "ICC_PROFILE\x00" . chr(1) . chr(1) . $fakeProfile;
        $segLen = strlen($iccPayload) + 2; // +2 for length field itself
        $jpeg .= "\xFF\xE2" . pack('n', $segLen) . $iccPayload;

        // SOF0 marker for dimensions
        $sofPayload = chr(8) // bits per component
            . pack('n', 4)   // height
            . pack('n', 4)   // width
            . chr(3);        // components (RGB)
        $sofLen = strlen($sofPayload) + 2;
        $jpeg .= "\xFF\xC0" . pack('n', $sofLen) . $sofPayload;

        // EOI
        $jpeg .= "\xFF\xD9";

        $info = JpegParser::parse($jpeg);
        $this->assertNotNull($info->iccProfile);
        $this->assertSame($fakeProfile, $info->iccProfile);
        $this->assertSame(4, $info->width);
        $this->assertSame(4, $info->height);
    }

    public function testJpegWithMultiChunkIccProfile(): void
    {
        // Build a JPEG with ICC profile split across two APP2 chunks
        $jpeg = "\xFF\xD8";

        $chunk1Data = str_repeat("\x41", 50);
        $chunk2Data = str_repeat("\x42", 50);

        // Chunk 1 of 2
        $payload1 = "ICC_PROFILE\x00" . chr(1) . chr(2) . $chunk1Data;
        $segLen1 = strlen($payload1) + 2;
        $jpeg .= "\xFF\xE2" . pack('n', $segLen1) . $payload1;

        // Chunk 2 of 2
        $payload2 = "ICC_PROFILE\x00" . chr(2) . chr(2) . $chunk2Data;
        $segLen2 = strlen($payload2) + 2;
        $jpeg .= "\xFF\xE2" . pack('n', $segLen2) . $payload2;

        // SOF0
        $sofPayload = chr(8) . pack('n', 2) . pack('n', 2) . chr(3);
        $jpeg .= "\xFF\xC0" . pack('n', strlen($sofPayload) + 2) . $sofPayload;

        $jpeg .= "\xFF\xD9";

        $info = JpegParser::parse($jpeg);
        $this->assertNotNull($info->iccProfile);
        $this->assertSame($chunk1Data . $chunk2Data, $info->iccProfile);
    }

    public function testPngWithoutIccProfileReturnsNull(): void
    {
        $img = imagecreatetruecolor(4, 4);
        ob_start();
        imagepng($img);
        $data = ob_get_clean();
        imagedestroy($img);

        $info = PngParser::parse($data);
        $this->assertNull($info->iccProfile);
    }

    public function testPngWithIccpChunkExtractsProfile(): void
    {
        // Build a minimal PNG with an iCCP chunk
        $fakeProfile = str_repeat("\x43", 80);
        $compressedProfile = gzcompress($fakeProfile);

        // PNG signature
        $png = "\x89PNG\r\n\x1A\n";

        // IHDR chunk: 13 bytes of data
        $ihdr = pack('N', 4)       // width
            . pack('N', 4)         // height
            . chr(8)               // bit depth
            . chr(2)               // color type (RGB)
            . chr(0)               // compression
            . chr(0)               // filter
            . chr(0);              // interlace
        $png .= $this->pngChunk('IHDR', $ihdr);

        // iCCP chunk: profile name + null + compression method + compressed data
        $iccpData = "sRGB\x00" . chr(0) . $compressedProfile;
        $png .= $this->pngChunk('iCCP', $iccpData);

        // IEND chunk
        $png .= $this->pngChunk('IEND', '');

        $info = PngParser::parse($png);
        $this->assertNotNull($info->iccProfile);
        $this->assertSame($fakeProfile, $info->iccProfile);
    }

    public function testImageInfoCarriesIccProfile(): void
    {
        $profile = 'fake-icc-data';
        $info = new ImageInfo(
            width: 10,
            height: 10,
            colorSpace: 'DeviceRGB',
            bitsPerComponent: 8,
            format: 'jpeg',
            iccProfile: $profile,
        );
        $this->assertSame($profile, $info->iccProfile);
    }

    public function testImageInfoDefaultsToNullIccProfile(): void
    {
        $info = new ImageInfo(
            width: 10,
            height: 10,
            colorSpace: 'DeviceRGB',
            bitsPerComponent: 8,
            format: 'jpeg',
        );
        $this->assertNull($info->iccProfile);
    }

    private function pngChunk(string $type, string $data): string
    {
        $chunk = $type . $data;
        return pack('N', strlen($data)) . $chunk . pack('N', crc32($chunk));
    }
}
