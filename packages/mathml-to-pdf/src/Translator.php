<?php

declare(strict_types=1);

namespace Phpdftk\MathmlToPdf;

use Phpdftk\Mathml\Element;
use Phpdftk\Mathml\GenericElement;
use Phpdftk\Mathml\Mfrac;
use Phpdftk\Mathml\Mi;
use Phpdftk\Mathml\Mn;
use Phpdftk\Mathml\Mo;
use Phpdftk\Mathml\Mroot;
use Phpdftk\Mathml\Mrow;
use Phpdftk\Mathml\Ms;
use Phpdftk\Mathml\Msqrt;
use Phpdftk\Mathml\Mtext;

/**
 * Per-element paint dispatcher. The {@see MathmlRenderer} owns the
 * BT/ET text block and the font resources; the translator walks the
 * typed tree and emits the right `Tf` / `Tj` / path operators against
 * a {@see MathmlPaintContext} that carries the active stream + fonts
 * + cursor state.
 *
 * v1 scope:
 *
 *   - Tokens (Mn, Mi, Mo, Ms, Mtext)
 *   - `<mrow>` (transparent container — children render inline)
 *   - `<mfrac>` (vertical-stacked numerator/denominator with bar)
 *   - `<msqrt>` (radical with vinculum — no √ glyph yet)
 *   - `<mroot>` (radical with vinculum + small index)
 *
 * GenericElement (unknown / future tags) recurses into children so a
 * stray container doesn't drop everything inside it on the floor. All
 * tags outside this set route to GenericElement at parse time, so
 * they all reach the fallback.
 *
 * Width estimation note: every paint method uses ~0.5em per character
 * as the advance width for glyphs (Times-Roman digits and lowercase
 * average close to this). Wide glyphs / italics / uppercase will be
 * slightly under-estimated; this is acceptable for the tracer-bullet
 * stage. Real glyph-derived widths land when the renderer learns to
 * read Type1 AFM metrics.
 */
final class Translator
{
    /** Approximate em-fraction width per character for Times-Roman. */
    private const float CHAR_EM_WIDTH = 0.5;

    public function paint(Element $element, MathmlPaintContext $ctx): void
    {
        match (true) {
            $element instanceof Mn      => $this->paintMn($element, $ctx),
            $element instanceof Mi      => $this->paintMi($element, $ctx),
            $element instanceof Mo      => $this->paintMo($element, $ctx),
            $element instanceof Ms      => $this->paintMs($element, $ctx),
            $element instanceof Mtext   => $this->paintMtext($element, $ctx),
            $element instanceof Mfrac   => $this->paintMfrac($element, $ctx),
            $element instanceof Msqrt   => $this->paintMsqrt($element, $ctx),
            $element instanceof Mroot   => $this->paintMroot($element, $ctx),
            $element instanceof Mrow    => $this->walkChildren($element, $ctx),
            $element instanceof GenericElement => $this->walkChildren($element, $ctx),
            // MathmlDocument flows through here too — its base class
            // is Element with no special painter behaviour for the
            // tracer-bullet slice, so children walk like an <mrow>.
            default => $this->walkChildren($element, $ctx),
        };
    }

    // -----------------------------------------------------------------
    // Vertical-stacking constructs (fractions, radicals)
    // -----------------------------------------------------------------

    /**
     * Paint `<mfrac>` as a vertically stacked numerator + denominator
     * with a horizontal bar between them.
     *
     * Steps:
     *   1. Centre numerator within `fracWidth`, raise the baseline,
     *      emit children.
     *   2. Centre denominator on the lowered baseline, emit children.
     *   3. Break out of the text block, draw the bar at midline,
     *      restart the text block, restore position past the fraction.
     *
     * The bar is skipped when `linethickness="0"` (binomial form per
     * Core §3.3.2).
     *
     * Invalid `<mfrac>` with anything other than exactly two element
     * children walks all children inline as a fallback so malformed
     * markup doesn't drop content.
     */
    private function paintMfrac(Mfrac $mfrac, MathmlPaintContext $ctx): void
    {
        $children = $this->elementChildren($mfrac);
        if (count($children) !== 2) {
            $this->walkChildren($mfrac, $ctx);
            return;
        }
        [$numerator, $denominator] = [$children[0], $children[1]];

        $numWidth = $this->estimateWidth($numerator, $ctx->fontSize);
        $denWidth = $this->estimateWidth($denominator, $ctx->fontSize);
        $fracWidth = max($numWidth, $denWidth);
        if ($fracWidth < 0.001) {
            return;
        }

        $raise = $ctx->fontSize * 0.4;
        $drop = $ctx->fontSize * 0.4;
        $numLead = ($fracWidth - $numWidth) / 2.0;
        $denLead = ($fracWidth - $denWidth) / 2.0;
        $fracLeftX = $ctx->cursorX;

        // Numerator: shift line origin into centred-on-raised position
        $ctx->stream->moveTextPosition($numLead, $raise);
        $this->paint($numerator, $ctx);

        // Denominator: shift from current line origin to centred-on-
        // lowered position. moveTextPosition (Td) is relative to
        // current line matrix; resets pen to the new origin so we
        // don't have to compensate for the numerator's Tj advance.
        $ctx->stream->moveTextPosition($denLead - $numLead, -$drop - $raise);
        $this->paint($denominator, $ctx);

        // Advance to the fraction's right edge on the original
        // baseline so subsequent siblings flow correctly.
        $ctx->stream->moveTextPosition($fracWidth - $denLead, $drop);
        $ctx->cursorX = $fracLeftX + $fracWidth;

        // Bar: spec default is 1 CSS pixel ≈ 0.75 pt. `linethickness="0"`
        // suppresses the bar (binomial form). The Translator can now
        // break out of the text block thanks to the absolute coords
        // in ctx — see drawHorizontalRule.
        $barThickness = $mfrac->linethickness();
        if ($barThickness === null) {
            $barThickness = 0.75;
        } elseif ($barThickness === 0.0) {
            return;
        } else {
            $barThickness *= 0.75; // CSS px → PDF points
        }
        // Bar Y: roughly half-em above the surrounding baseline —
        // matches where the eye expects a fraction line to sit.
        $barY = $ctx->baselineY + $ctx->fontSize * 0.3;
        $this->drawHorizontalRule(
            $ctx,
            $fracLeftX,
            $barY,
            $fracWidth,
            $barThickness,
        );
    }

