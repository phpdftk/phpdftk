<?php

declare(strict_types=1);

namespace Phpdftk\HtmlToPdf\Layout;

use Phpdftk\Css\Cascade\WritingMode;
use Phpdftk\Css\Value\Length;
use Phpdftk\HtmlToPdf\Box\AtomicInlineBox;
use Phpdftk\HtmlToPdf\Box\Box;
use Phpdftk\HtmlToPdf\Box\InlineBox;
use Phpdftk\HtmlToPdf\Box\LineBreakBox;
use Phpdftk\HtmlToPdf\Box\TextBox;
use Phpdftk\Text\LineBreaker;
use Phpdftk\Text\LineBreakKind;
use Phpdftk\Text\Shaper;
use Phpdftk\Text\ShapedGlyph;
use Phpdftk\Text\ShapedRun;
use Phpdftk\Text\ShapingContext;

/**
 * Inline formatting context layout — Phase 1F.2 (text shaping + greedy
 * line wrapping).
 *
 * Walks a parent block's inline children (InlineBox / AtomicInlineBox /
 * TextBox subtrees), shapes their text via `phpdftk/text`'s Shaper, finds
 * line-break opportunities via UAX #14, and greedily fits the resulting
 * fragments into line boxes that respect the parent's content width.
 *
 * Phase-1 simplifications:
 *  - Single font per inline run (the layout context's `defaultFont`).
 *    Font runs / fallback live alongside paragraph shaping in Phase 2.
 *  - Bidi reorder is the bidi engine's job; this layout reads logical
 *    order and lays runs left-to-right.
 *  - Atomic inline boxes (replaced elements, inline-block) are treated as
 *    fixed-size boxes; sizing comes from the box style (width / height).
 *  - Line height defaults to `1.2 × font-size` per CSS Inline §3.
 *
 * When no font is available in the layout context, this layout falls back
 * to producing zero-height (no-op) lines so block layout can still
 * complete end-to-end on the test surface.
 */
final class InlineLayout
{
    /**
     * Captured from the LayoutContext at the start of each `layout()`
     * call so `walkInline` can re-resolve fonts for nested `<code>` etc.
     * without threading the resolver through every recursive parameter.
     */
    private ?FontResolver $currentFontResolver = null;

    /**
     * Captured at the top of each `layout()` call so atomic-inline
     * width-percentage resolution (CSS Sizing 3 §6.2: % on an inline
     * box resolves against its containing block) can see the basis
     * without threading the value through `collectTokens` /
     * `walkInline`.
     */
    private float $currentAvailableWidth = 0.0;

    /**
     * CSS Writing Modes 4 §3 — the measure lines WRAP against, when that
     * differs from the physical width. Null in a horizontal writing mode
     * (and in a vertical one whose block size is indefinite), which keeps
     * every horizontal path byte-identical.
     */
    private ?float $currentWrapMeasure = null;

    /**
     * CSS Sizing 4 §6.4 — true when the container's BLOCK size (its
     * physical width, in a vertical writing mode) is `auto` and will be
     * set from this pass's column total. The `vrl` / `sideways-rl`
     * transpose then anchors the columns against that total rather than
     * against the pre-shrink physical width it was handed.
     */
    private bool $currentBlockSizeAuto = false;

    /**
     * True while laying out an inline formatting context whose block
     * container is in a vertical writing mode (CSS Writing Modes 4 §3).
     * The pass runs in a horizontal coordinate space and
     * {@see applyVerticalLineShift} transposes it at the end, so this is
     * what tells the atomic-inline sizing which physical dimension is the
     * INLINE one: an `<img>` keeps its physical `width`/`height`, so in a
     * vertical mode its inline extent is its HEIGHT and its contribution
     * to the line's cross-size is its WIDTH — the opposite of horizontal-tb.
     */
    private bool $currentIsVertical = false;

    /**
     * The containing block's content height and whether it is definite,
     * threaded from the block layout so an atomic replaced element can
     * resolve a percentage `height` / `max-height` / `min-height` and,
     * via its intrinsic ratio, derive an auto inline size (CSS 2.1
     * §10.3.2). `0` / `false` when the CB height is indefinite.
     */
    private float $currentCbHeight = 0.0;

    private bool $currentCbHeightDefinite = false;

    public function __construct(
        private readonly Shaper $shaper = new Shaper(),
        private readonly LineBreaker $lineBreaker = new LineBreaker(),
    ) {}

    /**
     * Lay out the inline children of `$parent` within `$availableWidth`,
     * starting at Y = 0 in the parent's coordinate space. Returns the
     * generated line boxes (positions relative to the parent's content
     * area) and the total height consumed.
     *
     * @return array{list<LineBox>, float} (lines, totalHeight)
     */
    public function layout(
        Box $parent,
        float $availableWidth,
        LayoutContext $context,
        ?float $inlineExtent = null,
        bool $blockSizeAuto = false,
    ): array {
        $this->currentFontResolver = $context->fontResolver;
        $this->currentAvailableWidth = $availableWidth;
        $this->currentCbHeight = $context->containingBlockHeight;
        $this->currentCbHeightDefinite = $context->inFlowHeightDefinite;
        $this->currentWrapMeasure = $this->verticalInlineMeasure($parent, $inlineExtent);
        $this->currentBlockSizeAuto = $blockSizeAuto;
        $this->currentIsVertical = WritingMode::fromStyle($parent->style)->isVertical();
        if ($availableWidth <= 0.0) {
            return [[], 0.0];
        }
        // Resolve the shaping font + post-match synthetic-effect flags via
        // CSS Fonts 4 §6 weight/style matching. When a real face matches
        // the cascaded weight/style, `isBold`/`isItalic` come back false so
        // the painter doesn't double up with fake-bold / fake-italic.
        $parentMatch = $this->resolveBoxFont($parent, $context->defaultFont);
        $font = $parentMatch['font'];
        $fontSize = $this->dominantFontSize($parent, $context);
        if ($font === null) {
            // No font means text shaping is impossible — but inline
            // replaced content (img, svg, math, inline-block divs)
            // doesn't need a font for layout. Fall back to a minimal
            // atomic-only pass that pulls width / height off the
            // cascade so paintImage and the foreign-content painters
            // (paintInlineSvg, paintInlineMath) see real geometry
            // instead of the (0, 0, 0, 0) box the full early return
            // used to produce. Closes #39 for the atomic-content
            // case; text-bearing documents still need an explicit
            // default font.
            return $this->layoutAtomicOnly($parent);
        }
        $shapingCtx = new ShapingContext($font, $fontSize, features: $this->resolveOpenTypeFeatures($parent));
        $lineHeight = $this->resolveLineHeight($parent, $fontSize);
        // CSS2 §10.8 strut: the block container's own font establishes a
        // zero-width inline box that every line box contains, so an empty
        // line or one of only-smaller text still reserves the block's line
        // height. `lineMetrics` folds this in alongside the real fragments.
        $strutAscent = ($font->ascent / max(1, $font->unitsPerEm)) * $fontSize;
        $strutDescent = (abs($font->descent) / max(1, $font->unitsPerEm)) * $fontSize;
        // x-height drives `vertical-align: middle` (box centre aligns with the
        // baseline minus half the parent's x-height). Fall back to 0.5em when
        // the font carries no x-height metric.
        $strutXHeight = $font->xHeight > 0
            ? ($font->xHeight / max(1, $font->unitsPerEm)) * $fontSize
            : $fontSize * 0.5;
        $whiteSpace = $this->whiteSpaceKeyword($parent);
        // CSS Text 3 §4 — wrap permission table:
        //   normal / pre-wrap / pre-line / break-spaces → allow soft wrap
        //   nowrap / pre → no soft wrap
        $allowSoftWrap = $whiteSpace !== 'nowrap' && $whiteSpace !== 'pre';
        // CSS Text 3 §4 — leading-whitespace collapse table:
        //   normal / nowrap / pre-line → collapse leading whitespace
        //   pre / pre-wrap / break-spaces → preserve leading whitespace
        // (`break-spaces` is `pre-wrap` plus the additional rule that
        // every preserved space is a wrap opportunity. Leading-edge
        // semantics match `pre-wrap` — both keep the leading run.)
        $collapseLeadingWhitespace = $whiteSpace !== 'pre'
            && $whiteSpace !== 'pre-wrap'
            && $whiteSpace !== 'break-spaces';
        $collapseInternalWhitespace = $whiteSpace === 'normal' || $whiteSpace === 'nowrap';

        $letterSpacing = $this->resolveLetterSpacing($parent);
        $wordSpacing = $this->resolveWordSpacing($parent);
        // CSS Text 3 §5.5 — when `pre-wrap` / `break-spaces` is in
        // effect, trailing whitespace at the end of a line hangs. For
        // that to work, the tokeniser must emit a separate token for
        // each whitespace run; otherwise UAX-14's `XX<ws>` bundle
        // hides the trailing ws from the line fitter.
        $hangsTrailingWhitespacePreCompute = $whiteSpace === 'pre-wrap' || $whiteSpace === 'break-spaces';
        $tokens = $this->collectTokens(
            $parent,
            $shapingCtx,
            $collapseInternalWhitespace,
            $letterSpacing,
            $wordSpacing,
            baselineShift: 0.0,
            lineHeight: $lineHeight,
            verticalAlign: 'baseline',
            href: null,
            isBold: $parentMatch['isBold'],
            isItalic: $parentMatch['isItalic'],
            decorationLines: $this->decorationLines($parent),
            textColor: $this->resolveColor($parent),
            backgroundColor: null,
            linkTitle: null,
            decorationColor: $this->resolveDecorationColor($parent),
            splitWsBoundaries: $hangsTrailingWhitespacePreCompute,
        );
        if ($tokens === []) {
            return [[], 0.0];
        }

        // CSS Text 3 §3.1: `text-indent` shifts the first inline box of the
        // first formatted line. Length resolves directly; Percentage resolves
        // against the block's content width (our `$availableWidth`).
        $textIndent = $this->resolveTextIndent($parent, $availableWidth);

        // CSS 2.1 §9.5.3 — line boxes shorten on the side(s) where a
        // float is currently active. Compute the per-line (left, right)
        // bounds against the float context each time we start a new line.
        // The strut is only a LOWER bound on the line's block extent; each
        // token that joins the line may grow `$lineBand` (see below).
        $lineBand = $lineHeight;
        $bounds = $this->lineBounds($parent, $availableWidth, $context, 0.0, $lineBand);
        $lines = [];
        $currentFragments = [];
        // CSS Text 3 §5.5 — when in `pre-wrap` / `break-spaces`, trailing
        // whitespace at the end of a line hangs (renders past the line edge
        // with zero contribution to line measurement). We mirror that by
        // tracking which fragments in `$currentFragments` were whitespace
        // so we can drop them from the line when wrapping.
        $hangsTrailingWhitespace = $whiteSpace === 'pre-wrap' || $whiteSpace === 'break-spaces';
        /** @var list<bool> $currentFragmentIsWs */
        $currentFragmentIsWs = [];
        // Advance of the collapsible whitespace the line currently ends with.
        // CSS Text 3 §4.1.3 removes it, so it must come off the line's WIDTH
        // as well as off the fit test — otherwise a line packed to its full
        // measure reports as over-wide and `text-align` sees negative slack.
        $currentTrailingSpace = 0.0;
        $currentX = $bounds['left'] + $textIndent;
        $lineMaxRight = $bounds['right'];
        $atLineStart = true;
        $y = 0.0;
        foreach ($tokens as $token) {
            $width = $token['shapedRun']->totalAdvance;
            // CSS Text 3 §4.1.3 — a sequence of COLLAPSIBLE spaces at the end
            // of a line is removed, so it must not decide whether the line
            // fits. A break opportunity sits after the space (UAX #14), which
            // makes "word + its trailing space" a single token; measuring the
            // space here broke every line one space early, and a line crafted
            // to fill its measure exactly lost its last word.
            //
            // `pre-wrap` / `break-spaces` keep their spaces (they hang
            // instead), and that path already drops the fragments after the
            // fact, so it is left alone.
            $fitWidth = $hangsTrailingWhitespace
                ? $width
                : $width - $token['trailingSpace'];
            $isMandatory = $token['kind'] === LineBreakKind::Mandatory;

            if ($collapseLeadingWhitespace && $token['isWhitespace'] && $atLineStart) {
                // Leading whitespace at a line start is collapsed.
                continue;
            }
            // CSS Shapes 1 §1.2 — an atomic inline's own block extent sets
            // the band the float contour is resolved over, so it has to be
            // known BEFORE this line's bounds are (re-)queried below. Only
            // the height resolution moves up here; committing the box's
            // geometry still waits until the inline cursor is final.
            $atomic = $token['atomicBox'] ?? null;
            $atomicContentHeight = 0.0;
            $atomicOuterHeight = 0.0;
            $atomicPadTop = 0.0;
            $atomicPadBottom = 0.0;
            $atomicBorderTop = 0.0;
            $atomicBorderBottom = 0.0;
            if ($atomic !== null) {
                // The token captured the box-sizing-resolved content +
                // outer widths; resolve the heights with the same
                // semantics here. Falling back to width when height is
                // unset keeps the square-replaced-element default
                // (img with intrinsic ratio) the existing tests rely on.
                // The token resolved the box-sizing-aware block size at
                // creation time (the inline advance depends on it in a
                // vertical writing mode, so it cannot wait until here).
                [
                    $atomicContentHeight,
                    $atomicOuterHeight,
                    $atomicPadTop,
                    $atomicPadBottom,
                    $atomicBorderTop,
                    $atomicBorderBottom,
                ] = $token['atomicHeights'] ?? $this->resolveAtomicHeights(
                    $atomic,
                    (bool) ($token['atomicBorderBox'] ?? false),
                    $width,
                );
            }
            // A line may not break inside an inline box's own padding /
            // border, so its spacer never opens a break opportunity: wrapping
            // before it would strand the inset on the next line, away from
            // the text it belongs to.
            // A ZERO-WIDTH token always fits, so it must never trigger a
            // wrap. Without this an already-overflowing line followed by a
            // `<br>` breaks BEFORE the break itself: the `<br>` lands alone
            // on a line of its own and everything after is pushed a whole
            // line down. `border-padding-bleed-001` overflows its 596px
            // measure with 640px of Ahem text, so its `<br>` produced a third
            // line box where two are correct.
            if ($allowSoftWrap
                && $fitWidth > 0.0
                && ($token['noWrapBefore'] ?? false) === false
                && $currentX + $fitWidth > $lineMaxRight
                && $currentFragments !== []
            ) {
                // Wrap before placing this token.
                // For `pre-wrap` / `break-spaces`: trailing whitespace at the
                // end of the current line "hangs" — drop those fragments so
                // they don't push the line width and don't get re-emitted on
                // the next line. The overflowing whitespace token that
                // triggered this wrap also hangs (we drop it below).
                if ($hangsTrailingWhitespace) {
                    while ($currentFragmentIsWs !== [] && end($currentFragmentIsWs) === true) {
                        array_pop($currentFragments);
                        array_pop($currentFragmentIsWs);
                    }
                    // Re-derive currentX from the surviving fragments so
                    // line-width-based math (e.g. alignment) sees the
                    // post-hang width.
                    $currentX = $currentFragments === []
                        ? $bounds['left'] + $textIndent
                        : end($currentFragments)->x + end($currentFragments)->width;
                }
                $currentFragments = $this->trimTrailingSpace($currentFragments, $currentTrailingSpace);
                $currentTrailingSpace = 0.0;
                [$effective, $lineBase, $currentFragments] = $this->finalizeLine($currentFragments, $strutAscent, $strutDescent, $lineHeight, $strutXHeight);
                $this->commitAtomicFragmentY($parent, $y, $lineBase, $currentFragments);
                $lines[] = new LineBox($y, $effective, $currentFragments, $lineBase, $lineMaxRight);
                $y += $effective;
                $currentFragments = [];
                $currentFragmentIsWs = [];
                $lineBand = $lineHeight;
                $bounds = $this->lineBounds($parent, $availableWidth, $context, $y, $lineBand);
                $currentX = $bounds['left'];
                $lineMaxRight = $bounds['right'];
                $atLineStart = true;
                if ($collapseLeadingWhitespace && $token['isWhitespace']) {
                    // Drop whitespace at start of next line.
                    continue;
                }
                if ($hangsTrailingWhitespace && $token['isWhitespace']) {
                    // The overflowing whitespace hangs on the prior line —
                    // don't carry it to the new line.
                    continue;
                }
            }
            // CSS Shapes 1 §1.2 — the float area is resolved per LINE BOX,
            // over that line's own block extent. The strut is only a lower
            // bound on it: a `line-height: 0` block whose lines are built
            // from 36px-tall inline-blocks has a 36px band, and a curved or
            // sloped contour intrudes far further across that band than it
            // does at the line's top edge. So grow the band as each token
            // joins the line and re-query the contour. Fragments already on
            // the line shift by the difference, which is what re-running the
            // line against the tighter bounds would have produced; the
            // dominant case — an atomic that is the line's first token —
            // has nothing to shift at all.
            $tokenBand = $atomic !== null
                ? $atomicOuterHeight
                    + (float) ($token['atomicMarginTop'] ?? 0.0)
                    + (float) ($token['atomicMarginBottom'] ?? 0.0)
                : max(0.0, (float) ($token['lineHeight'] ?? -1.0));
            if ($tokenBand > $lineBand + 0.001) {
                $lineBand = $tokenBand;
                $rebanded = $this->lineBounds($parent, $availableWidth, $context, $y, $lineBand);
                $dx = $rebanded['left'] - $bounds['left'];
                if ($dx !== 0.0 && $currentFragments !== []) {
                    $currentFragments = $this->shiftFragments($currentFragments, $dx);
                    $this->commitAtomicFragmentX($parent, $currentFragments);
                }
                $currentX += $dx;
                $bounds = $rebanded;
                $lineMaxRight = $rebanded['right'];
            }
            $currentFragments[] = new InlineFragment(
                $currentX,
                $width,
                $token['shapedRun'],
                $token['baselineShift'] ?? 0.0,
                $token['href'] ?? null,
                $token['isBold'] ?? false,
                $token['isItalic'] ?? false,
                $token['decorationLines'] ?? [],
                $token['textColor'] ?? null,
                $token['backgroundColor'] ?? null,
                $token['linkTitle'] ?? null,
                $token['decorationColor'] ?? null,
                (bool) $token['isWhitespace'],
                $token['lineHeight'] ?? -1.0,
                $token['verticalAlign'] ?? 'baseline',
                atomicBox: $token['atomicBox'] ?? null,
                bgExtendAbove: $token['bgExtendAbove'] ?? 0.0,
                bgExtendBelow: $token['bgExtendBelow'] ?? 0.0,
            );
            $currentFragmentIsWs[] = (bool) $token['isWhitespace'];
            $currentTrailingSpace = $hangsTrailingWhitespace ? 0.0 : $token['trailingSpace'];
            // Side-channel: AtomicInlineBox positions get committed back to
            // the box's geometry so the painter can draw images / replaced
            // content at the right spot. CSS Inline 3 §4.5: for the default
            // `vertical-align: baseline`, the inline-block's baseline aligns
            // with the parent line's baseline; for replaced elements like
            // `<img>` the baseline is the bottom of the box. So position
            // the box so its *bottom* sits at the line's baseline (line.y +
            // ascent of the shaping font) — same convention the painter
            // uses for text baselines.
            if ($atomic !== null) {
                $shapedRun = $token['shapedRun'];
                $atomicFont = $shapedRun->font;
                $atomicUpem = max(1, $atomicFont->unitsPerEm);
                $atomicAscent = ($atomicFont->ascent / $atomicUpem) * $shapedRun->fontSizePt;
                // Outer box top-left is `(currentX, y + ascent - outerHeight)`;
                // the content box sits inside the padding + border edges.
                $atomicContentWidth = $token['atomicContentWidth'] ?? $width;
                $atomicPadLeft = $token['atomicPadLeft'] ?? 0.0;
                $atomicBorderLeft = $token['atomicBorderLeft'] ?? 0.0;
                $atomicPadRight = $token['atomicPadRight'] ?? 0.0;
                $atomicBorderRight = $token['atomicBorderRight'] ?? 0.0;
                $atomicMarginLeft = $token['atomicMarginLeft'] ?? 0.0;
                $atomicMarginRight = $token['atomicMarginRight'] ?? 0.0;
                $atomicMarginTop = $token['atomicMarginTop'] ?? 0.0;
                $atomicMarginBottom = $token['atomicMarginBottom'] ?? 0.0;
                // `$currentX` sits at the item's margin-box start; step past
                // the left margin (plus border + padding) to the content box.
                $atomic->geometry->x = $parent->geometry->x + $currentX
                    + $atomicMarginLeft + $atomicBorderLeft + $atomicPadLeft;
                // CSS 2.2 §10.8 — the inline-block's baseline (no in-flow line
                // boxes here) is its bottom margin edge, so the margin box
                // bottom aligns with the line baseline; the border box bottom
                // sits a bottom-margin above it.
                $atomic->geometry->y = $parent->geometry->y + $y
                    + $atomicAscent - $atomicOuterHeight - $atomicMarginBottom
                    + $atomicBorderTop + $atomicPadTop;
                $atomic->geometry->width = $atomicContentWidth;
                $atomic->geometry->height = $atomicContentHeight;
                $atomic->geometry->paddingLeft = $atomicPadLeft;
                $atomic->geometry->paddingRight = $atomicPadRight;
                $atomic->geometry->paddingTop = $atomicPadTop;
                $atomic->geometry->paddingBottom = $atomicPadBottom;
                $atomic->geometry->borderLeft = $atomicBorderLeft;
                $atomic->geometry->borderRight = $atomicBorderRight;
                $atomic->geometry->borderTop = $atomicBorderTop;
                $atomic->geometry->borderBottom = $atomicBorderBottom;
                $atomic->geometry->marginLeft = $atomicMarginLeft;
                $atomic->geometry->marginRight = $atomicMarginRight;
                $atomic->geometry->marginTop = $atomicMarginTop;
                $atomic->geometry->marginBottom = $atomicMarginBottom;
            }
            $currentX += $width;
            $atLineStart = false;
            if ($isMandatory) {
                $currentFragments = $this->trimTrailingSpace($currentFragments, $currentTrailingSpace);
                $currentTrailingSpace = 0.0;
                [$effective, $lineBase, $currentFragments] = $this->finalizeLine($currentFragments, $strutAscent, $strutDescent, $lineHeight, $strutXHeight);
                $this->commitAtomicFragmentY($parent, $y, $lineBase, $currentFragments);
                $lines[] = new LineBox($y, $effective, $currentFragments, $lineBase, $lineMaxRight);
                $y += $effective;
                $currentFragments = [];
                $currentFragmentIsWs = [];
                $lineBand = $lineHeight;
                $bounds = $this->lineBounds($parent, $availableWidth, $context, $y, $lineBand);
                $currentX = $bounds['left'];
                $lineMaxRight = $bounds['right'];
                $atLineStart = true;
            }
        }
        if ($currentFragments !== []) {
            $currentFragments = $this->trimTrailingSpace($currentFragments, $currentTrailingSpace);
            $currentTrailingSpace = 0.0;
            [$effective, $lineBase, $currentFragments] = $this->finalizeLine($currentFragments, $strutAscent, $strutDescent, $lineHeight, $strutXHeight);
            $this->commitAtomicFragmentY($parent, $y, $lineBase, $currentFragments);
            $lines[] = new LineBox($y, $effective, $currentFragments, $lineBase, $lineMaxRight);
            $y += $effective;
        }

        // CSS Overflow 4 line-clamp: keep only the first N line boxes, mark
        // the last retained one with a block ellipsis, and shrink the block's
        // height to those lines. Runs before text-overflow so a clamped line
        // that also overflows horizontally isn't given two ellipses, and
        // before text-align so alignment sees the retained rect.
        $clamp = $this->lineClampCount($parent);
        if ($clamp !== null && count($lines) > $clamp) {
            [$lines, $y] = $this->applyLineClamp($lines, $clamp, $availableWidth, $shapingCtx, $letterSpacing);
        }

        // CSS UI 3 §6.2: `text-overflow: ellipsis` truncates each line's
        // tail when its content exceeds the available width. Runs before
        // text-align so the alignment math operates on the truncated rect.
        $lines = $this->applyTextOverflow($lines, $availableWidth, $parent, $shapingCtx, $letterSpacing);

        $lines = $this->applyTextAlign($lines, $availableWidth, $parent);
        // text-align shifts each line's fragments; re-commit any atomic /
        // replaced box's inline-axis geometry so an <img> / inline-block on a
        // centred / right-aligned / justified line paints at the shifted
        // position rather than the line's pre-alignment start. Horizontal
        // writing modes only — in a vertical mode the inline axis is Y and the
        // atomic's geometry is (re)placed by the transpose in
        // applyVerticalLineShift below, so an X commit here would fight it.
        if (!WritingMode::fromStyle($parent->style)->isVertical()) {
            foreach ($lines as $line) {
                $this->commitAtomicFragmentX($parent, $line->fragments);
            }
        }
        // CSS Writing Modes 4 §3 — for a vertical-mode block container
        // hosting an inline formatting context, lines should sit at the
        // block-start edge of the container's content area:
        //  - `vrl` / `sideways-rl`: block-start = right edge → shift the
        //    line's fragments rightward by (availableWidth - lineWidth)
        //    so the visible content lands at the right.
        //  - `vlr` / `sideways-lr`: block-start = left edge → no shift.
        //
        // Phase-4 scaffold: text glyphs still lay out horizontally
        // (per the current Shaper-driven flow) — full vertical text
        // shaping with rotated glyphs comes in a later commit. The
        // immediate win is that single-line atomic inline content
        // (`<img>`, inline-block divs) lands at the visually-correct
        // block-start edge in `vrl` containers.
        $lines = $this->applyVerticalLineShift($lines, $availableWidth, $parent);
        return [$lines, $y];
    }

