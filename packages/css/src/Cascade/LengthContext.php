<?php

declare(strict_types=1);

namespace Phpdftk\Css\Cascade;

/**
 * Context needed to resolve relative lengths (em/rem/%/vw/vh/ex/ch/lh/rlh)
 * to absolute pixels.
 *
 * - `parentFontSize` — used when resolving `em` on the `font-size` property
 *   itself (parent's font-size is the reference).
 * - `currentFontSize` — used for everything else; the resolved font-size of
 *   the element being computed. Two-pass cascade: resolve font-size first
 *   with `parentFontSize`, then resolve other lengths using the result.
 * - `rootFontSize` — `rem` reference; the document root's font-size.
 * - `viewportWidth` / `viewportHeight` — `vw` / `vh` references in CSS pixels.
 * - `percentageBasis` — the basis a `%` length resolves against; depends on
 *   the property (width: containing-block width; line-height: own font-size,
 *   etc.). Callers pass per-property as needed.
 */
final readonly class LengthContext
{
    public function __construct(
        public float $parentFontSize = 16.0,
        public float $currentFontSize = 16.0,
        public float $rootFontSize = 16.0,
        public float $viewportWidth = 816.0,    // 8.5in × 96 DPI
        public float $viewportHeight = 1056.0,  // 11in × 96 DPI
        public float $percentageBasis = 0.0,
        /**
         * x-height as a fraction of the em-square. CSS Values 4 §6.1.1
         * defines `ex` as the x-height of the element's first
         * available font; without font metrics we approximate with
         * `0.5em` (a sensible fallback for sans-serif designs).
         * Layout code that has access to the resolved font passes
         * `font.xHeight / font.unitsPerEm` here so `ex` for fonts
         * like Ahem (xHeight = full em) resolves correctly.
         */
        public float $xHeightRatio = 0.5,
        /**
         * Width of the `0` (ZERO) glyph as a fraction of the em-square.
         * CSS Values 4 §6.1.1 — `ch` is the advance of `0`. The same
         * 0.5 fallback applies when font metrics aren't reachable.
         */
        public float $chWidthRatio = 0.5,
    ) {}

    public function withCurrentFontSize(float $px): self
    {
        return new self(
            $this->parentFontSize,
            $px,
            $this->rootFontSize,
            $this->viewportWidth,
            $this->viewportHeight,
            $this->percentageBasis,
            $this->xHeightRatio,
            $this->chWidthRatio,
        );
    }

    public function withPercentageBasis(float $px): self
    {
        return new self(
            $this->parentFontSize,
            $this->currentFontSize,
            $this->rootFontSize,
            $this->viewportWidth,
            $this->viewportHeight,
            $px,
            $this->xHeightRatio,
            $this->chWidthRatio,
        );
    }

    public function withFontMetrics(float $xHeightRatio, float $chWidthRatio): self
    {
        return new self(
            $this->parentFontSize,
            $this->currentFontSize,
            $this->rootFontSize,
            $this->viewportWidth,
            $this->viewportHeight,
            $this->percentageBasis,
            $xHeightRatio,
            $chWidthRatio,
        );
    }
}
