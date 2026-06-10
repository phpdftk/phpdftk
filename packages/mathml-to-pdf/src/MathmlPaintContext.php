<?php

declare(strict_types=1);

namespace Phpdftk\MathmlToPdf;

use Phpdftk\Pdf\Core\Content\ContentStream;
use Phpdftk\Pdf\Writer\Font;

/**
 * Shared per-render state threaded through every {@see Translator}
 * `paint()` call. Carries the active content stream + fonts + font
 * size (immutable for the duration of a single `MathmlRenderer::draw`)
 * alongside the current cursor position in absolute PDF user-space
 * coordinates (mutable as glyphs and paths are emitted).
 *
 * Why a context object instead of additional positional parameters:
 *
 *   - `paintMfrac` needs the fraction's left/right edges in absolute
 *     coords to draw the horizontal bar after `endText()` (we have to
 *     break out of the BT/ET text block to emit path operators).
 *   - `paintMsqrt` / `paintMroot` need the same to draw the vinculum.
 *   - Nested constructs (continued fractions, fractions inside
 *     radicals) need to recurse with an updated cursor without
 *     repeatedly re-threading five positional parameters.
 *
 * The cursor (`cursorX`, `cursorY`) tracks where the next token will
 * land. After every `Tj` the painter updates `cursorX` by the
 * estimated glyph width; after vertical-stacking constructs
 * (`<mfrac>`, `<msqrt>`) the painter sets `cursorX` to the construct's
 * right edge.
 *
 * `cursorY` is the baseline of the surrounding math line — it stays
 * constant for horizontal flow. Vertical-stacking constructs use
 * PDF's text-rise (`Ts`) operator and `Td` line-matrix moves to
 * position content above / below this baseline, but the post-
 * construct cursor returns to `cursorY` so subsequent siblings flow
 * correctly.
 */
final class MathmlPaintContext
{
    public function __construct(
        public readonly ContentStream $stream,
        public readonly Font $upright,
        public readonly Font $italic,
        public readonly float $fontSize,
        /**
         * Mutable horizontal cursor in absolute PDF user-space
         * coordinates. Advances as content is emitted; set to the
         * right edge of a construct after vertical stacking.
         */
        public float $cursorX,
        /**
         * Baseline Y in absolute PDF user-space coordinates. Treated
         * as readonly by the painters today — the surrounding line
         * doesn't move during a single math expression.
         */
        public readonly float $baselineY,
        /**
         * Layout direction inherited from the enclosing element's
         * `dir` attribute, per MathML Core §3.1.5.4. When `rtl`,
         * {@see Translator::walkChildren()} iterates element children
         * in reverse source order so the first source child sits at
         * the rightmost visual position. Defaults to `ltr`.
         */
        public readonly string $direction = 'ltr',
    ) {}
}