    /**
     * @param list<LineBox> $lines
     * @return list<LineBox>
     */
    private function applyVerticalLineShift(array $lines, float $availableWidth, Box $parent): array
    {
        $wm = WritingMode::fromStyle($parent->style);
        if (!$wm->isVertical()) {
            return $lines;
        }
        // CSS WM 4 §3 — transpose the horizontal IFC to the vertical block flow.
        // Each line becomes a vertical column: the line's INLINE offset (the
        // fragment's x — where `text-indent` / `text-align` placed it) moves to
        // the vertical axis (`line.y`, which the painter reads as the column's
        // vertical start), and columns stack along the BLOCK axis (physical x),
        // each occupying the line's cross-size (`height`). `vlr` / `sideways-lr`
        // grow rightward from the left content edge; `vrl` / `sideways-rl` grow
        // leftward from the right edge (block-start = right). The painter's
        // vertical branch (paintFragment) rotates glyphs 90° CW, reads
        // `fragment.x` as the column's left edge and `line.y` as its top.
        //
        // Increment 2: EVERY line (single- or multi-fragment) transposes
        // into one vertical column. All fragments of a source line share the
        // column's block-axis position (`fragment.x = $blockLeft`); each
        // keeps its inline advance as a `blockOffset` DOWN the column so the
        // painter stacks them. `LineBox.y` becomes 0 (the column top; the
        // per-fragment offset now carries the inline position). The former
        // single-fragment special case is the degenerate blockOffset=x case
        // of this loop.
        $rtl = $wm->blockDirection() === -1;
        // CSS Sizing 4 §6.4 — the `vrl` / `sideways-rl` columns are
        // anchored to the container's block-START edge, i.e. the RIGHT
        // edge of its content box. With an `auto` block size that edge is
        // not `$availableWidth` (the pre-shrink stretch value handed to
        // this pass) but the column total the caller is about to adopt as
        // the used width — anchor against that instead, or the columns
        // land off the shrunk box.
        $anchor = $availableWidth;
        if ($this->currentBlockSizeAuto) {
            $anchor = 0.0;
            foreach ($lines as $line) {
                if ($line->height > 0.0) {
                    $anchor += $line->height;
                }
            }
        }
        $out = [];
        $cumBlock = 0.0;
        foreach ($lines as $line) {
            // Transpose needs a real cross-size to place the column along the
            // block axis; a degenerate line box (`line-height: 0`) has none,
            // so pass it through rather than seed a zero-width column.
            if ($line->height <= 0.0) {
                $out[] = $line;
                continue;
            }
            // `vlr` / `sideways-lr`: columns grow rightward from the left
            // content edge. `vrl` / `sideways-rl`: block-start = right, so
            // grow leftward (place the column `$cumBlock` in from the right).
            $blockLeft = $rtl
                ? max(0.0, $anchor - $cumBlock - $line->height)
                : $cumBlock;
            $newFrags = [];
            foreach ($line->fragments as $f) {
                $newFrags[] = new InlineFragment(
                    $blockLeft,
                    $f->width,
                    $f->shapedRun,
                    $f->baselineShift,
                    $f->href,
                    $f->isBold,
                    $f->isItalic,
                    $f->decorationLines,
                    $f->textColor,
                    $f->backgroundColor,
                    $f->linkTitle,
                    $f->decorationColor,
                    $f->isWhitespace,
                    $f->lineHeight,
                    $f->verticalAlign,
                    blockOffset: $f->x,
                    // The transposed fragment has to keep its atomic /
                    // replaced box: the painter reads it to place inline
                    // images, and commitVerticalAtomicGeometry below needs
                    // it to re-seat their geometry in physical coordinates.
                    atomicBox: $f->atomicBox,
                    bgExtendAbove: $f->bgExtendAbove,
                    bgExtendBelow: $f->bgExtendBelow,
                );
            }
            $this->commitVerticalAtomicGeometry($parent, $newFrags, $blockLeft, $line->height, $rtl);
            $out[] = new LineBox(0.0, $line->height, $newFrags, $line->baseline, $line->availableRight);
            $cumBlock += $line->height;
        }
        return $out;
    }

    /**
     * Place each atomic / replaced box of a TRANSPOSED line in physical
     * coordinates (CSS Writing Modes 4 §3 / §7.1).
     *
     * `commitAtomicFragmentX` / `commitAtomicFragmentY` placed the box in
     * the horizontal coordinate space the inline pass works in; after the
     * transpose a fragment's inline position lives in `blockOffset`
     * (physical +Y down the column) and the column's block-axis position
     * in `$blockLeft` (physical X). A replaced box keeps its physical
     * `width`/`height`, so only its ORIGIN moves.
     *
     * The box sits at the column's block-START edge, which is the column's
     * left edge for `vertical-lr` / `sideways-lr` and its right edge for
     * `vertical-rl` / `sideways-rl`.
     *
     * @param list<InlineFragment> $fragments
     */
    private function commitVerticalAtomicGeometry(
        Box $parent,
        array $fragments,
        float $blockLeft,
        float $columnExtent,
        bool $blockRtl,
    ): void {
        foreach ($fragments as $fragment) {
            $atomic = $fragment->atomicBox;
            if ($atomic === null) {
                continue;
            }
            $g = $atomic->geometry;
            $outerWidth = $g->marginLeft + $g->borderLeft + $g->paddingLeft + $g->width
                + $g->paddingRight + $g->borderRight + $g->marginRight;
            $blockStartInset = $blockRtl ? max(0.0, $columnExtent - $outerWidth) : 0.0;
            $g->x = $parent->geometry->x + $blockLeft + $blockStartInset
                + $g->marginLeft + $g->borderLeft + $g->paddingLeft;
            $g->y = $parent->geometry->y + $fragment->blockOffset
                + $g->marginTop + $g->borderTop + $g->paddingTop;
        }
    }

    /**
     * Fallback layout for blocks whose inline-formatting context has
     * no shaping font available. Closes the geometry gap from #39 for
     * documents that contain only inline replaced content (img, svg,
     * math, inline-block divs) and never registered a default font.
     *
     * Walks the parent's direct children, reads cascaded width /
     * height off each AtomicInlineBox, sets its geometry, and
     * advances a cursor. No line wrapping — atoms that overflow the
     * available width stack anyway (the painter clips per-page).
     * Non-atomic children (TextBox, InlineBox, LineBreakBox) are
     * skipped because they need a font to lay out.
     *
     * Returns no LineBox: the painter doesn't iterate lines to find
     * an AtomicInlineBox, it walks the box tree top-down, so setting
     * geometry directly is sufficient for paintImage's namespace
     * dispatch (paintInlineSvg / paintInlineMath) to render.
     *
     * @return array{list<LineBox>, float}
     */
    private function layoutAtomicOnly(Box $parent): array
    {
        $currentX = 0.0;
        $maxHeight = 0.0;
        // Line-box tracking: atomics flow left-to-right and wrap to a new
        // line when the next one won't fit in the IFC available width
        // (CSS 2.1 §9.4.2). `$lineTop` is the current line's top offset
        // from the parent's content top; `$lineHeight` its tallest box.
        $lineTop = 0.0;
        $lineHeight = 0.0;
        foreach ($parent->children as $child) {
            if (!($child instanceof AtomicInlineBox)) {
                continue;
            }
            // Border + padding insets, resolved BEFORE the inline size so a
            // definite height can derive an auto width via the intrinsic
            // ratio (CSS 2.1 §10.3.2). Folding both axes' insets in keeps a
            // uniformly-bordered inline-block's border box intact (the CSS2
            // border-width tests).
            $padTop = self::atomicLength($child->style->get('padding-top'));
            $padBottom = self::atomicLength($child->style->get('padding-bottom'));
            $padLeft = self::atomicLength($child->style->get('padding-left'));
            $padRight = self::atomicLength($child->style->get('padding-right'));
            $borderTop = self::atomicBorderWidth($child->style, 'top');
            $borderBottom = self::atomicBorderWidth($child->style, 'bottom');
            $borderLeft = self::atomicBorderWidth($child->style, 'left');
            $borderRight = self::atomicBorderWidth($child->style, 'right');
            $verticalInset = $padTop + $padBottom + $borderTop + $borderBottom;
            $horizontalInset = $padLeft + $padRight + $borderLeft + $borderRight;
            // CSS 2.2 §10.8 — an inline-block's margins take part in layout:
            // horizontal margins add to its inline advance, its margin box
            // contributes to line height.
            $marginTop = self::atomicLength($child->style->get('margin-top'));
            $marginBottom = self::atomicLength($child->style->get('margin-bottom'));
            $marginLeft = self::atomicLength($child->style->get('margin-left'));
            $marginRight = self::atomicLength($child->style->get('margin-right'));
            // CSS Sizing 3 §6.2 — with `box-sizing: border-box` the declared
            // width/height already includes the inset.
            $borderBox = self::atomicIsBorderBoxSizing($child->style);
            // Resolve a definite CONTENT height first: an explicit length /
            // `0`, or a percentage against a definite containing-block
            // height. A definite height lets an auto inline size come from
            // the intrinsic ratio below.
            $heightValue = $child->style->get('height');
            $declaredHeight = match (true) {
                $heightValue instanceof Length => $heightValue->value,
                $heightValue instanceof \Phpdftk\Css\Value\Integer => (float) $heightValue->value,
                $heightValue instanceof \Phpdftk\Css\Value\Percentage
                    && $this->currentCbHeightDefinite && $this->currentCbHeight > 0.0
                    => $this->currentCbHeight * ($heightValue->value / 100.0),
                default => null,
            };
            $definiteContentHeight = null;
            if ($declaredHeight !== null) {
                $declaredHeight = max(0.0, $declaredHeight);
                $definiteContentHeight = $borderBox
                    ? max(0.0, $declaredHeight - $verticalInset)
                    : $declaredHeight;
            }
            // Inline (content-box) width: an explicit length, a percentage
            // against the IFC available width (the basis the shaped path
            // uses), else — when auto with a definite height and intrinsic
            // ratio — height × ratio (§10.3.2 replaced sizing).
            $ratio = self::atomicAspectRatio($child->style);
            $widthValue = $child->style->get('width');
            $width = match (true) {
                $widthValue instanceof Length && $widthValue->value > 0.0
                    => $widthValue->value,
                $widthValue instanceof \Phpdftk\Css\Value\Percentage && $widthValue->value > 0.0
                    => $this->currentAvailableWidth * ($widthValue->value / 100.0),
                default => 0.0,
            };
            $contentWidth = $borderBox ? max(0.0, $width - $horizontalInset) : $width;
            if ($width <= 0.0
                && $definiteContentHeight !== null && $definiteContentHeight > 0.0
                && $ratio !== null && $ratio > 0.0
            ) {
                $contentWidth = $definiteContentHeight * $ratio;
            }
            if ($contentWidth <= 0.0) {
                // Atomic-content painters have their own intrinsic-size
                // fallbacks (svg attrs / viewBox; math defaults). Leave
                // geometry at 0 so the painter's fallback chain still runs.
                continue;
            }
            $outerWidth = $contentWidth + $horizontalInset;
            // Content height: the definite value resolved above, else square
            // the outer box to its width (the historical no-font contract
            // the shaped path also follows), floored at the inset.
            $contentHeight = $definiteContentHeight
                ?? max(0.0, max($outerWidth, $verticalInset) - $verticalInset);
            // CSS Sizing 3 §5.2 — replaced min/max-width / -height clamps
            // (incl. min/max-content transferred through the intrinsic
            // ratio). Without this `max-width: min-content` on a sized
            // <canvas>/<img> is ignored.
            [$contentWidth, $contentHeight] = $this->clampAtomicReplaced($child->style, $contentWidth, $contentHeight);
            $outerWidth = $contentWidth + $horizontalInset;
            $outerHeight = $contentHeight + $verticalInset;
            // The item's inline footprint is its margin box; the block-axis
            // footprint (line-height contribution) is likewise the margin box.
            $outerAdvance = $marginLeft + $outerWidth + $marginRight;
            $marginBoxHeight = $marginTop + $outerHeight + $marginBottom;
            // CSS 2.1 §9.4.2 — wrap to a new line when the current line
            // already holds content and this box would overflow the IFC
            // available width. (A single box wider than the line still
            // gets its own line rather than an infinite loop.)
            if ($currentX > 0.0
                && $currentX + $outerAdvance
                    > ($this->currentWrapMeasure ?? $this->currentAvailableWidth) + 0.01
            ) {
                $lineTop += $lineHeight;
                $currentX = 0.0;
                $lineHeight = 0.0;
            }
            // Offset the content box by the left/top margin + inset so the
            // border box's top-left edge sits inside the margin box.
            $child->geometry->x = $parent->geometry->x + $currentX + $marginLeft + $borderLeft + $padLeft;
            $child->geometry->y = $parent->geometry->y + $lineTop + $marginTop + $borderTop + $padTop;
            $child->geometry->width = $contentWidth;
            $child->geometry->height = $contentHeight;
            $child->geometry->paddingTop = $padTop;
            $child->geometry->paddingBottom = $padBottom;
            $child->geometry->paddingLeft = $padLeft;
            $child->geometry->paddingRight = $padRight;
            $child->geometry->borderTop = $borderTop;
            $child->geometry->borderBottom = $borderBottom;
            $child->geometry->borderLeft = $borderLeft;
            $child->geometry->borderRight = $borderRight;
            $child->geometry->marginTop = $marginTop;
            $child->geometry->marginBottom = $marginBottom;
            $child->geometry->marginLeft = $marginLeft;
            $child->geometry->marginRight = $marginRight;
            $currentX += $outerAdvance;
            if ($marginBoxHeight > $lineHeight) {
                $lineHeight = $marginBoxHeight;
            }
            if ($lineTop + $lineHeight > $maxHeight) {
                $maxHeight = $lineTop + $lineHeight;
            }
        }
        return [[], $maxHeight];
    }

