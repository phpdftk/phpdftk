<?php

declare(strict_types=1);

namespace Phpdftk\SvgToPdf\Geometry;

use Phpdftk\Svg\Element;
use Phpdftk\Svg\Path;
use Phpdftk\Svg\Path\ArcTo;
use Phpdftk\Svg\Path\ClosePath;
use Phpdftk\Svg\Path\CurveTo;
use Phpdftk\Svg\Path\HorizontalLineTo;
use Phpdftk\Svg\Path\LineTo;
use Phpdftk\Svg\Path\MoveTo;
use Phpdftk\Svg\Path\QuadraticCurveTo;
use Phpdftk\Svg\Path\SmoothCurveTo;
use Phpdftk\Svg\Path\SmoothQuadraticCurveTo;
use Phpdftk\Svg\Path\VerticalLineTo;
use Phpdftk\Svg\Shape\Circle;
use Phpdftk\Svg\Shape\Ellipse;
use Phpdftk\Svg\Shape\Line;
use Phpdftk\Svg\Shape\Polygon;
use Phpdftk\Svg\Shape\Polyline;
use Phpdftk\Svg\Shape\Rect;
use Phpdftk\Svg\Value\Paint\None_;
use Phpdftk\SvgToPdf\Path\ArcToCubic;

/**
 * Axis-aligned bounding box used by `objectBoundingBox`-mode gradients
 * (SVG 2 §13.6.5), clip paths (§14.4), and masks (§14.5). Returns null
 * for elements whose bbox can't be computed — the caller (gradient /
 * clip / mask painter) falls back to no paint per SVG 2's "invalid →
 * no paint" rule.
 *
 * `<path>` bbox handling tracks every command's contribution: line
 * endpoints, cubic / quadratic Bézier endpoints **and** interior extrema
 * (found by solving the derivative-equals-zero equation per axis), and
 * arc segments routed through the same arc→cubic conversion the painter
 * uses so the bbox is consistent with what gets drawn.
 */
final class BoundingBox
{
    /** Treat any value below this magnitude as zero. */
    private const float EPSILON = 1.0e-12;

    /**
     * `$includeStroke` widens the result to the element's STROKE box
     * (SVG 2 §7.5 / CSS Masking 1 §6's `stroke-box`): the stroke is
     * centred on the geometry, so it reaches half its width beyond the
     * fill box on every side.
     *
     * `$viewport` is the `{w, h}` a PERCENTAGE geometry attribute
     * resolves against (SVG 2 §7.10). Without it the shape accessors
     * strip the `%` and read `width="100%"` as a hundred user units,
     * which silently made every `objectBoundingBox` gradient, pattern,
     * mask and clip on a percentage-sized shape resolve against a box
     * of the wrong size. Null keeps the accessor's reading, for
     * callers that genuinely have no viewport to hand.
     *
     * @param array{w: float, h: float}|null $viewport
     * @return array{minX: float, minY: float, width: float, height: float}|null
     */
    public static function compute(
        Element $element,
        bool $includeStroke = false,
        ?array $viewport = null,
    ): ?array {
        if ($includeStroke) {
            $box = self::compute($element, viewport: $viewport);
            return $box === null ? null : self::grownByStroke($element, $box);
        }
        if ($element instanceof Rect) {
            $w = self::length($element, 'width', $viewport, $viewport['w'] ?? 0.0, $element->width());
            $h = self::length($element, 'height', $viewport, $viewport['h'] ?? 0.0, $element->height());
            return $w <= 0.0 || $h <= 0.0
                ? null
                : [
                    'minX' => self::length($element, 'x', $viewport, $viewport['w'] ?? 0.0, $element->x()),
                    'minY' => self::length($element, 'y', $viewport, $viewport['h'] ?? 0.0, $element->y()),
                    'width' => $w,
                    'height' => $h,
                ];
        }
        if ($element instanceof Circle) {
            $r = self::length($element, 'r', $viewport, self::diagonal($viewport), $element->r());
            if ($r <= 0.0) {
                return null;
            }
            $cx = self::length($element, 'cx', $viewport, $viewport['w'] ?? 0.0, $element->cx());
            $cy = self::length($element, 'cy', $viewport, $viewport['h'] ?? 0.0, $element->cy());
            return [
                'minX' => $cx - $r,
                'minY' => $cy - $r,
                'width' => 2.0 * $r,
                'height' => 2.0 * $r,
            ];
        }
        if ($element instanceof Ellipse) {
            $rx = $element->rx();
            $ry = $element->ry();
            if ($rx === null || $ry === null) {
                return null;
            }
            $rx = self::length($element, 'rx', $viewport, $viewport['w'] ?? 0.0, $rx);
            $ry = self::length($element, 'ry', $viewport, $viewport['h'] ?? 0.0, $ry);
            if ($rx <= 0.0 || $ry <= 0.0) {
                return null;
            }
            $cx = self::length($element, 'cx', $viewport, $viewport['w'] ?? 0.0, $element->cx());
            $cy = self::length($element, 'cy', $viewport, $viewport['h'] ?? 0.0, $element->cy());
            return [
                'minX' => $cx - $rx,
                'minY' => $cy - $ry,
                'width' => 2.0 * $rx,
                'height' => 2.0 * $ry,
            ];
        }
        if ($element instanceof Line) {
            $minX = min($element->x1(), $element->x2());
            $minY = min($element->y1(), $element->y2());
            $maxX = max($element->x1(), $element->x2());
            $maxY = max($element->y1(), $element->y2());
            return ['minX' => $minX, 'minY' => $minY, 'width' => $maxX - $minX, 'height' => $maxY - $minY];
        }
        if ($element instanceof Polyline || $element instanceof Polygon) {
            $points = $element->points();
            if ($points === []) {
                return null;
            }
            $minX = $points[0][0];
            $minY = $points[0][1];
            $maxX = $minX;
            $maxY = $minY;
            foreach ($points as $p) {
                $minX = min($minX, $p[0]);
                $minY = min($minY, $p[1]);
                $maxX = max($maxX, $p[0]);
                $maxY = max($maxY, $p[1]);
            }
            return ['minX' => $minX, 'minY' => $minY, 'width' => $maxX - $minX, 'height' => $maxY - $minY];
        }
        if ($element instanceof Path) {
            return self::pathBoundingBox($element);
        }
        return null;
    }

