<?php declare(strict_types=1);
namespace ApprLabs\ImageMetadata;

final class GifParser {
    public static function parseFile(string $path): ImageInfo {
        $fh = fopen($path, 'rb');
        if ($fh === false) throw new \RuntimeException("Cannot open file: $path");
        try {
            $data = fread($fh, 13);
        } finally {
            fclose($fh);
        }
        return self::parse($data);
    }

    public static function parse(string $data): ImageInfo {
        if (strlen($data) < 13) {
            throw new \RuntimeException('Not enough data for a valid GIF file');
        }

        $header = substr($data, 0, 6);
        if ($header !== 'GIF87a' && $header !== 'GIF89a') {
            throw new \RuntimeException('Not a valid GIF file');
        }

        // Width and height are little-endian 16-bit at bytes 6-7 and 8-9
        $width  = unpack('v', substr($data, 6, 2))[1];
        $height = unpack('v', substr($data, 8, 2))[1];

        return new ImageInfo(
            width: $width,
            height: $height,
            colorSpace: 'DeviceRGB',
            bitsPerComponent: 8,
            format: 'gif',
            hasAlpha: false,
        );
    }
}
