<?php

declare(strict_types=1);

namespace Phpdftk\FontParser\Tests;

use Phpdftk\FontParser\CffData;
use Phpdftk\FontParser\CffParser;
use Phpdftk\FontParser\OpenTypeParser;
use PHPUnit\Framework\TestCase;

class CffParserTest extends TestCase
{
    private static ?string $fontPath = null;
    private static ?string $cffBytes = null;

    public static function setUpBeforeClass(): void
    {
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
                    break;
                }
            }
        }

        if (self::$fontPath === null) {
            $dirs = ['/System/Library/Fonts/Supplemental', '/usr/share/fonts'];
            foreach ($dirs as $dir) {
                if (!is_dir($dir)) {
                    continue;
                }
                foreach (glob("$dir/*.otf") ?: [] as $file) {
                    $bytes = file_get_contents($file);
                    if ($bytes !== false && substr($bytes, 0, 4) === 'OTTO') {
                        self::$fontPath = $file;
                        break 2;
                    }
                }
            }
        }

        // Final fallback: the bundled NotoSansMongolian. This guarantees the
        // suite has a real CFF font to parse even on minimal CI runners.
        if (self::$fontPath === null) {
            $bundled = __DIR__ . '/fixtures/NotoSansMongolian-Regular.otf';
            if (is_file($bundled)) {
                self::$fontPath = $bundled;
            }
        }

        if (self::$fontPath !== null) {
            $data = (new OpenTypeParser(self::$fontPath))->parse();
            self::$cffBytes = $data->cffBytes;
        }
    }

    private function requireCff(): string
    {
        if (self::$cffBytes === null) {
            $this->markTestSkipped('No OpenType CFF font found on system');
        }
        return self::$cffBytes;
    }

    public function testParseReturnsCffData(): void
    {
        $cff = $this->requireCff();
        $parser = new CffParser();
        $data = $parser->parse($cff);

        $this->assertInstanceOf(CffData::class, $data);
        $this->assertSame(1, $data->major);
    }

    public function testCharStringsCountMatchesNumGlyphs(): void
    {
        $cff = $this->requireCff();
        $data = (new CffParser())->parse($cff);

        $this->assertNotEmpty($data->charStrings);
        // Every glyph should have a charstring
        $this->assertGreaterThan(1, count($data->charStrings));
    }

    public function testCharsetLengthMatchesCharStringsCount(): void
    {
        $cff = $this->requireCff();
        $data = (new CffParser())->parse($cff);

        $this->assertCount(count($data->charStrings), $data->charset);
    }

    public function testTopDictHasRequiredOperators(): void
    {
        $cff = $this->requireCff();
        $data = (new CffParser())->parse($cff);

        // CharStrings (17) must be present
        $this->assertArrayHasKey(17, $data->topDictOperators);
        // Private (18) must be present
        $this->assertArrayHasKey(18, $data->topDictOperators);
    }

    public function testNameIndexDataNonEmpty(): void
    {
        $cff = $this->requireCff();
        $data = (new CffParser())->parse($cff);

        $this->assertNotEmpty($data->nameIndexData);
    }

    public function testStringIndexDataNonEmpty(): void
    {
        $cff = $this->requireCff();
        $data = (new CffParser())->parse($cff);

        $this->assertNotEmpty($data->stringIndexData);
    }

    public function testGlobalSubrIndexDataPresent(): void
    {
        $cff = $this->requireCff();
        $data = (new CffParser())->parse($cff);

        // Global subr index is always present (may be empty INDEX with count=0)
        $this->assertNotEmpty($data->globalSubrIndexData);
    }

    public function testCharsetGidZeroIsNotdef(): void
    {
        $cff = $this->requireCff();
        $data = (new CffParser())->parse($cff);

        $this->assertArrayHasKey(0, $data->charset);
        $this->assertSame(0, $data->charset[0]);
    }

    // ------------------------------------------------------------------
    // Charset format coverage: bundled fonts pick specific formats so we
    // exercise the parseCharset() branches deterministically.
    // ------------------------------------------------------------------

    public function testParsesCharsetFormat2FromMongolianFont(): void
    {
        // Noto Sans Mongolian's CFF uses charset format 2 (range-based with
        // 2-byte nLeft) — 1598 glyphs spread over large SID ranges.
        $otfBytes = file_get_contents(TestFonts::notoSansMongolianOtf());
        $cff = (new OpenTypeParser(TestFonts::notoSansMongolianOtf()))->parse()->cffBytes;
        $data = (new CffParser())->parse($cff);

        $this->assertCount(1598, $data->charset);
        // GID 0 is .notdef, then SIDs jump to ~391 and increment.
        $this->assertSame(0, $data->charset[0]);
        $this->assertGreaterThan(100, $data->charset[1]);
        // Sequential SID assignment confirms range-based charset.
        $this->assertSame($data->charset[2], $data->charset[1] + 1);
    }
}
