<?php

declare(strict_types=1);

namespace Phpdftk\ImageMetadata;

/**
 * Parse JPEG 2000 (.jp2, .j2k, .j2c) image headers.
 *
 * Supports two container formats:
 *   - JP2 box format (starts with JP2 signature box)
 *   - Raw codestream (.j2k/.j2c, starts with SOC marker 0xFF4F)
 *
 * Extracts width, height, components, and bits per component from
 * the SIZ marker (required in every JPEG 2000 codestream).
 */
final class Jpeg2000Parser
{
    /** JP2 file signature: 12-byte box with "jP  " brand */
    private const JP2_SIGNATURE = "\x00\x00\x00\x0C\x6A\x50\x20\x20";

    /** JPEG 2000 codestream SOC (Start of Codestream) marker */
    private const SOC_MARKER = "\xFF\x4F";

    /** SIZ marker (Image and tile size) */
    private const SIZ_MARKER = "\xFF\x51";

    public static function parseFile(string $path): ImageInfo
    {
        $fh = fopen($path, 'rb');
        if ($fh === false) {
            throw new \RuntimeException("Cannot open file: $path");
        }
        try {
            // Read enough for JP2 box header + ihdr, or raw codestream SIZ
            $data = fread($fh, min(filesize($path), 4096));
        } finally {
            fclose($fh);
        }
        return self::parse($data);
    }

    public static function parse(string $data): ImageInfo
    {
        $len = strlen($data);
        if ($len < 2) {
            throw new \RuntimeException('Data too short for JPEG 2000');
        }

        // Detect format: JP2 box container or raw codestream
        if (str_starts_with($data, self::JP2_SIGNATURE)) {
            return self::parseJp2Boxes($data, $len);
        }

        if (str_starts_with($data, self::SOC_MARKER)) {
            return self::parseCodestream($data, 0, $len);
        }

        throw new \RuntimeException('Not a valid JPEG 2000 file');
    }

    /**
     * Parse JP2 box format — walk boxes to find the codestream (jp2c)
     * or the image header box (ihdr).
     */
    private static function parseJp2Boxes(string $data, int $len): ImageInfo
    {
        $pos = 0;
        $width = 0;
        $height = 0;
        $components = 3;
        $bitsPerComponent = 8;

        while ($pos + 8 <= $len) {
            $boxLen = self::readUint32($data, $pos);
            $boxType = substr($data, $pos + 4, 4);

            // Box length of 0 means "rest of file"; 1 means extended (8-byte) length
            if ($boxLen === 1 && $pos + 16 <= $len) {
                // Extended length — skip for our purposes, use upper bound
                $boxLen = $len - $pos;
            } elseif ($boxLen === 0) {
                $boxLen = $len - $pos;
            }

            $contentStart = $pos + 8;
            $contentLen = $boxLen - 8;

            if ($boxType === 'ihdr' && $contentLen >= 14) {
                // Image Header Box: height(4) width(4) nc(2) bpc(1) ...
                $height = self::readUint32($data, $contentStart);
                $width = self::readUint32($data, $contentStart + 4);
                $components = self::readUint16($data, $contentStart + 8);
                $bitsPerComponent = (ord($data[$contentStart + 10]) & 0x7F) + 1;

                return self::buildInfo($width, $height, $components, $bitsPerComponent);
            }

            if ($boxType === 'jp2c' && $contentLen >= 2) {
                // Contiguous Codestream Box — parse the embedded codestream
                return self::parseCodestream($data, $contentStart, $len);
            }

            // jp2h (JP2 Header super-box) contains ihdr — recurse into it
            if ($boxType === 'jp2h') {
                $innerPos = $contentStart;
                $innerEnd = min($pos + $boxLen, $len);
                while ($innerPos + 8 <= $innerEnd) {
                    $innerBoxLen = self::readUint32($data, $innerPos);
                    $innerBoxType = substr($data, $innerPos + 4, 4);
                    if ($innerBoxLen === 0) {
                        $innerBoxLen = $innerEnd - $innerPos;
                    }

                    if ($innerBoxType === 'ihdr' && $innerBoxLen >= 22) {
                        $ihdrStart = $innerPos + 8;
                        $height = self::readUint32($data, $ihdrStart);
                        $width = self::readUint32($data, $ihdrStart + 4);
                        $components = self::readUint16($data, $ihdrStart + 8);
                        $bitsPerComponent = (ord($data[$ihdrStart + 10]) & 0x7F) + 1;

                        return self::buildInfo($width, $height, $components, $bitsPerComponent);
                    }

                    $innerPos += max($innerBoxLen, 8);
                }
            }

            $pos += max($boxLen, 8);
        }

        // Fallback if no ihdr or codestream found
        throw new \RuntimeException('JPEG 2000: unable to find image dimensions');
    }

    /**
     * Parse a raw JPEG 2000 codestream starting at $offset.
     * Looks for the SIZ marker to extract dimensions.
     */
    private static function parseCodestream(string $data, int $offset, int $len): ImageInfo
    {
        $pos = $offset;

        // Skip SOC marker
        if ($pos + 2 <= $len && substr($data, $pos, 2) === self::SOC_MARKER) {
            $pos += 2;
        }

        // Next marker should be SIZ
        if ($pos + 2 <= $len && substr($data, $pos, 2) === self::SIZ_MARKER) {
            $pos += 2;

            if ($pos + 2 > $len) {
                throw new \RuntimeException('JPEG 2000: truncated SIZ marker');
            }

            // SIZ marker content: Lsiz(2) Rsiz(2) Xsiz(4) Ysiz(4) XOsiz(4) YOsiz(4)
            // XTsiz(4) YTsiz(4) XTOsiz(4) YTOsiz(4) Csiz(2) ...
            // Image dimensions = Xsiz - XOsiz, Ysiz - YOsiz
            $segLen = self::readUint16($data, $pos);
            $pos += 2;

            if ($pos + 36 > $len) {
                throw new \RuntimeException('JPEG 2000: truncated SIZ segment');
            }

            // Skip Rsiz (2 bytes)
            $pos += 2;

            $xSiz = self::readUint32($data, $pos);
            $ySiz = self::readUint32($data, $pos + 4);
            $xoSiz = self::readUint32($data, $pos + 8);
            $yoSiz = self::readUint32($data, $pos + 12);

            $width = $xSiz - $xoSiz;
            $height = $ySiz - $yoSiz;

            // Skip XTsiz(4) YTsiz(4) XTOsiz(4) YTOsiz(4)
            $csizOffset = $pos + 32;
            $components = self::readUint16($data, $csizOffset);

            // Ssiz_i: bit depth for first component
            $bitsPerComponent = 8;
            if ($csizOffset + 2 + 1 <= $len) {
                $ssiz = ord($data[$csizOffset + 2]);
                $bitsPerComponent = ($ssiz & 0x7F) + 1;
            }

            return self::buildInfo($width, $height, $components, $bitsPerComponent);
        }

        throw new \RuntimeException('JPEG 2000: SIZ marker not found');
    }

    private static function buildInfo(int $width, int $height, int $components, int $bitsPerComponent): ImageInfo
    {
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
            format: 'jpeg2000',
            hasAlpha: $components === 2 || $components === 4,
        );
    }

    private static function readUint32(string $data, int $offset): int
    {
        return (ord($data[$offset]) << 24)
             | (ord($data[$offset + 1]) << 16)
             | (ord($data[$offset + 2]) << 8)
             | ord($data[$offset + 3]);
    }

    private static function readUint16(string $data, int $offset): int
    {
        return (ord($data[$offset]) << 8) | ord($data[$offset + 1]);
    }
}
