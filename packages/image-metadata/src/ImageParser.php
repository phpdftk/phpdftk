<?php

declare(strict_types=1);

namespace Phpdftk\ImageMetadata;

use Phpdftk\Filesystem\LocalFilesystem;

/**
 * Detect image format from magic bytes and delegate to the format-specific parser.
 *
 * Extracts only header metadata (dimensions, color space, bit depth) —
 * never decodes pixel data. This keeps image embedding fast since PDF
 * can reference the compressed image bytes directly.
 */
final class ImageParser
{
    public static function parse(string $path): ImageInfo
    {
        // Read a larger prefix than the raster parsers need so the
        // SVG sniffer (which has to skip an optional XML prolog plus
        // doctype before the `<svg>` opening tag) gets enough bytes
        // on the first hop. Raster parsers still match on the first
        // few magic bytes.
        $data = LocalFilesystem::readPrefix($path, 4096, "image file");
        if (self::looksLikeSvg($data)) {
            return SvgParser::parseFile($path);
        }
        return match (true) {
            str_starts_with($data, "\xFF\xD8\xFF") => JpegParser::parseFile($path),
            str_starts_with($data, "\x89PNG\r\n\x1A\n") => PngParser::parseFile($path),
            str_starts_with($data, 'GIF87a') || str_starts_with($data, 'GIF89a') => GifParser::parseFile($path),
            str_starts_with($data, 'II') || str_starts_with($data, 'MM') => TiffParser::parseFile($path),
            str_starts_with($data, 'RIFF') && substr($data, 8, 4) === 'WEBP' => WebpParser::parseFile($path),
            str_starts_with($data, "\x00\x00\x00\x0C\x6A\x50\x20\x20") || str_starts_with($data, "\xFF\x4F\xFF\x51") => Jpeg2000Parser::parseFile($path),
            str_starts_with($data, "\x97\x4A\x42\x32\x0D\x0A\x1A\x0A") => Jbig2Parser::parseFile($path),
            default => throw new \RuntimeException('Unsupported image format'),
        };
    }

    public static function parseString(string $data): ImageInfo
    {
        if (self::looksLikeSvg($data)) {
            return SvgParser::parse($data);
        }
        return match (true) {
            str_starts_with($data, "\xFF\xD8\xFF") => JpegParser::parse($data),
            str_starts_with($data, "\x89PNG\r\n\x1A\n") => PngParser::parse($data),
            str_starts_with($data, 'GIF87a') || str_starts_with($data, 'GIF89a') => GifParser::parse($data),
            str_starts_with($data, 'II') || str_starts_with($data, 'MM') => TiffParser::parse($data),
            str_starts_with($data, 'RIFF') && substr($data, 8, 4) === 'WEBP' => WebpParser::parse($data),
            str_starts_with($data, "\x00\x00\x00\x0C\x6A\x50\x20\x20") || str_starts_with($data, "\xFF\x4F\xFF\x51") => Jpeg2000Parser::parse($data),
            str_starts_with($data, "\x97\x4A\x42\x32\x0D\x0A\x1A\x0A") => Jbig2Parser::parse($data),
            default => throw new \RuntimeException('Unsupported image format'),
        };
    }

    /**
     * SVG sniffer: case-insensitive `<svg` somewhere in the prefix,
     * with the file starting either with whitespace, `<?xml`, a
     * `<!--` comment, a `<!DOCTYPE`, or directly with `<svg`. Rules
     * out arbitrary XML/HTML where `<svg` happens to appear inside.
     */
    private static function looksLikeSvg(string $data): bool
    {
        $head = ltrim($data);
        if ($head === '') {
            return false;
        }
        if (stripos($head, '<svg') === false) {
            return false;
        }
        return str_starts_with($head, '<?xml')
            || str_starts_with($head, '<!--')
            || stripos($head, '<!doctype') === 0
            || stripos($head, '<svg') === 0;
    }
}
