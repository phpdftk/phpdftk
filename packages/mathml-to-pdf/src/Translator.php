<?php

declare(strict_types=1);

namespace Phpdftk\MathmlToPdf;

use Phpdftk\Mathml\Element;
use Phpdftk\Mathml\GenericElement;
use Phpdftk\Mathml\Mfrac;
use Phpdftk\Mathml\Mi;
use Phpdftk\Mathml\Mn;
use Phpdftk\Mathml\Mo;
use Phpdftk\Mathml\Mrow;
use Phpdftk\Mathml\Ms;
use Phpdftk\Mathml\Mtext;
use Phpdftk\Pdf\Core\Content\ContentStream;
use Phpdftk\Pdf\Writer\Font;

/**
 * Per-element paint dispatcher. The {@see MathmlRenderer} owns the
 * BT/ET text block and the font resources; the translator just walks
 * the typed tree and emits the right `Tf` / `Tj` operators.
 *
 * v1 scope: tokens (Mn, Mi, Mo, Ms, Mtext) + `<mrow>` + the document
 * root. GenericElement (unknown / future tags) recurses into children
 * so a stray container doesn't drop everything inside it on the
 * floor. All other tags route to GenericElement at parse time, so
 * they all reach this fallback.
 */
final class Translator
{
    public function paint(
        Element $element,
        ContentStream $stream,
        Font $upright,
        Font $italic,
        float $fontSize,
    ): void {
        match (true) {
            $element instanceof Mn      => $this->paintMn($element, $stream),
            $element instanceof Mi      => $this->paintMi($element, $stream, $upright, $italic, $fontSize),
            $element instanceof Mo      => $this->paintMo($element, $stream),
            $element instanceof Ms      => $this->paintMs($element, $stream),
            $element instanceof Mtext   => $this->paintMtext($element, $stream),
            $element instanceof Mfrac   => $this->paintMfrac($element, $stream, $upright, $italic, $fontSize),
            $element instanceof Mrow    => $this->walkChildren($element, $stream, $upright, $italic, $fontSize),
            $element instanceof GenericElement => $this->walkChildren($element, $stream, $upright, $italic, $fontSize),
            // MathmlDocument flows through here too — its base class
            // is Element with no special painter behaviour for the
            // tracer-bullet slice, so children walk like an <mrow>.
            default => $this->walkChildren($element, $stream, $upright, $italic, $fontSize),
        };
    }

    /**
     * Paint `<mfrac>` as a vertically stacked numerator + denominator.
     *
     * Uses PDF text-matrix repositioning (`Td`) plus text rise to
     * place numerator above the surrounding baseline and denominator
     * below, centring each within the wider of the two. The pen
     * advances past the fraction's right edge so subsequent tokens
     * flow correctly.
     *
     * Width estimation: each child's `textContent()` character count
     * times an `~0.5em` advance. This is approximate for Times-Roman
     * (good for digits and lowercase, off for uppercase wide glyphs)
     * and acceptable for the tracer-bullet. Real glyph-derived
     * widths land once the renderer learns to measure its own output.
     *
     * NOT YET DRAWN: the horizontal fraction bar between numerator
     * and denominator. Drawing it requires breaking out of the
     * BT/ET text block to emit path operators, which in turn means
     * threading the absolute fraction coordinates through the
     * Translator. Deferred to a follow-up. The
     * `linethickness="0"` form (binomial coefficients — no bar by
     * spec) renders correctly today.
     *
     * Invalid `<mfrac>` with anything other than exactly two element
     * children walks all children inline as a fallback so malformed
     * markup doesn't drop content.
     */
    private function paintMfrac(
        Mfrac $mfrac,
        ContentStream $stream,
        Font $upright,
        Font $italic,
        float $fontSize,
    ): void {
        $children = array_values(array_filter(
            $mfrac->children,
            static fn($c) => $c instanceof Element,
        ));
        if (count($children) !== 2) {
            $this->walkChildren($mfrac, $stream, $upright, $italic, $fontSize);
            return;
        }
        [$numerator, $denominator] = [$children[0], $children[1]];

        $charWidth = $fontSize * 0.5;
        $numWidth = mb_strlen($numerator->textContent(), 'UTF-8') * $charWidth;
        $denWidth = mb_strlen($denominator->textContent(), 'UTF-8') * $charWidth;
        $fracWidth = max($numWidth, $denWidth);
        if ($fracWidth < 0.001) {
            return;
        }

        // Baseline offsets above/below the surrounding line. ~0.4em
        // each puts the numerator and denominator about 4/5 of a line
        // apart — visually closer than two normal lines, which is
        // what mfrac wants.
        $raise = $fontSize * 0.4;
        $drop = $fontSize * 0.4;

        $numLead = ($fracWidth - $numWidth) / 2.0;
        $denLead = ($fracWidth - $denWidth) / 2.0;

        // Numerator: shift line origin into the centred position on
        // the raised baseline, emit children, leave pen where Tj left
        // it (at numLead + numWidth past the fraction's left edge,
        // on the raised baseline).
        $stream->moveTextPosition($numLead, $raise);
        $this->paint($numerator, $stream, $upright, $italic, $fontSize);

        // Denominator: shift line origin from where it is now to the
        // centred position on the lowered baseline. moveTextPosition
        // (Td) is relative to the current line matrix — the pen
        // resets to the new origin so we don't have to compensate
        // for whatever horizontal advance the numerator emitted.
        $stream->moveTextPosition($denLead - $numLead, -$drop - $raise);
        $this->paint($denominator, $stream, $upright, $italic, $fontSize);

        // Advance to the right edge of the fraction on the original
        // baseline so subsequent tokens (`<mfrac><mrow>1</mrow>...
        // </mfrac><mo>+</mo>…`) flow correctly.
        $stream->moveTextPosition($fracWidth - $denLead, $drop);
    }

