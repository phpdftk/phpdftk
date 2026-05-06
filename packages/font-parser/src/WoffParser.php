<?php

declare(strict_types=1);

namespace Phpdftk\FontParser;

/**
 * WOFF (Web Open Font Format 1.0) decompressor.
 *
 * Parses a WOFF container, decompresses each table, and reconstructs
 * the original sfnt (TrueType/OpenType) font bytes. The result can be
 * passed to TrueTypeParser::fromBytes() or OpenTypeParser::fromBytes().
 *
 * WOFF 1.0 uses zlib (gzcompress) for per-table compression.
 *
 * @see https://www.w3.org/TR/WOFF/
 */
final class WoffParser
{
    private const WOFF_SIGNATURE = 0x774F4646; // 'wOFF'

    /**
     * Decompress a WOFF file to raw sfnt (TTF/OTF) bytes.
     *
     * @param string $woffPath Path to the WOFF file
     * @return string Raw sfnt bytes
     */
    public static function decompress(string $woffPath): string
    {
        $data = file_get_contents($woffPath);
        if ($data === false) {
            throw new \RuntimeException("Cannot read WOFF file: $woffPath");
        }

        return self::decompressBytes($data);
    }

    /**
     * Decompress WOFF bytes to raw sfnt (TTF/OTF) bytes.
     */
    public static function decompressBytes(string $data): string
    {
        if (strlen($data) < 44) {
            throw new \RuntimeException('WOFF data too short for header');
        }

        // WOFF Header (44 bytes)
        $signature = self::readUint32($data, 0);
        if ($signature !== self::WOFF_SIGNATURE) {
            throw new \RuntimeException(sprintf(
                'Not a WOFF file (signature=0x%08X); expected 0x%08X',
                $signature,
                self::WOFF_SIGNATURE,
            ));
        }

        $flavor = self::readUint32($data, 4);         // Original sfVersion
        // $length = self::readUint32($data, 8);       // Total WOFF file size
        $numTables = self::readUint16($data, 12);
        // $reserved = self::readUint16($data, 14);    // Must be 0
        $totalSfntSize = self::readUint32($data, 16);  // Original sfnt total size
        // Remaining header fields: majorVersion, minorVersion, metaOffset, metaLength, metaOrigLength, privOffset, privLength

        // Parse table directory (20 bytes per entry, starting at offset 44)
        $tables = [];
        for ($i = 0; $i < $numTables; $i++) {
            $entryOffset = 44 + $i * 20;
            if ($entryOffset + 20 > strlen($data)) {
                throw new \RuntimeException('WOFF table directory truncated');
            }

            $tag = substr($data, $entryOffset, 4);
            $woffOffset = self::readUint32($data, $entryOffset + 4);
            $compLength = self::readUint32($data, $entryOffset + 8);
            $origLength = self::readUint32($data, $entryOffset + 12);
            $origChecksum = self::readUint32($data, $entryOffset + 16);

            $tables[] = [
                'tag' => $tag,
                'offset' => $woffOffset,
                'compLength' => $compLength,
                'origLength' => $origLength,
                'checksum' => $origChecksum,
            ];
        }

        // Decompress each table
        $decompressedTables = [];
        foreach ($tables as $table) {
            $compressed = substr($data, $table['offset'], $table['compLength']);

            if ($table['compLength'] === $table['origLength']) {
                // Not compressed — use raw data
                $decompressedTables[] = [
                    'tag' => $table['tag'],
                    'checksum' => $table['checksum'],
                    'data' => $compressed,
                ];
            } else {
                // zlib compressed
                $decompressed = @gzuncompress($compressed);
                if ($decompressed === false) {
                    throw new \RuntimeException(
                        "Failed to decompress WOFF table '{$table['tag']}'",
                    );
                }
                if (strlen($decompressed) !== $table['origLength']) {
                    throw new \RuntimeException(sprintf(
                        "Decompressed size mismatch for table '%s': got %d, expected %d",
                        $table['tag'],
                        strlen($decompressed),
                        $table['origLength'],
                    ));
                }
                $decompressedTables[] = [
                    'tag' => $table['tag'],
                    'checksum' => $table['checksum'],
                    'data' => $decompressed,
                ];
            }
        }

        // Reconstruct sfnt
        return self::buildSfnt($flavor, $decompressedTables);
    }

    /**
     * Detect whether bytes are a WOFF file.
     */
    public static function isWoff(string $data): bool
    {
        return strlen($data) >= 4 && self::readUint32($data, 0) === self::WOFF_SIGNATURE;
    }

    /**
     * Detect the flavor (TrueType or OpenType CFF) of a WOFF file.
     *
     * @return string 'truetype' or 'opentype', or 'unknown'
     */
    public static function detectFlavor(string $data): string
    {
        if (strlen($data) < 8) {
            return 'unknown';
        }
        $flavor = self::readUint32($data, 4);
        return match ($flavor) {
            0x00010000 => 'truetype',
            0x4F54544F => 'opentype',
            default => 'unknown',
        };
    }

    /**
     * Reconstruct an sfnt file from decompressed tables.
     *
     * @param int $flavor Original sfVersion
     * @param array<array{tag: string, checksum: int, data: string}> $tables
     */
    private static function buildSfnt(int $flavor, array $tables): string
    {
        $numTables = count($tables);

        // Compute search parameters
        $entrySelector = (int) floor(log($numTables, 2));
        $searchRange = (int) pow(2, $entrySelector) * 16;
        $rangeShift = $numTables * 16 - $searchRange;

        // Offset table (12 bytes) + table directory (16 bytes per table)
        $headerSize = 12 + $numTables * 16;

        // Compute table offsets (4-byte aligned)
        $offset = $headerSize;
        $tableEntries = [];
        foreach ($tables as $table) {
            $tableEntries[] = [
                'tag' => $table['tag'],
                'checksum' => $table['checksum'],
                'offset' => $offset,
                'length' => strlen($table['data']),
                'data' => $table['data'],
            ];
            // Pad to 4-byte boundary
            $offset += strlen($table['data']);
            $padding = (4 - ($offset % 4)) % 4;
            $offset += $padding;
        }

        // Build the sfnt
        $sfnt = '';

        // Offset table
        $sfnt .= pack('N', $flavor);
        $sfnt .= pack('n', $numTables);
        $sfnt .= pack('n', $searchRange);
        $sfnt .= pack('n', $entrySelector);
        $sfnt .= pack('n', $rangeShift);

        // Table directory
        foreach ($tableEntries as $entry) {
            $sfnt .= $entry['tag'];
            $sfnt .= pack('N', $entry['checksum']);
            $sfnt .= pack('N', $entry['offset']);
            $sfnt .= pack('N', $entry['length']);
        }

        // Table data (4-byte aligned)
        foreach ($tableEntries as $entry) {
            $sfnt .= $entry['data'];
            $padding = (4 - (strlen($entry['data']) % 4)) % 4;
            $sfnt .= str_repeat("\x00", $padding);
        }

        return $sfnt;
    }

    private static function readUint32(string $data, int $offset): int
    {
        return unpack('N', $data, $offset)[1];
    }

    private static function readUint16(string $data, int $offset): int
    {
        return unpack('n', $data, $offset)[1];
    }
}
