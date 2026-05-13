<?php

declare(strict_types=1);

namespace Phpdftk\Encoding\Tests;

use PHPUnit\Framework\TestCase;
use Phpdftk\Encoding\GlyphList;
use Phpdftk\Encoding\WinAnsiEncoder;
use Phpdftk\Encoding\WinAnsiTable;

class WinAnsiEncoderTest extends TestCase
{
    public function testAsciiPassesThroughUnchanged(): void
    {
        $encoder = new WinAnsiEncoder();
        $this->assertSame('Hello, world.', $encoder->encode('Hello, world.'));
        $this->assertSame([], $encoder->getMissingCodepoints());
    }

    public function testEmDashEncodesTo0x97(): void
    {
        $encoder = new WinAnsiEncoder();
        $this->assertSame("\x97", $encoder->encode("\u{2014}"));
        $this->assertSame([], $encoder->getMissingCodepoints());
    }

    public function testMultiplySignEncodesTo0xD7(): void
    {
        $encoder = new WinAnsiEncoder();
        $this->assertSame("\xD7", $encoder->encode("\u{00D7}"));
    }

    public function testMiddleDotEncodesTo0xB7(): void
    {
        $encoder = new WinAnsiEncoder();
        $this->assertSame("\xB7", $encoder->encode("\u{00B7}"));
    }

    public function testEveryUniqueGlyphRoundTrips(): void
    {
        // For each unique glyph in WinAnsi, encoding its Unicode codepoint
        // should produce some byte that maps back to that same glyph in the
        // forward table. (A few glyphs — e.g. 'space' and 'hyphen' — appear
        // at two byte positions; reverse mapping is one-to-many in those
        // cases and we just need the result to be valid.)
        $encoder = new WinAnsiEncoder();
        $forward = WinAnsiTable::getTable();
        $seen = [];
        foreach ($forward as $byte => $glyph) {
            if ($glyph === '.notdef' || isset($seen[$glyph])) {
                continue;
            }
            $seen[$glyph] = true;
            $cp = GlyphList::glyphToUnicode($glyph);
            if ($cp === null) {
                continue;
            }
            $encoded = $encoder->encode(mb_chr($cp, 'UTF-8'));
            $this->assertSame(
                1,
                strlen($encoded),
                sprintf('U+%04X (%s) encoded to %d bytes, expected 1', $cp, $glyph, strlen($encoded)),
            );
            $resultGlyph = $forward[ord($encoded)] ?? null;
            $this->assertSame(
                $glyph,
                $resultGlyph,
                sprintf('U+%04X (%s) round-tripped to glyph %s', $cp, $glyph, var_export($resultGlyph, true)),
            );
        }
        $this->assertSame([], $encoder->getMissingCodepoints());
    }

    public function testMixedAsciiAndExtended(): void
    {
        $encoder = new WinAnsiEncoder();
        $this->assertSame(
            "caf\xE9 \x97 r\xE9sum\xE9 \xB7 20\xD7 20",
            $encoder->encode("café — résumé · 20× 20"),
        );
        $this->assertSame([], $encoder->getMissingCodepoints());
    }

    public function testUnmappableCodepointFallsBackToQuestionMark(): void
    {
        $encoder = new WinAnsiEncoder();
        // U+2713 CHECK MARK has no WinAnsi byte.
        $this->assertSame('?', $encoder->encode("\u{2713}"));
        $this->assertSame([0x2713], $encoder->getMissingCodepoints());
    }

    public function testMissingCodepointsPreservesOrderAndDuplicates(): void
    {
        $encoder = new WinAnsiEncoder();
        $encoder->encode("\u{2713}\u{2318}\u{2713}");
        $this->assertSame([0x2713, 0x2318, 0x2713], $encoder->getMissingCodepoints());
    }

    public function testMissingCodepointsAccumulateAcrossCalls(): void
    {
        $encoder = new WinAnsiEncoder();
        $encoder->encode("hello \u{2713}");
        $encoder->encode("world \u{2318}");
        $this->assertSame([0x2713, 0x2318], $encoder->getMissingCodepoints());
    }
}
