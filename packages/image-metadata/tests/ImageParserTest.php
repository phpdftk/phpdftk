<?php declare(strict_types=1);

namespace Phpdftk\ImageMetadata\Tests;

use PHPUnit\Framework\TestCase;
use Phpdftk\ImageMetadata\ImageParser;
use Phpdftk\ImageMetadata\ImageInfo;

class ImageParserTest extends TestCase
{
    private function createMinimalJpeg(): string {
        $img = imagecreatetruecolor(10, 10);
        ob_start();
        imagejpeg($img);
        $data = ob_get_clean();
        imagedestroy($img);
        return $data;
    }

    private function createMinimalPng(): string {
        $img = imagecreatetruecolor(10, 10);
        ob_start();
        imagepng($img);
        $data = ob_get_clean();
        imagedestroy($img);
        return $data;
    }

    private function createMinimalGif(): string {
        // Minimal GIF89a header: 6-byte header + 7-byte logical screen descriptor
        // GIF89a + width(10, LE) + height(10, LE) + 3 more bytes
        return "GIF89a" . "\x0A\x00" . "\x0A\x00" . "\x00\x00\x00";
    }

    public function testParseJpeg(): void {
        $data = $this->createMinimalJpeg();
        $info = ImageParser::parseString($data);
        $this->assertInstanceOf(ImageInfo::class, $info);
        $this->assertSame('jpeg', $info->format);
        $this->assertSame(10, $info->width);
        $this->assertSame(10, $info->height);
        $this->assertSame('DeviceRGB', $info->colorSpace);
        $this->assertSame(8, $info->bitsPerComponent);
        $this->assertFalse($info->hasAlpha);
    }

    public function testParsePng(): void {
        $data = $this->createMinimalPng();
        $info = ImageParser::parseString($data);
        $this->assertInstanceOf(ImageInfo::class, $info);
        $this->assertSame('png', $info->format);
        $this->assertSame(10, $info->width);
        $this->assertSame(10, $info->height);
        $this->assertSame('DeviceRGB', $info->colorSpace);
    }

    public function testParseGif(): void {
        $data = $this->createMinimalGif();
        $info = ImageParser::parseString($data);
        $this->assertInstanceOf(ImageInfo::class, $info);
        $this->assertSame('gif', $info->format);
        $this->assertSame(10, $info->width);
        $this->assertSame(10, $info->height);
        $this->assertSame('DeviceRGB', $info->colorSpace);
        $this->assertSame(8, $info->bitsPerComponent);
    }

    public function testUnsupportedFormatThrows(): void {
        $this->expectException(\RuntimeException::class);
        ImageParser::parseString('This is not an image');
    }

    public function testParseFileNotFound(): void {
        $this->expectException(\RuntimeException::class);
        ImageParser::parse('/nonexistent/path/to/file.jpg');
    }

    public function testParseFileWithJpeg(): void {
        $data = $this->createMinimalJpeg();
        $tmpFile = tempnam(sys_get_temp_dir(), 'jpeg_test_') . '.jpg';
        file_put_contents($tmpFile, $data);
        try {
            $info = ImageParser::parse($tmpFile);
            $this->assertSame('jpeg', $info->format);
            $this->assertSame(10, $info->width);
        } finally {
            unlink($tmpFile);
        }
    }

    public function testParseFileWithPng(): void {
        $data = $this->createMinimalPng();
        $tmpFile = tempnam(sys_get_temp_dir(), 'png_test_') . '.png';
        file_put_contents($tmpFile, $data);
        try {
            $info = ImageParser::parse($tmpFile);
            $this->assertSame('png', $info->format);
        } finally {
            unlink($tmpFile);
        }
    }

    public function testParseFileWithGif(): void {
        $data = $this->createMinimalGif();
        $tmpFile = tempnam(sys_get_temp_dir(), 'gif_test_') . '.gif';
        file_put_contents($tmpFile, $data);
        try {
            $info = ImageParser::parse($tmpFile);
            $this->assertSame('gif', $info->format);
        } finally {
            unlink($tmpFile);
        }
    }

    public function testJpegSignatureDetection(): void {
        $data = "\xFF\xD8\xFF" . str_repeat("\x00", 50);
        // This is a technically invalid JPEG but has correct signature
        // The parser will return with 0 width/height since no SOF marker
        $info = ImageParser::parseString($data);
        $this->assertSame('jpeg', $info->format);
    }

    public function testPngSignatureDetection(): void {
        // Minimal PNG with valid signature and IHDR chunk
        $data = $this->createMinimalPng();
        $this->assertTrue(str_starts_with($data, "\x89PNG\r\n\x1A\n"));
        $info = ImageParser::parseString($data);
        $this->assertSame('png', $info->format);
    }

