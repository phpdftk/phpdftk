<?php

declare(strict_types=1);

namespace Phpdftk\ImageMetadata;

use Phpdftk\Filesystem\LocalFilesystem;

/**
 * Parse WebP container (RIFF/VP8/VP8L/VP8X) for dimensions and alpha.
 */
final class WebpParser
{
    public static function parseFile(string $path): ImageInfo
    {
        $data = LocalFilesystem::readFile($path, "image file");
        return self::parse($data);
    }

    public static function parse(string $data): ImageInfo
    {
        $len = strlen($data);
        if ($len < 12) {
            throw new \RuntimeException('Not enough data for WebP');
        }

        if (substr($data, 0, 4) !== 'RIFF' || substr($data, 8, 4) !== 'WEBP') {
            throw new \RuntimeException('Not a valid WebP file');
        }

        if ($len < 16) {
            throw new \RuntimeException('WebP data too short');
        }

        $chunkType = substr($data, 12, 4);
        $width = 0;
        $height = 0;
        $iccProfile = null;
        $hasAlpha = false;

        if ($chunkType === 'VP8 ') {
            // Lossy VP8
            if ($len < 30) {
                throw new \RuntimeException('WebP VP8 data too short');
            }
            $offset = 23;
            if (ord($data[$offset]) === 0x9D && ord($data[$offset + 1]) === 0x01 && ord($data[$offset + 2]) === 0x2A) {
                $w = unpack('v', substr($data, $offset + 3, 2))[1];
                $h = unpack('v', substr($data, $offset + 5, 2))[1];
                $width  = $w & 0x3FFF;
                $height = $h & 0x3FFF;
            }
        } elseif ($chunkType === 'VP8L') {
            // Lossless VP8L
            if ($len < 25) {
                throw new \RuntimeException('WebP VP8L data too short');
            }
            $offset = 20;
            if (ord($data[$offset]) === 0x2F) {
                $packed = unpack('V', substr($data, $offset + 1, 4))[1];
                $width  = ($packed & 0x3FFF) + 1;
                $height = (($packed >> 14) & 0x3FFF) + 1;
            }
        } elseif ($chunkType === 'VP8X') {
            // Extended VP8X
            if ($len < 30) {
                throw new \RuntimeException('WebP VP8X data too short');
            }

            // Flags byte at offset 20
            $flags = ord($data[20]);
            $hasIcc = ($flags & 0x20) !== 0;   // bit 5: ICC profile
            $hasAlpha = ($flags & 0x10) !== 0;  // bit 4: alpha

            // Canvas dimensions at offset 24
            $wBytes = substr($data, 24, 3) . "\x00";
            $hBytes = substr($data, 27, 3) . "\x00";
            $width  = unpack('V', $wBytes)[1] + 1;
            $height = unpack('V', $hBytes)[1] + 1;

            // Scan for ICCP chunk if flag indicates ICC profile is present
            if ($hasIcc) {
                $iccProfile = self::findChunk($data, $len, 'ICCP');
            }
        }

        return new ImageInfo(
            width: $width,
            height: $height,
            colorSpace: 'DeviceRGB',
            bitsPerComponent: 8,
            format: 'webp',
            hasAlpha: $hasAlpha,
            iccProfile: $iccProfile,
        );
    }

    /**
     * Search for a named chunk in the WebP RIFF container.
     * Chunks start at offset 12 (after RIFF + filesize + WEBP).
     */
    private static function findChunk(string $data, int $len, string $chunkId): ?string
    {
        $pos = 12; // after 'RIFF' + 4-byte size + 'WEBP'

        while ($pos + 8 <= $len) {
            $tag = substr($data, $pos, 4);
            $chunkSize = unpack('V', $data, $pos + 4)[1];

            if ($tag === $chunkId) {
                $dataStart = $pos + 8;
                if ($dataStart + $chunkSize <= $len) {
                    return substr($data, $dataStart, $chunkSize);
                }
                return null;
            }

            // Advance to next chunk (chunks are 2-byte aligned)
            $pos += 8 + $chunkSize;
            if ($chunkSize % 2 !== 0) {
                $pos++; // padding byte
            }
        }

        return null;
    }
}
