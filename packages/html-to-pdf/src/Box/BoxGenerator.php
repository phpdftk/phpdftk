<?php

declare(strict_types=1);

namespace Phpdftk\HtmlToPdf\Box;

use Phpdftk\Css\Cascade\Cascade;
use Phpdftk\Css\Cascade\CascadedValues;
use Phpdftk\Css\Sheet\Stylesheet;
use Phpdftk\Css\Value\Keyword;
use Phpdftk\Html\Dom\Document;
use Phpdftk\Html\Dom\Element;
use Phpdftk\Html\Dom\Text;

/**
 * Walks a parsed HTML document, runs the CSS cascade against each element,
 * and emits the box tree.
 *
 * Phase 1E.1 implements the common path of CSS Display 3 box generation:
 *  - `display: block` / `list-item` → {@see BlockBox}
 *  - `display: inline` → {@see InlineBox}
 *  - `display: inline-block` and replaced elements → {@see AtomicInlineBox}
 *  - `display: none` → element + subtree skipped
 *  - Text nodes inside any element → {@see TextBox}
 *  - Anonymous block wrapping per CSS Display 3 §3.4 when a block parent
 *    has mixed inline + block children
 *
 * Display values we don't yet generate for (table, flex, grid, ruby) fall
 * through to BlockBox as a sensible default — layout will reject them in a
 * dedicated message until those sub-phases ship.
 *
 * The flat-tree composition that Q11 calls for (slot distribution +
 * shadow-tree traversal) lives in 1E.2; this version walks the light DOM.
 */
final class BoxGenerator
{
    /**
     * Live CSS counter state during a single `generate()` walk. Keyed by
     * counter name, value is the current count. Reset per generate; not
     * shared across documents.
     *
     * @var array<string, int>
     */
    private array $counters = [];

    public function __construct(
        private readonly Cascade $cascade = new Cascade(),
        /**
         * Base directory for resolving local-file `<img src>` paths when
         * reading intrinsic image dimensions. Same posture as the
         * painter's `baseDir`: `null` disables local-file lookups (only
         * `data:` URLs supply natural sizes); a non-null value joins
         * relative paths and rejects any escape via `realpath()`.
         */
        private readonly ?string $baseDir = null,
    ) {}

    /**
     * Generate a box tree from a parsed HTML document + a list of
     * stylesheets in their cascade-origin order.
     *
     * @param list<Stylesheet> $sheets
     */
    public function generate(Document $document, array $sheets): ?Box
    {
        $root = $document->documentElement;
        if ($root === null) {
            return null;
        }
        $this->counters = [];
        return $this->buildElementBox($root, $sheets, null);
    }

