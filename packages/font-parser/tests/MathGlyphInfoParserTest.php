<?php

declare(strict_types=1);

namespace Phpdftk\FontParser\Tests;

use Phpdftk\FontParser\MathGlyphInfo;
use Phpdftk\FontParser\MathGlyphInfoParser;
use Phpdftk\FontParser\OpenTypeData;
use Phpdftk\FontParser\OpenTypeParser;
use Phpdftk\FontParser\WoffParser;
use PHPUnit\Framework\TestCase;

/**
 * Tests for the MathGlyphInfo sub-table parser.
 *
 * Two test surfaces:
 *
 *   1. Empty / truncated input handling - returns empty struct, no
 *      crash. Useful guard since real fonts in the wild have all
 *      shapes of partial tables.
 *
 *   2. WPT targeted fonts - e.g.
 *      `largeop-displayoperatorminheight2000-2AFF-italiccorrection3000.woff`
 *      sets the italic correction of the glyph at U+2AFF to 3000.
 *      We resolve the GID via the font's cmap and assert the parser
 *      reads the expected value.
 */
final class MathGlyphInfoParserTest extends TestCase
{
    private const string WPT_MATH_FONTS_DIR =
        __DIR__ . '/../../../vendor-data/wpt/fonts/math';

    public function testEmptyBytesReturnsEmptyStruct(): void
    {
        $info = (new MathGlyphInfoParser())->parse('');
        self::assertSame([], $info->italicCorrections);
        self::assertSame([], $info->topAccentAttachments);
        self::assertSame([], $info->extendedShapes);
        self::assertSame('', $info->kernInfoBytes);
    }

    public function testTruncatedBytesReturnsEmptyStruct(): void
    {
        // A 4-byte header isn't enough (need 8 for all four offsets).
        $info = (new MathGlyphInfoParser())->parse("\x00\x00\x00\x00");
        self::assertSame([], $info->italicCorrections);
    }

    public function testAllZeroOffsetsReturnsAllEmptyMaps(): void
    {
        // 8-byte header with every offset zeroed - explicitly tells
        // the parser every sub-sub-table is absent.
        $info = (new MathGlyphInfoParser())->parse(str_repeat("\x00", 8));
        self::assertSame([], $info->italicCorrections);
        self::assertSame([], $info->topAccentAttachments);
        self::assertSame([], $info->extendedShapes);
        self::assertSame('', $info->kernInfoBytes);
    }

    public function testItalicCorrectionValueAppearsInMap(): void
    {
        // The WPT fixture `...italiccorrection3000.woff` populates
        // some glyph in the font with italic correction 3000. The
        // exact GID depends on the font's MathVariants layout (often
        // a stretched variant of U+2AFF, not the base cmap entry),
        // so we don't pin the key - we pin the *value* appearing in
        // the map, which proves the parser walked the coverage +
        // value array correctly.
        $font = $this->loadFont(
            'largeop-displayoperatorminheight2000-2AFF-italiccorrection3000.woff',
        );
        self::assertNotNull($font->mathTable);
        self::assertTrue($font->mathTable->hasMathGlyphInfo());
        $info = (new MathGlyphInfoParser())
            ->parse($font->mathTable->mathGlyphInfoBytes);

        self::assertContains(3000, $info->italicCorrections);
    }

    public function testItalicCorrectionCountReturnedAccurately(): void
    {
        // WPT fixture name "...italiccorrection3000.woff" sets the
        // italic correction on (at least) one glyph. Confirm the
        // parser surfaces a non-zero entry (zero entries means the
        // coverage/value walk silently dropped data).
        $font = $this->loadFont(
            'largeop-displayoperatorminheight2000-2AFF-italiccorrection3000.woff',
        );
        self::assertNotNull($font->mathTable);
        $info = (new MathGlyphInfoParser())
            ->parse($font->mathTable->mathGlyphInfoBytes);
        $nonZero = array_filter($info->italicCorrections, fn(int $v): bool => $v !== 0);
        self::assertNotEmpty(
            $nonZero,
            'Targeted WPT fixture must surface a non-zero italic '
            . 'correction in MathGlyphInfo',
        );
    }

    public function testParserDoesNotCrashOnAllWptMathFonts(): void
    {
        // Walk every WPT math fixture - the parser must never throw,
        // even when the per-font layout varies (sub-tables present
        // / absent in any combination). Skips if the submodule
        // isn't checked out.
        $dir = self::WPT_MATH_FONTS_DIR;
        if (!is_dir($dir)) {
            self::markTestSkipped(
                "WPT math fonts dir not available: $dir",
            );
        }
        $parser = new MathGlyphInfoParser();
        $touched = 0;
        foreach (glob($dir . '/*.woff') ?: [] as $path) {
            $otfBytes = WoffParser::decompress($path);
            $font = OpenTypeParser::fromBytes($otfBytes)->parse();
            if ($font->mathTable === null) {
                continue;
            }
            $parser->parse($font->mathTable->mathGlyphInfoBytes);
            $touched++;
        }
        self::assertGreaterThan(
            0,
            $touched,
            'At least one WPT font should have a MATH table',
        );
    }

    public function testReturnedObjectHasExpectedShape(): void
    {
        // Sanity check the value-object property surface so renames
        // / additions show up loud.
        $properties = (new \ReflectionClass(MathGlyphInfo::class))->getProperties();
        $names = array_map(static fn($p) => $p->getName(), $properties);
        self::assertContains('italicCorrections', $names);
        self::assertContains('topAccentAttachments', $names);
        self::assertContains('extendedShapes', $names);
        self::assertContains('kernInfoBytes', $names);
    }

    private function loadFont(string $woffName): OpenTypeData
    {
        $path = self::WPT_MATH_FONTS_DIR . '/' . $woffName;
        if (!is_file($path)) {
            self::markTestSkipped(
                "WPT math font fixture not available: $path. "
                . "Run `git submodule update --init vendor-data/wpt`.",
            );
        }
        $otfBytes = WoffParser::decompress($path);
        return OpenTypeParser::fromBytes($otfBytes)->parse();
    }
}
