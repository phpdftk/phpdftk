<?php

declare(strict_types=1);

namespace Phpdftk\SvgToPdf\Path;

/**
 * SVG elliptical arc → list of cubic Bézier segments.
 *
 * PDF has no native elliptical-arc operator, so an SVG `A` / `a` command
 * gets baked into one or more cubic Béziers. The algorithm follows the
 * SVG 1.1 implementation note Appendix B / SVG 2 §9.5.2:
 *
 *  1. End-point parameterisation (rx, ry, φ, large-arc, sweep, x2, y2)
 *     converts to centre parameterisation `(cx, cy, θ₁, Δθ)`.
 *  2. `Δθ` is split into segments of at most `π/2` (90°). Approximating
 *     a quarter-arc with one cubic has a worst-case radial error
 *     ≈ 1.2·10⁻⁴ of the radius — invisible at print resolution.
 *  3. Each segment emits one cubic Bézier using the standard
 *     `α = (4/3) · tan(Δ/4)` control-point distance.
 *
 * Degenerate inputs (`rx == 0`, `ry == 0`, or start == end) return the
 * empty list — the caller falls back to a straight line or omits the
 * arc entirely.
 */
final class ArcToCubic
{
    /** Quarter-arc cap on the per-segment angular span. */
    private const float MAX_SEGMENT_ANGLE = M_PI / 2.0;

    /** Tolerance for "start == end" — well below print resolution. */
    private const float ZERO_LENGTH_EPSILON = 1.0e-12;

    /**
     * @return list<array{x1: float, y1: float, x2: float, y2: float, x: float, y: float}>
     *         A list of cubic Béziers, each {control1, control2, endpoint}.
     *         The first segment's start point is `($x1, $y1)`; each
     *         subsequent segment starts where the previous ended.
     */
    public static function convert(
        float $x1,
        float $y1,
        float $rx,
        float $ry,
        float $xAxisRotationDegrees,
        bool $largeArc,
        bool $sweep,
        float $x2,
        float $y2,
    ): array {
        if (abs($x1 - $x2) < self::ZERO_LENGTH_EPSILON
            && abs($y1 - $y2) < self::ZERO_LENGTH_EPSILON
        ) {
            return [];
        }
        if ($rx === 0.0 || $ry === 0.0) {
            return [];
        }

        $rx = abs($rx);
        $ry = abs($ry);
        $phi = deg2rad(fmod($xAxisRotationDegrees, 360.0));
        $cosPhi = cos($phi);
        $sinPhi = sin($phi);

        // Step 1: F.6.5.1 — compute (x1', y1').
        $dx = ($x1 - $x2) / 2.0;
        $dy = ($y1 - $y2) / 2.0;
        $x1p = $cosPhi * $dx + $sinPhi * $dy;
        $y1p = -$sinPhi * $dx + $cosPhi * $dy;

        // Step 2: F.6.6 — radius correction.
        $lambda = ($x1p * $x1p) / ($rx * $rx) + ($y1p * $y1p) / ($ry * $ry);
        if ($lambda > 1.0) {
            $scale = sqrt($lambda);
            $rx *= $scale;
            $ry *= $scale;
        }

        // Step 3: F.6.5.2 — compute (cx', cy').
        $rxSq = $rx * $rx;
        $rySq = $ry * $ry;
        $x1pSq = $x1p * $x1p;
        $y1pSq = $y1p * $y1p;
        $factor = max(
            0.0,
            ($rxSq * $rySq - $rxSq * $y1pSq - $rySq * $x1pSq)
                / ($rxSq * $y1pSq + $rySq * $x1pSq),
        );
        $coef = ($largeArc === $sweep ? -1.0 : 1.0) * sqrt($factor);
        $cxp = $coef * $rx * $y1p / $ry;
        $cyp = $coef * -$ry * $x1p / $rx;

        // Step 4: F.6.5.3 — back-transform centre into the original
        // coordinate system.
        $cx = $cosPhi * $cxp - $sinPhi * $cyp + ($x1 + $x2) / 2.0;
        $cy = $sinPhi * $cxp + $cosPhi * $cyp + ($y1 + $y2) / 2.0;

        // Step 5: F.6.5.4 — compute θ₁ and Δθ.
        $ux = ($x1p - $cxp) / $rx;
        $uy = ($y1p - $cyp) / $ry;
        $vx = (-$x1p - $cxp) / $rx;
        $vy = (-$y1p - $cyp) / $ry;
        $theta1 = self::angleBetween(1.0, 0.0, $ux, $uy);
        $deltaTheta = self::angleBetween($ux, $uy, $vx, $vy);
        if (!$sweep && $deltaTheta > 0.0) {
            $deltaTheta -= 2.0 * M_PI;
        }
        if ($sweep && $deltaTheta < 0.0) {
            $deltaTheta += 2.0 * M_PI;
        }

        // Step 6 — split into ≤ 90° segments and emit one cubic each.
        $segmentCount = (int) ceil(abs($deltaTheta) / self::MAX_SEGMENT_ANGLE);
        if ($segmentCount === 0) {
            return [];
        }
        $segmentDelta = $deltaTheta / $segmentCount;
        $alpha = (4.0 / 3.0) * tan($segmentDelta / 4.0);

        $segments = [];
        $theta = $theta1;
        for ($i = 0; $i < $segmentCount; $i++) {
            $thetaNext = $theta + $segmentDelta;
            $segments[] = self::cubicSegment(
                $cx,
                $cy,
                $rx,
                $ry,
                $cosPhi,
                $sinPhi,
                $theta,
                $thetaNext,
                $alpha,
            );
            $theta = $thetaNext;
        }
        return $segments;
    }