    /** @param list<Stylesheet> $sheets */
    private function buildElementBox(
        Element $element,
        array $sheets,
        ?CascadedValues $parentValues,
    ): ?Box {
        $values = $this->cascade->computeFor($sheets, $element, $parentValues);
        $this->applyPresentationalAttributes($element, $values);
        $display = $this->displayKeyword($values);
        if ($display === 'none') {
            return null;
        }

        // CSS Generated Content 3 §2: apply counter-reset (set named
        // counters at this scope) then counter-increment (bump them) so
        // any `::before` content that reads `counter()` sees the post-
        // increment value at this element's position in document order.
        $this->applyCounterReset($values);
        $this->applyCounterIncrement($values);

        // HTML `<br>` produces a sentinel line-break box — a hard break
        // inside the parent inline formatting context that survives
        // whitespace collapsing under `white-space: normal`.
        if (strtolower($element->localName) === 'br') {
            return new LineBreakBox($element, $values);
        }
        // HTML 5 `<wbr>` — a soft-break opportunity. Lower to an
        // `InlineBox` carrying a zero-width-space TextBox so UAX #14 has
        // a wrap point even when surrounding text doesn't.
        if (strtolower($element->localName) === 'wbr') {
            $inline = new InlineBox($element, $values);
            $inline->addChild(new TextBox($element, $values, "\u{200B}"));
            return $inline;
        }

        // HTML 5 §4.8.3: `<img alt="...">` — until image painting lands,
        // emit the alt text as a synthetic InlineBox + TextBox so the
        // fallback content takes part in inline layout. Empty `alt=""`
        // is intentional "decorative image, hide from a11y" — leave as
        // the regular atomic inline.
        if (strtolower($element->localName) === 'img') {
            // HTML 5 §4.8.4.2 — when the `<img>` is wrapped in a
            // `<picture>`, walk the preceding `<source>` siblings to
            // pick the best one for the print medium. Phase-1 honours
            // a `media` attribute containing `print` or `all` (or an
            // absent media attribute, which means "all media").
            $this->applyPictureSourceOverride($element);
            $alt = $element->getAttribute('alt');
            if ($alt !== null && $alt !== '') {
                $inline = new InlineBox($element, $values);
                $inline->addChild(new TextBox($element, $values, $alt));
                return $inline;
            }
        }

        // HTML 5 §4.10.5.1: `<input type="text|email|search|...">` renders
        // its `value` attribute as static text for print output. PDF
        // AcroForm field generation is a Phase 2 task. Emit as an
        // `InlineBox` so the text child flows through inline layout.
        if (strtolower($element->localName) === 'input') {
            $type = strtolower($element->getAttribute('type') ?? 'text');
            $textTypes = ['text', 'email', 'search', 'tel', 'url', 'number', 'date', 'time', 'datetime-local'];
            if (in_array($type, $textTypes, true)) {
                $inline = new InlineBox($element, $values);
                $value = $element->getAttribute('value') ?? '';
                if ($value !== '') {
                    $inline->addChild(new TextBox($element, $values, $value));
                }
                return $inline;
            }
            // HTML 5 §4.10.5.1.18: button-type inputs render the
            // `value` as the button label. Phase 2 will paint them as
            // proper PDF widget annotations; for now we just emit the
            // label text inline.
            $buttonTypes = ['button', 'submit', 'reset'];
            if (in_array($type, $buttonTypes, true)) {
                $inline = new InlineBox($element, $values);
                $label = $element->getAttribute('value');
                if ($label === null || $label === '') {
                    // HTML 5 default labels when `value` is missing.
                    $label = match ($type) {
                        'submit' => 'Submit',
                        'reset' => 'Reset',
                        default => '',
                    };
                }
                if ($label !== '') {
                    $inline->addChild(new TextBox($element, $values, $label));
                }
                return $inline;
            }
            // Checkbox / radio — render an ASCII visual indicator so
            // form-print output stays informative without depending on
            // ☐/☑ glyphs in the user's font.
            if ($type === 'checkbox' || $type === 'radio') {
                $checked = $element->getAttribute('checked') !== null;
                $marker = $type === 'checkbox'
                    ? ($checked ? '[x] ' : '[ ] ')
                    : ($checked ? '(o) ' : '( ) ');
                $inline = new InlineBox($element, $values);
                $inline->addChild(new TextBox($element, $values, $marker));
                return $inline;
            }
        }

        // HTML 5 §4.5.27: `<wbr>` (Word Break Opportunity) is a void
        // inline element that just marks a permissible line break.
        // Emit a U+200B (zero-width space) text child — it has zero
        // advance width but the line breaker recognises it as a break
        // opportunity, so a long unbroken token wrapping a `<wbr>`
        // can split at that point.
        if (strtolower($element->localName) === 'wbr') {
            $inline = new InlineBox($element, $values);
            $inline->addChild(new TextBox($element, $values, "\u{200B}"));
            return $inline;
        }

        // HTML 5 §4.10.7: `<select>` renders only its currently-selected
        // `<option>` in static print output (no dropdown widget).
        //  - Single-select (default): one option, the first one with
        //    `selected` (else the first option).
        //  - `<select multiple>`: every option with `selected` (else
        //    the empty selection), each on its own line.
        // `<optgroup label="...">` labels the contained options with
        // an inline-level "label: " prefix so the print form keeps
        // the grouping visible.
        if (strtolower($element->localName) === 'select') {
            $isMultiple = $element->getAttribute('multiple') !== null;
            /** @var list<array{label: ?string, option: Element}> $renderedOptions */
            $renderedOptions = $this->collectSelectOptions($element, $isMultiple);
            $inline = new InlineBox($element, $values);
            foreach ($renderedOptions as $i => $entry) {
                if ($i > 0) {
                    // Separate multi-select entries with a newline so
                    // they stack across lines instead of running on.
                    $inline->addChild(new TextBox($element, $values, "\n"));
                }
                if ($entry['label'] !== null) {
                    $inline->addChild(new TextBox($element, $values, $entry['label'] . ': '));
                }
                $text = $entry['option']->textContent();
                if ($text !== '') {
                    $inline->addChild(new TextBox($element, $values, $text));
                }
            }
            return $inline;
        }

        $box = $this->makeBox($element, $values, $display);

        // Walk children, building child boxes. Text nodes become TextBoxes.
        // `::before` is generated content prepended to the element's own
        // children; `::after` is appended. Both are inline boxes carrying a
        // synthetic TextBox of the `content` string. Phase-1 supports
        // `content: <string>` only — `attr()`, `counter()`, `open-quote` /
        // `close-quote`, etc. fall through to the `normal` initial.
        $rawChildren = [];
        $before = $this->makePseudoBox($element, $sheets, $values, 'before');
        if ($before !== null) {
            $rawChildren[] = $before;
        }
        for ($n = $element->firstChild; $n !== null; $n = $n->nextSibling) {
            if ($n instanceof Element) {
                $child = $this->buildElementBox($n, $sheets, $values);
                if ($child !== null) {
                    $rawChildren[] = $child;
                }
            } elseif ($n instanceof Text) {
                if ($n->data === '') {
                    continue;
                }
                $rawChildren[] = new TextBox($element, $values, $n->data);
            }
            // Comments and other node types are dropped.
        }
        $after = $this->makePseudoBox($element, $sheets, $values, 'after');
        if ($after !== null) {
            $rawChildren[] = $after;
        }

        // Anonymous-block wrapping per CSS Display 3 §3.4: only inside
        // block-context parents whose children mix block + inline.
        $needsAnonymous = $box instanceof BlockBox && $this->mixesBlockAndInline($rawChildren);
        if (!$needsAnonymous) {
            foreach ($rawChildren as $child) {
                $box->addChild($child);
            }
            return $box;
        }

        // Run through children; group contiguous inline-ish children under
        // an AnonymousBlockBox sharing the parent's style.
        $inlineGroup = [];
        foreach ($rawChildren as $child) {
            if ($this->isInlineLevel($child)) {
                $inlineGroup[] = $child;
                continue;
            }
            $this->flushInlineGroup($box, $values, $inlineGroup);
            $inlineGroup = [];
            $box->addChild($child);
        }
        $this->flushInlineGroup($box, $values, $inlineGroup);
        return $box;
    }

