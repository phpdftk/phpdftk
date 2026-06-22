<?php

declare(strict_types=1);

namespace Phpdftk\HtmlToPdf\Layout;

use Phpdftk\Css\Cascade\LengthContext;
use Phpdftk\Css\Cascade\WritingMode;
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
        /**
         * CSS 2.1 §10.1 — the containing block for `position:
         * absolute` / `fixed` descendants is the nearest positioned
         * ancestor's PADDING box, not the immediate parent. We thread
         * that ancestor's padding-box rectangle here so abs-pos
         * layout can read it directly. Null when no positioned
         * ancestor is established yet — `BlockLayout` falls back to
         * the initial containing block (the canvas).
         */
        public ?PositionedAncestor $positionedAncestor = null,
        /**
         * CSS 2.1 §10.5 + CSS Position 3 §3.4 — whether
         * `$containingBlockHeight` is the spec's "definite height"
         * (resolves percentage `top` / `bottom` / `height` directly)
         * or "indefinite" (those percentages resolve to 0). The
         * viewport at the root is always definite; descending through
         * an auto-height intermediate block breaks the chain. The
         * `<html>` and `<body>` elements are special-cased to inherit
         * the viewport per the HTML rendering rules.
         */
        public bool $containingBlockHeightDefinite = true,
        /**
         * CSS Writing Modes 4 §7.4 — the containing block's writing
         * mode determines which axis is the inline-axis for
         * percentage resolution of margin / padding. In `horizontal-
         * tb` the inline axis is x, so percentages resolve against
         * `containingBlockWidth`; in `vertical-*` the inline axis is
         * y, so they resolve against `containingBlockHeight`. Set
         * by the parent when it dispatches children; null at the
         * root (initial value = `horizontal-tb`, the default basis).
         */
        public ?WritingMode $parentWritingMode = null,
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
            $this->positionedAncestor,
            $this->containingBlockHeightDefinite,
            $this->parentWritingMode,
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
            $this->positionedAncestor,
            $this->containingBlockHeightDefinite,
            $this->parentWritingMode,
        );
    }

    public function withContainingBlockHeightDefinite(float $height, bool $definite): self
    {
        return new self(
            $this->containingBlockWidth,
            $height,
            $this->originX,
            $this->originY,
            $this->lengthContext,
            $this->defaultFont,
            $this->fontResolver,
            $this->floatContext,
            $this->positionedAncestor,
            $definite,
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
            $this->positionedAncestor,
            $this->containingBlockHeightDefinite,
            $this->parentWritingMode,
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
            $this->positionedAncestor,
            $this->containingBlockHeightDefinite,
            $this->parentWritingMode,
        );
    }

    public function withPositionedAncestor(?PositionedAncestor $pa): self
    {
        return new self(
            $this->containingBlockWidth,
            $this->containingBlockHeight,
            $this->originX,
            $this->originY,
            $this->lengthContext,
            $this->defaultFont,
            $this->fontResolver,
            $this->floatContext,
            $pa,
            $this->containingBlockHeightDefinite,
            $this->parentWritingMode,
        );
    }

    public function withParentWritingMode(?WritingMode $wm): self
    {
        return new self(
            $this->containingBlockWidth,
            $this->containingBlockHeight,
            $this->originX,
            $this->originY,
            $this->lengthContext,
            $this->defaultFont,
            $this->fontResolver,
            $this->floatContext,
            $this->positionedAncestor,
            $this->containingBlockHeightDefinite,
            $wm,
        );
    }
}