    /**
     * One geometry length, resolving a PERCENTAGE against `$basis`
     * (SVG 2 §7.10) and otherwise deferring to `$fallback` — the value
     * the shape's own accessor already computed, which handles absolute
     * units and plain numbers.
     *
     * @param array{w: float, h: float}|null $viewport
     */
    private static function length(
        Element $element,
        string $property,
        ?array $viewport,
        float $basis,
        float $fallback,
    ): float {
        if ($viewport === null) {
            return $fallback;
        }
        $raw = $element->geometryValue($property);
        if ($raw === null
            || preg_match(
                '/^\s*([+-]?(?:\d+\.?\d*|\.\d+)(?:[eE][+-]?\d+)?)\s*%\s*$/',
                $raw,
                $m,
            ) !== 1
        ) {
            return $fallback;
        }
        return (float) $m[1] / 100.0 * $basis;
    }

    /**
     * SVG 2 §7.10's "normalized diagonal" — `sqrt(w^2 + h^2)/sqrt(2)`,
     * the 1% basis for `r`, which is tied to neither axis.
     *
     * @param array{w: float, h: float}|null $viewport
     */
    private static function diagonal(?array $viewport): float
    {
        return $viewport === null
            ? 0.0
            : sqrt($viewport['w'] ** 2 + $viewport['h'] ** 2) / M_SQRT2;
    }

    /**
     * Expand `$box` by half the element's stroke width on each side. An
     * element with no stroke (or `stroke="none"`) is unchanged — its
     * stroke box equals its fill box.
     *
     * @param array{minX: float, minY: float, width: float, height: float} $box
     * @return array{minX: float, minY: float, width: float, height: float}
     */
    private static function grownByStroke(Element $element, array $box): array
    {
        $stroke = $element->stroke();
        if ($stroke === null || $stroke instanceof None_) {
            return $box;
        }
        $half = ($element->strokeWidth() ?? 1.0) / 2.0;
        return [
            'minX' => $box['minX'] - $half,
            'minY' => $box['minY'] - $half,
            'width' => $box['width'] + 2.0 * $half,
            'height' => $box['height'] + 2.0 * $half,
        ];
    }

