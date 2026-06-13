<?php

declare(strict_types=1);

namespace Phpdftk\HtmlToPdf\Layout;

use Phpdftk\Css\Value\Color;
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
    ) {}
}
