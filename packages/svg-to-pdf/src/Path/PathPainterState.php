<?php

declare(strict_types=1);

namespace Phpdftk\SvgToPdf\Path;

/**
 * Mutable per-path state for the `<path>` painter. Tracks the SVG cursor
 * (`x` / `y`), the start of the current subpath (so `Z` knows where to
 * close back to), and the last cubic / quadratic control point so the
 * smooth-curve commands (`S` / `T`) can reflect it.
 *
 * Internal to `phpdftk/svg-to-pdf` — exists in its own namespace so the
 * `Translator` stays focused on dispatch.
 */
final class PathPainterState
{
    public float $currentX = 0.0;
    public float $currentY = 0.0;
    public float $subpathStartX = 0.0;
    public float $subpathStartY = 0.0;
    public ?float $lastCubicControlX = null;
    public ?float $lastCubicControlY = null;
    public ?float $lastQuadraticControlX = null;
    public ?float $lastQuadraticControlY = null;

    public function moveTo(float $x, float $y): void
    {
        $this->currentX = $x;
        $this->currentY = $y;
        $this->subpathStartX = $x;
        $this->subpathStartY = $y;
        $this->clearControlPoints();
    }

    public function lineTo(float $x, float $y): void
    {
        $this->currentX = $x;
        $this->currentY = $y;
        $this->clearControlPoints();
    }

    public function clearControlPoints(): void
    {
        $this->lastCubicControlX = null;
        $this->lastCubicControlY = null;
        $this->lastQuadraticControlX = null;
        $this->lastQuadraticControlY = null;
    }

    /**
     * After a cubic curve, the last control point (the one that would
     * be reflected by a following `S`) is the second control point.
     */
    public function recordCubicControl(float $x, float $y): void
    {
        $this->lastCubicControlX = $x;
        $this->lastCubicControlY = $y;
        $this->lastQuadraticControlX = null;
        $this->lastQuadraticControlY = null;
    }

    public function recordQuadraticControl(float $x, float $y): void
    {
        $this->lastQuadraticControlX = $x;
        $this->lastQuadraticControlY = $y;
        $this->lastCubicControlX = null;
        $this->lastCubicControlY = null;
    }

    /**
     * Reflection of the last cubic control point about the current
     * point — used by `S` to synthesise its first control point. Falls
     * back to the current point when the previous command wasn't cubic
     * per SVG 2 §9.3.7.
     *
     * @return array{float, float}
     */
    public function reflectedCubicControl(): array
    {
        if ($this->lastCubicControlX === null || $this->lastCubicControlY === null) {
            return [$this->currentX, $this->currentY];
        }
        return [
            2.0 * $this->currentX - $this->lastCubicControlX,
            2.0 * $this->currentY - $this->lastCubicControlY,
        ];
    }

    /**
     * Reflection of the last quadratic control point — used by `T`.
     *
     * @return array{float, float}
     */
    public function reflectedQuadraticControl(): array
    {
        if ($this->lastQuadraticControlX === null || $this->lastQuadraticControlY === null) {
            return [$this->currentX, $this->currentY];
        }
        return [
            2.0 * $this->currentX - $this->lastQuadraticControlX,
            2.0 * $this->currentY - $this->lastQuadraticControlY,
        ];
    }

    public function closeSubpath(): void
    {
        $this->currentX = $this->subpathStartX;
        $this->currentY = $this->subpathStartY;
        $this->clearControlPoints();
    }
}
