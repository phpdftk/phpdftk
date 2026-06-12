<?php

declare(strict_types=1);

namespace Phpdftk\HtmlToPdf\Layout;

use Phpdftk\Css\Cascade\LengthContext;
use Phpdftk\FontParser\FontFaceData;

/**
 * Per-layout-step context: the containing block's content width / height,
 * the current X / Y origin where the next child will go, the
 * `LengthContext` used by the CSS resolver for em / rem / vw / vh / %, and
 * the default font supplied to {@see InlineLayout} for text measurement.
 *
 * Layout creates child contexts (via `with*()`) when it descends into a
 * box, shifting origin and updating the containing-block measurements so
 * `%` resolves correctly.
 *
 * `defaultFont` is null when no font is wired in yet — in that case
 * inline layout falls back to producing zero-height placeholders so
 * block layout can still run end-to-end. Hosts that want real typography
 * provide a parsed `FontFaceData` here (see `phpdftk/font-parser`).
 */
final readonly class LayoutContext
{
    public function __construct(
        public float $containingBlockWidth,
        public float $containingBlockHeight,
        public float $originX,
        public float $originY,
        public LengthContext $lengthContext,
        public ?FontFaceData $defaultFont = null,
        /**
         * Optional multi-font selector. When set, `InlineLayout` picks the
         * shaping font per box from this resolver — falling back to
         * `defaultFont` when no `font-family` matches.
         */
        public ?FontResolver $fontResolver = null,
        /**
         * Tracks active floats per CSS 2.1 §9.5 for the current block
         * formatting context. `InlineLayout` queries this to shorten
         * line boxes that overlap a float's vertical extent. Null when
         * no BFC has registered any floats yet.
         */
        public ?FloatContext $floatContext = null,
    ) {}

    public function withOrigin(float $x, float $y): self
    {
        return new self(
            $this->containingBlockWidth,
            $this->containingBlockHeight,
            $x,
            $y,
            $this->lengthContext,
            $this->defaultFont,
            $this->fontResolver,
            $this->floatContext,
        );
    }

    public function withContainingBlock(float $width, float $height): self
    {
        return new self(
            $width,
            $height,
            $this->originX,
            $this->originY,
            $this->lengthContext,
            $this->defaultFont,
            $this->fontResolver,
            $this->floatContext,
        );
    }

    public function withLengthContext(LengthContext $ctx): self
    {
        return new self(
            $this->containingBlockWidth,
            $this->containingBlockHeight,
            $this->originX,
            $this->originY,
            $ctx,
            $this->defaultFont,
            $this->fontResolver,
            $this->floatContext,
        );
    }

    public function withFloatContext(?FloatContext $ctx): self
    {
        return new self(
            $this->containingBlockWidth,
            $this->containingBlockHeight,
            $this->originX,
            $this->originY,
            $this->lengthContext,
            $this->defaultFont,
            $this->fontResolver,
            $ctx,
        );
    }
}