    private function walkChildren(
        Element $parent,
        ContentStream $stream,
        Font $upright,
        Font $italic,
        float $fontSize,
    ): void {
        foreach ($parent->children as $child) {
            if ($child instanceof Element) {
                $this->paint($child, $stream, $upright, $italic, $fontSize);
            }
            // Text children outside token elements are not part of
            // the MathML content model; we ignore them rather than
            // pollute the rendered output.
        }
    }

    private function paintMn(Mn $mn, ContentStream $stream): void
    {
        $content = $mn->textContent();
        if ($content === '') {
            return;
        }
        // <mn> is always upright per Core §3.2.4; the outer setFont
        // (upright) is already active, so we just emit the glyphs.
        $stream->showText($content);
    }

    private function paintMi(
        Mi $mi,
        ContentStream $stream,
        Font $upright,
        Font $italic,
        float $fontSize,
    ): void {
        $content = $mi->textContent();
        if ($content === '') {
            return;
        }
        // Core §3.2.3: single-character `<mi>` renders italic by
        // default; multi-character content (`sin`, `log`) renders
        // upright. `mathvariant` would override this; deferred to a
        // follow-up that wires the full variant table.
        $useItalic = $this->isSingleVisibleChar($content) && $mi->mathvariant() === null;
        $face = $useItalic ? $italic : $upright;
        $stream->setFont($face, $fontSize);
        $stream->showText($content);
        // Restore upright for whatever runs after this <mi>.
        if ($useItalic) {
            $stream->setFont($upright, $fontSize);
        }
    }

    private function paintMo(Mo $mo, ContentStream $stream): void
    {
        $content = $mo->textContent();
        if ($content === '') {
            return;
        }
        // Operator spacing (lspace / rspace from the dictionary) is
        // deferred to the follow-up that ports the MathML Operator
        // Dictionary. Tracer-bullet emits the glyph(s) verbatim.
        $stream->showText($content);
    }

    private function paintMs(Ms $ms, ContentStream $stream): void
    {
        $content = $ms->textContent();
        // <ms> wraps its content in lquote / rquote characters; the
        // typed accessors fall back to ASCII " when absent.
        $stream->showText($ms->lquote() . $content . $ms->rquote());
    }

    private function paintMtext(Mtext $mtext, ContentStream $stream): void
    {
        $content = $mtext->textContent();
        if ($content === '') {
            return;
        }
        $stream->showText($content);
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
