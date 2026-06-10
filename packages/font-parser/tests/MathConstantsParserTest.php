<?php

declare(strict_types=1);

namespace Phpdftk\FontParser\Tests;

use Phpdftk\FontParser\MathConstants;
use Phpdftk\FontParser\MathConstantsParser;
use Phpdftk\FontParser\OpenTypeParser;
use Phpdftk\FontParser\WoffParser;
use PHPUnit\Framework\TestCase;

/**
 * Tests for the MathConstants sub-table parser.
 *
 * Each WPT math font under vendor-data/wpt/fonts/math/ encodes the
 * MathConstants value it targets directly in its filename. We use
 * those names to pin specific fields:
 *
 *   - axisheight5000-verticalarrow14000.woff -> axisHeight = 5000
 *   - fraction-rulethickness10000.woff       -> fractionRuleThickness = 10000
 *   - limits-upperlimitgapmin7000.woff       -> upperLimitGapMin = 7000
 *   - etc.
 *
 * The WPT fonts use unitsPerEm 1000 (so the FUnit values match the
 * em*1000 numbers in the names).
 *
 * Each test skips itself when the WPT submodule isn't checked out
 * so CI without submodules doesn't fail hard.
 */
final class MathConstantsParserTest extends TestCase
{
    private const string WPT_MATH_FONTS_DIR =
        __DIR__ . '/../../../vendor-data/wpt/fonts/math';

    private function loadConstants(string $woffName): MathConstants
    {
        $path = self::WPT_MATH_FONTS_DIR . '/' . $woffName;
        if (!is_file($path)) {
            self::markTestSkipped(
                "WPT math font fixture not available: $path. "
                . "Run `git submodule update --init vendor-data/wpt`.",
            );
        }
        $otfBytes = WoffParser::decompress($path);
        $font = OpenTypeParser::fromBytes($otfBytes)->parse();
        self::assertNotNull(
            $font->mathTable,
            "MATH table missing from $woffName",
        );
        return (new MathConstantsParser())->parse(
            $font->mathTable->mathConstantsBytes,
        );
    }

    public function testThrowsOnShortInput(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/too short/');
        (new MathConstantsParser())->parse('');
    }

    public function testAxisHeightFromTargetedFont(): void
    {
        // 'axisheight5000-verticalarrow14000.woff' embeds 5000 for
        // axisHeight per the WPT naming convention.
        $c = $this->loadConstants('axisheight5000-verticalarrow14000.woff');
        self::assertSame(5000, $c->axisHeight);
    }

    public function testFractionRuleThicknessFromTargetedFont(): void
    {
        $c = $this->loadConstants('fraction-rulethickness10000.woff');
        self::assertSame(10000, $c->fractionRuleThickness);
    }

    public function testFractionAxisHeightSetByPairedFont(): void
    {
        // 'fraction-axisheight7000-rulethickness1000.woff' sets both
        // axisHeight=7000 AND fractionRuleThickness=1000 - confirms
        // multiple fields parse from the same font.
        $c = $this->loadConstants('fraction-axisheight7000-rulethickness1000.woff');
        self::assertSame(7000, $c->axisHeight);
        self::assertSame(1000, $c->fractionRuleThickness);
    }

    public function testFractionNumeratorShiftUpFromTargetedFont(): void
    {
        $c = $this->loadConstants(
            'fraction-numeratorshiftup11000-axisheight1000-rulethickness1000.woff',
        );
        self::assertSame(11000, $c->fractionNumeratorShiftUp);
    }

    public function testFractionDenominatorShiftDownFromTargetedFont(): void
    {
        $c = $this->loadConstants(
            'fraction-denominatorshiftdown3000-axisheight1000-rulethickness1000.woff',
        );
        self::assertSame(3000, $c->fractionDenominatorShiftDown);
    }

    public function testFractionNumeratorGapMinFromTargetedFont(): void
    {
        $c = $this->loadConstants('fraction-numeratorgapmin9000-rulethickness1000.woff');
        self::assertSame(9000, $c->fractionNumeratorGapMin);
    }

    public function testFractionDenominatorGapMinFromTargetedFont(): void
    {
        $c = $this->loadConstants('fraction-denominatorgapmin4000-rulethickness1000.woff');
        self::assertSame(4000, $c->fractionDenominatorGapMin);
    }

    public function testFractionNumDisplayStyleGapMinFromTargetedFont(): void
    {
        $c = $this->loadConstants(
            'fraction-numeratordisplaystylegapmin8000-rulethickness1000.woff',
        );
        self::assertSame(8000, $c->fractionNumDisplayStyleGapMin);
    }

    public function testFractionDenomDisplayStyleGapMinFromTargetedFont(): void
    {
        $c = $this->loadConstants(
            'fraction-denominatordisplaystylegapmin5000-rulethickness1000.woff',
        );
        self::assertSame(5000, $c->fractionDenomDisplayStyleGapMin);
    }

    public function testFractionNumeratorDisplayStyleShiftUpFromTargetedFont(): void
    {
        $c = $this->loadConstants(
            'fraction-numeratordisplaystyleshiftup2000-axisheight1000-rulethickness1000.woff',
        );
        self::assertSame(2000, $c->fractionNumeratorDisplayStyleShiftUp);
    }

    public function testFractionDenominatorDisplayStyleShiftDownFromTargetedFont(): void
    {
        $c = $this->loadConstants(
            'fraction-denominatordisplaystyleshiftdown6000-axisheight1000-rulethickness1000.woff',
        );
        self::assertSame(6000, $c->fractionDenominatorDisplayStyleShiftDown);
    }

    public function testLowerLimitGapMinFromTargetedFont(): void
    {
        $c = $this->loadConstants('limits-lowerlimitgapmin11000.woff');
        self::assertSame(11000, $c->lowerLimitGapMin);
    }

    public function testLowerLimitBaselineDropMinFromTargetedFont(): void
    {
        $c = $this->loadConstants('limits-lowerlimitbaselinedropmin3000.woff');
        self::assertSame(3000, $c->lowerLimitBaselineDropMin);
    }

    public function testUpperLimitGapMinFromTargetedFont(): void
    {
        $c = $this->loadConstants('limits-upperlimitgapmin7000.woff');
        self::assertSame(7000, $c->upperLimitGapMin);
    }

    public function testUpperLimitBaselineRiseMinFromTargetedFont(): void
    {
        $c = $this->loadConstants('limits-upperlimitbaselinerisemin5000.woff');
        self::assertSame(5000, $c->upperLimitBaselineRiseMin);
    }

    public function testDisplayOperatorMinHeightFromTargetedFont(): void
    {
        $c = $this->loadConstants('largeop-displayoperatorminheight5000.woff');
        self::assertSame(5000, $c->displayOperatorMinHeight);
    }

    public function testAllFieldsParsePopulated(): void
    {
        // Sanity check: every field in MathConstants should be
        // populated to *something* by parsing one of the WPT fonts,
        // not silently left at zero by an off-by-one in the cursor
        // walk.
        $c = $this->loadConstants('fraction-rulethickness10000.woff');
        $properties = (new \ReflectionClass(MathConstants::class))->getProperties();
        // 4 plain Int16 + 51 MathValueRecord + 1 trailing Int16 = 56.
        self::assertCount(56, $properties);
    }
}