    /**
     * HTML 5 §4.8.4.2 — when an `<img>` is the fallback inside a
     * `<picture>`, the browser walks the `<source>` siblings and
     * picks the first one whose `media` attribute matches. For
     * print rendering: pick the first `<source>` with
     * `media="print"` (or `media="all"` or no media attribute) and
     * use the first URL of its `srcset` as the effective `src`.
     *
     * Mutates the element's `src` attribute in place — feels
     * intrusive but means the existing painter code that reads
     * `$element->getAttribute('src')` Just Works without any
     * extra plumbing through the box tree.
     */
    private function applyPictureSourceOverride(Element $img): void
    {
        $parent = $img->parentNode;
        if (!($parent instanceof Element)
            || strtolower($parent->localName) !== 'picture'
        ) {
            return;
        }
        foreach ($parent->children() as $sibling) {
            if ($sibling === $img) {
                continue;
            }
            if (strtolower($sibling->localName) !== 'source') {
                continue;
            }
            $media = $sibling->getAttribute('media');
            if ($media !== null && $media !== '') {
                $lower = strtolower(trim($media));
                if ($lower !== 'all' && !str_contains($lower, 'print')) {
                    continue;
                }
            }
            // HTML 5 §4.8.4.2.4 — `<source type="image/...">` lets the
            // author flag a format hint. Skip a source whose declared
            // MIME isn't a format the painter can decode (currently
            // PNG + JPEG via the image-metadata pipeline).
            $type = $sibling->getAttribute('type');
            if ($type !== null && $type !== '' && !$this->sourceTypeAcceptable($type)) {
                continue;
            }
            $srcset = $sibling->getAttribute('srcset');
            if ($srcset === null || trim($srcset) === '') {
                continue;
            }
            $url = $this->firstSrcsetUrl($srcset);
            if ($url !== null && $url !== '') {
                $img->setAttribute('src', $url);
                return;
            }
        }
    }

    /**
     * Return true when the `<source type="...">` MIME indicates a
     * format the painter can render. Print PDF supports raster PNG
     * and JPEG today; anything else (AVIF, WebP, HEIF, SVG-as-image)
     * gets skipped so the next `<source>` or the `<img>` fallback
     * wins.
     */
    private function sourceTypeAcceptable(string $type): bool
    {
        $lower = strtolower(trim($type));
        $supported = ['image/png', 'image/jpeg', 'image/jpg'];
        return in_array($lower, $supported, true);
    }

    /**
     * Pick the best `srcset` candidate for print. HTML 5 §4.8.4.2.4
     * syntax: comma-separated `url [descriptor]` pairs where the
     * descriptor is `Nx` (density) or `Nw` (width). When no
     * descriptor is given, defaults to `1x`.
     *
     * Print rendering targets high resolution (300+ DPI) so the
     * algorithm picks the candidate with the highest density. Width
     * descriptors are converted to "approximate density" using a
     * reference width of 100 (so a 200w candidate counts as 2x,
     * 400w as 4x). Bare candidates count as 1x. On ties the first
     * declared candidate wins.
     */
    private function firstSrcsetUrl(string $srcset): ?string
    {
        $candidates = $this->parseSrcsetCandidates($srcset);
        if ($candidates === []) {
            return null;
        }
        $best = null;
        $bestDensity = -INF;
        foreach ($candidates as $cand) {
            if ($cand['density'] > $bestDensity) {
                $best = $cand['url'];
                $bestDensity = $cand['density'];
            }
        }
        return $best;
    }

