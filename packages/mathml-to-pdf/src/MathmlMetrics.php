<?php

declare(strict_types=1);

namespace Phpdftk\MathmlToPdf;

use Phpdftk\FontParser\MathConstants;

/**
 * Adapter that surfaces MathML layout constants either from a real
 * OpenType MATH-table font (when one is loaded) or from the
 * tracer-bullet defaults the painter has been using.
 *
 * The painter calls accessor methods (`scriptScale()`,
 * `superscriptShiftUp()`, ...) rather than reading raw constants.
 * Each accessor checks the optional {@see MathConstants} and falls
 * back to the historical default when none is present so the
 * standard-font path stays unchanged.
 *
 * Values are expressed in em (caller multiplies by `fontSize` for
 * points) so the rest of the painter doesn't need to know whether
 * the metrics came from a font.
 *
 * Future slices wire more constants and replace more painter
 * hard-codes; this initial cut wires the script-scaling +
 * sub/superscript shift values that drive every script construct.
 */
final readonly class MathmlMetrics
{
    /** Painter default when no math font is loaded - matches the
     *  values baked into the tracer-bullet (0.7 / 0.5 / 0.3). */
    public const float DEFAULT_SCRIPT_SCALE = 0.7;
    public const float DEFAULT_SCRIPT_SCRIPT_SCALE = 0.55;
    public const float DEFAULT_SUPERSCRIPT_SHIFT_UP_EM = 0.5;
    public const float DEFAULT_SUBSCRIPT_SHIFT_DOWN_EM = 0.3;
    public const float DEFAULT_FRACTION_RULE_THICKNESS_EM = 0.0625;
    public const float DEFAULT_AXIS_HEIGHT_EM = 0.25;

    public function __construct(
        public ?MathConstants $constants = null,
        public int $unitsPerEm = 1000,
    ) {}

    /**
     * Scale-down applied to script-level glyphs (sub/superscripts,
     * under/over scripts). Returned as a multiplier on the parent
     * font size (e.g. 0.7 -> render scripts at 70% size).
     */
    public function scriptScale(): float
    {
        if ($this->constants === null) {
            return self::DEFAULT_SCRIPT_SCALE;
        }
        return $this->constants->scriptPercentScaleDown / 100.0;
    }

    /**
     * Scale-down applied to script-script-level glyphs (scripts of
     * scripts).
     */
    public function scriptScriptScale(): float
    {
        if ($this->constants === null) {
            return self::DEFAULT_SCRIPT_SCRIPT_SCALE;
        }
        return $this->constants->scriptScriptPercentScaleDown / 100.0;
    }

    /**
     * Baseline shift up for superscripts, in em. Spec uses two
     * fields (regular + cramped); v1 painter only knows the regular
     * shift, so we return that.
     */
    public function superscriptShiftUpEm(): float
    {
        if ($this->constants === null) {
            return self::DEFAULT_SUPERSCRIPT_SHIFT_UP_EM;
        }
        return $this->constants->superscriptShiftUp / (float) $this->unitsPerEm;
    }

    /**
     * Baseline shift down for subscripts, in em. Positive number;
     * the painter negates it when applying.
     */
    public function subscriptShiftDownEm(): float
    {
        if ($this->constants === null) {
            return self::DEFAULT_SUBSCRIPT_SHIFT_DOWN_EM;
        }
        return $this->constants->subscriptShiftDown / (float) $this->unitsPerEm;
    }

    /**
     * Fraction-bar thickness in em.
     */
    public function fractionRuleThicknessEm(): float
    {
        if ($this->constants === null) {
            return self::DEFAULT_FRACTION_RULE_THICKNESS_EM;
        }
        return $this->constants->fractionRuleThickness / (float) $this->unitsPerEm;
    }

    /**
     * Position of the mathematical axis above baseline, in em. Used
     * to set the fraction-bar Y, large-operator centers, etc.
     */
    public function axisHeightEm(): float
    {
        if ($this->constants === null) {
            return self::DEFAULT_AXIS_HEIGHT_EM;
        }
        return $this->constants->axisHeight / (float) $this->unitsPerEm;
    }

    /** Fraction-numerator raise above baseline, in em.
     *  Display-style default reflects the typical 'fraction in
     *  display equation' shift, taller than the inline form. */
    public const float DEFAULT_FRACTION_NUMERATOR_SHIFT_UP_EM = 0.4;
    public const float DEFAULT_FRACTION_NUMERATOR_DISPLAY_SHIFT_UP_EM = 0.7;

    public function fractionNumeratorShiftUpEm(bool $displayStyle = false): float
    {
        if ($this->constants === null) {
            return $displayStyle
                ? self::DEFAULT_FRACTION_NUMERATOR_DISPLAY_SHIFT_UP_EM
                : self::DEFAULT_FRACTION_NUMERATOR_SHIFT_UP_EM;
        }
        $funits = $displayStyle
            ? $this->constants->fractionNumeratorDisplayStyleShiftUp
            : $this->constants->fractionNumeratorShiftUp;
        return $funits / (float) $this->unitsPerEm;
    }

    /** Fraction-denominator drop below baseline, in em. */
    public const float DEFAULT_FRACTION_DENOMINATOR_SHIFT_DOWN_EM = 0.4;
    public const float DEFAULT_FRACTION_DENOMINATOR_DISPLAY_SHIFT_DOWN_EM = 0.7;

    public function fractionDenominatorShiftDownEm(bool $displayStyle = false): float
    {
        if ($this->constants === null) {
            return $displayStyle
                ? self::DEFAULT_FRACTION_DENOMINATOR_DISPLAY_SHIFT_DOWN_EM
                : self::DEFAULT_FRACTION_DENOMINATOR_SHIFT_DOWN_EM;
        }
        $funits = $displayStyle
            ? $this->constants->fractionDenominatorDisplayStyleShiftDown
            : $this->constants->fractionDenominatorShiftDown;
        return $funits / (float) $this->unitsPerEm;
    }

    /** Overbar / vinculum raise above baseline, in em. */
    public const float DEFAULT_OVERBAR_VERTICAL_OFFSET_EM = 0.85;

    public function overbarVerticalOffsetEm(): float
    {
        if ($this->constants === null) {
            return self::DEFAULT_OVERBAR_VERTICAL_OFFSET_EM;
        }
        // overbarExtraAscender sits just above the cap height; we
        // approximate "vinculum height" as accentBaseHeight +
        // overbarExtraAscender. Mathml font designers tune this
        // pair to position the overbar just above the tallest
        // ascender in the typical content.
        return ($this->constants->accentBaseHeight + $this->constants->overbarExtraAscender)
            / (float) $this->unitsPerEm;
    }

    /** Overbar / vinculum thickness, in em. */
    public const float DEFAULT_OVERBAR_RULE_THICKNESS_EM = 0.0625;

    public function overbarRuleThicknessEm(): float
    {
        if ($this->constants === null) {
            return self::DEFAULT_OVERBAR_RULE_THICKNESS_EM;
        }
        return $this->constants->overbarRuleThickness / (float) $this->unitsPerEm;
    }

    /** Overscript raise above baseline, in em. */
    public const float DEFAULT_OVER_RAISE_EM = 0.85;

    public function overscriptRaiseEm(): float
    {
        if ($this->constants === null) {
            return self::DEFAULT_OVER_RAISE_EM;
        }
        // Use accentBaseHeight as the over-script attachment height
        // since it tracks the typical above-base placement in math
        // fonts (the same datum used for accents).
        return $this->constants->accentBaseHeight / (float) $this->unitsPerEm;
    }

    /** Underscript drop below baseline, in em (positive; negate when applying). */
    public const float DEFAULT_UNDER_DROP_EM = 0.5;

    public function underscriptDropEm(): float
    {
        if ($this->constants === null) {
            return self::DEFAULT_UNDER_DROP_EM;
        }
        return $this->constants->underbarVerticalGap / (float) $this->unitsPerEm;
    }

    /**
     * True iff this metrics object was built from a real math
     * font. The painter uses this to choose stretchy paths /
     * font-derived behaviour vs the standard-font fallback.
     */
    public function isMathFontActive(): bool
    {
        return $this->constants !== null;
    }
}