    public function testGifSignatureDetection(): void {
        $data = $this->createMinimalGif();
        $this->assertTrue(str_starts_with($data, 'GIF89a'));
        $info = ImageParser::parseString($data);
        $this->assertSame('gif', $info->format);
    }

    public function testGif87aSignatureDetection(): void {
        // GIF87a variant
        $data = "GIF87a" . "\x05\x00" . "\x07\x00" . "\x00\x00\x00";
        $info = ImageParser::parseString($data);
        $this->assertSame('gif', $info->format);
        $this->assertSame(5, $info->width);
        $this->assertSame(7, $info->height);
    }

    public function testImageInfoProperties(): void {
        $info = new ImageInfo(
            width: 800,
            height: 600,
            colorSpace: 'DeviceRGB',
            bitsPerComponent: 8,
            format: 'jpeg',
            hasAlpha: false,
            xDpi: 72,
            yDpi: 72,
        );
        $this->assertSame(800, $info->width);
        $this->assertSame(600, $info->height);
        $this->assertSame('DeviceRGB', $info->colorSpace);
        $this->assertSame(8, $info->bitsPerComponent);
        $this->assertSame('jpeg', $info->format);
        $this->assertFalse($info->hasAlpha);
        $this->assertSame(72, $info->xDpi);
        $this->assertSame(72, $info->yDpi);
    }

    /**
     * Build a minimal JP2 file with signature box + jp2h/ihdr box.
     *
     * Layout: signature box (12) + file type box (20) + jp2h super-box
     * containing ihdr (22 bytes: 8 header + 14 content).
     */
    private function createMinimalJp2(int $width = 64, int $height = 48, int $components = 3, int $bpc = 8): string
    {
        // Signature box: length(4) + 'jP  '(4) + signature(4)
        $sig = "\x00\x00\x00\x0C" . "jP  " . "\x0D\x0A\x87\x0A";

        // File type box: length(4) + 'ftyp'(4) + brand(4) + version(4) + compat(4)
        $ftyp = "\x00\x00\x00\x14" . "ftyp" . "jp2 " . "\x00\x00\x00\x00" . "jp2 ";

        // ihdr box: length(4) + 'ihdr'(4) + height(4) + width(4) + nc(2) + bpc(1) + C(1) + UnkC(1) + IPR(1)
        $ihdr = "\x00\x00\x00\x16" . "ihdr"
            . pack('N', $height) . pack('N', $width)
            . pack('n', $components)
            . chr(($bpc - 1) & 0x7F) // Ssiz: (bpc-1) stored
            . "\x07" // C = compression type (always 7 for JP2)
            . "\x00" // UnkC
            . "\x00"; // IPR

        // jp2h super-box wrapping ihdr
        $jp2hContent = $ihdr;
        $jp2h = pack('N', 8 + strlen($jp2hContent)) . "jp2h" . $jp2hContent;

        return $sig . $ftyp . $jp2h;
    }

    /**
     * Build a minimal raw JPEG 2000 codestream (SOC + SIZ marker).
     */
    private function createMinimalJ2kCodestream(int $width = 32, int $height = 24, int $components = 1, int $bpc = 8): string
    {
        // SOC marker
        $data = "\xFF\x4F";

        // SIZ marker: FF51 + Lsiz(2) + Rsiz(2) + Xsiz(4) + Ysiz(4) +
        //   XOsiz(4) + YOsiz(4) + XTsiz(4) + YTsiz(4) + XTOsiz(4) + YTOsiz(4) +
        //   Csiz(2) + [Ssiz(1) + XRsiz(1) + YRsiz(1)] * Csiz
        $compBytes = '';
        for ($i = 0; $i < $components; $i++) {
            $compBytes .= chr(($bpc - 1) & 0x7F) . "\x01\x01"; // Ssiz, XRsiz, YRsiz
        }

        $sizLen = 38 + 3 * $components; // Lsiz value (includes self)
        $siz = "\xFF\x51"
            . pack('n', $sizLen)
            . "\x00\x00" // Rsiz
            . pack('N', $width)  // Xsiz
            . pack('N', $height) // Ysiz
            . "\x00\x00\x00\x00" // XOsiz
            . "\x00\x00\x00\x00" // YOsiz
            . pack('N', $width)  // XTsiz
            . pack('N', $height) // YTsiz
            . "\x00\x00\x00\x00" // XTOsiz
            . "\x00\x00\x00\x00" // YTOsiz
            . pack('n', $components)
            . $compBytes;

        return $data . $siz;
    }

