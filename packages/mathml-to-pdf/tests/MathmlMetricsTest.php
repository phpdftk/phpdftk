<?php

declare(strict_types=1);

namespace Phpdftk\MathmlToPdf\Tests;

use Phpdftk\FontParser\MathConstants;
use Phpdftk\MathmlToPdf\MathmlMetrics;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for {@see MathmlMetrics} - the adapter that gives the
 * painter math-font-derived layout constants when one is loaded
 * and falls back to the tracer-bullet defaults when one isn't.
 *
 * The fallback path matters: every test in the suite that pre-dates
 * the math-font work was written against the 0.7 / 0.5 / 0.3 em
 * defaults. The default-constructed metrics must produce identical
 * output.
 */
final class MathmlMetricsTest extends TestCase
{
    public function testDefaultsMatchTracerBulletConstants(): void
    {
        $m = new MathmlMetrics();
        self::assertSame(MathmlMetrics::DEFAULT_SCRIPT_SCALE, $m->scriptScale());
        self::assertSame(
            MathmlMetrics::DEFAULT_SUPERSCRIPT_SHIFT_UP_EM,
            $m->superscriptShiftUpEm(),
        );
        self::assertSame(
            MathmlMetrics::DEFAULT_SUBSCRIPT_SHIFT_DOWN_EM,
            $m->subscriptShiftDownEm(),
        );
        self::assertFalse($m->isMathFontActive());
    }

    public function testNullConstantsKeepFallbackBehaviour(): void
    {
        $m = new MathmlMetrics(constants: null, unitsPerEm: 2048);
        // unitsPerEm gets ignored when constants are absent.
        self::assertSame(0.7, $m->scriptScale());
    }

    public function testScriptScaleDerivedFromConstants(): void
    {
        $constants = $this->makeConstants([
            'scriptPercentScaleDown' => 80,
            'scriptScriptPercentScaleDown' => 60,
        ]);
        $m = new MathmlMetrics(constants: $constants, unitsPerEm: 1000);
        self::assertSame(0.80, $m->scriptScale());
        self::assertSame(0.60, $m->scriptScriptScale());
    }

    public function testSuperscriptShiftUpDerivedFromConstants(): void
    {
        $constants = $this->makeConstants([
            'superscriptShiftUp' => 600,
        ]);
        $m = new MathmlMetrics(constants: $constants, unitsPerEm: 1000);
        self::assertEqualsWithDelta(0.6, $m->superscriptShiftUpEm(), 0.0001);
    }

    public function testSubscriptShiftDownDerivedFromConstants(): void
    {
        $constants = $this->makeConstants([
            'subscriptShiftDown' => 400,
        ]);
        $m = new MathmlMetrics(constants: $constants, unitsPerEm: 1000);
        self::assertEqualsWithDelta(0.4, $m->subscriptShiftDownEm(), 0.0001);
    }

    public function testFractionRuleThicknessDerivedFromConstants(): void
    {
        $constants = $this->makeConstants([
            'fractionRuleThickness' => 80,
        ]);
        $m = new MathmlMetrics(constants: $constants, unitsPerEm: 1000);
        self::assertEqualsWithDelta(0.08, $m->fractionRuleThicknessEm(), 0.0001);
    }

    public function testAxisHeightDerivedFromConstants(): void
    {
        $constants = $this->makeConstants([
            'axisHeight' => 250,
        ]);
        $m = new MathmlMetrics(constants: $constants, unitsPerEm: 1000);
        self::assertEqualsWithDelta(0.25, $m->axisHeightEm(), 0.0001);
    }

    public function testCustomUnitsPerEmScalesCorrectly(): void
    {
        // A 2048-unitsPerEm font (common for TrueType) with
        // superscriptShiftUp = 1024 should give 0.5 em.
        $constants = $this->makeConstants([
            'superscriptShiftUp' => 1024,
        ]);
        $m = new MathmlMetrics(constants: $constants, unitsPerEm: 2048);
        self::assertEqualsWithDelta(0.5, $m->superscriptShiftUpEm(), 0.0001);
    }

    public function testFractionNumeratorShiftUpDerivedFromConstants(): void
    {
        $constants = $this->makeConstants([
            'fractionNumeratorShiftUp' => 500,
        ]);
        $m = new MathmlMetrics(constants: $constants, unitsPerEm: 1000);
        self::assertEqualsWithDelta(0.5, $m->fractionNumeratorShiftUpEm(), 0.0001);
    }

    public function testFractionDenominatorShiftDownDerivedFromConstants(): void
    {
        $constants = $this->makeConstants([
            'fractionDenominatorShiftDown' => 350,
        ]);
        $m = new MathmlMetrics(constants: $constants, unitsPerEm: 1000);
        self::assertEqualsWithDelta(0.35, $m->fractionDenominatorShiftDownEm(), 0.0001);
    }

    public function testOverbarVerticalOffsetSumsAccentBaseAndOverbarAscender(): void
    {
        $constants = $this->makeConstants([
            'accentBaseHeight' => 700,
            'overbarExtraAscender' => 150,
        ]);
        $m = new MathmlMetrics(constants: $constants, unitsPerEm: 1000);
        self::assertEqualsWithDelta(0.85, $m->overbarVerticalOffsetEm(), 0.0001);
    }