    /**
     * Resolve CSS Text 3 §11.2 `tab-size` to an integer space count.
     *
     * - `<integer>` / `<number>`: direct space count.
     * - `<length>`: divide by a glyph-space advance estimate
     *   (0.25 × font-size — a sane default for sans-serif) and round
     *   to the nearest integer ≥ 0. This is an approximation since
     *   tab-stop alignment isn't implemented, but converts a
     *   length-based author intent to the closest N-space expansion.
     * - Anything else (`auto`, unknown keywords): the spec default 8.
     */
    private function resolveTabSize(Box $box): int
    {
        $value = $box->style->get('tab-size');
        if ($value instanceof \Phpdftk\Css\Value\Integer) {
            return max(0, $value->value);
        }
        if ($value instanceof \Phpdftk\Css\Value\Number) {
            return max(0, (int) round($value->value));
        }
        if ($value instanceof \Phpdftk\Css\Value\Length) {
            $fontSize = $this->dominantFontSize($box, new LayoutContext(
                0.0,
                0.0,
                0.0,
                0.0,
                new \Phpdftk\Css\Cascade\LengthContext(),
            ));
            $spaceAdvance = max(0.1, $fontSize * 0.25);
            return max(0, (int) round($value->value / $spaceAdvance));
        }
        return 8;
    }

    /**
     * CSS Writing Modes 4 §3 — the container's INLINE size, when it is not
     * the physical width. In a vertical writing mode the inline axis runs
     * vertically, so lines must wrap against the container's block size
     * (its physical height) and only then be transposed onto the vertical
     * axis by {@see applyVerticalLineShift}. Wrapping against the physical
     * width and transposing afterwards breaks lines on the wrong axis.
     *
     * The geometry height is not committed yet while the inline pass runs,
     * so this reads the cascaded `height`, which the cascade has already
     * resolved to px. Returns null for a horizontal container, and for a
     * vertical one whose block size is indefinite: the used height of
     * those is only settled AFTER inline layout, so wrapping against it
     * would be a sizing cycle. Those keep the physical-width measure.
     */
    private function verticalInlineMeasure(Box $parent, ?float $inlineExtent = null): ?float
    {
        if (!WritingMode::fromStyle($parent->style)->isVertical()) {
            return null;
        }
        $height = $parent->style->get('height');
        if ($height instanceof Length && $height->value > 0.0) {
            return $height->value;
        }
        // CSS Sizing 4 §6.4 — a block-level box in a vertical containing
        // block STRETCH-fits its inline size (physical height) to that
        // containing block, so an `auto` height is still a definite
        // measure to wrap against. BlockLayout resolves the stretch
        // before this pass runs and threads the used value in here.
        if ($inlineExtent !== null && $inlineExtent > 0.0) {
            return $inlineExtent;
        }
        // A §7.3 orthogonal-flow fallback (take the inline size from the
        // nearest definite ancestor block size) was measured here and
        // scored EXACTLY the same as this narrower gate, so it is left out:
        // it widens the blast radius for no gain.
        return null;
    }

    /**
     * Resolve the left and right inset of a line at relative-Y `$y`
     * against the active {@see FloatContext}. Returns offsets relative
     * to the parent's content-edge X — so `left` is the line's start X
     * within the parent's box, and `right` is the line's max-end X.
     *
     * Without floats this is just `[0, $availableWidth]`. With a left
     * float overlapping the line, `left` rises; with a right float,
     * `right` falls.
     *
     * CSS 2.1 §9.5 / CSS Shapes 1 §1.2 — a line box next to a float is
     * shortened by the MOST-constrained intrusion over the line's full block
     * extent, not just at its top edge. `$bandHeight` defines that extent and
     * {@see FloatContext::leftEdgeInBand} takes the true extremum over it —
     * analytic for `inset()` and `polygon()`, and for `circle()` / `ellipse()`
     * the value at whichever band endpoint (or the vertical centre, when the
     * band straddles it) intrudes furthest. A `$bandHeight` of 0 (the default)
     * degrades to a single-point query at the top edge.
     *
     * @return array{left: float, right: float}
     */
    private function lineBounds(Box $parent, float $availableWidth, LayoutContext $context, float $relY, float $bandHeight = 0.0): array
    {
        // A vertical IFC wraps against the block size, and FloatContext is
        // physically horizontal — it samples a Y band and returns left/right
        // X, so feeding it this measure would compare different axes. Until
        // exclusions are logical, a vertical IFC simply takes the full
        // measure with no float narrowing.
        if ($this->currentWrapMeasure !== null) {
            return ['left' => 0.0, 'right' => $this->currentWrapMeasure];
        }
        $floatCtx = $context->floatContext;
        if ($floatCtx === null) {
            return ['left' => 0.0, 'right' => $availableWidth];
        }
        $parentX = $parent->geometry->x;
        $absTop = $parent->geometry->y + $relY;
        $absBottom = $absTop + max(0.0, $bandHeight);
        $maxLeft = $floatCtx->leftEdgeInBand($absTop, $absBottom, $parentX);
        $minRight = $floatCtx->rightEdgeInBand($absTop, $absBottom, $parentX + $availableWidth);
        return [
            'left' => max(0.0, $maxLeft - $parentX),
            'right' => max(0.0, $minRight - $parentX),
        ];
    }

    /**
     * Drop fragments from each overflowing line until an ellipsis glyph
     * fits at the end. Only applies when the parent's `text-overflow` is
     * `ellipsis`; the default `clip` keyword silently lets the content
     * overflow (matching the no-op CSS spec behaviour).
     *
     * @param list<LineBox> $lines
     * @return list<LineBox>
     */
    private function applyTextOverflow(
        array $lines,
        float $availableWidth,
        Box $parent,
        ShapingContext $shapingCtx,
        float $letterSpacing,
    ): array {
        $value = $parent->style->get('text-overflow');
        if (!($value instanceof \Phpdftk\Css\Value\Keyword)
            || strtolower($value->name) !== 'ellipsis'
        ) {
            return $lines;
        }
        $out = [];
        foreach ($lines as $line) {
            // `force: false` — only lines whose content actually overflows the
            // available width get an ellipsis (CSS UI 3 §6.2).
            $out[] = $this->appendEllipsisToLine($line, $availableWidth, $shapingCtx, $letterSpacing, false);
        }
        return $out;
    }

    /**
     * Append a U+2026 ellipsis to the end of a single line, trimming the
     * trailing content so the ellipsis fits within `$availableWidth`. When
     * `$force` is false the line is returned untouched unless its content
     * exceeds the width (the `text-overflow: ellipsis` contract); when true
     * the ellipsis is added regardless of overflow (the line-clamp
     * block-ellipsis, required on the last retained line even if it fits).
     */
    private function appendEllipsisToLine(
        LineBox $line,
        float $availableWidth,
        ShapingContext $shapingCtx,
        float $letterSpacing,
        bool $force,
    ): LineBox {
        if (!$force && $line->totalWidth() <= $availableWidth) {
            return $line;
        }
        $ellipsis = $this->shaper->shapeRun("\u{2026}", $shapingCtx);
        if ($ellipsis->glyphs === []) {
            return $line;
        }
        if ($letterSpacing !== 0.0) {
            $ellipsis = $this->applyLetterSpacing($ellipsis, $letterSpacing);
        }
        $ellipsisWidth = $ellipsis->totalAdvance;

        // Drop fragments from the end until the remaining content + ellipsis
        // fits, then trim glyphs off the trailing fragment if it still
        // overflows. Per CSS UI 3 §6.2 the ellipsis sits immediately adjacent
        // to the last visible glyph.
        $fragments = $line->fragments;
        $cutoff = $availableWidth - $ellipsisWidth;
        while ($fragments !== []) {
            $last = $fragments[array_key_last($fragments)];
            if ($last->x + $last->width <= $cutoff) {
                break;
            }
            // Try a per-glyph trim of the trailing fragment before discarding
            // it whole — `ppppp` overflowing `400px / 100px-per-glyph` keeps
            // `ppp` + ellipsis, not just an orphan ellipsis at x = 0.
            $trimmed = $this->trimFragmentToFit($last, $cutoff);
            if ($trimmed !== null) {
                $fragments[array_key_last($fragments)] = $trimmed;
                break;
            }
            array_pop($fragments);
        }
        if ($fragments === []) {
            // Nothing fits before the ellipsis; emit just the ellipsis at
            // x = 0 so the user sees something.
            $fragments[] = new InlineFragment(0.0, $ellipsisWidth, $ellipsis);
        } else {
            $last = $fragments[array_key_last($fragments)];
            $tail = $last->x + $last->width;
            $fragments[] = new InlineFragment($tail, $ellipsisWidth, $ellipsis);
        }

        return new LineBox($line->y, $line->height, $fragments, $line->baseline, $line->availableRight);
    }

    /**
     * CSS Overflow 4 §6 — truncate an already-laid-out line host to its
     * first `$keep` line boxes and force the block ellipsis onto the last
     * retained one.
     *
     * The single-block clamp in {@see layout()} only sees the lines one box
     * produced for itself. `line-clamp` counts the lines of the clamp
     * container's whole formatting context, so the block-level pass in
     * {@see BlockLayout} walks the subtree and calls back in here for the
     * host that straddles the clamp point. The ellipsis is forced even when
     * `count($lines) === $keep`: the caller only reaches this host because
     * more lines follow it, which is exactly the condition the block
     * ellipsis marks.
     *
     * Returns the block-axis extent of the retained lines in the host's own
     * coordinate space (0 when every line was dropped).
     */
    public function clampHostLines(Box $host, int $keep, LayoutContext $context): float
    {
        if ($keep <= 0) {
            $host->lineBoxes = [];

            return 0.0;
        }
        $lines = $host->lineBoxes;
        if ($lines === []) {
            return 0.0;
        }
        $this->currentFontResolver = $context->fontResolver;
        $match = $this->resolveBoxFont($host, $context->defaultFont);
        $font = $match['font'];
        if ($font === null) {
            // No shapeable font — drop the surplus lines without an
            // ellipsis rather than leaving the overflow visible.
            $host->lineBoxes = array_slice($lines, 0, $keep);

            return $this->lineExtent($host->lineBoxes);
        }
        $shapingCtx = new ShapingContext(
            $font,
            $this->dominantFontSize($host, $context),
            features: $this->resolveOpenTypeFeatures($host),
        );
        [$kept, $height] = $this->applyLineClamp(
            $lines,
            $keep,
            $host->geometry->width,
            $shapingCtx,
            $this->resolveLetterSpacing($host),
        );
        $host->lineBoxes = $kept;

        return $height;
    }

    /**
     * Block-axis extent of a line list (the bottom edge of its last line).
     *
     * @param list<LineBox> $lines
     */
    private function lineExtent(array $lines): float
    {
        $height = 0.0;
        foreach ($lines as $line) {
            $height = max($height, $line->y + $line->height);
        }

        return $height;
    }

    /**
     * The effective `line-clamp` count for a block, or null when the block is
     * not clamped. The standard `line-clamp: <integer>` applies to any block;
     * the legacy `-webkit-line-clamp: <integer>` only takes effect under the
     * legacy `display: -webkit-box` / `-webkit-box-orient: vertical` pairing
     * (CSS Overflow 4 Appendix; WPT asserts it is otherwise inert).
     */
    private function lineClampCount(Box $box): ?int
    {
        $value = $box->style->get('line-clamp');
        if ($value instanceof \Phpdftk\Css\Value\Integer && $value->value > 0) {
            return $value->value;
        }
        $legacy = $box->style->get('-webkit-line-clamp');
        if ($legacy instanceof \Phpdftk\Css\Value\Integer
            && $legacy->value > 0
            && $this->legacyWebkitClampApplies($box)
        ) {
            return $legacy->value;
        }

        return null;
    }

    /**
     * Whether the legacy `-webkit-line-clamp` gate is satisfied: the box must
     * be a legacy `-webkit-box` / `-webkit-inline-box` with a vertical
     * `-webkit-box-orient`.
     */
    private function legacyWebkitClampApplies(Box $box): bool
    {
        $display = $box->style->get('display');
        if (!($display instanceof \Phpdftk\Css\Value\Keyword)) {
            return false;
        }
        $d = strtolower($display->name);
        if ($d !== '-webkit-box' && $d !== '-webkit-inline-box') {
            return false;
        }
        $orient = $box->style->get('-webkit-box-orient');

        return $orient instanceof \Phpdftk\Css\Value\Keyword
            && in_array(strtolower($orient->name), ['vertical', 'block-axis'], true);
    }

    /**
     * Clamp the line list to `$maxLines`: keep the first N lines, mark the
     * last retained line with a forced block ellipsis, and return the reduced
     * line list together with the new block-axis extent (so the block shrinks
     * to exactly the retained lines). Caller guarantees `count($lines) >
     * $maxLines >= 1`.
     *
     * @param list<LineBox> $lines
     * @return array{list<LineBox>, float}
     */
    private function applyLineClamp(
        array $lines,
        int $maxLines,
        float $availableWidth,
        ShapingContext $shapingCtx,
        float $letterSpacing,
    ): array {
        $kept = array_slice($lines, 0, $maxLines);
        $lastIdx = array_key_last($kept);
        $kept[$lastIdx] = $this->appendEllipsisToLine($kept[$lastIdx], $availableWidth, $shapingCtx, $letterSpacing, true);
        $height = 0.0;
        foreach ($kept as $line) {
            $height = max($height, $line->y + $line->height);
        }

        return [$kept, $height];
    }

    /**
     * Trim glyphs off the tail of `$fragment` until its right edge sits
     * at or before `$cutoff`. Returns a new `InlineFragment` with the
     * shorter `ShapedRun`, or `null` when not a single glyph fits
     * (caller should drop the whole fragment instead).
     */
    private function trimFragmentToFit(InlineFragment $fragment, float $cutoff): ?InlineFragment
    {
        $shaped = $fragment->shapedRun;
        if ($shaped->glyphs === []) {
            return null;
        }
        $maxWidth = max(0.0, $cutoff - $fragment->x);
        $keptGlyphs = [];
        $total = 0.0;
        foreach ($shaped->glyphs as $g) {
            if ($total + $g->advanceX > $maxWidth + 0.001) {
                break;
            }
            $keptGlyphs[] = $g;
            $total += $g->advanceX;
        }
        if ($keptGlyphs === []) {
            return null;
        }
        $newShaped = new ShapedRun(
            $shaped->font,
            $shaped->fontSizePt,
            $shaped->direction,
            $keptGlyphs,
            $total,
        );
        return new InlineFragment(
            $fragment->x,
            $total,
            $newShaped,
            $fragment->baselineShift,
            $fragment->href,
            $fragment->isBold,
            $fragment->isItalic,
            $fragment->decorationLines,
            $fragment->textColor,
            $fragment->backgroundColor,
            $fragment->linkTitle,
            $fragment->decorationColor,
            $fragment->isWhitespace,
            $fragment->lineHeight,
            $fragment->verticalAlign,
        );
    }

