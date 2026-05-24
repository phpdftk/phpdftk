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
final class AtomicInlineBox extends Box {}