    /**
     * Parse a `srcset` value into a list of `{url, density}` candidates.
     * Descriptor parsing:
     *  - `Nx` → density = N
     *  - `Nw` → density ≈ N / 100 (matches typical author intent)
     *  - missing → density = 1
     *  - unrecognised descriptor → candidate is dropped
     *
     * @return list<array{url: string, density: float}>
     */
    private function parseSrcsetCandidates(string $srcset): array
    {
        $out = [];
        foreach (explode(',', $srcset) as $part) {
            $part = trim($part);
            if ($part === '') {
                continue;
            }
            $tokens = preg_split('/\s+/', $part, 2) ?: [];
            $url = $tokens[0] ?? '';
            if ($url === '') {
                continue;
            }
            $descriptor = trim($tokens[1] ?? '');
            if ($descriptor === '') {
                $out[] = ['url' => $url, 'density' => 1.0];
                continue;
            }
            if (preg_match('/^([0-9]*\.?[0-9]+)([xw])$/i', $descriptor, $m) !== 1) {
                continue;
            }
            $value = (float) $m[1];
            $unit = strtolower($m[2]);
            $density = $unit === 'x' ? $value : $value / 100.0;
            $out[] = ['url' => $url, 'density' => $density];
        }
        return $out;
    }

    /**
     * Build a pseudo-element box (`::before` / `::after`) for an element
     * when the cascade produces a non-`none` / non-`normal` `content`
     * value. Returns null when no rule targets the pseudo, or when the
     * content keyword indicates no generated box.
     *
     * @param list<Stylesheet> $sheets
     */
    private function makePseudoBox(
        Element $element,
        array $sheets,
        CascadedValues $hostValues,
        string $pseudoName,
    ): ?Box {
        $pseudoValues = $this->cascade->computeFor($sheets, $element, $hostValues, $pseudoName);
        $content = $pseudoValues->get('content');
        $text = $this->resolvePseudoContent($content, $element, $pseudoValues);
        if ($text === null) {
            return null;
        }
        $display = $this->displayKeyword($pseudoValues);
        // Pseudo-elements default to `inline` when no `display` rule fires.
        $pseudo = $this->makeBox($element, $pseudoValues, $display);
        if ($text !== '') {
            $pseudo->addChild(new TextBox($element, $pseudoValues, $text));
        }
        return $pseudo;
    }

    /**
     * Walk a `<select>`'s children (and one level of `<optgroup>`)
     * collecting the `<option>` elements to render. For single-select,
     * returns at most one entry — the first option with `selected`
     * else the first option overall. For `<select multiple>`, returns
     * every option carrying `selected`. Each entry pairs the option
     * with the optgroup label that contains it (or null).
     *
     * @return list<array{label: ?string, option: Element}>
     */
    private function collectSelectOptions(Element $select, bool $multiple): array
    {
        /** @var list<array{label: ?string, option: Element}> $available */
        $available = [];
        /** @var list<array{label: ?string, option: Element}> $selectedEntries */
        $selectedEntries = [];
        foreach ($select->children() as $child) {
            if (!$child instanceof Element) {
                continue;
            }
            $tag = strtolower($child->localName);
            if ($tag === 'option') {
                $entry = ['label' => null, 'option' => $child];
                $available[] = $entry;
                if ($child->getAttribute('selected') !== null) {
                    $selectedEntries[] = $entry;
                }
            } elseif ($tag === 'optgroup') {
                $label = $child->getAttribute('label');
                foreach ($child->children() as $grand) {
                    if (!$grand instanceof Element) {
                        continue;
                    }
                    if (strtolower($grand->localName) !== 'option') {
                        continue;
                    }
                    $entry = ['label' => $label, 'option' => $grand];
                    $available[] = $entry;
                    if ($grand->getAttribute('selected') !== null) {
                        $selectedEntries[] = $entry;
                    }
                }
            }
        }
        if ($multiple) {
            return $selectedEntries;
        }
        if ($selectedEntries !== []) {
            return [$selectedEntries[0]];
        }
        if ($available !== []) {
            return [$available[0]];
        }
        return [];
    }

    /**
     * Resolve the `content` value to a plain string. Returns null when the
     * pseudo-element should produce no box (`none` / `normal` / unsupported
     * generators like `counter()` / `<image>` — Phase 2). Returns the empty
     * string when `content` is explicitly an empty string (the pseudo box
     * still generates).
     *
     * Supports `<string>`, `attr(name)`, and any space-joined list of those.
     */
    private function resolvePseudoContent(?\Phpdftk\Css\Value\Value $value, Element $host, CascadedValues $values): ?string
    {
        if ($value === null) {
            return null;
        }
        if ($value instanceof Keyword) {
            $name = strtolower($value->name);
            if ($name === 'none' || $name === 'normal') {
                return null;
            }
            // Other keywords (open-quote / close-quote / no-open-quote /
            // no-close-quote / etc.) fall through to `contentItemAsString`
            // which produces the right glyph.
        }
        $item = $this->contentItemAsString($value, $host, $values);
        if ($item !== null) {
            return $item;
        }
        if ($value instanceof \Phpdftk\Css\Value\ValueList) {
            $out = '';
            foreach ($value->values as $v) {
                $piece = $this->contentItemAsString($v, $host, $values);
                if ($piece === null) {
                    // Unsupported component (counter/url/etc.) — bail.
                    return null;
                }
                $out .= $piece;
            }
            return $out;
        }
        return null;
    }