    /**
     * Paint `<msqrt>` as content under a horizontal vinculum (overline).
     *
     * Tracer-bullet limitation: the radical sign √ itself is NOT drawn.
     * The standard Type1 Times-Roman font ships without the
     * U+221A glyph in StandardEncoding, so emitting it as text would
     * fail. The vinculum alone is recognisable as a radical from
     * context (`√x` reads correctly as "x under an overline"). Adding
     * the √ stroke is a follow-up gated on Symbol-font integration or
     * a math font.
     *
     * Children are walked as a transparent group (like `<mrow>`) — any
     * combination of tokens / containers under the vinculum is valid.
     */
    private function paintMsqrt(Msqrt $msqrt, MathmlPaintContext $ctx): void
    {
        $contentWidth = $this->estimateWidth($msqrt, $ctx->fontSize);
        if ($contentWidth < 0.001) {
            return;
        }
        $radLeftX = $ctx->cursorX;
        // Render content first — vinculum draws on top.
        $this->walkChildren($msqrt, $ctx);
        // Cursor should now be at radLeftX + contentWidth (approximately,
        // by estimation). Force-set so subsequent siblings flow
        // predictably even if estimation drifted.
        $ctx->cursorX = $radLeftX + $contentWidth;

        // Vinculum: thin horizontal rule above the content. Position
        // it ~1.0em above the baseline so it sits just above the
        // x-height of the content.
        $vinculumY = $ctx->baselineY + $ctx->fontSize * 0.85;
        $this->drawHorizontalRule(
            $ctx,
            $radLeftX,
            $vinculumY,
            $contentWidth,
            0.75,
        );
    }

    /**
     * Paint `<mroot>` as base under vinculum, with a small superscript-
     * sized index at the upper-left.
     *
     * Per Core §3.3.4 / §3.3.5, `<mroot>` has exactly two element
     * children: `<mroot>BASE INDEX</mroot>`. The base goes under the
     * vinculum; the index sits to the upper-left, at scriptlevel +1
     * (~0.7em font size, raised by ~0.5em).
     *
     * Invalid `<mroot>` with anything other than two element children
     * falls back to inline walk so content isn't dropped.
     */
    private function paintMroot(Mroot $mroot, MathmlPaintContext $ctx): void
    {
        $children = $this->elementChildren($mroot);
        if (count($children) !== 2) {
            $this->walkChildren($mroot, $ctx);
            return;
        }
        [$base, $index] = [$children[0], $children[1]];

        // Index: small font, raised. Emit first so it sits to the
        // upper-left of the base. Approximate width = base * 0.7.
        $indexFontSize = $ctx->fontSize * 0.7;
        $indexWidth = $this->estimateWidth($index, $indexFontSize);
        $indexRaise = $ctx->fontSize * 0.5;
        $rootLeftX = $ctx->cursorX;

        if ($indexWidth >= 0.001) {
            $ctx->stream->setFont($ctx->upright, $indexFontSize);
            $ctx->stream->moveTextPosition(0, $indexRaise);
            $indexCtx = new MathmlPaintContext(
                stream: $ctx->stream,
                upright: $ctx->upright,
                italic: $ctx->italic,
                fontSize: $indexFontSize,
                cursorX: $ctx->cursorX,
                baselineY: $ctx->baselineY + $indexRaise,
            );
            $this->paint($index, $indexCtx);
            // Drop back to base baseline + restore main font size.
            $ctx->stream->moveTextPosition(0, -$indexRaise);
            $ctx->stream->setFont($ctx->upright, $ctx->fontSize);
            $ctx->cursorX += $indexWidth;
        }

        // Base under vinculum (same shape as msqrt body, without the
        // recursive call into paintMsqrt — we already have the index).
        $baseLeftX = $ctx->cursorX;
        $baseWidth = $this->estimateWidth($base, $ctx->fontSize);
        if ($baseWidth < 0.001) {
            return;
        }
        $this->paint($base, $ctx);
        $ctx->cursorX = $baseLeftX + $baseWidth;

        $vinculumY = $ctx->baselineY + $ctx->fontSize * 0.85;
        $this->drawHorizontalRule($ctx, $baseLeftX, $vinculumY, $baseWidth, 0.75);
    }

