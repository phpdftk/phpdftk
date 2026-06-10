<?php

declare(strict_types=1);

namespace Phpdftk\MathmlToPdf;

use Phpdftk\Mathml\Element;
use Phpdftk\Mathml\GenericElement;
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
use Phpdftk\Mathml\Mtable;
use Phpdftk\Mathml\Mtd;
use Phpdftk\Mathml\Mtext;
use Phpdftk\Mathml\Mtr;
use Phpdftk\Mathml\Munder;
use Phpdftk\Mathml\Munderover;
use Phpdftk\Mathml\NoneElement;
use Phpdftk\Mathml\OperatorDictionary;
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

        $raise = $ctx->fontSize * $ctx->metrics->fractionNumeratorShiftUpEm();
        $drop = $ctx->fontSize * $ctx->metrics->fractionDenominatorShiftDownEm();
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
        $vinculumY = $ctx->baselineY + $ctx->fontSize * $ctx->metrics->overbarVerticalOffsetEm();
        $this->drawHorizontalRule(
            $ctx,
            $radLeftX,
            $vinculumY,
            $contentWidth,
            $ctx->metrics->overbarRuleThicknessEm() * $ctx->fontSize,
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

        $vinculumY = $ctx->baselineY + $ctx->fontSize * $ctx->metrics->overbarVerticalOffsetEm();
        $this->drawHorizontalRule(
            $ctx,
            $baseLeftX,
            $vinculumY,
            $baseWidth,
            $ctx->metrics->overbarRuleThicknessEm() * $ctx->fontSize,
        );
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
        $this->paintScript($sub, $ctx, -$ctx->fontSize * $ctx->metrics->subscriptShiftDownEm());
    }

    /**
     * Paint `<msup>` as base followed by a smaller superscript at
     * upper-right. Two element children expected.
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
        $this->paintScript($sup, $ctx, $ctx->fontSize * $ctx->metrics->superscriptShiftUpEm());
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
        $attachX = $ctx->cursorX;
        $scriptFontSize = $ctx->fontSize * $ctx->metrics->scriptScale();
        $subWidth = $this->estimateWidth($sub, $scriptFontSize);
        $supWidth = $this->estimateWidth($sup, $scriptFontSize);

        // Sup first at raised baseline.
        $this->paintScript($sup, $ctx, $ctx->fontSize * $ctx->metrics->superscriptShiftUpEm());
        // After paintScript, cursor advanced by supWidth. Back up
        // horizontally to the attach point, then drop to the
        // subscript baseline.
        $backup = $attachX - $ctx->cursorX;
        $ctx->stream->setFont($ctx->upright, $scriptFontSize);
        $ctx->stream->moveTextPosition($backup, -$ctx->fontSize * $ctx->metrics->subscriptShiftDownEm());
        $subCtx = new MathmlPaintContext(
            stream: $ctx->stream,
            upright: $ctx->upright,
            italic: $ctx->italic,
            fontSize: $scriptFontSize,
            cursorX: $attachX,
            baselineY: $ctx->baselineY - $ctx->fontSize * $ctx->metrics->subscriptShiftDownEm(),
        );
        $this->paint($sub, $subCtx);
        // Restore to the construct's right edge on the main baseline.
        $rightEdge = $attachX + max($subWidth, $supWidth);
        $ctx->stream->moveTextPosition(
            $rightEdge - $subCtx->cursorX,
            $ctx->fontSize * $ctx->metrics->subscriptShiftDownEm(),
        );
        $ctx->stream->setFont($ctx->upright, $ctx->fontSize);
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
        $scriptFontSize = $ctx->fontSize * $ctx->metrics->scriptScale();
        $ctx->stream->setFont($ctx->upright, $scriptFontSize);
        $ctx->stream->moveTextPosition(0.0, $yOffset);
        $scriptCtx = new MathmlPaintContext(
            stream: $ctx->stream,
            upright: $ctx->upright,
            italic: $ctx->italic,
            fontSize: $scriptFontSize,
            cursorX: $ctx->cursorX,
            baselineY: $ctx->baselineY + $yOffset,
        );
        $this->paint($script, $scriptCtx);
        $ctx->cursorX = $scriptCtx->cursorX;
        $ctx->stream->moveTextPosition(0.0, -$yOffset);
        $ctx->stream->setFont($ctx->upright, $ctx->fontSize);
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

        $scriptFontSize = $ctx->fontSize * $ctx->metrics->scriptScale();
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
        $scriptFontSize = $ctx->fontSize * $ctx->metrics->scriptScale();
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
            $ctx->stream->setFont($ctx->upright, $scriptFontSize);
            $ctx->stream->moveTextPosition($backup, -$ctx->fontSize * $ctx->metrics->subscriptShiftDownEm());
            $subCtx = new MathmlPaintContext(
                stream: $ctx->stream,
                upright: $ctx->upright,
                italic: $ctx->italic,
                fontSize: $scriptFontSize,
                cursorX: $attachX,
                baselineY: $ctx->baselineY - $ctx->fontSize * $ctx->metrics->subscriptShiftDownEm(),
            );
            $this->paint($sub, $subCtx);
            // End at the pair's right edge on the original baseline.
            $ctx->stream->moveTextPosition(
                $attachX + $pairWidth - $subCtx->cursorX,
                $ctx->fontSize * $ctx->metrics->subscriptShiftDownEm(),
            );
            $ctx->stream->setFont($ctx->upright, $ctx->fontSize);
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
    private const float MTABLE_COL_GAP_EM = 0.8;

    /**
     * Vertical padding between adjacent rows, in em. Core default
     * `rowspacing` is `1.0ex` (≈ 0.5em); we approximate uniformly.
     */
    private const float MTABLE_ROW_GAP_EM = 0.5;

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
        $rows = [];
        foreach ($this->elementChildren($mtable) as $rowEl) {
            if ($rowEl instanceof Mtr) {
                $rows[] = $this->elementChildren($rowEl);
            }
        }
        if ($rows === []) {
            // No <mtr>s at all — degrade to inline walk so any stray
            // content under <mtable> still reaches the stream.
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

        $colGap = $ctx->fontSize * self::MTABLE_COL_GAP_EM;
        $rowGap = $ctx->fontSize * self::MTABLE_ROW_GAP_EM;
        $rowHeight = $ctx->fontSize;

        $tableWidth = 0.0;
        foreach ($colWidths as $w) {
            $tableWidth += $w;
        }
        $tableWidth += $colGap * max(0, $colCount - 1);

        $tableLeftX = $ctx->cursorX;
        $rowCount = count($rows);

        // Vertical layout: centre the table on the math axis. With N
        // rows of height H plus (N-1) row-gaps G, the table occupies
        // N*H + (N-1)*G vertically. Top row's baseline sits at
        // baselineY + ((N-1)/2) * (H + G); bottom row at the inverse.
        $rowPitch = $rowHeight + $rowGap;
        $topRowOffset = ($rowCount - 1) * $rowPitch / 2.0;

        foreach ($rows as $rowIdx => $row) {
            $rowBaselineOffset = $topRowOffset - $rowIdx * $rowPitch;
            // Column x cursor starts at tableLeftX for every row.
            $colX = $tableLeftX;
            foreach ($row as $col => $cell) {
                $cellWidth = $this->estimateWidth($cell, $ctx->fontSize);
                $cellLeadX = $colX + ($colWidths[$col] - $cellWidth) / 2.0;

                $deltaX = $cellLeadX - $ctx->cursorX;
                // We always end the prior cell back on the parent
                // baseline (see the symmetric moveTextPosition below),
                // so the per-cell Y delta is just rowBaselineOffset.
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
                // Return to the parent baseline; we'll re-shift when we
                // move into the next cell.
                $ctx->stream->moveTextPosition(0.0, -$rowBaselineOffset);
                $ctx->cursorX = $cellCtx->cursorX;

                $colX += $colWidths[$col] + $colGap;
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
        $this->paintUnderOver(
            base: $base,
            under: $under,
            over: $over,
            ctx: $ctx,
        );
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
        $scriptFontSize = $ctx->fontSize * $ctx->metrics->scriptScale();
        $baseWidth = $this->estimateWidth($base, $ctx->fontSize);
        $overWidth = $over !== null ? $this->estimateWidth($over, $scriptFontSize) : 0.0;
        $underWidth = $under !== null ? $this->estimateWidth($under, $scriptFontSize) : 0.0;
        $constructWidth = max($baseWidth, $overWidth, $underWidth);

        $this->paint($base, $ctx);
        // Cursor now at baseLeftX + baseWidth.

        if ($over !== null && $overWidth > 0.0) {
            $this->placeCentredScript(
                script: $over,
                ctx: $ctx,
                baseLeftX: $baseLeftX,
                baseWidth: $baseWidth,
                constructWidth: $constructWidth,
                yOffset: $ctx->fontSize * $ctx->metrics->overscriptRaiseEm(),
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
    ): void {
        $scriptFontSize = $ctx->fontSize * $ctx->metrics->scriptScale();
        $scriptWidth = $this->estimateWidth($script, $scriptFontSize);
        $scriptStartX = $baseLeftX + ($baseWidth - $scriptWidth) / 2.0;
        $deltaX = $scriptStartX - $ctx->cursorX;

        $ctx->stream->setFont($ctx->upright, $scriptFontSize);
        $ctx->stream->moveTextPosition($deltaX, $yOffset);
        $scriptCtx = new MathmlPaintContext(
            stream: $ctx->stream,
            upright: $ctx->upright,
            italic: $ctx->italic,
            fontSize: $scriptFontSize,
            cursorX: $scriptStartX,
            baselineY: $ctx->baselineY + $yOffset,
        );
        $this->paint($script, $scriptCtx);

        // Restore: end at the construct's right edge on the original
        // baseline so subsequent siblings flow correctly.
        $constructRightX = $baseLeftX + $constructWidth;
        $ctx->stream->moveTextPosition(
            $constructRightX - $scriptCtx->cursorX,
            -$yOffset,
        );
        $ctx->stream->setFont($ctx->upright, $ctx->fontSize);
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

        $this->walkChildren($mpadded, $ctx);

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
        $stream->setFont($ctx->upright, $ctx->fontSize);
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

                // circle, radical, madruwb, phasorangle, downdiagonalarrow,
                // updiagonalarrow - no-op for v1. Content still renders.
        }
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
        $this->emitText($content, $ctx, $useItalic);
        if ($useItalic) {
            $ctx->stream->setFont($ctx->upright, $ctx->fontSize);
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

        $this->emitText($text, $ctx);

        if ($rspaceEm > 0.0) {
            $shift = $rspaceEm * $ctx->fontSize;
            $ctx->stream->moveTextPosition($shift, 0.0);
            $ctx->cursorX += $shift;
        }
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
    private function emitText(string $content, MathmlPaintContext $ctx, bool $italic = false): void
    {
        if ($content === '') {
            return;
        }
        if ($ctx->mathFont !== null) {
            // Math font path: translate UTF-8 to post-subset GIDs and
            // emit via the Type 0 / Identity-H hex stream. Width
            // measurement uses the font's hmtx for cursor accuracy
            // against the actual rendered ink.
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
}