    /**
     * Apply the parent's `text-align` to each line: `start` / `left` (default,
     * no-op), `center`, `end` / `right`, or `justify`. Justify is approximated
     * for the last line as left-aligned per CSS Text 3 §7.3 ("the last line
     * of a block, and any line ending with a forced line break, is start-
     * aligned"); inter-fragment justification on non-final lines distributes
     * extra space evenly across the gaps between fragments.
     *
     * @param list<LineBox> $lines
     * @return list<LineBox>
     */
    private function applyTextAlign(array $lines, float $availableWidth, Box $parent): array
    {
        $align = $this->textAlignKeyword($parent);
        $alignLast = $this->textAlignLastKeyword($parent, $align);
        // CSS Writing Modes 4 §3 — `text-align` aligns along the INLINE axis.
        // In a vertical writing mode the inline axis is vertical, so alignment
        // slack is measured against the container's inline size (its physical
        // height), not the block-axis `availableWidth` the horizontal model
        // was handed. (Increment 1 transposes the resulting inline offset onto
        // the vertical axis downstream in `applyVerticalLineShift`.)
        $wm = WritingMode::fromStyle($parent->style);
        // Same measure the fitter wrapped against, so alignment slack is
        // computed on the inline axis in both writing modes.
        $inlineExtent = $this->currentWrapMeasure ?? $availableWidth;
        // CSS Text 3 §7.2: `justify-all` is `justify` for every line
        // including the trailing one. Normalise to `justify` for the
        // body lines and force the last-line alignment to `justify`
        // too (the `textAlignLastKeyword` fallback would otherwise
        // start-align the last line for plain `justify`).
        if ($align === 'justify-all') {
            $align = 'justify';
            $alignLast = 'justify';
        }
        // CSS Text 3 §7.5: `text-justify: none` disables justification.
        // A `justify` text-align falls through to start-alignment.
        if ($this->isTextJustifyNone($parent)) {
            if ($align === 'justify') {
                $align = 'start';
            }
            if ($alignLast === 'justify') {
                $alignLast = 'start';
            }
        }
        // CSS Text 3 §7.1 — resolve direction-relative `start` / `end`
        // against the parent's writing direction. `start` is the
        // inline-start edge (left in LTR, right in RTL); `end` is the
        // inline-end edge (right in LTR, left in RTL). The physical
        // `left` / `right` / `center` values pass through unchanged.
        $isRtl = $this->isRtlDirection($parent);
        $align = $this->resolveLogicalTextAlign($align, $isRtl);
        $alignLast = $this->resolveLogicalTextAlign($alignLast, $isRtl);
        if ($align === 'left') {
            if ($alignLast === 'left' || $alignLast === 'auto') {
                return $lines;
            }
        }
        $count = count($lines);
        $out = [];
        foreach ($lines as $i => $line) {
            // CSS Text 3 §5.5 — trailing whitespace at the end of a
            // line hangs (zero-width for line measurement). For
            // alignment slack this means we measure the line's
            // visible content edge, NOT the full fragment tail.
            $used = $this->lineUsedWidth($line);
            // CSS 2.1 §9.5 — a line beside a float is SHORTER than the
            // container. Measuring slack against the container's inline
            // size instead sends right-aligned and centred text sliding
            // under the float.
            //
            // The fitter's bound is only comparable when it was measured in
            // the same axis alignment uses. That now holds for a vertical
            // IFC too whenever the fitter had a definite block size to wrap
            // against (`currentWrapMeasure`); a vertical container with an
            // indefinite block size still wraps against the physical width,
            // so its bound belongs to the other axis and is ignored.
            $axesAgree = !$wm->isVertical() || $this->currentWrapMeasure !== null;
            $lineExtent = $axesAgree && $line->availableRight !== null
                ? $line->availableRight
                : $inlineExtent;
            $slack = $lineExtent - $used;
            if ($slack <= 0.0) {
                $out[] = $line;
                continue;
            }
            $isLast = $i === $count - 1;
            $effective = $isLast ? $alignLast : $align;
            $newFragments = match ($effective) {
                'center' => $this->shiftFragments($line->fragments, $slack / 2.0),
                'right' => $this->shiftFragments($line->fragments, $slack),
                'justify' => $this->justifyFragments($line->fragments, $slack),
                default => $line->fragments,
            };
            $out[] = new LineBox($line->y, $line->height, $newFragments, $line->baseline, $line->availableRight);
        }
        return $out;
    }

    /**
     * Compute the line's content edge for alignment, excluding any
     * trailing whitespace fragments (CSS Text 3 §5.5). Used by
     * `applyTextAlign` so the centre / right shift respects the
     * visible content's right edge, not the hung-whitespace tail.
     */
    private function lineUsedWidth(LineBox $line): float
    {
        $right = 0.0;
        $fragments = $line->fragments;
        // Walk backwards skipping trailing whitespace fragments.
        $lastVisible = count($fragments) - 1;
        while ($lastVisible >= 0 && $fragments[$lastVisible]->isWhitespace) {
            $lastVisible--;
        }
        for ($i = 0; $i <= $lastVisible; $i++) {
            $edge = $fragments[$i]->x + $fragments[$i]->width;
            if ($edge > $right) {
                $right = $edge;
            }
        }
        return $right;
    }

    /**
     * Read the parent's cascaded `direction` and report whether it
     * resolves to `rtl`. Defaults to LTR when the property is
     * missing or the value isn't a Keyword the spec recognises.
     */
    private function isRtlDirection(Box $parent): bool
    {
        $value = $parent->style->get('direction');
        return $value instanceof \Phpdftk\Css\Value\Keyword
            && strtolower($value->name) === 'rtl';
    }

    /**
     * Map a CSS `text-align` keyword to its physical equivalent.
     * `start` → `left` in LTR, `right` in RTL; `end` → `right` in
     * LTR, `left` in RTL. The physical keywords pass through.
     */
    private function resolveLogicalTextAlign(string $align, bool $isRtl): string
    {
        return match ($align) {
            'start' => $isRtl ? 'right' : 'left',
            'end' => $isRtl ? 'left' : 'right',
            default => $align,
        };
    }

    /**
     * CSS Text 3 §7.5 — `true` when the parent declares
     * `text-justify: none`, in which case the justify branches of
     * `text-align` and `text-align-last` collapse to start-alignment.
     */
    private function isTextJustifyNone(Box $parent): bool
    {
        $value = $parent->style->get('text-justify');
        if (!($value instanceof \Phpdftk\Css\Value\Keyword)) {
            return false;
        }
        return strtolower($value->name) === 'none';
    }

    /**
     * Resolve CSS Text 3 §7.4 `text-align-last`. `auto` (initial)
     * inherits the block-aligned behaviour: when text-align is
     * `justify` the last line is start-aligned, otherwise it matches
     * text-align. Explicit values override.
     */
    private function textAlignLastKeyword(Box $parent, string $align): string
    {
        $value = $parent->style->get('text-align-last');
        if (!($value instanceof \Phpdftk\Css\Value\Keyword)) {
            return 'auto';
        }
        $lower = strtolower($value->name);
        if ($lower === 'auto') {
            // text-align: justify → last line is start-aligned per spec
            // (CSS Text 3 §7.4); `justify-all` is handled by the
            // caller before this resolution runs.
            return $align === 'justify' ? 'start' : $align;
        }
        // `text-align-last: justify-all` doesn't appear in any spec
        // grammar — only `text-align: justify-all` exists. Treat any
        // stray value as plain `justify` so the trailing line still
        // gets the fully-justified shifting.
        if ($lower === 'justify-all') {
            return 'justify';
        }
        return $lower;
    }

    private function textAlignKeyword(Box $parent): string
    {
        $value = $parent->style->get('text-align');
        if ($value instanceof \Phpdftk\Css\Value\Keyword) {
            return strtolower($value->name);
        }
        return 'start';
    }

    /**
     * Apply CSS Text 3 §2 `text-transform` to a text run before shaping.
     * `uppercase` / `lowercase` are full case mappings via `mb_strtoupper` /
     * `mb_strtolower`; `capitalize` upper-cases the first grapheme of each
     * whitespace-separated word; `full-width` / `full-size-kana` and other
     * Phase-2 transforms fall through unchanged.
     */
    private function applyTextTransform(string $text, Box $box): string
    {
        $value = $box->style->get('text-transform');
        if (!($value instanceof \Phpdftk\Css\Value\Keyword)) {
            return $text;
        }
        return \Phpdftk\Text\TextTransform::apply($text, $value->name);
    }

    /**
     * Resolve CSS Inline 3 §3 `line-height`:
     *  - `normal` → font-dependent multiplier (1.2 for Latin until proper
     *    OS/2 typo-metrics-driven line-height ships).
     *  - `<number>` → multiplier of font-size; value inherits as the number
     *    so children re-resolve against their own size.
     *  - `<length>` → absolute, already in px after `Cascade::resolveLengths`.
     *  - `<percentage>` → percentage of the element's own font-size.
     */
    private function resolveLineHeight(Box $parent, float $fontSize): float
    {
        $value = $parent->style->get('line-height');
        if ($value instanceof \Phpdftk\Css\Value\Keyword
            && strtolower($value->name) === 'normal'
        ) {
            return $fontSize * 1.2;
        }
        if ($value instanceof \Phpdftk\Css\Value\Number
            || $value instanceof \Phpdftk\Css\Value\Integer
        ) {
            return $fontSize * $value->value;
        }
        if ($value instanceof \Phpdftk\Css\Value\Percentage) {
            return $fontSize * ($value->value / 100.0);
        }
        if ($value instanceof Length) {
            return $value->value;
        }
        return $fontSize * 1.2;
    }

    /**
     * Resolve the parent's `text-indent` CSS value against the available
     * width. Length resolves directly; Percentage resolves against the
     * block's content width per CSS Text 3 §3.1; everything else falls to 0.
     */
    private function resolveTextIndent(Box $parent, float $availableWidth): float
    {
        $value = $parent->style->get('text-indent');
        if ($value instanceof Length) {
            return $value->value;
        }
        if ($value instanceof \Phpdftk\Css\Value\Percentage) {
            return $availableWidth * ($value->value / 100.0);
        }
        return 0.0;
    }

    private function whiteSpaceKeyword(Box $parent): string
    {
        $value = $parent->style->get('white-space');
        if ($value instanceof \Phpdftk\Css\Value\Keyword) {
            return strtolower($value->name);
        }
        return 'normal';
    }

    /**
     * Translate a line's fragments along the inline axis. `InlineFragment`
     * is readonly, so each one is rebuilt at the new `x`.
     *
     * Public because `BlockLayout` reuses it when it moves whole lines —
     * e.g. distributing an inline formatting context's lines into
     * multi-column columns.
     *
     * @param list<InlineFragment> $fragments
     * @return list<InlineFragment>
     */
    public function shiftFragments(array $fragments, float $dx): array
    {
        $out = [];
        foreach ($fragments as $f) {
            $out[] = new InlineFragment($f->x + $dx, $f->width, $f->shapedRun, $f->baselineShift, $f->href, $f->isBold, $f->isItalic, $f->decorationLines, $f->textColor, $f->backgroundColor, $f->linkTitle, $f->decorationColor, $f->isWhitespace, $f->lineHeight, $f->verticalAlign, $f->blockOffset, $f->atomicBox, $f->bgExtendAbove, $f->bgExtendBelow);
        }
        return $out;
    }

    /**
     * Spread the slack across the gaps between fragments (CSS Text 3 §7.3
     * `justify-content` approximation for inter-word distribution).
     *
     * @param list<InlineFragment> $fragments
     * @return list<InlineFragment>
     */
    private function justifyFragments(array $fragments, float $slack): array
    {
        // CSS Text 3 §7.5 — trailing whitespace at the end of a line
        // is excluded from justification (it hangs). Skip trailing
        // whitespace fragments when counting gaps and when shifting.
        $lastVisible = count($fragments) - 1;
        while ($lastVisible >= 0 && $fragments[$lastVisible]->isWhitespace) {
            $lastVisible--;
        }
        if ($lastVisible < 1) {
            return $fragments;
        }
        $gaps = $lastVisible;
        $delta = $slack / $gaps;
        $out = [];
        foreach ($fragments as $i => $f) {
            // Trailing-ws fragments pinned to whatever the last
            // visible fragment's right edge becomes — they aren't
            // shifted (they hang past the line edge).
            $shift = $i <= $lastVisible ? $i * $delta : $lastVisible * $delta;
            $out[] = new InlineFragment($f->x + $shift, $f->width, $f->shapedRun, $f->baselineShift, $f->href, $f->isBold, $f->isItalic, $f->decorationLines, $f->textColor, $f->backgroundColor, $f->linkTitle, $f->decorationColor, $f->isWhitespace, $f->lineHeight, $f->verticalAlign, $f->blockOffset, $f->atomicBox, $f->bgExtendAbove, $f->bgExtendBelow);
        }
        return $out;
    }

    /**
     * Tokenise the inline subtree at line-break opportunities and shape
     * each token. Each token records its width, its source kind
     * (whitespace / non-whitespace), and whether it closes a mandatory
     * break.
     *
     * @param list<string> $decorationLines
     * @return list<array{
     *     shapedRun: ShapedRun,
     *     isWhitespace: bool,
     *     kind: LineBreakKind,
     *     trailingSpace: float,
     *     noWrapBefore?: bool,
     *     baselineShift?: float,
     *     lineHeight?: float,
     *     verticalAlign?: string,
     *     href?: string|null,
     *     isBold?: bool,
     *     isItalic?: bool,
     *     decorationLines?: list<string>,
     *     textColor?: \Phpdftk\Css\Value\Color|null,
     *     backgroundColor?: \Phpdftk\Css\Value\Color|null,
     *     linkTitle?: string|null,
     *     decorationColor?: \Phpdftk\Css\Value\Color|null,
     *     bgExtendAbove?: float,
     *     bgExtendBelow?: float,
     *     atomicBox?: AtomicInlineBox,
     *     atomicContentWidth?: float,
     *     atomicOuterWidth?: float,
     *     atomicPadLeft?: float,
     *     atomicPadRight?: float,
     *     atomicBorderLeft?: float,
     *     atomicBorderRight?: float,
     *     atomicMarginLeft?: float,
     *     atomicMarginRight?: float,
     *     atomicMarginTop?: float,
     *     atomicMarginBottom?: float,
     *     atomicBorderBox?: bool,
     * }>
     */
    private function collectTokens(
        Box $parent,
        ShapingContext $shapingCtx,
        bool $collapseInternal,
        float $letterSpacing,
        float $wordSpacing,
        float $baselineShift,
        float $lineHeight,
        string $verticalAlign,
        ?string $href,
        bool $isBold,
        bool $isItalic,
        array $decorationLines,
        ?\Phpdftk\Css\Value\Color $textColor,
        ?\Phpdftk\Css\Value\Color $backgroundColor,
        ?string $linkTitle,
        ?\Phpdftk\Css\Value\Color $decorationColor,
        bool $splitWsBoundaries = false,
    ): array {
        $out = [];
        foreach ($parent->children as $child) {
            $this->walkInline(
                $child,
                $shapingCtx,
                $out,
                $collapseInternal,
                $letterSpacing,
                $wordSpacing,
                $baselineShift,
                $lineHeight,
                $verticalAlign,
                $href,
                $isBold,
                $isItalic,
                $decorationLines,
                $textColor,
                $backgroundColor,
                $linkTitle,
                $decorationColor,
                $splitWsBoundaries,
            );
        }
        // CSS 2.1 §16.6.1 — collapse runs of whitespace across the
        // *entire* inline tree, not per text node. The per-TextBox
        // collapse in `walkInline` only sees one node at a time, so
        // `<span>a </span><span> b</span>` arrives here as two
        // adjacent whitespace tokens. Drop any whitespace token whose
        // predecessor already ENDS in collapsible whitespace (only when
        // collapsing is on).
        //
        // "Ends in whitespace" is not the same as "is whitespace": the
        // tokeniser bundles a word with its trailing space (UAX #14 puts
        // the break opportunity AFTER the space), so `X <span> <i>s`
        // arrives as [`X `, ` `, `s`] — a run this pass used to keep
        // BOTH spaces of, because the first token isn't whitespace-only.
        // That put a spurious second space in front of every inline box
        // whose own content starts with collapsible whitespace.
        if ($collapseInternal) {
            $deduped = [];
            $prevEndsInWs = false;
            foreach ($out as $token) {
                if ($token['isWhitespace'] && $prevEndsInWs) {
                    continue;
                }
                $deduped[] = $token;
                $prevEndsInWs = $token['isWhitespace'] || $token['trailingSpace'] > 0.0;
            }
            $out = $deduped;
        }
        return $out;
    }

