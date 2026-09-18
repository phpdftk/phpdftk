<?php

declare(strict_types=1);

namespace Phpdftk\HtmlToPdf\Box;

/**
 * A box that's inline-level on the outside but opaque to inline layout —
 * `display: inline-block`, replaced elements (`<img>`, `<input>`),
 * `<svg>`, etc.
 *
 * Treated as a single atomic glyph in the parent inline formatting
 * context: it takes a known width / height computed from its own
 * intrinsic dimensions or CSS sizing, but its internal layout is its
 * own affair (typically a BFC).
 */
final class AtomicInlineBox extends Box
{
    /**
     * CSS 2.1 §10.3.9 — the content-box inline size this box resolved to
     * when its own formatting context was laid out, set by
     * `BlockLayout::layoutInlineAtomicContents()` before the surrounding
     * inline formatting context runs.
     *
     * `null` means the box was never pre-laid (a replaced element, or one
     * with no in-flow children), in which case `InlineLayout` falls back
     * to sizing it straight off the cascade.
     */
    public ?float $laidOutContentWidth = null;

    /** Companion block size for {@see $laidOutContentWidth}. */
    public ?float $laidOutContentHeight = null;
}
