<?php

declare(strict_types=1);

namespace Phpdftk\Css\Shape;

use Phpdftk\Css\Cascade\CalcEvaluator;
use Phpdftk\Css\Cascade\LengthContext;
use Phpdftk\Css\Value\BasicShape;
use Phpdftk\Css\Value\Calc;
use Phpdftk\Css\Value\CircleShape;
use Phpdftk\Css\Value\EllipseShape;
use Phpdftk\Css\Value\InsetShape;
use Phpdftk\Css\Value\Keyword;
use Phpdftk\Css\Value\Length;
use Phpdftk\Css\Value\Percentage;
use Phpdftk\Css\Value\PolygonShape;
use Phpdftk\Css\Value\RectShape;
use Phpdftk\Css\Value\Value;
use Phpdftk\Css\Value\XywhShape;

/**
 * CSS Shapes 1 §3 / CSS Masking 1 §6 — resolve a `<basic-shape>` against a
 * reference box into a flat outline.
 *
 * The result is pure geometry: absolute coordinates in the reference box's
 * own space, with x running right and **y running down** (the CSS
 * convention). Renderers map that into their own space — the HTML painter
 * flips y against the page height, the SVG translator hands it straight
 * through because its base matrix already carries the flip.
 *
 * Curves are pre-flattened to cubic Béziers so a consumer only has to
 * understand four commands:
 *
 *   - `['M', x, y]`                          move to
 *   - `['L', x, y]`                          line to
 *   - `['C', x1, y1, x2, y2, x, y]`          cubic curve to
 *   - `['Z']`                                close the subpath
 */
final class BasicShapePath
{
    /** Bézier circle constant: 4/3 · (√2 − 1). */
    private const KAPPA = 0.5522847498307933;

    /**
     * Build the outline of `$shape` inside the reference box
     * `($x, $y, $w, $h)`. Returns null when the value is not a basic
     * shape this resolver models, or the shape is degenerate (a polygon
     * with fewer than three vertices) — callers treat that as "no shape"
     * and skip clipping rather than clipping everything away.
     *
     * @return array{fillRule: 'nonzero'|'evenodd', commands: list<list<string|float>>}|null
     */
    public static function build(Value $shape, float $x, float $y, float $w, float $h): ?array
    {
        if (!$shape instanceof BasicShape) {
            return null;
        }
        return match (true) {
            $shape instanceof InsetShape => self::inset($shape, $x, $y, $w, $h),
            $shape instanceof CircleShape => self::circle($shape, $x, $y, $w, $h),
            $shape instanceof EllipseShape => self::ellipse($shape, $x, $y, $w, $h),
            $shape instanceof PolygonShape => self::polygon($shape, $x, $y, $w, $h),
            $shape instanceof RectShape => self::rect($shape, $x, $y, $w, $h),
            $shape instanceof XywhShape => self::xywh($shape, $x, $y, $w, $h),
            default => null,
        };
    }

    /**
     * CSS Shapes 1 §3.1 — `inset()` insets each edge of the reference box.
     * The 1–4 value form expands like the `margin` shorthand.
     *
     * @return array{fillRule: 'nonzero'|'evenodd', commands: list<list<string|float>>}
     */
    private static function inset(InsetShape $shape, float $x, float $y, float $w, float $h): array
    {
        $ins = $shape->insets;
        $n = count($ins);
        $top = self::lengthPercent($ins[0], $h);
        $right = self::lengthPercent($ins[$n >= 2 ? 1 : 0], $w);
        $bottom = self::lengthPercent($ins[$n >= 3 ? 2 : 0], $h);
        $left = self::lengthPercent($ins[$n >= 4 ? 3 : ($n >= 2 ? 1 : 0)], $w);
        return self::rectangle(
            $x + $left,
            $y + $top,
            max(0.0, $w - $left - $right),
            max(0.0, $h - $top - $bottom),
        );
    }

    /**
     * CSS Shapes 1 §3.2 — `circle(<radius> at <position>)`.
     *
     * @return array{fillRule: 'nonzero'|'evenodd', commands: list<list<string|float>>}
     */
    private static function circle(CircleShape $shape, float $x, float $y, float $w, float $h): array
    {
        $cx = self::position($shape->centerX, $w);
        $cy = self::position($shape->centerY, $h);
        $r = self::circleRadius($shape->radius, $cx, $cy, $w, $h);
        return self::ellipseOutline($x + $cx, $y + $cy, $r, $r);
    }

