<?php

declare(strict_types=1);

namespace Phpdftk\MathmlToPdf;

use Phpdftk\Mathml\Element;
use Phpdftk\Mathml\GenericElement;
use Phpdftk\Mathml\Maction;
use Phpdftk\Mathml\Merror;
use Phpdftk\Mathml\Mfrac;
use Phpdftk\Mathml\Mi;
use Phpdftk\Mathml\Mmultiscripts;
use Phpdftk\Mathml\Mn;
use Phpdftk\Mathml\Mo;
use Phpdftk\Mathml\Mover;
use Phpdftk\Mathml\Mprescripts;
use Phpdftk\Mathml\Mroot;
use Phpdftk\Mathml\Mrow;
use Phpdftk\Mathml\Ms;
use Phpdftk\Mathml\Msqrt;
use Phpdftk\Mathml\Msub;
use Phpdftk\Mathml\Msubsup;
use Phpdftk\Mathml\Msup;
use Phpdftk\Mathml\Menclose;
use Phpdftk\Mathml\Mpadded;
use Phpdftk\Mathml\Mphantom;
use Phpdftk\Mathml\Mspace;
use Phpdftk\Mathml\Mstyle;
use Phpdftk\Mathml\Mtable;
use Phpdftk\Mathml\Mtd;
use Phpdftk\Mathml\Mtext;
use Phpdftk\Mathml\Mtr;
use Phpdftk\Mathml\Munder;
use Phpdftk\Mathml\Munderover;
use Phpdftk\Mathml\NoneElement;
use Phpdftk\Mathml\OperatorDictionary;
use Phpdftk\Mathml\Semantics;
use Phpdftk\Pdf\Core\Content\ContentStream;

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
 *   - `<msub>` / `<msup>` / `<msubsup>` (subscript / superscript)
 *   - `<munder>` / `<mover>` / `<munderover>` (under / over)
 *   - `<mmultiscripts>` (arbitrary pre/post script pairs)
 *   - `<mtable>` / `<mtr>` / `<mtd>` (2-D grid layout)
 *   - `<mspace>` / `<mpadded>` / `<mphantom>` (spacing primitives)
 *   - `<menclose>` (notation framers: box, longdiv, strikes, edges)
 *
 * GenericElement (unknown / future tags) recurses into children so a
 * stray container doesn't drop everything inside it on the floor. All
 * tags outside this set route to GenericElement at parse time, so
 * they all reach the fallback.
 *
 * Width estimation uses real AFM glyph widths from
 * {@see MathmlGlyphMetrics} - Times-Roman for the upright face,
 * Times-Italic for the italic face used on single-character `<mi>`.
 * Characters outside WinAnsi fall back to the font's .notdef width;
 * those characters would render as `?` in the standard fonts anyway,
 * pending the OpenType MATH-table font work.
 */
