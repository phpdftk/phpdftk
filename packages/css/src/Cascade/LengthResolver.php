<?php

declare(strict_types=1);

namespace Phpdftk\Css\Cascade;

use Phpdftk\Css\Value\Length;
use Phpdftk\Css\Value\LengthUnit;
use Phpdftk\Css\Value\Percentage;
use Phpdftk\Css\Value\Value;

/**
 * Converts relative-unit `Length` values into absolute `px` Lengths against
 * a `LengthContext`. Used by the cascade in its computed-value pass.
 *
 * Mapping per CSS Values 4 §6:
 *  - Absolute (px, pt, pc, cm, mm, q, in) — converted directly via the
 *    CSS canonical relations (1in = 96px, 1pt = 96/72 px, etc.).
 *  - em / rem / ex / ch / lh / rlh — multiplied against the appropriate
 *    font-size reference from the context.
 *  - vw / vh / vmin / vmax / svw / svh / lvw / lvh / dvw / dvh — viewport
 *    references (the small/large/dynamic variants collapse onto the same
 *    print-medium viewport since there's no UI chrome to subtract).
 *  - Percentage — multiplied against the context's `percentageBasis`.
 */
final class LengthResolver
{
    /**
     * Maximum absolute pixel value that layout will operate on,
     * mirroring browser conventions: Blink caps at `LayoutUnit::Max`
     * (~16.7M CSS px from the int32 fixed-point representation),
     * WebKit clamps `kFixedPointDenominator * INT_MAX / ...` to the
     * same neighbourhood. Beyond this:
     *
     *  - float-precision is gone (mantissa is 24 bits, so values
     *    above 2^24 lose integer accuracy);
     *  - layout math becomes meaningless to a reader;
     *  - downstream code sized to these dimensions (content streams,
     *    column-balance arrays, paint-region rects) allocates
     *    gigabytes and OOMs on adversarial CSS like
     *    `padding: 2880804336vmax 854269137% 347744005in 2487922492pt`
     *    or `aspect-ratio: 1/0.00000000000001`.
     *
     * Authored CSS values stay untouched on the parsed Length /
     * Percentage objects; this constant only bounds the floats that
     * enter layout via {@see toPx()} / {@see resolveValue()}.
     *
     * Reference: WPT crashtests + `*-crash.html` fixtures (1,012
     * corpus-wide as of WPT @ 2026-06-08). See phpdftk/phpdftk#28.
     */
    public const MAX_PX = 16777216.0;   // 2^24

    /**
     * Clamp a resolved pixel value into the safe layout range.
     * NaN collapses to 0 (CSS Values 4 §6 treats undefined-typed
     * results as the property's initial value, and the call sites
     * here would otherwise propagate NaN through arithmetic until
     * a comparison fails). ±Inf clamps to ±{@see MAX_PX}.
     */
    public static function clampPx(float $px): float
    {
        if (is_nan($px)) {
            return 0.0;
        }
        if ($px > self::MAX_PX) {
            return self::MAX_PX;
        }
        if ($px < -self::MAX_PX) {
            return -self::MAX_PX;
        }
        return $px;
    }

    /**
     * 1 inch = 96 CSS pixels (CSS Values 4 §6.2). Derived conversions:
     *  1 pt = 96 / 72  ≈ 1.3333 px
     *  1 pc = 16       px
     *  1 cm = 96 / 2.54 ≈ 37.7953 px
     *  1 mm = 96 / 25.4 ≈ 3.7795 px
     *  1 Q  = 96 / 101.6 ≈ 0.9449 px
     */
    public static function toPx(Length $length, LengthContext $ctx): float
    {
        $v = $length->value;
        $px = match ($length->unit) {
            LengthUnit::Px => $v,
            LengthUnit::Pt => $v * (96.0 / 72.0),
            LengthUnit::Pc => $v * 16.0,
            LengthUnit::Cm => $v * (96.0 / 2.54),
            LengthUnit::Mm => $v * (96.0 / 25.4),
            LengthUnit::Q => $v * (96.0 / 101.6),
            LengthUnit::In => $v * 96.0,
            LengthUnit::Em => $v * $ctx->currentFontSize,
            LengthUnit::Rem => $v * $ctx->rootFontSize,
            // CSS Values 4 §6.1.1 — `ex` and `ch` resolve against the
            // first available font's metrics. LengthContext carries
            // ratios (defaulting to 0.5em); layout code with access to
            // the resolved font passes the real ratios via
            // {@see LengthContext::withFontMetrics}.
            LengthUnit::Ex => $v * $ctx->currentFontSize * $ctx->xHeightRatio,
            LengthUnit::Ch => $v * $ctx->currentFontSize * $ctx->chWidthRatio,
            LengthUnit::Lh, LengthUnit::Rlh => $v * $ctx->currentFontSize * 1.2,
            LengthUnit::Vw, LengthUnit::Svw, LengthUnit::Lvw, LengthUnit::Dvw
                => $v * ($ctx->viewportWidth / 100.0),
            LengthUnit::Vh, LengthUnit::Svh, LengthUnit::Lvh, LengthUnit::Dvh
                => $v * ($ctx->viewportHeight / 100.0),
            LengthUnit::Vmin
                => $v * (min($ctx->viewportWidth, $ctx->viewportHeight) / 100.0),
            LengthUnit::Vmax
                => $v * (max($ctx->viewportWidth, $ctx->viewportHeight) / 100.0),
            LengthUnit::Vi => $v * ($ctx->viewportWidth / 100.0),  // assumes horizontal-tb
            LengthUnit::Vb => $v * ($ctx->viewportHeight / 100.0),
        };
        return self::clampPx($px);
    }

    /**
     * Resolve a Value into an absolute-pixel Length when possible. Returns
     * the original value untouched if it's not a Length or Percentage.
     * Percentage requires a non-zero `percentageBasis` in the context; when
     * the basis is unknown the value is left as a Percentage for layout
     * to resolve later.
     */
    public static function resolveValue(Value $value, LengthContext $ctx): Value
    {
        if ($value instanceof Length) {
            return new Length(self::toPx($value, $ctx), LengthUnit::Px);
        }
        if ($value instanceof Percentage) {
            if ($ctx->percentageBasis === 0.0) {
                return $value;
            }
            return new Length(
                self::clampPx($value->value / 100.0 * $ctx->percentageBasis),
                LengthUnit::Px,
            );
        }
        return $value;
    }
}
