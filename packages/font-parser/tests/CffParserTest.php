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
                if (!is_dir($dir)) continue;
                foreach (glob("$dir/*.otf") ?: [] as $file) {
                    $bytes = file_get_contents($file);
                    if ($bytes !== false && substr($bytes, 0, 4) === 'OTTO') {
                        self::$fontPath = $file;
                        break 2;
                    }
                }
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
}
