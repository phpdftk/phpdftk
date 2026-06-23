<?php

declare(strict_types=1);

namespace Phpdftk\Css\Cascade;

use Phpdftk\Css\Value\Calc;
use Phpdftk\Css\Value\CalcBinary;
use Phpdftk\Css\Value\CalcExpression;
use Phpdftk\Css\Value\CalcFunc;
use Phpdftk\Css\Value\CalcFunction;
use Phpdftk\Css\Value\CalcLeaf;
use Phpdftk\Css\Value\CalcOp;
use Phpdftk\Css\Value\Integer;
use Phpdftk\Css\Value\Length;
use Phpdftk\Css\Value\LengthUnit;
use Phpdftk\Css\Value\Number;
use Phpdftk\Css\Value\Percentage;
use Phpdftk\Css\Value\Value;

/**
 * Reduce a {@see Calc} tree (or any {@see CalcExpression} subtree) to a
 * concrete numeric value in CSS pixels against a {@see LengthContext}.
 *
 * Scope (CSS Values 4 §10):
 *  - `calc()` — recursive arithmetic over Length / Percentage / Number leaves.
 *  - `min()` / `max()` — variadic min / max over evaluated args.
 *  - `clamp(min, val, max)` — clamps the middle arg.
 *  - `abs()` / `sign()` / `hypot()` — straightforward unary / variadic helpers.
 *  - Trig + power functions are out of v1 scope — they evaluate to NAN so
 *    callers can detect and route to legacy length parsing.
 *
 * Leaf resolution:
 *  - {@see Length} → {@see LengthResolver::toPx} (handles px / em / rem /
 *    vw / vh / etc).
 *  - {@see Percentage} → resolved against `LengthContext::percentageBasis`
 *    if non-zero, otherwise returns NAN so the caller can defer.
 *  - {@see Number} / {@see Integer} → the unitless value (callers use this
 *    in multiplications inside the tree; a bare `calc(2)` outside a length
 *    context degenerates to a unitless 2).
 *
 * Returns NAN whenever the tree contains an unresolvable percentage,
 * an unsupported function, or a leaf that isn't a numeric primitive.
 * Callers should treat NAN as "leave the Calc as-is, defer to the
 * legacy Length parser, or skip evaluation".
 */
final class CalcEvaluator
{
    /** Evaluate a top-level `Calc` value to a pixel quantity. */
    public static function evaluate(Calc $calc, LengthContext $ctx): float
    {
        return self::eval($calc->expression, $ctx);
    }

    public static function eval(CalcExpression $expr, LengthContext $ctx): float
    {
        if ($expr instanceof CalcLeaf) {
            return self::resolveLeaf($expr->value, $ctx);
        }
        if ($expr instanceof CalcBinary) {
            $left = self::eval($expr->left, $ctx);
            $right = self::eval($expr->right, $ctx);
            if (is_nan($left) || is_nan($right)) {
                return NAN;
            }
            return match ($expr->op) {
                CalcOp::Add => $left + $right,
                CalcOp::Sub => $left - $right,
                CalcOp::Mul => $left * $right,
                CalcOp::Div => $right == 0.0 ? NAN : $left / $right,
            };
        }
        if ($expr instanceof CalcFunc) {
            $args = [];
            foreach ($expr->args as $arg) {
                $args[] = self::eval($arg, $ctx);
            }
            return self::callFunc($expr->func, $args);
        }
        return NAN;
    }

    private static function resolveLeaf(Value $value, LengthContext $ctx): float
    {
        if ($value instanceof Length) {
            return LengthResolver::toPx($value, $ctx);
        }
        if ($value instanceof Percentage) {
            // Percentage requires a basis. Zero basis = unknown to us;
            // defer by returning NAN so the caller can leave the Calc
            // untouched (e.g. background-position-percent resolves at
            // paint time, not cascade time).
            if ($ctx->percentageBasis === 0.0) {
                return NAN;
            }
            return $value->value / 100.0 * $ctx->percentageBasis;
        }
        if ($value instanceof Number) {
            return $value->value;
        }
        if ($value instanceof Integer) {
            return (float) $value->value;
        }
        if ($value instanceof Calc) {
            return self::evaluate($value, $ctx);
        }
        return NAN;
    }

    /**
     * @param list<float> $args
     */
    private static function callFunc(CalcFunction $func, array $args): float
    {
        foreach ($args as $a) {
            if (is_nan($a)) {
                return NAN;
            }
        }
        return match ($func) {
            CalcFunction::Calc => $args[0] ?? NAN,
            // PHP's `min()` / `max()` require an array or at least
            // two arguments — the single-element case (`max(50%)`)
            // would throw "must be of type array, float given".
            CalcFunction::Min => count($args) === 1
                ? $args[0]
                : ($args === [] ? NAN : min(...$args)),
            CalcFunction::Max => count($args) === 1
                ? $args[0]
                : ($args === [] ? NAN : max(...$args)),
            CalcFunction::Clamp => count($args) === 3
                ? max($args[0], min($args[1], $args[2]))
                : NAN,
            CalcFunction::Abs => count($args) === 1 ? abs($args[0]) : NAN,
            CalcFunction::Sign => count($args) === 1
                ? ($args[0] <=> 0.0)
                : NAN,
            CalcFunction::Hypot => $args === [] ? NAN : sqrt(array_sum(array_map(static fn(float $a): float => $a * $a, $args))),
            CalcFunction::Sqrt => count($args) === 1 && $args[0] >= 0.0 ? sqrt($args[0]) : NAN,
            CalcFunction::Pow => count($args) === 2 ? $args[0] ** $args[1] : NAN,
            CalcFunction::Exp => count($args) === 1 ? exp($args[0]) : NAN,
            CalcFunction::Log => count($args) === 1 ? log($args[0]) : (count($args) === 2 ? log($args[0], $args[1]) : NAN),
            // Trig functions take radians per CSS Values 4 §10.8.
            // Phase-1: only honour them when callers pre-converted any
            // `deg` / `grad` / `turn` to radians at parse time.
            CalcFunction::Sin => count($args) === 1 ? sin($args[0]) : NAN,
            CalcFunction::Cos => count($args) === 1 ? cos($args[0]) : NAN,
            CalcFunction::Tan => count($args) === 1 ? tan($args[0]) : NAN,
            CalcFunction::Asin => count($args) === 1 ? asin($args[0]) : NAN,
            CalcFunction::Acos => count($args) === 1 ? acos($args[0]) : NAN,
            CalcFunction::Atan => count($args) === 1 ? atan($args[0]) : NAN,
            CalcFunction::Atan2 => count($args) === 2 ? atan2($args[0], $args[1]) : NAN,
            // round / mod / rem — out of v1 scope; defer.
            CalcFunction::Round, CalcFunction::Mod, CalcFunction::Rem => NAN,
        };
    }

    /**
     * Reduce a {@see Calc} value to a {@see Length} when possible.
     * Returns the original Calc untouched when evaluation hits a NAN
     * (unresolvable percentage, unsupported function, etc.) so the
     * caller can defer to later resolution.
     */
    public static function resolveValue(Value $value, LengthContext $ctx): Value
    {
        if (!$value instanceof Calc) {
            return $value;
        }
        $px = self::evaluate($value, $ctx);
        if (is_nan($px)) {
            return $value;
        }
        return new Length($px, LengthUnit::Px);
    }
}
