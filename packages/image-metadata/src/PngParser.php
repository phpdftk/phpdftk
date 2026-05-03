<?php declare(strict_types=1);
namespace Phpdftk\ImageMetadata;

/**
 * Parse PNG IHDR chunk for dimensions, bit depth, and color type.
 *
 * Also extracts ICC profiles from iCCP chunks. PNG alpha channels
 * are detected since PDF handles transparency via SMask, not inline.
 */
final class PngParser {
    public static function parseFile(string $path): ImageInfo {
        $fh = fopen($path, 'rb');
        if ($fh === false) throw new \RuntimeException("Cannot open file: $path");
        try {
            $data = fread($fh, filesize($path));
        } finally {
            fclose($fh);
        }
        return self::parse($data);
    }

    public static function parse(string $data): ImageInfo {
        $len = strlen($data);

        // Verify PNG signature (8 bytes)
        if ($len < 8 || substr($data, 0, 8) !== "\x89PNG\r\n\x1A\n") {
            throw new \RuntimeException('Not a valid PNG file');
        }

        $pos = 8;
        $width = 0;
        $height = 0;
        $bitDepth = 8;
        $colorType = 2;
        $xDpi = null;
        $yDpi = null;
        $iccProfile = null;

        while ($pos + 12 <= $len) {
            $chunkLen  = unpack('N', substr($data, $pos, 4))[1];
            $chunkType = substr($data, $pos + 4, 4);
            $chunkData = substr($data, $pos + 8, $chunkLen);
            $pos += 12 + $chunkLen;

            if ($chunkType === 'IHDR' && strlen($chunkData) >= 13) {
                $width    = unpack('N', substr($chunkData, 0, 4))[1];
                $height   = unpack('N', substr($chunkData, 4, 4))[1];
                $bitDepth = ord($chunkData[8]);
                $colorType = ord($chunkData[9]);
            } elseif ($chunkType === 'pHYs' && strlen($chunkData) >= 9) {
                $xPixelsPerUnit = unpack('N', substr($chunkData, 0, 4))[1];
                $yPixelsPerUnit = unpack('N', substr($chunkData, 4, 4))[1];
                $unit = ord($chunkData[8]);
                if ($unit === 1 && $xPixelsPerUnit > 0 && $yPixelsPerUnit > 0) {
                    // Unit is meters; convert to DPI
                    $xDpi = (int)round($xPixelsPerUnit / 39.3701);
                    $yDpi = (int)round($yPixelsPerUnit / 39.3701);
                }
            } elseif ($chunkType === 'iCCP' && strlen($chunkData) > 2) {
                // iCCP chunk: null-terminated profile name, 1-byte compression method, compressed data
                $nullPos = strpos($chunkData, "\x00");
                if ($nullPos !== false && $nullPos + 2 <= strlen($chunkData)) {
                    // Skip profile name + null byte + compression method byte (always 0 = deflate)
                    $compressedData = substr($chunkData, $nullPos + 2);
                    if ($compressedData !== '') {
                        $decompressed = @gzuncompress($compressedData);
                        if ($decompressed !== false) {
                            $iccProfile = $decompressed;
                        }
                    }
                }
            } elseif ($chunkType === 'IEND') {
                break;
            }
        }

        [$colorSpace, $hasAlpha] = match ($colorType) {
            0 => ['DeviceGray', false],
            2 => ['DeviceRGB', false],
            3 => ['DeviceRGB', false],  // indexed — treat as RGB
            4 => ['DeviceGray', true],
            6 => ['DeviceRGB', true],
            default => ['DeviceRGB', false],
        };

        return new ImageInfo(
            width: $width,
            height: $height,
            colorSpace: $colorSpace,
            bitsPerComponent: $bitDepth,
            format: 'png',
            hasAlpha: $hasAlpha,
            xDpi: $xDpi,
            yDpi: $yDpi,
            iccProfile: $iccProfile,
        );
    }
}
