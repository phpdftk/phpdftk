<?php declare(strict_types=1);
namespace Phpdftk\ImageMetadata;

/**
 * Parse JBIG2 image headers.
 *
 * JBIG2 is a bi-level (1-bit) image compression format used for
 * scanned documents. The format has two variants:
 *   - Sequential: file header + segments in order
 *   - Embedded: segments only (used inside PDF streams — no file header)
 *
 * This parser handles the file-based format with the standard
 * 8-byte file header (0x974A4232 0D0A1A0A).
 *
 * Page dimensions come from the Page Information segment (type 48).
 */
final class Jbig2Parser
{
    /** JBIG2 file header signature */
    private const SIGNATURE = "\x97\x4A\x42\x32\x0D\x0A\x1A\x0A";

    public static function parseFile(string $path): ImageInfo
    {
        $fh = fopen($path, 'rb');
        if ($fh === false) {
            throw new \RuntimeException("Cannot open file: $path");
        }
        try {
            // Read enough for header + first few segments
            $data = fread($fh, min(filesize($path), 4096));
        } finally {
            fclose($fh);
        }
        return self::parse($data);
    }

    public static function parse(string $data): ImageInfo
    {
        $len = strlen($data);

        if ($len >= 8 && str_starts_with($data, self::SIGNATURE)) {
            return self::parseFileFormat($data, $len);
        }

        // Try parsing as embedded format (segment stream without file header).
        // The first bytes should be a segment header.
        return self::parseSegments($data, 0, $len);
    }

    /**
     * Parse JBIG2 file format with standard header.
     *
     * Header layout (ISO 14492):
     *   bytes 0-7:  signature (97 4A 42 32 0D 0A 1A 0A)
     *   byte 8:     flags (bit 0 = sequential org, bit 1 = unknown page count)
     *   bytes 9-12: number of pages (only if bit 1 of flags is 0)
     */
    private static function parseFileFormat(string $data, int $len): ImageInfo
    {
        if ($len < 9) {
            throw new \RuntimeException('JBIG2: file too short');
        }

        $flags = ord($data[8]);
        $knownPageCount = ($flags & 0x02) === 0;

        // Segment data starts after header
        $pos = 9;
        if ($knownPageCount) {
            $pos = 13; // skip 4-byte page count
        }

        return self::parseSegments($data, $pos, $len);
    }

    /**
     * Walk segments looking for a Page Information segment (type 48)
     * which contains the page width and height.
     */
    private static function parseSegments(string $data, int $pos, int $len): ImageInfo
    {
        // Parse segment headers to find page information (segment type 48)
        $maxSegments = 100; // safety limit
        for ($i = 0; $i < $maxSegments && $pos + 6 <= $len; $i++) {
            // Segment header:
            //   4 bytes: segment number
            //   1 byte: flags (bits 0-5 = type, bit 6 = page association size, bit 7 = deferred)
            //   variable: referred-to segments count + list
            //   1 or 4 bytes: page association
            //   4 bytes: data length

            $segNum = self::readUint32($data, $pos);
            $pos += 4;

            if ($pos >= $len) break;
            $segFlags = ord($data[$pos]);
            $pos++;

            $segType = $segFlags & 0x3F;
            $pageAssocSizeLarge = ($segFlags & 0x40) !== 0;

            // Referred-to segment count (bits 5-7 of next byte, or long form)
            if ($pos >= $len) break;
            $retainByte = ord($data[$pos]);
            $refCount = ($retainByte >> 5) & 0x07;
            $pos++;

            if ($refCount === 7) {
                // Long form: next 4 bytes are the count
                if ($pos + 4 > $len) break;
                $refCount = self::readUint32($data, $pos) & 0x1FFFFFFF;
                $pos += 4;
            }

            // Skip referred-to segment numbers
            $refSize = ($segNum <= 256) ? 1 : (($segNum <= 65536) ? 2 : 4);
            $pos += $refCount * $refSize;

            // Page association
            $pageAssocSize = $pageAssocSizeLarge ? 4 : 1;
            $pos += $pageAssocSize;

            // Data length
            if ($pos + 4 > $len) break;
            $dataLen = self::readUint32($data, $pos);
            $pos += 4;

            // Segment type 48 = Page Information
            if ($segType === 48 && $pos + 8 <= $len) {
                $width = self::readUint32($data, $pos);
                $height = self::readUint32($data, $pos + 4);

                // JBIG2 is always 1-bit bi-level
                return new ImageInfo(
                    width: $width,
                    height: $height,
                    colorSpace: 'DeviceGray',
                    bitsPerComponent: 1,
                    format: 'jbig2',
                    hasAlpha: false,
                );
            }

            // Skip segment data (0xFFFFFFFF means unknown length — bail)
            if ($dataLen === 0xFFFFFFFF) break;
            $pos += $dataLen;
        }

        throw new \RuntimeException('JBIG2: unable to find page dimensions');
    }

    private static function readUint32(string $data, int $offset): int
    {
        return (ord($data[$offset]) << 24)
             | (ord($data[$offset + 1]) << 16)
             | (ord($data[$offset + 2]) << 8)
             | ord($data[$offset + 3]);
    }
}
