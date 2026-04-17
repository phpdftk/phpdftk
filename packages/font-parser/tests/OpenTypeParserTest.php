<?php

declare(strict_types=1);

namespace ApprLabs\FontParser\Tests;

use ApprLabs\FontParser\OpenTypeData;
use ApprLabs\FontParser\OpenTypeParser;
use PHPUnit\Framework\TestCase;

class OpenTypeParserTest extends TestCase
{
    private static ?string $fontPath = null;

    public static function setUpBeforeClass(): void
    {
        // Find an OpenType CFF font on the system
        $candidates = [
            '/System/Library/Fonts/Supplemental/STIXGeneral.otf',
            '/System/Library/Fonts/LastResort.otf',
            '/usr/share/fonts/opentype/stix/STIXGeneral.otf',
        ];

        foreach ($candidates as $path) {
            if (file_exists($path)) {
                // Verify it's actually CFF (sfVersion = OTTO)
                $bytes = file_get_contents($path);
                if ($bytes !== false && substr($bytes, 0, 4) === 'OTTO') {
                    self::$fontPath = $path;
                    return;
                }
            }
        }

        // Search for any OTF file
        $searchPaths = [
            '/System/Library/Fonts/Supplemental',
            '/usr/share/fonts',
        ];
        foreach ($searchPaths as $dir) {
            if (!is_dir($dir)) {
                continue;
            }
            $files = glob("$dir/*.otf") ?: [];
            foreach ($files as $file) {
                $bytes = file_get_contents($file);
                if ($bytes !== false && substr($bytes, 0, 4) === 'OTTO') {
                    self::$fontPath = $file;
                    return;
                }
            }
        }
    }

    private function requireFont(): string
    {
        if (self::$fontPath === null) {
            $this->markTestSkipped('No OpenType CFF font found on system');
        }
        return self::$fontPath;
    }

    public function testParseReturnsOpenTypeData(): void
    {
        $path = $this->requireFont();
        $parser = new OpenTypeParser($path);
        $data = $parser->parse();

        $this->assertInstanceOf(OpenTypeData::class, $data);
    }

    public function testParsedMetricsAreValid(): void
    {
        $path = $this->requireFont();
        $data = (new OpenTypeParser($path))->parse();

        $this->assertNotEmpty($data->postScriptName);
        $this->assertNotEmpty($data->familyName);
        $this->assertGreaterThan(0, $data->ascent);
        $this->assertLessThan(0, $data->descent);
        $this->assertGreaterThan(0, $data->unitsPerEm);
    }

    public function testFontBBoxHasFourElements(): void
    {
        $path = $this->requireFont();
        $data = (new OpenTypeParser($path))->parse();

        $this->assertCount(4, $data->fontBBox);
    }

    public function testCffBytesNonEmpty(): void
    {
        $path = $this->requireFont();
        $data = (new OpenTypeParser($path))->parse();

        $this->assertNotEmpty($data->cffBytes);
    }

    public function testFontBytesNonEmpty(): void
    {
        $path = $this->requireFont();
        $data = (new OpenTypeParser($path))->parse();

        $this->assertNotEmpty($data->fontBytes);
        $this->assertGreaterThan(strlen($data->cffBytes), strlen($data->fontBytes));
    }

    public function testCharWidthsPopulated(): void
    {
        $path = $this->requireFont();
        $data = (new OpenTypeParser($path))->parse();

        // Standard ASCII characters should have widths
        $this->assertArrayHasKey(65, $data->charWidths); // 'A'
        $this->assertGreaterThan(0, $data->charWidths[65]);
    }

    public function testUnicodeMapPopulated(): void
    {
        $path = $this->requireFont();
        $data = (new OpenTypeParser($path))->parse();

        $this->assertArrayHasKey(65, $data->unicodeMap); // 'A'
        $this->assertSame(65, $data->unicodeMap[65]); // byte 65 → U+0041
    }

    public function testFullUnicodeToGidPopulated(): void
    {
        $path = $this->requireFont();
        $data = (new OpenTypeParser($path))->parse();

        $this->assertNotEmpty($data->fullUnicodeToGid);
        // U+0041 ('A') should be mapped
        $this->assertArrayHasKey(0x41, $data->fullUnicodeToGid);
    }

    public function testGlyphWidthsPopulated(): void
    {
        $path = $this->requireFont();
        $data = (new OpenTypeParser($path))->parse();

        $this->assertNotEmpty($data->glyphWidths);
    }

    public function testFlagsHasNonsymbolic(): void
    {
        $path = $this->requireFont();
        $data = (new OpenTypeParser($path))->parse();

        $this->assertTrue(($data->flags & 32) !== 0, 'Nonsymbolic flag should be set');
    }

    public function testStemVInRange(): void
    {
        $path = $this->requireFont();
        $data = (new OpenTypeParser($path))->parse();

        $this->assertGreaterThanOrEqual(50, $data->stemV);
        $this->assertLessThanOrEqual(220, $data->stemV);
    }

    public function testEmbeddingCheck(): void
    {
        $path = $this->requireFont();
        $data = (new OpenTypeParser($path))->parse();

        $this->assertIsBool($data->embeddingAllowed);
    }

    public function testRejectsTrueTypeFont(): void
    {
        // Find a TrueType font
        $ttfPaths = [
            '/System/Library/Fonts/Supplemental/Arial.ttf',
            '/System/Library/Fonts/Supplemental/Verdana.ttf',
            '/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf',
        ];
        $ttfPath = null;
        foreach ($ttfPaths as $path) {
            if (file_exists($path)) {
                $ttfPath = $path;
                break;
            }
        }
        if ($ttfPath === null) {
            $this->markTestSkipped('No TrueType font found for rejection test');
        }

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Not an OpenType CFF font');
        (new OpenTypeParser($ttfPath))->parse();
    }
}
