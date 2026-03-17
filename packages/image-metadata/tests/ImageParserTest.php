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
}
