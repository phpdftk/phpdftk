<?php

declare(strict_types=1);

namespace Phpdftk\FontParser;

/**
 * Parsed MathConstants sub-table from an OpenType MATH table.
 *
 * Spec: https://learn.microsoft.com/en-us/typography/opentype/spec/math#mathconstants-table
 *
 * Every value is in font design units (FUnit) unless otherwise
 * noted; the consumer scales by (size / unitsPerEm) to get points.
 * Percent-scale fields are signed Int16 percentages (so a 0.7
 * scale-down is stored as 70).
 *
 * The painter consumes these constants to replace the hardcoded
 * em-fractions baked into the tracer-bullet (SCRIPT_FONT_SCALE,
 * SUP_RAISE_EM, fraction-bar thickness, etc.) with font-correct
 * values that match the rendered glyph metrics.
 *
 * v1 of this parser handles the FWord half of each MathValueRecord
 * field. The Device-table half (per-PPEM corrections for hinting)
 * is read but discarded - hinting at math-typesetting sizes is
 * negligible and adds parser surface area for no observable gain.
 */
final readonly class MathConstants
{
    public function __construct(
        // ----- script size scaling -----------------------------------
        /** Percent scale-down for script-level glyphs (e.g. 70 => 70%). */
        public int $scriptPercentScaleDown,
        /** Percent scale-down for script-script-level glyphs (e.g. 55). */
        public int $scriptScriptPercentScaleDown,
        /** Minimum height (in design units) at which a delimiter is
         *  drawn as the next stretched variant. Used by stretchy
         *  brackets and radicals. */
        public int $delimitedSubFormulaMinHeight,
        /** Minimum height (design units) at which an operator switches
         *  to its display (large) form. */
        public int $displayOperatorMinHeight,

        // ----- vertical metrics --------------------------------------
        /** Recommended gap between top of a math row and the top of
         *  its tallest glyph. */
        public int $mathLeading,
        /** Vertical position of the mathematical axis (the centerline
         *  for fraction bars, sum operators, etc.) above baseline. */
        public int $axisHeight,
        /** Vertical alignment for an accent's bottom relative to the
         *  base glyph's top. */
        public int $accentBaseHeight,
        /** Vertical alignment for a flattened accent's bottom. */
        public int $flattenedAccentBaseHeight,

        // ----- subscripts --------------------------------------------
        public int $subscriptShiftDown,
        public int $subscriptTopMax,
        public int $subscriptBaselineDropMin,

        // ----- superscripts ------------------------------------------
        public int $superscriptShiftUp,
        public int $superscriptShiftUpCramped,
        public int $superscriptBottomMin,
        public int $superscriptBaselineDropMax,

        // ----- sub+sup gap -------------------------------------------
        public int $subSuperscriptGapMin,
        public int $superscriptBottomMaxWithSubscript,

        // ----- space around super/sub --------------------------------
        public int $spaceAfterScript,

        // ----- upper / lower limit attachments (sum, integral) -------
        public int $upperLimitGapMin,
        public int $upperLimitBaselineRiseMin,
        public int $lowerLimitGapMin,
        public int $lowerLimitBaselineDropMin,

        // ----- stack layout (binomial-like) --------------------------
        public int $stackTopShiftUp,
        public int $stackTopDisplayStyleShiftUp,
        public int $stackBottomShiftDown,
        public int $stackBottomDisplayStyleShiftDown,
        public int $stackGapMin,
        public int $stackDisplayStyleGapMin,

        // ----- stretch stack (under-over with stretchy op) -----------
        public int $stretchStackTopShiftUp,
        public int $stretchStackBottomShiftDown,
        public int $stretchStackGapAboveMin,
        public int $stretchStackGapBelowMin,

        // ----- fractions ---------------------------------------------
        public int $fractionNumeratorShiftUp,
        public int $fractionNumeratorDisplayStyleShiftUp,
        public int $fractionDenominatorShiftDown,
        public int $fractionDenominatorDisplayStyleShiftDown,
        public int $fractionNumeratorGapMin,
        public int $fractionNumDisplayStyleGapMin,
        /** Thickness of the horizontal bar in fractions. */
        public int $fractionRuleThickness,
        public int $fractionDenominatorGapMin,
        public int $fractionDenomDisplayStyleGapMin,

        // ----- skewed fractions --------------------------------------
        public int $skewedFractionHorizontalGap,
        public int $skewedFractionVerticalGap,

        // ----- overbars / underbars ----------------------------------
        public int $overbarVerticalGap,
        public int $overbarRuleThickness,
        public int $overbarExtraAscender,
        public int $underbarVerticalGap,
        public int $underbarRuleThickness,
        public int $underbarExtraDescender,

        // ----- radicals ----------------------------------------------
        public int $radicalVerticalGap,
        public int $radicalDisplayStyleVerticalGap,
        public int $radicalRuleThickness,
        public int $radicalExtraAscender,
        public int $radicalKernBeforeDegree,
        public int $radicalKernAfterDegree,
        /** Percent of radical sign height the degree base rises (e.g. 60). */
        public int $radicalDegreeBottomRaisePercent,
    ) {}
}
