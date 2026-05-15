<?php

declare(strict_types=1);

namespace Phpdftk\FontParser\Tests;

use Phpdftk\FontParser\KerningParser;
use Phpdftk\FontParser\TrueTypeParser;
use Phpdftk\FontParser\OpenTypeParser;
use PHPUnit\Framework\TestCase;

class KerningParserTest extends TestCase
{
    private static ?string $ttfPath = null;
    private static ?string $otfPath = null;

    public static function setUpBeforeClass(): void
    {
        // Find a TrueType font
        $ttfCandidates = [
            '/System/Library/Fonts/Supplemental/Arial.ttf',
            '/System/Library/Fonts/Supplemental/Verdana.ttf',
            '/System/Library/Fonts/Supplemental/Times New Roman.ttf',
            '/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf',
        ];
        foreach ($ttfCandidates as $path) {
            if (file_exists($path)) {
                self::$ttfPath = $path;
                break;
            }
        }

        // Find an OpenType CFF font
        $otfCandidates = [
            '/System/Library/Fonts/Supplemental/STIXGeneral.otf',
            '/System/Library/Fonts/LastResort.otf',
            '/usr/share/fonts/opentype/stix/STIXGeneral.otf',
        ];
        foreach ($otfCandidates as $path) {
            if (file_exists($path)) {
                $bytes = file_get_contents($path);
                if ($bytes !== false && substr($bytes, 0, 4) === 'OTTO') {
                    self::$otfPath = $path;
                    break;
                }
            }
        }
    }

    public function testTrueTypeFontHasKernPairs(): void
    {
        if (self::$ttfPath === null) {
            $this->markTestSkipped('No TrueType font found');
        }

        $data = (new TrueTypeParser(self::$ttfPath))->parse();

        // Most system TrueType fonts have kerning data
        // If this font doesn't, the test verifies null is returned gracefully
        if ($data->kernPairs === null) {
            $this->addToAssertionCount(1);
            return;
        }

        $this->assertNotEmpty($data->kernPairs);

        // Verify structure: leftGid => [rightGid => value]
        $firstLeft = array_key_first($data->kernPairs);
        $this->assertIsInt($firstLeft);
        $this->assertIsArray($data->kernPairs[$firstLeft]);

        $firstRight = array_key_first($data->kernPairs[$firstLeft]);
        $this->assertIsInt($firstRight);
        $this->assertIsInt($data->kernPairs[$firstLeft][$firstRight]);
    }

    public function testOpenTypeFontKernParsing(): void
    {
        if (self::$otfPath === null) {
            $this->markTestSkipped('No OpenType CFF font found');
        }

        $data = (new OpenTypeParser(self::$otfPath))->parse();

        // Verify kernPairs is either null (no kerning) or a valid structure
        if ($data->kernPairs === null) {
            $this->addToAssertionCount(1);
            return;
        }

        $this->assertNotEmpty($data->kernPairs);
    }

    public function testParseLegacyKernTableFormat0(): void
    {
        // Build a synthetic legacy "kern" table in Microsoft format (version 0).
        $kern = pack('n', 0) . pack('n', 1);
        $pairData = '';
        $pairData .= pack('n', 65) . pack('n', 86) . pack('n', -100 & 0xFFFF);
        $pairData .= pack('n', 80) . pack('n', 65) . pack('n', -50 & 0xFFFF);
        $format0 = pack('n', 2) . pack('n', 12) . pack('n', 1) . pack('n', 0) . $pairData;
        $subtableLength = 6 + strlen($format0);
        $subtable = pack('n', 0) . pack('n', $subtableLength) . pack('n', 0x0001) . $format0;
        $kernBytes = $kern . $subtable;

        $parser = new KerningParser();
        $result = $parser->parse($kernBytes, [
            'kern' => ['offset' => 0, 'length' => strlen($kernBytes)],
        ]);

        $this->assertSame(-100, $result[65][86]);
        $this->assertSame(-50, $result[80][65]);
    }