    /**
     * @return array{minX: float, minY: float, width: float, height: float}|null
     */
    private static function pathBoundingBox(Path $path): ?array
    {
        $commands = $path->d()->commands;
        if ($commands === []) {
            return null;
        }

        $tracker = new self();
        foreach ($commands as $command) {
            match (true) {
                $command instanceof MoveTo => $tracker->visitMoveTo($command),
                $command instanceof LineTo => $tracker->visitLineTo($command),
                $command instanceof HorizontalLineTo => $tracker->visitHorizontalLineTo($command),
                $command instanceof VerticalLineTo => $tracker->visitVerticalLineTo($command),
                $command instanceof CurveTo => $tracker->visitCurveTo($command),
                $command instanceof SmoothCurveTo => $tracker->visitSmoothCurveTo($command),
                $command instanceof QuadraticCurveTo => $tracker->visitQuadraticCurveTo($command),
                $command instanceof SmoothQuadraticCurveTo => $tracker->visitSmoothQuadraticCurveTo($command),
                $command instanceof ArcTo => $tracker->visitArcTo($command),
                $command instanceof ClosePath => $tracker->visitClosePath(),
                default => null,
            };
        }
        return $tracker->result();
    }

    private ?float $minX = null;
    private ?float $maxX = null;
    private ?float $minY = null;
    private ?float $maxY = null;
    private float $currentX = 0.0;
    private float $currentY = 0.0;
    private float $subpathStartX = 0.0;
    private float $subpathStartY = 0.0;
    private ?float $lastCubicCtrlX = null;
    private ?float $lastCubicCtrlY = null;
    private ?float $lastQuadCtrlX = null;
    private ?float $lastQuadCtrlY = null;

    private function update(float $x, float $y): void
    {
        $this->minX = $this->minX === null ? $x : min($this->minX, $x);
        $this->maxX = $this->maxX === null ? $x : max($this->maxX, $x);
        $this->minY = $this->minY === null ? $y : min($this->minY, $y);
        $this->maxY = $this->maxY === null ? $y : max($this->maxY, $y);
    }

    /**
     * @return array{minX: float, minY: float, width: float, height: float}|null
     */
    private function result(): ?array
    {
        if ($this->minX === null || $this->maxX === null || $this->minY === null || $this->maxY === null) {
            return null;
        }
        return [
            'minX' => $this->minX,
            'minY' => $this->minY,
            'width' => $this->maxX - $this->minX,
            'height' => $this->maxY - $this->minY,
        ];
    }

    private function clearCurveState(): void
    {
        $this->lastCubicCtrlX = null;
        $this->lastCubicCtrlY = null;
        $this->lastQuadCtrlX = null;
        $this->lastQuadCtrlY = null;
    }

    /**
     * @return array{float, float}
     */
    private function resolveAbsolute(float $x, float $y, bool $absolute): array
    {
        return $absolute ? [$x, $y] : [$this->currentX + $x, $this->currentY + $y];
    }

    private function visitMoveTo(MoveTo $cmd): void
    {
        [$x, $y] = $this->resolveAbsolute($cmd->x, $cmd->y, $cmd->absolute);
        $this->update($x, $y);
        $this->currentX = $x;
        $this->currentY = $y;
        $this->subpathStartX = $x;
        $this->subpathStartY = $y;
        $this->clearCurveState();
    }

    private function visitLineTo(LineTo $cmd): void
    {
        [$x, $y] = $this->resolveAbsolute($cmd->x, $cmd->y, $cmd->absolute);
        $this->update($x, $y);
        $this->currentX = $x;
        $this->currentY = $y;
        $this->clearCurveState();
    }

    private function visitHorizontalLineTo(HorizontalLineTo $cmd): void
    {
        $x = $cmd->absolute ? $cmd->x : $this->currentX + $cmd->x;
        $this->update($x, $this->currentY);
        $this->currentX = $x;
        $this->clearCurveState();
    }

    private function visitVerticalLineTo(VerticalLineTo $cmd): void
    {
        $y = $cmd->absolute ? $cmd->y : $this->currentY + $cmd->y;
        $this->update($this->currentX, $y);
        $this->currentY = $y;
        $this->clearCurveState();
    }

    private function visitCurveTo(CurveTo $cmd): void
    {
        [$x1, $y1] = $this->resolveAbsolute($cmd->x1, $cmd->y1, $cmd->absolute);
        [$x2, $y2] = $this->resolveAbsolute($cmd->x2, $cmd->y2, $cmd->absolute);
        [$x, $y] = $this->resolveAbsolute($cmd->x, $cmd->y, $cmd->absolute);
        $this->visitCubicSegment($this->currentX, $this->currentY, $x1, $y1, $x2, $y2, $x, $y);
        $this->currentX = $x;
        $this->currentY = $y;
        $this->lastCubicCtrlX = $x2;
        $this->lastCubicCtrlY = $y2;
        $this->lastQuadCtrlX = null;
        $this->lastQuadCtrlY = null;
    }

