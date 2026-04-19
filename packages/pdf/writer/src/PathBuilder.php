<?php

declare(strict_types=1);

namespace ApprLabs\Pdf\Writer;

use ApprLabs\Pdf\Core\Content\ContentStream;

/**
 * Fluent builder for custom PDF paths.
 *
 * Used with Page::drawPath() to construct complex shapes without
 * knowing content stream operators. Supports lines, cubic Bézier
 * curves, quadratic curves (converted to cubic), arcs, and closure.
 *
 * Usage:
 *   $page->drawPath(function(PathBuilder $p) {
 *       $p->moveTo(100, 100)
 *         ->lineTo(200, 150)
 *         ->curveTo(250, 200, 300, 100, 350, 150)
 *         ->close();
 *   }, fill: RgbColor::fromHex('#FF0000'));
 */
final class PathBuilder
{
    /** @var list<array{op: string, args: float[]}> */
    private array $operations = [];

    public function moveTo(float $x, float $y): self
    {
        $this->operations[] = ['op' => 'moveTo', 'args' => [$x, $y]];
        return $this;
    }

    public function lineTo(float $x, float $y): self
    {
        $this->operations[] = ['op' => 'lineTo', 'args' => [$x, $y]];
        return $this;
    }

    /**
     * Cubic Bézier curve to (x3, y3) with control points (x1, y1) and (x2, y2).
     */
    public function curveTo(
        float $x1, float $y1,
        float $x2, float $y2,
        float $x3, float $y3,
    ): self {
        $this->operations[] = ['op' => 'curveTo', 'args' => [$x1, $y1, $x2, $y2, $x3, $y3]];
        return $this;
    }

    /**
     * Quadratic Bézier curve — converted to cubic internally.
     *
     * A quadratic curve with control point (cpx, cpy) and end point (x, y)
     * is converted to a cubic curve using the standard 2/3 approximation.
     */
    public function quadCurveTo(float $cpx, float $cpy, float $x, float $y): self
    {
        $this->operations[] = ['op' => 'quadCurveTo', 'args' => [$cpx, $cpy, $x, $y]];
        return $this;
    }

    /**
     * Circular arc from startAngle to endAngle (in degrees, counterclockwise).
     *
     * Approximated with Bézier curves (one per 90-degree segment).
     */
    public function arcTo(
        float $cx, float $cy,
        float $r,
        float $startAngle,
        float $endAngle,
    ): self {
        $this->operations[] = ['op' => 'arcTo', 'args' => [$cx, $cy, $r, $startAngle, $endAngle]];
        return $this;
    }

    public function close(): self
    {
        $this->operations[] = ['op' => 'close', 'args' => []];
        return $this;
    }

    /**
     * @internal Replay recorded operations onto a ContentStream.
     */
    public function replayTo(ContentStream $cs): void
    {
        $lastX = 0.0;
        $lastY = 0.0;

        foreach ($this->operations as $op) {
            match ($op['op']) {
                'moveTo' => (function () use ($cs, $op, &$lastX, &$lastY) {
                    $cs->moveTo($op['args'][0], $op['args'][1]);
                    $lastX = $op['args'][0];
                    $lastY = $op['args'][1];
                })(),
                'lineTo' => (function () use ($cs, $op, &$lastX, &$lastY) {
                    $cs->lineTo($op['args'][0], $op['args'][1]);
                    $lastX = $op['args'][0];
                    $lastY = $op['args'][1];
                })(),
                'curveTo' => (function () use ($cs, $op, &$lastX, &$lastY) {
                    $cs->curveTo(...$op['args']);
                    $lastX = $op['args'][4];
                    $lastY = $op['args'][5];
                })(),
                'quadCurveTo' => (function () use ($cs, $op, &$lastX, &$lastY) {
                    [$cpx, $cpy, $x, $y] = $op['args'];
                    // Convert quadratic to cubic: CP1 = P0 + 2/3*(CP-P0), CP2 = P + 2/3*(CP-P)
                    $cp1x = $lastX + 2.0 / 3.0 * ($cpx - $lastX);
                    $cp1y = $lastY + 2.0 / 3.0 * ($cpy - $lastY);
                    $cp2x = $x + 2.0 / 3.0 * ($cpx - $x);
                    $cp2y = $y + 2.0 / 3.0 * ($cpy - $y);
                    $cs->curveTo($cp1x, $cp1y, $cp2x, $cp2y, $x, $y);
                    $lastX = $x;
                    $lastY = $y;
                })(),
                'arcTo' => (function () use ($cs, $op, &$lastX, &$lastY) {
                    [$cx, $cy, $r, $startDeg, $endDeg] = $op['args'];
                    self::emitArc($cs, $cx, $cy, $r, $startDeg, $endDeg, $lastX, $lastY);
                })(),
                'close' => $cs->closePath(),
                default => null,
            };
        }
    }

    /**
     * Emit a circular arc as Bézier curves onto a ContentStream.
     * Splits into segments of at most 90 degrees.
     */
    private static function emitArc(
        ContentStream $cs,
        float $cx, float $cy,
        float $r,
        float $startDeg, float $endDeg,
        float &$lastX, float &$lastY,
    ): void {
        $startRad = deg2rad($startDeg);
        $endRad = deg2rad($endDeg);

        // Ensure we go counterclockwise
        if ($endRad < $startRad) {
            $endRad += 2 * M_PI;
        }

        $totalAngle = $endRad - $startRad;
        $segments = (int) ceil($totalAngle / (M_PI / 2));
        if ($segments === 0) {
            return;
        }

        $segmentAngle = $totalAngle / $segments;
        $currentAngle = $startRad;

        // Move to start of arc if not already there
        $sx = $cx + $r * cos($currentAngle);
        $sy = $cy + $r * sin($currentAngle);
        if (abs($sx - $lastX) > 0.01 || abs($sy - $lastY) > 0.01) {
            $cs->lineTo($sx, $sy);
        }

        for ($i = 0; $i < $segments; $i++) {
            $a1 = $currentAngle;
            $a2 = $currentAngle + $segmentAngle;

            // Bézier approximation of an arc segment
            $alpha = 4.0 / 3.0 * tan($segmentAngle / 4);

            $x1 = $cx + $r * cos($a1);
            $y1 = $cy + $r * sin($a1);
            $x4 = $cx + $r * cos($a2);
            $y4 = $cy + $r * sin($a2);

            $cp1x = $x1 - $alpha * $r * sin($a1);
            $cp1y = $y1 + $alpha * $r * cos($a1);
            $cp2x = $x4 + $alpha * $r * sin($a2);
            $cp2y = $y4 - $alpha * $r * cos($a2);

            $cs->curveTo($cp1x, $cp1y, $cp2x, $cp2y, $x4, $y4);

            $lastX = $x4;
            $lastY = $y4;
            $currentAngle = $a2;
        }
    }
}
