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
        return match ($length->unit) {
            LengthUnit::Px => $v,
            LengthUnit::Pt => $v * (96.0 / 72.0),
            LengthUnit::Pc => $v * 16.0,
            LengthUnit::Cm => $v * (96.0 / 2.54),
            LengthUnit::Mm => $v * (96.0 / 25.4),
            LengthUnit::Q => $v * (96.0 / 101.6),
            LengthUnit::In => $v * 96.0,
            LengthUnit::Em => $v * $ctx->currentFontSize,
            LengthUnit::Rem => $v * $ctx->rootFontSize,
            LengthUnit::Ex => $v * $ctx->currentFontSize * 0.5,   // approx without font metrics
            LengthUnit::Ch => $v * $ctx->currentFontSize * 0.5,   // approx without font metrics
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
            return new Length($value->value / 100.0 * $ctx->percentageBasis, LengthUnit::Px);
        }
        return $value;
    }
}