    public function testOverbarRuleThicknessDerivedFromConstants(): void
    {
        $constants = $this->makeConstants([
            'overbarRuleThickness' => 100,
        ]);
        $m = new MathmlMetrics(constants: $constants, unitsPerEm: 1000);
        self::assertEqualsWithDelta(0.1, $m->overbarRuleThicknessEm(), 0.0001);
    }

    public function testOverscriptRaiseUsesAccentBaseHeight(): void
    {
        $constants = $this->makeConstants([
            'accentBaseHeight' => 850,
        ]);
        $m = new MathmlMetrics(constants: $constants, unitsPerEm: 1000);
        self::assertEqualsWithDelta(0.85, $m->overscriptRaiseEm(), 0.0001);
    }

    public function testUnderscriptDropUsesUnderbarVerticalGap(): void
    {
        $constants = $this->makeConstants([
            'underbarVerticalGap' => 500,
        ]);
        $m = new MathmlMetrics(constants: $constants, unitsPerEm: 1000);
        self::assertEqualsWithDelta(0.5, $m->underscriptDropEm(), 0.0001);
    }

    public function testFractionRadicalDefaultsMatchTracerBullet(): void
    {
        $m = new MathmlMetrics();
        self::assertSame(0.4, $m->fractionNumeratorShiftUpEm());
        self::assertSame(0.4, $m->fractionDenominatorShiftDownEm());
        self::assertSame(0.85, $m->overbarVerticalOffsetEm());
        self::assertSame(0.85, $m->overscriptRaiseEm());
        self::assertSame(0.5, $m->underscriptDropEm());
    }

    public function testIsMathFontActiveReflectsConstantsPresence(): void
    {
        self::assertFalse((new MathmlMetrics())->isMathFontActive());
        $constants = $this->makeConstants([]);
        self::assertTrue((new MathmlMetrics($constants))->isMathFontActive());
    }

    /**
     * Build a MathConstants with the given overrides; everything
     * else gets a benign default so the constructor is satisfied.
     *
     * @param array<string, int> $overrides
     */
    private function makeConstants(array $overrides): MathConstants
    {
        $defaults = [
            'scriptPercentScaleDown' => 70,
            'scriptScriptPercentScaleDown' => 55,
            'delimitedSubFormulaMinHeight' => 0,
            'displayOperatorMinHeight' => 0,
            'mathLeading' => 0,
            'axisHeight' => 0,
            'accentBaseHeight' => 0,
            'flattenedAccentBaseHeight' => 0,
            'subscriptShiftDown' => 0,
            'subscriptTopMax' => 0,
            'subscriptBaselineDropMin' => 0,
            'superscriptShiftUp' => 0,
            'superscriptShiftUpCramped' => 0,
            'superscriptBottomMin' => 0,
            'superscriptBaselineDropMax' => 0,
            'subSuperscriptGapMin' => 0,
            'superscriptBottomMaxWithSubscript' => 0,
            'spaceAfterScript' => 0,
            'upperLimitGapMin' => 0,
            'upperLimitBaselineRiseMin' => 0,
            'lowerLimitGapMin' => 0,
            'lowerLimitBaselineDropMin' => 0,
            'stackTopShiftUp' => 0,
            'stackTopDisplayStyleShiftUp' => 0,
            'stackBottomShiftDown' => 0,
            'stackBottomDisplayStyleShiftDown' => 0,
            'stackGapMin' => 0,
            'stackDisplayStyleGapMin' => 0,
            'stretchStackTopShiftUp' => 0,
            'stretchStackBottomShiftDown' => 0,
            'stretchStackGapAboveMin' => 0,
            'stretchStackGapBelowMin' => 0,
            'fractionNumeratorShiftUp' => 0,
            'fractionNumeratorDisplayStyleShiftUp' => 0,
            'fractionDenominatorShiftDown' => 0,
            'fractionDenominatorDisplayStyleShiftDown' => 0,
            'fractionNumeratorGapMin' => 0,
            'fractionNumDisplayStyleGapMin' => 0,
            'fractionRuleThickness' => 0,
            'fractionDenominatorGapMin' => 0,
            'fractionDenomDisplayStyleGapMin' => 0,
            'skewedFractionHorizontalGap' => 0,
            'skewedFractionVerticalGap' => 0,
            'overbarVerticalGap' => 0,
            'overbarRuleThickness' => 0,
            'overbarExtraAscender' => 0,
            'underbarVerticalGap' => 0,
            'underbarRuleThickness' => 0,
            'underbarExtraDescender' => 0,
            'radicalVerticalGap' => 0,
            'radicalDisplayStyleVerticalGap' => 0,
            'radicalRuleThickness' => 0,
            'radicalExtraAscender' => 0,
            'radicalKernBeforeDegree' => 0,
            'radicalKernAfterDegree' => 0,
            'radicalDegreeBottomRaisePercent' => 0,
        ];
        $merged = array_merge($defaults, $overrides);
        return new MathConstants(...$merged);
    }
}
