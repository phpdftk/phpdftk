<?php

declare(strict_types=1);

namespace Phpdftk\FontParser\Tests;

use Phpdftk\FontParser\WoffParser;
use Phpdftk\FontParser\TrueTypeParser;
use PHPUnit\Framework\TestCase;

class WoffParserTest extends TestCase
{
    private static ?string $ttfPath = null;

    public static function setUpBeforeClass(): void
    {
        // Find a TrueType font on the system
        $candidates = [
            '/System/Library/Fonts/Supplemental/Arial.ttf',
            '/System/Library/Fonts/Helvetica.ttc',
            '/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf',
        ];
        foreach ($candidates as $path) {
            if (file_exists($path)) {
                $bytes = file_get_contents($path);
                if ($bytes !== false && unpack('N', $bytes)[1] === 0x00010000) {
                    self::$ttfPath = $path;
                    return;
                }
            }
        }

        // Fallback: scan font directories
        $dirs = ['/System/Library/Fonts/Supplemental', '/usr/share/fonts'];
        foreach ($dirs as $dir) {
            if (!is_dir($dir)) {
                continue;
            }
            foreach (glob("$dir/*.ttf") ?: [] as $file) {
                $bytes = file_get_contents($file);
                if ($bytes !== false && strlen($bytes) > 4 && unpack('N', $bytes)[1] === 0x00010000) {
                    self::$ttfPath = $file;
                    return;
                }
            }
        }
    }

    public function testIsWoffDetectsWoffSignature(): void
    {
        // 'wOFF' signature
        $woffHeader = pack('N', 0x774F4646) . str_repeat("\x00", 40);
        $this->assertTrue(WoffParser::isWoff($woffHeader));

        // Not WOFF
        $ttfHeader = pack('N', 0x00010000) . str_repeat("\x00", 40);
        $this->assertFalse(WoffParser::isWoff($ttfHeader));
    }

    public function testDetectFlavorTrueType(): void
    {
        // WOFF with TrueType flavor
        $data = pack('N', 0x774F4646) . pack('N', 0x00010000);
        $this->assertSame('truetype', WoffParser::detectFlavor($data));
    }

    public function testDetectFlavorOpenType(): void
    {
        // WOFF with OpenType CFF flavor
        $data = pack('N', 0x774F4646) . pack('N', 0x4F54544F);
        $this->assertSame('opentype', WoffParser::detectFlavor($data));
    }

    public function testCreateAndDecompressWoff(): void
    {
        if (self::$ttfPath === null) {
            $this->markTestSkipped('No TrueType font found');
        }

        $originalBytes = file_get_contents(self::$ttfPath);

        // Create a minimal WOFF from the TTF
        $woffBytes = self::createWoffFromTtf($originalBytes);

        // Verify it's detected as WOFF
        $this->assertTrue(WoffParser::isWoff($woffBytes));
        $this->assertSame('truetype', WoffParser::detectFlavor($woffBytes));

        // Decompress back to sfnt
        $sfntBytes = WoffParser::decompressBytes($woffBytes);

        // The decompressed result should be a valid TrueType font
        $this->assertSame(0x00010000, unpack('N', $sfntBytes)[1]);

        // Parse it to verify structural integrity
        $parser = TrueTypeParser::fromBytes($sfntBytes);
        $data = $parser->parse();
        $this->assertGreaterThan(0, $data->unitsPerEm);
        $this->assertNotEmpty($data->familyName);
    }

    public function testDecompressedWoffProducesSameMetrics(): void
    {
        if (self::$ttfPath === null) {
            $this->markTestSkipped('No TrueType font found');
        }

        // Parse original TTF
        $originalParser = new TrueTypeParser(self::$ttfPath);
        $originalData = $originalParser->parse();

        // Create WOFF, decompress, parse
        $woffBytes = self::createWoffFromTtf(file_get_contents(self::$ttfPath));
        $sfntBytes = WoffParser::decompressBytes($woffBytes);
        $roundTripParser = TrueTypeParser::fromBytes($sfntBytes);
        $roundTripData = $roundTripParser->parse();

        // Metrics should match
        $this->assertSame($originalData->unitsPerEm, $roundTripData->unitsPerEm);
        $this->assertSame($originalData->familyName, $roundTripData->familyName);
        $this->assertSame($originalData->ascent, $roundTripData->ascent);
        $this->assertSame($originalData->descent, $roundTripData->descent);
    }

