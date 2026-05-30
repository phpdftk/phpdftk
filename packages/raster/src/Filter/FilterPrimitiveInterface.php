<?php

declare(strict_types=1);

namespace Phpdftk\Raster\Filter;

use Phpdftk\Raster\RasterSurface;

/**
 * One SVG / CSS filter primitive — `feGaussianBlur`, `feColorMatrix`,
 * `feConvolveMatrix`, `feMorphology`, `feFlood`, `feImage`,
 * `feTurbulence`, `feDisplacementMap`, `feSpecularLighting`,
 * `feDiffuseLighting`, `feMerge`, `feTile`, `feOffset`,
 * `feDropShadow`, `feComponentTransfer`, `feComposite`.
 *
 * Each primitive maps one or more input surfaces to one output
 * surface. The CSS Filter Effects 1 + SVG 2 §15 spec defines the
 * exact pixel math; Phase 4C.3 implements them one primitive at a
 * time.
 *
 * The interface is stable from this scaffold so the translator
 * can be written against it now and pick up real implementations
 * as they land.
 */
interface FilterPrimitiveInterface
{
    /**
     * Apply the primitive to `$input` and return a new surface.
     * The input is not modified (primitives are pure functions of
     * their inputs).
     *
     * Multi-input primitives (`feBlend`, `feComposite`, `feMerge`,
     * `feDisplacementMap`) accept a list of surfaces in their
     * declared input order via the implementation's constructor;
     * the `apply` signature stays single-input so the type stays
     * uniform across the filter chain.
     */
    public function apply(RasterSurface $input): RasterSurface;
}
