<?php declare(strict_types=1);
namespace Phpdftk\ImageMetadata;

final class ImageParser {
    public static function parse(string $path): ImageInfo {
        if (!is_file($path)) throw new \RuntimeException("File not found: $path");
        $data = file_get_contents($path, false, null, 0, 32);  // read just enough for signature
        return match (true) {
            str_starts_with($data, "\xFF\xD8\xFF") => JpegParser::parseFile($path),
            str_starts_with($data, "\x89PNG\r\n\x1A\n") => PngParser::parseFile($path),
            str_starts_with($data, 'GIF87a') || str_starts_with($data, 'GIF89a') => GifParser::parseFile($path),
            str_starts_with($data, 'II') || str_starts_with($data, 'MM') => TiffParser::parseFile($path),
            str_starts_with($data, 'RIFF') && substr($data, 8, 4) === 'WEBP' => WebpParser::parseFile($path),
            default => throw new \RuntimeException('Unsupported image format'),
        };
    }

    public static function parseString(string $data): ImageInfo {
        return match (true) {
            str_starts_with($data, "\xFF\xD8\xFF") => JpegParser::parse($data),
            str_starts_with($data, "\x89PNG\r\n\x1A\n") => PngParser::parse($data),
            str_starts_with($data, 'GIF87a') || str_starts_with($data, 'GIF89a') => GifParser::parse($data),
            str_starts_with($data, 'II') || str_starts_with($data, 'MM') => TiffParser::parse($data),
            str_starts_with($data, 'RIFF') && substr($data, 8, 4) === 'WEBP' => WebpParser::parse($data),
            default => throw new \RuntimeException('Unsupported image format'),
        };
    }
}