    /**
     * @param list<array{
     *     shapedRun: ShapedRun,
     *     isWhitespace: bool,
     *     kind: LineBreakKind,
     *     trailingSpace: float,
     *     noWrapBefore?: bool,
     *     baselineShift?: float,
     *     lineHeight?: float,
     *     verticalAlign?: string,
     *     href?: string|null,
     *     isBold?: bool,
     *     isItalic?: bool,
     *     decorationLines?: list<string>,
     *     textColor?: \Phpdftk\Css\Value\Color|null,
     *     backgroundColor?: \Phpdftk\Css\Value\Color|null,
     *     linkTitle?: string|null,
     *     decorationColor?: \Phpdftk\Css\Value\Color|null,
     *     bgExtendAbove?: float,
     *     bgExtendBelow?: float,
     *     atomicBox?: AtomicInlineBox,
     *     atomicContentWidth?: float,
     *     atomicOuterWidth?: float,
     *     atomicPadLeft?: float,
     *     atomicPadRight?: float,
     *     atomicBorderLeft?: float,
     *     atomicBorderRight?: float,
     *     atomicMarginLeft?: float,
     *     atomicMarginRight?: float,
     *     atomicMarginTop?: float,
     *     atomicMarginBottom?: float,
     *     atomicBorderBox?: bool,
     * }> $tokens
     * @param list<string> $decorationLines
     */
    private function walkInline(
        Box $box,
        ShapingContext $shapingCtx,
        array &$tokens,
        bool $collapseInternal,
        float $letterSpacing,
        float $wordSpacing,
        float $baselineShift,
        float $lineHeight,
        string $verticalAlign,
        ?string $href,
        bool $isBold,
        bool $isItalic,
        array $decorationLines,
        ?\Phpdftk\Css\Value\Color $textColor,
        ?\Phpdftk\Css\Value\Color $backgroundColor,
        ?string $linkTitle,
        ?\Phpdftk\Css\Value\Color $decorationColor,
        bool $splitWsBoundaries = false,
        float $bgExtendAbove = 0.0,
        float $bgExtendBelow = 0.0,
    ): void {
        if ($box instanceof TextBox) {
            $text = $box->text;
            if ($collapseInternal) {
                // CSS Text 3 §4.1.1: in `normal` / `nowrap`, runs of
                // whitespace collapse to a single space. Newlines collapse
                // alongside spaces / tabs / form feeds.
                $text = preg_replace('/[ \t\n\r\f]+/', ' ', $text) ?? $text;
            } else {
                // CSS Text 3 §11.2 — in white-space modes that preserve
                // tabs (`pre`, `pre-wrap`), each U+0009 expands to N
                // spaces. Phase-1 simplification: fixed expansion
                // instead of tab-stop alignment (which would require
                // tracking column position across mid-text breaks).
                $tabSize = $this->resolveTabSize($box);
                if ($tabSize > 0) {
                    $text = str_replace("\t", str_repeat(' ', $tabSize), $text);
                } else {
                    $text = str_replace("\t", '', $text);
                }
            }
            // CSS Text 3 §2: `text-transform` runs before shaping so the
            // shaper sees the transformed codepoints.
            $text = $this->applyTextTransform($text, $box);
            $breakAll = $this->isBreakAll($box);
            foreach ($this->tokeniseText($text, $shapingCtx, $letterSpacing, $wordSpacing, $breakAll, $splitWsBoundaries) as $token) {
                $token['baselineShift'] = $baselineShift;
                $token['lineHeight'] = $lineHeight;
                $token['verticalAlign'] = $verticalAlign;
                $token['href'] = $href;
                $token['isBold'] = $isBold;
                $token['isItalic'] = $isItalic;
                $token['decorationLines'] = $decorationLines;
                $token['textColor'] = $textColor;
                $token['backgroundColor'] = $backgroundColor;
                $token['bgExtendAbove'] = $bgExtendAbove;
                $token['bgExtendBelow'] = $bgExtendBelow;
                $token['linkTitle'] = $linkTitle;
                $token['decorationColor'] = $decorationColor;
                $tokens[] = $token;
            }
            return;
        }
        if ($box instanceof LineBreakBox) {
            // `<br>` — hard break that survives `white-space: normal`'s
            // collapsing. Emit a zero-width mandatory-break token so the
            // line-fitter closes the current line and starts a new one.
            $tokens[] = [
                'shapedRun' => new ShapedRun(
                    $shapingCtx->font,
                    $shapingCtx->fontSizePt,
                    $shapingCtx->direction,
                    [],
                    0.0,
                ),
                'isWhitespace' => false,
                'trailingSpace' => 0.0,
                'kind' => LineBreakKind::Mandatory,
                'lineHeight' => $lineHeight,
                'verticalAlign' => $verticalAlign,
            ];
            return;
        }
        if ($box instanceof AtomicInlineBox) {
            // Resolve the box's intrinsic *outer* horizontal advance —
            // what the line breaker needs — and the *content-box*
            // width that the painter draws into. The two differ
            // whenever the atomic carries padding / border, and they
            // diverge further under `box-sizing: border-box` (CSS
            // Sizing 3 §6.2): under border-box, the declared `width`
            // already includes padding + border, so the content
            // shrinks by that inset instead of the outer growing.
            $widthValue = $box->style->get('width');
            // CSS Sizing 3 §6.2 — percentages on atomic-inline
            // `width` resolve against the inline-formatting-context's
            // containing block (= the `availableWidth` parameter).
            // Without this, `<canvas style="width: 100%">` collapses
            // to 0 instead of stretching to fill the line.
            $declaredWidth = match (true) {
                $widthValue instanceof Length => $widthValue->value,
                $widthValue instanceof \Phpdftk\Css\Value\Percentage
                    => $this->currentAvailableWidth * ($widthValue->value / 100.0),
                default => 0.0,
            };
            $atomicPadLeft = self::atomicLength($box->style->get('padding-left'));
            $atomicPadRight = self::atomicLength($box->style->get('padding-right'));
            $atomicBorderLeft = self::atomicBorderWidth($box->style, 'left');
            $atomicBorderRight = self::atomicBorderWidth($box->style, 'right');
            $horizontalInset = $atomicPadLeft + $atomicPadRight + $atomicBorderLeft + $atomicBorderRight;
            // CSS 2.2 §10.8 — an inline-block's margins participate in layout:
            // horizontal margins add to the inline advance it occupies on the
            // line; vertical margins are part of its margin box (which
            // determines its line-height contribution). Previously dropped, so
            // adjacent inline-blocks touched instead of showing their gaps.
            $atomicMarginLeft = self::atomicLength($box->style->get('margin-left'));
            $atomicMarginRight = self::atomicLength($box->style->get('margin-right'));
            $atomicMarginTop = self::atomicLength($box->style->get('margin-top'));
            $atomicMarginBottom = self::atomicLength($box->style->get('margin-bottom'));
            $atomicBorderBox = self::atomicIsBorderBoxSizing($box->style);
            // CSS 2.1 §9.4.2 — when the box has an inner formatting
            // context, `BlockLayout::layoutInlineAtomicContents()` has
            // already laid it out and resolved its used content size
            // (shrink-to-fit for `width: auto`, §10.3.9). Take that size
            // and the box-model edges it resolved verbatim: recomputing
            // them here from the cascade would desynchronise the advance
            // the line breaker allocates from the geometry the children
            // were actually laid out in.
            if ($box->laidOutContentWidth !== null) {
                $g = $box->geometry;
                $atomicPadLeft = $g->paddingLeft;
                $atomicPadRight = $g->paddingRight;
                $atomicBorderLeft = $g->borderLeft;
                $atomicBorderRight = $g->borderRight;
                $atomicMarginLeft = $g->marginLeft;
                $atomicMarginRight = $g->marginRight;
                $atomicMarginTop = $g->marginTop;
                $atomicMarginBottom = $g->marginBottom;
                $horizontalInset = $atomicPadLeft + $atomicPadRight
                    + $atomicBorderLeft + $atomicBorderRight;
                $atomicContentWidth = $box->laidOutContentWidth;
                $atomicOuterWidth = $atomicContentWidth + $horizontalInset;
            } elseif ($declaredWidth > 0.0) {
                if ($atomicBorderBox) {
                    // Declared width is the border-box; content shrinks
                    // by the inset, outer stays at the declared value.
                    $atomicContentWidth = max(0.0, $declaredWidth - $horizontalInset);
                    $atomicOuterWidth = $declaredWidth;
                } else {
                    // Declared width is the content-box; outer grows by
                    // the inset.
                    $atomicContentWidth = $declaredWidth;
                    $atomicOuterWidth = $declaredWidth + $horizontalInset;
                }
            } else {
                // `width: auto`: CSS 2.1 §10.3.2 — a replaced element with a
                // definite height and an intrinsic aspect ratio derives its
                // width from height × ratio. Resolve the definite content
                // height (an explicit length, or a percentage against a
                // definite CB height) and transfer it through the ratio, so
                // the shaped path sizes an auto-width `<img height="100%">`
                // the same way layoutAtomicOnly() does. Without a definite
                // height or a ratio, defer to the painter's intrinsic
                // fallback (content = 0, no allocated advance).
                $definiteHeight = $this->atomicDefiniteContentHeight($box);
                $ratio = self::atomicAspectRatio($box->style);
                if ($definiteHeight !== null && $definiteHeight > 0.0
                    && $ratio !== null && $ratio > 0.0
                ) {
                    $atomicContentWidth = $definiteHeight * $ratio;
                    $atomicOuterWidth = $atomicContentWidth + $horizontalInset;
                } else {
                    $atomicContentWidth = 0.0;
                    $atomicOuterWidth = $horizontalInset;
                }
            }
            // CSS Writing Modes 4 §7.1 — `width` / `height` on a replaced
            // or inline-block box stay PHYSICAL, so which of them is the
            // inline size depends on the writing mode. Resolve the block
            // extents here (not in the fitter loop) because the advance
            // below needs them in a vertical mode.
            $atomicHeights = $this->resolveAtomicHeights(
                $box,
                $atomicBorderBox,
                $atomicMarginLeft + $atomicOuterWidth + $atomicMarginRight,
            );
            // Advance = margin-box INLINE size so the line-breaker and fitter
            // allocate the item's full footprint along the inline axis: its
            // horizontal extent in horizontal-tb, its VERTICAL extent in a
            // vertical writing mode.
            $atomicAdvance = $this->currentIsVertical
                ? $atomicMarginTop + $atomicHeights[1] + $atomicMarginBottom
                : $atomicMarginLeft + $atomicOuterWidth + $atomicMarginRight;
            $tokens[] = [
                'shapedRun' => new ShapedRun(
                    $shapingCtx->font,
                    $shapingCtx->fontSizePt,
                    $shapingCtx->direction,
                    [],
                    $atomicAdvance,
                ),
                'isWhitespace' => false,
                'trailingSpace' => 0.0,
                'kind' => LineBreakKind::Allowed,
                'baselineShift' => $baselineShift,
                'lineHeight' => $lineHeight,
                'verticalAlign' => $this->resolveVerticalAlignKeyword($box),
                'href' => $href,
                'isBold' => $isBold,
                'isItalic' => $isItalic,
                'decorationLines' => $decorationLines,
                'textColor' => $textColor,
                'backgroundColor' => $backgroundColor,
                'linkTitle' => $linkTitle,
                'decorationColor' => $decorationColor,
                'atomicBox' => $box,
                'atomicHeights' => $atomicHeights,
                'atomicContentWidth' => $atomicContentWidth,
                'atomicOuterWidth' => $atomicOuterWidth,
                'atomicPadLeft' => $atomicPadLeft,
                'atomicPadRight' => $atomicPadRight,
                'atomicBorderLeft' => $atomicBorderLeft,
                'atomicBorderRight' => $atomicBorderRight,
                'atomicMarginLeft' => $atomicMarginLeft,
                'atomicMarginRight' => $atomicMarginRight,
                'atomicMarginTop' => $atomicMarginTop,
                'atomicMarginBottom' => $atomicMarginBottom,
                'atomicBorderBox' => $atomicBorderBox,
            ];
            return;
        }
        if ($box instanceof InlineBox) {
            // CSS Inline 3 §4.5 `vertical-align: sub` / `super` shifts the
            // child fragments' baselines. Composes with any outer shift so
            // nested `<sup><sub>x</sub></sup>` still has a sensible effect.
            $boxShift = $this->resolveVerticalAlign($box, $shapingCtx->fontSizePt);
            // HTML 4 / 5 `<a href="...">` — descendants inherit the href so
            // the painter can emit a `/Link` annotation per fragment. The
            // companion `<a title="...">` lands on the annotation's
            // `/Contents` for hover tooltips.
            $childHref = $href;
            $childTitle = $linkTitle;
            if ($box->element !== null
                && strtolower($box->element->localName) === 'a'
            ) {
                $linkUrl = $box->element->getAttribute('href');
                if ($linkUrl !== null && $linkUrl !== '') {
                    $childHref = $linkUrl;
                    $title = $box->element->getAttribute('title');
                    $childTitle = $title === null || $title === '' ? null : $title;
                }
            }
            // Inline-level emphasis: resolve this box's own weight/style
            // request against the FontResolver. A real face match flips
            // the per-fragment fake-bold / fake-italic flags off; an
            // unmatched request OR no faceMap entry leaves the cascade's
            // synthetic-effect flags on so the painter draws the fallback.
            // OR with the inherited flags so `<strong><em>X</em></strong>`
            // keeps both effects even when only one resolves to a real face.
            $boxMatch = $this->resolveBoxFont($box, $shapingCtx->font);
            $childBold = $boxMatch['isBold'] || $isBold;
            $childItalic = $boxMatch['isItalic'] || $isItalic;
            // CSS Text Decoration 4 §2 says decorations set on an inline
            // apply to all in-flow descendant text. Union the box's
            // decoration lines with whatever the enclosing context set.
            $childDeco = $this->mergeDecorationLines($decorationLines, $this->decorationLines($box));
            // §3: `text-decoration-color` doesn't inherit, but when an
            // inline element sets a colour explicitly that colour applies
            // to its descendant fragments' decorations. A child only
            // overrides if it sets its own value — otherwise it keeps the
            // closest ancestor's choice.
            $childDecoColor = $this->resolveDecorationColor($box) ?? $decorationColor;
            // The inline box's own cascaded `color` overrides the inherited
            // one — `<a>` gets blue from the UA stylesheet even when its
            // parent is black.
            $childColor = $this->resolveColor($box) ?? $textColor;
            // `background-color` is not inherited but propagates downward
            // for inline rendering — every descendant fragment of a
            // `<mark>` should carry the yellow rect.
            $boxBg = $this->resolveBackground($box);
            $childBg = $boxBg ?? $backgroundColor;
            // CSS 2.1 §10.6.1 — vertical padding / border on an inline box
            // leave line height alone but are still PAINTED, so the box's
            // background bleeds over the lines above and below. The insets
            // travel with the background they belong to: a box painting its
            // own background carries its own insets, otherwise the nearest
            // ancestor's pair passes through unchanged.
            $childExtendAbove = $bgExtendAbove;
            $childExtendBelow = $bgExtendBelow;
            if ($boxBg !== null) {
                $childExtendAbove = self::atomicBorderWidth($box->style, 'top')
                    + self::atomicLength($box->style->get('padding-top'));
                $childExtendBelow = self::atomicBorderWidth($box->style, 'bottom')
                    + self::atomicLength($box->style->get('padding-bottom'));
            }
            // Mixed-size inline runs: if this inline carries a different
            // computed `font-size` than the active shaping context, build
            // a per-subtree context so descendants shape at the right size.
            // Same for `font-family` — when an inline names a font that's
            // registered in the FontResolver, switch the shaping font.
            $childCtx = $shapingCtx;
            $boxFontSize = $this->boxFontSize($box) ?? $shapingCtx->fontSizePt;
            // CSS Inline 3 §3 — `line-height` inherits (as a number it
            // re-resolves against each box's own font-size), so resolve this
            // inline box's used line-height here and carry it down. Fragments
            // stamp it so `lineMetrics` can apply per-run half-leading
            // instead of the block's uniform value.
            $childLineHeight = $this->resolveLineHeight($box, $boxFontSize);
            // vertical-align does NOT inherit: each inline box gets its own
            // keyword (default `baseline`), applied to its direct content.
            $childVAlign = $this->resolveVerticalAlignKeyword($box);
            $boxFont = $boxMatch['font'] ?? $shapingCtx->font;
            $fontSizeChanged = abs($boxFontSize - $shapingCtx->fontSizePt) > 0.001;
            $fontChanged = $boxFont !== $shapingCtx->font;
            if ($fontSizeChanged || $fontChanged) {
                $childCtx = new ShapingContext($boxFont, $boxFontSize);
            }
            // CSS 2.1 §8.4 — horizontal padding, border and margin on an
            // INLINE box are laid out: they add to its advance, pushing the
            // following content along. (The vertical ones deliberately do
            // NOT affect line height per §10.6.1.) They were being dropped
            // entirely, so a padded `<span>` occupied exactly its text.
            //
            // The inset rides in as a zero-glyph spacer token carrying the
            // inline's own background, so the padding area paints with the
            // box rather than showing the parent through. It is emitted with
            // a non-breaking kind: a line may not break inside an inline's
            // own padding.
            $leadInset = $this->inlineInlineAxisInset($box, 'left');
            if ($leadInset > 0.0) {
                $tokens[] = $this->inlineSpacerToken(
                    $leadInset,
                    $childCtx,
                    $baselineShift + $boxShift,
                    $childLineHeight,
                    $childVAlign,
                    $childHref,
                    $childBg,
                    $childTitle,
                    $childExtendAbove,
                    $childExtendBelow,
                );
            }
            foreach ($box->children as $c) {
                $this->walkInline(
                    $c,
                    $childCtx,
                    $tokens,
                    $collapseInternal,
                    $letterSpacing,
                    $wordSpacing,
                    $baselineShift + $boxShift,
                    $childLineHeight,
                    $childVAlign,
                    $childHref,
                    $childBold,
                    $childItalic,
                    $childDeco,
                    $childColor,
                    $childBg,
                    $childTitle,
                    $childDecoColor,
                    $splitWsBoundaries,
                    $childExtendAbove,
                    $childExtendBelow,
                );
            }
            $trailInset = $this->inlineInlineAxisInset($box, 'right');
            if ($trailInset > 0.0) {
                $tokens[] = $this->inlineSpacerToken(
                    $trailInset,
                    $childCtx,
                    $baselineShift + $boxShift,
                    $childLineHeight,
                    $childVAlign,
                    $childHref,
                    $childBg,
                    $childTitle,
                    $childExtendAbove,
                    $childExtendBelow,
                );
            }
        }
    }

    /**
     * The inline-axis inset an inline box contributes on one side: its
     * margin + border + padding (CSS 2.1 §8.4).
     */
    private function inlineInlineAxisInset(InlineBox $box, string $side): float
    {
        return self::atomicLength($box->style->get("margin-$side"))
            + self::atomicBorderWidth($box->style, $side)
            + self::atomicLength($box->style->get("padding-$side"));
    }

    /**
     * A zero-glyph token that occupies `$width` of inline advance — used for
     * an inline box's own horizontal padding / border / margin.
     *
     * It carries the inline's background so the inset paints as part of the
     * box, and is marked `Mandatory`-free: `kind` stays `Allowed` only at the
     * OUTER edges of the run, never between the inset and its own text, which
     * is why the spacer itself reports no break opportunity of its own.
     *
     * @return array{shapedRun: ShapedRun, isWhitespace: bool, kind: LineBreakKind, trailingSpace: float, baselineShift: float, lineHeight: float, verticalAlign: string, href: ?string, backgroundColor: ?\Phpdftk\Css\Value\Color, linkTitle: ?string}
     */
    private function inlineSpacerToken(
        float $width,
        ShapingContext $ctx,
        float $baselineShift,
        float $lineHeight,
        string $verticalAlign,
        ?string $href,
        ?\Phpdftk\Css\Value\Color $background,
        ?string $linkTitle,
        float $bgExtendAbove = 0.0,
        float $bgExtendBelow = 0.0,
    ): array {
        return [
            'shapedRun' => new ShapedRun(
                $ctx->font,
                $ctx->fontSizePt,
                \Phpdftk\Text\ShapingDirection::Ltr,
                [],
                $width,
            ),
            'isWhitespace' => false,
            'kind' => LineBreakKind::Allowed,
            'trailingSpace' => 0.0,
            'noWrapBefore' => true,
            'baselineShift' => $baselineShift,
            'lineHeight' => $lineHeight,
            'verticalAlign' => $verticalAlign,
            'href' => $href,
            'backgroundColor' => $background,
            'bgExtendAbove' => $bgExtendAbove,
            'bgExtendBelow' => $bgExtendBelow,
            'linkTitle' => $linkTitle,
        ];
    }

    /**
     * The alphabetic ascent of a fragment's shaping font in layout units:
     * `(font.ascent / unitsPerEm) × fontSizePt`. Mirrors the painter's own
     * ascent computation so the layout baseline and the painted baseline
     * agree.
     */
    private function fragmentAscent(InlineFragment $f): float
    {
        $font = $f->shapedRun->font;
        return ($font->ascent / max(1, $font->unitsPerEm)) * $f->shapedRun->fontSizePt;
    }

    /**
     * The alphabetic descent of a fragment's shaping font in layout units:
     * `(|font.descent| / unitsPerEm) × fontSizePt`. Positive (distance below
     * the baseline), mirroring the painter's descent computation.
     */
    private function fragmentDescent(InlineFragment $f): float
    {
        $font = $f->shapedRun->font;
        return (abs($font->descent) / max(1, $font->unitsPerEm)) * $f->shapedRun->fontSizePt;
    }

