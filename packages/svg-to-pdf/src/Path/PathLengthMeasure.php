<?php

declare(strict_types=1);

namespace Phpdftk\SvgToPdf\Path;

use Phpdftk\Svg\Path\ArcTo;
use Phpdftk\Svg\Path\ClosePath;
use Phpdftk\Svg\Path\CurveTo;
use Phpdftk\Svg\Path\HorizontalLineTo;
use Phpdftk\Svg\Path\LineTo;
use Phpdftk\Svg\Path\MoveTo;
use Phpdftk\Svg\Path\PathData;
use Phpdftk\Svg\Path\QuadraticCurveTo;
use Phpdftk\Svg\Path\SmoothCurveTo;
use Phpdftk\Svg\Path\SmoothQuadraticCurveTo;
use Phpdftk\Svg\Path\VerticalLineTo;

/**
 * Geometric length of a parsed `d` attribute, in user units.
 *
 * This is the denominator of the SVG 2 §9.6 `pathLength` scaling
 * factor: the user agent's own computation of how long the path really
 * is, against which the author's declared `pathLength` is calibrated.
 *
 * Two properties matter more than raw speed:
 *
 *  - **Exactness on the easy cases.** Straight segments measure as
 *    their chord and a circular arc measures as `r·Δθ`, so a square
 *    path is exactly `4w` and a circle exactly `2πr`. WPT's
 *    `pathLength` reftests draw the test side as a shape and the
 *    reference side as a hand-written path; the two must agree to
 *    well under a device pixel after the dash phase has accumulated
 *    around the whole outline.
 *  - **Measuring the TRUE ellipse, not its Bézier approximation.** The
 *    painter lowers an `A` command to cubics because PDF has no arc
 *    operator, but a quarter-circle cubic is ~1.4·10⁻⁵ longer than the
 *    quarter circle. Measuring the approximation would make
 *    `<circle r="100">` and the equivalent `<path>` disagree.
 *
 * Bézier segments have no closed-form arc length, so they are
 * integrated with 24-point Gauss–Legendre quadrature over the
 * derivative magnitude. That is exact for the degenerate
 * straight-line cubics authors write constantly, and converges far
 * faster than chord subdivision on real curves.
 */
final class PathLengthMeasure
{
    /**
     * Gauss–Legendre abscissae on [-1, 1] for n = 24, paired with
     * their weights. Only the non-negative half is stored; the rule is
     * symmetric, so each entry is evaluated at ±x.
     *
     * @var list<array{float, float}>
     */
    private const array GAUSS_HALF = [
        [0.0640568928626056, 0.1279381953467522],
        [0.1911188674736163, 0.1258374563468283],
        [0.3150426796961634, 0.1216704729278034],
        [0.4337935076260451, 0.1155056680537256],
        [0.5454214713888396, 0.1074442701159656],
        [0.6480936519369755, 0.0976186521041139],
        [0.7401241915785544, 0.0861901615319533],
        [0.8200019859739029, 0.0733464814110803],
        [0.8864155270044011, 0.0592985849154368],
        [0.9382745520027328, 0.0442774388174198],
        [0.9747285559713095, 0.0285313886289337],
        [0.9951872199970213, 0.0123412297999872],
    ];

    /** Below this, a coordinate delta is noise rather than geometry. */
    private const float EPSILON = 1.0e-12;

