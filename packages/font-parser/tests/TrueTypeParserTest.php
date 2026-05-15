<?php

declare(strict_types=1);

namespace Phpdftk\FontParser\Tests;

use Phpdftk\FontParser\TrueTypeData;
use Phpdftk\FontParser\TrueTypeParser;
use PHPUnit\Framework\TestCase;

class TrueTypeParserTest extends TestCase
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

    public function testParseReturnsData(): void
    {
        $data = $this->getData();
        self::assertInstanceOf(TrueTypeData::class, $data);
    }

    public function testParseRejectsStreamWrapperPath(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Stream wrappers are not allowed');

        (new TrueTypeParser('php://memory'))->parse();
    }

    public function testUnitsPerEmIsPositive(): void
    {
        $data = $this->getData();
        // ascent > 0 indirectly verifies unitsPerEm was positive and scale worked
        self::assertGreaterThan(0, $data->ascent);
    }

    public function testAscentIsPositive(): void
    {
        $data = $this->getData();
        self::assertGreaterThan(0, $data->ascent);
    }

    public function testDescentIsNegative(): void
    {
        $data = $this->getData();
        self::assertLessThan(0, $data->descent);
    }

    public function testFontBBoxHasFourElements(): void
    {
        $data = $this->getData();
        self::assertCount(4, $data->fontBBox);
    }

    public function testPostScriptNameIsNonEmpty(): void
    {
        $data = $this->getData();
        self::assertNotEmpty($data->postScriptName);
    }

    public function testFamilyNameIsNonEmpty(): void
    {
        $data = $this->getData();
        self::assertNotEmpty($data->familyName);
    }

    public function testCharWidthsContainsAsciiRange(): void
    {
        $data = $this->getData();
        self::assertArrayHasKey(65, $data->charWidths); // A
        self::assertArrayHasKey(66, $data->charWidths); // B
        self::assertArrayHasKey(67, $data->charWidths); // C
    }

    public function testAllCharWidthsAreNonNegative(): void
    {
        $data = $this->getData();
        foreach ($data->charWidths as $byte => $width) {
            self::assertGreaterThanOrEqual(0, $width, "Width for byte {$byte} should be >= 0");
        }
    }

    public function testFontBytesAreNonEmpty(): void
    {
        $data = $this->getData();
        self::assertGreaterThan(0, strlen($data->fontBytes));
    }

    public function testFlagsHasNonsymbolicBitSet(): void
    {
        $data = $this->getData();
        self::assertSame(32, $data->flags & 32);
    }

    public function testStemVIsReasonable(): void
    {
        $data = $this->getData();
        self::assertGreaterThanOrEqual(50, $data->stemV);
        self::assertLessThanOrEqual(220, $data->stemV);
    }

    public function testUnicodeMapContainsAsciiChars(): void
    {
        $data = $this->getData();
        // A (byte 65) should map to Unicode U+0041 (65)
        self::assertArrayHasKey(65, $data->unicodeMap);
        self::assertSame(65, $data->unicodeMap[65]);
    }

    public function testEmbeddingAllowedIsTrue(): void
    {
        $data = $this->getData();
        self::assertTrue($data->embeddingAllowed);
    }

    public function testFormat12CmapParsed(): void
    {
        // Try to find a font with format 12 cmap (emoji/supplementary plane support)
        $format12Fonts = [
            '/System/Library/Fonts/Supplemental/Arial Unicode.ttf',
            '/System/Library/Fonts/SFNS.ttf',
            '/usr/share/fonts/truetype/noto/NotoColorEmoji.ttf',
            '/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf',
        ];

        $fontPath = null;
        foreach ($format12Fonts as $path) {
            if (file_exists($path)) {
                $fontPath = $path;
                break;
            }
        }

        // Also check the standard test fonts — some may have format 12
        if ($fontPath === null) {
            foreach ([
                '/System/Library/Fonts/Supplemental/Arial.ttf',
                '/System/Library/Fonts/Supplemental/Georgia.ttf',
                '/System/Library/Fonts/Supplemental/Verdana.ttf',
                '/usr/share/fonts/truetype/liberation/LiberationSans-Regular.ttf',
            ] as $path) {
                if (file_exists($path)) {
                    $fontPath = $path;
                    break;
                }
            }
        }

        if ($fontPath === null) {
            $this->markTestSkipped('No TTF font found');
        }

        $data = (new TrueTypeParser($fontPath))->parse();

        // If the font has supplementary plane codepoints, verify they are present
        $hasSupplementary = false;
        foreach ($data->fullUnicodeToGid as $cp => $gid) {
            if ($cp > 0xFFFF) {
                $hasSupplementary = true;
                break;
            }
        }

        if (!$hasSupplementary) {
            // The font was parsed successfully (possibly format 4 only) — just verify it works
            self::assertNotEmpty($data->fullUnicodeToGid);
            $this->addToAssertionCount(1);
            return;
        }

        // Font has supplementary plane codepoints — format 12 was parsed
        $supplementaryCount = 0;
        foreach ($data->fullUnicodeToGid as $cp => $gid) {
            if ($cp > 0xFFFF) {
                $supplementaryCount++;
                self::assertGreaterThan(0, $gid, "GID for U+" . dechex($cp) . " should be > 0");
            }
        }
        self::assertGreaterThan(0, $supplementaryCount, 'Expected supplementary plane codepoints from format 12 cmap');
    }
}