    /**
     * Build a minimal JBIG2 file with file header + page information segment.
     */
    private function createMinimalJbig2(int $width = 100, int $height = 200): string
    {
        // File header: signature(8) + flags(1) + page count(4)
        $header = "\x97\x4A\x42\x32\x0D\x0A\x1A\x0A"
            . "\x00"                   // flags: sequential, known page count
            . "\x00\x00\x00\x01";      // 1 page

        // Segment header for Page Information (type 48):
        //   segNum(4) + flags(1) + refCountByte(1) + pageAssoc(1) + dataLen(4)
        // Flags: type=48 (0x30), no deferred flag, 1-byte page association
        $segHeader = "\x00\x00\x00\x00"  // segment number 0
            . "\x30"                      // flags: type 48 (page info)
            . "\x00"                      // retain/ref count byte (0 refs)
            . "\x01"                      // page association = page 1
            . "\x00\x00\x00\x13";         // data length = 19 bytes

        // Page Information segment data (19 bytes):
        //   width(4) + height(4) + xRes(4) + yRes(4) + flags(1) + striping(2)
        $segData = pack('N', $width) . pack('N', $height)
            . "\x00\x00\x00\x00"   // x resolution
            . "\x00\x00\x00\x00"   // y resolution
            . "\x00"               // flags
            . "\x00\x00";          // striping info

        return $header . $segHeader . $segData;
    }

    public function testParseJpeg2000Jp2(): void
    {
        $data = $this->createMinimalJp2(64, 48, 3, 8);
        $info = ImageParser::parseString($data);
        $this->assertInstanceOf(ImageInfo::class, $info);
        $this->assertSame('jpeg2000', $info->format);
        $this->assertSame(64, $info->width);
        $this->assertSame(48, $info->height);
        $this->assertSame('DeviceRGB', $info->colorSpace);
        $this->assertSame(8, $info->bitsPerComponent);
    }

    public function testParseJpeg2000Jp2Grayscale(): void
    {
        $data = $this->createMinimalJp2(100, 200, 1, 16);
        $info = ImageParser::parseString($data);
        $this->assertSame('jpeg2000', $info->format);
        $this->assertSame(100, $info->width);
        $this->assertSame(200, $info->height);
        $this->assertSame('DeviceGray', $info->colorSpace);
        $this->assertSame(16, $info->bitsPerComponent);
    }

    public function testParseJpeg2000RawCodestream(): void
    {
        $data = $this->createMinimalJ2kCodestream(32, 24, 1, 8);
        $info = ImageParser::parseString($data);
        $this->assertSame('jpeg2000', $info->format);
        $this->assertSame(32, $info->width);
        $this->assertSame(24, $info->height);
        $this->assertSame('DeviceGray', $info->colorSpace);
        $this->assertSame(8, $info->bitsPerComponent);
    }

    public function testParseJpeg2000RawCodestreamRgb(): void
    {
        $data = $this->createMinimalJ2kCodestream(256, 128, 3, 8);
        $info = ImageParser::parseString($data);
        $this->assertSame('jpeg2000', $info->format);
        $this->assertSame(256, $info->width);
        $this->assertSame(128, $info->height);
        $this->assertSame('DeviceRGB', $info->colorSpace);
    }

    public function testParseJpeg2000File(): void
    {
        $data = $this->createMinimalJp2(80, 60);
        $tmpFile = tempnam(sys_get_temp_dir(), 'jp2_test_') . '.jp2';
        file_put_contents($tmpFile, $data);
        try {
            $info = ImageParser::parse($tmpFile);
            $this->assertSame('jpeg2000', $info->format);
            $this->assertSame(80, $info->width);
            $this->assertSame(60, $info->height);
        } finally {
            unlink($tmpFile);
        }
    }

    public function testParseJbig2(): void
    {
        $data = $this->createMinimalJbig2(100, 200);
        $info = ImageParser::parseString($data);
        $this->assertInstanceOf(ImageInfo::class, $info);
        $this->assertSame('jbig2', $info->format);
        $this->assertSame(100, $info->width);
        $this->assertSame(200, $info->height);
        $this->assertSame('DeviceGray', $info->colorSpace);
        $this->assertSame(1, $info->bitsPerComponent);
        $this->assertFalse($info->hasAlpha);
    }

    public function testParseJbig2File(): void
    {
        $data = $this->createMinimalJbig2(300, 400);
        $tmpFile = tempnam(sys_get_temp_dir(), 'jbig2_test_') . '.jbig2';
        file_put_contents($tmpFile, $data);
        try {
            $info = ImageParser::parse($tmpFile);
            $this->assertSame('jbig2', $info->format);
            $this->assertSame(300, $info->width);
            $this->assertSame(400, $info->height);
        } finally {
            unlink($tmpFile);
        }
    }

    public function testParseJbig2DifferentDimensions(): void
    {
        $data = $this->createMinimalJbig2(1728, 2376);
        $info = ImageParser::parseString($data);
        $this->assertSame(1728, $info->width);
        $this->assertSame(2376, $info->height);
    }
}