    private function visitSmoothCurveTo(SmoothCurveTo $cmd): void
    {
        // The first control point is the reflection of the previous
        // cubic's last control about the current point; falls back to
        // the current point itself when the previous command wasn't
        // cubic (matches PathPainterState's behaviour).
        $x1 = $this->lastCubicCtrlX === null
            ? $this->currentX
            : 2.0 * $this->currentX - $this->lastCubicCtrlX;
        $y1 = $this->lastCubicCtrlY === null
            ? $this->currentY
            : 2.0 * $this->currentY - $this->lastCubicCtrlY;
        [$x2, $y2] = $this->resolveAbsolute($cmd->x2, $cmd->y2, $cmd->absolute);
        [$x, $y] = $this->resolveAbsolute($cmd->x, $cmd->y, $cmd->absolute);
        $this->visitCubicSegment($this->currentX, $this->currentY, $x1, $y1, $x2, $y2, $x, $y);
        $this->currentX = $x;
        $this->currentY = $y;
        $this->lastCubicCtrlX = $x2;
        $this->lastCubicCtrlY = $y2;
        $this->lastQuadCtrlX = null;
        $this->lastQuadCtrlY = null;
    }

    private function visitQuadraticCurveTo(QuadraticCurveTo $cmd): void
    {
        [$qx, $qy] = $this->resolveAbsolute($cmd->x1, $cmd->y1, $cmd->absolute);
        [$x, $y] = $this->resolveAbsolute($cmd->x, $cmd->y, $cmd->absolute);
        $this->visitQuadraticSegment($this->currentX, $this->currentY, $qx, $qy, $x, $y);
        $this->currentX = $x;
        $this->currentY = $y;
        $this->lastQuadCtrlX = $qx;
        $this->lastQuadCtrlY = $qy;
        $this->lastCubicCtrlX = null;
        $this->lastCubicCtrlY = null;
    }

    private function visitSmoothQuadraticCurveTo(SmoothQuadraticCurveTo $cmd): void
    {
        $qx = $this->lastQuadCtrlX === null
            ? $this->currentX
            : 2.0 * $this->currentX - $this->lastQuadCtrlX;
        $qy = $this->lastQuadCtrlY === null
            ? $this->currentY
            : 2.0 * $this->currentY - $this->lastQuadCtrlY;
        [$x, $y] = $this->resolveAbsolute($cmd->x, $cmd->y, $cmd->absolute);
        $this->visitQuadraticSegment($this->currentX, $this->currentY, $qx, $qy, $x, $y);
        $this->currentX = $x;
        $this->currentY = $y;
        $this->lastQuadCtrlX = $qx;
        $this->lastQuadCtrlY = $qy;
        $this->lastCubicCtrlX = null;
        $this->lastCubicCtrlY = null;
    }

    private function visitArcTo(ArcTo $cmd): void
    {
        [$endX, $endY] = $this->resolveAbsolute($cmd->x, $cmd->y, $cmd->absolute);
        // Update the endpoint regardless so a zero-length arc still
        // affects the bbox via its endpoints.
        $this->update($endX, $endY);
        $segments = ArcToCubic::convert(
            $this->currentX,
            $this->currentY,
            $cmd->rx,
            $cmd->ry,
            $cmd->xAxisRotation,
            $cmd->largeArc,
            $cmd->sweep,
            $endX,
            $endY,
        );
        $segStartX = $this->currentX;
        $segStartY = $this->currentY;
        foreach ($segments as $seg) {
            $this->visitCubicSegment(
                $segStartX,
                $segStartY,
                $seg['x1'],
                $seg['y1'],
                $seg['x2'],
                $seg['y2'],
                $seg['x'],
                $seg['y'],
            );
            $segStartX = $seg['x'];
            $segStartY = $seg['y'];
        }
        $this->currentX = $endX;
        $this->currentY = $endY;
        $this->clearCurveState();
    }

    private function visitClosePath(): void
    {
        $this->update($this->subpathStartX, $this->subpathStartY);
        $this->currentX = $this->subpathStartX;
        $this->currentY = $this->subpathStartY;
        $this->clearCurveState();
    }