    /**
     * CSS2 §10.8 line-box sizing + `vertical-align` placement, in two passes.
     *
     * Each inline box (and the block's own strut) contributes a content box of
     * `ascent + descent` grown by its half-leading `(lineHeight − ascent −
     * descent) / 2`. A fragment's offset `o` from the line baseline (positive =
     * lower) drives both its placement and its extent (`above = expandedAscent
     * − o`, `below = expandedDescent + o`).
     *
     * Pass A — baseline (with composed `sub`/`super`/`<length>` already in
     * `baselineShift`) plus the strut-relative keywords `text-top`
     * (content-top → line text-top), `text-bottom` (content-bottom → line
     * text-bottom) and `middle` (box centre → baseline − x-height/2). The line
     * baseline sits at the greatest `above`; the height runs to the greatest
     * `below`. The strut always participates, so an empty line or a line of
     * only-smaller text still reserves the block's line height.
     *
     * Pass B — the line-box-relative keywords `top` (box top → line top, may
     * grow the line downward) and `bottom` (box bottom → line bottom), placed
     * against the Pass-A extent.
     *
     * Fragments whose computed offset differs from their stored `baselineShift`
     * are reconstructed so the painter (which draws at `line.baseline +
     * baselineShift`) places them correctly. A fragment whose `lineHeight` is
     * the `-1.0` sentinel falls back to no leading; an explicit `0.0` is
     * honoured as full negative leading.
     *
     * @param list<InlineFragment> $fragments
     * @return array{float, float, list<InlineFragment>} [height, baseline, fragments]
     */
    private function finalizeLine(
        array $fragments,
        float $strutAscent,
        float $strutDescent,
        float $strutLineHeight,
        float $strutXHeight,
    ): array {
        $strutLead = ($strutLineHeight - ($strutAscent + $strutDescent)) / 2.0;
        // CSS Writing Modes 4 §4.1 — a vertical writing mode's dominant
        // baseline is CENTRAL, not alphabetic: every item's extent is
        // centred on it rather than split into an ascent above and a
        // descent below. Modelling that as a symmetric half-extent makes
        // the line's cross size come out as the widest item's extent,
        // instead of that item's full extent PLUS the strut's descent.
        $vertical = $this->currentIsVertical;
        $above = $vertical
            ? ($strutAscent + $strutDescent) / 2.0 + $strutLead
            : $strutAscent + $strutLead;
        $below = $vertical
            ? ($strutAscent + $strutDescent) / 2.0 + $strutLead
            : $strutDescent + $strutLead;
        // Per-fragment offset below the line baseline; null defers a
        // top/bottom fragment to pass B.
        /** @var array<int, float|null> $offsets */
        $offsets = [];
        foreach ($fragments as $i => $f) {
            [$a, $d, $ea, $ed] = $this->fragmentExtents($f);
            if ($f->verticalAlign === 'top' || $f->verticalAlign === 'bottom') {
                $offsets[$i] = null;
                continue;
            }
            $o = match ($f->verticalAlign) {
                'text-top' => $a - $strutAscent,
                'text-bottom' => $strutDescent - $d,
                'middle' => ($a - $d) / 2.0 - $strutXHeight / 2.0,
                default => $f->baselineShift,
            };
            $offsets[$i] = $o;
            if ($vertical) {
                $half = ($ea + $ed) / 2.0;
                $above = max($above, $half - $o);
                $below = max($below, $half + $o);
                continue;
            }
            $above = max($above, $ea - $o);
            $below = max($below, $ed + $o);
        }
        $baseline = $above;
        $height = $above + $below;
        // Pass B — `top` / `bottom` align against the LINE BOX rather than the
        // baseline, so they can only be placed once the baseline-aligned
        // content has sized it. CSS 2.1 §10.8.1: the line box height spans the
        // uppermost box top to the lowermost box bottom, and top/bottom-aligned
        // boxes are included in that span — a box taller than the line grows
        // it. Which EDGE is pinned decides which way it grows: `top` holds the
        // box top against the line top so the line grows downward and the
        // baseline stays put, while `bottom` holds the box bottom against the
        // line bottom so the line grows upward and the baseline travels down
        // with it.
        //
        // Growth therefore has to finish before any offset is assigned;
        // computing them in one pass left every `bottom` box pinned to a line
        // that had not yet grown, so a 50px inline-block sat in a 19px line
        // and consecutive ones overlapped.
        $deferred = [];
        foreach ($fragments as $i => $f) {
            if ($offsets[$i] !== null) {
                continue;
            }
            [, , $ea, $ed] = $this->fragmentExtents($f);
            $deferred[$i] = [$f->verticalAlign, $ea, $ed];
        }
        foreach ($deferred as [$verticalAlign, $ea, $ed]) {
            $extent = $ea + $ed;
            if ($extent <= $height) {
                continue;
            }
            if ($verticalAlign !== 'top') {
                $baseline += $extent - $height;
            }
            $height = $extent;
        }
        foreach ($deferred as $i => [$verticalAlign, $ea, $ed]) {
            $offsets[$i] = $verticalAlign === 'top'
                ? $ea - $baseline
                : $height - $ed - $baseline;
        }
        $out = [];
        foreach ($fragments as $i => $f) {
            $o = $offsets[$i] ?? $f->baselineShift;
            $out[] = abs($o - $f->baselineShift) < 0.0001
                ? $f
                : $this->withBaselineShift($f, $o);
        }
        return [$height, $baseline, $out];
    }

    /**
     * Resolve an atomic inline box's used BLOCK-axis extents, with the
     * same box-sizing semantics the inline-axis pass applies to its
     * widths.
     *
     * Returns `[contentHeight, outerHeight, padTop, padBottom,
     * borderTop, borderBottom]`, where `outerHeight` is the border box
     * (padding + border included, margins not).
     *
     * `$fallbackOuter` is the value an `auto` height with no intrinsic
     * source squares to — the box's own outer WIDTH, preserving the
     * historical "no height = square box" contract.
     *
     * @return array{float, float, float, float, float, float}
     */
    private function resolveAtomicHeights(
        AtomicInlineBox $box,
        bool $borderBox,
        float $fallbackOuter,
    ): array {
        $heightValue = $box->style->get('height');
        // CSS 2.1 §10.5 — a percentage block size resolves only against a
        // definite containing-block height; otherwise it stays auto (0
        // here) and the box squares to its width. An explicit length / `0`
        // is always definite.
        $declaredHeight = match (true) {
            $heightValue instanceof Length => $heightValue->value,
            $heightValue instanceof \Phpdftk\Css\Value\Integer => (float) $heightValue->value,
            $heightValue instanceof \Phpdftk\Css\Value\Percentage
                && $this->currentCbHeightDefinite && $this->currentCbHeight > 0.0
                => $this->currentCbHeight * ($heightValue->value / 100.0),
            default => 0.0,
        };
        $padTop = self::atomicLength($box->style->get('padding-top'));
        $padBottom = self::atomicLength($box->style->get('padding-bottom'));
        $borderTop = self::atomicBorderWidth($box->style, 'top');
        $borderBottom = self::atomicBorderWidth($box->style, 'bottom');
        $inset = $padTop + $padBottom + $borderTop + $borderBottom;
        // The pre-layout pass resolved the used insets and the used content
        // block size (CSS 2.1 §10.6.3 — `height: auto` on a block container
        // is the distance to its last line box's bottom). Prefer both over
        // the cascade re-read, which has no way to know how tall the
        // contents came out.
        if ($box->laidOutContentHeight !== null) {
            $padTop = $box->geometry->paddingTop;
            $padBottom = $box->geometry->paddingBottom;
            $borderTop = $box->geometry->borderTop;
            $borderBottom = $box->geometry->borderBottom;
            $inset = $padTop + $padBottom + $borderTop + $borderBottom;
            $content = $box->laidOutContentHeight;

            return [$content, $content + $inset, $padTop, $padBottom, $borderTop, $borderBottom];
        }
        if ($declaredHeight > 0.0) {
            if ($borderBox) {
                return [
                    max(0.0, $declaredHeight - $inset),
                    $declaredHeight,
                    $padTop,
                    $padBottom,
                    $borderTop,
                    $borderBottom,
                ];
            }

            return [
                $declaredHeight,
                $declaredHeight + $inset,
                $padTop,
                $padBottom,
                $borderTop,
                $borderBottom,
            ];
        }

        return [
            max(0.0, $fallbackOuter - $inset),
            $fallbackOuter,
            $padTop,
            $padBottom,
            $borderTop,
            $borderBottom,
        ];
    }

    /**
     * Re-commit each inline atomic / replaced fragment's block-axis position
     * against the FINALIZED line baseline. The atomic side-channel in the
     * token loop seeds `geometry->y` from the raw shaping-font ascent, but the
     * true baseline is only known once {@see finalizeLine} has sized the line
     * — a tall atomic grows the line and moves the baseline downward. CSS 2.1
     * §10.8.1: a replaced / inline-block box aligns its bottom margin edge with
     * the line baseline, as shifted by `vertical-align` (folded into the
     * fragment's resolved `baselineShift` by finalizeLine). This overwrites the
     * seed with the correct value; nothing reads the seed in between. Pure-text
     * lines carry no atomic fragments, so this is a no-op for them.
     *
     * @param list<InlineFragment> $fragments
     */
    private function commitAtomicFragmentY(Box $parent, float $lineTop, float $lineBaseline, array $fragments): void
    {
        foreach ($fragments as $f) {
            $atomic = $f->atomicBox;
            if ($atomic === null) {
                continue;
            }
            $g = $atomic->geometry;
            $baselineY = $parent->geometry->y + $lineTop + $lineBaseline + $f->baselineShift;
            // §10.8.1 — an `inline-block` with in-flow line boxes puts its
            // OWN last baseline on the line's baseline, so the content-box
            // top sits exactly that offset above it.
            if ($atomic->laidOutBaseline !== null) {
                $g->y = $baselineY - $atomic->laidOutBaseline;
                continue;
            }
            // Border-box height from the committed geometry (content +
            // padding + border); the margin box adds the vertical margins.
            $outerHeight = $g->borderTop + $g->paddingTop + $g->height + $g->paddingBottom + $g->borderBottom;
            // Margin-box bottom on the baseline (+ vertical-align shift); step
            // up past bottom margin and the border box, then back down into the
            // content box's top-left — mirrors the seed formula in the token
            // loop but with the finalized baseline instead of the font ascent.
            $g->y = $baselineY
                - $g->marginBottom - $outerHeight + $g->borderTop + $g->paddingTop;
        }
    }

    /**
     * Re-commit each inline atomic / replaced fragment's inline-axis position
     * after `text-align` (and justify) have shifted the line's fragments. The
     * token loop seeded the atomic box's `geometry->x` from the pre-alignment
     * cursor, so a centred / right-aligned / justified line left the box
     * behind at the line's start while its text shifted. This mirrors the
     * seed formula in the token loop (content-box left = margin-box start +
     * left margin + border + padding) but with the fragment's now-shifted
     * `x`. Pure-text lines carry no atomic fragments, so this is a no-op.
     *
     * @param list<InlineFragment> $fragments
     */
    private function commitAtomicFragmentX(Box $parent, array $fragments): void
    {
        foreach ($fragments as $f) {
            $atomic = $f->atomicBox;
            if ($atomic === null) {
                continue;
            }
            $g = $atomic->geometry;
            $g->x = $parent->geometry->x + $f->x + $g->marginLeft + $g->borderLeft + $g->paddingLeft;
        }
    }

    /**
     * CSS 2.1 §10.8.1 — the distance from an atomic inline box's MARGIN-box
     * top edge down to the baseline it aligns on the line with.
     *
     * `BlockLayout` records the offset from the content-box top while laying
     * the box's own formatting context out; this re-bases it onto the margin
     * box, which is the extent the line box actually reserves. `null` when
     * the box takes the §10.8.1 fallback (no in-flow line boxes, or
     * `overflow` other than `visible`) and so aligns its bottom margin edge.
     */
    private static function atomicBaselineFromMarginTop(AtomicInlineBox $box): ?float
    {
        if ($box->laidOutBaseline === null) {
            return null;
        }
        $g = $box->geometry;

        return $g->marginTop + $g->borderTop + $g->paddingTop + $box->laidOutBaseline;
    }

    /**
     * A fragment's ascent, descent, and their half-leading-expanded forms:
     * `[ascent, descent, expandedAscent, expandedDescent]`.
     *
     * @return array{float, float, float, float}
     */
    private function fragmentExtents(InlineFragment $f): array
    {
        // CSS 2.1 §10.8 — an inline atomic / replaced box contributes its
        // *margin-box height* to the line box, not the surrounding font's
        // ascent/descent (its glyph run is empty). The box's baseline is its
        // bottom margin edge (§10.8.1), so the whole margin box sits above the
        // baseline: ascent = margin-box height, descent = 0. No half-leading —
        // replaced boxes don't carry line-height leading. The geometry was
        // already resolved by the atomic side-channel in the token loop before
        // this line was finalized.
        if ($f->atomicBox !== null) {
            $g = $f->atomicBox->geometry;
            if ($this->currentIsVertical) {
                // CSS Writing Modes 4 §7.1 — the box keeps its physical
                // `width`/`height`, so in a vertical writing mode the extent
                // it contributes to the line's CROSS size is its margin-box
                // WIDTH (its height is the inline advance instead). The
                // §10.8.1 baseline split doesn't apply across the cross axis
                // here, so the whole margin box counts as ascent.
                $crossBox = $g->marginLeft + $g->borderLeft + $g->paddingLeft + $g->width
                    + $g->paddingRight + $g->borderRight + $g->marginRight;

                return [$crossBox, 0.0, $crossBox, 0.0];
            }
            $marginBox = $g->marginTop + $g->borderTop + $g->paddingTop + $g->height
                + $g->paddingBottom + $g->borderBottom + $g->marginBottom;
            // §10.8.1 again: an `inline-block` WITH in-flow line boxes
            // aligns its own last baseline with the line's, so only the
            // part of its margin box above that baseline counts as
            // ascent — the rest hangs below like a descender.
            $ascent = self::atomicBaselineFromMarginTop($f->atomicBox);
            if ($ascent !== null) {
                $descent = max(0.0, $marginBox - $ascent);

                return [$ascent, $descent, $ascent, $descent];
            }

            return [$marginBox, 0.0, $marginBox, 0.0];
        }
        $a = $this->fragmentAscent($f);
        $d = $this->fragmentDescent($f);
        $lh = $f->lineHeight >= 0.0 ? $f->lineHeight : ($a + $d);
        $lead = ($lh - ($a + $d)) / 2.0;
        return [$a, $d, $a + $lead, $d + $lead];
    }

    /**
     * Clone a fragment with a new `baselineShift` (its offset below the line
     * baseline). Used by {@see finalizeLine} to write back the resolved
     * `vertical-align` offset while preserving every other field.
     */
    private function withBaselineShift(InlineFragment $f, float $shift): InlineFragment
    {
        return new InlineFragment(
            $f->x,
            $f->width,
            $f->shapedRun,
            $shift,
            $f->href,
            $f->isBold,
            $f->isItalic,
            $f->decorationLines,
            $f->textColor,
            $f->backgroundColor,
            $f->linkTitle,
            $f->decorationColor,
            $f->isWhitespace,
            $f->lineHeight,
            $f->verticalAlign,
            $f->blockOffset,
            $f->atomicBox,
            $f->bgExtendAbove,
            $f->bgExtendBelow,
        );
    }

    /**
     * The box's cascaded `font-size` in user-space units, or null when the
     * cascade didn't produce a `Length` (the cascade should always produce
     * one after `resolveLengths`; null is just a safety fallback).
     */
    private function boxFontSize(Box $box): ?float
    {
        $value = $box->style->get('font-size');
        return $value instanceof Length ? $value->value : null;
    }

    /**
     * Resolve the cascaded `font-weight` to a numeric value in the CSS
     * Fonts 4 1–1000 range. Keywords map per spec: `normal` → 400,
     * `bold` / `bolder` → 700, `lighter` → 100.
     */
    private function resolveWeight(Box $box): int
    {
        $value = $box->style->get('font-weight');
        if ($value instanceof \Phpdftk\Css\Value\Keyword) {
            return match (strtolower($value->name)) {
                'bold', 'bolder' => 700,
                'lighter' => 100,
                default => 400,
            };
        }
        if ($value instanceof \Phpdftk\Css\Value\Integer
            || $value instanceof \Phpdftk\Css\Value\Number
        ) {
            return max(1, min(1000, (int) $value->value));
        }
        return 400;
    }

    /**
     * Resolve the cascaded `font-style` to a lower-case keyword in the
     * `normal` | `italic` | `oblique` set. Unrecognised values fall back
     * to `normal`.
     */
    private function resolveStyle(Box $box): string
    {
        $value = $box->style->get('font-style');
        if ($value instanceof \Phpdftk\Css\Value\Keyword) {
            $lc = strtolower($value->name);
            if (in_array($lc, ['italic', 'oblique'], true)) {
                return $lc;
            }
        }
        return 'normal';
    }

