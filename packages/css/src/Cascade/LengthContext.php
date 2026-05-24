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
        );
    }
}
