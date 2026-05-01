<?php

declare(strict_types=1);

namespace ApprLabs\Pdf\Reader;

/**
 * A positioned span of text extracted from a PDF page.
 *
 * Coordinates are in PDF user space (origin at bottom-left of page).
 * Width and height are computed from font metrics and the current
 * text/graphics state at the time the text was rendered.
 */
final class TextSpan
{
    public function __construct(
        /** The Unicode text content of this span. */
        public readonly string $text,
        /** X coordinate of the span origin (left edge) in user space points. */
        public readonly float $x,
        /** Y coordinate of the span baseline in user space points. */
        public readonly float $y,
        /** Width of the span in user space points. */
        public readonly float $width,
        /** Height of the span in user space points (based on font size). */
        public readonly float $height,
        /** Font size in points at the time of rendering. */
        public readonly float $fontSize,
        /** PDF resource name of the font (e.g., "F1", "F2"). */
        public readonly string $fontName,
    ) {}
}