    /**
     * Resolve the cascaded `font-variant-*` family + `font-feature-
     * settings` into the OpenType feature-tag list the shaper
     * consumes. Implements the mappings in CSS Fonts 4 §6 from
     * each high-level value keyword to the underlying OpenType
     * GSUB / GPOS feature tags.
     *
     * Tags from font-variant-* are emitted as bare tag strings
     * (= "enable"); font-feature-settings entries with a non-1
     * integer are emitted as `tag=N` so the shaper can encode
     * the variant index. The default `kern liga` baseline is
     * always present.
     *
     * @return list<string>
     */
    private function resolveOpenTypeFeatures(Box $box): array
    {
        $tags = ['kern', 'liga'];
        $add = function (string $tag) use (&$tags): void {
            if (!in_array($tag, $tags, true)) {
                $tags[] = $tag;
            }
        };
        $disable = function (string $tag) use (&$tags): void {
            $tags = array_values(array_filter($tags, fn(string $t) => $t !== $tag));
            $tags[] = $tag . '=0';
        };
        $variantMap = [
            'font-variant-caps' => [
                'small-caps' => ['smcp'],
                'all-small-caps' => ['smcp', 'c2sc'],
                'petite-caps' => ['pcap'],
                'all-petite-caps' => ['pcap', 'c2pc'],
                'unicase' => ['unic'],
                'titling-caps' => ['titl'],
            ],
            'font-variant-numeric' => [
                'lining-nums' => ['lnum'],
                'oldstyle-nums' => ['onum'],
                'proportional-nums' => ['pnum'],
                'tabular-nums' => ['tnum'],
                'diagonal-fractions' => ['frac'],
                'stacked-fractions' => ['afrc'],
                'ordinal' => ['ordn'],
                'slashed-zero' => ['zero'],
            ],
            'font-variant-position' => [
                'sub' => ['subs'],
                'super' => ['sups'],
            ],
            'font-variant-east-asian' => [
                'jis78' => ['jp78'],
                'jis83' => ['jp83'],
                'jis90' => ['jp90'],
                'jis04' => ['jp04'],
                'simplified' => ['smpl'],
                'traditional' => ['trad'],
                'full-width' => ['fwid'],
                'proportional-width' => ['pwid'],
                'ruby' => ['ruby'],
            ],
        ];
        foreach ($variantMap as $prop => $kwMap) {
            $value = $box->style->get($prop);
            foreach ($this->iterateKeywords($value) as $kw) {
                foreach ($kwMap[$kw] ?? [] as $tag) {
                    $add($tag);
                }
            }
        }
        // font-variant-ligatures has both enable / disable forms.
        $ligValue = $box->style->get('font-variant-ligatures');
        foreach ($this->iterateKeywords($ligValue) as $kw) {
            match ($kw) {
                'common-ligatures' => $add('liga'),
                'no-common-ligatures' => $disable('liga'),
                'discretionary-ligatures' => $add('dlig'),
                'no-discretionary-ligatures' => $disable('dlig'),
                'historical-ligatures' => $add('hlig'),
                'no-historical-ligatures' => $disable('hlig'),
                'contextual' => $add('calt'),
                'no-contextual' => $disable('calt'),
                default => null,
            };
        }
        // font-feature-settings — typed values land as
        // FontFeatureSettings; pass each entry through.
        $fss = $box->style->get('font-feature-settings');
        if ($fss instanceof \Phpdftk\Css\Value\FontFeatureSettings) {
            foreach ($fss->features as $entry) {
                if ($entry->value === 1) {
                    $add($entry->tag);
                } elseif ($entry->value === 0) {
                    $disable($entry->tag);
                } else {
                    // Variant index — encode as tag=N.
                    $add($entry->tag . '=' . $entry->value);
                }
            }
        }
        return $tags;
    }

    /**
     * Walk a cascaded value yielding each keyword name it
     * carries. Handles bare Keyword and Space-separated ValueList
     * forms (the two shapes font-variant-* values arrive in).
     *
     * @return iterable<string>
     */
    private function iterateKeywords(?\Phpdftk\Css\Value\Value $value): iterable
    {
        if ($value instanceof \Phpdftk\Css\Value\Keyword) {
            yield strtolower($value->name);
            return;
        }
        if ($value instanceof \Phpdftk\Css\Value\ValueList) {
            foreach ($value->values as $v) {
                if ($v instanceof \Phpdftk\Css\Value\Keyword) {
                    yield strtolower($v->name);
                }
            }
        }
    }

    /**
     * Resolve the cascaded `font-stretch` to its percentage value on
     * the CSS Fonts 4 §3.4 axis (50..200). Accepts the named keyword
     * forms (`condensed`, `expanded`, ...) and bare percentages.
     */
    private function resolveStretch(Box $box): float
    {
        $value = $box->style->get('font-stretch');
        if ($value instanceof \Phpdftk\Css\Value\Keyword) {
            return match (strtolower($value->name)) {
                'ultra-condensed' => 50.0,
                'extra-condensed' => 62.5,
                'condensed' => 75.0,
                'semi-condensed' => 87.5,
                'semi-expanded' => 112.5,
                'expanded' => 125.0,
                'extra-expanded' => 150.0,
                'ultra-expanded' => 200.0,
                default => 100.0,
            };
        }
        if ($value instanceof \Phpdftk\Css\Value\Percentage) {
            return max(50.0, min(200.0, (float) $value->value));
        }
        return 100.0;
    }

    /**
     * Combined font/weight/style resolution for the box. Picks the
     * concrete `OpenTypeData` via {@see FontResolver::resolveMatch()} and
     * derives the post-match "still needs synthetic effect" flags by
     * comparing the matched face's axes against the requested cascade.
     *
     * @return array{font: ?\Phpdftk\FontParser\FontFaceData, isBold: bool, isItalic: bool}
     */
    private function resolveBoxFont(Box $box, ?\Phpdftk\FontParser\FontFaceData $fallback): array
    {
        $weight = $this->resolveWeight($box);
        $style = $this->resolveStyle($box);
        $stretch = $this->resolveStretch($box);
        $requestBold = $weight >= 600;
        $requestItalic = $style !== 'normal';
        $resolver = $this->currentFontResolver;
        $match = $resolver?->resolveMatch(
            $box->style->get('font-family'),
            $weight,
            $style,
            $stretch,
        );
        $font = $match?->face->data ?? $fallback;
        // CSS Fonts 4 §6.7 — `font-synthesis-weight` / `font-synthesis-style`
        // veto the fake-bold stroke / fake-italic skew the painter would
        // otherwise apply when the matched face has no real bold or italic.
        $isBold = $requestBold
            && ($match === null || !$match->matchesWeight)
            && self::allowsSynthesis($box, 'font-synthesis-weight');
        $isItalic = $requestItalic
            && ($match === null || !$match->matchesStyle)
            && self::allowsSynthesis($box, 'font-synthesis-style');
        return ['font' => $font, 'isBold' => $isBold, 'isItalic' => $isItalic];
    }

    /**
     * Is the named `font-synthesis-*` longhand anything other than `none`?
     *
     * Both longhands are `auto | none` with an `auto` initial, so only an
     * explicit `none` suppresses the synthetic face.
     */
    private static function allowsSynthesis(Box $box, string $property): bool
    {
        $value = $box->style->get($property);
        return !($value instanceof \Phpdftk\Css\Value\Keyword)
            || strtolower($value->name) !== 'none';
    }

    /**
     * Read the box's cascaded `text-decoration-line` and return the set of
     * line keywords it carries — empty list for `none` / unset.
     *
     * @return list<string>
     */
    private function decorationLines(Box $box): array
    {
        $value = $box->style->get('text-decoration-line');
        if ($value === null
            || ($value instanceof \Phpdftk\Css\Value\Keyword
                && strtolower($value->name) === 'none')
        ) {
            return [];
        }
        $names = [];
        if ($value instanceof \Phpdftk\Css\Value\Keyword) {
            $names[] = strtolower($value->name);
        } elseif ($value instanceof \Phpdftk\Css\Value\ValueList) {
            foreach ($value->values as $v) {
                if ($v instanceof \Phpdftk\Css\Value\Keyword) {
                    $kw = strtolower($v->name);
                    if ($kw !== 'none') {
                        $names[] = $kw;
                    }
                }
            }
        }
        return array_values(array_unique(array_filter(
            $names,
            static fn(string $n): bool => in_array($n, ['underline', 'overline', 'line-through'], true),
        )));
    }

    /**
     * Combine outer + inner text-decoration line lists into a deduped list.
     *
     * @param list<string> $outer
     * @param list<string> $inner
     * @return list<string>
     */
    private function mergeDecorationLines(array $outer, array $inner): array
    {
        return array_values(array_unique(array_merge($outer, $inner)));
    }

    /**
     * Read the box's cascaded `color`, or null when the cascade didn't
     * produce a `Color` value (e.g. unresolved keyword fallback).
     */
    private function resolveColor(Box $box): ?\Phpdftk\Css\Value\Color
    {
        $value = $box->style->get('color');
        return $value instanceof \Phpdftk\Css\Value\Color ? $value : null;
    }

    private function resolveBackground(Box $box): ?\Phpdftk\Css\Value\Color
    {
        $value = $box->style->get('background-color');
        return $value instanceof \Phpdftk\Css\Value\Color ? $value : null;
    }

    /**
     * Read the box's cascaded `text-decoration-color`, or null when the
     * property is unset / inherits to the default keyword. CSS Text
     * Decoration 4 §3: the property does *not* inherit through inlines,
     * so callers explicitly pick whichever closer ancestor set it.
     */
    private function resolveDecorationColor(Box $box): ?\Phpdftk\Css\Value\Color
    {
        $value = $box->style->get('text-decoration-color');
        return $value instanceof \Phpdftk\Css\Value\Color ? $value : null;
    }

    /**
     * CSS Text 3 §5 / §6 — soft-wrap opportunities exist between
     * every two codepoints under: `word-break: break-all`,
     * `overflow-wrap: anywhere`, and `line-break: anywhere`. All
     * three are checked here so the line-fitter splits at any
     * character regardless of which property the author used.
     */
    private function isBreakAll(Box $box): bool
    {
        $wb = $box->style->get('word-break');
        if ($wb instanceof \Phpdftk\Css\Value\Keyword && strtolower($wb->name) === 'break-all') {
            return true;
        }
        $ow = $box->style->get('overflow-wrap');
        if ($ow instanceof \Phpdftk\Css\Value\Keyword) {
            $name = strtolower($ow->name);
            // `anywhere` adds break opportunities AND contributes to
            // min-content intrinsic sizing. `break-word` (and its
            // legacy `word-wrap: break-word` alias, which the cascade
            // normalises into `overflow-wrap`) adds the same
            // codepoint-level opportunities for line-fitting but does
            // NOT change intrinsic sizing — see CSS Text 3 §5.5. For
            // the line-fitter both fall into the same "every code-
            // point is a break opportunity" path; the min/max-content
            // measurer gates on `anywhere` only via
            // `intrinsicBreaksAnywhere`.
            if ($name === 'anywhere' || $name === 'break-word') {
                return true;
            }
        }
        // Legacy `word-wrap: break-word` is the same property under a
        // different name. The shorthand expander normalises it into
        // `overflow-wrap`, but author CSS that sets `word-wrap`
        // directly still lands here — read both.
        $ww = $box->style->get('word-wrap');
        if ($ww instanceof \Phpdftk\Css\Value\Keyword
            && strtolower($ww->name) === 'break-word'
        ) {
            return true;
        }
        $lb = $box->style->get('line-break');
        if ($lb instanceof \Phpdftk\Css\Value\Keyword && strtolower($lb->name) === 'anywhere') {
            return true;
        }
        return false;
    }

    /**
     * CSS Inline 3 §4.5 `vertical-align`: the `sub` and `super` keywords lift
     * / lower the fragment's baseline by a font-size-relative amount and
     * COMPOSE through nesting, so they're folded into the running
     * `baselineShift` during the tree walk. Browser defaults: `super` ≈
     * +0.5em lift, `sub` ≈ +0.2em drop. Returns the offset in layout-Y space
     * (negative lifts, positive drops). The line-relative keywords (top /
     * bottom / middle / text-top / text-bottom) do NOT compose and are
     * resolved later against the line box in `finalizeLine`, so they return 0
     * here — see {@see resolveVerticalAlignKeyword}.
     */
    private function resolveVerticalAlign(Box $box, float $fontSize): float
    {
        $value = $box->style->get('vertical-align');
        if (!($value instanceof \Phpdftk\Css\Value\Keyword)) {
            return 0.0;
        }
        return match (strtolower($value->name)) {
            'super' => -$fontSize * 0.5,
            'sub' => $fontSize * 0.2,
            default => 0.0,
        };
    }

    /**
     * The line-relative `vertical-align` keyword for a box — one of `top`,
     * `bottom`, `middle`, `text-top`, `text-bottom` — or `baseline` for
     * everything else (including `sub`/`super`/`<length>`, which compose via
     * {@see resolveVerticalAlign} into `baselineShift`). These keywords are
     * resolved against the line box / strut in `finalizeLine`.
     */
    private function resolveVerticalAlignKeyword(Box $box): string
    {
        $value = $box->style->get('vertical-align');
        if (!($value instanceof \Phpdftk\Css\Value\Keyword)) {
            return 'baseline';
        }
        $name = strtolower($value->name);
        return match ($name) {
            'top', 'bottom', 'middle', 'text-top', 'text-bottom' => $name,
            default => 'baseline',
        };
    }

    /**
     * Tokenise plain text at UAX #14 break opportunities, shaping each
     * resulting segment. Each segment is one token. Whitespace segments
     * are tagged so the line-fitter can collapse them at line edges. When
     * `$letterSpacing` is non-zero, every shaped glyph's advance is bumped
     * by that amount per CSS Text 3 §10 — the painter picks the difference
     * up automatically via its TJ-kerning path.
     *
     * @return list<array{
     *     shapedRun: ShapedRun,
     *     isWhitespace: bool,
     *     kind: LineBreakKind,
     *     trailingSpace: float,
     *     noWrapBefore?: bool,
     *     baselineShift?: float,
     *     lineHeight?: float,
     *     verticalAlign?: string,
     *     href?: string|null,
     *     isBold?: bool,
     *     isItalic?: bool,
     *     decorationLines?: list<string>,
     *     textColor?: \Phpdftk\Css\Value\Color|null,
     *     backgroundColor?: \Phpdftk\Css\Value\Color|null,
     *     linkTitle?: string|null,
     *     decorationColor?: \Phpdftk\Css\Value\Color|null,
     *     bgExtendAbove?: float,
     *     bgExtendBelow?: float,
     *     atomicBox?: AtomicInlineBox,
     *     atomicContentWidth?: float,
     *     atomicOuterWidth?: float,
     *     atomicPadLeft?: float,
     *     atomicPadRight?: float,
     *     atomicBorderLeft?: float,
     *     atomicBorderRight?: float,
     *     atomicMarginLeft?: float,
     *     atomicMarginRight?: float,
     *     atomicMarginTop?: float,
     *     atomicMarginBottom?: float,
     *     atomicBorderBox?: bool,
     * }>
     */
    private function tokeniseText(
        string $text,
        ShapingContext $shapingCtx,
        float $letterSpacing,
        float $wordSpacing,
        bool $breakAll = false,
        bool $splitWsBoundaries = false,
    ): array {
        if ($text === '') {
            return [];
        }
        if ($breakAll) {
            // CSS Text 3 §5 `word-break: break-all` — every codepoint is a
            // valid break point. Walk UTF-8 codepoints and emit one
            // segment per character. Whitespace runs still collapse into
            // their own segment so word/letter-spacing logic stays sane.
            $segments = [];
            $bytes = strlen($text);
            $i = 0;
            while ($i < $bytes) {
                $b = ord($text[$i]);
                $cpLen = $b < 0x80 ? 1 : ($b < 0xE0 ? 2 : ($b < 0xF0 ? 3 : 4));
                $segments[] = [
                    'text' => substr($text, $i, $cpLen),
                    'kind' => LineBreakKind::Allowed,
                ];
                $i += $cpLen;
            }
        } else {
            $breaks = iterator_to_array($this->lineBreaker->breakOpportunities($text), false);
            $segments = [];
            $start = 0;
            foreach ($breaks as $opp) {
                if ($opp->offset > $start) {
                    $segments[] = ['text' => substr($text, $start, $opp->offset - $start), 'kind' => $opp->kind];
                    $start = $opp->offset;
                }
            }
            if ($start < strlen($text)) {
                $segments[] = ['text' => substr($text, $start), 'kind' => LineBreakKind::Allowed];
            }
            // CSS Text 3 §5.5 — for the hanging-trailing-whitespace
            // behaviour to take effect under `pre-wrap` / `break-spaces`,
            // each contiguous whitespace run must be its own token. UAX-14
            // bundles `XX<ws>` into one segment (the break opportunity
            // sits at the end of the whitespace), which prevents the
            // line-fitter from telling the trailing ws apart from the
            // leading word at wrap time. Refine bundled segments here:
            // split any segment that mixes ws and non-ws into alternating
            // runs. Only applied when the caller opts in — under `normal`
            // / `nowrap` the bundled segments are correct (whitespace
            // collapses to single spaces that contribute to line width).
            if ($splitWsBoundaries) {
                $refined = [];
                foreach ($segments as $seg) {
                    if (preg_match('/[ \t\n\r\f]/', $seg['text']) !== 1
                        || preg_match('/[^ \t\n\r\f]/', $seg['text']) !== 1
                    ) {
                        $refined[] = $seg;
                        continue;
                    }
                    if (preg_match_all('/[ \t\n\r\f]+|[^ \t\n\r\f]+/u', $seg['text'], $m) === false) {
                        $refined[] = $seg;
                        continue;
                    }
                    $lastIdx = count($m[0]) - 1;
                    foreach ($m[0] as $i => $part) {
                        $refined[] = [
                            'text' => $part,
                            // The original break opportunity sits at
                            // the end of the bundled segment — keep
                            // that on the last sub-segment; intermediate
                            // boundaries get a plain `Allowed`
                            // opportunity so the breaker can wrap there
                            // too.
                            'kind' => $i === $lastIdx ? $seg['kind'] : LineBreakKind::Allowed,
                        ];
                    }
                }
                $segments = $refined;
            }
        }

        // CSS Writing Modes 3 §2 / Unicode UAX #9 — split each segment
        // at bidi-direction boundaries so a single text node like
        // "First שלום world" produces three separate shaping runs
        // (LTR-RTL-LTR) the layout can place individually. Without
        // this split, the run containing both LTR and RTL characters
        // shapes as a single block at one bidi level, miscoordinating
        // with browser-emitted swatch reference geometries.
        $segments = $this->splitBidiRuns($segments);

        $out = [];
        foreach ($segments as $seg) {
            $isWs = preg_match('/^[ \t\n\r\f]+$/', $seg['text']) === 1;
            $runDirection = $seg['direction'] ?? null;
            $runCtx = $shapingCtx;
            if ($runDirection === 'rtl') {
                $runCtx = new ShapingContext(
                    $shapingCtx->font,
                    $shapingCtx->fontSizePt,
                    $shapingCtx->script,
                    $shapingCtx->language,
                    \Phpdftk\Text\ShapingDirection::Rtl,
                    $shapingCtx->features,
                );
            }
            // CSS Text 3 §4.1.1 — a *segment break* (U+000A, and the
            // U+000D that may precede it) is a control character: in the
            // preserving white-space modes it forces a line break, and it
            // is NEVER rendered. UAX-14 already handed us the `Mandatory`
            // break opportunity, so all that is left is to keep the
            // codepoint away from the shaper — otherwise the font has no
            // glyph for it and every preserved line ended in a `.notdef`
            // box. The break kind and the whitespace flag still come from
            // the original segment text.
            $shapeText = strtr($seg['text'], ["\r\n" => '', "\n" => '', "\r" => '']);
            $shaped = $this->shaper->shapeRun($shapeText, $runCtx);
            if ($letterSpacing !== 0.0 && $shaped->glyphs !== []) {
                $shaped = $this->applyLetterSpacing($shaped, $letterSpacing);
            }
            if ($wordSpacing !== 0.0 && $shaped->glyphs !== []) {
                // CSS Text 3 §9: `word-spacing` adds advance only at word-
                // separator glyphs (U+0020 / U+00A0 at MVP).
                $shaped = $this->applyWordSpacing($shaped, $shapeText, $wordSpacing);
            }
            $out[] = [
                'shapedRun' => $shaped,
                'isWhitespace' => $isWs,
                'kind' => $seg['kind'],
                'trailingSpace' => $this->trailingCollapsibleAdvance($shaped, $shapeText, $isWs),
            ];
        }
        return $out;
    }

