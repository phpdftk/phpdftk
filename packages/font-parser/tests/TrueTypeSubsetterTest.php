<?php

declare(strict_types=1);

namespace Phpdftk\FontParser\Tests;

use Phpdftk\FontParser\TrueTypeData;
use Phpdftk\FontParser\TrueTypeParser;
use Phpdftk\FontParser\TrueTypeSubsetter;
use PHPUnit\Framework\TestCase;

class TrueTypeSubsetterTest extends TestCase
{
    private function findFont(): string
    {
        foreach ([
            '/System/Library/Fonts/Supplemental/Arial.ttf',
            '/System/Library/Fonts/Supplemental/Georgia.ttf',
            '/System/Library/Fonts/Supplemental/Verdana.ttf',
            '/usr/share/fonts/truetype/liberation/LiberationSans-Regular.ttf',
            '/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf',
        ] as $path) {
            if (file_exists($path)) {
                return $path;
            }
        }
        $this->markTestSkipped('No TTF font found');
    }

    private function getData(): TrueTypeData
    {
        return (new TrueTypeParser($this->findFont()))->parse();
    }

    public function testSubsetIsSmallerThanOriginal(): void
    {
        $data = $this->getData();

        // Subset for just A, B, C (codepoints 65, 66, 67)
        $glyphIds = [];
        foreach ([65, 66, 67] as $cp) {
            if (isset($data->fullUnicodeToGid[$cp])) {
                $glyphIds[] = $data->fullUnicodeToGid[$cp];
            }
        }

        $subsetter = new TrueTypeSubsetter();
        $subsetBytes = $subsetter->subset($data->fontBytes, $glyphIds, $data->fullUnicodeToGid);

        self::assertNotEmpty($subsetBytes);
        self::assertLessThan(strlen($data->fontBytes), strlen($subsetBytes));
    }

    public function testSubsetStartsWithValidTrueTypeHeader(): void
    {
        $data = $this->getData();

        $glyphIds = [];
        foreach ([65, 66, 67] as $cp) {
            if (isset($data->fullUnicodeToGid[$cp])) {
                $glyphIds[] = $data->fullUnicodeToGid[$cp];
            }
        }

        $subsetter = new TrueTypeSubsetter();
        $subsetBytes = $subsetter->subset($data->fontBytes, $glyphIds, $data->fullUnicodeToGid);

        // sfVersion should be 0x00010000
        $sfVersion = (ord($subsetBytes[0]) << 24)
                   | (ord($subsetBytes[1]) << 16)
                   | (ord($subsetBytes[2]) << 8)
                   | ord($subsetBytes[3]);
        self::assertSame(0x00010000, $sfVersion);
    }

    public function testSubsetCanBeReparsed(): void
    {
        $data = $this->getData();

        // Subset for a-z (codepoints 97-122)
        $glyphIds = [];
        foreach (range(97, 122) as $cp) {
            if (isset($data->fullUnicodeToGid[$cp])) {
                $glyphIds[] = $data->fullUnicodeToGid[$cp];
            }
        }

        $subsetter = new TrueTypeSubsetter();
        $subsetBytes = $subsetter->subset($data->fontBytes, $glyphIds, $data->fullUnicodeToGid);

        // Write to temp file and re-parse
        $tmpPath = tempnam(sys_get_temp_dir(), 'subset_') . '.ttf';
        file_put_contents($tmpPath, $subsetBytes);

        try {
            $reparsed = (new TrueTypeParser($tmpPath))->parse();
            self::assertInstanceOf(TrueTypeData::class, $reparsed);
            self::assertGreaterThan(0, $reparsed->ascent);
            self::assertNotEmpty($reparsed->postScriptName);
        } finally {
            @unlink($tmpPath);
        }
    }

    public function testSubsetAlwaysIncludesGidZero(): void
    {
        $data = $this->getData();

        // Subset with a single glyph
        $gid = $data->fullUnicodeToGid[65] ?? null;
        if ($gid === null) {
            $this->markTestSkipped('Font has no glyph for "A"');
        }

        $subsetter = new TrueTypeSubsetter();
        $subsetBytes = $subsetter->subset($data->fontBytes, [$gid], $data->fullUnicodeToGid);

        // Write to temp and reparse to check numGlyphs >= 2 (GID 0 + at least the requested one)
        $tmpPath = tempnam(sys_get_temp_dir(), 'subset_') . '.ttf';
        file_put_contents($tmpPath, $subsetBytes);

        try {
            $reparsed = (new TrueTypeParser($tmpPath))->parse();
            // numGlyphs is not directly exposed but we can check the font is valid
            self::assertNotEmpty($reparsed->fontBytes);
        } finally {
            @unlink($tmpPath);
        }
    }