    // -----------------------------------------------------------------
    // Tokens
    // -----------------------------------------------------------------

    private function paintMn(Mn $mn, MathmlPaintContext $ctx): void
    {
        $this->emitText($mn->textContent(), $ctx);
    }

    private function paintMi(Mi $mi, MathmlPaintContext $ctx): void
    {
        $content = $mi->textContent();
        if ($content === '') {
            return;
        }
        // Core §3.2.3: single-character <mi> renders italic by default.
        $useItalic = $this->isSingleVisibleChar($content) && $mi->mathvariant() === null;
        if ($useItalic) {
            $ctx->stream->setFont($ctx->italic, $ctx->fontSize);
        }
        $this->emitText($content, $ctx);
        if ($useItalic) {
            $ctx->stream->setFont($ctx->upright, $ctx->fontSize);
        }
    }

    private function paintMo(Mo $mo, MathmlPaintContext $ctx): void
    {
        // Operator spacing (lspace / rspace from the dictionary) is
        // deferred to the follow-up that ports the MathML Operator
        // Dictionary. Tracer-bullet emits the glyph(s) verbatim.
        $this->emitText($mo->textContent(), $ctx);
    }

    private function paintMs(Ms $ms, MathmlPaintContext $ctx): void
    {
        // <ms> wraps its content in lquote / rquote characters; the
        // typed accessors fall back to ASCII " when absent.
        $this->emitText($ms->lquote() . $ms->textContent() . $ms->rquote(), $ctx);
    }

    private function paintMtext(Mtext $mtext, MathmlPaintContext $ctx): void
    {
        $this->emitText($mtext->textContent(), $ctx);
    }

    // -----------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------

    private function walkChildren(Element $parent, MathmlPaintContext $ctx): void
    {
        foreach ($parent->children as $child) {
            if ($child instanceof Element) {
                $this->paint($child, $ctx);
            }
            // Text children outside token elements are not part of
            // the MathML content model; we ignore them rather than
            // pollute the rendered output.
        }
    }

    private function emitText(string $content, MathmlPaintContext $ctx): void
    {
        if ($content === '') {
            return;
        }
        $ctx->stream->showText($content);
        $ctx->cursorX += mb_strlen($content, 'UTF-8') * $ctx->fontSize * self::CHAR_EM_WIDTH;
    }

    /**
     * Break out of the BT/ET text block, draw a horizontal rule at
     * absolute coords, restart the text block at the original cursor
     * position. Used for fraction bars and radical vinculums.
     *
     * Restoring the text position is critical — without it, subsequent
     * tokens would draw from (0, 0) in absolute coords. We use
     * setTextMatrix (Tm) for an absolute reset rather than Td chains.
     */
    private function drawHorizontalRule(
        MathmlPaintContext $ctx,
        float $x,
        float $y,
        float $width,
        float $thickness,
    ): void {
        $stream = $ctx->stream;
        $stream->endText();
        $stream->saveGraphicsState();
        $stream->setLineWidth($thickness);
        $stream->moveTo($x, $y);
        $stream->lineTo($x + $width, $y);
        $stream->stroke();
        $stream->restoreGraphicsState();
        $stream->beginText();
        $stream->setFont($ctx->upright, $ctx->fontSize);
        // Tm — absolute text matrix. Identity rotation/scale, translate
        // to (cursorX, baselineY). Subsequent Tj advances the pen
        // from there.
        $stream->setTextMatrix(1.0, 0.0, 0.0, 1.0, $ctx->cursorX, $ctx->baselineY);
    }

    /**
     * Rough width estimate based on flattened text content. Wide
     * glyphs, italics, and uppercase will be under-estimated; this
     * is acceptable for the tracer-bullet positioning math.
     */
    private function estimateWidth(Element $element, float $fontSize): float
    {
        return mb_strlen($element->textContent(), 'UTF-8') * $fontSize * self::CHAR_EM_WIDTH;
    }

    /** @return list<Element> */
    private function elementChildren(Element $parent): array
    {
        return array_values(array_filter(
            $parent->children,
            static fn($c) => $c instanceof Element,
        ));
    }

    /**
     * "Single visible character" per Core §3.2.3 — a single Unicode
     * grapheme, excluding combining marks. We approximate via
     * `mb_strlen` which counts codepoints; close enough for the
     * tracer-bullet ASCII / BMP cases. The follow-up that ports
     * Core's full variant table can use grapheme-aware counting.
     */
    private function isSingleVisibleChar(string $content): bool
    {
        return mb_strlen($content, 'UTF-8') === 1;
    }
}
