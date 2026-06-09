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
use Phpdftk\Mathml\Mtable;
use Phpdftk\Mathml\Mtd;
use Phpdftk\Mathml\Mtext;
use Phpdftk\Mathml\Mtr;
use Phpdftk\Mathml\Munder;
use Phpdftk\Mathml\Munderover;
use Phpdftk\Mathml\NoneElement;

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
            $element instanceof Msub    => $this->paintMsub($element, $ctx),
            $element instanceof Msup    => $this->paintMsup($element, $ctx),
            $element instanceof Msubsup => $this->paintMsubsup($element, $ctx),
            $element instanceof Munder  => $this->paintMunder($element, $ctx),
            $element instanceof Mover   => $this->paintMover($element, $ctx),
            $element instanceof Munderover => $this->paintMunderover($element, $ctx),
            $element instanceof Mmultiscripts => $this->paintMmultiscripts($element, $ctx),
            $element instanceof Mtable  => $this->paintMtable($element, $ctx),
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
    // Scripts (msub / msup / msubsup) — attached at base right edge
    // -----------------------------------------------------------------

    /** Approximate scaled font size for subscript / superscript children. */
    private const float SCRIPT_FONT_SCALE = 0.7;

    /** Baseline raise for superscripts, in em. */
    private const float SUP_RAISE_EM = 0.5;

    /** Baseline drop for subscripts, in em (positive value, used negated). */
    private const float SUB_DROP_EM = 0.3;

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
        $this->paintScript($sub, $ctx, -$ctx->fontSize * self::SUB_DROP_EM);
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
        $this->paintScript($sup, $ctx, $ctx->fontSize * self::SUP_RAISE_EM);
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
        $scriptFontSize = $ctx->fontSize * self::SCRIPT_FONT_SCALE;
        $subWidth = $this->estimateWidth($sub, $scriptFontSize);
        $supWidth = $this->estimateWidth($sup, $scriptFontSize);

        // Sup first at raised baseline.
        $this->paintScript($sup, $ctx, $ctx->fontSize * self::SUP_RAISE_EM);
        // After paintScript, cursor advanced by supWidth. Back up
        // horizontally to the attach point, then drop to the
        // subscript baseline.
        $backup = $attachX - $ctx->cursorX;
        $ctx->stream->setFont($ctx->upright, $scriptFontSize);
        $ctx->stream->moveTextPosition($backup, -$ctx->fontSize * self::SUB_DROP_EM);
        $subCtx = new MathmlPaintContext(
            stream: $ctx->stream,
            upright: $ctx->upright,
            italic: $ctx->italic,
            fontSize: $scriptFontSize,
            cursorX: $attachX,
            baselineY: $ctx->baselineY - $ctx->fontSize * self::SUB_DROP_EM,
        );
        $this->paint($sub, $subCtx);
        // Restore to the construct's right edge on the main baseline.
        $rightEdge = $attachX + max($subWidth, $supWidth);
        $ctx->stream->moveTextPosition(
            $rightEdge - $subCtx->cursorX,
            $ctx->fontSize * self::SUB_DROP_EM,
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
        $scriptFontSize = $ctx->fontSize * self::SCRIPT_FONT_SCALE;
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

        $scriptFontSize = $ctx->fontSize * self::SCRIPT_FONT_SCALE;
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
        $scriptFontSize = $ctx->fontSize * self::SCRIPT_FONT_SCALE;
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
            $this->paintScript($sup, $ctx, $ctx->fontSize * self::SUP_RAISE_EM);
            // Cursor now at attachX + supWidth on original baseline.
        }

        if ($hasSub && $subWidth > 0.0) {
            // Back up to attachX, drop to sub baseline.
            $backup = $attachX - $ctx->cursorX;
            $ctx->stream->setFont($ctx->upright, $scriptFontSize);
            $ctx->stream->moveTextPosition($backup, -$ctx->fontSize * self::SUB_DROP_EM);
            $subCtx = new MathmlPaintContext(
                stream: $ctx->stream,
                upright: $ctx->upright,
                italic: $ctx->italic,
                fontSize: $scriptFontSize,
                cursorX: $attachX,
                baselineY: $ctx->baselineY - $ctx->fontSize * self::SUB_DROP_EM,
            );
            $this->paint($sub, $subCtx);
            // End at the pair's right edge on the original baseline.
            $ctx->stream->moveTextPosition(
                $attachX + $pairWidth - $subCtx->cursorX,
                $ctx->fontSize * self::SUB_DROP_EM,
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
    // -----------------------------------------------------------------

    /** Vertical raise for overscripts, in em. */
    private const float OVER_RAISE_EM = 0.85;

    /** Vertical drop for underscripts, in em (positive value, negated when used). */
    private const float UNDER_DROP_EM = 0.5;

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
        $scriptFontSize = $ctx->fontSize * self::SCRIPT_FONT_SCALE;
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
                yOffset: $ctx->fontSize * self::OVER_RAISE_EM,
            );
        }

        if ($under !== null && $underWidth > 0.0) {
            $this->placeCentredScript(
                script: $under,
                ctx: $ctx,
                baseLeftX: $baseLeftX,
                baseWidth: $baseWidth,
                constructWidth: $constructWidth,
                yOffset: -$ctx->fontSize * self::UNDER_DROP_EM,
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
        $scriptFontSize = $ctx->fontSize * self::SCRIPT_FONT_SCALE;
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