    /**
     * CSS Shapes 1 §3.3 — `ellipse(<rx> <ry> at <position>)`.
     *
     * @return array{fillRule: 'nonzero'|'evenodd', commands: list<list<string|float>>}
     */
    private static function ellipse(EllipseShape $shape, float $x, float $y, float $w, float $h): array
    {
        $cx = self::position($shape->centerX, $w);
        $cy = self::position($shape->centerY, $h);
        return self::ellipseOutline(
            $x + $cx,
            $y + $cy,
            self::axisRadius($shape->radiusX, $cx, $w),
            self::axisRadius($shape->radiusY, $cy, $h),
        );
    }

    /**
     * CSS Shapes 1 §3.4 — `polygon(<fill-rule>?, <vertex>#)`. Fewer than
     * three vertices cannot enclose an area, so the shape is dropped.
     *
     * @return array{fillRule: 'nonzero'|'evenodd', commands: list<list<string|float>>}|null
     */
    private static function polygon(PolygonShape $shape, float $x, float $y, float $w, float $h): ?array
    {
        if (count($shape->vertices) < 3) {
            return null;
        }
        $commands = [];
        foreach ($shape->vertices as $i => [$vx, $vy]) {
            $commands[] = [
                $i === 0 ? 'M' : 'L',
                $x + self::lengthPercent($vx, $w),
                $y + self::lengthPercent($vy, $h),
            ];
        }
        $commands[] = ['Z'];
        return [
            'fillRule' => strtolower($shape->fillRule) === 'evenodd' ? 'evenodd' : 'nonzero',
            'commands' => $commands,
        ];
    }

    /**
     * CSS Shapes 2 §4.5 — `rect(top right bottom left)`: each value is an
     * edge POSITION measured from the reference box's origin (top / bottom
     * against its height, left / right against its width). `auto` resolves
     * to the matching box edge.
     *
     * @return array{fillRule: 'nonzero'|'evenodd', commands: list<list<string|float>>}
     */
    private static function rect(RectShape $shape, float $x, float $y, float $w, float $h): array
    {
        $e = $shape->edges;
        $top = self::edge($e[0] ?? null, $h, 0.0);
        $right = self::edge($e[1] ?? null, $w, $w);
        $bottom = self::edge($e[2] ?? null, $h, $h);
        $left = self::edge($e[3] ?? null, $w, 0.0);
        return self::rectangle(
            $x + $left,
            $y + $top,
            max(0.0, $right - $left),
            max(0.0, $bottom - $top),
        );
    }

    /**
     * CSS Shapes 2 §4.6 — `xywh(x y width height)`: origin plus size, x and
     * width against the reference box width, y and height against its height.
     *
     * @return array{fillRule: 'nonzero'|'evenodd', commands: list<list<string|float>>}
     */
    private static function xywh(XywhShape $shape, float $x, float $y, float $w, float $h): array
    {
        return self::rectangle(
            $x + self::lengthPercent($shape->x, $w),
            $y + self::lengthPercent($shape->y, $h),
            self::lengthPercent($shape->width, $w),
            self::lengthPercent($shape->height, $h),
        );
    }

    /**
     * @return array{fillRule: 'nonzero'|'evenodd', commands: list<list<string|float>>}
     */
    private static function rectangle(float $x, float $y, float $w, float $h): array
    {
        return [
            'fillRule' => 'nonzero',
            'commands' => [
                ['M', $x, $y],
                ['L', $x + $w, $y],
                ['L', $x + $w, $y + $h],
                ['L', $x, $y + $h],
                ['Z'],
            ],
        ];
    }

