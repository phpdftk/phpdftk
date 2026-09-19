<?php

declare(strict_types=1);

namespace Phpdftk\HtmlToPdf\Layout;

use Phpdftk\Css\Value\Color;
use Phpdftk\HtmlToPdf\Box\AtomicInlineBox;
use Phpdftk\Text\ShapedRun;

/**
 * One positioned shaped-text fragment inside a {@see LineBox}. `x` is the
 * fragment's left edge relative to the line box's left edge; `width` is
 * the run's total advance.
 *
 * Multiple fragments per line allow a single line to mix runs from
 * different inline parents (e.g. `<p>hello <em>world</em></p>` produces a
 * "hello " fragment and a "world" fragment on the same line).
 */
final readonly class InlineFragment
{
    public function __construct(
        public float $x,
        public float $width,
        public ShapedRun $shapedRun,
        /**
         * Additional Y offset applied to the fragment's baseline relative
         * to the line's main baseline. Used for `vertical-align: sub` /
         * `super` — negative values lift the fragment (higher on the page),
         * positive values drop it. Layout-space Y, top-down.
         */
        public float $baselineShift = 0.0,
        /**
         * When non-null, the fragment originated inside an `<a href>`
         * subtree; the painter emits a `/Link` annotation covering the
         * fragment's rect, targeting this URI.
         */
        public ?string $href = null,
        /**
         * `true` when the fragment's cascaded `font-weight` is bold-ish
         * (≥ 600). The painter renders these in PDF text mode 2 (fill +
         * stroke) as a fake-bold fallback when a real bold font isn't
         * available.
         */
        public bool $isBold = false,
        /**
         * `true` when the fragment's cascaded `font-style` is `italic` or
         * `oblique`. The painter applies a skew transform in `Tm` as a
         * fake-italic fallback for the same reason.
         */
        public bool $isItalic = false,
        /**
         * Text-decoration lines effective for this fragment (per CSS Text
         * Decoration 4 §2 the property applies to "all in-flow boxes" but
         * propagates from the inline element where it was set). Values
         * are CSS keywords from the `text-decoration-line` vocabulary:
         * `'underline'` / `'overline'` / `'line-through'`.
         *
         * @var list<string>
         */
        public array $decorationLines = [],
        /**
         * Per-fragment fill color. When non-null the painter sets the text
         * fill colour to this before emitting the fragment, overriding the
         * line's block-level default — needed for inline elements like
         * `<a>` whose UA `color` differs from the surrounding paragraph.
         */
        public ?Color $textColor = null,
        /**
         * Per-fragment inline background, propagated downward from an
         * `InlineBox` whose cascade sets `background-color`. When non-null
         * the painter fills a rect under the fragment in this colour before
         * the text emits, matching browser inline-background rendering of
         * elements like `<mark>`.
         */
        public ?Color $backgroundColor = null,
        /**
         * Companion `<a title>` text. Lands on the link annotation's
         * `/Contents` field — PDF viewers show this as a tooltip on hover.
         */
        public ?string $linkTitle = null,
        /**
         * Per-fragment text-decoration colour (CSS Text Decoration 4 §3).
         * When non-null the painter uses this when stroking the fragment's
         * underline / overline / line-through, overriding the block-level
         * default — so an inline element like
         * `<u style="text-decoration-color: red">` paints a red underline
         * even when the surrounding paragraph carries the cascaded default.
         */
        public ?Color $decorationColor = null,
        /**
         * `true` when the fragment is a pure whitespace token (spaces,
         * tabs, newlines). Used by `applyTextAlign` to honour
         * CSS Text 3 §5.5 — trailing whitespace at the end of a line
         * "hangs" and is excluded from the alignment slack
         * calculation — and by future justify-distribution work to
         * skip the trailing-ws gap.
         */
        public bool $isWhitespace = false,
        /**
         * The fragment's own used `line-height` in layout units (CSS Inline
         * 3 §3), resolved against the inline box it came from — NOT the
         * block's. Drives the half-leading extent math in `lineMetrics`:
         * leading = lineHeight − (ascent + descent), split half above / half
         * below the fragment's content box. The sentinel `-1.0` means "unset"
         * — the line sizer then falls back to the fragment's font ascent +
         * descent (no leading), preserving pre-half-leading behaviour for
         * synthetic fragments (ellipsis, trims) that don't carry a resolved
         * value. `-1.0` rather than `0.0` so an explicit `line-height: 0` is
         * honoured as zero leading, not mistaken for unset.
         */
        public float $lineHeight = -1.0,
        /**
         * The line-relative `vertical-align` keyword for this fragment's
         * originating inline box (CSS2 §10.8.1) — one of `top`, `bottom`,
         * `middle`, `text-top`, `text-bottom`. The default `baseline` (also
         * covers `sub`/`super`/`<length>`, which are folded into
         * `baselineShift` during the tree walk since they compose through
         * nesting). The keyword forms do NOT compose and are resolved against
         * the line box / strut in `finalizeLine`, which overrides
         * `baselineShift` for these fragments.
         */
        public string $verticalAlign = 'baseline',
        /**
         * Vertical writing-mode transpose (CSS Writing Modes 4 §7.1): the
         * fragment's offset DOWN the column (physical +Y = inline axis)
         * from the line box's `y`. `applyVerticalLineShift` moves each
         * fragment's original inline advance (its horizontal `x`) here and
         * repurposes `x` as the column's block-axis (physical-X) position,
         * so multiple fragments in one source line stack down a single
         * column. The painter adds it to the column top. Zero (the default)
         * for horizontal-tb, so every non-vertical path is byte-identical.
         */
        public float $blockOffset = 0.0,
        /**
         * When non-null, this fragment stands in for an inline atomic /
         * replaced box (`<img>`, inline-block, inline SVG). Its glyph run is
         * empty (advance-only), so line-box sizing derives the fragment's
         * ascent/descent from this box's committed margin-box height rather
         * than the surrounding font metrics (CSS 2.1 §10.8 — an inline
         * replaced/inline-block box contributes its margin-box height to the
         * line, with the box's baseline at its bottom margin edge). The atomic
         * box's own `geometry->y` is re-committed against the finalized line
         * baseline in {@see InlineLayout::commitAtomicFragmentY}. Text
         * fragments keep `null`, so their sizing path is unchanged.
         */
        public ?AtomicInlineBox $atomicBox = null,
        /**
         * How far this fragment's background extends beyond the line box,
         * above and below.
         *
         * CSS 2.1 §10.6.1 — an inline box's vertical padding and border do
         * NOT change the line box height, but they ARE painted, bleeding over
         * the adjacent lines. Layout ignores these entirely; only the painter
         * reads them.
         */
        public float $bgExtendAbove = 0.0,
        public float $bgExtendBelow = 0.0,
    ) {}

    /**
     * Clone with a different {@see $blockOffset}, preserving every other
     * field. Used when a vertical writing-mode box shrink-wraps its inline
     * size and its transposed runs have to be re-aligned against the size
     * the box actually ended up with (CSS Writing Modes 4 §7.1).
     */
    public function withBlockOffset(float $blockOffset): self
    {
        return new self(
            $this->x,
            $this->width,
            $this->shapedRun,
            $this->baselineShift,
            $this->href,
            $this->isBold,
            $this->isItalic,
            $this->decorationLines,
            $this->textColor,
            $this->backgroundColor,
            $this->linkTitle,
            $this->decorationColor,
            $this->isWhitespace,
            $this->lineHeight,
            $this->verticalAlign,
            $blockOffset,
            $this->atomicBox,
            $this->bgExtendAbove,
            $this->bgExtendBelow,
        );
    }
}
