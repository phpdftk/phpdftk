<?php

declare(strict_types=1);

namespace Phpdftk\MathmlToPdf;

use Phpdftk\Mathml\Element;
use Phpdftk\Mathml\GenericElement;
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
            $element instanceof Mrow    => $this->walkChildren($element, $stream, $upright, $italic, $fontSize),
            $element instanceof GenericElement => $this->walkChildren($element, $stream, $upright, $italic, $fontSize),
            // MathmlDocument flows through here too — its base class
            // is Element with no special painter behaviour for the
            // tracer-bullet slice, so children walk like an <mrow>.
            default => $this->walkChildren($element, $stream, $upright, $italic, $fontSize),
        };
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
