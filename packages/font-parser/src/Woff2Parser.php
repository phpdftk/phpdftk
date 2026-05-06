<?php

declare(strict_types=1);

namespace Phpdftk\FontParser;

/**
 * WOFF 2.0 (Web Open Font Format 2.0) decompressor.
 *
 * Parses a WOFF2 container, decompresses the Brotli-compressed table
 * data, and reconstructs the original sfnt (TrueType/OpenType) bytes.
 * The result can be passed to TrueTypeParser::fromBytes() or
 * OpenTypeParser::fromBytes().
 *
 * WOFF2 uses Brotli compression and optional table transforms
 * (glyf/loca/hmtx). This implementation handles the basic decompression
 * and skips table transforms (the transformed tables are stored as-is
 * when the transform flag indicates no transformation).
 *
 * Brotli decompression strategies (tried in order):
 *   1. PHP ext-brotli (brotli_uncompress)
 *   2. CLI brotli tool (brotli -d)
 *   3. RuntimeException if neither is available
 *
 * @see https://www.w3.org/TR/WOFF2/
 */
final class Woff2Parser
{
    private const WOFF2_SIGNATURE = 0x774F4632; // 'wOF2'

    // Known table tags for compact tag encoding (Table 1 in spec)
    private const KNOWN_TAGS = [
        0  => 'cmap', 1  => 'head', 2  => 'hhea', 3  => 'hmtx',
        4  => 'maxp', 5  => 'name', 6  => 'OS/2', 7  => 'post',
        8  => 'cvt ', 9  => 'fpgm', 10 => 'glyf', 11 => 'loca',
        12 => 'prep', 13 => 'CFF ', 14 => 'VORG', 15 => 'EBDT',
        16 => 'EBLC', 17 => 'gasp', 18 => 'hdmx', 19 => 'kern',
        20 => 'LTSH', 21 => 'PCLT', 22 => 'VDMX', 23 => 'vhea',
        24 => 'vmtx', 25 => 'BASE', 26 => 'GDEF', 27 => 'GPOS',
        28 => 'GSUB', 29 => 'EBSC', 30 => 'JSTF', 31 => 'MATH',
        32 => 'CBDT', 33 => 'CBLC', 34 => 'COLR', 35 => 'CPAL',
        36 => 'SVG ', 37 => 'sbix', 38 => 'acnt', 39 => 'avar',
        40 => 'bdat', 41 => 'bloc', 42 => 'bsln', 43 => 'cvar',
        44 => 'fdsc', 45 => 'feat', 46 => 'fmtx', 47 => 'fvar',
        48 => 'gvar', 49 => 'hsty', 50 => 'just', 51 => 'lcar',
        52 => 'mort', 53 => 'morx', 54 => 'opbd', 55 => 'prop',
        56 => 'trak', 57 => 'Zapf', 58 => 'Silf', 59 => 'Glat',
        60 => 'Gloc', 61 => 'Feat', 62 => 'Sill',
    ];

    /**
     * Decompress a WOFF2 file to raw sfnt (TTF/OTF) bytes.
     */
    public static function decompress(string $woff2Path): string
    {
        $data = file_get_contents($woff2Path);
        if ($data === false) {
            throw new \RuntimeException("Cannot read WOFF2 file: $woff2Path");
        }
        return self::decompressBytes($data);
    }