    /**
     * One cubic Bézier approximating the arc from `$thetaStart` to
     * `$thetaEnd` on the unit ellipse, then transformed by
     * `(rx, ry, φ, cx, cy)`.
     *
     * @return array{x1: float, y1: float, x2: float, y2: float, x: float, y: float}
     */
    private static function cubicSegment(
        float $cx,
        float $cy,
        float $rx,
        float $ry,
        float $cosPhi,
        float $sinPhi,
        float $thetaStart,
        float $thetaEnd,
        float $alpha,
    ): array {
        $cosA = cos($thetaStart);
        $sinA = sin($thetaStart);
        $cosB = cos($thetaEnd);
        $sinB = sin($thetaEnd);

        // Unit-ellipse control points.
        $p1x = $cosA - $alpha * $sinA;
        $p1y = $sinA + $alpha * $cosA;
        $p2x = $cosB + $alpha * $sinB;
        $p2y = $sinB - $alpha * $cosB;
        $p3x = $cosB;
        $p3y = $sinB;

        return [
            'x1' => self::transformX($p1x, $p1y, $rx, $ry, $cosPhi, $sinPhi, $cx),
            'y1' => self::transformY($p1x, $p1y, $rx, $ry, $cosPhi, $sinPhi, $cy),
            'x2' => self::transformX($p2x, $p2y, $rx, $ry, $cosPhi, $sinPhi, $cx),
            'y2' => self::transformY($p2x, $p2y, $rx, $ry, $cosPhi, $sinPhi, $cy),
            'x' => self::transformX($p3x, $p3y, $rx, $ry, $cosPhi, $sinPhi, $cx),
            'y' => self::transformY($p3x, $p3y, $rx, $ry, $cosPhi, $sinPhi, $cy),
        ];
    }

    private static function transformX(
        float $x,
        float $y,
        float $rx,
        float $ry,
        float $cosPhi,
        float $sinPhi,
        float $cx,
    ): float {
        return $cosPhi * $x * $rx - $sinPhi * $y * $ry + $cx;
    }

    private static function transformY(
        float $x,
        float $y,
        float $rx,
        float $ry,
        float $cosPhi,
        float $sinPhi,
        float $cy,
    ): float {
        return $sinPhi * $x * $rx + $cosPhi * $y * $ry + $cy;
    }

    /**
     * Signed angle from `(ux, uy)` to `(vx, vy)` per SVG 2 §F.6.5.4.
     * Result in `(-π, π]`.
     */
    private static function angleBetween(float $ux, float $uy, float $vx, float $vy): float
    {
        $dot = $ux * $vx + $uy * $vy;
        $mag = sqrt(($ux * $ux + $uy * $uy) * ($vx * $vx + $vy * $vy));
        if ($mag === 0.0) {
            return 0.0;
        }
        $cos = max(-1.0, min(1.0, $dot / $mag));
        $sign = ($ux * $vy - $uy * $vx) < 0.0 ? -1.0 : 1.0;
        return $sign * acos($cos);
    }
}
