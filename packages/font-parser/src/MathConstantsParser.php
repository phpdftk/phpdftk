<?php

declare(strict_types=1);

namespace Phpdftk\FontParser;

/**
 * Parser for the MathConstants sub-table of an OpenType MATH table.
 *
 * Layout per https://learn.microsoft.com/en-us/typography/opentype/spec/math#mathconstants-table :
 *
 *   - 4 plain Int16 fields up front (ScriptPercentScaleDown,
 *     ScriptScriptPercentScaleDown, DelimitedSubFormulaMinHeight,
 *     DisplayOperatorMinHeight).
 *   - 51 MathValueRecord fields (each = Int16 FWord + uint16 Device
 *     table offset). The painter only needs the FWord half.
 *   - 1 trailing Int16 percent (RadicalDegreeBottomRaisePercent).
 *
 * v1 reads the FWord half of each MathValueRecord and discards the
 * Device table offset. Device tables encode per-PPEM tweaks for
 * hinting; the painter doesn't ship a hinter so the corrections
 * would be noise.
 *
 * Total fixed-size payload:
 *   4 * 2 + 51 * (2 + 2) + 2 = 214 bytes.
 *
 * Throws on short input - a font's MathConstants record is fixed-
 * length per the spec and any short read is a malformed font.
 */