    /**
     * Translate a single content-list item into a plain string, returning
     * null when the item isn't a Phase-1 supported producer. Handles
     * `<string>`, `attr(name)`, and the `open-quote` / `close-quote`
     * keywords. Phase-1 emits an ASCII double quote for both — full
     * `quotes` property + nesting depth tracking lands in a follow-up.
     */
    /**
     * Resolve CSS Generated Content 3 §3.1 `quotes` to the
     * `[openQuote, closeQuote]` pair for `open-quote` / `close-quote`
     * content keywords. `auto` (initial value) defers to the
     * typographic default U+201C / U+201D ("smart quotes"). `none`
     * suppresses quote glyphs entirely — both `open-quote` and
     * `close-quote` evaluate to the empty string in that case.
     * Explicit string lists are paired open/close; nested-depth
     * tracking through ancestor `<q>` chains picks the pair at the
     * current depth (clamping to the last pair when nesting exceeds
     * the list).
     *
     * @return array{0:string, 1:string}|null  Null means `quotes: none`.
     */
    private function resolveQuotePair(CascadedValues $values, int $depth): ?array
    {
        $value = $values->get('quotes');
        if ($value instanceof Keyword && strtolower($value->name) === 'none') {
            return null;
        }
        if ($value instanceof \Phpdftk\Css\Value\ValueList) {
            $pairs = [];
            $strings = [];
            foreach ($value->values as $v) {
                if ($v instanceof \Phpdftk\Css\Value\StringValue) {
                    $strings[] = $v->value;
                    if (count($strings) === 2) {
                        $pairs[] = [$strings[0], $strings[1]];
                        $strings = [];
                    }
                }
            }
            if ($pairs !== []) {
                $idx = max(0, min($depth, count($pairs) - 1));
                return $pairs[$idx];
            }
        }
        // U+201C LEFT DOUBLE QUOTATION MARK + U+201D RIGHT DOUBLE
        // QUOTATION MARK — the typographic default for English. Other
        // locales (German „..." / French «...») are Phase 2 once the
        // cascade tracks `:lang()`-driven UA stylesheets.
        return ["\u{201C}", "\u{201D}"];
    }

    /**
     * Walk up the host element's `<q>` ancestor chain to compute the
     * current quote nesting depth. Each enclosing `<q>` bumps the
     * depth by one. The depth is what indexes into the `quotes`
     * property's pair list.
     */
    private function currentQuoteDepth(Element $host): int
    {
        $depth = 0;
        $node = $host->parentNode;
        while ($node !== null) {
            if ($node instanceof Element && strtolower($node->localName) === 'q') {
                $depth++;
            }
            $node = $node->parentNode;
        }
        return $depth;
    }