    public static function ofPathData(PathData $data): float
    {
        $total = 0.0;
        // Current point, and the start of the current subpath (where a
        // `Z` returns to). Also the previous curve's second control
        // point, which the smooth (`S` / `T`) commands reflect.
        $cx = $cy = 0.0;
        $startX = $startY = 0.0;
        $lastCubicControlX = $lastCubicControlY = null;
        $lastQuadControlX = $lastQuadControlY = null;

        foreach ($data->commands as $command) {
            $isCubic = false;
            $isQuad = false;

            switch (true) {
                case $command instanceof MoveTo:
                    [$x, $y] = self::resolve($command->absolute, $command->x, $command->y, $cx, $cy);
                    $cx = $startX = $x;
                    $cy = $startY = $y;
                    break;

                case $command instanceof LineTo:
                    [$x, $y] = self::resolve($command->absolute, $command->x, $command->y, $cx, $cy);
                    $total += hypot($x - $cx, $y - $cy);
                    $cx = $x;
                    $cy = $y;
                    break;

                case $command instanceof HorizontalLineTo:
                    $x = $command->absolute ? $command->x : $cx + $command->x;
                    $total += abs($x - $cx);
                    $cx = $x;
                    break;

                case $command instanceof VerticalLineTo:
                    $y = $command->absolute ? $command->y : $cy + $command->y;
                    $total += abs($y - $cy);
                    $cy = $y;
                    break;

                case $command instanceof CurveTo:
                    [$x1, $y1] = self::resolve($command->absolute, $command->x1, $command->y1, $cx, $cy);
                    [$x2, $y2] = self::resolve($command->absolute, $command->x2, $command->y2, $cx, $cy);
                    [$x, $y] = self::resolve($command->absolute, $command->x, $command->y, $cx, $cy);
                    $total += self::cubicLength($cx, $cy, $x1, $y1, $x2, $y2, $x, $y);
                    $lastCubicControlX = $x2;
                    $lastCubicControlY = $y2;
                    $cx = $x;
                    $cy = $y;
                    $isCubic = true;
                    break;

                case $command instanceof SmoothCurveTo:
                    $x1 = $lastCubicControlX === null ? $cx : 2.0 * $cx - $lastCubicControlX;
                    $y1 = $lastCubicControlY === null ? $cy : 2.0 * $cy - $lastCubicControlY;
                    [$x2, $y2] = self::resolve($command->absolute, $command->x2, $command->y2, $cx, $cy);
                    [$x, $y] = self::resolve($command->absolute, $command->x, $command->y, $cx, $cy);
                    $total += self::cubicLength($cx, $cy, $x1, $y1, $x2, $y2, $x, $y);
                    $lastCubicControlX = $x2;
                    $lastCubicControlY = $y2;
                    $cx = $x;
                    $cy = $y;
                    $isCubic = true;
                    break;

                case $command instanceof QuadraticCurveTo:
                    [$x1, $y1] = self::resolve($command->absolute, $command->x1, $command->y1, $cx, $cy);
                    [$x, $y] = self::resolve($command->absolute, $command->x, $command->y, $cx, $cy);
                    $total += self::quadraticLength($cx, $cy, $x1, $y1, $x, $y);
                    $lastQuadControlX = $x1;
                    $lastQuadControlY = $y1;
                    $cx = $x;
                    $cy = $y;
                    $isQuad = true;
                    break;

                case $command instanceof SmoothQuadraticCurveTo:
                    $x1 = $lastQuadControlX === null ? $cx : 2.0 * $cx - $lastQuadControlX;
                    $y1 = $lastQuadControlY === null ? $cy : 2.0 * $cy - $lastQuadControlY;
                    [$x, $y] = self::resolve($command->absolute, $command->x, $command->y, $cx, $cy);
                    $total += self::quadraticLength($cx, $cy, $x1, $y1, $x, $y);
                    $lastQuadControlX = $x1;
                    $lastQuadControlY = $y1;
                    $cx = $x;
                    $cy = $y;
                    $isQuad = true;
                    break;

                case $command instanceof ArcTo:
                    [$x, $y] = self::resolve($command->absolute, $command->x, $command->y, $cx, $cy);
                    $total += self::arcLength($command, $cx, $cy, $x, $y);
                    $cx = $x;
                    $cy = $y;
                    break;

                case $command instanceof ClosePath:
                    $total += hypot($startX - $cx, $startY - $cy);
                    $cx = $startX;
                    $cy = $startY;
                    break;

                default:
                    // Third-party `PathCommand` implementations are not
                    // part of the spec; the painter no-ops on them, so
                    // they contribute no length either.
                    break;
            }

            // Only a cubic seeds `S`'s reflection and only a quadratic
            // seeds `T`'s — SVG 2 §9.3.6/§9.3.7. Anything else clears
            // the respective anchor so the next smooth command starts
            // from the current point.
            if (!$isCubic) {
                $lastCubicControlX = $lastCubicControlY = null;
            }
            if (!$isQuad) {
                $lastQuadControlX = $lastQuadControlY = null;
            }
        }

        return $total;
    }

