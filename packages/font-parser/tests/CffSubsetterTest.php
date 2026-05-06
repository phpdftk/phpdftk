<?php

declare(strict_types=1);

namespace Phpdftk\FontParser\Tests;

use Phpdftk\FontParser\CffData;
use Phpdftk\FontParser\CffParser;
use Phpdftk\FontParser\CffSubsetter;
use Phpdftk\FontParser\OpenTypeParser;
use PHPUnit\Framework\TestCase;

class CffSubsetterTest extends TestCase
{
    private static ?string $fontPath = null;
    private static ?string $cffBytes = null;
    private static ?int $numGlyphs = null;

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

        if (self::$fontPath !== null) {
            $data = (new OpenTypeParser(self::$fontPath))->parse();
            self::$cffBytes = $data->cffBytes;
            self::$numGlyphs = count((new CffParser())->parse($data->cffBytes)->charStrings);
        }
    }

    private function requireCff(): string
    {
        if (self::$cffBytes === null) {
            $this->markTestSkipped('No OpenType CFF font found on system');
        }
        return self::$cffBytes;
    }

    public function testSubsetIsSmallerThanOriginal(): void
    {
        $cff = $this->requireCff();
        $subsetter = new CffSubsetter();

        // Subset to just 3 glyphs
        $subset = $subsetter->subset($cff, [1, 2, 3]);

        $this->assertLessThan(strlen($cff), strlen($subset));
    }

    public function testSubsetStartsWithValidCffHeader(): void
    {
        $cff = $this->requireCff();
        $subset = (new CffSubsetter())->subset($cff, [1, 2]);

        // CFF header: major=1, minor=0
        $this->assertSame(1, ord($subset[0]));
        $this->assertSame(0, ord($subset[1]));
    }

    public function testSubsetAlwaysIncludesGidZero(): void
    {
        $cff = $this->requireCff();
        // Request only GID 5 — GID 0 should still be included
        $subset = (new CffSubsetter())->subset($cff, [5]);

        $data = (new CffParser())->parse($subset);
        // Should have GID 0 + GID 5 = 2 charstrings
        $this->assertSame(2, count($data->charStrings));
    }

    public function testSubsetCharStringsCountMatchesRequestedGlyphs(): void
    {
        $cff = $this->requireCff();
        $requestedGids = [1, 2, 3, 10, 20];
        // Filter to valid GIDs
        $validGids = array_filter($requestedGids, fn(int $gid): bool => $gid < self::$numGlyphs);

        $subset = (new CffSubsetter())->subset($cff, $requestedGids);
        $data = (new CffParser())->parse($subset);

        // Count = requested valid GIDs + GID 0
        $expected = count(array_unique(array_merge([0], array_values($validGids))));
        $this->assertSame($expected, count($data->charStrings));
    }

    public function testSubsetPreservesGlobalSubroutines(): void
    {
        $cff = $this->requireCff();
        $originalData = (new CffParser())->parse($cff);

        $subset = (new CffSubsetter())->subset($cff, [1]);
        $subsetData = (new CffParser())->parse($subset);

        // Global subr index should be preserved
        $this->assertSame($originalData->globalSubrIndexData, $subsetData->globalSubrIndexData);
    }

    public function testSubsetWithEmptyGlyphSetProducesValidCff(): void
    {
        $cff = $this->requireCff();
        // Empty glyph set — should still include GID 0
        $subset = (new CffSubsetter())->subset($cff, []);

        $data = (new CffParser())->parse($subset);
        $this->assertSame(1, count($data->charStrings));
        $this->assertArrayHasKey(0, $data->charset);
    }

    public function testSubsetIsReparseable(): void
    {
        $cff = $this->requireCff();
        $subset = (new CffSubsetter())->subset($cff, [1, 2, 3, 4, 5]);

        // Should be parseable without exception
        $data = (new CffParser())->parse($subset);
        $this->assertInstanceOf(CffData::class, $data);
        $this->assertSame(1, $data->major);
    }

    public function testSubsetPreservesStringIndex(): void
    {
        $cff = $this->requireCff();
        $originalData = (new CffParser())->parse($cff);

        $subset = (new CffSubsetter())->subset($cff, [1, 2]);
        $subsetData = (new CffParser())->parse($subset);

        $this->assertSame($originalData->stringIndexData, $subsetData->stringIndexData);
    }
}