    private function contentItemAsString(\Phpdftk\Css\Value\Value $value, Element $host, CascadedValues $values): ?string
    {
        if ($value instanceof \Phpdftk\Css\Value\StringValue) {
            return $value->value;
        }
        if ($value instanceof Keyword) {
            $kw = strtolower($value->name);
            if ($kw === 'open-quote' || $kw === 'close-quote') {
                $depth = $this->currentQuoteDepth($host);
                $pair = $this->resolveQuotePair($values, $depth);
                if ($pair === null) {
                    return '';
                }
                return $kw === 'open-quote' ? $pair[0] : $pair[1];
            }
            return match ($kw) {
                'no-open-quote', 'no-close-quote' => '',
                default => null,
            };
        }
        if ($value instanceof \Phpdftk\Css\Value\CssFunction
            && strtolower($value->name) === 'attr'
            && $value->arguments !== []
        ) {
            $arg = $value->arguments[0];
            $name = null;
            if ($arg instanceof Keyword) {
                $name = $arg->name;
            } elseif ($arg instanceof \Phpdftk\Css\Value\StringValue) {
                $name = $arg->value;
            }
            if ($name !== null && $name !== '') {
                $attrValue = $host->getAttribute($name);
                return $attrValue ?? '';
            }
        }
        if ($value instanceof \Phpdftk\Css\Value\CssFunction
            && strtolower($value->name) === 'counter'
            && $value->arguments !== []
        ) {
            $nameArg = $value->arguments[0];
            if ($nameArg instanceof Keyword) {
                $count = $this->counters[$nameArg->name] ?? 0;
                $style = isset($value->arguments[1]) && $value->arguments[1] instanceof Keyword
                    ? strtolower($value->arguments[1]->name)
                    : 'decimal';
                return $this->formatCounter($count, $style);
            }
        }
        // CSS Generated Content 3 §2.3 — `counters(name, separator, style?)`
        // formats the nested chain of counters with `name` joined by
        // `separator`. The cascade doesn't track per-scope counter
        // stacks yet, so this falls back to formatting the single
        // current value (matching the common authored use case
        // `counters(foo, ".")` on a non-nested counter).
        if ($value instanceof \Phpdftk\Css\Value\CssFunction
            && strtolower($value->name) === 'counters'
            && count($value->arguments) >= 2
        ) {
            $nameArg = $value->arguments[0];
            $sepArg = $value->arguments[1];
            if (!$nameArg instanceof Keyword || !$sepArg instanceof \Phpdftk\Css\Value\StringValue) {
                return null;
            }
            $count = $this->counters[$nameArg->name] ?? 0;
            $style = isset($value->arguments[2]) && $value->arguments[2] instanceof Keyword
                ? strtolower($value->arguments[2]->name)
                : 'decimal';
            // Single-scope fallback: emit the current counter value
            // (no separator-joining since there's no nested chain).
            // The separator is retained for grammar compatibility.
            return $this->formatCounter($count, $style);
        }
        // `content: url(...)` is a replaced-element generator. For
        // Phase-2 we accept the syntax but emit no text — the pseudo
        // box still generates so author CSS targeting it (e.g.
        // `::before { content: url(badge.png); margin-right: 4px }`)
        // doesn't get silently dropped. Image insertion through
        // generated content is a follow-up requiring XObject hooks
        // to thread through pseudo-element generation.
        if ($value instanceof \Phpdftk\Css\Value\Url) {
            return '';
        }
        return null;
    }

    /**
     * Apply `counter-reset: <name> [<int>]?` declarations to {@see counters}.
     * Multiple name/value pairs in a list are supported.
     */
    private function applyCounterReset(CascadedValues $values): void
    {
        $value = $values->get('counter-reset');
        $this->forEachCounterPair($value, function (string $name, int $defaultOrSpecified): void {
            $this->counters[$name] = $defaultOrSpecified;
        }, defaultValue: 0);
    }

    /**
     * Apply `counter-increment: <name> [<int>]?` declarations — bumps the
     * named counter by the specified delta (default +1).
     */
    private function applyCounterIncrement(CascadedValues $values): void
    {
        $value = $values->get('counter-increment');
        $this->forEachCounterPair($value, function (string $name, int $delta): void {
            $this->counters[$name] = ($this->counters[$name] ?? 0) + $delta;
        }, defaultValue: 1);
    }

    /**
     * Walk a `counter-reset` / `counter-increment` value and invoke the
     * callback for each `<name> [<int>]?` pair encountered. Handles single
     * Keyword, single Keyword + Integer, and Space-separated `ValueList`
     * shapes. Skips when the value is the `none` keyword.
     *
     * @param \Closure(string, int): void $cb
     */
    private function forEachCounterPair(?\Phpdftk\Css\Value\Value $value, \Closure $cb, int $defaultValue): void
    {
        if ($value === null
            || ($value instanceof Keyword && strtolower($value->name) === 'none')
        ) {
            return;
        }
        if ($value instanceof Keyword) {
            $cb($value->name, $defaultValue);
            return;
        }
        if ($value instanceof \Phpdftk\Css\Value\ValueList) {
            $items = $value->values;
            $i = 0;
            $n = count($items);
            while ($i < $n) {
                if (!($items[$i] instanceof Keyword)) {
                    $i++;
                    continue;
                }
                $name = $items[$i]->name;
                if ($i + 1 < $n && $items[$i + 1] instanceof \Phpdftk\Css\Value\Integer) {
                    $cb($name, $items[$i + 1]->value);
                    $i += 2;
                } else {
                    $cb($name, $defaultValue);
                    $i++;
                }
            }
        }
    }

    /**
     * Format `$count` per `$style`: `decimal` / `decimal-leading-zero`,
     * `lower-alpha` / `upper-alpha` / `lower-latin` / `upper-latin`,
     * `lower-roman` / `upper-roman`. Other style names fall back to decimal.
     */
    private function formatCounter(int $count, string $style): string
    {
        return match ($style) {
            'decimal-leading-zero' => sprintf('%02d', $count),
            'lower-alpha', 'lower-latin' => $this->bijectiveBase26($count, lower: true),
            'upper-alpha', 'upper-latin' => $this->bijectiveBase26($count, lower: false),
            'lower-roman' => strtolower($this->roman($count)),
            'upper-roman' => $this->roman($count),
            default => (string) $count,
        };
    }

