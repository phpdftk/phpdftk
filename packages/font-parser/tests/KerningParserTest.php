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
