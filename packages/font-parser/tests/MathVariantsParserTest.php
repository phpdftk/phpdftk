<?php

declare(strict_types=1);

namespace Phpdftk\FontParser\Tests;

use Phpdftk\FontParser\MathGlyphAssembly;
use Phpdftk\FontParser\MathGlyphConstruction;
use Phpdftk\FontParser\MathVariants;
use Phpdftk\FontParser\MathVariantsParser;
use Phpdftk\FontParser\OpenTypeData;
use Phpdftk\FontParser\OpenTypeParser;
use Phpdftk\FontParser\WoffParser;
use PHPUnit\Framework\TestCase;

/**
 * Tests for the MathVariants sub-table parser.
 *
 * Stretchy delimiters are the most complex part of the MATH table:
 * each base glyph carries a sorted list of pre-drawn variants plus
 * an optional assembly recipe for arbitrary sizes. We verify the
 * parser surfaces the right number of constructions, picks up the
 * minConnectorOverlap header field, and doesn't crash on the
 * full WPT corpus.
 */
final class MathVariantsParserTest extends TestCase
{
    private const string WPT_MATH_FONTS_DIR =
        __DIR__ . '/../../../vendor-data/wpt/fonts/math';

    public function testEmptyBytesReturnsEmptyStruct(): void
    {
        $v = (new MathVariantsParser())->parse('');
        self::assertSame(0, $v->minConnectorOverlap);
        self::assertSame([], $v->verticalConstructions);
        self::assertSame([], $v->horizontalConstructions);
    }

    public function testTruncatedHeaderReturnsEmptyStruct(): void
    {
        $v = (new MathVariantsParser())->parse(str_repeat("\x00", 8));
        self::assertSame([], $v->verticalConstructions);
    }

    public function testAllZeroHeaderReturnsEmptyMaps(): void
    {
        // Valid 10-byte header but all-zero - no coverage tables,
        // no constructions.
        $v = (new MathVariantsParser())->parse(str_repeat("\x00", 10));
        self::assertSame(0, $v->minConnectorOverlap);
        self::assertSame([], $v->verticalConstructions);
        self::assertSame([], $v->horizontalConstructions);
    }

    public function testParsesVerticalConstructionsFromTargetedFont(): void
    {
        // axisheight5000-verticalarrow14000.woff embeds a vertical-
        // arrow construction (the "verticalarrow14000" piece). The
        // parser must surface a non-empty verticalConstructions map.
        $font = $this->loadFont('axisheight5000-verticalarrow14000.woff');
        self::assertNotNull($font->mathTable);
        self::assertTrue($font->mathTable->hasMathVariants());
        $v = (new MathVariantsParser())
            ->parse($font->mathTable->mathVariantsBytes);
        self::assertNotEmpty($v->verticalConstructions);
    }

    public function testVerticalConstructionHasSensibleShape(): void
    {
        $font = $this->loadFont('axisheight5000-verticalarrow14000.woff');
        self::assertNotNull($font->mathTable);
        $v = (new MathVariantsParser())
            ->parse($font->mathTable->mathVariantsBytes);
        // The targeted construction carries at least one variant at
        // advance near 14000 (per the filename; WPT fixtures encode
        // off-by-one variations on top of the named target).
        $largeAdvance = 0;
        foreach ($v->verticalConstructions as $construction) {
            self::assertInstanceOf(MathGlyphConstruction::class, $construction);
            foreach ($construction->variants as $variant) {
                if ($variant['advance'] > $largeAdvance) {
                    $largeAdvance = $variant['advance'];
                }
            }
        }
        self::assertGreaterThanOrEqual(
            14000,
            $largeAdvance,
            'Expected a variant near advance 14000 per the WPT filename',
        );
    }

    public function testParserDoesNotCrashOnAllWptMathFonts(): void
    {
        // Walk every WPT math fixture - parser must never throw on
        // any of the WPT-generated layouts.
        $dir = self::WPT_MATH_FONTS_DIR;
        if (!is_dir($dir)) {
            self::markTestSkipped("WPT math fonts dir not available: $dir");
        }
        $parser = new MathVariantsParser();
        $withVariants = 0;
        foreach (glob($dir . '/*.woff') ?: [] as $path) {
            $otfBytes = WoffParser::decompress($path);
            $font = OpenTypeParser::fromBytes($otfBytes)->parse();
            if ($font->mathTable === null) {
                continue;
            }
            $v = $parser->parse($font->mathTable->mathVariantsBytes);
            if ($v->verticalConstructions !== [] || $v->horizontalConstructions !== []) {
                $withVariants++;
            }
        }
        self::assertGreaterThan(
            0,
            $withVariants,
            'At least one WPT font should have non-empty MathVariants',
        );
    }

    public function testMathGlyphAssemblyShapeIsTyped(): void
    {
        // The assembly value object's surface - if WPT fonts surface
        // an assembly we type-check it; otherwise this test just
        // confirms the class exists. Walk the WPT corpus to find one
        // with an assembly.
        $dir = self::WPT_MATH_FONTS_DIR;
        if (!is_dir($dir)) {
            self::markTestSkipped("WPT math fonts dir not available: $dir");
        }
        $parser = new MathVariantsParser();
        foreach (glob($dir . '/*.woff') ?: [] as $path) {
            $otfBytes = WoffParser::decompress($path);
            $font = OpenTypeParser::fromBytes($otfBytes)->parse();
            if ($font->mathTable === null) {
                continue;
            }
            $v = $parser->parse($font->mathTable->mathVariantsBytes);
            foreach ($v->verticalConstructions as $construction) {
                if ($construction->assembly !== null) {
                    self::assertInstanceOf(MathGlyphAssembly::class, $construction->assembly);
                    self::assertIsArray($construction->assembly->parts);
                    return;
                }
            }
        }
        // No WPT font supplies an assembly - that's fine, we still
        // verify the type is correct via reflection.
        $rc = new \ReflectionClass(MathGlyphAssembly::class);
        self::assertTrue($rc->hasProperty('italicsCorrection'));
        self::assertTrue($rc->hasProperty('parts'));
    }

    public function testReturnedObjectsHaveTheExpectedTypes(): void
    {
        // Type-level pin so renames blow up loud.
        $rc = new \ReflectionClass(MathVariants::class);
        self::assertTrue($rc->hasProperty('minConnectorOverlap'));
        self::assertTrue($rc->hasProperty('verticalConstructions'));
        self::assertTrue($rc->hasProperty('horizontalConstructions'));
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