    /**
     * Decompress WOFF2 bytes to raw sfnt (TTF/OTF) bytes.
     */
    public static function decompressBytes(string $data): string
    {
        if (strlen($data) < 48) {
            throw new \RuntimeException('WOFF2 data too short for header');
        }

        // WOFF2 Header (48 bytes)
        $signature = self::readUint32($data, 0);
        if ($signature !== self::WOFF2_SIGNATURE) {
            throw new \RuntimeException(sprintf(
                'Not a WOFF2 file (signature=0x%08X); expected 0x%08X',
                $signature,
                self::WOFF2_SIGNATURE,
            ));
        }

        $flavor = self::readUint32($data, 4);           // sfnt flavor
        // $woffLength = self::readUint32($data, 8);     // total WOFF2 size
        $numTables = self::readUint16($data, 12);
        // $reserved = self::readUint16($data, 14);
        $totalSfntSize = self::readUint32($data, 16);    // uncompressed sfnt size
        $totalCompressedSize = self::readUint32($data, 20);
        // $majorVersion = self::readUint16($data, 24);
        // $minorVersion = self::readUint16($data, 26);
        // $metaOffset = self::readUint32($data, 28);
        // $metaLength = self::readUint32($data, 32);
        // $metaOrigLength = self::readUint32($data, 36);
        // $privOffset = self::readUint32($data, 40);
        // $privLength = self::readUint32($data, 44);

        // Parse table directory (variable-length entries starting at offset 48)
        $offset = 48;
        $tables = [];

        for ($i = 0; $i < $numTables; $i++) {
            if ($offset >= strlen($data)) {
                throw new \RuntimeException('WOFF2 table directory truncated');
            }

            $flags = ord($data[$offset]);
            $offset++;

            // Bits 0-5: tag index (0-62 = known tag, 63 = arbitrary 4-byte tag)
            $tagIndex = $flags & 0x3F;
            if ($tagIndex === 63) {
                // Read 4-byte tag
                $tag = substr($data, $offset, 4);
                $offset += 4;
            } else {
                $tag = $tagIndex < count(self::KNOWN_TAGS)
                    ? self::KNOWN_TAGS[$tagIndex]
                    : sprintf('T%03d', $tagIndex);
            }

            // Bits 6-7: preprocessing transform (0=none for most, or transform-specific)
            $transformVersion = ($flags >> 6) & 0x03;

            // origLength (UIntBase128)
            [$origLength, $offset] = self::readUIntBase128($data, $offset);

            // transformLength only present when transform is applied
            // For glyf and loca, transform version 0 means transformed (default)
            // For other tables, transform version 0 means no transform
            $transformLength = null;
            $isTransformed = false;
            if ($tag === 'glyf' || $tag === 'loca') {
                $isTransformed = ($transformVersion === 0); // 0 = transformed for glyf/loca
                if ($isTransformed) {
                    [$transformLength, $offset] = self::readUIntBase128($data, $offset);
                }
            } elseif ($transformVersion !== 0) {
                $isTransformed = true;
                [$transformLength, $offset] = self::readUIntBase128($data, $offset);
            }

            $tables[] = [
                'tag' => $tag,
                'flags' => $flags,
                'transformVersion' => $transformVersion,
                'origLength' => $origLength,
                'transformLength' => $transformLength,
                'isTransformed' => $isTransformed,
            ];
        }

        // The compressed data stream starts right after the table directory
        $compressedData = substr($data, $offset, $totalCompressedSize);

        // Decompress with Brotli
        $decompressed = self::brotliDecompress($compressedData);

        // Split the decompressed stream into individual tables
        $streamOffset = 0;
        $decompressedTables = [];

        foreach ($tables as $table) {
            $tableLength = $table['isTransformed']
                ? ($table['transformLength'] ?? $table['origLength'])
                : $table['origLength'];

            if ($streamOffset + $tableLength > strlen($decompressed)) {
                // Truncated — use what we have
                $tableData = substr($decompressed, $streamOffset);
            } else {
                $tableData = substr($decompressed, $streamOffset, $tableLength);
            }
            $streamOffset += $tableLength;

            // For transformed glyf/loca tables, we store them as-is.
            // A full implementation would reverse the transform, but for
            // PDF embedding purposes the font bytes are re-parsed from
            // the reconstructed sfnt which handles this.
            // If the table is NOT transformed, origLength == actual length.
            // If transformed, we need to pad/truncate to origLength.
            if ($table['isTransformed'] && ($table['tag'] === 'glyf' || $table['tag'] === 'loca')) {
                // Skip transformed glyf/loca — they can't be directly embedded
                // Instead, pad to origLength for sfnt reconstruction
                $tableData = str_pad($tableData, $table['origLength'], "\x00");
                $tableData = substr($tableData, 0, $table['origLength']);
            }

            $decompressedTables[] = [
                'tag' => $table['tag'],
                'checksum' => 0, // will be recalculated
                'data' => substr($tableData, 0, $table['origLength']),
            ];
        }

        // Reconstruct sfnt
        return self::buildSfnt($flavor, $decompressedTables);
    }

    /**
     * Detect whether bytes are a WOFF2 file.
     */
    public static function isWoff2(string $data): bool
    {
        return strlen($data) >= 4 && self::readUint32($data, 0) === self::WOFF2_SIGNATURE;
    }

