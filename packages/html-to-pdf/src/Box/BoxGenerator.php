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

    /**
     * CSS Generated Content for Paged Media 3 §5 — named-string
     * store populated as `string-set` declarations flow through
     * the document. Keyed by the string name (the first arg of
     * `string-set: <name> <value>`), value is the resolved string.
     * Page-margin painting reads this via {@see getNamedStrings}.
     *
     * @var array<string, string>
     */
    private array $namedStrings = [];

    /**
     * CSS Generated Content for Paged Media 3 §4 — running-element
     * store populated by `position: running(name)` declarations.
     * Keyed by the running name, value is the element's text
     * content captured at the point the element was visited.
     * Page-margin painting reads this via {@see getRunningElements}
     * to resolve `content: element(name)`.
     *
     * @var array<string, string>
     */
    private array $runningElements = [];

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
        /**
         * Optional broader sandbox the resolved path must remain
         * under (mirrors Painter / RendererOptions). Defaults to
         * `baseDir`.
         */
        private readonly ?string $sandboxRoot = null,
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
        $this->namedStrings = [];
        $this->runningElements = [];
        return $this->buildElementBox($root, $sheets, null);
    }

    /**
     * Snapshot of the named-string store accumulated during the
     * last {@see generate} run. Used by the page-margin painter
     * to resolve `content: string(name)` references in @page
     * margin boxes.
     *
     * @return array<string, string>
     */
    public function getNamedStrings(): array
    {
        return $this->namedStrings;
    }

    /**
     * Snapshot of the running-element store. Used by the page-
     * margin painter to resolve `content: element(name)` against
     * `position: running(name)` opt-outs in the document body.
     *
     * @return array<string, string>
     */
    public function getRunningElements(): array
    {
        return $this->runningElements;
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
        // CSS Grid 3 §11 — `display: grid-lanes` (masonry layout)
        // currently aliases to `display: grid` with `grid-auto-flow`
        // derived from which axis the author specified tracks for.
        // For a row-axis grid-lanes (only `grid-template-rows` set),
        // items flow column-major (auto-flow: column) and the
        // column tracks grow implicitly. Symmetric for column-axis.
        // The actual masonry algorithm (variable-height packing
        // across tracks) is a future enhancement; this aliasing
        // already lights up the empty-container / order /
        // basic-placement subset of the corpus.
        if ($display === 'grid-lanes' || $display === 'inline-grid-lanes') {
            $hasRowTracks = !$this->isInitialValue($values->get('grid-template-rows'));
            $hasColTracks = !$this->isInitialValue($values->get('grid-template-columns'));
            if ($hasRowTracks && !$hasColTracks) {
                $values->set('grid-auto-flow', new Keyword('column'));
            }
            $values->set('display', new Keyword($display === 'inline-grid-lanes' ? 'inline-grid' : 'grid'));
            $display = $display === 'inline-grid-lanes' ? 'inline-grid' : 'grid';
        }
        // CSS Writing Modes 3 §2 — `direction` doesn't apply to
        // certain internal-table display types (table-row-group /
        // -header-group / -footer-group / -row / -column /
        // -column-group). Browsers reset the value to the inherited
        // one from the parent rather than honouring an explicit
        // declaration on the row-group itself. Force the cascade back
        // to the parent's resolved direction (or `ltr` at the root)
        // so descendants don't pick up an invalid declaration via
        // inheritance.
        if (in_array($display, ['table-row-group', 'table-header-group', 'table-footer-group', 'table-row', 'table-column', 'table-column-group'], true)) {
            $parentDirection = $parentValues?->get('direction');
            if ($parentDirection instanceof Keyword) {
                $values->set('direction', $parentDirection);
            } else {
                $values->set('direction', new Keyword('ltr'));
            }
        }
        // CSS Display 3 §3.2.1 — `display: contents` on the root
        // element is "blockified": the value is treated as `block`
        // so the root still generates a box and its background /
        // borders still propagate to the canvas. The caller (the
        // top-level `generate()` entry) hits this for the document
        // root only; in-tree `display: contents` is handled by the
        // expansion in the child-collection loop below.
        if ($display === 'contents' && $parentValues === null) {
            $values->set('display', new Keyword('block'));
            $display = 'block';
        }
        // CSS 2.1 §9.7 + CSS Display §2.7 — out-of-flow elements
        // (position: absolute / fixed, float: left / right) are
        // "blockified": an inline-level computed `display` becomes
        // `block` so the box participates in the abs-pos / float
        // layout pipeline rather than the inline-flow line-box
        // path. This is what `<img position: absolute; left: 7.5px>`
        // needs to honour its corner anchors (#21, also unblocks
        // the SVG-embed positioning fixture in #143).
        //
        // Foreign content (root <math> and <svg>) is intentionally
        // excluded: those elements route through their own atomic-
        // inline painters (`paintInlineMath` / `paintInlineSvg`)
        // that already honour cascaded `position` / `left` / `top`
        // via `resolveInlineAbsoluteOrigin`. Blockifying them
        // would re-route through the generic block pipeline that
        // doesn't know how to delegate to those painters — and
        // doing so regresses `mathml/presentation-markup/spaces/
        // space-3` (which uses `<math style="position: absolute;
        // top: 0; left: 0">`).
        if (in_array($display, ['inline', 'inline-block', 'inline-flex', 'inline-grid', 'inline-table'], true)
            && $this->isOutOfFlow($values)
            && !$this->isForeignContentRoot($element)
        ) {
            $values->set('display', new Keyword('block'));
            $display = 'block';
        }
        // CSS Containment 2 §4 — `content-visibility: hidden`
        // suppresses box generation just like `display: none` for
        // static print. (`auto` is a runtime-visibility optimisation
        // with no print equivalent and is treated as `visible`.)
        $cv = $values->get('content-visibility');
        if ($cv instanceof Keyword && strtolower($cv->name) === 'hidden') {
            return null;
        }
        // CSS GCPM 3 §4 — `position: running(<name>)` opts the
        // element out of normal flow and into the running-element
        // store. No box is generated; the element's text content
        // becomes available to @page margin boxes via
        // `content: element(<name>)`.
        $runningName = $this->extractRunningPositionName($values);
        if ($runningName !== null) {
            $this->runningElements[$runningName] = $element->textContent();
            return null;
        }

        // CSS Generated Content 3 §2: apply counter-reset (creates +
        // sets), then counter-set (sets to a specific value WITHOUT
        // creating a new scope), then counter-increment (bumps) so any
        // `::before` content that reads `counter()` sees the post-
        // increment value at this element's position in document order.
        // counter-set's distinction from counter-reset is scope-related;
        // since BoxGenerator carries a single flat counter table for
        // print render rather than a per-scope stack, both reduce to the
        // same write here but the property is still honoured rather than
        // silently dropped.
        $this->applyCounterReset($values);
        $this->applyCounterSet($values);
        $this->applyCounterIncrement($values);
        $this->applyStringSet($element, $values);

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

        // HTML 5 §4.8.4.2 — when the `<img>` is wrapped in a
        // `<picture>`, walk the preceding `<source>` siblings to
        // pick the best one for the print medium. Phase-1 honours
        // a `media` attribute containing `print` or `all` (or an
        // absent media attribute, which means "all media").
        if (strtolower($element->localName) === 'img') {
            $this->applyPictureSourceOverride($element);
            // HTML 5 §4.8.3 — the alt-text fallback only kicks in when
            // the image *can't* be painted: missing src, an unloadable
            // source, or an unsupported format. When the painter can
            // resolve the src, render the image; otherwise hand the
            // alt text to inline layout as a synthetic TextBox so the
            // surrounding flow doesn't collapse to nothing.
            $alt = $element->getAttribute('alt');
            if ($alt !== null && $alt !== '' && !$this->imageIsLoadable($element)) {
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
            // HTML 5 §4.10.5.1.7 — `<input type=hidden>` is never
            // rendered, regardless of the `hidden` attribute on
            // its ancestors.
            if ($type === 'hidden') {
                return null;
            }
            $textTypes = ['text', 'email', 'search', 'tel', 'url', 'number', 'date', 'time', 'datetime-local'];
            if (in_array($type, $textTypes, true)) {
                $inline = new InlineBox($element, $values);
                $value = $element->getAttribute('value') ?? '';
                if ($value !== '') {
                    $inline->addChild(new TextBox($element, $values, $value));
                }
                return $inline;
            }
            // HTML 5 §4.10.5.1.15 — password fields render their
            // value as a sequence of U+2022 bullets so the printed
            // form keeps a sense of "this field is populated"
            // without leaking the value.
            if ($type === 'password') {
                $inline = new InlineBox($element, $values);
                $value = $element->getAttribute('value') ?? '';
                if ($value !== '') {
                    $masked = str_repeat("\u{2022}", mb_strlen($value, 'UTF-8'));
                    $inline->addChild(new TextBox($element, $values, $masked));
                }
                return $inline;
            }
            // HTML 5 §4.10.5.1.21 — `<input type=file>` renders a
            // placeholder label since the actual file picker is
            // interactive. The chosen filename never reaches a
            // server-side print render.
            if ($type === 'file') {
                $inline = new InlineBox($element, $values);
                $inline->addChild(new TextBox($element, $values, 'No file chosen'));
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
                // CSS Display 3 §3.2 — `display: contents` makes the
                // element generate no box of its own; its children
                // render as if they were direct children of this
                // element's parent (i.e. the box we're currently
                // building). Recurse via the helper so nested
                // `display: contents` chains flatten cleanly.
                $childCascade = $this->cascade->computeFor($sheets, $n, $values);
                $this->applyPresentationalAttributes($n, $childCascade);
                if ($this->displayKeyword($childCascade) === 'contents') {
                    foreach ($this->expandDisplayContents($n, $sheets, $values) as $grandchild) {
                        $rawChildren[] = $grandchild;
                    }
                    continue;
                }
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

        // CSS Flexbox 1 §4 / CSS Grid Layout 2 §6: an anonymous flex /
        // grid item that contains only whitespace is not rendered (as
        // if its text nodes were `display: none`). Without this filter
        // the trailing `\n` after `<div class="box"></div>` becomes a
        // second flex item and consumes the slack `justify-content`
        // would otherwise distribute.
        if ($box instanceof FlexBox || $box instanceof GridBox) {
            $rawChildren = $this->stripWhitespaceTextChildren($rawChildren, $values);
        }

        // CSS 2.1 §9.2.1.1 — when an inline box has a block-level
        // descendant, the inline box splits around the block. The
        // block sits between two anonymous inline halves, all
        // wrapped in an anonymous block. We implement the simpler
        // single-level case: an InlineBox / AtomicInlineBox whose
        // immediate `$rawChildren` contain at least one block-level
        // child gets promoted to an AnonymousBlockBox whose children
        // are alternating (anonymous inline halves around blocks).
        // The original element's cascade rides on the
        // AnonymousBlockBox so `position: relative` on the inline
        // still affects the block half per spec.
        if (($box instanceof InlineBox || $box instanceof AtomicInlineBox)
            && $this->containsBlockLevel($rawChildren)
        ) {
            $promoted = new AnonymousBlockBox($element, $values);
            $inlineGroup = [];
            foreach ($rawChildren as $child) {
                if ($this->isInlineLevel($child)) {
                    $inlineGroup[] = $child;
                    continue;
                }
                if ($inlineGroup !== []) {
                    $half = new InlineBox($element, $values);
                    foreach ($inlineGroup as $g) {
                        $half->addChild($g);
                    }
                    $promoted->addChild($half);
                    $inlineGroup = [];
                }
                $promoted->addChild($child);
            }
            if ($inlineGroup !== []) {
                $half = new InlineBox($element, $values);
                foreach ($inlineGroup as $g) {
                    $half->addChild($g);
                }
                $promoted->addChild($half);
            }
            return $promoted;
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
     * `true` when any direct child of `$children` is block-level.
     * Used by the block-in-inline split (CSS 2.1 §9.2.1.1) to detect
     * when an inline box needs promotion to an anonymous block.
     *
     * @param list<Box> $children
     */
    private function containsBlockLevel(array $children): bool
    {
        foreach ($children as $c) {
            if (!$this->isInlineLevel($c)) {
                return true;
            }
        }
        return false;
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
    /**
     * `true` when the painter can resolve the `<img>`'s `src` to bytes
     * the renderer can paint. Drives the alt-text fallback in
     * `boxForElement`: a loadable image keeps its `AtomicInlineBox`
     * status (painted as an Image XObject or routed through the SVG
     * renderer for `image/svg+xml`); an unloadable one falls back to
     * the alt text so the surrounding inline flow still has content.
     *
     * Looks for `src` first, then `srcset` (first candidate only —
     * full responsive selection is a separate substrate gate). Data
     * URLs are loadable when the MIME label is one the painter
     * recognises; local paths are loadable when they parse as one
     * of the supported formats via {@see ImageParser}.
     */
    private function imageIsLoadable(Element $img): bool
    {
        $src = $img->getAttribute('src');
        if ($src === null || $src === '') {
            return false;
        }
        if (str_starts_with($src, 'data:')) {
            // The renderer's data: handlers accept image/png, image/jpeg
            // (raster), image/svg+xml, plus a few siblings the painter
            // routes the same way. Anything else falls back to alt.
            return preg_match(
                '~^data:image/(png|jpe?g|gif|bmp|webp|svg\+xml|tiff?|jpeg2000|jbig2)\b~i',
                $src,
            ) === 1;
        }
        if (str_starts_with($src, 'http://') || str_starts_with($src, 'https://')) {
            // Network sources are loadable only when the renderer has
            // a resource loader attached. The painter still handles
            // the actual fetch (and any errors there fall back to
            // the no-image path), but we want the box-generator
            // decision to track loader configuration.
            return false;
        }
        return $this->naturalImageSize($src) !== null;
    }

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
        // CSS Display 3 §3.2 — `display: contents` on a pseudo-element
        // suppresses its box entirely; its generated `content` flows
        // into the parent as if it were a plain text node carrying
        // the pseudo's inherited text styles. Skip the box wrap and
        // return a TextBox directly so the pseudo's border / background
        // / etc. don't paint (the pseudo has no box).
        if ($display === 'contents') {
            return $text === '' ? null : new TextBox($element, $pseudoValues, $text);
        }
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
        // CSS Values 5 §11 typed AttrFunction (preferred path).
        if ($value instanceof \Phpdftk\Css\Value\AttrFunction) {
            $name = $value->attributeName;
            if ($name !== '') {
                $attrValue = $host->getAttribute($name);
                if ($attrValue !== null) {
                    return $attrValue;
                }
                // Fallback expression on missing attribute — use
                // its serialized form for now (the typed fallback
                // value lands when AttrFunction is consumed by
                // computed-value time).
                if ($value->fallback !== null) {
                    return $value->fallback->toCss();
                }
                return '';
            }
        }
        // Legacy generic CssFunction path for value-paths that
        // bypass Parser::makeDeclaration.
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
     * Extract `<name>` from `position: running(<name>)` when the
     * cascaded `position` value is a generic CssFunction wrapping
     * a bare ident argument. Returns null for any other position
     * value, so normal positioning (static / relative / absolute
     * / fixed) keeps its existing layout path.
     */
    private function extractRunningPositionName(CascadedValues $values): ?string
    {
        $pos = $values->get('position');
        if (!($pos instanceof \Phpdftk\Css\Value\CssFunction)
            || strtolower($pos->name) !== 'running'
            || $pos->arguments === []
        ) {
            return null;
        }
        $arg = $pos->arguments[0];
        if (!($arg instanceof Keyword)) {
            return null;
        }
        return $arg->name;
    }

    /**
     * Apply `string-set: <name> <content-list>` declarations — set
     * the named string value to a resolved content list. Used by
     * GCPM 3 §5 for running headers / footers.
     *
     * Supported `<content-list>` items for the initial pass:
     *
     *   - `<string>` literal       → emit literally
     *   - `content()`              → emit the element's text content
     *   - `attr(name)`             → emit the named attribute value
     *
     * Multiple `string-set` pairs may appear in a comma-separated
     * list; each pair is processed independently. Unsupported
     * content-list items are silently skipped so an unrecognised
     * form doesn't corrupt the rest of the assignment.
     */
    private function applyStringSet(Element $element, CascadedValues $values): void
    {
        $value = $values->get('string-set');
        if ($value === null
            || ($value instanceof Keyword && strtolower($value->name) === 'none')
        ) {
            return;
        }
        $groups = $this->splitStringSetGroups($value);
        foreach ($groups as $group) {
            if (count($group) < 2) {
                continue;
            }
            $head = $group[0];
            if (!($head instanceof Keyword)) {
                continue;
            }
            $name = $head->name;
            $resolved = '';
            for ($i = 1; $i < count($group); $i++) {
                $resolved .= $this->resolveStringSetPart($group[$i], $element);
            }
            $this->namedStrings[$name] = $resolved;
        }
    }

    /**
     * Split the `string-set` value into the per-name groups —
     * `string-set: a "x", b "y"` becomes `[[Kw(a), "x"], [Kw(b), "y"]]`.
     * Each group is a name followed by a content list. Top-level
     * commas are separators between groups; everything else is
     * part of the current group.
     *
     * @return list<list<\Phpdftk\Css\Value\Value>>
     */
    private function splitStringSetGroups(\Phpdftk\Css\Value\Value $value): array
    {
        if (!($value instanceof \Phpdftk\Css\Value\ValueList)) {
            return [[$value]];
        }
        if ($value->separator === \Phpdftk\Css\Value\ListSeparator::Comma) {
            $out = [];
            foreach ($value->values as $item) {
                $out[] = $item instanceof \Phpdftk\Css\Value\ValueList
                    && $item->separator === \Phpdftk\Css\Value\ListSeparator::Space
                        ? $item->values
                        : [$item];
            }
            return $out;
        }
        return [$value->values];
    }

    private function resolveStringSetPart(\Phpdftk\Css\Value\Value $value, Element $host): string
    {
        if ($value instanceof \Phpdftk\Css\Value\StringValue) {
            return $value->value;
        }
        if ($value instanceof \Phpdftk\Css\Value\CssFunction
            && strtolower($value->name) === 'content'
        ) {
            // CSS GCPM 3 §5.1 — `content()` reads the host element's
            // text content. Arguments select sub-text (text, before,
            // after, first-letter); only the default form is honoured
            // here for now.
            return $host->textContent();
        }
        if ($value instanceof \Phpdftk\Css\Value\AttrFunction) {
            $name = $value->attributeName;
            if ($name === '') {
                return '';
            }
            return $host->getAttribute($name) ?? '';
        }
        if ($value instanceof \Phpdftk\Css\Value\CssFunction
            && strtolower($value->name) === 'attr'
            && $value->arguments !== []
        ) {
            $arg = $value->arguments[0];
            $name = $arg instanceof Keyword ? $arg->name : null;
            if ($name === null || $name === '') {
                return '';
            }
            return $host->getAttribute($name) ?? '';
        }
        if ($value instanceof \Phpdftk\Css\Value\CssFunction
            && strtolower($value->name) === 'counter'
            && $value->arguments !== []
        ) {
            // CSS GCPM 3 §5.1 — `counter(<name> [, <style>]?)` inside
            // string-set emits the current counter value at this
            // element. Reuses the existing counter store + formatter.
            $args = $value->arguments;
            $head = $args[0];
            if (!($head instanceof Keyword)) {
                return '';
            }
            $count = $this->counters[$head->name] ?? 0;
            $style = 'decimal';
            if (isset($args[1]) && $args[1] instanceof Keyword) {
                $style = strtolower($args[1]->name);
            }
            return $this->formatCounter($count, $style);
        }
        return '';
    }

    /**
     * Apply `counter-set: <name> [<int>]?` declarations — sets the
     * named counter to the specified value (default 0), without the
     * scope-creating semantics of `counter-reset`. CSS Lists 3 §6.
     */
    private function applyCounterSet(CascadedValues $values): void
    {
        $value = $values->get('counter-set');
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
     * Format `$count` per `$style`. Supports the CSS Counter Styles 3 §6
     * predefined styles:
     *
     *  - decimal, decimal-leading-zero
     *  - lower-alpha / upper-alpha (aliases lower-latin / upper-latin)
     *  - lower-roman / upper-roman
     *  - lower-greek (α β γ ...)
     *  - cjk-decimal (Chinese decimal — uses the same arabic digits but
     *    appended with U+3001 punctuation per browsers' implementation)
     *  - hebrew (Hebrew letter numerals 1-999)
     *  - armenian / lower-armenian / upper-armenian (1-9999)
     *  - georgian (1-19999)
     *  - hiragana / hiragana-iroha (Japanese kana ordering)
     *  - katakana / katakana-iroha
     *
     * Unknown style names fall back to decimal.
     */
    private function formatCounter(int $count, string $style): string
    {
        return match ($style) {
            'decimal-leading-zero' => sprintf('%02d', $count),
            'lower-alpha', 'lower-latin' => $this->bijectiveBase26($count, lower: true),
            'upper-alpha', 'upper-latin' => $this->bijectiveBase26($count, lower: false),
            'lower-roman' => strtolower($this->roman($count)),
            'upper-roman' => $this->roman($count),
            'lower-greek' => $this->lowerGreek($count),
            'hebrew' => $this->hebrew($count),
            'armenian', 'upper-armenian' => $this->armenian($count, lower: false),
            'lower-armenian' => $this->armenian($count, lower: true),
            'georgian' => $this->georgian($count),
            'hiragana' => $this->kanaList($count, [
                'あ','い','う','え','お','か','き','く','け','こ',
                'さ','し','す','せ','そ','た','ち','つ','て','と',
                'な','に','ぬ','ね','の','は','ひ','ふ','へ','ほ',
                'ま','み','む','め','も','や','ゆ','よ','ら','り',
                'る','れ','ろ','わ','ゐ','ゑ','を','ん',
            ]),
            'hiragana-iroha' => $this->kanaList($count, [
                'い','ろ','は','に','ほ','へ','と','ち','り','ぬ',
                'る','を','わ','か','よ','た','れ','そ','つ','ね',
                'な','ら','む','う','ゐ','の','お','く','や','ま',
                'け','ふ','こ','え','て','あ','さ','き','ゆ','め',
                'み','し','ゑ','ひ','も','せ','す',
            ]),
            'katakana' => $this->kanaList($count, [
                'ア','イ','ウ','エ','オ','カ','キ','ク','ケ','コ',
                'サ','シ','ス','セ','ソ','タ','チ','ツ','テ','ト',
                'ナ','ニ','ヌ','ネ','ノ','ハ','ヒ','フ','ヘ','ホ',
                'マ','ミ','ム','メ','モ','ヤ','ユ','ヨ','ラ','リ',
                'ル','レ','ロ','ワ','ヰ','ヱ','ヲ','ン',
            ]),
            'katakana-iroha' => $this->kanaList($count, [
                'イ','ロ','ハ','ニ','ホ','ヘ','ト','チ','リ','ヌ',
                'ル','ヲ','ワ','カ','ヨ','タ','レ','ソ','ツ','ネ',
                'ナ','ラ','ム','ウ','ヰ','ノ','オ','ク','ヤ','マ',
                'ケ','フ','コ','エ','テ','ア','サ','キ','ユ','メ',
                'ミ','シ','ヱ','ヒ','モ','セ','ス',
            ]),
            default => (string) $count,
        };
    }

    /**
     * CSS Counter Styles 3 §6.4 — lower-greek. 1-24 maps to α-ω
     * (skipping final-σ and using ς in position 18 per the spec
     * actually uses non-final sigma). Values outside 1-24 wrap
     * via bijective base-24 over the alphabet.
     */
    private function lowerGreek(int $n): string
    {
        $alphabet = [
            'α','β','γ','δ','ε','ζ','η','θ','ι','κ','λ','μ',
            'ν','ξ','ο','π','ρ','σ','τ','υ','φ','χ','ψ','ω',
        ];
        if ($n < 1) {
            return (string) $n;
        }
        $out = '';
        while ($n > 0) {
            $n--;
            $out = $alphabet[$n % 24] . $out;
            $n = intdiv($n, 24);
        }
        return $out;
    }

    /**
     * CSS Counter Styles 3 §6.5 — hebrew numerals 1-999.
     * Out-of-range falls back to decimal.
     */
    private function hebrew(int $n): string
    {
        if ($n < 1 || $n > 999) {
            return (string) $n;
        }
        $map = [
            400 => 'ת', 300 => 'ש', 200 => 'ר', 100 => 'ק',
            90  => 'צ', 80  => 'פ', 70  => 'ע', 60  => 'ס',
            50  => 'נ', 40  => 'מ', 30  => 'ל', 20  => 'כ',
            19  => 'יט', 18 => 'יח', 17 => 'יז', 16 => 'טז', 15 => 'טו',
            14  => 'יד', 13 => 'יג', 12 => 'יב', 11 => 'יא',
            10  => 'י',
            9 => 'ט', 8 => 'ח', 7 => 'ז', 6 => 'ו', 5 => 'ה',
            4 => 'ד', 3 => 'ג', 2 => 'ב', 1 => 'א',
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

    /**
     * CSS Counter Styles 3 §6.7 — Armenian numerals. Range 1-9999.
     */
    private function armenian(int $n, bool $lower): string
    {
        if ($n < 1 || $n > 9999) {
            return (string) $n;
        }
        $upperOnes = ['Ա','Բ','Գ','Դ','Ե','Զ','Է','Ը','Թ'];
        $upperTens = ['Ժ','Ի','Լ','Խ','Ծ','Կ','Հ','Ձ','Ղ'];
        $upperHundreds = ['Ճ','Մ','Յ','Ն','Շ','Ո','Չ','Պ','Ջ'];
        $upperThousands = ['Ռ','Ս','Վ','Տ','Ր','Ց','Ւ','Փ','Ք'];
        $thousands = intdiv($n, 1000);
        $hundreds = intdiv($n % 1000, 100);
        $tens = intdiv($n % 100, 10);
        $ones = $n % 10;
        $out = '';
        if ($thousands > 0) {
            $out .= $upperThousands[$thousands - 1];
        }
        if ($hundreds > 0) {
            $out .= $upperHundreds[$hundreds - 1];
        }
        if ($tens > 0) {
            $out .= $upperTens[$tens - 1];
        }
        if ($ones > 0) {
            $out .= $upperOnes[$ones - 1];
        }
        return $lower ? mb_strtolower($out, 'UTF-8') : $out;
    }

    /**
     * CSS Counter Styles 3 §6.6 — Georgian numerals (Mkhedruli).
     * Range 1-19999.
     */
    private function georgian(int $n): string
    {
        if ($n < 1 || $n > 19999) {
            return (string) $n;
        }
        $ones = ['ა','ბ','გ','დ','ე','ვ','ზ','ჱ','თ'];
        $tens = ['ი','კ','ლ','მ','ნ','ჲ','ო','პ','ჟ'];
        $hundreds = ['რ','ს','ტ','ჳ','ფ','ქ','ღ','ყ','შ'];
        $thousands = ['ჩ','ც','ძ','წ','ჭ','ხ','ჴ','ჯ','ჰ'];
        $tt = intdiv($n, 10000);
        $h = intdiv($n % 10000, 1000);
        $t = intdiv($n % 1000, 100);
        $te = intdiv($n % 100, 10);
        $o = $n % 10;
        $out = '';
        if ($tt > 0) {
            $out .= 'ჵ';
        }
        if ($h > 0) {
            $out .= $thousands[$h - 1];
        }
        if ($t > 0) {
            $out .= $hundreds[$t - 1];
        }
        if ($te > 0) {
            $out .= $tens[$te - 1];
        }
        if ($o > 0) {
            $out .= $ones[$o - 1];
        }
        return $out;
    }

    /**
     * Generic kana / alphabetic style — bijective expansion over
     * the supplied symbol list. Used by hiragana / katakana
     * (gojuon + iroha orderings).
     *
     * @param list<string> $symbols
     */
    private function kanaList(int $n, array $symbols): string
    {
        if ($n < 1 || $symbols === []) {
            return (string) $n;
        }
        $base = count($symbols);
        $out = '';
        while ($n > 0) {
            $n--;
            $out = $symbols[$n % $base] . $out;
            $n = intdiv($n, $base);
        }
        return $out;
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
        // CSS 2.1 §9.2.2.1 / Display 3 §3.4 — anonymous block boxes that
        // contain only whitespace text are removed during box generation.
        // The whitespace was already going to collapse to nothing in
        // inline layout, but keeping the wrapper as an empty box still
        // breaks adjacent-sibling margin collapse on the parent (the
        // first sibling's `margin-bottom` no longer adjoins the next
        // sibling's `margin-top`).
        if ($this->onlyCollapsibleWhitespace($inlineGroup, $values)) {
            return;
        }
        // CSS Display 3 §3.4 — anonymous block boxes have no element
        // and inherit only the inheritable properties from their
        // parent. Crucially, non-inherited properties (background,
        // width, height, border, padding, margin, …) MUST stay at
        // their initial values; otherwise an anonymous wrapper around
        // a run of whitespace text inside a `width: 100px; height:
        // 100px; background: black` parent would paint a second
        // 100×100 black rect at the cursor.
        $anonValues = $this->cascade->anonymousFromParent($values);
        $anon = new AnonymousBlockBox(null, $anonValues);
        foreach ($inlineGroup as $c) {
            $anon->addChild($c);
        }
        $parent->addChild($anon);
    }

    /**
     * True when every entry in `$inlineGroup` is a TextBox carrying only
     * ASCII / Unicode collapsible whitespace — and the parent's
     * `white-space` value lets that whitespace collapse. Whitespace-
     * preserving values (`pre`, `pre-wrap`, `pre-line`, `break-spaces`)
     * keep the run, since the spec says we have to lay them out.
     *
     * @param list<Box> $inlineGroup
     */
    private function onlyCollapsibleWhitespace(array $inlineGroup, CascadedValues $values): bool
    {
        $whiteSpace = $values->get('white-space');
        if ($whiteSpace instanceof Keyword) {
            $kw = strtolower($whiteSpace->name);
            if ($kw === 'pre' || $kw === 'pre-wrap' || $kw === 'pre-line' || $kw === 'break-spaces') {
                return false;
            }
        }
        foreach ($inlineGroup as $box) {
            if (!$box instanceof TextBox) {
                return false;
            }
            if (preg_match('/^[\s\x{200B}]*$/u', $box->text) !== 1) {
                return false;
            }
        }
        return true;
    }

    /**
     * Drop direct TextBox children whose text is entirely collapsible
     * whitespace when the parent is a flex / grid container, matching
     * the "anonymous whitespace flex item is not rendered" rule in
     * CSS Flexbox 1 §4 (echoed by CSS Grid Layout 2 §6).
     *
     * @param  list<Box> $rawChildren
     * @return list<Box>
     */
    private function stripWhitespaceTextChildren(array $rawChildren, CascadedValues $values): array
    {
        $whiteSpace = $values->get('white-space');
        if ($whiteSpace instanceof Keyword) {
            $kw = strtolower($whiteSpace->name);
            if ($kw === 'pre' || $kw === 'pre-wrap' || $kw === 'pre-line' || $kw === 'break-spaces') {
                return $rawChildren;
            }
        }
        $out = [];
        foreach ($rawChildren as $child) {
            if ($child instanceof TextBox
                && preg_match('/^[\s\x{200B}]*$/u', $child->text) === 1
            ) {
                continue;
            }
            $out[] = $child;
        }
        return $out;
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
            'table-column', 'table-column-group' => new TableColumnBox($element, $values),
            'flex' => new FlexBox($element, $values),
            'grid' => new GridBox($element, $values),
            default => new BlockBox($element, $values),
        };
    }

    /**
     * Root foreign-content elements (`<math>` and `<svg>`) route
     * through dedicated atomic-inline painters that resolve their
     * own positioning via `resolveInlineAbsoluteOrigin`. They must
     * NOT be blockified by the CSS Display §2.7 out-of-flow rule
     * because the generic block pipeline doesn't know how to
     * delegate to those painters. See the `mathml/spaces/space-3`
     * regression note where blockification dropped the inline-math
     * paint entirely.
     */
    private function isForeignContentRoot(Element $element): bool
    {
        $tag = strtolower($element->localName);
        if ($tag !== 'math' && $tag !== 'svg') {
            return false;
        }
        // Belt-and-braces: confirm the namespace too so an HTML
        // element with `localName="math"` (which can happen in
        // some XML fragments) doesn't escape the blockification.
        $ns = $element->namespaceUri();
        if ($tag === 'math') {
            return $ns === \Phpdftk\Mathml\Parser::MATHML_NS;
        }
        return $ns === \Phpdftk\Svg\Parser::SVG_NS;
    }

    /**
     * CSS 2.1 §9.7 — an element is "out-of-flow" when its `position`
     * is `absolute` / `fixed` or its `float` is `left` / `right`.
     * Out-of-flow elements are blockified: an inline-level computed
     * `display` becomes `block`.
     */
    private function isOutOfFlow(CascadedValues $values): bool
    {
        $position = $values->get('position');
        if ($position instanceof Keyword) {
            $name = strtolower($position->name);
            if ($name === 'absolute' || $name === 'fixed') {
                return true;
            }
        }
        $float = $values->get('float');
        if ($float instanceof Keyword) {
            $name = strtolower($float->name);
            if ($name === 'left' || $name === 'right') {
                return true;
            }
        }
        return false;
    }

    private function displayKeyword(CascadedValues $values): string
    {
        $display = $values->get('display');
        if ($display instanceof Keyword) {
            return strtolower($display->name);
        }
        // CSS Display 3 §2 — `display: <outside> <inside>` (the two-
        // keyword syntax, e.g. `display: inline grid` /
        // `display: inline grid-lanes`) parses as a ValueList of
        // Keywords. We compose them into the canonical single
        // keyword form (`inline-grid`, `inline-grid-lanes`) so the
        // rest of BoxGenerator's `match ($display)` paths keep
        // working as before.
        if ($display instanceof \Phpdftk\Css\Value\ValueList) {
            $names = [];
            foreach ($display->values as $v) {
                if ($v instanceof Keyword) {
                    $names[] = strtolower($v->name);
                }
            }
            if ($names !== []) {
                $outside = $names[0] ?? null;
                $inside = $names[1] ?? null;
                if ($outside === 'inline' && $inside !== null) {
                    return 'inline-' . $inside;
                }
                if ($outside === 'block' && $inside !== null) {
                    return $inside;
                }
                return implode('-', $names);
            }
        }
        return 'inline';
    }

    /**
     * Returns `true` when the cascaded value is the property's initial
     * (or a `none` / `auto` keyword that's effectively initial for the
     * track-list properties). Used by the `display: grid-lanes`
     * aliasing to decide which axis the author specified tracks for.
     */
    private function isInitialValue(?\Phpdftk\Css\Value\Value $value): bool
    {
        if ($value === null) {
            return true;
        }
        if ($value instanceof Keyword) {
            $name = strtolower($value->name);
            return $name === 'none' || $name === 'auto' || $name === 'initial';
        }
        return false;
    }

    /**
     * CSS Display 3 §3.2 — expand a `display: contents` element's
     * children as if they were direct children of the parent that
     * called us. Nested `display: contents` elements flatten
     * recursively. Text node children become {@see TextBox}es
     * attached to the parent's text cascade (the parent passed in
     * `$parentValues`).
     *
     * Pseudo-element generation (`::before` / `::after`) on a
     * `display: contents` element is honoured because the cascade
     * runs as normal — the pseudos generate boxes which get
     * collected here just like any other child.
     *
     * @param list<Stylesheet> $sheets
     * @return list<Box>
     */
    private function expandDisplayContents(
        Element $element,
        array $sheets,
        CascadedValues $parentValues,
    ): array {
        $values = $this->cascade->computeFor($sheets, $element, $parentValues);
        $this->applyPresentationalAttributes($element, $values);
        $result = [];
        // `::before` generated content participates in the flattened
        // child stream — it's defined on the display:contents element
        // so it visually still attaches "at" that element's position.
        $before = $this->makePseudoBox($element, $sheets, $values, 'before');
        if ($before !== null) {
            $result[] = $before;
        }
        for ($n = $element->firstChild; $n !== null; $n = $n->nextSibling) {
            if ($n instanceof Element) {
                $childCascade = $this->cascade->computeFor($sheets, $n, $values);
                $this->applyPresentationalAttributes($n, $childCascade);
                if ($this->displayKeyword($childCascade) === 'contents') {
                    foreach ($this->expandDisplayContents($n, $sheets, $values) as $g) {
                        $result[] = $g;
                    }
                    continue;
                }
                $box = $this->buildElementBox($n, $sheets, $values);
                if ($box !== null) {
                    $result[] = $box;
                }
            } elseif ($n instanceof Text) {
                if ($n->data === '') {
                    continue;
                }
                // Text node children of a display:contents element
                // attach as TextBoxes using the element's own cascade
                // (so the contents-styled element's inherited
                // text properties still apply, even though the
                // element itself produces no box).
                $result[] = new TextBox($element, $values, $n->data);
            }
        }
        $after = $this->makePseudoBox($element, $sheets, $values, 'after');
        if ($after !== null) {
            $result[] = $after;
        }
        return $result;
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
                // Replaced elements have an intrinsic aspect ratio
                // per CSS Sizing 4 §5.1. Expose it as the
                // `aspect-ratio` cascade value (when the author
                // hasn't overridden it) so layout primitives like
                // `aspectRatioTransfer` and Flexbox §4.5 automatic
                // minimum sizing can read it without re-decoding
                // the image.
                $natural = $this->naturalImageSize($element->getAttribute('src'));
                if ($natural !== null) {
                    [$nw, $nh] = $natural;
                    if ($nw > 0 && $nh > 0 && !$values->has('aspect-ratio')) {
                        $values->set(
                            'aspect-ratio',
                            new \Phpdftk\Css\Value\ValueList(
                                [
                                    new \Phpdftk\Css\Value\Number((float) $nw),
                                    new \Phpdftk\Css\Value\Number((float) $nh),
                                ],
                                \Phpdftk\Css\Value\ListSeparator::Slash,
                            ),
                        );
                    }
                }
                if ($wUnset || $hUnset) {
                    if ($natural !== null) {
                        [$nw, $nh] = $natural;
                        if ($wUnset && $hUnset) {
                            $values->set('width', new \Phpdftk\Css\Value\Length((float) $nw, \Phpdftk\Css\Value\LengthUnit::Px));
                            $values->set('height', new \Phpdftk\Css\Value\Length((float) $nh, \Phpdftk\Css\Value\LengthUnit::Px));
                        } elseif ($wUnset && $hValue instanceof \Phpdftk\Css\Value\Length && $nh > 0) {
                            // Under `box-sizing: border-box`, the declared
                            // height includes the padding/border vertical
                            // inset, so derive the content height first,
                            // then add the horizontal inset to land on the
                            // declared width that produces the right
                            // content-width-to-content-height ratio.
                            [$hInset, $vInset, $borderBox] = $this->presentationalInsetsAndBoxSizing($values);
                            $declaredH = $hValue->value;
                            $contentH = $borderBox ? max(0.0, $declaredH - $vInset) : $declaredH;
                            $contentW = $contentH * ($nw / $nh);
                            $declaredW = $borderBox ? ($contentW + $hInset) : $contentW;
                            $values->set(
                                'width',
                                new \Phpdftk\Css\Value\Length(
                                    $declaredW,
                                    \Phpdftk\Css\Value\LengthUnit::Px,
                                ),
                            );
                        } elseif ($hUnset && $wValue instanceof \Phpdftk\Css\Value\Length && $nw > 0) {
                            [$hInset, $vInset, $borderBox] = $this->presentationalInsetsAndBoxSizing($values);
                            $declaredW = $wValue->value;
                            $contentW = $borderBox ? max(0.0, $declaredW - $hInset) : $declaredW;
                            $contentH = $contentW * ($nh / $nw);
                            $declaredH = $borderBox ? ($contentH + $vInset) : $contentH;
                            $values->set(
                                'height',
                                new \Phpdftk\Css\Value\Length(
                                    $declaredH,
                                    \Phpdftk\Css\Value\LengthUnit::Px,
                                ),
                            );
                        }
                    }
                }
            }
        }
        // HTML 5 §4.12.5 — a `<canvas>` is a replaced element whose
        // intrinsic dimensions are its `width` / `height` content
        // attributes (default 300 x 150). When BOTH are given
        // explicitly, apply them as presentational width/height (unless
        // the author set CSS dims, mirroring the `<img>` path) so block
        // layout sizes the canvas, and expose the intrinsic ratio as
        // `aspect-ratio` so the replaced-element keyword sizing can
        // transfer a definite cross size into a `min-content` /
        // `max-content` main size. An attribute-less canvas keeps the
        // default 300 x 150 handled downstream — forcing that default
        // here disturbs cases (object-view-box, vertical writing modes)
        // that already render correctly. Skip under `contain: size`,
        // where CSS Containment 3 §4.1 substitutes `contain-intrinsic-
        // size` for the intrinsic size.
        $canvasW = $tag === 'canvas' ? $this->parseHtmlLength($element->getAttribute('width') ?? '') : null;
        $canvasH = $tag === 'canvas' ? $this->parseHtmlLength($element->getAttribute('height') ?? '') : null;
        if ($canvasW !== null && $canvasW > 0.0
            && $canvasH !== null && $canvasH > 0.0
            && !$this->hasSizeContainment($values)
        ) {
            foreach (['width' => $canvasW, 'height' => $canvasH] as $attr => $val) {
                if (!$values->has($attr) || $this->isAutoLength($values->get($attr))) {
                    $values->set($attr, new \Phpdftk\Css\Value\Length($val, \Phpdftk\Css\Value\LengthUnit::Px));
                }
            }
            if (!$values->has('aspect-ratio')) {
                $values->set(
                    'aspect-ratio',
                    new \Phpdftk\Css\Value\ValueList(
                        [
                            new \Phpdftk\Css\Value\Number($canvasW),
                            new \Phpdftk\Css\Value\Number($canvasH),
                        ],
                        \Phpdftk\Css\Value\ListSeparator::Slash,
                    ),
                );
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

    /**
     * Sum the horizontal and vertical padding + border lengths from a
     * cascaded-values bundle, plus whether `box-sizing` is `border-box`.
     * Used by the `<img>` intrinsic-ratio derivation to compute the
     * declared dimension that produces the right content dimension once
     * border-box subtracts the inset.
     *
     * @return array{0: float, 1: float, 2: bool}
     */
    private function presentationalInsetsAndBoxSizing(\Phpdftk\Css\Cascade\CascadedValues $values): array
    {
        $sumLength = function (string $property) use ($values): float {
            $v = $values->get($property);
            return $v instanceof \Phpdftk\Css\Value\Length
                ? \Phpdftk\Css\Cascade\LengthResolver::clampPx($v->value)
                : 0.0;
        };
        $borderSide = function (string $side) use ($values): float {
            $styleValue = $values->get("border-$side-style");
            if ($styleValue instanceof Keyword && strtolower($styleValue->name) === 'none') {
                return 0.0;
            }
            $width = $values->get("border-$side-width");
            if ($width instanceof \Phpdftk\Css\Value\Length) {
                return \Phpdftk\Css\Cascade\LengthResolver::clampPx($width->value);
            }
            if ($width instanceof Keyword) {
                return match (strtolower($width->name)) {
                    'thin' => 1.0,
                    'medium' => 3.0,
                    'thick' => 5.0,
                    default => 0.0,
                };
            }
            return 0.0;
        };
        $hInset = $sumLength('padding-left') + $sumLength('padding-right')
            + $borderSide('left') + $borderSide('right');
        $vInset = $sumLength('padding-top') + $sumLength('padding-bottom')
            + $borderSide('top') + $borderSide('bottom');
        $boxSizing = $values->get('box-sizing');
        $borderBox = $boxSizing instanceof Keyword
            && strtolower($boxSizing->name) === 'border-box';
        return [$hInset, $vInset, $borderBox];
    }

    private function isAutoLength(?\Phpdftk\Css\Value\Value $v): bool
    {
        return $v instanceof Keyword && strtolower($v->name) === 'auto';
    }

    /**
     * CSS Containment 3 §2 — `true` when the cascaded `contain` enables
     * size containment: the `size` or `strict` keyword, or a list that
     * includes `size`. (`content` = layout|paint|style does NOT contain
     * size.) Under size containment a replaced element's intrinsic size
     * is taken from `contain-intrinsic-size`, so attribute / natural
     * dimensions must not be applied.
     */
    private function hasSizeContainment(\Phpdftk\Css\Cascade\CascadedValues $values): bool
    {
        $contain = $values->get('contain');
        if ($contain instanceof Keyword) {
            return in_array(strtolower($contain->name), ['size', 'strict'], true);
        }
        if ($contain instanceof \Phpdftk\Css\Value\ValueList) {
            foreach ($contain->values as $part) {
                if ($part instanceof Keyword && strtolower($part->name) === 'size') {
                    return true;
                }
            }
        }
        return false;
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
            // Accept any `data:image/...` MIME, plus `data:image/svg+xml`
            // textual payloads. The sniffer in `ImageParser::parseString`
            // dispatches on the actual byte signature, so we don't gate
            // on the MIME label here — broader than the prior
            // `(png|jpeg|jpg)` allow-list and consistent with the
            // painter's permissive handling.
            if (preg_match('~^data:image/[^;,]+(?:;([^,]*))?,(.*)$~s', $src, $m) !== 1) {
                return null;
            }
            $parameters = strtolower($m[1]);
            $rawPayload = $m[2];
            $payload = str_contains($parameters, 'base64')
                ? base64_decode($rawPayload, strict: true)
                : urldecode($rawPayload);
            if ($payload === false || $payload === '') {
                return null;
            }
            try {
                $info = \Phpdftk\ImageMetadata\ImageParser::parseString($payload);
            } catch (\Throwable) {
                return null;
            }
            return $info->width > 0 && $info->height > 0
                ? [$info->width, $info->height]
                : null;
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
        return $info->width > 0 && $info->height > 0
            ? [$info->width, $info->height]
            : null;
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
        return (new \Phpdftk\Filesystem\ResourceLoader($this->baseDir, $this->sandboxRoot))
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
        // HTML 5 §2.4.4.4 "rules for parsing dimension values" — skip
        // a leading number, then accept (and ignore) a trailing `px`
        // suffix per the relaxed dimension form. Browsers treat
        // `width="100"` and `width="100px"` identically on
        // `<img>` / `<embed>` / `<iframe>` / `<video>`; rejecting
        // the `px` form drops the value back to `width: auto` and
        // mis-sizes the replaced element.
        if (preg_match('/^(\d+(?:\.\d+)?)(?:px)?$/i', $raw, $m) === 1) {
            return (float) $m[1];
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