final class Translator
{
    /**
     * Paint one element. `$operatorForm`, when supplied, hints the
     * default form for an `<mo>` whose own `form` attribute is
     * absent — the painter computes it from sibling position in
     * {@see walkChildren}. Outside `<mo>` paint, the parameter is
     * ignored.
     */
    public function paint(
        Element $element,
        MathmlPaintContext $ctx,
        ?string $operatorForm = null,
    ): void {
        match (true) {
            $element instanceof Mn      => $this->paintMn($element, $ctx),
            $element instanceof Mi      => $this->paintMi($element, $ctx),
            $element instanceof Mo      => $this->paintMo($element, $ctx, $operatorForm),
            $element instanceof Ms      => $this->paintMs($element, $ctx),
            $element instanceof Mtext   => $this->paintMtext($element, $ctx),
            $element instanceof Mfrac   => $this->paintMfrac($element, $ctx),
            $element instanceof Msqrt   => $this->paintMsqrt($element, $ctx),
            $element instanceof Mroot   => $this->paintMroot($element, $ctx),
            $element instanceof Msub    => $this->paintMsub($element, $ctx),
            $element instanceof Msup    => $this->paintMsup($element, $ctx),
            $element instanceof Msubsup => $this->paintMsubsup($element, $ctx),
            $element instanceof Munder  => $this->paintMunder($element, $ctx),
            $element instanceof Mover   => $this->paintMover($element, $ctx),
            $element instanceof Munderover => $this->paintMunderover($element, $ctx),
            $element instanceof Mmultiscripts => $this->paintMmultiscripts($element, $ctx),
            $element instanceof Mtable  => $this->paintMtable($element, $ctx),
            $element instanceof Mspace  => $this->paintMspace($element, $ctx),
            $element instanceof Mpadded => $this->paintMpadded($element, $ctx),
            $element instanceof Mphantom => $this->paintMphantom($element, $ctx),
            $element instanceof Menclose => $this->paintMenclose($element, $ctx),
            $element instanceof Mstyle  => $this->paintMstyle($element, $ctx),
            $element instanceof Maction => $this->paintMaction($element, $ctx),
            $element instanceof Merror  => $this->paintMerror($element, $ctx),
            $element instanceof Semantics => $this->paintSemantics($element, $ctx),
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

        // Per MathML Core §3.1.6, <mfrac> sets displaystyle=false on
        // its children and (in inline mode only) bumps their
        // scriptlevel by 1. Build the child context up front and use
        // its scaled fontSize for width estimation + paint.
        $displayStyle = $mfrac->displaystyle();
        $effectiveDisplay = $displayStyle ?? $ctx->displayStyle;
        $childCtx = $this->childContextForFraction($ctx, $effectiveDisplay);

        $numWidth = $this->estimateWidth($numerator, $childCtx->fontSize, $childCtx);
        $denWidth = $this->estimateWidth($denominator, $childCtx->fontSize, $childCtx);
        $fracWidth = max($numWidth, $denWidth);
        if ($fracWidth < 0.001) {
            return;
        }

        // `displaystyle="true"` on <mfrac> picks the taller
        // display-style shifts from MathConstants (typical of
        // fractions inside `<math display="block">`); otherwise the
        // inline shifts apply.
        $raise = $ctx->fontSize * $ctx->metrics->fractionNumeratorShiftUpEm($effectiveDisplay);
        $drop = $ctx->fontSize * $ctx->metrics->fractionDenominatorShiftDownEm($effectiveDisplay);
        $numLead = ($fracWidth - $numWidth) / 2.0;
        $denLead = ($fracWidth - $denWidth) / 2.0;
        $fracLeftX = $ctx->cursorX;

        // Numerator: shift line origin into centred-on-raised position
        // and switch to the scaled child font.
        $ctx->stream->moveTextPosition($numLead, $raise);
        if ($childCtx->fontSize !== $ctx->fontSize) {
            $ctx->stream->setFont($this->activeFont($ctx), $childCtx->fontSize);
        }
        $childCtx->cursorX = $fracLeftX + $numLead;
        $this->paint($numerator, $childCtx);

        // Denominator: shift from current line origin to centred-on-
        // lowered position. moveTextPosition (Td) is relative to
        // current line matrix; resets pen to the new origin so we
        // don't have to compensate for the numerator's Tj advance.
        $ctx->stream->moveTextPosition($denLead - $numLead, -$drop - $raise);
        $childCtx->cursorX = $fracLeftX + $denLead;
        $this->paint($denominator, $childCtx);

        // Advance to the fraction's right edge on the original
        // baseline so subsequent siblings flow correctly. Restore
        // the parent font size before doing so.
        if ($childCtx->fontSize !== $ctx->fontSize) {
            $ctx->stream->setFont($this->activeFont($ctx), $ctx->fontSize);
        }
        $ctx->stream->moveTextPosition($fracWidth - $denLead, $drop);
        $ctx->cursorX = $fracLeftX + $fracWidth;

        // Bar thickness: author's `linethickness` wins; otherwise
        // the font's fractionRuleThickness (in em, scaled by current
        // size) when a math font is active, else the historical
        // 0.75 pt default.
        $linethickness = $mfrac->linethickness();
        if ($linethickness === 0.0) {
            return;
        }
        if ($linethickness !== null) {
            $barThickness = $linethickness * 0.75; // CSS px -> PDF points
        } else {
            $barThickness = $ctx->metrics->fractionRuleThicknessEm() * $ctx->fontSize;
            // Math fonts often report large values (the test fonts
            // do up to 10em as an extreme); for the standard-font
            // fallback this evaluates to 0.0625em * 12pt = 0.75pt
            // which matches the previous hardcode.
        }
        // Bar Y: at the math axis above the surrounding baseline
        // (axisHeight when a math font is active; the default keeps
        // the previous 0.3em raise).
        $barY = $ctx->baselineY + $ctx->fontSize * (
            $ctx->metrics->isMathFontActive()
                ? $ctx->metrics->axisHeightEm()
                : 0.3
        );
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
    /** Unicode radical sign emitted before msqrt/mroot content when a
     *  math font is loaded. Standard fonts don't carry this glyph in
     *  WinAnsi - the painter falls back to vinculum-only in that case. */
    private const string RADICAL_SIGN = "\u{221A}";

    private function paintMsqrt(Msqrt $msqrt, MathmlPaintContext $ctx): void
    {
        $contentWidth = $this->estimateWidth($msqrt, $ctx->fontSize);
        if ($contentWidth < 0.001) {
            return;
        }
        $rootLeftX = $ctx->cursorX;

        // Emit the radical sign when a math font is loaded - that's
        // when the painter knows U+221A renders correctly. Without
        // a math font, the standard Type 1 face has no √ glyph in
        // WinAnsi, so we keep the vinculum-only fallback the
        // tracer-bullet shipped with.
        $this->emitRadicalSign($ctx);
        $contentStartX = $ctx->cursorX;

        // Render content under the vinculum.
        $this->walkChildren($msqrt, $ctx);
        $ctx->cursorX = $contentStartX + $contentWidth;

        // Vinculum spans the content (NOT the radical sign).
        $vinculumY = $ctx->baselineY + $ctx->fontSize * $ctx->metrics->overbarVerticalOffsetEm();
        $this->drawHorizontalRule(
            $ctx,
            $contentStartX,
            $vinculumY,
            $contentWidth,
            $ctx->metrics->overbarRuleThicknessEm() * $ctx->fontSize,
        );
        // If we emitted a radical sign, the cursor reflects radical
        // width + content width. Otherwise we leave the historical
        // contentWidth-only behaviour intact.
        unset($rootLeftX);
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

        // Index: smaller font, raised. Emit first so it sits to
        // the upper-left of the base. The index renders at
        // scriptscript level per Core §3.1.6, so we ask the
        // cascade helper for `levelDelta = 2`.
        $indexFontSize = $this->scriptFontSizeFor($ctx, levelDelta: 2);
        $indexWidth = $this->estimateWidth($index, $indexFontSize);
        $indexRaise = $ctx->fontSize * 0.5;
        $rootLeftX = $ctx->cursorX;

        if ($indexWidth >= 0.001) {
            $ctx->stream->setFont($this->activeFont($ctx), $indexFontSize);
            $ctx->stream->moveTextPosition(0, $indexRaise);
            // mroot's index renders at scriptscript level per
            // Core §3.1.6 - use levelDelta=2 instead of 1.
            $indexCtx = $this->childContextForScript(
                $ctx,
                $indexFontSize,
                $ctx->cursorX,
                $ctx->baselineY + $indexRaise,
                levelDelta: 2,
            );
            $this->paint($index, $indexCtx);
            // Drop back to base baseline + restore main font size.
            $ctx->stream->moveTextPosition(0, -$indexRaise);
            $ctx->stream->setFont($this->activeFont($ctx), $ctx->fontSize);
            $ctx->cursorX += $indexWidth;
        }

        // Radical sign (between the index and the base when a math
        // font is loaded). The index already shifted the cursor
        // right by its width; the radical now sits right of it.
        $this->emitRadicalSign($ctx);

        // Base under vinculum (same shape as msqrt body, without the
        // recursive call into paintMsqrt — we already have the index).
        $baseLeftX = $ctx->cursorX;
        $baseWidth = $this->estimateWidth($base, $ctx->fontSize);
        if ($baseWidth < 0.001) {
            return;
        }
        $this->paint($base, $ctx);
        $ctx->cursorX = $baseLeftX + $baseWidth;

        $vinculumY = $ctx->baselineY + $ctx->fontSize * $ctx->metrics->overbarVerticalOffsetEm();
        $this->drawHorizontalRule(
            $ctx,
            $baseLeftX,
            $vinculumY,
            $baseWidth,
            $ctx->metrics->overbarRuleThicknessEm() * $ctx->fontSize,
        );
    }

    /**
     * Emit the Unicode radical sign U+221A before the radicand
     * content. No-op when no math font is loaded - standard Type 1
     * fonts don't carry the glyph in WinAnsi, so emitText would
     * substitute `?` which is uglier than no radical at all.
     */
    private function emitRadicalSign(MathmlPaintContext $ctx): void
    {
        if ($ctx->mathFont === null) {
            return;
        }
        // Confirm U+221A made it into the font's cmap before
        // emitting. emitText handles the math-font Type 0 path
        // automatically; we just need to advance the cursor.
        $cp = 0x221A;
        if (!isset($ctx->mathFont->unicodeToGid[$cp])) {
            return;
        }
        $this->emitText(self::RADICAL_SIGN, $ctx);
    }

    // -----------------------------------------------------------------
    // Scripts (msub / msup / msubsup) — attached at base right edge
    //
    // Script metrics (font scale, superscript shift up, subscript
    // shift down) come from {@see MathmlMetrics} on the paint
    // context. When a math font is loaded, the metrics flow from
    // the font's MathConstants table; otherwise they fall back to
    // the tracer-bullet defaults (0.7 / 0.5 / 0.3 em).
    // -----------------------------------------------------------------

    /**
     * Paint `<msub>` as base followed by a smaller subscript at
     * lower-right. Two element children expected.
     */
    private function paintMsub(Msub $msub, MathmlPaintContext $ctx): void
    {
        $children = $this->elementChildren($msub);
        if (count($children) !== 2) {
            $this->walkChildren($msub, $ctx);
            return;
        }
        [$base, $sub] = [$children[0], $children[1]];
        $this->paint($base, $ctx);
        $subShiftEm = $ctx->metrics->subscriptShiftDownEm();
        $this->applyCornerKern($base, $ctx, 'bottomRight', $subShiftEm);
        $this->paintScript($sub, $ctx, -$ctx->fontSize * $subShiftEm);
    }

    /**
     * Paint `<msup>` as base followed by a smaller superscript at
     * upper-right. Two element children expected.
     *
     * When the base renders italic (single-char `<mi>` + a math
     * font), the painter applies the italic-correction X shift
     * between the base and the superscript per OpenType MATH spec.
     * This visually clears the slanted upper-right of italic glyphs
     * so the script doesn't kiss the base.
     */
    private function paintMsup(Msup $msup, MathmlPaintContext $ctx): void
    {
        $children = $this->elementChildren($msup);
        if (count($children) !== 2) {
            $this->walkChildren($msup, $ctx);
            return;
        }
        [$base, $sup] = [$children[0], $children[1]];
        $this->paint($base, $ctx);
        $this->applyItalicCorrection($base, $ctx);
        $supShiftEm = $ctx->metrics->superscriptShiftUpEm();
        $this->applyCornerKern($base, $ctx, 'topRight', $supShiftEm);
        $this->paintScript($sup, $ctx, $ctx->fontSize * $supShiftEm);
    }

    /**
     * Paint `<msubsup>` as base followed by both subscript and
     * superscript attached at the same x (the base right edge).
     * Three element children expected.
     *
     * After painting both scripts, the cursor advances to
     * `base_right + max(sub_width, sup_width)` so subsequent siblings
     * flow correctly.
     */
    private function paintMsubsup(Msubsup $msubsup, MathmlPaintContext $ctx): void
    {
        $children = $this->elementChildren($msubsup);
        if (count($children) !== 3) {
            $this->walkChildren($msubsup, $ctx);
            return;
        }
        [$base, $sub, $sup] = [$children[0], $children[1], $children[2]];
        $this->paint($base, $ctx);
        // Subscript attach point - BEFORE italic correction. The
        // sub sits at the base's right edge regardless of italic
        // correction; only the sup shifts right.
        $subAttachX = $ctx->cursorX;
        $this->applyItalicCorrection($base, $ctx);
        $supAttachX = $ctx->cursorX;
        $scriptFontSize = $this->scriptFontSizeFor($ctx);
        $subWidth = $this->estimateWidth($sub, $scriptFontSize);
        $supWidth = $this->estimateWidth($sup, $scriptFontSize);

        // Sup first at raised baseline.
        $this->paintScript($sup, $ctx, $ctx->fontSize * $ctx->metrics->superscriptShiftUpEm());
        // After paintScript, cursor advanced by supWidth from
        // supAttachX. Back up horizontally to the sub attach point
        // (unshifted by italic correction), then drop to the
        // subscript baseline.
        $backup = $subAttachX - $ctx->cursorX;
        $ctx->stream->setFont($this->activeFont($ctx), $scriptFontSize);
        $ctx->stream->moveTextPosition($backup, -$ctx->fontSize * $ctx->metrics->subscriptShiftDownEm());
        $subCtx = $this->childContextForScript(
            $ctx,
            $scriptFontSize,
            $subAttachX,
            $ctx->baselineY - $ctx->fontSize * $ctx->metrics->subscriptShiftDownEm(),
        );
        $this->paint($sub, $subCtx);
        // Restore to the construct's right edge on the main baseline.
        // The sup may extend further right due to italic correction;
        // pick the actual rightmost extent.
        $supRightEdge = $supAttachX + $supWidth;
        $subRightEdge = $subAttachX + $subWidth;
        $rightEdge = max($supRightEdge, $subRightEdge);
        $ctx->stream->moveTextPosition(
            $rightEdge - $subCtx->cursorX,
            $ctx->fontSize * $ctx->metrics->subscriptShiftDownEm(),
        );
        $ctx->stream->setFont($this->activeFont($ctx), $ctx->fontSize);
        $ctx->cursorX = $rightEdge;
    }

    /**
     * Shared subscript / superscript renderer. Switches font, shifts
     * baseline by `$yOffset` (positive = raise, negative = lower),
     * renders the script via a nested context, then restores.
     *
     * Caller is responsible for positioning the cursor before this
     * call — it doesn't adjust horizontal placement, only the
     * baseline + font.
     */
    private function paintScript(
        Element $script,
        MathmlPaintContext $ctx,
        float $yOffset,
    ): void {
        $scriptFontSize = $this->scriptFontSizeFor($ctx);
        $ctx->stream->setFont($this->activeFont($ctx), $scriptFontSize);
        $ctx->stream->moveTextPosition(0.0, $yOffset);
        $scriptCtx = $this->childContextForScript(
            $ctx,
            $scriptFontSize,
            $ctx->cursorX,
            $ctx->baselineY + $yOffset,
        );
        $this->paint($script, $scriptCtx);
        $ctx->cursorX = $scriptCtx->cursorX;
        $ctx->stream->moveTextPosition(0.0, -$yOffset);
        $ctx->stream->setFont($this->activeFont($ctx), $ctx->fontSize);
    }

    // -----------------------------------------------------------------
    // mmultiscripts — arbitrary pre/post script pairs
    // -----------------------------------------------------------------

    /**
     * Paint `<mmultiscripts>` per MathML Core §3.3.6.2.
     *
     * Child shape:
     *
     *   base
     *   (postSub postSup)*
     *   [ <mprescripts/>
     *     (preSub preSup)* ]
     *
     * Either side may have any number of pairs, including zero.
     * `<none/>` ({@see NoneElement}) stands in for an absent script
     * slot within a pair.
     *
     * Algorithm:
     *   1. Split element children at the {@see Mprescripts} marker.
     *   2. Validate that each list has an even count (each pair has
     *      both a sub and a sup, even if either is `<none/>`).
     *   3. Compute total prescript width so the base shifts right by
     *      that amount.
     *   4. Render prescripts in REVERSE source order — the first pair
     *      in source is closest to the base (rightmost prescript), so
     *      we paint left-to-right starting from the outermost (last
     *      source pair).
     *   5. Render base.
     *   6. Render postscripts in source order — first pair attaches
     *      immediately to base, subsequent pairs stack rightward.
     *
     * Falls back to inline `walkChildren` on malformed structure
     * (odd script count on either side).
     */
    private function paintMmultiscripts(
        Mmultiscripts $mu,
        MathmlPaintContext $ctx,
    ): void {
        $children = $this->elementChildren($mu);
        if ($children === []) {
            return;
        }
        $base = $children[0];
        $rest = array_slice($children, 1);

        // Split at <mprescripts/>.
        $boundary = -1;
        foreach ($rest as $i => $c) {
            if ($c instanceof Mprescripts) {
                $boundary = $i;
                break;
            }
        }
        if ($boundary === -1) {
            $postRaw = $rest;
            $preRaw = [];
        } else {
            $postRaw = array_slice($rest, 0, $boundary);
            $preRaw = array_slice($rest, $boundary + 1);
        }

        if (count($postRaw) % 2 !== 0 || count($preRaw) % 2 !== 0) {
            $this->walkChildren($mu, $ctx);
            return;
        }

        $postPairs = $this->pairUpScripts($postRaw);
        $prePairs = $this->pairUpScripts($preRaw);

        $scriptFontSize = $this->scriptFontSizeFor($ctx);
        $totalPreWidth = 0.0;
        foreach ($prePairs as $pair) {
            $totalPreWidth += $this->scriptPairWidth($pair, $scriptFontSize);
        }

        if ($totalPreWidth > 0.0) {
            // Reverse source order: the last pair in source is the
            // outermost (leftmost) prescript visually, so paint it
            // first as we sweep left-to-right toward the base.
            foreach (array_reverse($prePairs) as $pair) {
                $this->paintScriptPair($pair, $ctx);
            }
        }

        $this->paint($base, $ctx);

        foreach ($postPairs as $pair) {
            $this->paintScriptPair($pair, $ctx);
        }
    }

    /**
     * Pair up a flat list of script elements into [sub, sup] tuples.
     * Caller has already validated that the count is even.
     *
     * @param  list<Element>     $scripts
     * @return list<array{0: Element, 1: Element}>
     */
    private function pairUpScripts(array $scripts): array
    {
        $pairs = [];
        $count = count($scripts);
        for ($i = 0; $i + 1 < $count; $i += 2) {
            $pairs[] = [$scripts[$i], $scripts[$i + 1]];
        }
        return $pairs;
    }

    /**
     * Width budget for a [sub, sup] pair at the given script font
     * size. {@see NoneElement} slots contribute zero width.
     *
     * @param array{0: Element, 1: Element} $pair
     */
    private function scriptPairWidth(array $pair, float $scriptFontSize): float
    {
        [$sub, $sup] = $pair;
        $subWidth = $sub instanceof NoneElement
            ? 0.0
            : $this->estimateWidth($sub, $scriptFontSize);
        $supWidth = $sup instanceof NoneElement
            ? 0.0
            : $this->estimateWidth($sup, $scriptFontSize);
        return max($subWidth, $supWidth);
    }

    /**
     * Paint a [sub, sup] pair stacked at the current cursor X. The
     * pair occupies `max(subWidth, supWidth)` horizontally and
     * advances the cursor by that amount.
     *
     * Handles {@see NoneElement} slots by skipping the corresponding
     * sub or sup but still advancing the cursor by the pair width
     * (so adjacent pairs align with the pair-grid, not just the
     * rendered content).
     *
     * @param array{0: Element, 1: Element} $pair
     */
    private function paintScriptPair(array $pair, MathmlPaintContext $ctx): void
    {
        [$sub, $sup] = $pair;
        $scriptFontSize = $this->scriptFontSizeFor($ctx);
        $hasSub = !$sub instanceof NoneElement;
        $hasSup = !$sup instanceof NoneElement;
        if (!$hasSub && !$hasSup) {
            return;
        }
        $subWidth = $hasSub ? $this->estimateWidth($sub, $scriptFontSize) : 0.0;
        $supWidth = $hasSup ? $this->estimateWidth($sup, $scriptFontSize) : 0.0;
        $pairWidth = max($subWidth, $supWidth);
        if ($pairWidth < 0.001) {
            return;
        }
        $attachX = $ctx->cursorX;

        if ($hasSup && $supWidth > 0.0) {
            $this->paintScript($sup, $ctx, $ctx->fontSize * $ctx->metrics->superscriptShiftUpEm());
            // Cursor now at attachX + supWidth on original baseline.
        }

        if ($hasSub && $subWidth > 0.0) {
            // Back up to attachX, drop to sub baseline.
            $backup = $attachX - $ctx->cursorX;
            $ctx->stream->setFont($this->activeFont($ctx), $scriptFontSize);
            $ctx->stream->moveTextPosition($backup, -$ctx->fontSize * $ctx->metrics->subscriptShiftDownEm());
            $subCtx = $this->childContextForScript(
                $ctx,
                $scriptFontSize,
                $attachX,
                $ctx->baselineY - $ctx->fontSize * $ctx->metrics->subscriptShiftDownEm(),
            );
            $this->paint($sub, $subCtx);
            // End at the pair's right edge on the original baseline.
            $ctx->stream->moveTextPosition(
                $attachX + $pairWidth - $subCtx->cursorX,
                $ctx->fontSize * $ctx->metrics->subscriptShiftDownEm(),
            );
            $ctx->stream->setFont($this->activeFont($ctx), $ctx->fontSize);
            $ctx->cursorX = $attachX + $pairWidth;
            return;
        }

        // Sup-only with sup narrower than pairWidth would leave the
        // cursor short; pad to the pair's right edge.
        if ($ctx->cursorX < $attachX + $pairWidth) {
            $ctx->stream->moveTextPosition(
                $attachX + $pairWidth - $ctx->cursorX,
                0.0,
            );
            $ctx->cursorX = $attachX + $pairWidth;
        }
    }

    // -----------------------------------------------------------------
    // Table layout (mtable / mtr / mtd) — 2-D grid
    // -----------------------------------------------------------------

    /**
     * Horizontal padding between adjacent columns, in em. MathML Core's
     * default `columnspacing` is `0.8em`; we approximate with a uniform
     * gutter pending real attribute support.
     */
    /**
     * Default per-column gap when `<mtable>` carries no
     * `columnspacing` attribute. Matches Core's recommended muskip.
     */
    private const float MTABLE_COL_GAP_EM = 0.8;

    /**
     * Vertical padding between adjacent rows, in em. Core default
     * `rowspacing` is `1.0ex` (≈ 0.5em); we approximate uniformly.
     */
    private const float MTABLE_ROW_GAP_EM = 0.5;

    /**
     * Resolve the cell's horizontal lead (in points) inside its
     * column box, given the alignment cascade:
     *
     *   cell.columnAlign() > row.columnAlign()[col] > table.columnAlign()[col]
     *
     * `cell` may override; otherwise the row's per-column list (if
     * any); otherwise the table's per-column list; otherwise `center`.
     * Lists shorter than the column index repeat their last entry.
     *
     * @param Mtd|Element $cell      Cell - we accept Mtd specifically and
     *                                fall back when generic Element child
     *                                slipped in (loose markup).
     * @param list<string> $rowAligns
     * @param list<string> $tableAligns
     */
    private function cellLeadOffset(
        Element $cell,
        int $col,
        float $colWidth,
        float $cellWidth,
        array $rowAligns,
        array $tableAligns,
    ): float {
        $align = null;
        if ($cell instanceof Mtd) {
            $align = $cell->columnAlign();
        }
        if ($align === null && $rowAligns !== []) {
            $align = $rowAligns[$col] ?? $rowAligns[count($rowAligns) - 1];
        }
        if ($align === null && $tableAligns !== []) {
            $align = $tableAligns[$col] ?? $tableAligns[count($tableAligns) - 1];
        }
        $align ??= 'center';

        return match ($align) {
            'left'   => 0.0,
            'right'  => $colWidth - $cellWidth,
            default  => ($colWidth - $cellWidth) / 2.0,
        };
    }

    /**
     * Paint `<mtable>` as a 2-D grid (MathML Core §3.3.7).
     *
     * Layout algorithm:
     *
     *   1. Walk the table's typed children, gather rows ({@see Mtr}).
     *      Non-`Mtr` direct children are skipped — Core requires `Mtr`
     *      as the only direct child class of `Mtable`.
     *   2. For each row, gather its cells ({@see Mtd}). Non-`Mtd`
     *      children of an `Mtr` are skipped.
     *   3. Compute column widths: `colWidth[j] = max(width(cell[i][j]))`
     *      across all rows.
     *   4. Compute row heights: tracer-bullet uses uniform `fontSize`
     *      per row. Real per-row maxima land with proper bbox tracking.
     *   5. Paint cells column-by-column within each row, horizontally
     *      centred in their column, vertically anchored at the row's
     *      baseline (centred on the math axis approximated as
     *      `baselineY + fontSize/4`).
     *   6. Advance cursor by `tableWidth` so trailing siblings flow.
     *
     * Empty tables (no rows or all rows empty) emit nothing and don't
     * advance the cursor.
     */
    private function paintMtable(Mtable $mtable, MathmlPaintContext $ctx): void
    {
        // Materialise rows as Mtr instances so we can read per-row
        // overrides alongside the cell content.
        $rowEls = [];
        $rows = [];
        foreach ($this->elementChildren($mtable) as $rowEl) {
            if ($rowEl instanceof Mtr) {
                $rowEls[] = $rowEl;
                $rows[] = $this->elementChildren($rowEl);
            }
        }
        if ($rows === []) {
            $this->walkChildren($mtable, $ctx);
            return;
        }

        $colCount = 0;
        foreach ($rows as $row) {
            $colCount = max($colCount, count($row));
        }
        if ($colCount === 0) {
            return;
        }

        // Column widths: max(cellWidth) per column across rows.
        $colWidths = array_fill(0, $colCount, 0.0);
        foreach ($rows as $row) {
            foreach ($row as $col => $cell) {
                $w = $this->estimateWidth($cell, $ctx->fontSize);
                if ($w > $colWidths[$col]) {
                    $colWidths[$col] = $w;
                }
            }
        }

        // Resolve effective alignment + spacing arrays. Empty author
        // input falls back to the painter's defaults; non-empty input
        // extends short lists by repeating the last entry per Core.
        $tableColAlign = $mtable->columnAlign();
        $colSpacingEms = $mtable->columnSpacingEm();
        $rowSpacingEms = $mtable->rowSpacingEm();

        $colGapAt = function (int $beforeCol) use ($ctx, $colSpacingEms): float {
            $emList = $colSpacingEms;
            $emValue = $emList === []
                ? self::MTABLE_COL_GAP_EM
                : ($emList[$beforeCol] ?? $emList[count($emList) - 1]);
            return $emValue * $ctx->fontSize;
        };
        $rowGapAt = function (int $beforeRow) use ($ctx, $rowSpacingEms): float {
            $emList = $rowSpacingEms;
            $emValue = $emList === []
                ? self::MTABLE_ROW_GAP_EM
                : ($emList[$beforeRow] ?? $emList[count($emList) - 1]);
            return $emValue * $ctx->fontSize;
        };

        $rowHeight = $ctx->fontSize;

        $tableWidth = 0.0;
        foreach ($colWidths as $col => $w) {
            $tableWidth += $w;
            if ($col < $colCount - 1) {
                $tableWidth += $colGapAt($col);
            }
        }

        $tableLeftX = $ctx->cursorX;
        $rowCount = count($rows);

        // Vertical layout: precompute each row's baselineY offset so
        // varying rowGapAt() values just work. Anchor the table so its
        // visual centre lands on the math axis (offset 0).
        $rowBaselineOffsets = [];
        $cursorY = 0.0;
        for ($r = 0; $r < $rowCount; $r++) {
            $rowBaselineOffsets[$r] = $cursorY;
            if ($r < $rowCount - 1) {
                $cursorY -= $rowHeight + $rowGapAt($r);
            }
        }
        // Shift everything so the top + bottom rows are symmetric about 0.
        $bottomY = $rowBaselineOffsets[$rowCount - 1];
        $centreShift = -$bottomY / 2.0;
        foreach ($rowBaselineOffsets as $r => $offset) {
            $rowBaselineOffsets[$r] = $offset + $centreShift;
        }

        foreach ($rows as $rowIdx => $row) {
            $rowBaselineOffset = $rowBaselineOffsets[$rowIdx];
            $rowEl = $rowEls[$rowIdx];
            $rowColAlign = $rowEl->columnAlign();

            $colX = $tableLeftX;
            foreach ($row as $col => $cell) {
                $cellWidth = $this->estimateWidth($cell, $ctx->fontSize);
                $cellLeadX = $colX + $this->cellLeadOffset(
                    $cell,
                    $col,
                    $colWidths[$col],
                    $cellWidth,
                    $rowColAlign,
                    $tableColAlign,
                );

                $deltaX = $cellLeadX - $ctx->cursorX;
                $ctx->stream->moveTextPosition($deltaX, $rowBaselineOffset);
                $cellCtx = new MathmlPaintContext(
                    stream: $ctx->stream,
                    upright: $ctx->upright,
                    italic: $ctx->italic,
                    fontSize: $ctx->fontSize,
                    cursorX: $cellLeadX,
                    baselineY: $ctx->baselineY + $rowBaselineOffset,
                );
                $this->paint($cell, $cellCtx);
                $ctx->stream->moveTextPosition(0.0, -$rowBaselineOffset);
                $ctx->cursorX = $cellCtx->cursorX;

                $colX += $colWidths[$col];
                if ($col < $colCount - 1) {
                    $colX += $colGapAt($col);
                }
            }
        }

        // Advance cursor to the table's right edge so following
        // siblings flow correctly.
        $tableRightX = $tableLeftX + $tableWidth;
        if ($ctx->cursorX < $tableRightX) {
            $ctx->stream->moveTextPosition($tableRightX - $ctx->cursorX, 0.0);
            $ctx->cursorX = $tableRightX;
        }
    }

    // -----------------------------------------------------------------
    // Under / over (munder / mover / munderover) — centred above/below
    //
    // Vertical raise / drop come from {@see MathmlMetrics}; the
    // fallback default produces the same 0.85 em over-raise and
    // 0.5 em under-drop the tracer-bullet shipped with.
    // -----------------------------------------------------------------

    /** Paint `<munder>` — base with element centred below. */
    private function paintMunder(Munder $munder, MathmlPaintContext $ctx): void
    {
        $children = $this->elementChildren($munder);
        if (count($children) !== 2) {
            $this->walkChildren($munder, $ctx);
            return;
        }
        [$base, $under] = [$children[0], $children[1]];
        if ($this->shouldRouteLimitsToScripts($base, $ctx)) {
            $this->paintLimitsAsScripts($base, $under, null, $ctx);
            return;
        }
        $this->paintUnderOver(
            base: $base,
            under: $under,
            over: null,
            ctx: $ctx,
        );
    }

    /** Paint `<mover>` — base with element centred above. */
    private function paintMover(Mover $mover, MathmlPaintContext $ctx): void
    {
        $children = $this->elementChildren($mover);
        if (count($children) !== 2) {
            $this->walkChildren($mover, $ctx);
            return;
        }
        [$base, $over] = [$children[0], $children[1]];
        if ($this->shouldRouteLimitsToScripts($base, $ctx)) {
            $this->paintLimitsAsScripts($base, null, $over, $ctx);
            return;
        }
        $this->paintUnderOver(
            base: $base,
            under: null,
            over: $over,
            ctx: $ctx,
        );
    }

    /** Paint `<munderover>` — base with elements both above and below. */
    private function paintMunderover(Munderover $mu, MathmlPaintContext $ctx): void
    {
        $children = $this->elementChildren($mu);
        if (count($children) !== 3) {
            $this->walkChildren($mu, $ctx);
            return;
        }
        [$base, $under, $over] = [$children[0], $children[1], $children[2]];
        if ($this->shouldRouteLimitsToScripts($base, $ctx)) {
            $this->paintLimitsAsScripts($base, $under, $over, $ctx);
            return;
        }
        $this->paintUnderOver(
            base: $base,
            under: $under,
            over: $over,
            ctx: $ctx,
        );
    }

    /**
     * Whether `<munder>` / `<mover>` / `<munderover>` should switch to
     * sub/superscript positioning instead of the centred over/under
     * placement. Per Core §3.3.6.3, this happens when:
     *
     *   - The base is an `<mo>` with `movablelimits="true"`, AND
     *   - The painter is in *inline* style (not display).
     *
     * Inline style is the default for `<math>` without
     * `display="block"`; explicit `displaystyle` on an ancestor
     * `<mfrac>` doesn't propagate yet so we read only the document-
     * level value.
     */
    private function shouldRouteLimitsToScripts(
        Element $base,
        MathmlPaintContext $ctx,
    ): bool {
        if ($ctx->displayStyle) {
            return false;
        }
        if (!$base instanceof Mo) {
            return false;
        }
        $explicit = $base->movablelimits();
        if ($explicit !== null) {
            return $explicit;
        }
        // Fall back to the operator dictionary's default. ∑/∏/∫ etc.
        // are flagged movablelimits=true so an unmarked author
        // <mo>2211</mo> still routes correctly in inline mode.
        $entry = OperatorDictionary::lookup(
            $base->textContent(),
            'prefix',
        );
        return $entry['movablelimits'];
    }

    /**
     * Render an under/over construct as scripts: under -> subscript,
     * over -> superscript. The cursor advances by base + max(sub,
     * sup) like {@see paintMsubsup}, so the construct slots into the
     * surrounding row width correctly.
     */
    private function paintLimitsAsScripts(
        Element $base,
        ?Element $under,
        ?Element $over,
        MathmlPaintContext $ctx,
    ): void {
        $this->paint($base, $ctx);
        $attachX = $ctx->cursorX;
        $scriptFontSize = $this->scriptFontSizeFor($ctx);
        $subWidth = $under !== null
            ? $this->estimateWidth($under, $scriptFontSize) : 0.0;
        $supWidth = $over !== null
            ? $this->estimateWidth($over, $scriptFontSize) : 0.0;

        $supShift = $ctx->fontSize * $ctx->metrics->superscriptShiftUpEm();
        $subShift = $ctx->fontSize * $ctx->metrics->subscriptShiftDownEm();

        if ($over !== null && $supWidth > 0.0) {
            $this->paintScript($over, $ctx, $supShift);
        }
        if ($under !== null && $subWidth > 0.0) {
            // Back up to attachX, then drop to the sub baseline -
            // same pattern as paintMsubsup.
            $backup = $attachX - $ctx->cursorX;
            $ctx->stream->setFont($this->activeFont($ctx), $scriptFontSize);
            $ctx->stream->moveTextPosition($backup, -$subShift);
            $subCtx = $this->childContextForScript(
                $ctx,
                $scriptFontSize,
                $attachX,
                $ctx->baselineY - $subShift,
            );
            $this->paint($under, $subCtx);
            $rightEdge = $attachX + max($subWidth, $supWidth);
            $ctx->stream->moveTextPosition(
                $rightEdge - $subCtx->cursorX,
                $subShift,
            );
            $ctx->stream->setFont($this->activeFont($ctx), $ctx->fontSize);
            $ctx->cursorX = $rightEdge;
        } else {
            $rightEdge = $attachX + $supWidth;
            if ($ctx->cursorX < $rightEdge) {
                $ctx->stream->moveTextPosition(
                    $rightEdge - $ctx->cursorX,
                    0.0,
                );
                $ctx->cursorX = $rightEdge;
            }
        }
    }

    /**
     * Shared paint for `<munder>` / `<mover>` / `<munderover>`.
     *
     * Renders the base first, then positions any over/under scripts
     * centred horizontally on the base. The construct's total width
     * is the max of base, under, and over widths so all three
     * variants share the cursor-advance logic.
     */
    private function paintUnderOver(
        Element $base,
        ?Element $under,
        ?Element $over,
        MathmlPaintContext $ctx,
    ): void {
        $baseLeftX = $ctx->cursorX;
        $scriptFontSize = $this->scriptFontSizeFor($ctx);
        $baseWidth = $this->estimateWidth($base, $ctx->fontSize);
        $overWidth = $over !== null ? $this->estimateWidth($over, $scriptFontSize) : 0.0;
        $underWidth = $under !== null ? $this->estimateWidth($under, $scriptFontSize) : 0.0;
        $constructWidth = max($baseWidth, $overWidth, $underWidth);

        $this->paint($base, $ctx);
        // Cursor now at baseLeftX + baseWidth.

        if ($over !== null && $overWidth > 0.0) {
            // Top-accent attachment: when the math font supplies a
            // per-glyph attachment point for the base, use it as the
            // accent's centre instead of the base's geometric centre.
            // This visually aligns accents over italic letters (a
            // hat over î sits at the stem, not the centre of the
            // italic glyph's bounding box).
            $attachOverride = $this->topAccentAttachmentEm($base, $ctx);
            $this->placeCentredScript(
                script: $over,
                ctx: $ctx,
                baseLeftX: $baseLeftX,
                baseWidth: $baseWidth,
                constructWidth: $constructWidth,
                yOffset: $ctx->fontSize * $ctx->metrics->overscriptRaiseEm(),
                accentCentreOffsetEm: $attachOverride,
            );
        }

        if ($under !== null && $underWidth > 0.0) {
            $this->placeCentredScript(
                script: $under,
                ctx: $ctx,
                baseLeftX: $baseLeftX,
                baseWidth: $baseWidth,
                constructWidth: $constructWidth,
                yOffset: -$ctx->fontSize * $ctx->metrics->underscriptDropEm(),
            );
        }

        // Ensure the cursor advances past the construct even if both
        // scripts were absent / zero-width (rare malformed inputs).
        if ($ctx->cursorX < $baseLeftX + $constructWidth) {
            $ctx->stream->moveTextPosition(
                $baseLeftX + $constructWidth - $ctx->cursorX,
                0.0,
            );
            $ctx->cursorX = $baseLeftX + $constructWidth;
        }
    }

    /**
     * Position a small overscript or underscript centred horizontally
     * over/under the base, at the requested vertical offset. Restores
     * the cursor to the construct's right edge on the original
     * baseline.
     */
    private function placeCentredScript(
        Element $script,
        MathmlPaintContext $ctx,
        float $baseLeftX,
        float $baseWidth,
        float $constructWidth,
        float $yOffset,
        ?float $accentCentreOffsetEm = null,
    ): void {
        $scriptFontSize = $this->scriptFontSizeFor($ctx);
        $scriptWidth = $this->estimateWidth($script, $scriptFontSize);
        if ($accentCentreOffsetEm !== null) {
            // Centre the script on the attachment point reported by
            // the font instead of the geometric centre.
            $centreX = $baseLeftX + $accentCentreOffsetEm * $ctx->fontSize;
            $scriptStartX = $centreX - $scriptWidth / 2.0;
        } else {
            $scriptStartX = $baseLeftX + ($baseWidth - $scriptWidth) / 2.0;
        }
        $deltaX = $scriptStartX - $ctx->cursorX;

        $ctx->stream->setFont($this->activeFont($ctx), $scriptFontSize);
        $ctx->stream->moveTextPosition($deltaX, $yOffset);
        $scriptCtx = $this->childContextForScript(
            $ctx,
            $scriptFontSize,
            $scriptStartX,
            $ctx->baselineY + $yOffset,
        );
        $this->paint($script, $scriptCtx);

        // Restore: end at the construct's right edge on the original
        // baseline so subsequent siblings flow correctly.
        $constructRightX = $baseLeftX + $constructWidth;
        $ctx->stream->moveTextPosition(
            $constructRightX - $scriptCtx->cursorX,
            -$yOffset,
        );
        $ctx->stream->setFont($this->activeFont($ctx), $ctx->fontSize);
        $ctx->cursorX = $constructRightX;
    }

    // -----------------------------------------------------------------
    // Spacing primitives (mspace / mpadded / mphantom)
    // -----------------------------------------------------------------

    /**
     * Default width when `<mspace>` has no `width` attribute. MathML
     * Core gives `0` as the default, but most renderers (and the
     * tracer-bullet here) treat an absent width as "thin space" so
     * an attribute-less `<mspace/>` produces something visible.
     */
    private const float MSPACE_DEFAULT_WIDTH_EM = 0.2;

    /**
     * Paint `<mspace>` — advance the cursor by `width` (in em). No
     * glyph output. Negative widths shift the cursor backward (used
     * to back-up over previous content, occasionally seen for tight
     * kerning hacks).
     */
    private function paintMspace(Mspace $mspace, MathmlPaintContext $ctx): void
    {
        $widthEm = $mspace->widthEm() ?? self::MSPACE_DEFAULT_WIDTH_EM;
        $widthPt = $widthEm * $ctx->fontSize;
        if ($widthPt === 0.0) {
            return;
        }
        $ctx->stream->moveTextPosition($widthPt, 0.0);
        $ctx->cursorX += $widthPt;
    }

    /**
     * Paint `<mpadded>` — wrap a child element and apply `lspace`
     * before, then constrain the total advance to `width` after.
     *
     * Algorithm:
     *   1. Apply `lspace` shift (positive moves content right). The
     *      cursor advances by `lspace` before children paint.
     *   2. Paint children inline as a transparent group (like
     *      `<mrow>`).
     *   3. If `width` is set, force the cursor to
     *      `pre_lspace_x + width` (which may pull it back if width is
     *      narrower than natural content, or push it forward if
     *      wider).
     *
     * The vertical attributes (`height`, `depth`, `voffset`) are
     * round-tripped by the parser but ignored by the v1 painter.
     */
    private function paintMpadded(Mpadded $mpadded, MathmlPaintContext $ctx): void
    {
        $startX = $ctx->cursorX;
        $lspaceEm = $mpadded->lspaceEm() ?? 0.0;
        $lspacePt = $lspaceEm * $ctx->fontSize;
        if ($lspacePt !== 0.0) {
            $ctx->stream->moveTextPosition($lspacePt, 0.0);
            $ctx->cursorX += $lspacePt;
        }

        // voffset raises (positive) or lowers (negative) the
        // content's baseline. Apply the shift before walking
        // children and restore it after so subsequent siblings
        // flow on the original baseline.
        $voffsetEm = $mpadded->voffsetEm() ?? 0.0;
        $voffsetPt = $voffsetEm * $ctx->fontSize;
        if ($voffsetPt !== 0.0) {
            $ctx->stream->moveTextPosition(0.0, $voffsetPt);
        }

        $this->walkChildren($mpadded, $ctx);

        if ($voffsetPt !== 0.0) {
            $ctx->stream->moveTextPosition(0.0, -$voffsetPt);
        }

        $widthEm = $mpadded->widthEm();
        if ($widthEm !== null) {
            $targetX = $startX + $widthEm * $ctx->fontSize;
            $delta = $targetX - $ctx->cursorX;
            if ($delta !== 0.0) {
                $ctx->stream->moveTextPosition($delta, 0.0);
                $ctx->cursorX = $targetX;
            }
        }
    }

    /**
     * Paint `<mphantom>` — reserve space for the children's natural
     * width without emitting any glyphs.
     *
     * Tracer-bullet approach: estimate the children's width via
     * `estimateWidth()` and advance the cursor by that amount.
     * Skipping the children's `paint()` call entirely means nested
     * path operators (fraction bars, vinculums) aren't emitted
     * either — pure space reservation. A follow-up that adds a
     * "rendering-mode: invisible" flag to the paint context can
     * paint the full subtree while suppressing only the Tj/path
     * emission, restoring spec-correct behaviour for paths.
     */
    private function paintMphantom(Mphantom $mphantom, MathmlPaintContext $ctx): void
    {
        $width = $this->estimateWidth($mphantom, $ctx->fontSize);
        if ($width === 0.0) {
            return;
        }
        $ctx->stream->moveTextPosition($width, 0.0);
        $ctx->cursorX += $width;
    }

    // -----------------------------------------------------------------
    // menclose - notation framers (box, longdiv, strikes, edges)
    // -----------------------------------------------------------------

    /** Notation stroke thickness, in PDF points. */
    private const float MENCLOSE_STROKE_PT = 0.75;

    /**
     * Padding around the enclosed content, in em. Visually separates
     * the notation strokes from the glyphs they enclose.
     */
    private const float MENCLOSE_PAD_EM = 0.1;

    /**
     * Paint `<menclose>` - render children inline, then overlay each
     * requested notation decoration. Unknown notations no-op.
     *
     * Bounding box:
     *
     *   left   = startX
     *   right  = startX + contentWidth + 2 * pad
     *   bottom = baselineY - 0.2 * fontSize        (descender slop)
     *   top    = baselineY + 0.9 * fontSize        (ascender + cap)
     *
     * Each notation's stroke draws against this box. The content
     * itself paints with the natural `pad` left-padding so glyphs
     * don't kiss the frame.
     */
    /**
     * Paint `<mstyle>` — apply explicit displaystyle / scriptlevel
     * overrides for the duration of the children's paint, then drop
     * back. Per Core §3.5.1 mstyle is transparent for content: no
     * cursor adjustments, no spacing, no glyph emission beyond what
     * the children produce.
     *
     * When no override attributes are set, `mstyle` behaves as a
     * plain `<mrow>` walk.
     */
    private function paintMstyle(Mstyle $mstyle, MathmlPaintContext $ctx): void
    {
        $displayOverride = $mstyle->displaystyle();
        $levelSpec = $mstyle->scriptlevel();

        $newDisplay = $displayOverride ?? $ctx->displayStyle;

        // Resolve scriptlevel: absolute sets, relative adds.
        $newLevel = $ctx->scriptLevel;
        if ($levelSpec !== null) {
            [$mode, $value] = $levelSpec;
            if ($mode === 'absolute') {
                $newLevel = max(0, $value);
            } else {
                $newLevel = max(0, $ctx->scriptLevel + $value);
            }
        }

        if (
            $newDisplay === $ctx->displayStyle
            && $newLevel === $ctx->scriptLevel
        ) {
            // No effective change - act as transparent container.
            $this->walkChildren($mstyle, $ctx);
            return;
        }

        // Compute the child fontSize based on the new scriptLevel
        // (relative to the parent's base, NOT compounding).
        $baseFontSize = $ctx->fontSize
            / $this->scaleForLevel($ctx->scriptLevel, $ctx);
        // mstyle clamps higher than 2 to scriptscript scale.
        $clampedLevel = min(2, $newLevel);
        $childFontSize = $baseFontSize * $this->scaleForLevel($clampedLevel, $ctx);

        $emitTf = abs($childFontSize - $ctx->fontSize) > 0.001;
        if ($emitTf) {
            $ctx->stream->setFont($this->activeFont($ctx), $childFontSize);
        }

        $childCtx = new MathmlPaintContext(
            stream: $ctx->stream,
            upright: $ctx->upright,
            italic: $ctx->italic,
            fontSize: $childFontSize,
            cursorX: $ctx->cursorX,
            baselineY: $ctx->baselineY,
            direction: $ctx->direction,
            metrics: $ctx->metrics,
            mathFont: $ctx->mathFont,
            stretchTargetEm: $ctx->stretchTargetEm,
            displayStyle: $newDisplay,
            scriptLevel: $newLevel,
        );
        $this->walkChildren($mstyle, $childCtx);

        // Sync cursor advance back to the parent so trailing
        // siblings see the right position.
        $ctx->cursorX = $childCtx->cursorX;

        if ($emitTf) {
            $ctx->stream->setFont($this->activeFont($ctx), $ctx->fontSize);
        }
    }

    private function paintMenclose(Menclose $menclose, MathmlPaintContext $ctx): void
    {
        $startX = $ctx->cursorX;
        $padPt = $ctx->fontSize * self::MENCLOSE_PAD_EM;

        // Shift content right by pad before paint so glyphs sit
        // inside the frame.
        if ($padPt > 0.0) {
            $ctx->stream->moveTextPosition($padPt, 0.0);
            $ctx->cursorX += $padPt;
        }

        $this->walkChildren($menclose, $ctx);

        // Pad on the right side too.
        if ($padPt > 0.0) {
            $ctx->stream->moveTextPosition($padPt, 0.0);
            $ctx->cursorX += $padPt;
        }

        $left = $startX;
        $right = $ctx->cursorX;
        $bottom = $ctx->baselineY - $ctx->fontSize * 0.2;
        $top = $ctx->baselineY + $ctx->fontSize * 0.9;

        // Single graphics-state save covers every notation in the
        // list, then restores once at the end.
        $stream = $ctx->stream;
        $stream->endText();
        $stream->saveGraphicsState();
        $stream->setLineWidth(self::MENCLOSE_STROKE_PT);

        foreach ($menclose->notations() as $notation) {
            $this->drawNotation(
                $stream,
                $notation,
                $left,
                $bottom,
                $right,
                $top,
            );
        }

        $stream->restoreGraphicsState();
        $stream->beginText();
        $stream->setFont($this->activeFont($ctx), $ctx->fontSize);
        // Tm - absolute reset so the next emit picks up at the right
        // place. Same pattern as drawHorizontalRule.
        $stream->setTextMatrix(1.0, 0.0, 0.0, 1.0, $ctx->cursorX, $ctx->baselineY);
    }

    /**
     * Emit the path operators for a single notation keyword against
     * the menclose bounding box (left, bottom, right, top). Caller
     * has already broken out of BT/ET and set the line width.
     */
    private function drawNotation(
        ContentStream $stream,
        string $notation,
        float $left,
        float $bottom,
        float $right,
        float $top,
    ): void {
        $midX = ($left + $right) / 2.0;
        $midY = ($bottom + $top) / 2.0;

        switch ($notation) {
            case 'box':
            case 'roundedbox':
                // Four edges. Rounded variant ignores the corner
                // radius for v1.
                $stream->moveTo($left, $bottom);
                $stream->lineTo($right, $bottom);
                $stream->lineTo($right, $top);
                $stream->lineTo($left, $top);
                $stream->lineTo($left, $bottom);
                $stream->stroke();
                return;

            case 'longdiv':
                // Top edge + left edge (open on the right).
                $stream->moveTo($left, $bottom);
                $stream->lineTo($left, $top);
                $stream->lineTo($right, $top);
                $stream->stroke();
                return;

            case 'actuarial':
                // Top edge + right edge.
                $stream->moveTo($left, $top);
                $stream->lineTo($right, $top);
                $stream->lineTo($right, $bottom);
                $stream->stroke();
                return;

            case 'top':
                $stream->moveTo($left, $top);
                $stream->lineTo($right, $top);
                $stream->stroke();
                return;

            case 'bottom':
                $stream->moveTo($left, $bottom);
                $stream->lineTo($right, $bottom);
                $stream->stroke();
                return;

            case 'left':
                $stream->moveTo($left, $bottom);
                $stream->lineTo($left, $top);
                $stream->stroke();
                return;

            case 'right':
                $stream->moveTo($right, $bottom);
                $stream->lineTo($right, $top);
                $stream->stroke();
                return;

            case 'horizontalstrike':
                $stream->moveTo($left, $midY);
                $stream->lineTo($right, $midY);
                $stream->stroke();
                return;

            case 'verticalstrike':
                $stream->moveTo($midX, $bottom);
                $stream->lineTo($midX, $top);
                $stream->stroke();
                return;

            case 'updiagonalstrike':
                // Bottom-left to top-right.
                $stream->moveTo($left, $bottom);
                $stream->lineTo($right, $top);
                $stream->stroke();
                return;

            case 'downdiagonalstrike':
                // Top-left to bottom-right.
                $stream->moveTo($left, $top);
                $stream->lineTo($right, $bottom);
                $stream->stroke();
                return;

            case 'madruwb':
                // Mirror of `actuarial` - bottom edge + right edge.
                $stream->moveTo($left, $bottom);
                $stream->lineTo($right, $bottom);
                $stream->lineTo($right, $top);
                $stream->stroke();
                return;

            case 'phasorangle':
                // A right-angle bracket: vertical line up from the
                // bottom-left, then a diagonal stroke going up-right
                // to the top-right corner.
                $stream->moveTo($left, $top);
                $stream->lineTo($left, $bottom);
                $stream->lineTo($right, $bottom);
                $stream->stroke();
                return;

            case 'circle':
                // Approximate the ellipse inscribed in (left, bottom)
                // -> (right, top) with four cubic Bezier curves. The
                // magic kappa = 4*(sqrt(2)-1)/3 ~ 0.5523 makes a near-
                // perfect circle approximation.
                $rx = ($right - $left) / 2.0;
                $ry = ($top - $bottom) / 2.0;
                $kappa = 0.5522847498307933;
                $kx = $kappa * $rx;
                $ky = $kappa * $ry;
                // Start at right midpoint.
                $stream->moveTo($midX + $rx, $midY);
                // Right -> top quarter.
                $stream->curveTo(
                    $midX + $rx,
                    $midY + $ky,
                    $midX + $kx,
                    $midY + $ry,
                    $midX,
                    $midY + $ry,
                );
                // Top -> left quarter.
                $stream->curveTo(
                    $midX - $kx,
                    $midY + $ry,
                    $midX - $rx,
                    $midY + $ky,
                    $midX - $rx,
                    $midY,
                );
                // Left -> bottom quarter.
                $stream->curveTo(
                    $midX - $rx,
                    $midY - $ky,
                    $midX - $kx,
                    $midY - $ry,
                    $midX,
                    $midY - $ry,
                );
                // Bottom -> right quarter.
                $stream->curveTo(
                    $midX + $kx,
                    $midY - $ry,
                    $midX + $rx,
                    $midY - $ky,
                    $midX + $rx,
                    $midY,
                );
                $stream->stroke();
                return;

            case 'updiagonalarrow':
                // Diagonal from bottom-left to top-right (like
                // updiagonalstrike) plus an arrowhead at top-right.
                $stream->moveTo($left, $bottom);
                $stream->lineTo($right, $top);
                // Arrowhead: two short lines at the tip pointing
                // back along the diagonal at +/- ~30 degrees.
                $arrowLen = ($right - $left) * 0.18;
                // Diagonal direction = (1, 1) / sqrt(2). Rotate by
                // 150 and 210 degrees from that for the arrowhead
                // legs. Direct cos/sin makes this readable.
                $dx = $right - $left;
                $dy = $top - $bottom;
                $len = sqrt($dx * $dx + $dy * $dy);
                $ux = $dx / $len;
                $uy = $dy / $len;
                // 150 deg from diagonal: rotate (-ux, -uy) by -30.
                // We can use cos -30 = sqrt(3)/2, sin -30 = -1/2.
                $cos = 0.8660254037844387; // sqrt(3)/2
                $sin = 0.5;
                // Left leg of arrowhead.
                $lx1 = -$ux * $cos + -$uy * (-$sin);
                $ly1 = -$ux * $sin + -$uy * $cos;
                // Right leg.
                $rx1 = -$ux * $cos + -$uy * $sin;
                $ry1 = $ux * $sin + -$uy * $cos;
                $stream->moveTo($right, $top);
                $stream->lineTo(
                    $right + $lx1 * $arrowLen,
                    $top + $ly1 * $arrowLen,
                );
                $stream->moveTo($right, $top);
                $stream->lineTo(
                    $right + $rx1 * $arrowLen,
                    $top + $ry1 * $arrowLen,
                );
                $stream->stroke();
                return;

            case 'downdiagonalarrow':
                // Diagonal from top-left to bottom-right with arrowhead.
                $stream->moveTo($left, $top);
                $stream->lineTo($right, $bottom);
                $arrowLen = ($right - $left) * 0.18;
                $dx = $right - $left;
                $dy = $bottom - $top;
                $len = sqrt($dx * $dx + $dy * $dy);
                $ux = $dx / $len;
                $uy = $dy / $len;
                $cos = 0.8660254037844387;
                $sin = 0.5;
                $lx1 = -$ux * $cos + -$uy * (-$sin);
                $ly1 = -$ux * $sin + -$uy * $cos;
                $rx1 = -$ux * $cos + -$uy * $sin;
                $ry1 = $ux * $sin + -$uy * $cos;
                $stream->moveTo($right, $bottom);
                $stream->lineTo(
                    $right + $lx1 * $arrowLen,
                    $bottom + $ly1 * $arrowLen,
                );
                $stream->moveTo($right, $bottom);
                $stream->lineTo(
                    $right + $rx1 * $arrowLen,
                    $bottom + $ry1 * $arrowLen,
                );
                $stream->stroke();
                return;

                // radical - emitting the √ glyph requires the math
                // font to be loaded AND U+221A to be in its cmap.
                // Decorating an arbitrary box with a radical sign
                // doesn't fit the path-only model `drawNotation`
                // operates in (we'd need to fall back into the
                // text block to emit a glyph). Skipping for v1;
                // the radical-around-content use case is better
                // served by the actual `<msqrt>` element which
                // already emits U+221A under a math font (PR #73).
        }
    }

    // -----------------------------------------------------------------
    // Tokens
    // -----------------------------------------------------------------

    private function paintMn(Mn $mn, MathmlPaintContext $ctx): void
    {
        $this->emitText($this->withMathvariant($mn, $mn->textContent()), $ctx);
    }

    private function paintMi(Mi $mi, MathmlPaintContext $ctx): void
    {
        $content = $mi->textContent();
        if ($content === '') {
            return;
        }
        // Core §3.2.3: single-character <mi> renders italic by default.
        // When a math font is active, the upright math font already
        // carries the italic-shaped letters in the Math Italic Unicode
        // block (or via mathvariant transformation). Don't swap to
        // standard Times-Italic - the math font's own glyphs are
        // correct, and swapping would point the stream at a different
        // font for emitText's hex emission.
        $useItalic = $this->isSingleVisibleChar($content)
            && $mi->mathvariant() === null
            && $ctx->mathFont === null;
        if ($useItalic) {
            $ctx->stream->setFont($ctx->italic, $ctx->fontSize);
        }
        // Apply mathvariant transform (no-op for auto-italic mi
        // because mathvariant is null in that branch). When set,
        // it maps ASCII letters/digits into Mathematical
        // Alphanumeric Symbols (U+1D400+).
        $emitContent = $this->withMathvariant($mi, $content);
        $this->emitText($emitContent, $ctx, $useItalic);
        if ($useItalic) {
            $ctx->stream->setFont($this->activeFont($ctx), $ctx->fontSize);
        }
    }

    private function paintMo(
        Mo $mo,
        MathmlPaintContext $ctx,
        ?string $form = null,
    ): void {
        $text = $mo->textContent();
        if ($text === '') {
            return;
        }

        $effectiveForm = $form ?? $mo->form() ?? 'infix';

        // Author-supplied lspace / rspace attributes win over the
        // dictionary's default. They are CSS lengths but the v1
        // painter only honours em / unitless; everything else falls
        // back to the dictionary value.
        $entry = OperatorDictionary::lookup($text, $effectiveForm);
        $lspaceEm = $this->resolveOperatorSpacing(
            $mo->attributes['lspace'] ?? null,
            $entry['lspace'],
        );
        $rspaceEm = $this->resolveOperatorSpacing(
            $mo->attributes['rspace'] ?? null,
            $entry['rspace'],
        );

        if ($lspaceEm > 0.0) {
            $shift = $lspaceEm * $ctx->fontSize;
            $ctx->stream->moveTextPosition($shift, 0.0);
            $ctx->cursorX += $shift;
        }

        // `largeop="true"` (typical for ∑, ∏, ∫ when authors want
        // display-style proportions) and stretchy fences both pick
        // taller pre-drawn variants from the font's MathVariants
        // when available. largeop takes precedence: an operator
        // marked both stretchy AND largeop renders at its
        // displayOperatorMinHeight rather than its row's content
        // height.
        // Resolve effective largeop: author attribute wins, otherwise
        // the dictionary's default. Only apply in display mode so
        // big ops don't grow when authors don't expect them inline.
        $effectiveLargeOp = $mo->largeop()
            ?? ($ctx->displayStyle && $entry['largeop']);
        $emitted = false;
        if ($effectiveLargeOp === true) {
            $emitted = $this->tryLargeOpEmit($text, $ctx);
        }
        if (!$emitted) {
            $emitted = $this->tryStretchyEmit($mo, $text, $entry, $ctx);
        }
        if (!$emitted) {
            $this->emitText($this->withMathvariant($mo, $text), $ctx);
        }

        if ($rspaceEm > 0.0) {
            $shift = $rspaceEm * $ctx->fontSize;
            $ctx->stream->moveTextPosition($shift, 0.0);
            $ctx->cursorX += $shift;
        }
    }

    /**
     * Try to emit a stretched variant glyph for `$mo` via MathVariants.
     * Returns true when a variant emission was performed (caller
     * must skip the standard emit); false otherwise.
     *
     * Conditions for stretching:
     *   - The math font is loaded.
     *   - The operator is marked stretchy: either via author's
     *     `stretchy="true"` or the dictionary entry's stretchy bit.
     *   - The font has a vertical-construction entry for the
     *     operator's base GID.
     *   - The chosen variant GID survived the subsetter (otherwise
     *     emitting it would draw a tofu).
     *
     * Required-height target: `$ctx->stretchTargetEm`, set by
     * {@see walkChildren()} to the max height of the surrounding
     * row's non-stretchy children. Conservative 1.0 em fallback
     * when paintMo is reached outside a row walk.
     *
     * @param array{lspace: float, rspace: float, stretchy: bool} $entry
     */
    private function tryStretchyEmit(
        Mo $mo,
        string $text,
        array $entry,
        MathmlPaintContext $ctx,
    ): bool {
        if ($ctx->mathFont === null || $ctx->mathFont->variants === null) {
            return false;
        }
        if (!$this->isStretchy($mo, $entry)) {
            return false;
        }
        $cp = mb_strlen($text, 'UTF-8') === 1 ? mb_ord($text, 'UTF-8') : false;
        if ($cp === false) {
            return false;
        }
        $baseGid = $ctx->mathFont->unicodeToGid[$cp] ?? null;
        if ($baseGid === null) {
            return false;
        }

        $requiredFunits = (int) round(
            $ctx->stretchTargetEm * $ctx->mathFont->unitsPerEm,
        );
        $variant = $ctx->mathFont->verticalVariantFor($baseGid, $requiredFunits);

        // Variant-fits path: the picker returned a pre-drawn variant
        // tall enough for the row, OR returned the largest variant
        // as a best-effort fallback. If the best-effort variant
        // doesn't reach the required height AND the font has an
        // assembly recipe, prefer assembly so very tall content
        // gets visually correct fences.
        if ($variant !== null && $variant['advance'] >= $requiredFunits) {
            return $this->emitStretchyVariant($variant, $ctx);
        }

        // Assembly path: stack glyph parts (top + middle(s) + bottom)
        // with overlap to fill the required height.
        $assembly = $ctx->mathFont->verticalAssemblyFor($baseGid);
        if ($assembly !== null && $assembly->parts !== []) {
            $emitted = $this->emitStretchyAssembly($assembly, $requiredFunits, $ctx);
            if ($emitted) {
                return true;
            }
            // Assembly emission can fall through (e.g. a part GID
            // wasn't subsetted); continue to the variant fallback.
        }

        // Fall back to the largest pre-drawn variant if assembly
        // didn't fire.
        if ($variant !== null) {
            return $this->emitStretchyVariant($variant, $ctx);
        }
        return false;
    }

    /**
     * Emit a single pre-drawn variant glyph at the cursor and
     * advance the cursor by its hmtx advance.
     *
     * @param array{glyphId: int, advance: int} $variant
     */
    /**
     * Try to emit a large-form variant of the operator glyph via
     * MathVariants. The target height is the font's
     * displayOperatorMinHeight (from MathConstants); we pick the
     * smallest vertical variant whose advance meets or exceeds it.
     *
     * Conditions to fire:
     *   - The math font is loaded with variants registered.
     *   - The operator is a single-character glyph (so we can look
     *     up a base GID; multi-char operators don't have variants).
     *   - The glyph has a vertical-construction entry in the font.
     *   - The chosen variant GID survived the subsetter.
     *
     * Returns false on any failure so the caller can fall through
     * to the standard emit.
     */
    private function tryLargeOpEmit(string $text, MathmlPaintContext $ctx): bool
    {
        if ($ctx->mathFont === null || $ctx->mathFont->variants === null) {
            return false;
        }
        $cp = mb_strlen($text, 'UTF-8') === 1 ? mb_ord($text, 'UTF-8') : false;
        if ($cp === false) {
            return false;
        }
        $baseGid = $ctx->mathFont->unicodeToGid[$cp] ?? null;
        if ($baseGid === null) {
            return false;
        }
        $targetFunits = (int) round(
            $ctx->metrics->displayOperatorMinHeightEm()
            * $ctx->mathFont->unitsPerEm,
        );
        $variant = $ctx->mathFont->verticalVariantFor($baseGid, $targetFunits);
        if ($variant === null) {
            return false;
        }
        return $this->emitStretchyVariant($variant, $ctx);
    }

    private function emitStretchyVariant(
        array $variant,
        MathmlPaintContext $ctx,
    ): bool {
        if ($ctx->mathFont === null) {
            return false;
        }
        $newGid = $ctx->mathFont->preSubsetToPostSubset($variant['glyphId']);
        if ($newGid === null) {
            return false;
        }
        $ctx->stream->showTextHex(sprintf('%04X', $newGid));
        $advancePt = $variant['advance']
            / (float) $ctx->mathFont->unitsPerEm
            * $ctx->fontSize;
        $ctx->cursorX += $advancePt;
        return true;
    }

    /**
     * Stack an assembly's glyph parts vertically to span the
     * required height. Each non-extender part appears once;
     * extenders are repeated together by a single repeat count
     * computed to make the total height ≥ required.
     *
     * Spec sketch (https://learn.microsoft.com/en-us/typography/opentype/spec/math#mathglyphassembly):
     *
     *   - Adjacent parts overlap by `minConnectorOverlap` (FUnit).
     *   - Total span = Σ(part advances) − Σ(joint overlaps).
     *   - Required ≤ span iff a sufficient extender repeat count
     *     was chosen.
     *
     * v1 implementation:
     *
     *   - All parts must survive the CFF subset; otherwise we
     *     return false so the caller falls back to the variant
     *     emit. PdfWriter::addOpenTypeFont's `extraGids` param
     *     (already populated by {@see MathmlRenderer}) covers the
     *     common case.
     *   - Vertical positioning uses Td moves between Tj emissions:
     *     emit a glyph, back up X by the glyph's hmtx width, drop
     *     Y by (part_advance − overlap), repeat. After the final
     *     part, restore the text cursor to (assembly_right_X,
     *     baselineY).
     *   - The assembly centres on the math axis: top sits at
     *     `axisHeightEm + totalHeight/2` above baseline.
     */
    private function emitStretchyAssembly(
        \Phpdftk\FontParser\MathGlyphAssembly $assembly,
        int $requiredFunits,
        MathmlPaintContext $ctx,
    ): bool {
        if ($ctx->mathFont === null) {
            return false;
        }
        $unitsPerEm = $ctx->mathFont->unitsPerEm;
        $variants = $ctx->mathFont->variants;
        $minOverlap = $variants?->minConnectorOverlap ?? 0;

        // Resolve all post-subset GIDs up front - if any part isn't
        // in the subset, assembly is impossible and the caller falls
        // back to the variant emit.
        $resolved = [];
        foreach ($assembly->parts as $part) {
            $newGid = $ctx->mathFont->preSubsetToPostSubset($part['glyphId']);
            if ($newGid === null) {
                return false;
            }
            $resolved[] = ['gid' => $newGid, 'part' => $part];
        }

        // Compute extender repeat count k.
        $fixedAdvSum = 0;
        $extAdvSum = 0;
        $nFixed = 0;
        $nExt = 0;
        foreach ($assembly->parts as $part) {
            if ($part['extender']) {
                $extAdvSum += $part['fullAdvance'];
                $nExt++;
            } else {
                $fixedAdvSum += $part['fullAdvance'];
                $nFixed++;
            }
        }
        $k = 1;
        if ($nExt > 0) {
            // Joints in a sequence of (nFixed + k*nExt) parts:
            //   total joints = nFixed + k*nExt - 1
            // Total height:
            //   fixedAdvSum + k*extAdvSum - (nFixed + k*nExt - 1) * minOverlap
            // Solve for total ≥ requiredFunits:
            //   k * (extAdvSum - nExt*minOverlap)
            //     ≥ requiredFunits - fixedAdvSum + (nFixed - 1) * minOverlap
            $denom = $extAdvSum - $nExt * $minOverlap;
            if ($denom > 0) {
                $needed = $requiredFunits - $fixedAdvSum
                    + ($nFixed - 1) * $minOverlap;
                $k = (int) max(1, (int) ceil($needed / $denom));
            }
        }

        // Build the linear sequence: each part once, except
        // extenders k times. Preserves spec part order.
        $sequence = [];
        foreach ($resolved as $entry) {
            $reps = $entry['part']['extender'] ? $k : 1;
            for ($i = 0; $i < $reps; $i++) {
                $sequence[] = $entry;
            }
        }
        $partsCount = count($sequence);
        if ($partsCount === 0) {
            return false;
        }

        // Convert FUnit -> em -> points helper.
        $toPt = fn(int $funits): float
            => $funits / (float) $unitsPerEm * $ctx->fontSize;

        // Total assembly height in points (parts - overlaps).
        $totalH = 0.0;
        foreach ($sequence as $i => $entry) {
            $totalH += $toPt($entry['part']['fullAdvance']);
            if ($i > 0) {
                $totalH -= $toPt($minOverlap);
            }
        }

        // Topmost part's text-origin Y above baseline. The assembly
        // centres on the math axis (typical for delimiters around
        // centred content).
        $axisHeightPt = $ctx->metrics->axisHeightEm() * $ctx->fontSize;
        $topY = $axisHeightPt + $totalH / 2.0
            // Subtract the topmost glyph's advance because Tj's
            // origin is at the glyph baseline; we want the top of
            // the topmost glyph to land at axisHeight + totalH/2.
            - $toPt($sequence[0]['part']['fullAdvance']);

        // Glyph width helper - drives the X back-up between Tj's.
        $glyphWidthPt = function (int $gid) use ($ctx, $unitsPerEm): float {
            $w = $ctx->mathFont?->glyphWidths[$gid] ?? 500;
            return $w / (float) $unitsPerEm * $ctx->fontSize;
        };

        // Move text cursor to the topmost part's origin.
        $ctx->stream->moveTextPosition(0.0, $topY);

        $cumulativeY = $topY;
        $assemblyGlyphWidth = 0.0;
        foreach ($sequence as $i => $entry) {
            $gid = $entry['gid'];
            $ctx->stream->showTextHex(sprintf('%04X', $gid));
            $gw = $glyphWidthPt($gid);
            // Track assembly's horizontal extent as max of part widths.
            if ($gw > $assemblyGlyphWidth) {
                $assemblyGlyphWidth = $gw;
            }
            if ($i < $partsCount - 1) {
                $advance = $toPt($entry['part']['fullAdvance']);
                $deltaY = -($advance - $toPt($minOverlap));
                // Back up X by the glyph we just advanced through;
                // drop Y to the next part's origin.
                $ctx->stream->moveTextPosition(-$gw, $deltaY);
                $cumulativeY += $deltaY;
            }
        }

        // After the last part, the text cursor sits at
        // (cursor_at_top + lastGlyphWidth, cumulativeY). Restore
        // back to (startX + assembly_width, baselineY) so the next
        // sibling flows correctly.
        $netY = -$cumulativeY;
        $netX = $assemblyGlyphWidth - $glyphWidthPt(
            $sequence[$partsCount - 1]['gid'],
        );
        $ctx->stream->moveTextPosition($netX, $netY);
        $ctx->cursorX += $assemblyGlyphWidth;
        return true;
    }

    /**
     * Whether `<mo>` should render stretchy. Author's `stretchy`
     * attribute wins over the dictionary entry per Core §3.2.5.
     *
     * @param array{lspace: float, rspace: float, stretchy: bool} $entry
     */
    private function isStretchy(Mo $mo, array $entry): bool
    {
        $explicit = $mo->stretchy();
        if ($explicit !== null) {
            return $explicit;
        }
        return $entry['stretchy'];
    }

    /**
     * Resolve `<mo>`'s `lspace` / `rspace` attribute. Author values
     * in em / unitless override the dictionary default; anything else
     * (px, pt, junk, absent) falls through to the dictionary.
     */
    private function resolveOperatorSpacing(
        ?string $raw,
        float $dictionaryDefault,
    ): float {
        if ($raw === null) {
            return $dictionaryDefault;
        }
        $trimmed = trim($raw);
        if ($trimmed === '') {
            return $dictionaryDefault;
        }
        if (!preg_match('/^(-?\d*\.?\d+)\s*([a-zA-Z%]*)$/', $trimmed, $m)) {
            return $dictionaryDefault;
        }
        $unit = strtolower($m[2]);
        if ($unit !== 'em' && $unit !== '') {
            return $dictionaryDefault;
        }
        $value = (float) $m[1];
        return $value >= 0.0 ? $value : $dictionaryDefault;
    }

    private function paintMs(Ms $ms, MathmlPaintContext $ctx): void
    {
        // <ms> wraps its content in lquote / rquote characters; the
        // typed accessors fall back to ASCII " when absent. Quotes
        // pass through the mathvariant transform unchanged.
        $content = $ms->lquote() . $ms->textContent() . $ms->rquote();
        $this->emitText($this->withMathvariant($ms, $content), $ctx);
    }

    private function paintMtext(Mtext $mtext, MathmlPaintContext $ctx): void
    {
        $this->emitText(
            $this->withMathvariant($mtext, $mtext->textContent()),
            $ctx,
        );
    }

    // -----------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------

    private function walkChildren(Element $parent, MathmlPaintContext $ctx): void
    {
        $elementChildren = $this->elementChildren($parent);
        $count = count($elementChildren);
        if ($count === 0) {
            return;
        }

        // Resolve effective direction. The parent's own `dir` (if any)
        // overrides what the surrounding context says. Mid-tree
        // overrides spawn a fresh context for the children only.
        $parentDir = $parent->dir();
        $childCtx = $ctx;
        if ($parentDir !== null && $parentDir !== $ctx->direction) {
            $childCtx = new MathmlPaintContext(
                stream: $ctx->stream,
                upright: $ctx->upright,
                italic: $ctx->italic,
                fontSize: $ctx->fontSize,
                cursorX: $ctx->cursorX,
                baselineY: $ctx->baselineY,
                direction: $parentDir,
            );
        }

        // Form detection uses SOURCE position - reversing visual order
        // for RTL doesn't change which Mo is prefix / infix / postfix.
        $formByIndex = [];
        foreach ($elementChildren as $i => $child) {
            if ($child instanceof Mo) {
                $formByIndex[$i] = $this->effectiveOperatorForm($child, $i, $count);
            }
        }

        // Compute the row's content height so stretchy operators
        // pick a variant tall enough to wrap their siblings. We
        // walk the NON-stretchy siblings (a stretchy `<mo>` would
        // measure as its own glyph height, defeating the goal of
        // scaling to surround them).
        $contentHeightEm = 1.0;
        foreach ($elementChildren as $i => $child) {
            if ($this->isStretchyChild($child, $childCtx, $formByIndex[$i] ?? null)) {
                continue;
            }
            $childHeightEm = $this->estimateHeightEm($child, $childCtx);
            if ($childHeightEm > $contentHeightEm) {
                $contentHeightEm = $childHeightEm;
            }
        }
        $childCtx->stretchTargetEm = $contentHeightEm;

        // RTL flips iteration order so the first source child sits at
        // the rightmost visual position. Cursor mechanics stay
        // unchanged - we just paint the children in the visually
        // correct order.
        $indices = range(0, $count - 1);
        if ($childCtx->direction === 'rtl') {
            $indices = array_reverse($indices);
        }

        foreach ($indices as $i) {
            $this->paint($elementChildren[$i], $childCtx, $formByIndex[$i] ?? null);
        }

        // Sync the parent's cursorX with the child context's final
        // position so subsequent siblings of the *parent* flow
        // correctly.
        if ($childCtx !== $ctx) {
            $ctx->cursorX = $childCtx->cursorX;
        }
    }

    /**
     * Whether `$child` would render as a stretchy operator under the
     * current context. Used by walkChildren to exclude stretchy
     * children from the row-height calculation - they need to
     * adapt to siblings, not influence the target.
     */
    private function isStretchyChild(
        Element $child,
        MathmlPaintContext $ctx,
        ?string $formHint,
    ): bool {
        if (!$child instanceof Mo) {
            return false;
        }
        $form = $formHint ?? $child->form() ?? 'infix';
        $entry = OperatorDictionary::lookup($child->textContent(), $form);
        return $this->isStretchy($child, $entry);
    }

    /**
     * Approximate vertical extent of an element, in em. Used to
     * size stretchy fences around tall content (fractions, radicals,
     * matrices, stacks of scripts).
     *
     * Each construct contributes its own additive vertical extent:
     *   - mfrac: numerator + denominator + a rule-thickness gap.
     *   - msqrt / mroot: content + vinculum gap above.
     *   - msub / msup: base + script shift on the active side.
     *   - msubsup: base + sup shift + sub shift.
     *   - munder / mover / munderover: base + over raise + under drop.
     *   - mtable: row count × (1 em + row gap).
     *   - menclose: content + 2 × pad.
     *   - mphantom: same as wrapped content (still reserves height).
     *   - mrow / GenericElement / Document: max of element children
     *     (mrow is a line container; its height = tallest child).
     *   - tokens (mn, mi, mo, ms, mtext): a single line (1 em).
     */
    private function estimateHeightEm(
        Element $element,
        MathmlPaintContext $ctx,
    ): float {
        $metrics = $ctx->metrics;
        $children = $this->elementChildren($element);

        if ($element instanceof Mfrac && count($children) === 2) {
            return $metrics->fractionNumeratorShiftUpEm()
                + $metrics->fractionDenominatorShiftDownEm()
                + $metrics->fractionRuleThicknessEm()
                + $this->estimateHeightEm($children[0], $ctx) - 1.0
                + $this->estimateHeightEm($children[1], $ctx) - 1.0
                + 1.0;
        }
        if ($element instanceof Msqrt) {
            return $metrics->overbarVerticalOffsetEm()
                + $this->maxChildHeightEm($element, $ctx) - 1.0
                + 0.1; // small overhang above the vinculum
        }
        if ($element instanceof Mroot && count($children) === 2) {
            return $metrics->overbarVerticalOffsetEm()
                + $this->estimateHeightEm($children[0], $ctx) - 1.0
                + 0.1;
        }
        if ($element instanceof Msup && count($children) === 2) {
            return $this->estimateHeightEm($children[0], $ctx)
                + $metrics->superscriptShiftUpEm();
        }
        if ($element instanceof Msub && count($children) === 2) {
            return $this->estimateHeightEm($children[0], $ctx)
                + $metrics->subscriptShiftDownEm();
        }
        if ($element instanceof Msubsup && count($children) === 3) {
            return $this->estimateHeightEm($children[0], $ctx)
                + $metrics->superscriptShiftUpEm()
                + $metrics->subscriptShiftDownEm();
        }
        if (($element instanceof Mover || $element instanceof Munder
            || $element instanceof Munderover) && $children !== []) {
            return $this->estimateHeightEm($children[0], $ctx)
                + $metrics->overscriptRaiseEm()
                + $metrics->underscriptDropEm();
        }
        if ($element instanceof Mtable) {
            $rowCount = 0;
            foreach ($children as $row) {
                if ($row instanceof Mtr) {
                    $rowCount++;
                }
            }
            if ($rowCount === 0) {
                return 1.0;
            }
            // Same rowPitch the painter uses (fontSize + row gap),
            // expressed in em.
            $rowPitchEm = 1.0 + 0.5; // matches MTABLE_ROW_GAP_EM
            return $rowCount * $rowPitchEm;
        }
        if ($element instanceof Menclose) {
            return $this->maxChildHeightEm($element, $ctx) + 0.2;
        }
        if ($element instanceof Mphantom) {
            return $this->maxChildHeightEm($element, $ctx);
        }
        if ($children !== [] && (
            $element instanceof Mrow
            || $element instanceof GenericElement
            || $element instanceof \Phpdftk\Mathml\MathmlDocument
        )) {
            return $this->maxChildHeightEm($element, $ctx);
        }
        // Tokens and anything else default to a single line.
        return 1.0;
    }

    /**
     * Max height in em of the element's direct element children.
     * 1.0 em floor so an empty container still reports a sensible
     * line height.
     */
    private function maxChildHeightEm(
        Element $element,
        MathmlPaintContext $ctx,
    ): float {
        $max = 1.0;
        foreach ($this->elementChildren($element) as $child) {
            $h = $this->estimateHeightEm($child, $ctx);
            if ($h > $max) {
                $max = $h;
            }
        }
        return $max;
    }

    /**
     * Compute the effective form for an `<mo>` based on its sibling
     * position when the element has no explicit `form` attribute.
     * The Core dictionary keys spacing by form, so this determines
     * which spacing rule fires.
     *
     * Heuristic from Core §3.2.5.7:
     *   - First child of a row → prefix.
     *   - Last child of a row → postfix.
     *   - Anywhere else (including single-child) → infix.
     */
    private function effectiveOperatorForm(Mo $mo, int $index, int $count): string
    {
        $explicit = $mo->form();
        if ($explicit !== null) {
            return $explicit;
        }
        if ($count <= 1) {
            return 'infix';
        }
        if ($index === 0) {
            return 'prefix';
        }
        if ($index === $count - 1) {
            return 'postfix';
        }
        return 'infix';
    }

    /**
     * Emit `$content` and advance the cursor by its real rendered
     * width. `$italic` chooses the metrics table when the painter
     * just emitted with the italic face (single-char `<mi>`); upright
     * elsewhere.
     */
    /**
     * Return the right "upright" font for the active context: the
     * math font when one is loaded, the standard upright Type 1
     * font otherwise. Used at every callsite that previously hard-
     * coded $ctx->upright; without this swap the script paint
     * methods would set the stream font back to Times-Roman, which
     * would interpret subsequent emit-text bytes against the wrong
     * glyph table and produce visible regressions under a math
     * font.
     */
    private function activeFont(MathmlPaintContext $ctx): \Phpdftk\Pdf\Writer\Font
    {
        return $ctx->mathFont?->font ?? $ctx->upright;
    }

    /**
     * Compute the font size a script-level child should render at,
     * given the parent context and the level increment.
     *
     * The formula reconstructs the base font size from the parent's
     * current scriptLevel + fontSize, then scales it by the child's
     * level. This keeps nested scripts spec-compliant: a script of
     * a script doesn't compound 0.7 × 0.7 = 0.49 (wrong); it picks
     * the scriptscript scale 0.55 directly.
     */
    private function scriptFontSizeFor(
        MathmlPaintContext $parent,
        int $levelDelta = 1,
    ): float {
        $childLevel = min(2, $parent->scriptLevel + $levelDelta);
        $baseFontSize = $parent->fontSize
            / $this->scaleForLevel($parent->scriptLevel, $parent);
        return $baseFontSize * $this->scaleForLevel($childLevel, $parent);
    }

    /**
     * Build the paint context for a script-level child (subscript,
     * superscript, over/under limit, mroot index, etc.) per Core
     * §3.1.6.
     *
     * Inherits every readonly field from the parent so math-font
     * features, metrics, direction, etc. flow correctly into nested
     * constructs. Overrides displayStyle to false, scriptLevel to
     * `min(2, parent + levelDelta)`, plus the caller-supplied
     * cursor / baseline / fontSize.
     *
     * `levelDelta` is 1 for normal scripts (sub/sup/over/under) and
     * 2 for mroot's index (Core says it's scriptscript-level).
     */
    private function childContextForScript(
        MathmlPaintContext $parent,
        float $fontSize,
        float $cursorX,
        float $baselineY,
        int $levelDelta = 1,
    ): MathmlPaintContext {
        return new MathmlPaintContext(
            stream: $parent->stream,
            upright: $parent->upright,
            italic: $parent->italic,
            fontSize: $fontSize,
            cursorX: $cursorX,
            baselineY: $baselineY,
            direction: $parent->direction,
            metrics: $parent->metrics,
            mathFont: $parent->mathFont,
            stretchTargetEm: $parent->stretchTargetEm,
            displayStyle: false,
            scriptLevel: min(2, $parent->scriptLevel + $levelDelta),
        );
    }

    /**
     * Build the paint context for `<mfrac>`'s numerator and
     * denominator per MathML Core §3.1.6:
     *
     *   - displayStyle becomes false unconditionally on the children.
     *   - scriptLevel increments by 1 when the surrounding displayStyle
     *     was false (typical inline mode); stays the same in display
     *     mode (the children render at the parent's full size).
     *   - fontSize follows scriptLevel via {@see scaleForLevel()} so
     *     each nested fraction step shrinks by the right factor.
     */
    private function childContextForFraction(
        MathmlPaintContext $ctx,
        bool $effectiveDisplayStyle,
    ): MathmlPaintContext {
        // Children always lose display style.
        $childDisplay = false;
        // Inline mode bumps scriptLevel; display mode keeps it.
        $childLevel = $effectiveDisplayStyle
            ? $ctx->scriptLevel
            : min(2, $ctx->scriptLevel + 1);
        $baseFontSize = $ctx->fontSize / $this->scaleForLevel($ctx->scriptLevel, $ctx);
        $childFontSize = $baseFontSize * $this->scaleForLevel($childLevel, $ctx);
        return new MathmlPaintContext(
            stream: $ctx->stream,
            upright: $ctx->upright,
            italic: $ctx->italic,
            fontSize: $childFontSize,
            cursorX: $ctx->cursorX,
            baselineY: $ctx->baselineY,
            direction: $ctx->direction,
            metrics: $ctx->metrics,
            mathFont: $ctx->mathFont,
            stretchTargetEm: $ctx->stretchTargetEm,
            displayStyle: $childDisplay,
            scriptLevel: $childLevel,
        );
    }

    /**
     * Scale factor from the base font size to the size at
     * `$scriptLevel`. 0 = base (1.0), 1 = script (scriptScale),
     * 2+ = scriptscript (scriptScriptScale).
     */
    private function scaleForLevel(int $level, MathmlPaintContext $ctx): float
    {
        if ($level <= 0) {
            return 1.0;
        }
        if ($level === 1) {
            return $ctx->metrics->scriptScale();
        }
        return $ctx->metrics->scriptScriptScale();
    }

    /**
     * Resolve the base glyph's top-accent attachment point (in em
     * from the base's left edge) for `<mover>` placement.
     *
     * Returns null when:
     *   - no math font is loaded,
     *   - the base isn't a single-character token,
     *   - the font has no attachment value for this glyph.
     *
     * The caller then falls back to centring the accent on the
     * base's geometric mid-line.
     */
    private function topAccentAttachmentEm(
        Element $base,
        MathmlPaintContext $ctx,
    ): ?float {
        if ($ctx->mathFont === null) {
            return null;
        }
        $text = $base->textContent();
        if (mb_strlen($text, 'UTF-8') !== 1) {
            return null;
        }
        $cp = mb_ord($text, 'UTF-8');
        if ($cp === false) {
            return null;
        }
        $gid = $ctx->mathFont->unicodeToGid[$cp] ?? null;
        if ($gid === null) {
            return null;
        }
        $attachFunits = $ctx->mathFont->topAccentAttachmentFor($gid);
        if ($attachFunits === null) {
            return null;
        }
        return $attachFunits / (float) $ctx->mathFont->unitsPerEm;
    }

    /**
     * Compute the corner-kern adjustment (in em) the painter should
     * apply when attaching a script to a base glyph.
     *
     * The corner kern is the per-glyph fine-tuning the font supplies
     * for the script-attachment positions. Top-right and top-left
     * kerns nudge superscripts; bottom-right and bottom-left nudge
     * subscripts. Each is a piecewise function of attachment height,
     * looked up via {@see MathKern::valueAt()}.
     */
    private function cornerKernEm(
        Element $base,
        MathmlPaintContext $ctx,
        string $corner,
        float $attachHeightEm,
    ): float {
        if ($ctx->mathFont === null) {
            return 0.0;
        }
        $info = $ctx->mathFont->glyphInfo;
        if ($info === null || $info->kernInfoBytes === '') {
            return 0.0;
        }
        $text = $base->textContent();
        if (mb_strlen($text, 'UTF-8') !== 1) {
            return 0.0;
        }
        $cp = mb_ord($text, 'UTF-8');
        if ($cp === false) {
            return 0.0;
        }
        $postSubsetGid = $ctx->mathFont->unicodeToGid[$cp] ?? null;
        if ($postSubsetGid === null) {
            return 0.0;
        }
        // MathKernInfo is keyed by pre-subset GID; we parsed it lazily
        // and cache the result on the math-font instance.
        $kernInfo = $this->mathKernInfoFor($ctx);
        if ($kernInfo === null) {
            return 0.0;
        }
        $oldGid = array_flip($ctx->mathFont->oldToNewGid)[$postSubsetGid] ?? null;
        if ($oldGid === null) {
            return 0.0;
        }
        $record = $kernInfo->records[$oldGid] ?? null;
        if ($record === null) {
            return 0.0;
        }
        $kern = match ($corner) {
            'topRight' => $record->topRight,
            'topLeft' => $record->topLeft,
            'bottomRight' => $record->bottomRight,
            'bottomLeft' => $record->bottomLeft,
            default => null,
        };
        if ($kern === null) {
            return 0.0;
        }
        $heightFunits = (int) round(
            $attachHeightEm * $ctx->mathFont->unitsPerEm,
        );
        return $kern->valueAt($heightFunits) / (float) $ctx->mathFont->unitsPerEm;
    }

    /**
     * Apply a corner kern shift to the cursor before painting a
     * script. Looks up `$corner` on the base glyph (per MathKernInfo)
     * and translates the FUnit kern at `$attachHeightEm` into a
     * point-space Td move. No-op when no kern is registered.
     */
    private function applyCornerKern(
        Element $base,
        MathmlPaintContext $ctx,
        string $corner,
        float $attachHeightEm,
    ): void {
        $kernEm = $this->cornerKernEm($base, $ctx, $corner, $attachHeightEm);
        if ($kernEm === 0.0) {
            return;
        }
        $shift = $kernEm * $ctx->fontSize;
        $ctx->stream->moveTextPosition($shift, 0.0);
        $ctx->cursorX += $shift;
    }

    /** @var array<string, ?\Phpdftk\FontParser\MathKernInfo> Lazy MathKernInfo cache keyed by font path hash. */
    private array $mathKernInfoCache = [];

    private function mathKernInfoFor(MathmlPaintContext $ctx): ?\Phpdftk\FontParser\MathKernInfo
    {
        if ($ctx->mathFont === null || $ctx->mathFont->glyphInfo === null) {
            return null;
        }
        $key = spl_object_hash($ctx->mathFont);
        if (!array_key_exists($key, $this->mathKernInfoCache)) {
            $bytes = $ctx->mathFont->glyphInfo->kernInfoBytes;
            $this->mathKernInfoCache[$key] = $bytes !== ''
                ? (new \Phpdftk\FontParser\MathKernInfoParser())->parse($bytes)
                : null;
        }
        return $this->mathKernInfoCache[$key];
    }

    /**
     * Shift the cursor by the base glyph's italic correction (per
     * MathGlyphInfo) when applicable. No-op when:
     *   - no math font is loaded,
     *   - the base isn't a single-char `<mi>` (italic only triggers
     *     for the single-character italic rule),
     *   - the base has an explicit `mathvariant`,
     *   - the base's codepoint isn't in the font's cmap.
     */
    private function applyItalicCorrection(Element $base, MathmlPaintContext $ctx): void
    {
        if ($ctx->mathFont === null) {
            return;
        }
        if (!$base instanceof Mi) {
            return;
        }
        $content = $base->textContent();
        if (mb_strlen($content, 'UTF-8') !== 1) {
            return;
        }
        if ($base->mathvariant() !== null) {
            return;
        }
        $cp = mb_ord($content, 'UTF-8');
        if ($cp === false) {
            return;
        }
        $gid = $ctx->mathFont->unicodeToGid[$cp] ?? null;
        if ($gid === null) {
            return;
        }
        $correctionFunits = $ctx->mathFont->italicCorrectionFor($gid);
        if ($correctionFunits === 0) {
            return;
        }
        $shiftPt = $correctionFunits / (float) $ctx->mathFont->unitsPerEm
            * $ctx->fontSize;
        $ctx->stream->moveTextPosition($shiftPt, 0.0);
        $ctx->cursorX += $shiftPt;
    }

    /**
     * Apply the element's `mathvariant` attribute (if any) to
     * `$content`, mapping ASCII letters and digits into the
     * Mathematical Alphanumeric Symbols block per Core §3.2.3.
     * No-op when the element has no `mathvariant`, the value is
     * `normal`, or the value isn't one of the supported variants.
     */
    private function withMathvariant(Element $el, string $content): string
    {
        $variant = $el->mathvariant();
        if ($variant === null || $content === '') {
            return $content;
        }
        return MathvariantTransform::apply($content, $variant);
    }

    private function emitText(string $content, MathmlPaintContext $ctx, bool $italic = false): void
    {
        if ($content === '') {
            return;
        }
        // Arabic shaping: replace logical-order Arabic letters with
        // their contextual Presentation Forms-B codepoints (initial /
        // medial / final / isolated) before the bidi pass. Shaping
        // runs first because the bidi pass needs to act on the final
        // codepoint sequence that will hit the cmap. Non-Arabic
        // content passes through unchanged.
        if (preg_match('/[\x{0600}-\x{06FF}]/u', $content) === 1) {
            $content = ArabicShaper::shape($content);
        }
        // Bidi: pure-RTL content gets reversed; mixed-direction
        // content is run-aware reordered via BidiReorder using the
        // surrounding paragraph direction. Pure-LTR / neutral
        // content passes through unchanged.
        $runDirection = BidiAnalyzer::runDirection($content);
        if ($runDirection === BidiAnalyzer::DIRECTION_RTL) {
            $content = $this->reverseUtf8($content);
        } elseif ($runDirection === 'mixed') {
            $paragraphDir = $ctx->direction === 'rtl'
                ? BidiAnalyzer::DIRECTION_RTL
                : BidiAnalyzer::DIRECTION_LTR;
            $content = BidiReorder::reorder($content, $paragraphDir);
        }
        if ($ctx->mathFont !== null) {
            $hex = $ctx->mathFont->utf8ToHexGids($content);
            if ($hex !== '') {
                $ctx->stream->showTextHex($hex);
            }
            $ctx->cursorX += $ctx->mathFont->measure($content, $ctx->fontSize);
            return;
        }
        $ctx->stream->showText($content);
        $ctx->cursorX += MathmlGlyphMetrics::measure($content, $ctx->fontSize, $italic);
    }

    /**
     * Reverse a UTF-8 string by codepoint. PHP's strrev() works on
     * bytes and would corrupt multi-byte glyphs; mb_str_split with
     * length=1 splits into codepoints (each a UTF-8 byte sequence)
     * that can be reversed and rejoined safely.
     */
    private function reverseUtf8(string $utf8): string
    {
        return implode('', array_reverse(mb_str_split($utf8, 1, 'UTF-8')));
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
        $stream->setFont($this->activeFont($ctx), $ctx->fontSize);
        // Tm — absolute text matrix. Identity rotation/scale, translate
        // to (cursorX, baselineY). Subsequent Tj advances the pen
        // from there.
        $stream->setTextMatrix(1.0, 0.0, 0.0, 1.0, $ctx->cursorX, $ctx->baselineY);
    }

    /**
     * Width estimate based on flattened text content measured
     * against the currently-active font.
     *
     * The optional `$ctx` lets callers route through the math font's
     * real per-GID hmtx widths so construct widths (fractions, table
     * columns, scripts) line up with the ink. When no ctx is passed,
     * or when the ctx has no math font, falls back to Times-Roman
     * AFM widths.
     */
    private function estimateWidth(
        Element $element,
        float $fontSize,
        ?MathmlPaintContext $ctx = null,
    ): float {
        $text = $element->textContent();
        if ($ctx?->mathFont !== null) {
            return $ctx->mathFont->measure($text, $fontSize);
        }
        return MathmlGlyphMetrics::measure($text, $fontSize, italic: false);
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

    // -----------------------------------------------------------------
    // maction / merror / semantics - passthrough containers
    // -----------------------------------------------------------------

    /**
     * Paint `<maction>` - render only the child indicated by the
     * `selection` attribute (1-based, default 1). MathML Core
     * §3.6.1 drops the interactive semantics; the element is now
     * a passthrough that picks one of its children.
     *
     * Selection values that are out of range fall back to the
     * first child (per Core's "if it doesn't refer to a valid
     * child, the first child is selected" rule). An empty
     * maction renders nothing.
     */
    private function paintMaction(Maction $maction, MathmlPaintContext $ctx): void
    {
        $children = $this->elementChildren($maction);
        $count = count($children);
        if ($count === 0) {
            return;
        }
        $index = $maction->selection() - 1;
        if ($index < 0 || $index >= $count) {
            $index = 0;
        }
        $this->paint($children[$index], $ctx);
    }

    /**
     * Paint `<merror>` - render children inline. The Core default
     * styling (red text / salmon background) would require
     * stroke/fill colour management plus a content-bounds pass;
     * that lands in a follow-up alongside `mathcolor` /
     * `mathbackground`. For now `<merror>` is functionally a
     * passthrough so its contents at least render instead of
     * triggering the unknown-element default.
     */
    private function paintMerror(Merror $merror, MathmlPaintContext $ctx): void
    {
        $this->walkChildren($merror, $ctx);
    }

    /**
     * Paint `<semantics>` - render only the first element child
     * (the presentation form). Any `<annotation>` /
     * `<annotation-xml>` siblings carry alternate encodings
     * (Content MathML, TeX source, etc.) for consumers; they are
     * not visual content per Core §5.1.
     */
    private function paintSemantics(Semantics $semantics, MathmlPaintContext $ctx): void
    {
        $children = $this->elementChildren($semantics);
        if ($children === []) {
            return;
        }
        $this->paint($children[0], $ctx);
    }
}
