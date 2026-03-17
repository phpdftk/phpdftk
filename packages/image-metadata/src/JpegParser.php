<?php declare(strict_types=1);
namespace Phpdftk\ImageMetadata;

final class JpegParser {
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
        $pos = 0;

        // Verify SOI marker
        if ($pos + 2 > $len || ord($data[$pos]) !== 0xFF || ord($data[$pos + 1]) !== 0xD8) {
            throw new \RuntimeException('Not a valid JPEG file');
        }
        $pos += 2;

        $width = 0;
        $height = 0;
        $components = 3;
        $bitsPerComponent = 8;
        $xDpi = null;
        $yDpi = null;

        while ($pos + 4 <= $len) {
            // Find next marker
            if (ord($data[$pos]) !== 0xFF) {
                $pos++;
                continue;
            }
            $pos++;
            // Skip padding 0xFF bytes
            while ($pos < $len && ord($data[$pos]) === 0xFF) {
                $pos++;
            }
            if ($pos >= $len) break;

            $marker = ord($data[$pos]);
            $pos++;

            // EOI or standalone markers
            if ($marker === 0xD9 || $marker === 0xD8) break;
            // Skip standalone markers (RST0-RST7, SOI)
            if ($marker >= 0xD0 && $marker <= 0xD7) continue;

            if ($pos + 2 > $len) break;
            $segLen = (ord($data[$pos]) << 8) | ord($data[$pos + 1]);
            $segStart = $pos;
            $pos += 2;

            // APP0 (JFIF) for DPI
            if ($marker === 0xE0 && $segLen >= 16) {
                // Check JFIF identifier
                if (substr($data, $pos, 5) === "JFIF\x00") {
                    $units = ord($data[$pos + 7]);
                    $xDens = (ord($data[$pos + 8]) << 8) | ord($data[$pos + 9]);
                    $yDens = (ord($data[$pos + 10]) << 8) | ord($data[$pos + 11]);
                    if ($units === 1 && $xDens > 0 && $yDens > 0) {
                        $xDpi = $xDens;
                        $yDpi = $yDens;
                    } elseif ($units === 2 && $xDens > 0 && $yDens > 0) {
                        // dots per cm → DPI
                        $xDpi = (int)round($xDens * 2.54);
                        $yDpi = (int)round($yDens * 2.54);
                    }
                }
            }

            // SOF markers: 0xC0=SOF0, 0xC1=SOF1, 0xC2=SOF2
            if (in_array($marker, [0xC0, 0xC1, 0xC2], true)) {
                if ($pos + 6 <= $len) {
                    $bitsPerComponent = ord($data[$pos]);
                    $height = (ord($data[$pos + 1]) << 8) | ord($data[$pos + 2]);
                    $width  = (ord($data[$pos + 3]) << 8) | ord($data[$pos + 4]);
                    $components = ord($data[$pos + 5]);
                }
            }

            $pos = $segStart + $segLen;
        }

        $colorSpace = match ($components) {
            1 => 'DeviceGray',
            4 => 'DeviceCMYK',
            default => 'DeviceRGB',
        };

        return new ImageInfo(
            width: $width,
            height: $height,
            colorSpace: $colorSpace,
            bitsPerComponent: $bitsPerComponent,
            format: 'jpeg',
            hasAlpha: false,
            xDpi: $xDpi,
            yDpi: $yDpi,
        );
    }
}