    /** @return array{float, float} */
    private static function resolve(
        bool $absolute,
        float $x,
        float $y,
        float $cx,
        float $cy,
    ): array {
        return $absolute ? [$x, $y] : [$cx + $x, $cy + $y];
    }

    /**
     * Arc length of one `A` command, measured on the TRUE ellipse.
     *
     * A zero radius (or a zero-length chord) is not an arc at all: SVG
     * 2 §9.5.1 renders it as a straight line to the endpoint, and the
     * painter does the same, so it measures as the chord.
     */
    private static function arcLength(
        ArcTo $arc,
        float $fromX,
        float $fromY,
        float $toX,
        float $toY,
    ): float {
        $centre = ArcToCubic::centreParameters(
            $fromX,
            $fromY,
            $arc->rx,
            $arc->ry,
            $arc->xAxisRotation,
            $arc->largeArc,
            $arc->sweep,
            $toX,
            $toY,
        );
        if ($centre === null) {
            return hypot($toX - $fromX, $toY - $fromY);
        }
        $rx = $centre['rx'];
        $ry = $centre['ry'];
        $theta1 = $centre['theta1'];
        $delta = $centre['delta'];
        if (abs($delta) < self::EPSILON) {
            return 0.0;
        }
        // A circular arc integrates in closed form; skip the quadrature
        // so `<circle>` and an `A`-command circle agree bit for bit.
        if (abs($rx - $ry) < self::EPSILON) {
            return $rx * abs($delta);
        }
        // |dP/dθ| on the ellipse. The x-axis rotation φ is a rigid
        // rotation, so it leaves arc length alone and drops out.
        return self::gauss(
            static fn(float $t): float => hypot($rx * sin($t), $ry * cos($t)),
            $theta1,
            $theta1 + $delta,
        );
    }

    private static function cubicLength(
        float $x0,
        float $y0,
        float $x1,
        float $y1,
        float $x2,
        float $y2,
        float $x3,
        float $y3,
    ): float {
        return self::gauss(
            static function (float $t) use ($x0, $y0, $x1, $y1, $x2, $y2, $x3, $y3): float {
                $u = 1.0 - $t;
                $dx = 3.0 * $u * $u * ($x1 - $x0)
                    + 6.0 * $u * $t * ($x2 - $x1)
                    + 3.0 * $t * $t * ($x3 - $x2);
                $dy = 3.0 * $u * $u * ($y1 - $y0)
                    + 6.0 * $u * $t * ($y2 - $y1)
                    + 3.0 * $t * $t * ($y3 - $y2);
                return hypot($dx, $dy);
            },
            0.0,
            1.0,
        );
    }

    private static function quadraticLength(
        float $x0,
        float $y0,
        float $x1,
        float $y1,
        float $x2,
        float $y2,
    ): float {
        return self::gauss(
            static function (float $t) use ($x0, $y0, $x1, $y1, $x2, $y2): float {
                $u = 1.0 - $t;
                $dx = 2.0 * $u * ($x1 - $x0) + 2.0 * $t * ($x2 - $x1);
                $dy = 2.0 * $u * ($y1 - $y0) + 2.0 * $t * ($y2 - $y1);
                return hypot($dx, $dy);
            },
            0.0,
            1.0,
        );
    }

    /**
     * 24-point Gauss–Legendre quadrature of `$f` over `[$a, $b]`.
     *
     * @param callable(float): float $f
     */
    private static function gauss(callable $f, float $a, float $b): float
    {
        $half = ($b - $a) / 2.0;
        $mid = ($a + $b) / 2.0;
        $sum = 0.0;
        foreach (self::GAUSS_HALF as [$x, $w]) {
            $offset = $half * $x;
            $sum += $w * ($f($mid - $offset) + $f($mid + $offset));
        }
        return abs($half) * $sum;
    }
}
