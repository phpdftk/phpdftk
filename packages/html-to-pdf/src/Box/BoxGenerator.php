<?php

declare(strict_types=1);

namespace Phpdftk\HtmlToPdf\Box;

use Phpdftk\Css\Cascade\Cascade;
use Phpdftk\Css\Cascade\CascadedValues;
use Phpdftk\Css\Cascade\WritingMode;
use Phpdftk\Css\Sheet\Stylesheet;
use Phpdftk\Css\Value\Color;
use Phpdftk\Css\Value\Keyword;
use Phpdftk\Css\Value\Length;
use Phpdftk\Css\Value\LengthUnit;
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

    /** Lazily-created parser for coercing typed `attr()` attribute values. */
    private ?\Phpdftk\Css\ValueParser $attrParser = null;

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

    /**
     * Resolved `direction` for an element with HTML auto
     * directionality, or null when the element does not have it.
     *
     * @return 'ltr'|'rtl'|null
     */
    private function autoDirectionFor(Element $element): ?string
    {
        $dir = $element->getAttribute('dir');
        $dirValue = $dir === null ? null : strtolower(trim($dir));
        // `<bdi>` has auto directionality by default (HTML §4.5.24).
        $isBareBdi = $dirValue === null
            && strtolower($element->localName) === 'bdi';
        if ($dirValue !== 'auto' && !$isBareBdi) {
            return null;
        }
        $text = $this->autoDirectionText($element);
        if ($text === '') {
            return null;
        }
        $result = (new \Phpdftk\Text\Bidi())->analyze($text, \Phpdftk\Text\BidiBase::Auto);
        return $result->resolvedBase === \Phpdftk\Text\BidiBase::Rtl ? 'rtl' : 'ltr';
    }

    /**
     * Descendant text in tree order for the auto-directionality scan,
     * skipping subtrees that establish their own direction (an element
     * carrying `dir`, or a nested `<bdi>`) and non-rendered content.
     */
    private function autoDirectionText(Element $element): string
    {
        $text = '';
        for ($n = $element->firstChild; $n !== null; $n = $n->nextSibling) {
            if ($n instanceof Text) {
                $text .= $n->data;
                continue;
            }
            if (!($n instanceof Element)) {
                continue;
            }
            $tag = strtolower($n->localName);
            if ($tag === 'script' || $tag === 'style' || $tag === 'bdi') {
                continue;
            }
            if ($n->getAttribute('dir') !== null) {
                continue;
            }
            $text .= $this->autoDirectionText($n);
        }
        return $text;
    }

    /** @param list<Stylesheet> $sheets */
    private function buildElementBox(
        Element $element,
        array $sheets,
        ?CascadedValues $parentValues,
    ): ?Box {
        $values = $this->cascade->computeFor($sheets, $element, $parentValues);
        $this->applyPresentationalAttributes(
            $element,
            $values,
            $parentValues !== null && $this->isFlexOrGridContainer($parentValues),
        );
        $display = $this->displayKeyword($values);
        if ($display === 'none') {
            return null;
        }
        // CSS Grid 3 §2.2 — `display: grid-lanes` generates a grid lanes
        // (masonry) container. It shares the `GridBox` box type and the
        // grid cascade shape, so the display value is rewritten to
        // `grid` here and the lanes-ness is recorded on the box below;
        // `BlockLayout::layoutGridBox` then routes to the §4.4 lanes
        // placement algorithm instead of the 2D grid one.
        //
        // §2.3 fixes the orientation: `grid-template-columns: none`
        // together with a non-`none` `grid-template-rows` makes the
        // BLOCK axis the grid axis (the lanes are rows); in every other
        // case the INLINE axis is the grid axis (the lanes are columns).
        $lanesGridAxisIsInline = null;
        $lanesFillReverse = false;
        $lanesTrackReverse = false;
        if ($display === 'grid-lanes' || $display === 'inline-grid-lanes') {
            $hasRowTracks = !$this->isInitialValue($values->get('grid-template-rows'));
            $hasColTracks = !$this->isInitialValue($values->get('grid-template-columns'));
            $lanesGridAxisIsInline = !($hasRowTracks && !$hasColTracks);
            // `grid-lanes-direction: normal | [ row | column ]
            //  [ fill-reverse || track-reverse ]?` — an explicit `row` /
            // `column` names the grid axis outright instead of deriving it
            // from which grid-template-* the author set.
            $directionNames = $this->keywordNames($values->get('grid-lanes-direction'));
            if (in_array('row', $directionNames, true)) {
                $lanesGridAxisIsInline = false;
            } elseif (in_array('column', $directionNames, true)) {
                $lanesGridAxisIsInline = true;
            }
            $lanesFillReverse = in_array('fill-reverse', $directionNames, true);
            $lanesTrackReverse = in_array('track-reverse', $directionNames, true);
            if (!$lanesGridAxisIsInline) {
                // Keeps the fallback 2D grid path sane for the
                // `inline-grid-lanes` case, which still lands on an
                // `AtomicInlineBox` rather than a `GridBox`.
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
        // HTML §3.2.6.5 — auto directionality. `dir="auto"` (and a
        // `<bdi>` with no `dir`) resolves `direction` from the first
        // STRONG character of the element's text, which is exactly
        // UAX#9's P2/P3 rule that {@see \Phpdftk\Text\Bidi::analyze}
        // already implements for `BidiBase::Auto`. The UA sheet maps
        // `dir=auto` to `unicode-bidi: plaintext` but never resolved
        // `direction`, so such an element silently inherited its
        // parent's — putting Hebrew under `text-align: start` on the
        // wrong side.
        $autoDirection = $this->autoDirectionFor($element);
        if ($autoDirection !== null) {
            $values->set('direction', new Keyword($autoDirection));
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
        $wasInlineLevelOutOfFlow = false;
        // CSS 2.1 §9.7 / CSS Display 3 §2.7 — out-of-flow blockification
        // covers the internal-table displays too, not just the inline
        // family: a `position: absolute` / floated `table-row` /
        // `table-cell` / `table-column` (etc.) computes to `block`.
        // `inline-table` is the one value that blockifies to `table`
        // rather than `block`, matching the flex-item mapping elsewhere.
        $outOfFlowBlockified = self::OUT_OF_FLOW_BLOCKIFIED;
        if (isset($outOfFlowBlockified[$display])
            && $this->isOutOfFlow($values)
            && !$this->isInlinePositionedForeignRoot($element)
        ) {
            // Remember the pre-blockification level so the abs-pos
            // static-position recovery can distinguish an originally
            // inline-level box (inline-continuation static position) from an
            // originally block-level one (block-flow static position). The
            // cascade's `display` is about to be overwritten to `block`.
            // Only the inline family was inline-LEVEL before
            // blockification; an internal-table display was not, so it
            // must not claim the inline static position.
            $wasInlineLevelOutOfFlow = str_starts_with($display, 'inline');
            $display = $outOfFlowBlockified[$display];
            $values->set('display', new Keyword($display));
        }
        // Foreign-content roots (`<svg>` / `<math>`) are replaced
        // atomic-inline boxes routed to the dedicated foreign painters.
        // The UA sheet's `svg, math { display: inline-block }` matches the
        // unprefixed form by tag name; the prefixed XHTML form
        // (`<svg:svg>`, localName `"svg:svg"`) misses that selector and
        // would fall back to generic inline flow. Force any inline-level
        // foreign root to `inline-block` so it generates an
        // `AtomicInlineBox`.
        if (self::foreignContentKind($element) === 'svg') {
            // SVG 2 §6.7 — the root `<svg>`'s own presentation attributes
            // are author declarations too, so the subtree must inherit
            // from a cascade that has seen them. `$values` comes from the
            // HTML cascade, which has not; recompute with the synthesised
            // sheet rather than mutating `$values` (box generation below
            // relies on the unmodified one).
            $svgRootSheets = $this->svgCascadeSheets($element, $sheets);
            $svgRootValues = $svgRootSheets === $sheets
                ? $values
                : $this->cascade->computeFor($svgRootSheets, $element, $parentValues);
            $this->projectCssOntoSvgSubtree($element, $sheets, $svgRootValues);
        }
        if ($this->isForeignContentRoot($element)
            && in_array($display, ['inline', 'inline-block', 'inline-flex', 'inline-grid', 'inline-table'], true)
        ) {
            $values->set('display', new Keyword('inline-block'));
            $display = 'inline-block';
        }
        // CSS Display 3 §2.7 — a flex ITEM is BLOCKIFIED: an in-flow
        // inline-level child of a flex container computes to its block-level
        // equivalent so it participates as a flex item (fills its line,
        // honours width / height) instead of laying out as inline text.
        // Out-of-flow children were already blockified above; foreign-content
        // roots keep their atomic-inline box (excluded).
        // NOTE: scoped to FLEX only — blockifying GRID items is equally
        // spec-correct but our grid track sizing mishandles the resulting
        // block items (measured net −47 on css-grid vs +26 on flex), so it
        // is deferred until the grid item path is fixed.
        $parentDisplay = $parentValues !== null ? $this->displayKeyword($parentValues) : null;
        if (($parentDisplay === 'flex' || $parentDisplay === 'inline-flex')
            && !$this->isForeignContentRoot($element)
            && in_array($display, ['inline', 'inline-block', 'inline-flex', 'inline-grid', 'inline-table', 'run-in'], true)
        ) {
            $blockified = match ($display) {
                'inline-flex' => 'flex',
                'inline-grid' => 'grid',
                'inline-table' => 'table',
                default => 'block',
            };
            $values->set('display', new Keyword($blockified));
            $display = $blockified;
        }
        // CSS Grid 1 §4 / CSS Flexbox 1 §3 — `float` and `clear` have NO
        // effect on a grid / flex item: the used values compute to `none`,
        // so a `float: left` item stays an in-flow grid/flex item instead of
        // being pulled out of the formatting context as a float (which
        // `isOutOfFlow` would otherwise do). Absolutely-positioned children
        // are NOT items — they keep their own rules (and are already
        // float:none per CSS 2.1 §9.7), so exclude them.
        if (in_array($parentDisplay, ['flex', 'inline-flex', 'grid', 'inline-grid'], true)) {
            $position = $values->get('position');
            $isAbsPos = $position instanceof Keyword
                && in_array(strtolower($position->name), ['absolute', 'fixed'], true);
            if (!$isAbsPos) {
                $floatVal = $values->get('float');
                if ($floatVal instanceof Keyword && strtolower($floatVal->name) !== 'none') {
                    $values->set('float', new Keyword('none'));
                }
                $clearVal = $values->get('clear');
                if ($clearVal instanceof Keyword && strtolower($clearVal->name) !== 'none') {
                    $values->set('clear', new Keyword('none'));
                }
            }
        }
        // CSS Containment 2 §4 — `content-visibility: hidden` does NOT
        // suppress the box (unlike `display: none`). The element still
        // generates a box that lays out and paints its own background /
        // border / outline, but it SKIPS its contents: descendants are
        // neither laid out nor painted, and the element is size-contained
        // (its auto size comes from `contain-intrinsic-size`, not its
        // contents). This is exactly `contain: strict` plus a childless
        // box, so synthesize the containment and drop the children below.
        // (`auto` is a runtime-visibility optimisation with no print
        // equivalent and is treated as `visible`.)
        $cv = $values->get('content-visibility');
        $contentVisibilityHidden = $cv instanceof Keyword && strtolower($cv->name) === 'hidden';
        if ($contentVisibilityHidden) {
            $values->set('contain', new Keyword('strict'));
        }
        // CSS GCPM 3 §4 — `position: running(<name>)` opts the
        // element out of normal flow and into the running-element
        // store. No box is generated; the element's text content
        // becomes available to @page margin boxes via
        // `content: element(<name>)`. A content-visibility:hidden element
        // keeps its box (and must not read its skipped contents), so it
        // never enters the running store.
        $runningName = $contentVisibilityHidden ? null : $this->extractRunningPositionName($values);
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

        // CSS Containment 2 §4 — a content-visibility:hidden element paints
        // its own box but skips ALL of its contents. Return a CHILDLESS box
        // (no ::before/::after, no DOM/text children, no hoisted abspos
        // descendants — they are never generated) at this element's own
        // display type. `contain: strict` (synthesized above) makes layout
        // size-contain the box via `contain-intrinsic-size` and the painter
        // paint-clip it. This must precede the `<br>`/replaced-element
        // shortcuts so those elements' contents are hidden too.
        if ($contentVisibilityHidden) {
            $hiddenBox = $this->makeBox($element, $values, $display);
            $hiddenBox->wasInlineLevel = $wasInlineLevelOutOfFlow;
            return $hiddenBox;
        }

        // HTML §4.8.9 / §4.8.10 — `<video>` and `<audio>` are REPLACED
        // elements. Their children are fallback content "for user agents
        // that do not support the element", so a user agent that DOES
        // support them never renders those children: the media element
        // represents its media, not its contents. Return a childless box
        // (this must precede the `<br>` / replaced shortcuts below, and
        // it is why `<video><img src=fail.gif></video>` shows nothing).
        $mediaTag = strtolower($element->localName);
        if ($mediaTag === 'video' || $mediaTag === 'audio') {
            $mediaBox = $this->makeBox($element, $values, $display);
            $mediaBox->wasInlineLevel = $wasInlineLevelOutOfFlow;
            return $mediaBox;
        }

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
            // HTML §15.5.12 — for an `<input>` that is a TEXT ENTRY
            // WIDGET, the used `line-height` must not be smaller than
            // the used value of `normal`. A single-line field has no
            // second line to space away from, so a short line-height
            // only crops the glyphs; browsers floor it instead.
            if (in_array($type, ['text', 'search', 'tel', 'url', 'email', 'password'], true)) {
                $this->floorTextEntryLineHeight($values);
            }
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
        $box->wasInlineLevel = $wasInlineLevelOutOfFlow;
        if ($lanesGridAxisIsInline !== null && $box instanceof GridBox) {
            $box->lanes = true;
            $box->lanesGridAxisIsInline = $lanesGridAxisIsInline;
            $box->lanesFillReverse = $lanesFillReverse;
            $box->lanesTrackReverse = $lanesTrackReverse;
        }

        // Walk children, building child boxes. Text nodes become TextBoxes.
        // `::before` is generated content prepended to the element's own
        // children; `::after` is appended. Both are inline boxes carrying a
        // synthetic TextBox of the `content` string. Phase-1 supports
        // `content: <string>` only — `attr()`, `counter()`, `open-quote` /
        // `close-quote`, etc. fall through to the `normal` initial.
        $rawChildren = [];
        // CSS Lists 3 §3.3 — with `list-style-position: inside` the
        // `::marker` is an INLINE box at the start of the list item's
        // own content, ahead of `::before`. Materialising it as a text
        // child is what makes it push the content along and — the part
        // the painter-only `outside` path can never do — gives an empty
        // `<li></li>` a line box, so a list of empty items still steps
        // down one line-height per item instead of collapsing to zero.
        $insideMarker = $this->insideListMarker($element, $values);
        if ($insideMarker !== null) {
            $rawChildren[] = $insideMarker;
        }
        $before = $this->makePseudoBox($element, $sheets, $values, 'before');
        if ($before !== null) {
            $rawChildren[] = $before;
        }
        // HTML §15.3.11 + CSS Pseudo 4 §3.6 — a `<details>` slots
        // everything EXCEPT its first `<summary>` into the
        // `::details-content` pseudo-element. That pseudo is what
        // authors style to change the disclosure content as a unit, and
        // what the UA sheet hides with `content-visibility` while the
        // details is closed. Resolve it up front: when it is hidden
        // there is no point building (or counter-incrementing) the
        // slotted children at all.
        $isDetails = strtolower($element->localName) === 'details';
        $detailsContentValues = $isDetails
            ? $this->cascade->computeFor($sheets, $element, $values, 'details-content')
            : null;
        $detailsContentDisplay = $detailsContentValues !== null
            ? $this->displayKeyword($detailsContentValues)
            : 'block';
        $detailsContentHidden = false;
        if ($detailsContentValues !== null) {
            $dcv = $detailsContentValues->get('content-visibility');
            $detailsContentHidden = $dcv instanceof Keyword
                && strtolower($dcv->name) === 'hidden';
            if ($detailsContentHidden) {
                // CSS Containment 2 §4 — a `content-visibility: hidden`
                // box is size-contained and paints no contents, which is
                // `contain: strict` over a childless box.
                $detailsContentValues->set('contain', new Keyword('strict'));
            }
        }
        $detailsContentSkipped = $detailsContentHidden || $detailsContentDisplay === 'none';
        /** @var list<Box> $detailsContentChildren */
        $detailsContentChildren = [];
        $detailsSummarySeen = false;
        for ($n = $element->firstChild; $n !== null; $n = $n->nextSibling) {
            $intoContent = false;
            if ($isDetails) {
                if (!$detailsSummarySeen
                    && $n instanceof Element
                    && strtolower($n->localName) === 'summary'
                ) {
                    $detailsSummarySeen = true;
                } else {
                    $intoContent = true;
                }
            }
            if ($intoContent && $detailsContentSkipped) {
                continue;
            }
            // Slotted children inherit through the slot — the flattened
            // tree puts `::details-content` between `<details>` and its
            // non-summary children.
            $host = $intoContent && $detailsContentValues !== null
                ? $detailsContentValues
                : $values;
            /** @var list<Box> $produced */
            $produced = [];
            if ($n instanceof Element) {
                // CSS Display 3 §3.2 — `display: contents` makes the
                // element generate no box of its own; its children
                // render as if they were direct children of this
                // element's parent (i.e. the box we're currently
                // building). Recurse via the helper so nested
                // `display: contents` chains flatten cleanly.
                $childCascade = $this->cascade->computeFor($sheets, $n, $host);
                $this->applyPresentationalAttributes($n, $childCascade, $this->isFlexOrGridContainer($host));
                if ($this->displayKeyword($childCascade) === 'contents') {
                    foreach ($this->expandDisplayContents($n, $sheets, $host) as $grandchild) {
                        $produced[] = $grandchild;
                    }
                } else {
                    $child = $this->buildElementBox($n, $sheets, $host);
                    if ($child !== null) {
                        $produced[] = $child;
                    }
                }
            } elseif ($n instanceof Text) {
                if ($n->data !== '') {
                    $produced[] = new TextBox($element, $host, $n->data);
                }
            }
            // Comments and other node types are dropped.
            foreach ($produced as $producedBox) {
                if ($intoContent) {
                    $detailsContentChildren[] = $producedBox;
                } else {
                    $rawChildren[] = $producedBox;
                }
            }
        }
        if ($detailsContentValues !== null && $detailsContentDisplay !== 'none') {
            if ($detailsContentDisplay === 'contents') {
                // No box for the pseudo; the slotted content flows
                // straight into the details, which is what makes an
                // inline `<details>` keep summary + content on one line.
                foreach ($detailsContentChildren as $slotted) {
                    $rawChildren[] = $slotted;
                }
            } else {
                $slot = $this->makeBox($element, $detailsContentValues, $detailsContentDisplay);
                foreach ($detailsContentChildren as $slotted) {
                    $slot->addChild($slotted);
                }
                $rawChildren[] = $slot;
            }
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

        // CSS 2.1 §17.2.1 — anonymous table-object generation. When a
        // `table` / `table-row-group` box has bare `table-cell` (or
        // other non-row) children, or a `table-row` box has non-cell
        // children, browsers synthesise the missing anonymous rows /
        // cells so the table grid stays well-formed. Without this, a
        // `<div style="display:table"><div style="display:table-cell">`
        // pair falls through `collectTableRows` (which only finds
        // TableRowBox nodes) and the cells stack as bare blocks with
        // zero width. Runs on the just-assembled child list so the
        // downstream anonymous-block / inline-split passes see the
        // repaired structure.
        if ($box instanceof TableBox || $this->actsAsTableRowGroup($box)) {
            $rawChildren = $this->wrapBareTableCellsInRows($rawChildren, $values);
        } elseif ($box instanceof TableRowBox) {
            $rawChildren = $this->wrapBareTableRowChildrenInCells($rawChildren, $values);
        } elseif (!$box instanceof TableColumnBox && !$this->isInlineTableBox($box)) {
            // CSS 2.1 §17.2.1 "generate missing parents" — an internal
            // table box (cell / row / row-group / column(-group))
            // whose parent is NOT the table object it requires gets an
            // anonymous `table` synthesised around it and every
            // consecutive sibling that also needs one. Without this a
            // bare `<span style="display: table-cell">` inside a plain
            // `<div>` laid out as a naked block: no column widths, no
            // row, no grid — which is exactly what the whole
            // `table-anonymous-objects` infer-* family checks.
            $rawChildren = $this->wrapMisparentedTableBoxesInTables($rawChildren, $values);
        }

        // CSS 2.1 §9.2.3 / CSS Display 3 §2.3 — `display: run-in`. Runs on
        // the assembled sibling list because the decision is a function of
        // what FOLLOWS the run-in box, which only this level can see.
        $rawChildren = $this->applyRunIn($rawChildren);

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
        $splitsAroundBlock = $box instanceof AtomicInlineBox
            ? $this->containsInFlowBlockLevel($rawChildren)
            : $this->containsBlockLevel($rawChildren);
        if (($box instanceof InlineBox || $box instanceof AtomicInlineBox)
            && $splitsAroundBlock
        ) {
            // Only a NON-atomic inline (`display: inline`) splits around a
            // block into an ANONYMOUS wrapper whose box-decoration is dropped
            // (CSS 2.1 §9.2.1.1). An atomic inline-block is itself a block
            // container: its border / padding / margin / background apply to
            // the inline-block box as usual, so keep its cascade verbatim.
            $promoted = new AnonymousBlockBox(
                $element,
                $box instanceof InlineBox
                    ? $this->blockInInlineWrapperValues($values)
                    : $values,
            );
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
     * CSS 2.1 §9.2.3 / CSS Display 3 §2.3 — resolve every `display: run-in`
     * box in a just-assembled sibling list.
     *
     * A run-in box is inline-level content that "runs into" the block box
     * that follows it, becoming that block's first inline child. It falls
     * back to being a block box when it can't:
     *
     *  - it contains an in-flow block-level box of its own, or
     *  - nothing suitable follows it (end of the list, an inline-level
     *    sibling, a table / inline-table / inline-block, or another run-in).
     *
     * Collapsible whitespace and out-of-flow siblings (floats, abs-pos)
     * between the run-in and its target are skipped — they don't break the
     * association. `display: none` siblings never generated a box, so they
     * are skipped for free.
     *
     * @param  list<Box> $rawChildren
     * @return list<Box>
     */
    private function applyRunIn(array $rawChildren): array
    {
        $seen = false;
        foreach ($rawChildren as $candidate) {
            if ($this->isRunInBox($candidate)) {
                $seen = true;
                break;
            }
        }
        if (!$seen) {
            return $rawChildren;
        }

        $out = [];
        $count = count($rawChildren);
        for ($i = 0; $i < $count; $i++) {
            $child = $rawChildren[$i];
            if (!$this->isRunInBox($child)) {
                $out[] = $child;
                continue;
            }
            $target = $this->runInTarget($rawChildren, $i);
            if ($target === null) {
                // "Otherwise, the run-in box becomes a block box."
                $child->style->set('display', new Keyword('block'));
                $out[] = $child;
                continue;
            }
            // "…the run-in box becomes the first inline box of the block
            // box." The element's own cascade rides along, so `font-weight`
            // / colour / `line-height` on the run-in still apply; only the
            // outer display type changes.
            // A replaced run-in becomes an ATOMIC inline: it has no child
            // boxes to carry across, so wrapping it in a plain `InlineBox`
            // would drop the element's rendering entirely (a
            // `display: run-in` `<img>` simply vanished).
            if ($this->isReplacedElementBox($child)) {
                $child->style->set('display', new Keyword('inline-block'));
                $this->prependInlineChild(
                    $target,
                    new AtomicInlineBox($child->element, $child->style),
                );
                continue;
            }
            $child->style->set('display', new Keyword('inline'));
            $inline = new InlineBox($child->element, $child->style);
            foreach ($child->children as $grandchild) {
                $inline->addChild($grandchild);
            }
            $this->prependInlineChild($target, $inline);
        }
        return $out;
    }

    /**
     * `true` when `$box` is the principal box generated for an element
     * whose computed display is `run-in`.
     *
     * The `BlockBox` test is load-bearing, not decoration: anonymous
     * wrappers and `TextBox` children carry their PARENT's element and
     * cascade, so a looser check would see the run-in's own text node as a
     * second run-in and rewrite the cascade out from under it.
     */
    private function isRunInBox(Box $box): bool
    {
        return $box instanceof BlockBox
            && $box->element !== null
            && $this->displayKeyword($box->style) === 'run-in';
    }

    /**
     * The block box a run-in at `$index` runs into, or `null` when it has to
     * stay a block. See {@see self::applyRunIn} for the rule set.
     *
     * @param list<Box> $children
     */
    private function runInTarget(array $children, int $index): ?Box
    {
        // "If the run-in box contains a block box, the run-in box becomes a
        // block box." Out-of-flow descendants don't count: they're not part
        // of the run-in's own inline content.
        foreach ($children[$index]->children as $grandchild) {
            if (!$this->isInlineLevel($grandchild)
                && !$this->isOutOfFlow($grandchild->style)
            ) {
                return null;
            }
        }
        $count = count($children);
        for ($j = $index + 1; $j < $count; $j++) {
            $candidate = $children[$j];
            if ($this->isCollapsibleWhitespaceBox($candidate)) {
                // Only whitespace that actually collapses away is
                // "nothing between". Under `white-space: pre` the space
                // between the run-in and the block is rendered content, so
                // the run-in has something after it and stays a block
                // (`run-in-basic-014`).
                if ($this->onlyCollapsibleWhitespace([$candidate], $candidate->style)) {
                    continue;
                }
                return null;
            }
            if ($this->isOutOfFlow($candidate->style)) {
                continue;
            }
            // A following run-in is not a block box yet — the run-in
            // before it therefore has nothing to run into and blocks out.
            if ($this->isRunInBox($candidate)) {
                return null;
            }
            if (!$candidate instanceof BlockBox) {
                return null;
            }
            return in_array(
                $this->displayKeyword($candidate->style),
                ['block', 'flow-root', 'list-item'],
                true,
            ) ? $candidate : null;
        }
        return null;
    }

    /**
     * Insert `$inline` as the first inline-level child of `$target`.
     *
     * `$target` was fully built (its own anonymous-block grouping already
     * ran), so when its first child is an anonymous block wrapping an
     * inline run the new box belongs INSIDE that wrapper — prepending at
     * the outer level would re-mix block and inline siblings that the
     * §3.4 pass had already separated.
     */
    /**
     * `true` when `$box`'s element is replaced — its rendering comes from
     * outside the CSS box tree, so it has no child boxes of its own.
     */
    private function isReplacedElementBox(Box $box): bool
    {
        if ($box->element === null) {
            return false;
        }
        return match (strtolower($box->element->localName)) {
            'img', 'canvas', 'video', 'audio', 'object', 'embed',
            'iframe', 'svg', 'svg:svg', 'math', 'input', 'textarea',
            'select', 'progress', 'meter' => true,
            default => false,
        };
    }

    private function prependInlineChild(Box $target, Box $inline): void
    {
        $first = $target->children[0] ?? null;
        if ($first instanceof AnonymousBlockBox) {
            array_unshift($first->children, $inline);
            return;
        }
        // A target whose children are block-level has no inline formatting
        // context to join: the run-in needs its own anonymous block ahead
        // of them, or the §3.4 invariant (a block container's children are
        // all-inline or all-block) breaks (`run-in-basic-005`).
        if ($this->containsBlockLevel($target->children)) {
            $anon = new AnonymousBlockBox(
                null,
                $this->cascade->anonymousFromParent($target->style),
            );
            $anon->addChild($inline);
            array_unshift($target->children, $anon);
            return;
        }
        array_unshift($target->children, $inline);
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

    /** @param list<Box> $children */
    private function containsInFlowBlockLevel(array $children): bool
    {
        foreach ($children as $c) {
            if (!$this->isInlineLevel($c) && !$this->isAbsolutelyPositioned($c->style)) {
                return true;
            }
        }
        return false;
    }

    /**
     * `position: absolute | fixed` only — NOT floats. A floated block child
     * still interacts with the inline box's layout in ways our flex /
     * multicol paths depend on, so it keeps triggering blockification;
     * only genuinely out-of-flow (abs-pos) children are skipped.
     */
    private function isAbsolutelyPositioned(CascadedValues $values): bool
    {
        $position = $values->get('position');
        return $position instanceof Keyword
            && in_array(strtolower($position->name), ['absolute', 'fixed'], true);
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
        // CSS 2.1 §9.7 / CSS Display 3 §2.7 — a pseudo-element is
        // blockified by going out of flow just like an element is.
        // `::before { position: absolute }` with no `display` rule of its
        // own computes to `inline`, and an inline out-of-flow box has no
        // layout path: it generated an InlineBox that the abs-pos
        // machinery never looked at, so the pseudo simply never painted.
        if ($this->isOutOfFlow($pseudoValues)
            && isset(self::OUT_OF_FLOW_BLOCKIFIED[$display])
        ) {
            $display = self::OUT_OF_FLOW_BLOCKIFIED[$display];
            $pseudoValues->set('display', new Keyword($display));
        }
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
        $pseudo->pseudoElement = $pseudoName;
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
     * Build the inline `::marker` text child for a `display: list-item`
     * box whose `list-style-position` is `inside`, or null when the box
     * isn't a list item, the marker is `outside` (the initial value —
     * {@see \Phpdftk\HtmlToPdf\Painter\Painter::paintListMarker()} draws
     * that one beside the principal box), or `list-style-type: none`.
     *
     * The marker string is the counter text plus the CSS Counter
     * Styles 3 §3 `.` suffix for the numeric styles, or the literal
     * bullet glyph for the three geometric ones. A trailing space
     * separates it from the item's content; being collapsible, it
     * disappears again when the item is empty.
     */
    private function insideListMarker(Element $element, CascadedValues $values): ?TextBox
    {
        if ($this->displayKeyword($values) !== 'list-item') {
            return null;
        }
        $position = $values->get('list-style-position');
        if (!$position instanceof Keyword || strtolower($position->name) !== 'inside') {
            return null;
        }
        $typeValue = $values->get('list-style-type');
        $type = $typeValue instanceof Keyword ? strtolower($typeValue->name) : 'disc';
        if ($type === 'none') {
            return null;
        }
        $text = match ($type) {
            'disc' => "\u{2022}",
            'circle' => "\u{25E6}",
            'square' => "\u{25AA}",
            'disclosure-open' => "\u{25BC}",
            'disclosure-closed' => "\u{25B6}",
            default => $this->formatCounter(
                \Phpdftk\HtmlToPdf\Layout\ListItemOrdinal::of($element),
                $type,
            ) . '.',
        };
        return new TextBox($element, $values, $text . ' ');
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
        // CSS Overflow 4 §5 — `line-clamp` clamps the lines of the block
        // container's whole formatting context, not just the ones the styled
        // box happens to lay out itself. `line-clamp` is not inherited, so an
        // anonymous wrapper dropped it and the clamp silently did nothing the
        // moment the container held any element child: `line-clamp: 4` around
        // an abspos plus five lines rendered all five.
        //
        // The effective count is resolved here and planted as the modern
        // property, so the wrapper needs no `-webkit-box` display of its own
        // to satisfy the legacy gate.
        $inheritedClamp = $this->effectiveLineClamp($values);
        if ($inheritedClamp !== null) {
            $anonValues->set('line-clamp', new \Phpdftk\Css\Value\Integer($inheritedClamp));
        }
        $anon = new AnonymousBlockBox(null, $anonValues);
        foreach ($inlineGroup as $c) {
            $anon->addChild($c);
        }
        $parent->addChild($anon);
    }


    /**
     * The effective `line-clamp` count on a block container: the modern
     * property, or `-webkit-line-clamp` when the legacy `-webkit-box` gate
     * is satisfied. Null when the box does not clamp.
     */
    private function effectiveLineClamp(CascadedValues $values): ?int
    {
        $modern = $values->get('line-clamp');
        if ($modern instanceof \Phpdftk\Css\Value\Integer && $modern->value > 0) {
            return $modern->value;
        }
        $legacy = $values->get('-webkit-line-clamp');
        if (!($legacy instanceof \Phpdftk\Css\Value\Integer) || $legacy->value <= 0) {
            return null;
        }
        $display = $values->get('display');
        if (!($display instanceof Keyword)) {
            return null;
        }
        $name = strtolower($display->name);
        if ($name !== '-webkit-box' && $name !== '-webkit-inline-box') {
            return null;
        }
        $orient = $values->get('-webkit-box-orient');
        if (!($orient instanceof Keyword)
            || strtolower($orient->name) !== 'vertical'
        ) {
            return null;
        }
        return $legacy->value;
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

    /**
     * True when `$box` is a table row-group (row-group / header-group /
     * footer-group). These currently generate a `BlockBox` (no dedicated
     * class) so we classify them by their resolved `display`.
     */
    private function isTableRowGroupBox(Box $box): bool
    {
        if (!$box instanceof BlockBox) {
            return false;
        }
        return in_array(
            $this->displayKeyword($box->style),
            ['table-row-group', 'table-header-group', 'table-footer-group'],
            true,
        );
    }

    /**
     * True when `$box` behaves as a table row group for the purposes of
     * CSS 2.1 §17.2.1 fixup.
     *
     * {@see isTableRowGroupBox} keys off the resolved `display`, which is
     * enough for `display: table-row-group` authored in CSS. It is NOT
     * enough for `<tbody>` / `<thead>` / `<tfoot>`: the UA sheet in
     * {@see \Phpdftk\HtmlToPdf\RendererOptions} maps those to
     * `display: block` (BlockLayout's `collectTableRows` walks through
     * them transparently instead). They must still be recognised here —
     * otherwise the "generate missing parents" pass would decide their
     * `<tr>` children are misparented and bury a second, anonymous table
     * inside every real `<table>`.
     */
    private function actsAsTableRowGroup(Box $box): bool
    {
        if ($this->isTableRowGroupBox($box)) {
            return true;
        }
        $local = $box->element !== null ? strtolower($box->element->localName) : '';
        return ($local === 'tbody' || $local === 'thead' || $local === 'tfoot')
            && $this->displayKeyword($box->style) === 'block';
    }

    /**
     * True when `$box` is a `display: inline-table` box.
     *
     * {@see makeBox} routes `inline-table` to an {@see AtomicInlineBox},
     * not a {@see TableBox}, so an `instanceof TableBox` test misses it —
     * and the "generate missing parents" pass would then decide the
     * inline-table's own rows were misparented and bury an anonymous
     * table inside it (caught as the two `*-width-applies-to-014`
     * regressions in CSS2/normal-flow). An inline-table IS a table
     * object, so its children need no parent generated.
     */
    private function isInlineTableBox(Box $box): bool
    {
        return $this->displayKeyword($box->style) === 'inline-table';
    }

    /**
     * CSS 2.1 §17.2.1 "generate missing parents" — wrap each run of
     * consecutive internal-table children of a NON-table parent in an
     * anonymous `table` box.
     *
     * Per spec the run is "C and all consecutive siblings of C that are
     * proper table children"; whitespace-only anonymous inlines sitting
     * between two internal table boxes are removed first ("remove
     * irrelevant boxes"), so they neither break a run nor survive into
     * the output. Whitespace that turns out NOT to be between two
     * internal boxes is handed back untouched.
     *
     * Bare `table-cell` children of the synthesised table are handed to
     * {@see wrapBareTableCellsInRows}, which supplies the anonymous
     * `table-row` the spec's first missing-parent clause asks for.
     *
     * @param  list<Box> $rawChildren
     * @return list<Box>
     */
    private function wrapMisparentedTableBoxesInTables(array $rawChildren, CascadedValues $values): array
    {
        if (!$this->containsMisparentedTableBox($rawChildren)) {
            return $rawChildren;
        }
        $out = [];
        /** @var list<Box> $run */
        $run = [];
        /** @var list<Box> $held */
        $held = [];
        foreach ($rawChildren as $child) {
            if ($this->needsAnonymousTableParent($child)) {
                $run[] = $child;
                // Whitespace between two internal table boxes is dropped.
                $held = [];
                continue;
            }
            if ($run !== [] && $this->isCollapsibleWhitespaceBox($child)) {
                $held[] = $child;
                continue;
            }
            if ($run !== []) {
                $out[] = $this->makeAnonymousTable($run, $values);
                $run = [];
            }
            foreach ($held as $h) {
                $out[] = $h;
            }
            $held = [];
            $out[] = $child;
        }
        if ($run !== []) {
            $out[] = $this->makeAnonymousTable($run, $values);
        }
        foreach ($held as $h) {
            $out[] = $h;
        }
        return $out;
    }

    /** @param list<Box> $children */
    private function containsMisparentedTableBox(array $children): bool
    {
        foreach ($children as $c) {
            if ($this->needsAnonymousTableParent($c)) {
                return true;
            }
        }
        return false;
    }

    /**
     * True when `$box` is an internal table box that cannot live under
     * the parent currently being assembled and therefore needs an
     * anonymous `table` synthesised for it (CSS 2.1 §17.2.1).
     *
     * A `table-column` is only misparented when its parent is neither a
     * `table-column-group` nor a table; since this test only runs for
     * non-table parents, every internal box reaching it is misparented —
     * except a column inside a column group, which our box tree models as
     * a `TableColumnBox` parent and which short-circuits before the call.
     *
     * §17.2.1 also calls a misparented `table-caption` box a candidate,
     * and that arm is DELIBERATELY not implemented here. Generating the
     * anonymous table is only half the job: the caption then has to be
     * sized and positioned as a caption of that table, and this renderer
     * cannot yet do that (a `display: table-caption` inside a real
     * `display: table` already lays out wrong). Wrapping a stray caption
     * therefore trades a structurally-correct box tree for a visibly
     * worse render — measured as 8 genuine regressions across
     * `css/css-writing-modes/{block-flow,line-box}-direction-*`, which
     * matched their reference to within 0.0033 before. Restore this arm
     * together with table-caption layout, not before it.
     */
    private function needsAnonymousTableParent(Box $box): bool
    {
        return $box instanceof TableCellBox
            || $box instanceof TableRowBox
            || $box instanceof TableColumnBox
            || $this->isTableRowGroupBox($box);
    }

    /** True when `$box` is a text box holding nothing but collapsible whitespace. */
    private function isCollapsibleWhitespaceBox(Box $box): bool
    {
        return $box instanceof TextBox
            && preg_match('/^[\s\x{200B}]*$/u', $box->text) === 1;
    }

    /**
     * Build the anonymous `table` box demanded by CSS 2.1 §17.2.1.
     *
     * The box is anonymous, so it takes the inherited properties of its
     * parent and the initial value of everything else — `border-spacing`
     * included, which is why a synthesised table never inherits the UA
     * sheet's `2px` (that lives on the `table` element selector, not on
     * an ancestor). `display` is NOT inherited, so it has to be stamped
     * explicitly: several layout paths read the keyword rather than the
     * box class (e.g. BlockLayout's `stackIsTableBody`).
     *
     * @param list<Box> $run
     */
    private function makeAnonymousTable(array $run, CascadedValues $values): TableBox
    {
        $anonValues = $this->cascade->anonymousFromParent($values);
        $anonValues->set('display', new Keyword('table'));
        $anon = new TableBox(null, $anonValues);
        foreach ($this->wrapBareTableCellsInRows($run, $anonValues) as $c) {
            $anon->addChild($c);
        }
        return $anon;
    }

    /**
     * CSS 2.1 §17.2.1 rule 3 — group consecutive misparented children
     * of a `table` / row-group (anything that is not a proper table
     * child: rows, row-groups, columns, column-groups, captions) into
     * anonymous `table-row` boxes. Collapsible whitespace between
     * proper table children is dropped rather than forced into a row.
     *
     * @param  list<Box> $rawChildren
     * @return list<Box>
     */
    private function wrapBareTableCellsInRows(array $rawChildren, CascadedValues $values): array
    {
        $out = [];
        /** @var list<Box> $pending */
        $pending = [];
        foreach ($rawChildren as $child) {
            if ($this->isProperTableChild($child)) {
                if ($pending !== []) {
                    $out[] = $this->makeAnonymousTableRow($pending, $values);
                    $pending = [];
                }
                $out[] = $child;
                continue;
            }
            // Collapsible whitespace between table-level items is not a
            // cell — drop it instead of manufacturing an empty row.
            if ($child instanceof TextBox
                && preg_match('/^[\s\x{200B}]*$/u', $child->text) === 1
            ) {
                continue;
            }
            $pending[] = $child;
        }
        if ($pending !== []) {
            $out[] = $this->makeAnonymousTableRow($pending, $values);
        }
        return $out;
    }

    /**
     * Build an anonymous `table-row` wrapping `$pending`. Non-cell
     * content inside the synthesised row still needs an anonymous cell
     * wrapper (rule 3 chains row → cell).
     *
     * @param list<Box> $pending
     */
    private function makeAnonymousTableRow(array $pending, CascadedValues $values): TableRowBox
    {
        $anon = new TableRowBox(null, $this->cascade->anonymousFromParent($values));
        foreach ($this->wrapBareTableRowChildrenInCells($pending, $values) as $c) {
            $anon->addChild($c);
        }
        return $anon;
    }

    /**
     * CSS 2.1 §17.2.1 rule 3 — wrap runs of non-cell children of a
     * `table-row` in anonymous `table-cell` boxes. Cells pass through
     * untouched; collapsible whitespace between cells is dropped.
     *
     * @param  list<Box> $rawChildren
     * @return list<Box>
     */
    private function wrapBareTableRowChildrenInCells(array $rawChildren, CascadedValues $values): array
    {
        $out = [];
        /** @var list<Box> $pending */
        $pending = [];
        foreach ($rawChildren as $child) {
            if ($child instanceof TableCellBox) {
                if ($pending !== []) {
                    $out[] = $this->makeAnonymousTableCell($pending, $values);
                    $pending = [];
                }
                $out[] = $child;
                continue;
            }
            if ($child instanceof TextBox
                && preg_match('/^[\s\x{200B}]*$/u', $child->text) === 1
            ) {
                continue;
            }
            $pending[] = $child;
        }
        if ($pending !== []) {
            $out[] = $this->makeAnonymousTableCell($pending, $values);
        }
        return $out;
    }

    /**
     * Build an anonymous `table-cell` wrapping the run `$pending`.
     *
     * @param list<Box> $pending
     */
    private function makeAnonymousTableCell(array $pending, CascadedValues $values): TableCellBox
    {
        $anon = new TableCellBox(null, $this->cascade->anonymousFromParent($values));
        foreach ($pending as $c) {
            $anon->addChild($c);
        }
        return $anon;
    }

    /**
     * True when `$box` is a proper child of a table / row-group per
     * CSS 2.1 §17.2.1: a row, a row-group, a column(-group), or a
     * caption. Such children pass through the anonymous-row fixup
     * untouched; bare cells and arbitrary flow content get wrapped.
     *
     * Our renderer models table row-groups (`<tbody>` / `<thead>` /
     * `<tfoot>`) as plain `BlockBox`es with `display: block` (see the
     * UA sheet) and relies on {@see BlockLayout::collectTableRows}
     * walking through them transparently. To stay consistent, any box
     * that directly contains a `table-row` is treated as a row-group
     * boundary here too — otherwise the fixup would bury the real rows
     * inside a synthesised row+cell and break every `<table><tbody>`.
     */
    private function isProperTableChild(Box $box): bool
    {
        return $box instanceof TableRowBox
            || $box instanceof TableColumnBox
            || $this->isTableRowGroupBox($box)
            || $this->isTableCaptionBox($box)
            || $this->containsTableRow($box);
    }

    /**
     * True when `$box` is a `<caption>` (or `display: table-caption`).
     * Captions sit above / below the table grid (CSS 2.1 §17.4.1) and
     * must not be swept into an anonymous row.
     */
    private function isTableCaptionBox(Box $box): bool
    {
        if ($box->element !== null
            && strtolower($box->element->localName) === 'caption'
        ) {
            return true;
        }
        return $this->displayKeyword($box->style) === 'table-caption';
    }

    /** True when any direct child of `$box` is a table row. */
    private function containsTableRow(Box $box): bool
    {
        foreach ($box->children as $c) {
            if ($c instanceof TableRowBox) {
                return true;
            }
        }
        return false;
    }

    /**
     * CSS 2.1 §9.7 / CSS Display 3 §2.7 — the `display` an out-of-flow
     * box blockifies to. Covers the internal-table displays as well as
     * the inline family; `inline-table` is the one value that goes to
     * `table` rather than `block`.
     *
     * @var array<string, string>
     */
    private const OUT_OF_FLOW_BLOCKIFIED = [
        'inline' => 'block',
        'inline-block' => 'block',
        // CSS Display 3 §2.7 — `run-in` is an inline-level outer
        // display type, so an out-of-flow run-in blockifies and never
        // runs into anything.
        'run-in' => 'block',
        'inline-flex' => 'flex',
        'inline-grid' => 'grid',
        'inline-table' => 'table',
        'table-row-group' => 'block',
        'table-header-group' => 'block',
        'table-footer-group' => 'block',
        'table-row' => 'block',
        'table-column' => 'block',
        'table-column-group' => 'block',
        'table-cell' => 'block',
        'table-caption' => 'block',
    ];

    private function makeBox(Element $element, CascadedValues $values, string $display): Box
    {
        return match ($display) {
            'inline' => new InlineBox($element, $values),
            'inline-block', 'inline-table', 'inline-grid'
                => new AtomicInlineBox($element, $values),
            'table' => new TableBox($element, $values),
            'table-row' => new TableRowBox($element, $values),
            'table-cell' => new TableCellBox($element, $values),
            'table-column', 'table-column-group' => new TableColumnBox($element, $values),
            // CSS Display 3 §2.6 — `inline-flex` differs from `flex` only in
            // its OUTER display type: both establish a flex formatting
            // context for their children. Routing it to AtomicInlineBox
            // meant the flex algorithm never ran; worse, since flex items
            // are blockified, the §9.2.1.1 inline-splits-around-block pass
            // below then promoted the atomic to an anonymous BLOCK, so the
            // items stacked vertically. Shrink-to-fit sizing for the inline
            // outer type is handled by `flexContainerNeedsShrinkToFit`.
            'flex', 'inline-flex' => new FlexBox($element, $values),
            'grid' => new GridBox($element, $values),
            default => new BlockBox($element, $values),
        };
    }

    /**
     * Properties projected from the HTML cascade onto inline-SVG
     * descendants. Deliberately an allowlist: an inline `<svg>` is
     * painted atomically from a serialised copy of its subtree, so
     * anything written here is the ONLY route by which a document
     * stylesheet reaches those elements — but writing the full
     * computed set would also stamp every CSS initial onto them and
     * shift paint behaviour for properties the SVG renderer reads
     * opportunistically.
     *
     * Mirrors `SvgCascadeProjector::PROJECTED` (which does the same
     * job for `<style>` blocks INSIDE a standalone SVG document);
     * extend both together.
     */
    private const array SVG_PROJECTED = [
        'fill',
        'stroke',
        'fill-rule',
        'stroke-width',
        'stroke-linecap',
        'stroke-linejoin',
        'stroke-miterlimit',
        'stroke-dasharray',
        'stroke-dashoffset',
        'fill-opacity',
        'stroke-opacity',
        'opacity',
        'font-family',
        'font-size',
        'font-weight',
        'font-style',
        'transform',
        'transform-origin',
        'transform-box',
        'clip-path',
        'mask',
        'stop-color',
        'stop-opacity',
        'text-shadow',
    ];

    /**
     * Project the document cascade onto an inline `<svg>` subtree.
     *
     * The subtree never generates boxes of its own — the painter
     * serialises it and hands it to the SVG renderer, which reads
     * presentation attributes and inline `style`. A document
     * stylesheet rule like `rect { transform-box: fill-box }` would
     * otherwise never reach the element it names. Writing the
     * cascaded value into `style` puts it on the one path the SVG
     * side already reads.
     *
     * Author intent on the element itself wins: an element carrying
     * its own presentation attribute for a property is left alone,
     * matching `SvgCascadeProjector`.
     *
     * @param list<Stylesheet> $sheets
     */
    private function projectCssOntoSvgSubtree(
        Element $root,
        array $sheets,
        CascadedValues $rootValues,
    ): void {
        foreach ($root->children() as $child) {
            $values = $this->cascade->computeFor(
                $this->svgCascadeSheets($child, $sheets),
                $child,
                $rootValues,
            );
            $this->substituteVarInSvgAttributes($child, $values);
            $declarations = [];
            foreach (self::SVG_PROJECTED as $property) {
                if ($child->getAttribute($property) !== null) {
                    continue;
                }
                if (!$values->has($property)) {
                    continue;
                }
                $value = $values->get($property);
                if ($value === null) {
                    continue;
                }
                $declarations[] = $property . ': ' . $value->toCss();
            }
            if ($declarations !== []) {
                $existing = $child->getAttribute('style');
                $projection = implode('; ', $declarations);
                $child->setAttribute(
                    'style',
                    $existing === null || $existing === ''
                        ? $projection
                        // The element's own inline style comes LAST so it
                        // still wins over the projected cascade.
                        : $projection . '; ' . $existing,
                );
            }
            $this->projectCssOntoSvgSubtree($child, $sheets, $values);
        }
    }

    /**
     * SVG presentation attributes fed into the cascade for inline-SVG
     * descendants.
     *
     * Deliberately limited to the INHERITED paint / text properties (plus
     * `color`, which `currentColor` resolves against). A presentation
     * attribute for a non-inherited property already reaches the SVG
     * renderer through `Element::presentationOrStyle`, so synthesising a
     * declaration for it would buy nothing while risking a bad parse of
     * SVG-only grammars (`transform="translate(1 2)"` is not a CSS
     * `<transform-list>` in every legal form).
     *
     * @var list<string>
     */
    private const array SVG_PRESENTATION_ATTRIBUTES = [
        'fill',
        'stroke',
        'fill-opacity',
        'stroke-opacity',
        'fill-rule',
        'stroke-width',
        'stroke-linecap',
        'stroke-linejoin',
        'stroke-miterlimit',
        'stroke-dasharray',
        'stroke-dashoffset',
        'font-family',
        'font-size',
        'font-weight',
        'font-style',
        'color',
        'visibility',
    ];

    /**
     * The `*` selector every synthesised presentation-attribute rule
     * carries. Parsed once — `SelectorParser::parse` is not free and this
     * runs per inline-SVG element.
     */
    private static ?\Phpdftk\Css\Selector\SelectorList $svgPresentationSelector = null;

    /**
     * `$sheets` with `$element`'s presentation attributes prepended as a
     * synthesised author-origin stylesheet.
     *
     * SVG 2 §6.7 — a presentation attribute is an author-origin CSS
     * declaration whose specificity is zero, ordered before every other
     * author declaration. Modelling it as a real sheet (rather than
     * reading the attribute at paint time) is what makes an inherited
     * property set that way reach DESCENDANTS: the cascade for the child
     * inherits from the parent's cascaded values, and without the sheet
     * those values carry the property's initial instead.
     *
     * The rule's selector is `*`, which would match any element — safe
     * because the returned sheet is only ever passed to a `computeFor`
     * call for `$element` itself.
     *
     * @param list<Stylesheet> $sheets
     * @return list<Stylesheet>
     */
    private function svgCascadeSheets(Element $element, array $sheets): array
    {
        $declarations = [];
        foreach (self::SVG_PRESENTATION_ATTRIBUTES as $name) {
            $raw = $element->getAttribute($name);
            if ($raw === null || trim($raw) === '') {
                continue;
            }
            // SVG 2 §6.7 — `inherit` is the one CSS-wide keyword a
            // presentation attribute accepts; anything the value parser
            // rejects makes the attribute invalid and it is dropped.
            try {
                $value = $this->attrValueParser()->parseFromString($raw);
            } catch (\Throwable) {
                continue;
            }
            $declarations[] = new \Phpdftk\Css\Sheet\Declaration($name, $value, important: false);
        }
        if ($declarations === []) {
            return $sheets;
        }
        self::$svgPresentationSelector ??= \Phpdftk\Css\Selector\SelectorParser::parse('*');
        $rule = new \Phpdftk\Css\Sheet\StyleRule(self::$svgPresentationSelector, $declarations);
        return [
            new Stylesheet([$rule], \Phpdftk\Css\Sheet\Origin::Author),
            ...$sheets,
        ];
    }

    /** Lazily-built value parser shared by the presentation-attribute sheet. */
    private function attrValueParser(): \Phpdftk\Css\ValueParser
    {
        return $this->attrParser ??= new \Phpdftk\Css\ValueParser();
    }

    /**
     * SVG attributes in which a `var()` reference is substituted.
     *
     * CSS Variables 1 §3 only substitutes inside a CSS PROPERTY's value,
     * and SVG 2 §6.7 makes exactly the presentation attributes into
     * properties — the geometry ones (§7.4) plus the styling ones. An
     * attribute that is not a presentation attribute (`viewBox`,
     * `points`, `preserveAspectRatio`, `href`) keeps its literal text,
     * which is what a browser does too.
     *
     * @var list<string>
     */
    private const array SVG_VAR_SUBSTITUTABLE = [
        // SVG 2 §7.4 — geometry properties.
        'cx', 'cy', 'r', 'rx', 'ry', 'x', 'y', 'width', 'height', 'd',
        // SVG 2 §6.7 / §13 — styling presentation attributes.
        'fill', 'stroke', 'fill-opacity', 'stroke-opacity', 'opacity',
        'fill-rule', 'stroke-width', 'stroke-linecap', 'stroke-linejoin',
        'stroke-miterlimit', 'stroke-dasharray', 'stroke-dashoffset',
        'font-family', 'font-size', 'font-weight', 'font-style',
        'color', 'visibility', 'stop-color', 'stop-opacity',
        'clip-path', 'mask', 'transform', 'transform-origin',
    ];

    /**
     * Resolve `var()` inside `$element`'s presentation attributes,
     * rewriting each attribute with the substituted text.
     *
     * The value has to land back on the ATTRIBUTE rather than in the
     * projected `style`: `Element::presentationOrStyle` reads the
     * attribute first, so leaving `r="var(--radii)"` in place would keep
     * feeding the raw text to the length parser (which reads it as 0 and
     * drops the shape) no matter what the cascade resolved.
     *
     * CSS Variables 1 §4 — a `var()` that resolves to nothing and has no
     * fallback is invalid at computed-value time. The attribute is
     * blanked rather than left literal, so the element behaves as if it
     * had never carried it.
     */
    private function substituteVarInSvgAttributes(Element $element, CascadedValues $values): void
    {
        foreach (self::SVG_VAR_SUBSTITUTABLE as $name) {
            $raw = $element->getAttribute($name);
            if ($raw === null || stripos($raw, 'var(') === false) {
                continue;
            }
            try {
                $parsed = $this->attrValueParser()->parseFromString($raw);
            } catch (\Throwable) {
                $element->removeAttribute($name);
                continue;
            }
            $resolved = self::substituteVarValue($parsed, $values, 0);
            if ($resolved === null) {
                $element->removeAttribute($name);
                continue;
            }
            $element->setAttribute($name, $resolved->toCss());
        }
    }

    /**
     * Replace every `var()` node in `$value` with what the element's
     * custom properties resolve it to, or the `var()` fallback. Returns
     * null when any reference resolves to nothing — per CSS Variables 1
     * §4 that poisons the WHOLE declaration, not just the one token.
     */
    private static function substituteVarValue(
        \Phpdftk\Css\Value\Value $value,
        CascadedValues $values,
        int $depth,
    ): ?\Phpdftk\Css\Value\Value {
        // Matches the cascade's own guard against a cyclic
        // `--a: var(--b); --b: var(--a)` chain.
        if ($depth > 32) {
            return null;
        }
        if ($value instanceof \Phpdftk\Css\Value\CustomProperty) {
            $referenced = $values->get($value->name);
            if ($referenced !== null) {
                return self::substituteVarValue($referenced, $values, $depth + 1);
            }
            if ($value->fallback !== null) {
                return self::substituteVarValue($value->fallback, $values, $depth + 1);
            }
            return null;
        }
        if ($value instanceof \Phpdftk\Css\Value\ValueList) {
            $out = [];
            foreach ($value->values as $child) {
                $resolved = self::substituteVarValue($child, $values, $depth + 1);
                if ($resolved === null) {
                    return null;
                }
                $out[] = $resolved;
            }
            return new \Phpdftk\Css\Value\ValueList($out, $value->separator);
        }
        return $value;
    }

    /**
     * Whether the cascade carries an `aspect-ratio` with an actual ratio
     * in it (`1/1`, `auto 1/1`) rather than the bare `auto` initial.
     *
     * CSS Sizing 4 §5.1 — such a ratio makes the other axis definite
     * from the one the author sized, so the default object size must not
     * step in and pin it. `<svg style="width:100px;aspect-ratio:auto 1/1">`
     * is a 100x100 square, not 100x150.
     */
    private static function hasExplicitAspectRatio(CascadedValues $values): bool
    {
        $ratio = $values->get('aspect-ratio');
        if ($ratio === null) {
            return false;
        }
        return !($ratio instanceof Keyword && strtolower($ratio->name) === 'auto');
    }

    /**
     * Whether `$element` sits inside another `<svg>`. SVG 2 §8.2's
     * sizing rules are about the OUTERMOST svg only - a nested `<svg>`
     * is an inner viewport sized by the SVG pipeline, not a replaced
     * box in the HTML flow.
     */
    private static function hasSvgAncestor(Element $element): bool
    {
        for ($n = $element->parentNode; $n !== null; $n = $n->parentNode) {
            if ($n instanceof Element && self::foreignContentKind($n) === 'svg') {
                return true;
            }
        }
        return false;
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
        return self::foreignContentKind($element) !== null;
    }

    /**
     * Foreign-content roots that must keep their atomic-inline box even
     * when out of flow.
     *
     * `<math>` stays excluded: `paintInlineMath` resolves its own origin
     * from the cascade (`resolveInlineAbsoluteOrigin`) and the generic
     * block pipeline drops the inline-math paint entirely (see the
     * `mathml/presentation-markup/spaces/space-3` regression note).
     *
     * `<svg>` is NOT excluded. CSS Display 3 §2.7 blockifies an
     * out-of-flow box, and CSS 2.1 §9.6 / §10.3.7 then place it from its
     * containing block plus `left` / `top`. The painter already accepts a
     * replaced `BlockBox` and routes an `<svg>` element to
     * `paintInlineSvg`, so blockification gives the element real abspos
     * geometry — which every consumer of `Box::$geometry` (background,
     * border, `clip-path`, the SVG viewport itself) then agrees on.
     * Keeping it inline left `left` / `top` unapplied: the box painted at
     * its static-flow position instead.
     */
    private function isInlinePositionedForeignRoot(Element $element): bool
    {
        return self::foreignContentKind($element) === 'math';
    }

    /**
     * Classify an element as a foreign-content root: `'svg'`, `'math'`,
     * or `null`. Handles both the standard namespaced form (`<svg>` in
     * the SVG namespace) AND the prefixed XHTML form (`<svg:svg>` /
     * `<math:math>`), which the HTML parser leaves as a plain element
     * with `localName` like `"svg:svg"` and the HTML namespace — the
     * prefix then identifies the foreign content.
     */
    public static function foreignContentKind(Element $element): ?string
    {
        $tag = strtolower($element->localName);
        $colon = strrpos($tag, ':');
        $prefixed = $colon !== false;
        $local = $prefixed ? substr($tag, $colon + 1) : $tag;
        $ns = $element->namespaceUri();
        if ($local === 'math' && ($prefixed || $ns === \Phpdftk\Mathml\Parser::MATHML_NS)) {
            return 'math';
        }
        if ($local === 'svg' && ($prefixed || $ns === \Phpdftk\Svg\Parser::SVG_NS)) {
            return 'svg';
        }
        return null;
    }

    /**
     * CSS 2.1 §9.2.1.1 — the anonymous block box that wraps a block split
     * out of an inline is anonymous: the inline's own border / padding /
     * margin / background belong to the inline FRAGMENTS around the block,
     * not to the wrapper (which would otherwise paint a full-width border /
     * background band across the block). Clone the split inline's cascade
     * and neutralise those box-decoration longhands while preserving
     * everything else — notably `position` (so `position: relative` on the
     * inline still shifts the block half) and the originating `$element`
     * (the caller keeps it for `<a>` link rects).
     */
    private function blockInInlineWrapperValues(CascadedValues $values): CascadedValues
    {
        $reset = clone $values;
        $none = new Keyword('none');
        $zero = new Length(0.0, LengthUnit::Px);
        foreach (['top', 'right', 'bottom', 'left'] as $side) {
            $reset->set("border-$side-style", $none);
            $reset->set("border-$side-width", $zero);
            $reset->set("padding-$side", $zero);
            $reset->set("margin-$side", $zero);
        }
        $reset->set('background-color', new Color(0.0, 0.0, 0.0, 0.0));
        $reset->set('background-image', $none);
        return $reset;
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
            // CSS Logical 1 §4.1: resolve `inline-start` / `inline-end` to a
            // physical side so a logical float is blockified + pulled out of
            // flow exactly when it resolves to left/right (horizontal mode).
            if ($name === 'inline-start' || $name === 'inline-end') {
                $name = WritingMode::fromStyle($values)->physicalEdge($name);
            }
            if ($name === 'left' || $name === 'right') {
                return true;
            }
        }
        return false;
    }

    /**
     * `true` when the cascaded display establishes a flex or grid
     * formatting context, so its children are flex / grid items whose
     * min/max sizing is owned by the flex / grid algorithm rather than
     * the replaced-element box-gen constraint pass.
     */
    private function isFlexOrGridContainer(CascadedValues $values): bool
    {
        return in_array(
            $this->displayKeyword($values),
            ['flex', 'inline-flex', 'grid', 'inline-grid'],
            true,
        );
    }

    /**
     * Flatten a cascaded value that is either a single keyword or a
     * space-separated list of keywords into its lower-cased names.
     *
     * @return list<string>
     */
    private function keywordNames(?\Phpdftk\Css\Value\Value $value): array
    {
        if ($value instanceof Keyword) {
            return [strtolower($value->name)];
        }
        if ($value instanceof \Phpdftk\Css\Value\ValueList) {
            $names = [];
            foreach ($value->values as $v) {
                if ($v instanceof Keyword) {
                    $names[] = strtolower($v->name);
                }
            }
            return $names;
        }
        return [];
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
                $outside = $names[0];
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
                $this->applyPresentationalAttributes($n, $childCascade, $this->isFlexOrGridContainer($values));
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
     * Clamp a replaced element's DECLARED size by its length `min-*` /
     * `max-*` before the intrinsic ratio transfers it to the other axis.
     *
     * CSS 2.1 §10.4 — the ratio transfers the USED size, which is the
     * declared one after the min/max constraint violation is resolved.
     * Deriving from the raw declared value instead makes
     * `width: 0; min-width: 100px` on a 300x150 image compute a zero
     * height, so the image vanishes rather than rendering 100x50.
     *
     * Percentages and keywords are left alone: they have no basis here
     * and are resolved by the layout-time replaced clamp.
     */
    private function clampDeclaredReplacedSize(
        float $declared,
        CascadedValues $values,
        string $minProperty,
        string $maxProperty,
    ): float {
        $max = $values->get($maxProperty);
        if ($max instanceof \Phpdftk\Css\Value\Length) {
            $declared = min($declared, $max->value);
        }
        $min = $values->get($minProperty);
        if ($min instanceof \Phpdftk\Css\Value\Length) {
            // The minimum wins over the maximum (§10.4).
            $declared = max($declared, $min->value);
        }
        return max(0.0, $declared);
    }

    /**
     * The `cellpadding` of the nearest ancestor `<table>`, in px, or null
     * when there is none (or its value is not a valid HTML dimension).
     *
     * `cellpadding` is declared on the table but applies to its cells, so
     * the cell has to look upwards for it. Stops at the first table so a
     * nested table's own `cellpadding` wins for its own cells.
     *
     * Null means the attribute is absent, in which case the UA sheet's
     * 1px default stands.
     */
    private function cellPaddingFromAncestorTable(Element $cell): ?float
    {
        for ($node = $cell->parentElement; $node !== null; $node = $node->parentElement) {
            if (strtolower($node->localName) !== 'table') {
                continue;
            }
            $raw = $node->getAttribute('cellpadding');
            return $raw === null ? null : $this->parseHtmlLength($raw);
        }
        return null;
    }

    /**
     * Marks which of a replaced element's width / height this generator
     * DERIVED from the natural size rather than the author declaring it.
     *
     * The natural size is baked into the cascade here, so by layout time
     * an author `height: 100px` and a ratio-derived one are the same
     * `Length`. Anything that later re-sizes the box — flexing, in
     * particular — needs to know which axis it may rewrite through the
     * aspect ratio and which is a real author constraint.
     *
     * Values: `both`, `width`, `height`.
     */
    public const string REPLACED_DERIVED_SIZE = '--phpdftk-replaced-derived-size';

    /**
     * Pre-CSS HTML attributes that map to CSS properties — `<img width>`,
     * `<img height>`, `<font color>` etc. Per HTML 5 §15.3, these
     * "presentational attributes" map into the user-agent style sheet at
     * the lowest specificity. We apply them after the cascade so any
     * author CSS still wins, but they provide the size to layout for
     * elements that lack explicit `width` / `height` declarations.
     */
    private function applyPresentationalAttributes(Element $element, CascadedValues $values, bool $parentIsFlexOrGrid = false): void
    {
        // CSS Values 5 §11 — substitute typed `attr()` into property values
        // from the element's attributes, before the presentational sizing
        // below reads width/height.
        $this->resolveAttrFunctions($element, $values);
        $tag = strtolower($element->localName);
        // HTML §15.3.8 — the table presentational attributes map onto CSS:
        // `cellspacing` on the table becomes `border-spacing`, and
        // `cellpadding` becomes the `padding` of every cell it contains.
        // Both are "presentational hints", so author CSS still wins; a
        // value that fails the HTML dimension rules is ignored.
        if ($tag === 'table') {
            // `border-spacing` INHERITS, so it is present in every
            // element's cascade map and `has()` cannot say whether this
            // table declared it. The attribute therefore wins outright
            // here — unlike `cellpadding` below, where the non-inherited
            // `padding` lets author CSS be detected and preferred. The
            // 2px HTML default stays in the UA sheet.
            $spacing = $this->parseHtmlLength($element->getAttribute('cellspacing') ?? '');
            if ($spacing !== null) {
                $values->set('border-spacing', new \Phpdftk\Css\Value\Length(
                    $spacing,
                    \Phpdftk\Css\Value\LengthUnit::Px,
                ));
            }
        }
        // HTML §15.3.3 — the legacy colour / font presentational hints.
        // `color` and `font-family` INHERIT, so `has()` is true on every
        // element and cannot tell an author declaration from an inherited
        // value; `wasDeclared()` answers the question a hint actually
        // needs ("did a declaration win this property here?"), which is
        // what keeps `<font color="x" style="color:fuchsia">` fuchsia.
        if ($tag === 'font') {
            $color = $this->parseLegacyColor($element->getAttribute('color') ?? '');
            if ($color !== null && !$values->wasDeclared('color')) {
                $values->set('color', $color);
            }
            $face = trim($element->getAttribute('face') ?? '');
            if ($face !== '' && !$values->wasDeclared('font-family')) {
                $values->set('font-family', new \Phpdftk\Css\Value\StringValue($face));
            }
        }
        // `bgcolor` on `<body>`, `<table>`, `<tr>`, `<td>`, `<th>` and the
        // legacy `<marquee>` maps to `background-color`; `<body text>` to
        // `color`.
        $bgColor = $this->parseLegacyColor($element->getAttribute('bgcolor') ?? '');
        if ($bgColor !== null && !$values->wasDeclared('background-color')) {
            $values->set('background-color', $bgColor);
        }
        if ($tag === 'body') {
            $textColor = $this->parseLegacyColor($element->getAttribute('text') ?? '');
            if ($textColor !== null && !$values->wasDeclared('color')) {
                $values->set('color', $textColor);
            }
        }
        if ($tag === 'table') {
            // HTML §15.3.9 — `<table width>` is a dimension value
            // (percentages allowed) mapped onto `width`, and `<table
            // align>` floats the table left / right or centres it with
            // auto inline margins. The attribute values are matched
            // ASCII case-insensitively.
            $rawWidth = $element->getAttribute('width');
            if ($rawWidth !== null && !$values->has('width')) {
                $pct = $this->parseHtmlPercentage($rawWidth);
                $len = $pct === null ? $this->parseHtmlLength($rawWidth) : null;
                if ($pct !== null) {
                    $values->set('width', new \Phpdftk\Css\Value\Percentage($pct));
                } elseif ($len !== null) {
                    $values->set('width', new \Phpdftk\Css\Value\Length(
                        $len,
                        \Phpdftk\Css\Value\LengthUnit::Px,
                    ));
                }
            }
            $align = strtolower(trim($element->getAttribute('align') ?? ''));
            if (($align === 'left' || $align === 'right') && !$values->has('float')) {
                $values->set('float', new Keyword($align));
            } elseif ($align === 'center' || $align === 'middle') {
                foreach (['margin-left', 'margin-right'] as $side) {
                    if (!$values->has($side)) {
                        $values->set($side, new Keyword('auto'));
                    }
                }
            }
        }
        if ($tag === 'td' || $tag === 'th') {
            // Same caveat as `cellspacing`: the UA sheet's own
            // `padding: 1px` is indistinguishable from an author
            // declaration once the cascade has run, so an explicit
            // `cellpadding` overrides outright. Only the attribute is
            // handled here — the 1px default belongs to the UA sheet,
            // where a stylesheet that opts out of UA rules does not
            // inherit it.
            $padding = $this->cellPaddingFromAncestorTable($element);
            if ($padding !== null) {
                foreach (['padding-top', 'padding-right', 'padding-bottom', 'padding-left'] as $side) {
                    $values->set($side, new \Phpdftk\Css\Value\Length(
                        $padding,
                        \Phpdftk\Css\Value\LengthUnit::Px,
                    ));
                }
            }
        }
        // SVG 2 §8.2 — `width` / `height` on the outermost `<svg>` are
        // presentation attributes mapping onto the CSS `width` / `height`
        // properties, so an inline `<svg width="100" height="60">` is a
        // 100x60 CSS-px replaced box. Without this the atomic-inline box
        // lays out at zero size: it neither occupies space in the line nor
        // gives the painter geometry, and the painter's attribute fallback
        // then has to guess a size (which it did at 0.75x, shrinking every
        // inline SVG relative to the HTML around it).
        //
        // Presentation attributes sort into the author origin at the very
        // start, so any real declaration wins — hence the `has()` guard,
        // matching the `<img>` path below.
        if (self::foreignContentKind($element) === 'svg' && !self::hasSvgAncestor($element)) {
            foreach (['width', 'height'] as $attr) {
                if ($values->has($attr)) {
                    continue;
                }
                $raw = $element->getAttribute($attr);
                if ($raw === null) {
                    continue;
                }
                $pct = $this->parseHtmlPercentage($raw);
                if ($pct !== null) {
                    $values->set($attr, new \Phpdftk\Css\Value\Percentage($pct));
                    continue;
                }
                $px = $this->parseHtmlLength($raw);
                if ($px !== null) {
                    $values->set($attr, new \Phpdftk\Css\Value\Length($px, \Phpdftk\Css\Value\LengthUnit::Px));
                }
            }
            // SVG 2 §8.2 — the initial value of `width` / `height` on the
            // outermost svg is `auto`, which resolves as `100%`. A
            // percentage against an auto-sized parent is indefinite, so
            // the outermost svg has NO intrinsic size, and with no
            // `viewBox` no intrinsic ratio either — CSS Images 3 §5.3 then
            // sizes the replaced box from the default object size,
            // 300x150. That is why a dimensionless `<svg>` is 300x150 in
            // every browser instead of collapsing to its content.
            //
            // Each axis defaults independently: `<svg height="200">` is
            // 300 wide, and without the width half such an svg laid out
            // zero-wide and painted NOTHING. A `viewBox` is excluded on
            // both axes: it supplies an intrinsic ratio, so the missing
            // axis derives from the other instead.
            foreach (['width' => 300.0, 'height' => 150.0] as $axis => $default) {
                if (!$values->has($axis)
                    && $element->getAttribute($axis) === null
                    && $element->getAttribute('viewBox') === null
                    && !self::hasExplicitAspectRatio($values)
                ) {
                    $values->set(
                        $axis,
                        new \Phpdftk\Css\Value\Length($default, \Phpdftk\Css\Value\LengthUnit::Px),
                    );
                }
            }
        }
        // HTML §15.3.4 — `hspace` / `vspace` on embedded content map to
        // the horizontal / vertical margins, and `border` to a solid
        // border of that pixel width on all four sides. All three are
        // presentational hints, so an author declaration still wins.
        if (in_array($tag, ['img', 'object', 'embed', 'iframe', 'applet'], true)) {
            foreach ([
                'hspace' => ['margin-left', 'margin-right'],
                'vspace' => ['margin-top', 'margin-bottom'],
            ] as $attr => $properties) {
                $raw = $element->getAttribute($attr);
                if ($raw === null) {
                    continue;
                }
                // Parsed with the "rules for parsing dimension values",
                // which accept a trailing `%` — `<img hspace="10%">` is a
                // percentage margin, not 10px.
                $pct = $this->parseHtmlPercentage($raw);
                $len = $pct === null ? $this->parseHtmlLength($raw) : null;
                if ($pct === null && $len === null) {
                    continue;
                }
                foreach ($properties as $property) {
                    if ($values->has($property)) {
                        continue;
                    }
                    $values->set($property, $pct !== null
                        ? new \Phpdftk\Css\Value\Percentage($pct)
                        : new \Phpdftk\Css\Value\Length((float) $len, \Phpdftk\Css\Value\LengthUnit::Px));
                }
            }
            // `border` is parsed with the rules for parsing NON-NEGATIVE
            // INTEGERS (which stop at the first non-digit, so `border="50%"`
            // is 50 pixels) and only takes effect when greater than zero —
            // `border="0"` leaves the element's own border style alone
            // rather than forcing `solid` at zero width.
            $border = $this->parseHtmlNonNegativeInteger($element->getAttribute('border') ?? '');
            if ($border !== null && $border > 0) {
                foreach (['top', 'right', 'bottom', 'left'] as $side) {
                    if (!$values->has("border-$side-width")) {
                        $values->set("border-$side-width", new \Phpdftk\Css\Value\Length(
                            (float) $border,
                            \Phpdftk\Css\Value\LengthUnit::Px,
                        ));
                    }
                    if (!$values->has("border-$side-style")) {
                        $values->set("border-$side-style", new Keyword('solid'));
                    }
                }
            }
        }
        // HTML §15.3.10 — `<td nowrap>` / `<th nowrap>` unconditionally
        // suppress wrapping in the cell. `white-space` INHERITS, so it is
        // present in every cascade map and `has()` cannot tell an author
        // declaration from the inherited default; the attribute therefore
        // wins outright, same caveat as `cellspacing` above.
        if (($tag === 'td' || $tag === 'th') && $element->getAttribute('nowrap') !== null) {
            $values->set('white-space', new Keyword('nowrap'));
        }
        if ($tag === 'img' || $tag === 'embed' || $tag === 'iframe' || $tag === 'video') {
            foreach (['width', 'height'] as $attr) {
                // Author CSS wins over a presentational hint — INCLUDING an
                // explicit `auto`. Treating `auto` as "unspecified" let the
                // attribute overwrite it, which breaks the single commonest
                // image reset there is (`img { height: auto }` alongside
                // `width`/`height` attributes): the declared `auto` is what
                // asks for the intrinsic ratio to size the other axis, and
                // silently replacing it with the attribute pins both axes.
                if ($values->has($attr)) {
                    continue;
                }
                $raw = $element->getAttribute($attr);
                if ($raw === null) {
                    continue;
                }
                // HTML 5 §2.4.4.4 dimension values allow a trailing `%`;
                // browsers map `<img width="100%">` to a CSS percentage on
                // the replaced box (resolved against its containing block at
                // layout time), not the intrinsic size.
                $pct = $this->parseHtmlPercentage($raw);
                if ($pct !== null) {
                    $values->set($attr, new \Phpdftk\Css\Value\Percentage($pct));
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
                // CSS Sizing 3 §5.2 — `min-content` / `max-content` /
                // `fit-content` on a replaced element resolve to its
                // intrinsic (natural) size, so for dimension derivation
                // they behave like `auto`.
                $wUnset = !$values->has('width') || $this->isReplacedSizeAuto($wValue);
                $hUnset = !$values->has('height') || $this->isReplacedSizeAuto($hValue);
                // CSS Sizing 4 §5.1 — an author `aspect-ratio: <ratio>` (a bare
                // ratio, no `auto`) OVERRIDES the image's natural ratio when
                // deriving the missing dimension. Capture it before the natural
                // ratio is exposed below. `auto <ratio>` defers to the natural
                // ratio for replaced elements, so it is not captured here.
                $authorRatioWH = $this->explicitReplacedAspectRatio($values);
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
                            // Both dimensions auto / intrinsic: start at the
                            // natural size, then apply any length `min/max-
                            // width|height` preserving the aspect ratio
                            // (CSS 2.1 §10.4 constraint-violation table —
                            // e.g. `max-width: 100px` on a 150x150 image
                            // yields 100x100, not 100x150). Percentage /
                            // keyword min-max are left to the layout-time
                            // replaced clamp. A flex / grid *item*'s min/max
                            // is resolved by the flex / grid algorithm (which
                            // also transfers through the ratio), so leave the
                            // natural size untouched there to avoid double-
                            // applying the constraint.
                            [$uw, $uh] = $parentIsFlexOrGrid
                                ? [(float) $nw, (float) $nh]
                                : $this->constrainReplacedNaturalSize((float) $nw, (float) $nh, $values);
                            $values->set('width', new \Phpdftk\Css\Value\Length($uw, \Phpdftk\Css\Value\LengthUnit::Px));
                            $values->set('height', new \Phpdftk\Css\Value\Length($uh, \Phpdftk\Css\Value\LengthUnit::Px));
                            // Both axes came from the natural size, so
                            // neither is an author constraint: a later
                            // ratio transfer may rewrite either.
                            $values->set(
                                self::REPLACED_DERIVED_SIZE,
                                new \Phpdftk\Css\Value\Keyword('both'),
                            );
                        } elseif ($wUnset && $hValue instanceof \Phpdftk\Css\Value\Length && $nh > 0) {
                            // Under `box-sizing: border-box`, the declared
                            // height includes the padding/border vertical
                            // inset, so derive the content height first,
                            // then add the horizontal inset to land on the
                            // declared width that produces the right
                            // content-width-to-content-height ratio.
                            [$hInset, $vInset, $borderBox] = $this->presentationalInsetsAndBoxSizing($values);
                            $declaredH = $this->clampDeclaredReplacedSize(
                                $hValue->value,
                                $values,
                                'min-height',
                                'max-height',
                            );
                            $contentH = $borderBox ? max(0.0, $declaredH - $vInset) : $declaredH;
                            $contentW = $authorRatioWH !== null
                                ? $contentH * $authorRatioWH
                                : $contentH * ($nw / $nh);
                            $declaredW = $borderBox ? ($contentW + $hInset) : $contentW;
                            $values->set(
                                'width',
                                new \Phpdftk\Css\Value\Length(
                                    $declaredW,
                                    \Phpdftk\Css\Value\LengthUnit::Px,
                                ),
                            );
                            // The author declared the HEIGHT; this width is
                            // ratio-derived.
                            $values->set(
                                self::REPLACED_DERIVED_SIZE,
                                new \Phpdftk\Css\Value\Keyword('width'),
                            );
                        } elseif ($hUnset && $wValue instanceof \Phpdftk\Css\Value\Length && $nw > 0) {
                            [$hInset, $vInset, $borderBox] = $this->presentationalInsetsAndBoxSizing($values);
                            $declaredW = $this->clampDeclaredReplacedSize(
                                $wValue->value,
                                $values,
                                'min-width',
                                'max-width',
                            );
                            $contentW = $borderBox ? max(0.0, $declaredW - $hInset) : $declaredW;
                            $contentH = $authorRatioWH !== null
                                ? $contentW / $authorRatioWH
                                : $contentW * ($nh / $nw);
                            $declaredH = $borderBox ? ($contentH + $vInset) : $contentH;
                            $values->set(
                                'height',
                                new \Phpdftk\Css\Value\Length(
                                    $declaredH,
                                    \Phpdftk\Css\Value\LengthUnit::Px,
                                ),
                            );
                            // The author declared the WIDTH; this height is
                            // ratio-derived.
                            $values->set(
                                self::REPLACED_DERIVED_SIZE,
                                new \Phpdftk\Css\Value\Keyword('height'),
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
            // HTML 5 §4.12.5 — the width/height content attributes are the
            // canvas's INTRINSIC (bitmap) size, not presentational CSS
            // width/height. So they become the used size only when BOTH
            // axes are `auto`; if the author fixed one axis, the other is
            // derived from the intrinsic ratio (CSS 2.1 §10.3.2 replaced
            // sizing), so it must stay `auto`. The ratio is always exposed
            // via `aspect-ratio` for that derivation. (Setting the unfixed
            // axis to the raw attribute pixel value would suppress the
            // ratio and stretch the canvas — e.g. `<canvas width=1
            // height=1 style="height: 100px">` must be a 100×100 square,
            // not 1×100.)
            $widthAuto = !$values->has('width')
                || $this->isAutoLength($values->get('width'));
            $heightAuto = !$values->has('height')
                || $this->isAutoLength($values->get('height'));
            if ($widthAuto && $heightAuto) {
                // Same CSS 2.1 §10.4 treatment the `<img>` path gets: a
                // length min/max constrains the natural size as a PAIR, so
                // `max-width: 120px; max-height: 100px` on an 8000x8000
                // canvas is 100x100, not the 120x100 that clamping each
                // axis on its own produces. Flex / grid items are left
                // alone — their algorithm owns the min/max transfer.
                [$usedW, $usedH] = $parentIsFlexOrGrid
                    ? [$canvasW, $canvasH]
                    : $this->constrainReplacedNaturalSize($canvasW, $canvasH, $values);
                $values->set('width', new \Phpdftk\Css\Value\Length($usedW, \Phpdftk\Css\Value\LengthUnit::Px));
                $values->set('height', new \Phpdftk\Css\Value\Length($usedH, \Phpdftk\Css\Value\LengthUnit::Px));
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
     * `true` when a replaced element's width/height is `auto` or one of
     * the intrinsic sizing keywords (`min-content` / `max-content` /
     * `fit-content`), all of which resolve to the natural dimension for
     * dimension derivation. {@see isAutoLength} but also matching the
     * content keywords.
     */
    private function isReplacedSizeAuto(?\Phpdftk\Css\Value\Value $v): bool
    {
        return $v instanceof Keyword
            && in_array(strtolower($v->name), ['auto', 'min-content', 'max-content', 'fit-content'], true);
    }

    /**
     * CSS Values 5 §11 — replace typed `attr()` occurrences in an element's
     * cascaded property values with the coerced attribute value (or fallback).
     * Recurses into value lists / functions so `max(attr(...), ...)` resolves.
     */
    private function resolveAttrFunctions(Element $element, CascadedValues $values): void
    {
        foreach ($values->all() as $prop => $value) {
            $resolved = $this->substituteAttr($value, $element);
            if ($resolved !== null && $resolved !== $value) {
                $values->set($prop, $resolved);
            }
        }
    }

    /**
     * Recursively substitute AttrFunction nodes inside a value. Returns the
     * (possibly new) value, or null when a bare `attr()` has no attribute and
     * no fallback (guaranteed-invalid — leave the original in place).
     */
    private function substituteAttr(
        \Phpdftk\Css\Value\Value $value,
        Element $element,
    ): ?\Phpdftk\Css\Value\Value {
        if ($value instanceof \Phpdftk\Css\Value\AttrFunction) {
            return $this->coerceAttr($value, $element);
        }
        if ($value instanceof \Phpdftk\Css\Value\ValueList) {
            $changed = false;
            $out = [];
            foreach ($value->values as $v) {
                $r = $this->substituteAttr($v, $element);
                if ($r === null) {
                    return $value; // an unresolvable attr — keep the list as-is
                }
                $changed = $changed || $r !== $v;
                $out[] = $r;
            }
            return $changed ? new \Phpdftk\Css\Value\ValueList($out, $value->separator) : $value;
        }
        if ($value instanceof \Phpdftk\Css\Value\CssFunction) {
            $changed = false;
            $out = [];
            foreach ($value->arguments as $v) {
                $r = $this->substituteAttr($v, $element);
                if ($r === null) {
                    return $value;
                }
                $changed = $changed || $r !== $v;
                $out[] = $r;
            }
            return $changed ? new \Phpdftk\Css\Value\CssFunction($value->name, $out) : $value;
        }
        return $value;
    }

    /**
     * Coerce a single `attr()` to a typed value: read the attribute, cast per
     * its `type()`/unit, or use the fallback when missing / the cast fails.
     * Null when the attribute is absent and no fallback is given.
     */
    private function coerceAttr(
        \Phpdftk\Css\Value\AttrFunction $attr,
        Element $element,
    ): ?\Phpdftk\Css\Value\Value {
        $raw = $element->getAttribute($attr->attributeName);
        if ($raw !== null) {
            $cast = $this->castAttrValue(trim($raw), $attr->typeOrUnit);
            if ($cast !== null) {
                return $cast;
            }
        }
        if ($attr->fallback !== null) {
            return $this->substituteAttr($attr->fallback, $element);
        }
        return null;
    }

    /**
     * Cast a raw attribute string to a CSS value per the `attr()` type. Handles
     * the modern `type(<length>|<color>|<number>|<integer>|<percentage>)` form
     * and a bare CSS unit (old syntax). Returns null on an invalid cast.
     */
    private function castAttrValue(string $raw, ?string $type): ?\Phpdftk\Css\Value\Value
    {
        $this->attrParser ??= new \Phpdftk\Css\ValueParser();
        $syntax = null;
        if ($type !== null && preg_match('/^type\(\s*(.+?)\s*\)$/i', $type, $m) === 1) {
            $syntax = strtolower($m[1]);
        }
        if ($syntax !== null) {
            $parsed = $raw === '' ? null : $this->attrParser->parseFromString($raw);
            return match ($syntax) {
                '<length>', '<length-percentage>' => $parsed instanceof \Phpdftk\Css\Value\Length
                    || $parsed instanceof \Phpdftk\Css\Value\Percentage ? $parsed : null,
                '<color>' => $parsed instanceof \Phpdftk\Css\Value\Color ? $parsed : null,
                '<number>' => $parsed instanceof \Phpdftk\Css\Value\Number
                    || $parsed instanceof \Phpdftk\Css\Value\Integer ? $parsed : null,
                '<integer>' => $parsed instanceof \Phpdftk\Css\Value\Integer ? $parsed : null,
                '<percentage>' => $parsed instanceof \Phpdftk\Css\Value\Percentage ? $parsed : null,
                default => null,
            };
        }
        // Old syntax: a bare unit (`attr(data-w px)`) means the attribute is a
        // unitless number scaled by that unit.
        if ($type !== null && strtolower($type) !== 'string' && is_numeric($raw)) {
            $unit = \Phpdftk\Css\Value\LengthUnit::tryFrom(strtolower($type));
            if ($unit !== null) {
                return new \Phpdftk\Css\Value\Length((float) $raw, $unit);
            }
        }
        return null;
    }

    /**
     * The author's `aspect-ratio` as a width/height ratio (float), but ONLY
     * for a bare `<ratio>` — a `<number>` (`n` = n/1) or `w / h`. Returns null
     * when unset, non-positive, or `auto`-qualified: CSS Sizing 4 §5.1 says
     * `auto <ratio>` defers to a replaced element's NATURAL ratio, so it must
     * not override the intrinsic-ratio derivation.
     */
    private function explicitReplacedAspectRatio(\Phpdftk\Css\Cascade\CascadedValues $values): ?float
    {
        if (!$values->has('aspect-ratio')) {
            return null;
        }
        $ar = $values->get('aspect-ratio');
        if ($ar instanceof \Phpdftk\Css\Value\Number || $ar instanceof \Phpdftk\Css\Value\Integer) {
            return $ar->value > 0.0 ? (float) $ar->value : null;
        }
        if ($ar instanceof \Phpdftk\Css\Value\ValueList
            && $ar->separator === \Phpdftk\Css\Value\ListSeparator::Slash
            && count($ar->values) === 2
        ) {
            $w = $ar->values[0];
            $h = $ar->values[1];
            // A bare ratio has plain numeric operands; the `auto <ratio>` form
            // carries the `auto` keyword in the first slash operand (a nested
            // space list), which fails this instanceof check and is skipped.
            if (($w instanceof \Phpdftk\Css\Value\Number || $w instanceof \Phpdftk\Css\Value\Integer)
                && ($h instanceof \Phpdftk\Css\Value\Number || $h instanceof \Phpdftk\Css\Value\Integer)
                && $w->value > 0.0 && $h->value > 0.0
            ) {
                return (float) $w->value / (float) $h->value;
            }
        }
        return null;
    }

    /**
     * CSS 2.1 §10.4 — given a replaced element's natural size and a
     * cascaded-values bundle, apply any *length* `min/max-width|height`
     * while preserving the aspect ratio. Percentage and keyword
     * constraints are skipped here (no containing block at box-gen time;
     * the layout-time replaced clamp handles those).
     *
     * `box-sizing: border-box` makes the min/max properties describe the
     * BORDER box (CSS Sizing 3 §6.2), so the constraints are pulled into
     * content-box space before the table runs and the result is pushed
     * back out — the caller writes the values into `width` / `height`,
     * which the same `box-sizing` will re-interpret.
     *
     * @return array{0: float, 1: float} used [width, height]
     */
    private function constrainReplacedNaturalSize(float $w, float $h, \Phpdftk\Css\Cascade\CascadedValues $values): array
    {
        if ($h <= 0.0 || $w <= 0.0) {
            return [$w, $h];
        }
        [$hInset, $vInset, $borderBox] = $this->presentationalInsetsAndBoxSizing($values);
        $len = static function (?\Phpdftk\Css\Value\Value $v): ?float {
            return $v instanceof \Phpdftk\Css\Value\Length && $v->value >= 0.0 ? $v->value : null;
        };
        $toContent = static function (?float $value, float $inset) use ($borderBox): ?float {
            if ($value === null) {
                return null;
            }
            return $borderBox ? max(0.0, $value - $inset) : $value;
        };
        [$usedW, $usedH] = $this->replacedConstraintTable(
            $w,
            $h,
            $toContent($len($values->get('min-width')), $hInset),
            $toContent($len($values->get('max-width')), $hInset),
            $toContent($len($values->get('min-height')), $vInset),
            $toContent($len($values->get('max-height')), $vInset),
        );
        return $borderBox
            ? [$usedW + $hInset, $usedH + $vInset]
            : [$usedW, $usedH];
    }

    /**
     * The CSS 2.1 §10.4 constraint-violation table, transcribed.
     *
     * The table is NOT a sequence of independent clamps: when both axes
     * violate a constraint the winning axis is the one whose violation
     * ratio is larger, and the other axis is then clamped by its OPPOSITE
     * constraint. Applying `max` then `min` per axis (what this used to
     * do) gets the single-constraint rows right and every combined row
     * wrong — `min-width` + `max-height` on a square image produced a
     * ratio-preserving box instead of the spec's `(min-w, max-h)`.
     *
     * All sizes are content-box. Absent constraints are 0 / ∞.
     *
     * @return array{0: float, 1: float}
     */
    private function replacedConstraintTable(
        float $w,
        float $h,
        ?float $minW,
        ?float $maxW,
        ?float $minH,
        ?float $maxH,
    ): array {
        $minWv = $minW ?? 0.0;
        $minHv = $minH ?? 0.0;
        $maxWv = $maxW ?? INF;
        $maxHv = $maxH ?? INF;
        // A min that exceeds its max wins outright (§10.4 note).
        $maxWv = max($maxWv, $minWv);
        $maxHv = max($maxHv, $minHv);

        $wTooBig = $w > $maxWv;
        $wTooSmall = $w < $minWv;
        $hTooBig = $h > $maxHv;
        $hTooSmall = $h < $minHv;

        if ($wTooBig && $hTooBig) {
            return ($maxWv / $w) <= ($maxHv / $h)
                ? [$maxWv, max($minHv, $maxWv * $h / $w)]
                : [max($minWv, $maxHv * $w / $h), $maxHv];
        }
        if ($wTooSmall && $hTooSmall) {
            return ($minWv / $w) <= ($minHv / $h)
                ? [min($maxWv, $minHv * $w / $h), $minHv]
                : [$minWv, min($maxHv, $minWv * $h / $w)];
        }
        if ($wTooSmall && $hTooBig) {
            return [$minWv, $maxHv];
        }
        if ($wTooBig && $hTooSmall) {
            return [$maxWv, $minHv];
        }
        if ($wTooBig) {
            return [$maxWv, max($maxWv * $h / $w, $minHv)];
        }
        if ($wTooSmall) {
            return [$minWv, min($minWv * $h / $w, $maxHv)];
        }
        if ($hTooBig) {
            return [max($maxHv * $w / $h, $minWv), $maxHv];
        }
        if ($hTooSmall) {
            return [min($minHv * $w / $h, $maxWv), $minHv];
        }
        return [$w, $h];
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
    /**
     * HTML 5 §2.4.4.4 — a dimension attribute value ending in `%` is a
     * percentage. Returns the numeric percentage (e.g. `100` for
     * `"100%"`), or null when the value isn't a percentage form. Callers
     * that only accept absolute pixel sizes (e.g. a `<canvas>` bitmap)
     * skip this and use {@see parseHtmlLength}.
     */
    private function parseHtmlPercentage(string $raw): ?float
    {
        $raw = trim($raw);
        if (preg_match('/^(\d+(?:\.\d+)?)%$/', $raw, $m) === 1) {
            return (float) $m[1];
        }
        return null;
    }

    /**
     * HTML §15.5.12 — floor a text-entry widget's `line-height` at the
     * used value of `normal`, by replacing anything smaller with the
     * `normal` keyword itself.
     *
     * The `normal` multiplier has to agree with
     * {@see \Phpdftk\HtmlToPdf\Layout\InlineLayout::resolveLineHeight()},
     * which is where `normal` is actually turned into pixels; comparing
     * against a different constant here would floor at the wrong place.
     */
    private function floorTextEntryLineHeight(CascadedValues $values): void
    {
        $fontSizeValue = $values->get('font-size');
        $fontSize = $fontSizeValue instanceof \Phpdftk\Css\Value\Length
            ? $fontSizeValue->value
            : 16.0;
        $normal = $fontSize * 1.2;
        $declared = $values->get('line-height');
        $used = match (true) {
            $declared instanceof \Phpdftk\Css\Value\Number => $fontSize * $declared->value,
            $declared instanceof \Phpdftk\Css\Value\Integer => $fontSize * $declared->value,
            $declared instanceof \Phpdftk\Css\Value\Percentage => $fontSize * ($declared->value / 100.0),
            $declared instanceof \Phpdftk\Css\Value\Length => $declared->value,
            default => $normal,
        };
        if ($used < $normal) {
            $values->set('line-height', new Keyword('normal'));
        }
    }

    /**
     * HTML 5 §2.4.6 "rules for parsing a legacy colour value". Returns
     * null only for the two genuine error cases — the empty string and
     * `transparent` — because the algorithm's tail is total: anything
     * else is coerced to SOME colour (`color="x"` really is black).
     */
    private function parseLegacyColor(string $raw): ?\Phpdftk\Css\Value\Color
    {
        $value = trim($raw, " \t\n\r\f");
        if ($value === '' || strcasecmp($value, 'transparent') === 0) {
            return null;
        }
        $named = \Phpdftk\Css\Value\NamedColors::lookup(strtolower($value));
        if ($named !== null) {
            return $named;
        }
        // `#rgb` shorthand keeps its own expansion before the generic tail.
        if (preg_match('/^#([0-9a-f])([0-9a-f])([0-9a-f])$/i', $value, $m) === 1) {
            return new \Phpdftk\Css\Value\Color(
                hexdec($m[1] . $m[1]) / 255.0,
                hexdec($m[2] . $m[2]) / 255.0,
                hexdec($m[3] . $m[3]) / 255.0,
                1.0,
            );
        }
        if (strlen($value) > 128) {
            $value = substr($value, 0, 128);
        }
        if (str_starts_with($value, '#')) {
            $value = substr($value, 1);
        }
        // Every non-hex character becomes `0`, then the string is padded
        // to a multiple of three so it splits into equal R / G / B runs.
        $value = preg_replace('/[^0-9a-f]/i', '0', $value) ?? '0';
        if ($value === '') {
            $value = '0';
        }
        while (strlen($value) % 3 !== 0) {
            $value .= '0';
        }
        $componentLength = intdiv(strlen($value), 3);
        $components = [
            substr($value, 0, $componentLength),
            substr($value, $componentLength, $componentLength),
            substr($value, 2 * $componentLength, $componentLength),
        ];
        // Keep at most the leading two significant hex digits of each run.
        foreach ($components as $i => $component) {
            if (strlen($component) > 2) {
                $trimmed = ltrim($component, '0');
                $component = $trimmed === '' ? '0' : $trimmed;
                $component = strlen($component) > 2 ? substr($component, 0, 2) : $component;
            }
            $components[$i] = str_pad($component, 2, '0', STR_PAD_LEFT);
        }
        return new \Phpdftk\Css\Value\Color(
            hexdec($components[0]) / 255.0,
            hexdec($components[1]) / 255.0,
            hexdec($components[2]) / 255.0,
            1.0,
        );
    }

    /**
     * HTML 5 §2.4.4.2 "rules for parsing non-negative integers" — skip
     * leading whitespace, then collect a run of ASCII digits. Trailing
     * junk is IGNORED rather than rejected, which is what makes
     * `<img border="50%">` a 50-pixel border.
     */
    private function parseHtmlNonNegativeInteger(string $raw): ?int
    {
        if (preg_match('/^[ \t\n\r\f]*(\d+)/', $raw, $m) !== 1) {
            return null;
        }
        return (int) $m[1];
    }

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