    private function visitCubicSegment(
        float $p0x,
        float $p0y,
        float $p1x,
        float $p1y,
        float $p2x,
        float $p2y,
        float $p3x,
        float $p3y,
    ): void {
        // Endpoint of the segment (the start is the previous current
        // point, already covered by an earlier update).
        $this->update($p3x, $p3y);
        foreach (self::cubicExtremaT($p0x, $p1x, $p2x, $p3x) as $t) {
            $x = self::evalCubic($p0x, $p1x, $p2x, $p3x, $t);
            $y = self::evalCubic($p0y, $p1y, $p2y, $p3y, $t);
            $this->update($x, $y);
        }
        foreach (self::cubicExtremaT($p0y, $p1y, $p2y, $p3y) as $t) {
            $x = self::evalCubic($p0x, $p1x, $p2x, $p3x, $t);
            $y = self::evalCubic($p0y, $p1y, $p2y, $p3y, $t);
            $this->update($x, $y);
        }
    }

    private function visitQuadraticSegment(
        float $p0x,
        float $p0y,
        float $p1x,
        float $p1y,
        float $p2x,
        float $p2y,
    ): void {
        $this->update($p2x, $p2y);
        $tx = self::quadraticExtremumT($p0x, $p1x, $p2x);
        if ($tx !== null) {
            $x = self::evalQuadratic($p0x, $p1x, $p2x, $tx);
            $y = self::evalQuadratic($p0y, $p1y, $p2y, $tx);
            $this->update($x, $y);
        }
        $ty = self::quadraticExtremumT($p0y, $p1y, $p2y);
        if ($ty !== null) {
            $x = self::evalQuadratic($p0x, $p1x, $p2x, $ty);
            $y = self::evalQuadratic($p0y, $p1y, $p2y, $ty);
            $this->update($x, $y);
        }
    }

    /**
     * Roots of `B'(t) = 0` for a cubic Bézier component, clipped to
     * the open interval `(0, 1)` — endpoints are already covered by
     * the segment's own endpoint contributions.
     *
     * @return list<float>
     */
    private static function cubicExtremaT(float $p0, float $p1, float $p2, float $p3): array
    {
        // B(t) = (1-t)³ p0 + 3(1-t)²t p1 + 3(1-t)t² p2 + t³ p3
        // B'(t) = 3 [ (p1-p0) + 2t (p2 - 2p1 + p0) + t² (p3 - 3p2 + 3p1 - p0) ]
        $a = $p3 - 3.0 * $p2 + 3.0 * $p1 - $p0;
        $b = 2.0 * ($p2 - 2.0 * $p1 + $p0);
        $c = $p1 - $p0;

        if (abs($a) < self::EPSILON) {
            // Reduced to linear: b·t + c = 0 → t = -c / b.
            if (abs($b) < self::EPSILON) {
                return [];
            }
            $t = -$c / $b;
            return $t > 0.0 && $t < 1.0 ? [$t] : [];
        }

        $discriminant = $b * $b - 4.0 * $a * $c;
        if ($discriminant < 0.0) {
            return [];
        }
        $sqrtD = sqrt($discriminant);
        $out = [];
        foreach ([(-$b + $sqrtD) / (2.0 * $a), (-$b - $sqrtD) / (2.0 * $a)] as $t) {
            if ($t > 0.0 && $t < 1.0) {
                $out[] = $t;
            }
        }
        return $out;
    }

    /**
     * Root of `Q'(t) = 0` for a quadratic Bézier component, clipped to
     * the open interval `(0, 1)`. Returns null when the quadratic
     * collapses to a line (no interior extremum).
     */
    private static function quadraticExtremumT(float $p0, float $p1, float $p2): ?float
    {
        $denom = $p0 - 2.0 * $p1 + $p2;
        if (abs($denom) < self::EPSILON) {
            return null;
        }
        $t = ($p0 - $p1) / $denom;
        return $t > 0.0 && $t < 1.0 ? $t : null;
    }

    private static function evalCubic(float $p0, float $p1, float $p2, float $p3, float $t): float
    {
        $s = 1.0 - $t;
        return $s * $s * $s * $p0
            + 3.0 * $s * $s * $t * $p1
            + 3.0 * $s * $t * $t * $p2
            + $t * $t * $t * $p3;
    }

    private static function evalQuadratic(float $p0, float $p1, float $p2, float $t): float
    {
        $s = 1.0 - $t;
        return $s * $s * $p0 + 2.0 * $s * $t * $p1 + $t * $t * $p2;
    }
}