    /**
     * Four-Bézier approximation of the ellipse with radii (rx, ry) centred
     * at (cx, cy). Wound clockwise in a y-down space.
     *
     * @return array{fillRule: 'nonzero'|'evenodd', commands: list<list<string|float>>}
     */
    private static function ellipseOutline(float $cx, float $cy, float $rx, float $ry): array
    {
        $kx = $rx * self::KAPPA;
        $ky = $ry * self::KAPPA;
        return [
            'fillRule' => 'nonzero',
            'commands' => [
                ['M', $cx + $rx, $cy],
                ['C', $cx + $rx, $cy + $ky, $cx + $kx, $cy + $ry, $cx, $cy + $ry],
                ['C', $cx - $kx, $cy + $ry, $cx - $rx, $cy + $ky, $cx - $rx, $cy],
                ['C', $cx - $rx, $cy - $ky, $cx - $kx, $cy - $ry, $cx, $cy - $ry],
                ['C', $cx + $kx, $cy - $ry, $cx + $rx, $cy - $ky, $cx + $rx, $cy],
                ['Z'],
            ],
        ];
    }

    /**
     * CSS Shapes 1 §3.2 — a `circle()` radius. A percentage resolves
     * against the reference box's diagonal / √2; the extent keywords
     * measure from the shape's centre.
     */
    private static function circleRadius(?Value $value, float $cx, float $cy, float $w, float $h): float
    {
        if ($value instanceof Length) {
            return $value->value;
        }
        if ($value instanceof Percentage) {
            return $value->value / 100.0 * (sqrt($w * $w + $h * $h) / M_SQRT2);
        }
        if ($value instanceof Calc) {
            return self::calc($value, sqrt($w * $w + $h * $h) / M_SQRT2);
        }
        $sides = [$cx, $w - $cx, $cy, $h - $cy];
        $corners = [
            hypot($cx, $cy), hypot($w - $cx, $cy),
            hypot($cx, $h - $cy), hypot($w - $cx, $h - $cy),
        ];
        return match ($value instanceof Keyword ? strtolower($value->name) : 'closest-side') {
            'farthest-side' => max($sides),
            'closest-corner' => min($corners),
            'farthest-corner' => max($corners),
            default => min($sides),
        };
    }

    /**
     * One `ellipse()` radius. `$center` is the centre offset along this
     * axis and `$extent` the reference box extent along it; percentages
     * resolve against `$extent` (not the diagonal, unlike `circle()`).
     */
    private static function axisRadius(?Value $value, float $center, float $extent): float
    {
        if ($value instanceof Length) {
            return $value->value;
        }
        if ($value instanceof Percentage) {
            return $value->value / 100.0 * $extent;
        }
        if ($value instanceof Calc) {
            return self::calc($value, $extent);
        }
        $near = min($center, $extent - $center);
        $far = max($center, $extent - $center);
        return match ($value instanceof Keyword ? strtolower($value->name) : 'closest-side') {
            'farthest-side' => $far,
            default => $near,
        };
    }

    /**
     * A `<position>` component. `null` centres the shape; the edge
     * keywords map to 0 / 50% / 100% of `$basis`.
     */
    private static function position(?Value $value, float $basis): float
    {
        if ($value === null) {
            return $basis * 0.5;
        }
        if ($value instanceof Keyword) {
            return match (strtolower($value->name)) {
                'left', 'top' => 0.0,
                'right', 'bottom' => $basis,
                default => $basis * 0.5,
            };
        }
        if ($value instanceof Length || $value instanceof Percentage || $value instanceof Calc) {
            return self::lengthPercent($value, $basis);
        }
        return $basis * 0.5;
    }

    /**
     * One `rect()` edge as an absolute position. `auto` (or a value this
     * resolver can't read) falls back to `$auto`, the matching box edge.
     */
    private static function edge(?Value $value, float $basis, float $auto): float
    {
        if ($value instanceof Length || $value instanceof Percentage || $value instanceof Calc) {
            return self::lengthPercent($value, $basis);
        }
        return $auto;
    }

    /** `<length-percentage>` → px against `$basis`; anything else → 0. */
    private static function lengthPercent(Value $value, float $basis): float
    {
        if ($value instanceof Length) {
            return $value->value;
        }
        if ($value instanceof Percentage) {
            return $value->value / 100.0 * $basis;
        }
        if ($value instanceof Calc) {
            return self::calc($value, $basis);
        }
        return 0.0;
    }

    private static function calc(Calc $value, float $basis): float
    {
        $resolved = CalcEvaluator::evaluate($value, new LengthContext(percentageBasis: $basis));
        return is_finite($resolved) ? $resolved : 0.0;
    }
}