    public function testDecompressBytesTooShort(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('too short');
        WoffParser::decompressBytes('abc');
    }

    public function testDecompressBytesWrongSignature(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Not a WOFF');
        WoffParser::decompressBytes(str_repeat("\x00", 44));
    }

    /**
     * Create a minimal WOFF from raw TTF bytes.
     *
     * This is a test helper — it compresses each table with gzcompress
     * and builds the WOFF header + directory.
     */
    private static function createWoffFromTtf(string $ttfBytes): string
    {
        $flavor = unpack('N', $ttfBytes, 0)[1];
        $numTables = unpack('n', $ttfBytes, 4)[1];

        // Parse TTF table directory
        $tables = [];
        for ($i = 0; $i < $numTables; $i++) {
            $base = 12 + $i * 16;
            $tag = substr($ttfBytes, $base, 4);
            $checksum = unpack('N', $ttfBytes, $base + 4)[1];
            $offset = unpack('N', $ttfBytes, $base + 8)[1];
            $length = unpack('N', $ttfBytes, $base + 12)[1];
            $tables[] = [
                'tag' => $tag,
                'checksum' => $checksum,
                'data' => substr($ttfBytes, $offset, $length),
            ];
        }

        // Build WOFF
        $headerSize = 44;
        $dirSize = $numTables * 20;
        $dataOffset = $headerSize + $dirSize;

        // Compress tables and compute entries
        $compressedTables = [];
        $currentOffset = $dataOffset;
        foreach ($tables as $table) {
            $compressed = gzcompress($table['data'], 6);
            // Only use compression if it's smaller
            if (strlen($compressed) < strlen($table['data'])) {
                $compressedTables[] = [
                    'tag' => $table['tag'],
                    'checksum' => $table['checksum'],
                    'offset' => $currentOffset,
                    'compLength' => strlen($compressed),
                    'origLength' => strlen($table['data']),
                    'compData' => $compressed,
                ];
                $currentOffset += strlen($compressed);
            } else {
                $compressedTables[] = [
                    'tag' => $table['tag'],
                    'checksum' => $table['checksum'],
                    'offset' => $currentOffset,
                    'compLength' => strlen($table['data']),
                    'origLength' => strlen($table['data']),
                    'compData' => $table['data'],
                ];
                $currentOffset += strlen($table['data']);
            }
            // 4-byte align
            $padding = (4 - ($currentOffset % 4)) % 4;
            $currentOffset += $padding;
        }

        $totalWoffSize = $currentOffset;

        // WOFF header (44 bytes)
        $woff = pack('N', 0x774F4646);    // signature
        $woff .= pack('N', $flavor);       // flavor
        $woff .= pack('N', $totalWoffSize); // length
        $woff .= pack('n', $numTables);    // numTables
        $woff .= pack('n', 0);            // reserved
        $woff .= pack('N', strlen($ttfBytes)); // totalSfntSize
        $woff .= pack('nn', 1, 0);        // majorVersion, minorVersion
        $woff .= pack('NNN', 0, 0, 0);   // metaOffset, metaLength, metaOrigLength
        $woff .= pack('NN', 0, 0);        // privOffset, privLength

        // Table directory
        foreach ($compressedTables as $entry) {
            $woff .= $entry['tag'];
            $woff .= pack('N', $entry['offset']);
            $woff .= pack('N', $entry['compLength']);
            $woff .= pack('N', $entry['origLength']);
            $woff .= pack('N', $entry['checksum']);
        }

        // Table data
        foreach ($compressedTables as $entry) {
            $woff .= $entry['compData'];
            $padding = (4 - (strlen($entry['compData']) % 4)) % 4;
            $woff .= str_repeat("\x00", $padding);
        }

        return $woff;
    }
}