    /**
     * Shrink the last fragment by the collapsible whitespace it ends with,
     * so a line's reported width is its width AFTER CSS Text 3 §4.1.3 has
     * removed that space. The glyph stays in the run — a space paints
     * nothing — but it stops counting toward alignment and decoration.
     *
     * @param list<InlineFragment> $fragments
     * @return list<InlineFragment>
     */
    private function trimTrailingSpace(array $fragments, float $trailing): array
    {
        if ($trailing <= 0.0 || $fragments === []) {
            return $fragments;
        }
        $last = $fragments[count($fragments) - 1];
        $width = max(0.0, $last->width - $trailing);
        if ($width === $last->width) {
            return $fragments;
        }
        $fragments[count($fragments) - 1] = new InlineFragment(
            $last->x,
            $width,
            $last->shapedRun,
            $last->baselineShift,
            $last->href,
            $last->isBold,
            $last->isItalic,
            $last->decorationLines,
            $last->textColor,
            $last->backgroundColor,
            $last->linkTitle,
            $last->decorationColor,
            $last->isWhitespace,
            $last->lineHeight,
            $last->verticalAlign,
            blockOffset: $last->blockOffset,
            atomicBox: $last->atomicBox,
            bgExtendAbove: $last->bgExtendAbove,
            bgExtendBelow: $last->bgExtendBelow,
        );
        return $fragments;
    }

    /**
     * The advance of the COLLAPSIBLE whitespace a token ends with — the run
     * that CSS Text 3 §4.1.3 removes when the token lands at a line end.
     *
     * U+00A0 and friends are deliberately excluded: a no-break space is not
     * collapsible, so it keeps its width and its power to push a line over.
     */
    private function trailingCollapsibleAdvance(ShapedRun $shaped, string $text, bool $isWhitespace): float
    {
        if ($shaped->glyphs === []) {
            return 0.0;
        }
        if ($isWhitespace) {
            return $shaped->totalAdvance;
        }
        if (preg_match('/[ \t\n\r\f]+$/', $text, $matches, PREG_OFFSET_CAPTURE) !== 1) {
            return 0.0;
        }
        $start = $matches[0][1];
        $advance = 0.0;
        foreach ($shaped->glyphs as $glyph) {
            if ($glyph->sourceOffset >= $start) {
                $advance += $glyph->advanceX;
            }
        }
        return $advance;
    }

    /**
     * Unicode UAX #9 (Bidi) — minimal per-codepoint split. Walks each
     * segment, classifies codepoints into LTR / RTL / neutral via
     * `IntlChar::charDirection`, groups consecutive same-direction
     * characters into runs, and emits one segment per run. Neutrals
     * adopt the direction of their surrounding run (left-context
     * fallback). When `intl` is unavailable or the segment is pure
     * one-direction, segments pass through untouched.
     *
     * Each returned segment carries a `direction` field (`'ltr'` /
     * `'rtl'`) so the shaping pass can switch ShapingDirection per
     * run without re-classifying codepoints.
     *
     * @param list<array{text: string, kind: LineBreakKind}> $segments
     * @return list<array{text: string, kind: LineBreakKind, direction?: string}>
     */
    private function splitBidiRuns(array $segments): array
    {
        if (!class_exists(\IntlChar::class)) {
            return $segments;
        }
        $out = [];
        foreach ($segments as $seg) {
            $text = $seg['text'];
            // Quick exit: if no codepoint has explicit RTL direction,
            // the whole segment is LTR. Avoids the per-codepoint walk
            // for the common Latin case.
            if (preg_match('/[\x{0590}-\x{08FF}\x{FB1D}-\x{FDFF}\x{FE70}-\x{FEFF}]/u', $text) !== 1) {
                $seg['direction'] = 'ltr';
                $out[] = $seg;
                continue;
            }
            $bytes = strlen($text);
            $i = 0;
            $currentRun = '';
            $currentDir = null;
            while ($i < $bytes) {
                $b = ord($text[$i]);
                $cpLen = $b < 0x80 ? 1 : ($b < 0xE0 ? 2 : ($b < 0xF0 ? 3 : 4));
                $chunk = substr($text, $i, $cpLen);
                $cp = mb_ord($chunk, 'UTF-8');
                $i += $cpLen;
                if ($cp === false) {
                    $currentRun .= $chunk;
                    continue;
                }
                $bidiClass = \IntlChar::charDirection($cp);
                // L (LEFT_TO_RIGHT) → LTR; R / AL (RIGHT_TO_LEFT,
                // RIGHT_TO_LEFT_ARABIC) → RTL; everything else is a
                // neutral and inherits the surrounding run.
                $dirHere = match ($bidiClass) {
                    \IntlChar::CHAR_DIRECTION_LEFT_TO_RIGHT => 'ltr',
                    \IntlChar::CHAR_DIRECTION_RIGHT_TO_LEFT,
                    \IntlChar::CHAR_DIRECTION_RIGHT_TO_LEFT_ARABIC => 'rtl',
                    default => null,
                };
                if ($dirHere === null) {
                    $currentRun .= $chunk;
                    continue;
                }
                if ($currentDir === null) {
                    $currentDir = $dirHere;
                }
                if ($dirHere !== $currentDir) {
                    // Direction boundary — flush the current run.
                    if ($currentRun !== '') {
                        $out[] = [
                            'text' => $currentRun,
                            'kind' => LineBreakKind::Allowed,
                            'direction' => $currentDir,
                        ];
                    }
                    $currentRun = $chunk;
                    $currentDir = $dirHere;
                } else {
                    $currentRun .= $chunk;
                }
            }
            if ($currentRun !== '') {
                $out[] = [
                    'text' => $currentRun,
                    // The last sub-run keeps the parent segment's
                    // original break-kind (so a UAX-14 mandatory
                    // break at the source's end still fires).
                    'kind' => $seg['kind'],
                    'direction' => $currentDir ?? 'ltr',
                ];
            }
        }
        return $out;
    }

    /**
     * Return a new `ShapedRun` with every glyph's `advanceX` bumped by
     * `$letterSpacing` and the `totalAdvance` summed accordingly.
     */
    private function applyLetterSpacing(ShapedRun $run, float $letterSpacing): ShapedRun
    {
        $glyphs = [];
        $total = 0.0;
        foreach ($run->glyphs as $g) {
            $newAdvance = $g->advanceX + $letterSpacing;
            $glyphs[] = new ShapedGlyph(
                $g->glyphId,
                $g->sourceOffset,
                $g->sourceLength,
                $newAdvance,
                $g->advanceY,
                $g->offsetX,
                $g->offsetY,
            );
            $total += $newAdvance;
        }
        return new ShapedRun(
            $run->font,
            $run->fontSizePt,
            $run->direction,
            $glyphs,
            $total,
        );
    }

    /**
     * CSS Text 3 §10: `letter-spacing` keyword `normal` resolves to 0;
     * any `Length` (already in px after `Cascade::resolveLengths`) is the
     * extra advance applied to every glyph.
     */
    private function resolveLetterSpacing(Box $parent): float
    {
        $value = $parent->style->get('letter-spacing');
        if ($value instanceof Length) {
            return $value->value;
        }
        return 0.0;
    }

    /**
     * CSS Text 3 §9: `word-spacing` adds advance only at word-separator
     * glyphs. `normal` → 0; any `Length` is the extra advance per separator.
     */
    private function resolveWordSpacing(Box $parent): float
    {
        $value = $parent->style->get('word-spacing');
        if ($value instanceof Length) {
            return $value->value;
        }
        return 0.0;
    }

    /**
     * Bump the advance of every glyph whose source codepoint is a CSS
     * word separator (U+0020 SPACE or U+00A0 NO-BREAK SPACE). Builds and
     * returns a new `ShapedRun`.
     */
    private function applyWordSpacing(ShapedRun $run, string $sourceText, float $wordSpacing): ShapedRun
    {
        $glyphs = [];
        $total = 0.0;
        foreach ($run->glyphs as $g) {
            $bump = $this->isWordSeparatorAt($sourceText, $g->sourceOffset) ? $wordSpacing : 0.0;
            $newAdvance = $g->advanceX + $bump;
            $glyphs[] = new ShapedGlyph(
                $g->glyphId,
                $g->sourceOffset,
                $g->sourceLength,
                $newAdvance,
                $g->advanceY,
                $g->offsetX,
                $g->offsetY,
            );
            $total += $newAdvance;
        }
        return new ShapedRun(
            $run->font,
            $run->fontSizePt,
            $run->direction,
            $glyphs,
            $total,
        );
    }

    private function isWordSeparatorAt(string $text, int $offset): bool
    {
        if ($offset < 0 || $offset >= strlen($text)) {
            return false;
        }
        $b = ord($text[$offset]);
        if ($b === 0x20) {
            return true;
        }
        // U+00A0 NO-BREAK SPACE → UTF-8 bytes 0xC2 0xA0.
        return $b === 0xC2 && ($offset + 1) < strlen($text) && ord($text[$offset + 1]) === 0xA0;
    }

    /**
     * The dominant font-size for the inline run. Phase 1F.2 reads it from
     * the parent's cascaded `font-size`; mixed-size content is a Phase 2
     * follow-up alongside multi-font runs.
     */
    private function dominantFontSize(Box $parent, LayoutContext $context): float
    {
        $value = $parent->style->get('font-size');
        if ($value instanceof Length) {
            return $value->value;
        }
        return $context->lengthContext->currentFontSize;
    }

    /**
     * Pull a Length value off a cascaded property in pixels. Atomic
     * inline boxes don't have an in-progress containing-block width
     * to resolve percentages against at token-collection time (the
     * containing block isn't passed to {@see collectTokens}), so
     * Percentages resolve to 0 here. That matches the older atomic
     * behaviour and is acceptable for the in-scope test surface
     * (no `<img padding-left="50%">` fixtures); percentage-padding
     * on replaced inlines is a future enhancement once the inline
     * layout owns its parent's content width directly.
     */
    private static function atomicLength(?\Phpdftk\Css\Value\Value $value): float
    {
        return $value instanceof Length
            ? \Phpdftk\Css\Cascade\LengthResolver::clampPx($value->value)
            : 0.0;
    }

    /**
     * Side-specific border width for atomic inline boxes, accounting
     * for `border-<side>-style: none` (which collapses the width to
     * zero per CSS Backgrounds 3 §4.4) and the `thin`/`medium`/`thick`
     * keyword widths.
     */
    private static function atomicBorderWidth(\Phpdftk\Css\Cascade\CascadedValues $style, string $side): float
    {
        $styleValue = $style->get("border-$side-style");
        if ($styleValue instanceof \Phpdftk\Css\Value\Keyword
            && strtolower($styleValue->name) === 'none'
        ) {
            return 0.0;
        }
        $width = $style->get("border-$side-width");
        if ($width instanceof Length) {
            return \Phpdftk\Css\Cascade\LengthResolver::clampPx($width->value);
        }
        if ($width instanceof \Phpdftk\Css\Value\Keyword) {
            return match (strtolower($width->name)) {
                'thin' => 1.0,
                'medium' => 3.0,
                'thick' => 5.0,
                default => 0.0,
            };
        }
        return 0.0;
    }

    /**
     * CSS Sizing 3 §6.2 — `true` when the cascaded `box-sizing` is
     * `border-box`, meaning declared width/height include the
     * padding + border edges.
     */
    private static function atomicIsBorderBoxSizing(\Phpdftk\Css\Cascade\CascadedValues $style): bool
    {
        $value = $style->get('box-sizing');
        return $value instanceof \Phpdftk\Css\Value\Keyword
            && strtolower($value->name) === 'border-box';
    }

    /**
     * Resolve an atomic replaced box's DEFINITE content-box height, or
     * null when the height is auto / indefinite. An explicit length (or
     * `0`) is definite; a percentage is definite only against a definite
     * containing-block height (CSS 2.1 §10.5). Under `box-sizing:
     * border-box` the declared value includes the padding + border, so
     * the content height is the declared value minus the vertical inset.
     * Mirrors the height resolution in layoutAtomicOnly() so the shaped
     * path can transfer a definite height through the intrinsic ratio to
     * derive an auto width (§10.3.2).
     */
    private function atomicDefiniteContentHeight(AtomicInlineBox $box): ?float
    {
        $heightValue = $box->style->get('height');
        $declared = match (true) {
            $heightValue instanceof Length => $heightValue->value,
            $heightValue instanceof \Phpdftk\Css\Value\Integer => (float) $heightValue->value,
            $heightValue instanceof \Phpdftk\Css\Value\Percentage
                && $this->currentCbHeightDefinite && $this->currentCbHeight > 0.0
                => $this->currentCbHeight * ($heightValue->value / 100.0),
            default => null,
        };
        if ($declared === null) {
            return null;
        }
        $declared = max(0.0, $declared);
        if (!self::atomicIsBorderBoxSizing($box->style)) {
            return $declared;
        }
        $inset = self::atomicLength($box->style->get('padding-top'))
            + self::atomicLength($box->style->get('padding-bottom'))
            + self::atomicBorderWidth($box->style, 'top')
            + self::atomicBorderWidth($box->style, 'bottom');
        return max(0.0, $declared - $inset);
    }

    /**
     * Intrinsic aspect ratio (width / height) of a replaced atomic box,
     * read from the `aspect-ratio` cascade value the box generator
     * exposes for `<img>` / `<canvas>`. Null when absent or malformed.
     */
    private static function atomicAspectRatio(\Phpdftk\Css\Cascade\CascadedValues $style): ?float
    {
        $value = $style->get('aspect-ratio');
        if ($value instanceof \Phpdftk\Css\Value\ValueList
            && $value->separator === \Phpdftk\Css\Value\ListSeparator::Slash
            && count($value->values) >= 2
        ) {
            $w = self::atomicNumeric($value->values[0]);
            $h = self::atomicNumeric($value->values[1]);
            if ($w !== null && $h !== null && $h > 0.0) {
                return $w / $h;
            }
        }
        $direct = self::atomicNumeric($value);
        return ($direct !== null && $direct > 0.0) ? $direct : null;
    }

    private static function atomicNumeric(?\Phpdftk\Css\Value\Value $v): ?float
    {
        if ($v instanceof \Phpdftk\Css\Value\Integer) {
            return (float) $v->value;
        }
        if ($v instanceof \Phpdftk\Css\Value\Number) {
            return $v->value;
        }
        return null;
    }

    /**
     * CSS Sizing 3 §5.2 + csswg issue 3973 — resolve a min/max-width or
     * min/max-height value for a replaced atomic box to pixels. Lengths
     * pass through; `min-content` / `max-content` / `fit-content`
     * transfer the box's definite cross size through the intrinsic
     * aspect ratio (a replaced element's content-based size in one axis
     * is the other axis times the ratio). Returns null when the
     * constraint does not apply (`none` / `auto` / `stretch`, a block-
     * axis percentage, or a keyword with no ratio to transfer through).
     */
    private function resolveAtomicMinMax(
        \Phpdftk\Css\Cascade\CascadedValues $style,
        string $property,
        float $crossContentSize,
        ?float $ratio,
        bool $isWidth,
    ): ?float {
        $v = $style->get($property);
        if ($v instanceof \Phpdftk\Css\Value\Keyword) {
            $name = strtolower($v->name);
            if (in_array($name, ['min-content', 'max-content', 'fit-content'], true)) {
                if ($ratio === null || $ratio <= 0.0) {
                    return null;
                }
                return $isWidth ? $crossContentSize * $ratio : $crossContentSize / $ratio;
            }
            return null;
        }
        if ($v instanceof Length) {
            return \Phpdftk\Css\Cascade\LengthResolver::clampPx($v->value);
        }
        if ($v instanceof \Phpdftk\Css\Value\Percentage) {
            if ($isWidth) {
                return $this->currentAvailableWidth * ($v->value / 100.0);
            }
            // A percentage block-axis min/max resolves only against a
            // definite containing-block height (CSS 2.1 §10.5).
            if ($this->currentCbHeightDefinite && $this->currentCbHeight > 0.0) {
                return $this->currentCbHeight * ($v->value / 100.0);
            }
            return null;
        }
        return null;
    }

    /**
     * Apply the replaced-element min/max-width / -height clamps to a
     * resolved (content-box) width / height pair. The width clamps
     * transfer the original height through the ratio (and vice versa)
     * so each keyword resolves against the definite cross size, not a
     * value another clamp on the same call already changed.
     *
     * @return array{0: float, 1: float} clamped [width, height]
     */
    private function clampAtomicReplaced(
        \Phpdftk\Css\Cascade\CascadedValues $style,
        float $width,
        float $height,
    ): array {
        $ratio = self::atomicAspectRatio($style);
        $origWidth = $width;
        $origHeight = $height;
        $hasRatio = $ratio !== null && $ratio > 0.0;
        $maxW = $this->resolveAtomicMinMax($style, 'max-width', $origHeight, $ratio, true);
        if ($maxW !== null && $width > $maxW) {
            $width = $maxW;
            // CSS 2.1 §10.4 — clamping one axis of a replaced element with
            // an intrinsic ratio transfers to the other axis to preserve
            // the ratio.
            if ($hasRatio) {
                $height = $width / $ratio;
            }
        }
        $minW = $this->resolveAtomicMinMax($style, 'min-width', $origHeight, $ratio, true);
        if ($minW !== null && $width < $minW) {
            $width = $minW;
            if ($hasRatio) {
                $height = $width / $ratio;
            }
        }
        $maxH = $this->resolveAtomicMinMax($style, 'max-height', $origWidth, $ratio, false);
        if ($maxH !== null && $height > $maxH) {
            $height = $maxH;
            if ($hasRatio) {
                $width = $height * $ratio;
            }
        }
        $minH = $this->resolveAtomicMinMax($style, 'min-height', $origWidth, $ratio, false);
        if ($minH !== null && $height < $minH) {
            $height = $minH;
            if ($hasRatio) {
                $width = $height * $ratio;
            }
        }
        return [$width, $height];
    }
}
