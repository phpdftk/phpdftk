<?php

declare(strict_types=1);

namespace Phpdftk\Text\Tests;

use Phpdftk\FontParser\OpenTypeParser;
use Phpdftk\Text\Shaper;
use Phpdftk\Text\ShapingContext;
use PHPUnit\Framework\TestCase;

/**
 * End-to-end smoke: parse a real OTF font via `phpdftk/font-parser`,
 * shape a string against it, and verify the round-trip produces non-zero
 * glyphs + non-zero advances. Asserts the Shaper actually consumes
 * `OpenTypeData` correctly rather than relying purely on hand-rolled
 * fixtures.
 */
final class ShaperIntegrationTest extends TestCase
{
    private const string FONT_PATH = __DIR__ . '/../../../tests/fixtures/fonts/NotoSansMongolian-Regular.otf';

    protected function setUp(): void
    {
        if (!is_file(self::FONT_PATH)) {
            self::markTestSkipped('Mongolian test font missing — shared fixtures not initialised');
        }
    }

    public function testShapesNonZeroGlyphsForMongolianText(): void
    {
        $font = (new OpenTypeParser(self::FONT_PATH))->parse();
        $shaper = new Shaper();

        // U+1820 is MONGOLIAN LETTER A — the font ships glyphs for the
        // basic Mongolian range so the lookup should resolve to a real GID.
        $text = "\u{1820}\u{1820}";
        $run = $shaper->shapeRun($text, new ShapingContext($font, 12.0, script: 'Mong'));

        self::assertCount(2, $run->glyphs);
        self::assertNotSame(0, $run->glyphs[0]->glyphId, 'Mongolian A should map to a real GID');
        self::assertGreaterThan(0.0, $run->totalAdvance);
    }
}