final class MathConstantsParser
{
    public function parse(string $bytes): MathConstants
    {
        if (strlen($bytes) < 214) {
            throw new \RuntimeException(sprintf(
                'MathConstants sub-table too short: got %d bytes, expected at least 214',
                strlen($bytes),
            ));
        }

        // Cursor walks the byte string; helpers advance it.
        $cursor = 0;
        $readInt16 = function () use ($bytes, &$cursor): int {
            $value = unpack('n', substr($bytes, $cursor, 2))[1] ?? 0;
            $cursor += 2;
            // Convert unsigned 16-bit to signed.
            return $value >= 0x8000 ? $value - 0x10000 : $value;
        };
        $readMathValue = function () use ($readInt16, &$cursor): int {
            $value = $readInt16();
            $cursor += 2; // Skip the Device table offset (uint16).
            return $value;
        };

        // 4 plain Int16 fields.
        $scriptPercentScaleDown = $readInt16();
        $scriptScriptPercentScaleDown = $readInt16();
        $delimitedSubFormulaMinHeight = $readInt16();
        $displayOperatorMinHeight = $readInt16();

        // 51 MathValueRecord fields in the spec-defined order.
        $mathLeading = $readMathValue();
        $axisHeight = $readMathValue();
        $accentBaseHeight = $readMathValue();
        $flattenedAccentBaseHeight = $readMathValue();
        $subscriptShiftDown = $readMathValue();
        $subscriptTopMax = $readMathValue();
        $subscriptBaselineDropMin = $readMathValue();
        $superscriptShiftUp = $readMathValue();
        $superscriptShiftUpCramped = $readMathValue();
        $superscriptBottomMin = $readMathValue();
        $superscriptBaselineDropMax = $readMathValue();
        $subSuperscriptGapMin = $readMathValue();
        $superscriptBottomMaxWithSubscript = $readMathValue();
        $spaceAfterScript = $readMathValue();
        $upperLimitGapMin = $readMathValue();
        $upperLimitBaselineRiseMin = $readMathValue();
        $lowerLimitGapMin = $readMathValue();
        $lowerLimitBaselineDropMin = $readMathValue();
        $stackTopShiftUp = $readMathValue();
        $stackTopDisplayStyleShiftUp = $readMathValue();
        $stackBottomShiftDown = $readMathValue();
        $stackBottomDisplayStyleShiftDown = $readMathValue();
        $stackGapMin = $readMathValue();
        $stackDisplayStyleGapMin = $readMathValue();
        $stretchStackTopShiftUp = $readMathValue();
        $stretchStackBottomShiftDown = $readMathValue();
        $stretchStackGapAboveMin = $readMathValue();
        $stretchStackGapBelowMin = $readMathValue();
        $fractionNumeratorShiftUp = $readMathValue();
        $fractionNumeratorDisplayStyleShiftUp = $readMathValue();
        $fractionDenominatorShiftDown = $readMathValue();
        $fractionDenominatorDisplayStyleShiftDown = $readMathValue();
        $fractionNumeratorGapMin = $readMathValue();
        $fractionNumDisplayStyleGapMin = $readMathValue();
        $fractionRuleThickness = $readMathValue();
        $fractionDenominatorGapMin = $readMathValue();
        $fractionDenomDisplayStyleGapMin = $readMathValue();
        $skewedFractionHorizontalGap = $readMathValue();
        $skewedFractionVerticalGap = $readMathValue();
        $overbarVerticalGap = $readMathValue();
        $overbarRuleThickness = $readMathValue();
        $overbarExtraAscender = $readMathValue();
        $underbarVerticalGap = $readMathValue();
        $underbarRuleThickness = $readMathValue();
        $underbarExtraDescender = $readMathValue();
        $radicalVerticalGap = $readMathValue();
        $radicalDisplayStyleVerticalGap = $readMathValue();
        $radicalRuleThickness = $readMathValue();
        $radicalExtraAscender = $readMathValue();
        $radicalKernBeforeDegree = $readMathValue();
        $radicalKernAfterDegree = $readMathValue();

        // Trailing plain Int16.
        $radicalDegreeBottomRaisePercent = $readInt16();

        return new MathConstants(
            scriptPercentScaleDown: $scriptPercentScaleDown,
            scriptScriptPercentScaleDown: $scriptScriptPercentScaleDown,
            delimitedSubFormulaMinHeight: $delimitedSubFormulaMinHeight,
            displayOperatorMinHeight: $displayOperatorMinHeight,
            mathLeading: $mathLeading,
            axisHeight: $axisHeight,
            accentBaseHeight: $accentBaseHeight,
            flattenedAccentBaseHeight: $flattenedAccentBaseHeight,
            subscriptShiftDown: $subscriptShiftDown,
            subscriptTopMax: $subscriptTopMax,
            subscriptBaselineDropMin: $subscriptBaselineDropMin,
            superscriptShiftUp: $superscriptShiftUp,
            superscriptShiftUpCramped: $superscriptShiftUpCramped,
            superscriptBottomMin: $superscriptBottomMin,
            superscriptBaselineDropMax: $superscriptBaselineDropMax,
            subSuperscriptGapMin: $subSuperscriptGapMin,
            superscriptBottomMaxWithSubscript: $superscriptBottomMaxWithSubscript,
            spaceAfterScript: $spaceAfterScript,
            upperLimitGapMin: $upperLimitGapMin,
            upperLimitBaselineRiseMin: $upperLimitBaselineRiseMin,
            lowerLimitGapMin: $lowerLimitGapMin,
            lowerLimitBaselineDropMin: $lowerLimitBaselineDropMin,
            stackTopShiftUp: $stackTopShiftUp,
            stackTopDisplayStyleShiftUp: $stackTopDisplayStyleShiftUp,
            stackBottomShiftDown: $stackBottomShiftDown,
            stackBottomDisplayStyleShiftDown: $stackBottomDisplayStyleShiftDown,
            stackGapMin: $stackGapMin,
            stackDisplayStyleGapMin: $stackDisplayStyleGapMin,
            stretchStackTopShiftUp: $stretchStackTopShiftUp,
            stretchStackBottomShiftDown: $stretchStackBottomShiftDown,
            stretchStackGapAboveMin: $stretchStackGapAboveMin,
            stretchStackGapBelowMin: $stretchStackGapBelowMin,
            fractionNumeratorShiftUp: $fractionNumeratorShiftUp,
            fractionNumeratorDisplayStyleShiftUp: $fractionNumeratorDisplayStyleShiftUp,
            fractionDenominatorShiftDown: $fractionDenominatorShiftDown,
            fractionDenominatorDisplayStyleShiftDown: $fractionDenominatorDisplayStyleShiftDown,
            fractionNumeratorGapMin: $fractionNumeratorGapMin,
            fractionNumDisplayStyleGapMin: $fractionNumDisplayStyleGapMin,
            fractionRuleThickness: $fractionRuleThickness,
            fractionDenominatorGapMin: $fractionDenominatorGapMin,
            fractionDenomDisplayStyleGapMin: $fractionDenomDisplayStyleGapMin,
            skewedFractionHorizontalGap: $skewedFractionHorizontalGap,
            skewedFractionVerticalGap: $skewedFractionVerticalGap,
            overbarVerticalGap: $overbarVerticalGap,
            overbarRuleThickness: $overbarRuleThickness,
            overbarExtraAscender: $overbarExtraAscender,
            underbarVerticalGap: $underbarVerticalGap,
            underbarRuleThickness: $underbarRuleThickness,
            underbarExtraDescender: $underbarExtraDescender,
            radicalVerticalGap: $radicalVerticalGap,
            radicalDisplayStyleVerticalGap: $radicalDisplayStyleVerticalGap,
            radicalRuleThickness: $radicalRuleThickness,
            radicalExtraAscender: $radicalExtraAscender,
            radicalKernBeforeDegree: $radicalKernBeforeDegree,
            radicalKernAfterDegree: $radicalKernAfterDegree,
            radicalDegreeBottomRaisePercent: $radicalDegreeBottomRaisePercent,
        );
    }
}
