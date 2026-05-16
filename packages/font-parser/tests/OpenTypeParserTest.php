<?php

declare(strict_types=1);

namespace Phpdftk\FontParser\Tests;

use Phpdftk\FontParser\OpenTypeData;
use Phpdftk\FontParser\OpenTypeParser;
use PHPUnit\Framework\TestCase;

class OpenTypeParserTest extends TestCase
{
    private static ?string $fontPath = null;

    public static function setUpBeforeClass(): void
    {
        // The legacy tests below assume basic Latin coverage (e.g. 'A' / 0x41),
        // so prefer system fonts that have Latin. Newer feature-specific
        // tests at the bottom of this file use the bundled
        // NotoSansMongolian directly via TestFonts.
        $candidates = [
            '/System/Library/Fonts/Supplemental/STIXGeneral.otf',
            '/System/Library/Fonts/LastResort.otf',
            '/usr/share/fonts/opentype/stix/STIXGeneral.otf',
        ];

        foreach ($candidates as $path) {
            if (file_exists($path)) {
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

    public function testFromBytesParsesIdenticalResult(): void
    {
        $path = $this->requireFont();
        $fileData = (new OpenTypeParser($path))->parse();

        $fontBytes = file_get_contents($path);
        $byteData = OpenTypeParser::fromBytes($fontBytes)->parse();

        // Both entry points should produce the same parsed result
        $this->assertSame($fileData->postScriptName, $byteData->postScriptName);
        $this->assertSame($fileData->unitsPerEm, $byteData->unitsPerEm);
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

    // ------------------------------------------------------------------
    // Bundled-font coverage tests: features that not every system OTF has.
    // These use NotoSansMongolian-Regular.otf which is committed under
    // tests/fixtures/ and excluded from the published Composer artifact.
    // ------------------------------------------------------------------

    public function testParsesFormat12CmapFromMongolianFont(): void
    {
        // Noto Sans Mongolian's cmap has both format-4 and format-12
        // subtables. Format-12 (full Unicode, 32-bit codepoints) is preferred
        // when present and exercises parseCmapFormat12().
        $data = (new OpenTypeParser(TestFonts::notoSansMongolianOtf()))->parse();
        // Mongolian script lives in U+1800-U+18AF — well inside BMP — but
        // the format-12 subtable still ranges across it. Confirm we picked
        // up a glyph for U+1820 (MONGOLIAN LETTER A).
        $this->assertArrayHasKey(0x1820, $data->fullUnicodeToGid);
        $this->assertGreaterThan(0, $data->fullUnicodeToGid[0x1820]);
    }

    public function testParsesVerticalMetricsFromMongolianFont(): void
    {
        // Noto Sans Mongolian carries vhea/vmtx (Mongolian is a vertical
        // script). Verify the vertical-widths branch in parse() runs.
        $data = (new OpenTypeParser(TestFonts::notoSansMongolianOtf()))->parse();
        $this->assertNotNull($data->verticalWidths);
        $this->assertNotEmpty($data->verticalWidths);
        foreach (array_slice($data->verticalWidths, 0, 5, preserve_keys: true) as $gid => $width) {
            $this->assertIsInt($gid);
            $this->assertIsInt($width);
        }
    }

    public function testParsesLargeCffCharsetFromMongolianFont(): void
    {
        // The Mongolian font has >1500 glyphs, so its CFF charset is encoded
        // in format 1 or 2 (range-based) rather than format 0 (flat array).
        // This exercises CffParser::parseCharset's non-zero format branches.
        // glyphWidths reflects the full numGlyphs (every glyph in the CFF),
        // not just the codepoints reachable via cmap.
        $data = (new OpenTypeParser(TestFonts::notoSansMongolianOtf()))->parse();
        $this->assertGreaterThan(1000, count($data->glyphWidths));
    }

    public function testParsesFormat4CmapFromTifinaghFont(): void
    {
        // Noto Sans Tifinagh's cmap has only format-4 subtables (no format-12).
        // The Mongolian font lets format-12 win the priority race; Tifinagh
        // is the dedicated fixture that forces parseCmapFormat4().
        $data = (new OpenTypeParser(TestFonts::notoSansTifinaghOtf()))->parse();
        // Tifinagh letter YA (U+2D30) sits in the Tifinagh block.
        $this->assertArrayHasKey(0x2D30, $data->fullUnicodeToGid);
    }
}
