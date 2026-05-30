<?php

declare(strict_types=1);

namespace Phpdftk\Raster\Painter;

use Phpdftk\Raster\RasterSurface;
use Phpdftk\Raster\RgbaPixel;
use Phpdftk\Raster\BlendMode;

/**
 * Surface painter — primitive tree → pixels.
 *
 * Phase 4C.1 implements the concrete painter. The interface is
 * stable from the scaffold so call sites in svg-to-pdf and
 * html-to-pdf can be written against it now.
 *
 * The painter is stateful with respect to the surface (which it
 * writes into) but stateless across primitives — each call is a
 * self-contained drawing op. Caller manages the graphics-state
 * stack at the translator layer.
 */
interface PainterInterface
{
    /**
     * Fill a path defined by a sequence of PDF-style path operators
     * (`m`, `l`, `c`, `v`, `y`, `h`) plus the SVG arc-to-cubic
     * conversion svg-to-pdf already does. The fill colour is
     * applied to all pixels inside the winding-rule-determined
     * interior.
     *
     * @param list<array{op: string, args: list<float>}> $path
     */
    public function fillPath(array $path, RgbaPixel $fill, string $fillRule = 'nonzero'): void;

    /**
     * Stroke a path with the given colour and width. Stroke
     * geometry (linecap, linejoin, dasharray) follows the SVG /
     * CSS conventions.
     *
     * @param list<array{op: string, args: list<float>}> $path
     */
    public function strokePath(array $path, RgbaPixel $stroke, float $width): void;

    /**
     * Composite another surface onto this one at `(x, y)` with the
     * given blend mode. Source-over by default — the standard
     * Porter-Duff "src over dst" rule. Other blend modes go through
     * the CSS Compositing 1 / 2 math (4C.2).
     */
    public function compositeSurface(
        RasterSurface $source,
        int $x,
        int $y,
        BlendMode $blendMode = BlendMode::Normal,
    ): void;
}
