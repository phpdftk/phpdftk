<?php

declare(strict_types=1);

namespace Phpdftk\HtmlToPdf\Layout;

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
    public function layout(Box $parent, float $availableWidth, LayoutContext $context): array
    {
        $this->currentFontResolver = $context->fontResolver;
        // Resolve the shaping font + post-match synthetic-effect flags via
        // CSS Fonts 4 §6 weight/style matching. When a real face matches
        // the cascaded weight/style, `isBold`/`isItalic` come back false so
        // the painter doesn't double up with fake-bold / fake-italic.
        $parentMatch = $this->resolveBoxFont($parent, $context->defaultFont);
        $font = $parentMatch['font'];
        $fontSize = $this->dominantFontSize($parent, $context);
        if ($font === null || $availableWidth <= 0.0) {
            return [[], 0.0];
        }
        $shapingCtx = new ShapingContext($font, $fontSize);
        $lineHeight = $this->resolveLineHeight($parent, $fontSize);
        $whiteSpace = $this->whiteSpaceKeyword($parent);
        $allowSoftWrap = $whiteSpace !== 'nowrap' && $whiteSpace !== 'pre';
        // `pre` and `pre-wrap` preserve whitespace; only `normal` / `nowrap` /
        // `pre-line` collapse leading whitespace at line starts (CSS Text 3 §4).
        $collapseLeadingWhitespace = $whiteSpace !== 'pre' && $whiteSpace !== 'pre-wrap';
        $collapseInternalWhitespace = $whiteSpace === 'normal' || $whiteSpace === 'nowrap';

        $letterSpacing = $this->resolveLetterSpacing($parent);
        $wordSpacing = $this->resolveWordSpacing($parent);
        $tokens = $this->collectTokens(
            $parent,
            $shapingCtx,
            $collapseInternalWhitespace,
            $letterSpacing,
            $wordSpacing,
            baselineShift: 0.0,
            href: null,
            isBold: $parentMatch['isBold'],
            isItalic: $parentMatch['isItalic'],
            decorationLines: $this->decorationLines($parent),
            textColor: $this->resolveColor($parent),
            backgroundColor: null,
            linkTitle: null,
            decorationColor: $this->resolveDecorationColor($parent),
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
        $bounds = $this->lineBounds($parent, $availableWidth, $context, 0.0);
        $lines = [];
        $currentFragments = [];
        $currentX = $bounds['left'] + $textIndent;
        $lineMaxRight = $bounds['right'];
        $atLineStart = true;
        $y = 0.0;
        foreach ($tokens as $token) {
            $width = $token['shapedRun']->totalAdvance;
            $isMandatory = $token['kind'] === LineBreakKind::Mandatory;

            if ($collapseLeadingWhitespace && $token['isWhitespace'] && $atLineStart) {
                // Leading whitespace at a line start is collapsed.
                continue;
            }
            if ($allowSoftWrap
                && $currentX + $width > $lineMaxRight
                && $currentFragments !== []
            ) {
                // Wrap before placing this token.
                $effective = $this->lineHeightFor($currentFragments, $lineHeight);
                $lines[] = new LineBox($y, $effective, $currentFragments);
                $y += $effective;
                $currentFragments = [];
                $bounds = $this->lineBounds($parent, $availableWidth, $context, $y);
                $currentX = $bounds['left'];
                $lineMaxRight = $bounds['right'];
                $atLineStart = true;
                if ($collapseLeadingWhitespace && $token['isWhitespace']) {
                    // Drop whitespace at start of next line.
                    continue;
                }
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
            );
            // Side-channel: AtomicInlineBox positions get committed back to
            // the box's geometry so the painter can draw images / replaced
            // content at the right spot. CSS Inline 3 §4.5: for the default
            // `vertical-align: baseline`, the inline-block's baseline aligns
            // with the parent line's baseline; for replaced elements like
            // `<img>` the baseline is the bottom of the box. So position
            // the box so its *bottom* sits at the line's baseline (line.y +
            // ascent of the shaping font) — same convention the painter
            // uses for text baselines.
            $atomic = $token['atomicBox'] ?? null;
            if ($atomic !== null) {
                $heightValue = $atomic->style->get('height');
                $atomicHeight = $heightValue instanceof Length
                    ? $heightValue->value
                    : $width;
                $shapedRun = $token['shapedRun'];
                $atomicFont = $shapedRun->font;
                $atomicUpem = max(1, $atomicFont->unitsPerEm);
                $atomicAscent = ($atomicFont->ascent / $atomicUpem) * $shapedRun->fontSizePt;
                $atomic->geometry->x = $parent->geometry->x + $currentX;
                $atomic->geometry->y = $parent->geometry->y + $y + $atomicAscent - $atomicHeight;
                $atomic->geometry->width = $width;
                $atomic->geometry->height = $atomicHeight;
            }
            $currentX += $width;
            $atLineStart = false;
            if ($isMandatory) {
                $effective = $this->lineHeightFor($currentFragments, $lineHeight);
                $lines[] = new LineBox($y, $effective, $currentFragments);
                $y += $effective;
                $currentFragments = [];
                $bounds = $this->lineBounds($parent, $availableWidth, $context, $y);
                $currentX = $bounds['left'];
                $lineMaxRight = $bounds['right'];
                $atLineStart = true;
            }
        }
        if ($currentFragments !== []) {
            $effective = $this->lineHeightFor($currentFragments, $lineHeight);
            $lines[] = new LineBox($y, $effective, $currentFragments);
            $y += $effective;
        }

        // CSS UI 3 §6.2: `text-overflow: ellipsis` truncates each line's
        // tail when its content exceeds the available width. Runs before
        // text-align so the alignment math operates on the truncated rect.
        $lines = $this->applyTextOverflow($lines, $availableWidth, $parent, $shapingCtx, $letterSpacing);

        $lines = $this->applyTextAlign($lines, $availableWidth, $parent);
        return [$lines, $y];
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
     * Resolve the left and right inset of a line at relative-Y `$y`
     * against the active {@see FloatContext}. Returns offsets relative
     * to the parent's content-edge X — so `left` is the line's start X
     * within the parent's box, and `right` is the line's max-end X.
     *
     * Without floats this is just `[0, $availableWidth]`. With a left
     * float overlapping the line, `left` rises; with a right float,
     * `right` falls.
     *
     * Phase-1 simplification: samples at the line's top edge only.
     * Browsers conceptually sample across the full line range and take
     * the most-constrained bounds.
     *
     * @return array{left: float, right: float}
     */
    private function lineBounds(Box $parent, float $availableWidth, LayoutContext $context, float $relY): array
    {
        $floatCtx = $context->floatContext;
        if ($floatCtx === null) {
            return ['left' => 0.0, 'right' => $availableWidth];
        }
        $parentX = $parent->geometry->x;
        $parentY = $parent->geometry->y;
        $absY = $parentY + $relY;
        $absLeft = $floatCtx->leftEdgeAt($absY, $parentX);
        $absRight = $floatCtx->rightEdgeAt($absY, $parentX + $availableWidth);
        return [
            'left' => max(0.0, $absLeft - $parentX),
            'right' => max(0.0, $absRight - $parentX),
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
        $ellipsis = $this->shaper->shapeRun("\u{2026}", $shapingCtx);
        if ($ellipsis->glyphs === []) {
            return $lines;
        }
        if ($letterSpacing !== 0.0) {
            $ellipsis = $this->applyLetterSpacing($ellipsis, $letterSpacing);
        }
        $ellipsisWidth = $ellipsis->totalAdvance;

        $out = [];
        foreach ($lines as $line) {
            if ($line->totalWidth() <= $availableWidth) {
                $out[] = $line;
                continue;
            }
            // Drop fragments from the end until the remaining content +
            // ellipsis fits. Phase-1 truncates at fragment boundaries —
            // mid-fragment truncation lands with a per-glyph cut later.
            $fragments = $line->fragments;
            $cutoff = $availableWidth - $ellipsisWidth;
            while ($fragments !== []) {
                $last = $fragments[array_key_last($fragments)];
                if ($last->x + $last->width <= $cutoff) {
                    break;
                }
                array_pop($fragments);
            }
            if ($fragments === []) {
                // Nothing fits before the ellipsis; emit just the ellipsis
                // at x = 0 so the user sees something.
                $fragments[] = new InlineFragment(0.0, $ellipsisWidth, $ellipsis);
            } else {
                $last = $fragments[array_key_last($fragments)];
                $tail = $last->x + $last->width;
                $fragments[] = new InlineFragment($tail, $ellipsisWidth, $ellipsis);
            }
            $out[] = new LineBox($line->y, $line->height, $fragments);
        }
        return $out;
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
        if ($align === 'left' || $align === 'start') {
            if ($alignLast === 'left' || $alignLast === 'start' || $alignLast === 'auto') {
                return $lines;
            }
        }
        $count = count($lines);
        $out = [];
        foreach ($lines as $i => $line) {
            $used = $line->totalWidth();
            $slack = $availableWidth - $used;
            if ($slack <= 0.0) {
                $out[] = $line;
                continue;
            }
            $isLast = $i === $count - 1;
            $effective = $isLast ? $alignLast : $align;
            $newFragments = match ($effective) {
                'center' => $this->shiftFragments($line->fragments, $slack / 2.0),
                'right', 'end' => $this->shiftFragments($line->fragments, $slack),
                'justify' => $this->justifyFragments($line->fragments, $slack),
                default => $line->fragments,
            };
            $out[] = new LineBox($line->y, $line->height, $newFragments);
        }
        return $out;
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
            // text-align: justify → last line is start-aligned per spec.
            return $align === 'justify' ? 'start' : $align;
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
        return match (strtolower($value->name)) {
            'uppercase' => mb_strtoupper($text, 'UTF-8'),
            'lowercase' => mb_strtolower($text, 'UTF-8'),
            'capitalize' => $this->capitalizeWords($text),
            default => $text,
        };
    }

    private function capitalizeWords(string $text): string
    {
        // Split on whitespace runs, capitalize the first codepoint of each
        // non-empty word, and rejoin with the original separators.
        $parts = preg_split('/(\s+)/u', $text, -1, PREG_SPLIT_DELIM_CAPTURE);
        if ($parts === false) {
            return $text;
        }
        $out = '';
        foreach ($parts as $part) {
            if ($part === '' || preg_match('/^\s+$/u', $part) === 1) {
                $out .= $part;
                continue;
            }
            $first = mb_substr($part, 0, 1, 'UTF-8');
            $rest = mb_substr($part, 1, null, 'UTF-8');
            $out .= mb_strtoupper($first, 'UTF-8') . $rest;
        }
        return $out;
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
     * @param list<InlineFragment> $fragments
     * @return list<InlineFragment>
     */
    private function shiftFragments(array $fragments, float $dx): array
    {
        $out = [];
        foreach ($fragments as $f) {
            $out[] = new InlineFragment($f->x + $dx, $f->width, $f->shapedRun, $f->baselineShift, $f->href, $f->isBold, $f->isItalic, $f->decorationLines, $f->textColor, $f->backgroundColor, $f->linkTitle, $f->decorationColor);
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
        $gaps = max(0, count($fragments) - 1);
        if ($gaps === 0) {
            return $fragments;
        }
        $delta = $slack / $gaps;
        $out = [];
        foreach ($fragments as $i => $f) {
            $out[] = new InlineFragment($f->x + $i * $delta, $f->width, $f->shapedRun, $f->baselineShift, $f->href, $f->isBold, $f->isItalic, $f->decorationLines, $f->textColor, $f->backgroundColor, $f->linkTitle, $f->decorationColor);
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
     * @return list<array{shapedRun: ShapedRun, isWhitespace: bool, kind: LineBreakKind}>
     */
    private function collectTokens(
        Box $parent,
        ShapingContext $shapingCtx,
        bool $collapseInternal,
        float $letterSpacing,
        float $wordSpacing,
        float $baselineShift,
        ?string $href,
        bool $isBold,
        bool $isItalic,
        array $decorationLines,
        ?\Phpdftk\Css\Value\Color $textColor,
        ?\Phpdftk\Css\Value\Color $backgroundColor,
        ?string $linkTitle,
        ?\Phpdftk\Css\Value\Color $decorationColor,
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
                $href,
                $isBold,
                $isItalic,
                $decorationLines,
                $textColor,
                $backgroundColor,
                $linkTitle,
                $decorationColor,
            );
        }
        return $out;
    }

    /**
     * @param list<array{shapedRun: ShapedRun, isWhitespace: bool, kind: LineBreakKind}> $tokens
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
        ?string $href,
        bool $isBold,
        bool $isItalic,
        array $decorationLines,
        ?\Phpdftk\Css\Value\Color $textColor,
        ?\Phpdftk\Css\Value\Color $backgroundColor,
        ?string $linkTitle,
        ?\Phpdftk\Css\Value\Color $decorationColor,
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
            foreach ($this->tokeniseText($text, $shapingCtx, $letterSpacing, $wordSpacing, $breakAll) as $token) {
                $token['baselineShift'] = $baselineShift;
                $token['href'] = $href;
                $token['isBold'] = $isBold;
                $token['isItalic'] = $isItalic;
                $token['decorationLines'] = $decorationLines;
                $token['textColor'] = $textColor;
                $token['backgroundColor'] = $backgroundColor;
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
                'kind' => LineBreakKind::Mandatory,
            ];
            return;
        }
        if ($box instanceof AtomicInlineBox) {
            // Treat as a single non-wrapping token with its computed width.
            $widthValue = $box->style->get('width');
            $width = $widthValue instanceof Length ? $widthValue->value : 0.0;
            $tokens[] = [
                'shapedRun' => new ShapedRun(
                    $shapingCtx->font,
                    $shapingCtx->fontSizePt,
                    $shapingCtx->direction,
                    [],
                    $width,
                ),
                'isWhitespace' => false,
                'kind' => LineBreakKind::Allowed,
                'baselineShift' => $baselineShift,
                'href' => $href,
                'isBold' => $isBold,
                'isItalic' => $isItalic,
                'decorationLines' => $decorationLines,
                'textColor' => $textColor,
                'backgroundColor' => $backgroundColor,
                'linkTitle' => $linkTitle,
                'decorationColor' => $decorationColor,
                'atomicBox' => $box,
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
            // Mixed-size inline runs: if this inline carries a different
            // computed `font-size` than the active shaping context, build
            // a per-subtree context so descendants shape at the right size.
            // Same for `font-family` — when an inline names a font that's
            // registered in the FontResolver, switch the shaping font.
            $childCtx = $shapingCtx;
            $boxFontSize = $this->boxFontSize($box) ?? $shapingCtx->fontSizePt;
            $boxFont = $boxMatch['font'] ?? $shapingCtx->font;
            $fontSizeChanged = abs($boxFontSize - $shapingCtx->fontSizePt) > 0.001;
            $fontChanged = $boxFont !== $shapingCtx->font;
            if ($fontSizeChanged || $fontChanged) {
                $childCtx = new ShapingContext($boxFont, $boxFontSize);
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
                    $childHref,
                    $childBold,
                    $childItalic,
                    $childDeco,
                    $childColor,
                    $childBg,
                    $childTitle,
                    $childDecoColor,
                );
            }
        }
    }

    /**
     * Per CSS Inline 3 §3, the line box's used height is the maximum of the
     * inline heights it contains. Use the parent's resolved line-height as
     * the baseline, then grow if a fragment carries a larger font.
     *
     * @param list<InlineFragment> $fragments
     */
    private function lineHeightFor(array $fragments, float $parentLineHeight): float
    {
        $maxFontSize = 0.0;
        foreach ($fragments as $f) {
            if ($f->shapedRun->fontSizePt > $maxFontSize) {
                $maxFontSize = $f->shapedRun->fontSizePt;
            }
        }
        return max($parentLineHeight, $maxFontSize * 1.2);
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
     * @return array{font: ?\Phpdftk\FontParser\OpenTypeData, isBold: bool, isItalic: bool}
     */
    private function resolveBoxFont(Box $box, ?\Phpdftk\FontParser\OpenTypeData $fallback): array
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
        $isBold = $requestBold && ($match === null || !$match->matchesWeight);
        $isItalic = $requestItalic && ($match === null || !$match->matchesStyle);
        return ['font' => $font, 'isBold' => $isBold, 'isItalic' => $isItalic];
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
     * CSS Text 3 §5: `word-break: break-all` (and `overflow-wrap:
     * anywhere`) allow line breaks between every two codepoints.
     */
    private function isBreakAll(Box $box): bool
    {
        $wb = $box->style->get('word-break');
        if ($wb instanceof \Phpdftk\Css\Value\Keyword && strtolower($wb->name) === 'break-all') {
            return true;
        }
        $ow = $box->style->get('overflow-wrap');
        if ($ow instanceof \Phpdftk\Css\Value\Keyword && strtolower($ow->name) === 'anywhere') {
            return true;
        }
        return false;
    }

    /**
     * CSS Inline 3 §4.5 `vertical-align`: Phase-1 honours the `sub` and
     * `super` keywords, lifting / lowering the fragment's baseline by a
     * font-size-relative amount. Browser defaults: `super` ≈ +0.5em lift,
     * `sub` ≈ +0.2em drop. Returns the offset in layout-Y space (negative
     * lifts, positive drops). All other values (baseline / Length /
     * Percentage / top / middle / bottom / text-top / text-bottom) fall
     * through to 0 for now — full vertical-align lands with mixed-size
     * inline runs.
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
     * Tokenise plain text at UAX #14 break opportunities, shaping each
     * resulting segment. Each segment is one token. Whitespace segments
     * are tagged so the line-fitter can collapse them at line edges. When
     * `$letterSpacing` is non-zero, every shaped glyph's advance is bumped
     * by that amount per CSS Text 3 §10 — the painter picks the difference
     * up automatically via its TJ-kerning path.
     *
     * @return list<array{shapedRun: ShapedRun, isWhitespace: bool, kind: LineBreakKind}>
     */
    private function tokeniseText(
        string $text,
        ShapingContext $shapingCtx,
        float $letterSpacing,
        float $wordSpacing,
        bool $breakAll = false,
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
        }

        $out = [];
        foreach ($segments as $seg) {
            $isWs = preg_match('/^[ \t\n\r\f]+$/', $seg['text']) === 1;
            $shaped = $this->shaper->shapeRun($seg['text'], $shapingCtx);
            if ($letterSpacing !== 0.0 && $shaped->glyphs !== []) {
                $shaped = $this->applyLetterSpacing($shaped, $letterSpacing);
            }
            if ($wordSpacing !== 0.0 && $shaped->glyphs !== []) {
                // CSS Text 3 §9: `word-spacing` adds advance only at word-
                // separator glyphs (U+0020 / U+00A0 at MVP).
                $shaped = $this->applyWordSpacing($shaped, $seg['text'], $wordSpacing);
            }
            $out[] = [
                'shapedRun' => $shaped,
                'isWhitespace' => $isWs,
                'kind' => $seg['kind'],
            ];
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
}