    private function bijectiveBase26(int $n, bool $lower): string
    {
        if ($n <= 0) {
            return (string) $n;
        }
        $out = '';
        while ($n > 0) {
            $n--;
            $out = chr(($lower ? ord('a') : ord('A')) + ($n % 26)) . $out;
            $n = intdiv($n, 26);
        }
        return $out;
    }

    private function roman(int $n): string
    {
        if ($n < 1 || $n > 3999) {
            return (string) $n;
        }
        $map = [
            1000 => 'M', 900 => 'CM', 500 => 'D', 400 => 'CD',
            100 => 'C', 90 => 'XC', 50 => 'L', 40 => 'XL',
            10 => 'X', 9 => 'IX', 5 => 'V', 4 => 'IV', 1 => 'I',
        ];
        $out = '';
        foreach ($map as $v => $s) {
            while ($n >= $v) {
                $out .= $s;
                $n -= $v;
            }
        }
        return $out;
    }

    /** @param list<Box> $inlineGroup */
    private function flushInlineGroup(Box $parent, CascadedValues $values, array $inlineGroup): void
    {
        if ($inlineGroup === []) {
            return;
        }
        $anon = new AnonymousBlockBox(null, $values);
        foreach ($inlineGroup as $c) {
            $anon->addChild($c);
        }
        $parent->addChild($anon);
    }

    private function makeBox(Element $element, CascadedValues $values, string $display): Box
    {
        return match ($display) {
            'inline' => new InlineBox($element, $values),
            'inline-block', 'inline-table', 'inline-flex', 'inline-grid'
                => new AtomicInlineBox($element, $values),
            'table' => new TableBox($element, $values),
            'table-row' => new TableRowBox($element, $values),
            'table-cell' => new TableCellBox($element, $values),
            'flex' => new FlexBox($element, $values),
            'grid' => new GridBox($element, $values),
            default => new BlockBox($element, $values),
        };
    }

    private function displayKeyword(CascadedValues $values): string
    {
        $display = $values->get('display');
        if ($display instanceof Keyword) {
            return strtolower($display->name);
        }
        return 'inline';
    }

    /**
     * Pre-CSS HTML attributes that map to CSS properties — `<img width>`,
     * `<img height>`, `<font color>` etc. Per HTML 5 §15.3, these
     * "presentational attributes" map into the user-agent style sheet at
     * the lowest specificity. We apply them after the cascade so any
     * author CSS still wins, but they provide the size to layout for
     * elements that lack explicit `width` / `height` declarations.
     */
    private function applyPresentationalAttributes(Element $element, CascadedValues $values): void
    {
        $tag = strtolower($element->localName);
        if ($tag === 'img' || $tag === 'embed' || $tag === 'iframe' || $tag === 'video') {
            foreach (['width', 'height'] as $attr) {
                if ($values->has($attr) && !$this->isAutoLength($values->get($attr))) {
                    continue; // author CSS wins
                }
                $raw = $element->getAttribute($attr);
                if ($raw === null) {
                    continue;
                }
                $px = $this->parseHtmlLength($raw);
                if ($px !== null) {
                    $values->set($attr, new \Phpdftk\Css\Value\Length($px, \Phpdftk\Css\Value\LengthUnit::Px));
                }
            }
            // Intrinsic-dimension fallback for `<img src="data:image/...">`
            // when neither CSS nor HTML attributes provide width/height.
            // Decode the data URL once via ImageParser::parseString so layout
            // gets the natural pixel dimensions, then derive missing sides
            // from the aspect ratio when exactly one dimension is given
            // (CSS Images 3 §3.3 "used image dimensions").
            if ($tag === 'img') {
                $wValue = $values->get('width');
                $hValue = $values->get('height');
                $wUnset = !$values->has('width') || $this->isAutoLength($wValue);
                $hUnset = !$values->has('height') || $this->isAutoLength($hValue);
                if ($wUnset || $hUnset) {
                    $natural = $this->naturalImageSize($element->getAttribute('src'));
                    if ($natural !== null) {
                        [$nw, $nh] = $natural;
                        if ($wUnset && $hUnset) {
                            $values->set('width', new \Phpdftk\Css\Value\Length((float) $nw, \Phpdftk\Css\Value\LengthUnit::Px));
                            $values->set('height', new \Phpdftk\Css\Value\Length((float) $nh, \Phpdftk\Css\Value\LengthUnit::Px));
                        } elseif ($wUnset && $hValue instanceof \Phpdftk\Css\Value\Length && $nh > 0) {
                            $values->set(
                                'width',
                                new \Phpdftk\Css\Value\Length(
                                    $hValue->value * ($nw / $nh),
                                    \Phpdftk\Css\Value\LengthUnit::Px,
                                ),
                            );
                        } elseif ($hUnset && $wValue instanceof \Phpdftk\Css\Value\Length && $nw > 0) {
                            $values->set(
                                'height',
                                new \Phpdftk\Css\Value\Length(
                                    $wValue->value * ($nh / $nw),
                                    \Phpdftk\Css\Value\LengthUnit::Px,
                                ),
                            );
                        }
                    }
                }
            }
        }
        // HTML 5 §4.4.5.1: `<ol type="A">` / `"a"` / `"I"` / `"i"` / `"1"`
        // maps to a `list-style-type` keyword. `<ul type="..."` is the
        // older HTML 4 form; supported because real-world docs still use
        // it.
        if ($tag === 'ol' || $tag === 'ul') {
            $type = $element->getAttribute('type');
            if ($type !== null && $type !== '') {
                $keyword = match ($type) {
                    '1' => 'decimal',
                    'A' => 'upper-alpha',
                    'a' => 'lower-alpha',
                    'I' => 'upper-roman',
                    'i' => 'lower-roman',
                    'disc', 'circle', 'square' => $type,
                    default => null,
                };
                if ($keyword !== null) {
                    // Author CSS still wins: only apply when the cascade
                    // hasn't already set a non-default value.
                    $current = $values->get('list-style-type');
                    $defaulted = $current instanceof Keyword
                        && in_array(strtolower($current->name), ['disc', 'decimal'], true);
                    if (!$values->has('list-style-type') || $defaulted) {
                        $values->set('list-style-type', new Keyword($keyword));
                    }
                }
            }
        }
    }

