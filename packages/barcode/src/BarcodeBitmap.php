<?php

declare(strict_types=1);

namespace Phpdftk\Barcode;

/**
 * The output of {@see BarcodeRenderer::render()}: a 2D grid of bools
 * (`true` = dark module, `false` = light) plus intrinsic dimensions.
 *
 * For a 1D barcode, the grid is exactly 1 row tall (height comes from
 * {@see BarcodeOptions::$height}). For a 2D barcode, the grid is N×N.
 *
 * Consumers (PDF, PNG, SVG renderers) walk the grid and draw filled
 * rectangles for each `true` cell at `moduleWidth` x `moduleWidth`
 * (or `moduleWidth` x `height` for 1D rows).
 */
final class BarcodeBitmap
{
    /**
     * @param list<list<bool>> $modules Rows of columns; `true` = dark.
     */
    public function __construct(
        public readonly array $modules,
        public readonly float $moduleWidth,
        public readonly float $height,
        public readonly int $quietZoneModules,
    ) {}

    /** Number of columns (modules) in each row. */
    public function columns(): int
    {
        return $this->modules === [] ? 0 : count($this->modules[0]);
    }

    public function rows(): int
    {
        return count($this->modules);
    }

    /** Total width including quiet zones, in user units. */
    public function totalWidth(): float
    {
        return ($this->columns() + 2 * $this->quietZoneModules) * $this->moduleWidth;
    }

    /** Total height including module height (1D) or module-square (2D). */
    public function totalHeight(): float
    {
        // 1D = single row, full bar height. 2D = N rows × moduleWidth (square modules).
        return $this->rows() === 1
            ? $this->height
            : $this->rows() * $this->moduleWidth;
    }
}
