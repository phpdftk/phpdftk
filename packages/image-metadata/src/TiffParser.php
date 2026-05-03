<?php declare(strict_types=1);
namespace Phpdftk\ImageMetadata;

/**
 * Parse TIFF IFD tags for dimensions, color space, and bit depth.
 *
 * Handles both little-endian (II) and big-endian (MM) byte orders.
 * TIFF is commonly encountered in scanned-document workflows.
 */
final class TiffParser {
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
        if ($len < 8) throw new \RuntimeException('Not enough data for TIFF');

        $byteOrder = substr($data, 0, 2);
        if ($byteOrder !== 'II' && $byteOrder !== 'MM') {
            throw new \RuntimeException('Not a valid TIFF file');
        }
        $le = ($byteOrder === 'II');

        $readUint16 = function(string $buf, int $offset) use ($le): int {
            $v = unpack($le ? 'v' : 'n', substr($buf, $offset, 2))[1];
            return $v;
        };
        $readUint32 = function(string $buf, int $offset) use ($le): int {
            $v = unpack($le ? 'V' : 'N', substr($buf, $offset, 4))[1];
            return $v;
        };

        // Check magic number
        $magic = $readUint16($data, 2);
        if ($magic !== 42) throw new \RuntimeException('Not a valid TIFF file (bad magic)');

        // Offset to first IFD
        $ifdOffset = $readUint32($data, 4);
        if ($ifdOffset + 2 > $len) throw new \RuntimeException('IFD offset out of range');

        $numEntries = $readUint16($data, $ifdOffset);
        $pos = $ifdOffset + 2;

        $width = 0;
        $height = 0;
        $bitsPerSample = 8;
        $samplesPerPixel = 3;
        $photometric = 2; // Default: RGB
        $iccProfile = null;

        for ($i = 0; $i < $numEntries; $i++) {
            if ($pos + 12 > $len) break;

            $tag   = $readUint16($data, $pos);
            $type  = $readUint16($data, $pos + 2);
            $count = $readUint32($data, $pos + 4);

            // Read value (SHORT=3, LONG=4)
            $valueOrOffset = $readUint32($data, $pos + 8);
            if ($type === 3) {
                $value = $readUint16($data, $pos + 8);
            } else {
                $value = $valueOrOffset;
            }

            switch ($tag) {
                case 256: $width = $value; break;           // ImageWidth
                case 257: $height = $value; break;          // ImageLength
                case 258: $bitsPerSample = $value; break;   // BitsPerSample
                case 277: $samplesPerPixel = $value; break; // SamplesPerPixel
                case 262: $photometric = $value; break;     // PhotometricInterpretation
                case 34675:                                  // InterColorProfile (ICC)
                    if ($count > 0 && $valueOrOffset + $count <= $len) {
                        $iccProfile = substr($data, $valueOrOffset, $count);
                    }
                    break;
            }

            $pos += 12;
        }

        $colorSpace = match ($photometric) {
            0, 1 => 'DeviceGray',
            5    => 'DeviceCMYK',
            default => 'DeviceRGB',
        };

        return new ImageInfo(
            width: $width,
            height: $height,
            colorSpace: $colorSpace,
            bitsPerComponent: $bitsPerSample,
            format: 'tiff',
            hasAlpha: false,
            iccProfile: $iccProfile,
        );
    }
}