    private function isAutoLength(?\Phpdftk\Css\Value\Value $v): bool
    {
        return $v instanceof Keyword && strtolower($v->name) === 'auto';
    }

    /**
     * Read the natural pixel dimensions for an `<img src>` value. Returns
     * `[width, height]` or null when the URL isn't a recognised Phase-1
     * variant, when local-file resolution fails security gates, or when
     * the underlying bytes don't parse as a supported image format.
     *
     * Supported sources:
     *   - `data:image/{png,jpeg};base64,...` (and the rfc2397 non-base64
     *     form) — bytes go straight to `ImageParser::parseString`.
     *   - relative or absolute filesystem paths — joined with `baseDir`,
     *     must resolve under it via `realpath()`. Stream-wrapper URLs
     *     (`http://`, `phar://`, etc.) are rejected.
     *
     * @return array{int, int}|null
     */
    private function naturalImageSize(?string $src): ?array
    {
        if ($src === null || $src === '') {
            return null;
        }
        if (str_starts_with($src, 'data:')) {
            if (preg_match('~^data:image/(png|jpeg|jpg);(base64,)?(.*)$~s', $src, $m) !== 1) {
                return null;
            }
            $payload = $m[2] === 'base64,'
                ? base64_decode($m[3], strict: true)
                : urldecode($m[3]);
            if ($payload === false || $payload === '') {
                return null;
            }
            try {
                $info = \Phpdftk\ImageMetadata\ImageParser::parseString($payload);
            } catch (\Throwable) {
                return null;
            }
            return [$info->width, $info->height];
        }
        $resolved = $this->resolveLocalImagePath($src);
        if ($resolved === null) {
            return null;
        }
        try {
            $info = \Phpdftk\ImageMetadata\ImageParser::parse($resolved);
        } catch (\Throwable) {
            return null;
        }
        return [$info->width, $info->height];
    }

    /**
     * Resolve an `<img src>` value to a real local-file path, or null
     * when the path can't be confirmed safe. Delegates to the unified
     * `Phpdftk\Filesystem\ResourceLoader` so the BoxGenerator and the
     * painter share one resolver — they must agree on what's loadable
     * (the BoxGenerator decides layout, the painter fetches the bytes).
     */
    private function resolveLocalImagePath(string $src): ?string
    {
        return (new \Phpdftk\Filesystem\ResourceLoader($this->baseDir))
            ->resolveLocalPath($src);
    }

    /**
     * HTML legacy `width` / `height` attributes: plain integer = pixels;
     * trailing `%` = percentage (not yet honoured here, returns null and
     * leaves the value at whatever the cascade said). Everything else is
     * rejected.
     */
    private function parseHtmlLength(string $raw): ?float
    {
        $raw = trim($raw);
        if ($raw === '') {
            return null;
        }
        if (preg_match('/^\d+(?:\.\d+)?$/', $raw) === 1) {
            return (float) $raw;
        }
        return null;
    }

    /** @param list<Box> $boxes */
    private function mixesBlockAndInline(array $boxes): bool
    {
        $hasBlock = false;
        $hasInline = false;
        foreach ($boxes as $b) {
            if ($this->isInlineLevel($b)) {
                $hasInline = true;
            } else {
                $hasBlock = true;
            }
            if ($hasBlock && $hasInline) {
                return true;
            }
        }
        return false;
    }

    private function isInlineLevel(Box $box): bool
    {
        return $box instanceof InlineBox
            || $box instanceof TextBox
            || $box instanceof AtomicInlineBox
            || $box instanceof LineBreakBox;
    }
}
