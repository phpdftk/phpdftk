<?php

declare(strict_types=1);

namespace Phpdftk\FontParser\Tests;

use Phpdftk\FontParser\GsubParser;
use Phpdftk\FontParser\OpenTypeParser;
use Phpdftk\FontParser\TextShaper;
use Phpdftk\FontParser\TrueTypeParser;
use PHPUnit\Framework\TestCase;

class GsubParserTest extends TestCase
{
    private static ?string $ttfPath = null;
    private static ?string $otfPath = null;

    public static function setUpBeforeClass(): void
    {
        // Find a TrueType font with GSUB
        $ttfCandidates = [
            '/System/Library/Fonts/Supplemental/Arial.ttf',
            '/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf',
        ];
        foreach ($ttfCandidates as $path) {
            if (file_exists($path)) {
                $bytes = file_get_contents($path);
                if ($bytes !== false && unpack('N', $bytes)[1] === 0x00010000) {
                    // Check for GSUB table
                    $numTables = unpack('n', $bytes, 4)[1];
                    for ($i = 0; $i < $numTables; $i++) {
                        $tag = substr($bytes, 12 + $i * 16, 4);
                        if ($tag === 'GSUB') {
                            self::$ttfPath = $path;
                            break 2;
                        }
                    }
                }
            }
        }

        // Find an OpenType CFF font with GSUB
        $otfCandidates = [
            '/System/Library/Fonts/Supplemental/STIXGeneral.otf',
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

    public function testGsubParserReturnLigatures(): void
    {
        if (self::$ttfPath === null) {
            $this->markTestSkipped('No TrueType font with GSUB found');
        }

        $data = (new TrueTypeParser(self::$ttfPath))->parse();

        if ($data->ligatures === null || $data->ligatures === []) {
            $this->markTestSkipped('Font has no ligature data');
        }

        // Verify structure: array keyed by GID with ligature rules
        $this->assertIsArray($data->ligatures);

        $firstGid = array_key_first($data->ligatures);
        $this->assertIsInt($firstGid);

        $rules = $data->ligatures[$firstGid];
        $this->assertIsArray($rules);
        $this->assertArrayHasKey('components', $rules[0]);
        $this->assertArrayHasKey('ligature', $rules[0]);
    }

    public function testGsubParserOpenType(): void
    {
        if (self::$otfPath === null) {
            $this->markTestSkipped('No OpenType CFF font found');
        }

        $data = (new OpenTypeParser(self::$otfPath))->parse();

        // Even if no ligatures, the field should be set
        $this->assertTrue($data->ligatures === null || is_array($data->ligatures));
    }

    public function testTextShaperAppliesLigature(): void
    {
        // Simulate: GID 1='f', GID 2='i', ligature GID 100='fi'
        $ligatures = [
            1 => [
                ['components' => [2], 'ligature' => 100],
            ],
        ];

        $result = TextShaper::applyLigatures([1, 2], $ligatures);
        $this->assertSame([100], $result);
    }

    public function testTextShaperLongestMatchFirst(): void
    {
        // GID 1='f', GID 2='f', GID 3='i'
        // ffi ligature (GID 200) should match before fi (GID 100)
        $ligatures = [
            1 => [
                ['components' => [1, 3], 'ligature' => 200], // ffi (sorted first — longest)
                ['components' => [3], 'ligature' => 100],     // fi
                ['components' => [1], 'ligature' => 150],     // ff
            ],
        ];

        $result = TextShaper::applyLigatures([1, 1, 3], $ligatures);
        $this->assertSame([200], $result); // ffi → single ligature glyph
    }

    public function testTextShaperPreservesNonLigatureGlyphs(): void
    {
        $ligatures = [
            1 => [
                ['components' => [2], 'ligature' => 100],
            ],
        ];

        // 'h' 'e' 'f' 'i' 'n' → 'h' 'e' 'fi' 'n'
        $result = TextShaper::applyLigatures([10, 20, 1, 2, 30], $ligatures);
        $this->assertSame([10, 20, 100, 30], $result);
    }

    public function testTextShaperEmptyInput(): void
    {
        $this->assertSame([], TextShaper::applyLigatures([], []));
    }

    public function testTextShaperNoMatchingLigatures(): void
    {
        $ligatures = [
            1 => [
                ['components' => [2], 'ligature' => 100],
            ],
        ];

        // No 'f' glyph (GID 1) in input
        $result = TextShaper::applyLigatures([10, 20, 30], $ligatures);
        $this->assertSame([10, 20, 30], $result);
    }

    public function testTextShaperMultipleLigatures(): void
    {
        // 'f' 'i' 'n' 'f' 'l'
        $ligatures = [
            1 => [
                ['components' => [2], 'ligature' => 100],  // fi
                ['components' => [3], 'ligature' => 101],  // fl
            ],
        ];

        $result = TextShaper::applyLigatures([1, 2, 30, 1, 3], $ligatures);
        $this->assertSame([100, 30, 101], $result); // fi, n, fl
    }
}