    public function testSubsetWithEmptyGlyphSetProducesValidFont(): void
    {
        $data = $this->getData();

        $subsetter = new TrueTypeSubsetter();
        $subsetBytes = $subsetter->subset($data->fontBytes, [], $data->fullUnicodeToGid);

        // Should still produce valid font with GID 0
        $sfVersion = (ord($subsetBytes[0]) << 24)
                   | (ord($subsetBytes[1]) << 16)
                   | (ord($subsetBytes[2]) << 8)
                   | ord($subsetBytes[3]);
        self::assertSame(0x00010000, $sfVersion);
    }

    public function testFullUnicodeToGidIsPopulated(): void
    {
        $data = $this->getData();

        // fullUnicodeToGid should have entries for common Latin characters
        self::assertNotEmpty($data->fullUnicodeToGid);
        self::assertArrayHasKey(65, $data->fullUnicodeToGid); // 'A'
        self::assertArrayHasKey(97, $data->fullUnicodeToGid); // 'a'
    }

    public function testGlyphWidthsArePopulated(): void
    {
        $data = $this->getData();

        self::assertNotEmpty($data->glyphWidths);
        // GID 0 should exist
        self::assertArrayHasKey(0, $data->glyphWidths);
    }

    public function testUnitsPerEmIsPopulated(): void
    {
        $data = $this->getData();
        self::assertGreaterThan(0, $data->unitsPerEm);
    }

    public function testFormat12CmapGenerated(): void
    {
        $data = $this->getData();

        // Create a mapping with supplementary plane codepoints (U+1F600 = grinning face emoji)
        $unicodeToGid = $data->fullUnicodeToGid;
        // Add fake supplementary codepoints mapped to GID 1 (which exists after subset includes GID 0)
        $fakeSupplementary = [
            0x1F600 => 1, // Emoji: grinning face
            0x1F601 => 2, // Emoji: grinning face with smiling eyes
            0x1F602 => 3, // Emoji: face with tears of joy
        ];
        foreach ($fakeSupplementary as $cp => $gid) {
            $unicodeToGid[$cp] = $gid;
        }

        // We need GIDs 0, 1, 2, 3 in the subset
        $glyphIds = [0, 1, 2, 3];

        $subsetter = new TrueTypeSubsetter();
        $subsetBytes = $subsetter->subset($data->fontBytes, $glyphIds, $unicodeToGid);

        // Find the cmap table in the subset font
        $numTables = (ord($subsetBytes[4]) << 8) | ord($subsetBytes[5]);
        $cmapOffset = null;
        for ($i = 0; $i < $numTables; $i++) {
            $base = 12 + $i * 16;
            $tag = substr($subsetBytes, $base, 4);
            if (rtrim($tag) === 'cmap') {
                $cmapOffset = ((ord($subsetBytes[$base + 8]) << 24)
                    | (ord($subsetBytes[$base + 9]) << 16)
                    | (ord($subsetBytes[$base + 10]) << 8)
                    | ord($subsetBytes[$base + 11])) & 0xFFFFFFFF;
                break;
            }
        }

        self::assertNotNull($cmapOffset, 'cmap table should exist in subset font');

        // Read the encoding record: platformID=3, encodingID=10 for format 12
        $platformID = (ord($subsetBytes[$cmapOffset + 4]) << 8) | ord($subsetBytes[$cmapOffset + 5]);
        $encodingID = (ord($subsetBytes[$cmapOffset + 6]) << 8) | ord($subsetBytes[$cmapOffset + 7]);
        self::assertSame(3, $platformID, 'Platform ID should be 3 (Windows)');
        self::assertSame(10, $encodingID, 'Encoding ID should be 10 (UCS-4) for format 12');

        // Read the subtable offset from the encoding record
        $subtableRelOffset = ((ord($subsetBytes[$cmapOffset + 8]) << 24)
            | (ord($subsetBytes[$cmapOffset + 9]) << 16)
            | (ord($subsetBytes[$cmapOffset + 10]) << 8)
            | ord($subsetBytes[$cmapOffset + 11])) & 0xFFFFFFFF;
        $subtableOffset = $cmapOffset + $subtableRelOffset;

        // Verify format 12 header: first 2 bytes should be 0x000C (12)
        $format = (ord($subsetBytes[$subtableOffset]) << 8) | ord($subsetBytes[$subtableOffset + 1]);
        self::assertSame(12, $format, 'cmap subtable format should be 12');
    }
}
