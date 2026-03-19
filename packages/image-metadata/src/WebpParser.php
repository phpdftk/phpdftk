<?php declare(strict_types=1);
namespace ApprLabs\ImageMetadata;

final class WebpParser {
    public static function parseFile(string $path): ImageInfo {
        $fh = fopen($path, 'rb');
        if ($fh === false) throw new \RuntimeException("Cannot open file: $path");
        try {
            $data = fread($fh, 64);
        } finally {
            fclose($fh);
        }
        return self::parse($data);
    }

    public static function parse(string $data): ImageInfo {
        $len = strlen($data);
        if ($len < 12) throw new \RuntimeException('Not enough data for WebP');

        if (substr($data, 0, 4) !== 'RIFF' || substr($data, 8, 4) !== 'WEBP') {
            throw new \RuntimeException('Not a valid WebP file');
        }

        if ($len < 16) throw new \RuntimeException('WebP data too short');

        $chunkType = substr($data, 12, 4);
        $width = 0;
        $height = 0;

        if ($chunkType === 'VP8 ') {
            // Lossy VP8
            // Skip RIFF+size+WEBP+VP8 header+chunk size = 20 bytes, then 3 byte frame tag
            if ($len < 30) throw new \RuntimeException('WebP VP8 data too short');
            $offset = 23; // 12 (RIFF/WEBP) + 4 (chunk type) + 4 (chunk size) + 3 (frame tag)
            // Check bitstream start bytes
            if (ord($data[$offset]) === 0x9D && ord($data[$offset + 1]) === 0x01 && ord($data[$offset + 2]) === 0x2A) {
                $w = unpack('v', substr($data, $offset + 3, 2))[1];
                $h = unpack('v', substr($data, $offset + 5, 2))[1];
                $width  = $w & 0x3FFF;
                $height = $h & 0x3FFF;
            }
        } elseif ($chunkType === 'VP8L') {
            // Lossless VP8L
            // After RIFF(12) + chunktype(4) + chunksize(4) = offset 20, then signature byte 0x2F
            if ($len < 25) throw new \RuntimeException('WebP VP8L data too short');
            $offset = 20;
            if (ord($data[$offset]) === 0x2F) {
                // Next 4 bytes: packed width-1 (14 bits) and height-1 (14 bits)
                $packed = unpack('V', substr($data, $offset + 1, 4))[1];
                $width  = ($packed & 0x3FFF) + 1;
                $height = (($packed >> 14) & 0x3FFF) + 1;
            }
        } elseif ($chunkType === 'VP8X') {
            // Extended VP8X
            // After RIFF(12) + VP8X(4) + chunksize(4) = offset 20, then 4 bytes flags
            if ($len < 30) throw new \RuntimeException('WebP VP8X data too short');
            $offset = 24; // skip flags (4 bytes)
            // Canvas width-1 (24-bit LE) and canvas height-1 (24-bit LE)
            $wBytes = substr($data, $offset, 3) . "\x00";
            $hBytes = substr($data, $offset + 3, 3) . "\x00";
            $width  = unpack('V', $wBytes)[1] + 1;
            $height = unpack('V', $hBytes)[1] + 1;
        }

        return new ImageInfo(
            width: $width,
            height: $height,
            colorSpace: 'DeviceRGB',
            bitsPerComponent: 8,
            format: 'webp',
            hasAlpha: false,
        );
    }
}