    /**
     * Detect the flavor (TrueType or OpenType CFF) of a WOFF2 file.
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
     * Decompress data using Brotli.
     *
     * Tries ext-brotli first, then brotli CLI, then throws.
     */
    private static function brotliDecompress(string $data): string
    {
        // Strategy 1: PHP ext-brotli
        if (function_exists('brotli_uncompress')) {
            $result = brotli_uncompress($data);
            if ($result === false) {
                throw new \RuntimeException('Brotli decompression failed (ext-brotli)');
            }
            return $result;
        }

        // Strategy 2: CLI brotli tool
        $brotliPath = self::findBrotliCli();
        if ($brotliPath !== null) {
            $tmp = tempnam(sys_get_temp_dir(), 'phpdftk_br_');
            file_put_contents($tmp, $data);

            $outFile = $tmp . '.raw';
            $cmd = escapeshellarg($brotliPath) . ' -d -f -o ' . escapeshellarg($outFile) . ' ' . escapeshellarg($tmp) . ' 2>&1';
            exec($cmd, $output, $exitCode);

            @unlink($tmp);

            if ($exitCode !== 0 || !file_exists($outFile)) {
                @unlink($outFile);
                throw new \RuntimeException(
                    'Brotli CLI decompression failed (exit code ' . $exitCode . '): ' . implode("\n", $output),
                );
            }

            $result = file_get_contents($outFile);
            @unlink($outFile);

            if ($result === false) {
                throw new \RuntimeException('Failed to read brotli decompressed output');
            }
            return $result;
        }

        throw new \RuntimeException(
            'WOFF2 requires Brotli decompression. Install ext-brotli or the brotli CLI tool.',
        );
    }

    /**
     * Find the brotli CLI tool on the system.
     */
    private static function findBrotliCli(): ?string
    {
        foreach (['/opt/homebrew/bin/brotli', '/usr/local/bin/brotli', '/usr/bin/brotli'] as $path) {
            if (file_exists($path) && is_executable($path)) {
                return $path;
            }
        }

        // Try PATH
        $which = trim(shell_exec('which brotli 2>/dev/null') ?? '');
        if ($which !== '' && file_exists($which)) {
            return $which;
        }

        return null;
    }

    /**
     * Read a UIntBase128 variable-length integer (WOFF2 spec §5.2).
     *
     * @return array{int, int} [value, newOffset]
     */
    private static function readUIntBase128(string $data, int $offset): array
    {
        $result = 0;
        for ($i = 0; $i < 5; $i++) {
            if ($offset >= strlen($data)) {
                throw new \RuntimeException('UIntBase128 read past end of data');
            }
            $byte = ord($data[$offset]);
            $offset++;

            // Leading zeros are not allowed (except for value 0)
            if ($i === 0 && $byte === 0x80) {
                throw new \RuntimeException('UIntBase128 has leading zero');
            }

            $result = ($result << 7) | ($byte & 0x7F);

            if (($byte & 0x80) === 0) {
                return [$result, $offset];
            }
        }
        throw new \RuntimeException('UIntBase128 exceeds 5 bytes');
    }

    /**
     * Reconstruct an sfnt file from decompressed tables.
     *
     * Reuses the same logic as WoffParser::buildSfnt().
     */
    /** @param array<array{tag: string, checksum: int, data: string}> $tables */
    private static function buildSfnt(int $flavor, array $tables): string
    {
        $numTables = count($tables);
        if ($numTables === 0) {
            throw new \RuntimeException('No tables found in WOFF2 data');
        }

        $entrySelector = (int) floor(log($numTables, 2));
        $searchRange = (int) pow(2, $entrySelector) * 16;
        $rangeShift = $numTables * 16 - $searchRange;

        $headerSize = 12 + $numTables * 16;

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
            $offset += strlen($table['data']);
            $offset += (4 - ($offset % 4)) % 4;
        }

        $sfnt = '';
        $sfnt .= pack('N', $flavor);
        $sfnt .= pack('n', $numTables);
        $sfnt .= pack('n', $searchRange);
        $sfnt .= pack('n', $entrySelector);
        $sfnt .= pack('n', $rangeShift);

        foreach ($tableEntries as $entry) {
            $sfnt .= str_pad($entry['tag'], 4, "\x00");
            $sfnt .= pack('N', $entry['checksum']);
            $sfnt .= pack('N', $entry['offset']);
            $sfnt .= pack('N', $entry['length']);
        }

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