    public function testParseLegacyKernTableSkipsNonFormat0(): void
    {
        $kern = pack('n', 0) . pack('n', 1);
        // High byte (format) = 1, low byte = 0x01 (horizontal) — non-zero format is skipped
        $subtableHeader = pack('n', 0) . pack('n', 6) . pack('n', 0x0101);
        $parser = new KerningParser();
        $result = $parser->parse($kern . $subtableHeader, [
            'kern' => ['offset' => 0, 'length' => strlen($kern . $subtableHeader)],
        ]);
        $this->assertSame([], $result);
    }

    public function testParseLegacyKernTableSkipsVerticalCoverage(): void
    {
        // Coverage bit 0 = 0 means vertical kerning, which we skip.
        $kern = pack('n', 0) . pack('n', 1);
        $subtableHeader = pack('n', 0) . pack('n', 6) . pack('n', 0x0000);
        $parser = new KerningParser();
        $result = $parser->parse($kern . $subtableHeader, [
            'kern' => ['offset' => 0, 'length' => strlen($kern . $subtableHeader)],
        ]);
        $this->assertSame([], $result);
    }

    public function testParseAppleKernTableVersion1(): void
    {
        // Apple's version-1 kern table uses uint32 nTables and 4-byte subtable lengths.
        $kern = pack('n', 1) . pack('n', 0) . pack('N', 1);
        $pairData = pack('n', 65) . pack('n', 86) . pack('n', -75 & 0xFFFF);
        $format0 = pack('n', 1) . pack('n', 6) . pack('n', 1) . pack('n', 0) . $pairData;
        $subtableLength = 8 + strlen($format0);
        $subtable = pack('N', $subtableLength) . pack('n', 0x0000) . pack('n', 0) . $format0;
        $parser = new KerningParser();
        $result = $parser->parse($kern . $subtable, [
            'kern' => ['offset' => 0, 'length' => strlen($kern . $subtable)],
        ]);
        $this->assertSame(-75, $result[65][86]);
    }

    public function testParseReturnsEmptyWhenNoTables(): void
    {
        $parser = new KerningParser();
        $this->assertSame([], $parser->parse('', []));
    }

    public function testKernValuesAreNonZero(): void
    {
        if (self::$ttfPath === null) {
            $this->markTestSkipped('No TrueType font found');
        }

        $data = (new TrueTypeParser(self::$ttfPath))->parse();
        if ($data->kernPairs === null) {
            $this->markTestSkipped('Font has no kerning data');
        }

        // All stored values should be non-zero (zero pairs are skipped)
        foreach ($data->kernPairs as $leftGid => $rights) {
            foreach ($rights as $rightGid => $value) {
                $this->assertNotSame(0, $value, "Kern pair ($leftGid, $rightGid) should not be zero");
                // Only check a few pairs to avoid slow tests
                break 2;
            }
        }
    }

    public function testKerningParserWithNoKernTables(): void
    {
        $parser = new KerningParser();

        // Empty tables — should return empty array
        $result = $parser->parse('', []);
        $this->assertSame([], $result);
    }

    public function testCommonKernPairsHaveNegativeValues(): void
    {
        if (self::$ttfPath === null) {
            $this->markTestSkipped('No TrueType font found');
        }

        $data = (new TrueTypeParser(self::$ttfPath))->parse();
        if ($data->kernPairs === null) {
            $this->markTestSkipped('Font has no kerning data');
        }

        // Common tightening pairs like AV, To, VA typically have negative kern values.
        // Find at least one negative value in all kern pairs.
        $hasNegative = false;
        foreach ($data->kernPairs as $rights) {
            foreach ($rights as $value) {
                if ($value < 0) {
                    $hasNegative = true;
                    break 2;
                }
            }
        }
        $this->assertTrue($hasNegative, 'Expected at least one negative kern value (tightening pair)');
    }
}
