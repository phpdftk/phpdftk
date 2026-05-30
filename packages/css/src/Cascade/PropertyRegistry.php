<?php

declare(strict_types=1);

namespace Phpdftk\Css\Cascade;

use Phpdftk\Css\Value\Color;
use Phpdftk\Css\Value\ColorSpace;
use Phpdftk\Css\Value\Keyword;
use Phpdftk\Css\Value\Length;
use Phpdftk\Css\Value\LengthUnit;
use Phpdftk\Css\Value\ListSeparator;
use Phpdftk\Css\Value\Number;
use Phpdftk\Css\Value\Percentage;
use Phpdftk\Css\Value\StringValue;
use Phpdftk\Css\Value\Value;
use Phpdftk\Css\Value\ValueList;

/**
 * Registry of CSS properties recognised by the cascade. Each entry binds a
 * lower-cased property name to its `PropertyDefinition` (initial value,
 * inherits flag).
 *
 * The cascade consults this registry for two things:
 *  1. When the cascade encounters `initial`, return the initial value here.
 *  2. When walking inheritance, properties with `inherits=false` reset to
 *     initial on each element rather than copying the parent's value.
 *
 * Phase 1D.3 ships a pragmatic subset (~50 properties — colour, font,
 * box-model, text, basic decoration). Sub-phase 1E onwards will add
 * properties as box-generation / layout grows. The registry is additive;
 * registering a duplicate name throws.
 */
final class PropertyRegistry
{
    /** @var array<string, PropertyDefinition> */
    private array $definitions = [];

    public function register(PropertyDefinition $def): void
    {
        $name = strtolower($def->name);
        if (isset($this->definitions[$name])) {
            throw new \LogicException("Property '$name' already registered");
        }
        $this->definitions[$name] = $def;
    }

    public function get(string $name): ?PropertyDefinition
    {
        return $this->definitions[strtolower($name)] ?? null;
    }

    public function has(string $name): bool
    {
        return isset($this->definitions[strtolower($name)]);
    }

    /** @return array<string, PropertyDefinition> */
    public function all(): array
    {
        return $this->definitions;
    }

    /**
     * Default registry populated with the Phase-1 MVP property surface.
     * Sufficient to cascade the invoice fixture; additional properties are
     * added by host code calling `register()` for now (1E sub-phases will
     * formalise the surface).
     */
    public static function default(): self
    {
        $r = new self();
        $black = new Color(0, 0, 0, 1, ColorSpace::sRGB);
        $transparent = new Color(0, 0, 0, 0, ColorSpace::sRGB);
        $zero = new Length(0, LengthUnit::Px);
        $initial = static fn(string $name, Value $v, bool $inh = false): PropertyDefinition
            => new PropertyDefinition($name, $v, $inh);

        // Color & background.
        $r->register($initial('color', $black, true));
        $r->register($initial('background-color', $transparent));
        $r->register($initial('background-image', new Keyword('none')));
        $r->register($initial('background-repeat', new Keyword('repeat')));
        $r->register($initial('background-position', new ValueList([], ListSeparator::Space)));
        $r->register($initial('background-size', new Keyword('auto')));
        // CSS Backgrounds 3 §3.5 — `background-clip` & `background-origin`.
        // `clip` controls the painted area; `origin` controls the
        // positioning reference. Both initial to `border-box` and
        // `padding-box` respectively per spec. Neither inherits.
        $r->register($initial('background-clip', new Keyword('border-box')));
        $r->register($initial('background-origin', new Keyword('padding-box')));
        // Print-irrelevant but registered so author CSS isn't dropped.
        $r->register($initial('background-attachment', new Keyword('scroll')));
        $r->register($initial('opacity', new Number(1.0)));

        // Font & text.
        $r->register($initial('font-family', new ValueList([new StringValue('serif')], ListSeparator::Comma), true));
        $r->register($initial('font-size', new Length(16.0, LengthUnit::Px), true));
        $r->register($initial('font-style', new Keyword('normal'), true));
        $r->register($initial('font-weight', new Keyword('normal'), true));
        $r->register($initial('line-height', new Keyword('normal'), true));
        $r->register($initial('text-align', new Keyword('start'), true));
        // CSS Text 3 §7.4 — `text-align-last` controls the alignment of
        // the last line in a justified block. `auto` (initial) defers
        // to the spec's default: start-aligned when text-align: justify
        // / start / end / left / right; matches text-align otherwise.
        // Inherits per spec.
        $r->register($initial('text-align-last', new Keyword('auto'), true));
        $r->register($initial('text-decoration', new Keyword('none')));
        $r->register($initial('text-decoration-line', new Keyword('none')));
        // CSS Text Decoration 4 §3: the initial value is `currentColor`.
        // Consumers resolve the keyword against the element's `color` at
        // use-time so an `<a>` with `color: blue` paints a blue underline
        // even though `text-decoration-color` was never explicitly set.
        $r->register($initial('text-decoration-color', new Keyword('currentcolor')));
        $r->register($initial('text-decoration-style', new Keyword('solid')));
        // CSS Text Decoration 4 §4 — `auto` defers to the font's
        // OS/2 underline metrics; an explicit `<length>` (or
        // `<percentage>` relative to the font size) overrides.
        $r->register($initial('text-decoration-thickness', new Keyword('auto')));
        $r->register($initial('text-underline-offset', new Keyword('auto')));
        $r->register($initial('text-shadow', new Keyword('none'), true));
        $r->register($initial('text-transform', new Keyword('none'), true));
        // CSS Text 3 §7.5: `text-justify` controls the justification
        // algorithm used when `text-align: justify`. `auto` (initial)
        // lets the UA pick a reasonable default; `none` disables
        // justification entirely (the line falls back to its declared
        // text-align direction). `inter-word` / `inter-character`
        // accepted but resolve as `auto` at Phase 1.
        $r->register($initial('text-justify', new Keyword('auto'), true));
        $r->register($initial('text-indent', $zero, true));
        $r->register($initial('letter-spacing', new Keyword('normal'), true));
        $r->register($initial('word-spacing', new Keyword('normal'), true));
        $r->register($initial('white-space', new Keyword('normal'), true));
        $r->register($initial('direction', new Keyword('ltr'), true));
        $r->register($initial('unicode-bidi', new Keyword('normal')));
        $r->register($initial('writing-mode', new Keyword('horizontal-tb'), true));
        $r->register($initial('text-orientation', new Keyword('mixed'), true));
        $r->register($initial('vertical-align', new Keyword('baseline')));
        // CSS UI 4 — pointer, user-select, cursor are runtime-only on
        // print but author CSS shouldn't be dropped at cascade time.
        $r->register($initial('cursor', new Keyword('auto'), true));
        $r->register($initial('user-select', new Keyword('auto')));
        $r->register($initial('pointer-events', new Keyword('auto'), true));
        // CSS UI 4 — `caret-color` (text caret colour) and CSS UI 4
        // `accent-color` (UA-widget accent). Both are runtime-only
        // for print but register so author CSS isn't dropped at the
        // cascade. `caret-color` inherits per spec.
        $r->register($initial('caret-color', new Keyword('auto'), true));
        $r->register($initial('accent-color', new Keyword('auto')));

        // Box model.
        $r->register($initial('display', new Keyword('inline')));
        $r->register($initial('position', new Keyword('static')));
        $r->register($initial('top', new Keyword('auto')));
        $r->register($initial('right', new Keyword('auto')));
        $r->register($initial('bottom', new Keyword('auto')));
        $r->register($initial('left', new Keyword('auto')));
        $r->register($initial('z-index', new Keyword('auto')));
        $r->register($initial('width', new Keyword('auto')));
        $r->register($initial('height', new Keyword('auto')));
        $r->register($initial('min-width', $zero));
        $r->register($initial('min-height', $zero));
        $r->register($initial('max-width', new Keyword('none')));
        $r->register($initial('max-height', new Keyword('none')));
        $r->register($initial('margin-top', $zero));
        $r->register($initial('margin-right', $zero));
        $r->register($initial('margin-bottom', $zero));
        $r->register($initial('margin-left', $zero));
        $r->register($initial('padding-top', $zero));
        $r->register($initial('padding-right', $zero));
        $r->register($initial('padding-bottom', $zero));
        $r->register($initial('padding-left', $zero));
        $r->register($initial('border-top-width', new Length(3.0, LengthUnit::Px))); // 'medium' = 3px
        $r->register($initial('border-right-width', new Length(3.0, LengthUnit::Px)));
        $r->register($initial('border-bottom-width', new Length(3.0, LengthUnit::Px)));
        $r->register($initial('border-left-width', new Length(3.0, LengthUnit::Px)));
        $r->register($initial('border-top-style', new Keyword('none')));
        $r->register($initial('border-right-style', new Keyword('none')));
        $r->register($initial('border-bottom-style', new Keyword('none')));
        $r->register($initial('border-left-style', new Keyword('none')));
        $r->register($initial('border-top-color', $black));
        $r->register($initial('border-right-color', $black));
        $r->register($initial('border-bottom-color', $black));
        $r->register($initial('border-left-color', $black));
        // Border-radius (CSS Backgrounds 3 §6) — Phase 1 reads a uniform
        // radius from the shorthand and the four corner longhands.
        $zero = new Length(0.0, LengthUnit::Px);
        $r->register($initial('border-top-left-radius', $zero));
        $r->register($initial('border-top-right-radius', $zero));
        $r->register($initial('border-bottom-right-radius', $zero));
        $r->register($initial('border-bottom-left-radius', $zero));

        // CSS UI 3 §4 outline — like border but doesn't take part in
        // layout (drawn outside the border edge).
        $r->register($initial('outline-color', $black));
        $r->register($initial('outline-style', new Keyword('none')));
        $r->register($initial('outline-width', new Length(3.0, LengthUnit::Px)));
        $r->register($initial('outline-offset', $zero));
        $r->register($initial('box-sizing', new Keyword('content-box')));
        // CSS Sizing 4 §4.2 — `aspect-ratio` constrains the box's
        // width-to-height ratio. `auto` (initial, non-inheriting)
        // means no constraint; `<ratio>` form is honoured by
        // BlockLayout when width OR height is auto.
        $r->register($initial('aspect-ratio', new Keyword('auto')));
        $r->register($initial('overflow', new Keyword('visible')));
        // CSS Overflow 3 §3.1 — per-axis longhands. `overflow` is
        // the shorthand (CSS Overflow 3 §3.2). Painter clips if
        // EITHER axis is non-visible.
        $r->register($initial('overflow-x', new Keyword('visible')));
        $r->register($initial('overflow-y', new Keyword('visible')));
        $r->register($initial('visibility', new Keyword('visible'), true));
        // CSS Images 3 §5.4 — `image-rendering` controls how the UA
        // scales raster images. Print rendering treats them as `auto`
        // regardless, but register so author CSS isn't dropped.
        $r->register($initial('image-rendering', new Keyword('auto')));
        // CSS Fonts 4 §6.5 — `font-kerning` toggles OpenType kern.
        // `auto` (initial) means the UA decides; inherits.
        $r->register($initial('font-kerning', new Keyword('auto'), true));
        $r->register($initial('font-feature-settings', new Keyword('normal'), true));
        $r->register($initial('font-variation-settings', new Keyword('normal'), true));
        // CSS Compositing 1 — `isolation` controls stacking context;
        // print medium has no blending compositing layers but register
        // so cascade keeps the value.
        $r->register($initial('isolation', new Keyword('auto')));
        $r->register($initial('mix-blend-mode', new Keyword('normal')));
        // CSS Color Adjustment 1 — `print-color-adjust` (initial `economy`)
        // tells the UA whether it may adjust colours for print
        // (downsampling, removing bg). `color-scheme` hints
        // light / dark preference. `forced-color-adjust` opts out of
        // forced-colors (high contrast) on Windows. All print-irrelevant
        // for our default-economy output but registered so author CSS
        // isn't dropped. All inherit per spec.
        $r->register($initial('print-color-adjust', new Keyword('economy'), true));
        $r->register($initial('color-scheme', new Keyword('normal'), true));
        $r->register($initial('forced-color-adjust', new Keyword('auto'), true));

        // CSS Images 3 §5: `object-fit` controls how a replaced element
        // (currently `<img>`) scales within its declared width × height.
        // Initial `fill` matches the legacy "stretch to box" behaviour.
        $r->register($initial('object-fit', new Keyword('fill')));
        $r->register($initial('object-position', new Keyword('center')));

        // Generated content (CSS Generated Content 3) — only the host's
        // `::before` / `::after` pseudo-elements read `content`.
        $r->register($initial('content', new Keyword('normal')));
        $r->register($initial('counter-reset', new Keyword('none')));
        $r->register($initial('counter-increment', new Keyword('none')));
        // CSS Generated Content 3 §3.1: `quotes` controls the strings
        // emitted by `open-quote` / `close-quote` in `content`.
        // Initial `auto` — UA picks typographic defaults; explicit value
        // is a space-separated list of paired strings. Inherits.
        $r->register($initial('quotes', new Keyword('auto'), true));

        // CSS UI 3 §6.2 text-overflow — when the content overflows the
        // single-line box, replace the visible tail with U+2026 HORIZONTAL
        // ELLIPSIS.
        $r->register($initial('text-overflow', new Keyword('clip')));

        // CSS Tables 3 §10. Phase-1 doesn't act on these values yet
        // (cells always paint their own borders, no spacing), but
        // registering them prevents author CSS from being dropped at
        // computed-value time.
        $r->register($initial('border-collapse', new Keyword('separate'), true));
        $r->register($initial('border-spacing', new Length(0.0, LengthUnit::Px), true));
        $r->register($initial('table-layout', new Keyword('auto')));
        $r->register($initial('caption-side', new Keyword('top'), true));
        $r->register($initial('empty-cells', new Keyword('show'), true));

        // CSS Text 3 §11.2 — `tab-size` integer (number of advance
        // spaces) or length. Initial value 8, inheriting.
        $r->register($initial('tab-size', new \Phpdftk\Css\Value\Integer(8), true));

        // CSS Text 3 §5 word-break / overflow-wrap.
        $r->register($initial('word-break', new Keyword('normal'), true));
        $r->register($initial('overflow-wrap', new Keyword('normal'), true));
        $r->register($initial('word-wrap', new Keyword('normal'), true));

        // CSS Text 4 §6 — text-wrap controls how text wraps inside
        // the inline formatting context. `auto` (initial) lets the
        // engine choose; `balance` reflows to balance line lengths,
        // `pretty` minimises bad breaks at the cost of more passes,
        // `stable` keeps existing breaks when re-laid-out.
        $r->register($initial('text-wrap', new Keyword('auto'), true));
        $r->register($initial('text-wrap-style', new Keyword('auto'), true));
        // CSS Text 4 §7 — hyphenation control. `hyphens: auto`
        // enables automatic hyphenation when a hyphenation pattern
        // is available; `manual` only breaks at U+00AD soft hyphens.
        $r->register($initial('hyphens', new Keyword('manual'), true));
        $r->register($initial('hyphenate-character', new Keyword('auto'), true));
        $r->register($initial('hyphenate-limit-chars', new Keyword('auto'), true));
        // CSS Text 4 §10 — text-spacing-trim defaults to
        // space-first (CJK spec) so cascade preserves author intent.
        $r->register($initial('text-spacing-trim', new Keyword('space-all'), true));
        $r->register($initial('text-spacing', new Keyword('normal'), true));
        $r->register($initial('text-autospace', new Keyword('normal'), true));

        // CSS Text Decoration 4 §8 — text-emphasis (CJK ruby
        // decoration marks). Inherits.
        $r->register($initial('text-emphasis', new Keyword('none'), true));
        $r->register($initial('text-emphasis-color', new Keyword('currentcolor'), true));
        $r->register($initial('text-emphasis-position', new Keyword('over'), true));
        $r->register($initial('text-emphasis-style', new Keyword('none'), true));
        // CSS Text Decoration 4 §1.6 — decoration-skip-ink.
        $r->register($initial('text-decoration-skip-ink', new Keyword('auto'), true));

        // CSS Inline 3 §6 — text-box-trim controls trimming of
        // half-leading space above ascenders / below descenders so
        // a heading sits tighter against its background. Layout uses
        // these to size line boxes; missing values cascade to none.
        $r->register($initial('text-box-trim', new Keyword('none'), true));
        $r->register($initial('text-box-edge', new Keyword('auto'), true));
        $r->register($initial('initial-letter', new Keyword('normal'), true));

        // CSS Sizing 4 §6 — contain-intrinsic-size declares a
        // placeholder size for an element with size containment so
        // layout can proceed without measuring its contents. The
        // longhands let authors set width / height (logical) /
        // block / inline independently. Print medium doesn't use
        // size containment but we register so author CSS keeps
        // cascading.
        $r->register($initial('contain-intrinsic-size', new Keyword('none')));
        $r->register($initial('contain-intrinsic-width', new Keyword('none')));
        $r->register($initial('contain-intrinsic-height', new Keyword('none')));
        $r->register($initial('contain-intrinsic-block-size', new Keyword('none')));
        $r->register($initial('contain-intrinsic-inline-size', new Keyword('none')));

        // CSS Anchor Positioning 1 — declarative anchor-target
        // pairs. The interactive flip behaviour is out of scope per
        // the ledger, but the declarative anchor-name / position-
        // anchor / position-area / inset-area properties cascade so
        // print rendering of an anchored element uses its static
        // position.
        $r->register($initial('anchor-name', new Keyword('none')));
        $r->register($initial('anchor-scope', new Keyword('none')));
        $r->register($initial('position-anchor', new Keyword('auto')));
        $r->register($initial('position-area', new Keyword('none')));
        $r->register($initial('inset-area', new Keyword('none')));  // legacy name; aliased to position-area

        // CSS Fragmentation 4 §3 + legacy `page-break-*` aliases.
        $r->register($initial('break-before', new Keyword('auto')));
        $r->register($initial('break-after', new Keyword('auto')));
        $r->register($initial('break-inside', new Keyword('auto')));
        $r->register($initial('page-break-before', new Keyword('auto')));
        $r->register($initial('page-break-after', new Keyword('auto')));
        $r->register($initial('page-break-inside', new Keyword('auto')));
        // CSS Fragmentation 4 §5.5: `box-decoration-break` controls
        // how a box's borders / padding / backgrounds / box-shadow
        // render across fragments. `slice` (initial) treats the box as
        // one rectangle clipped by the fragmentainer — only the
        // outermost edges paint at fragment seams. `clone` paints full
        // decorations on every fragment. Non-inherited per spec.
        $r->register($initial('box-decoration-break', new Keyword('slice')));
        // CSS Paged Media 3 §3.4: `page` names a page type defined by
        // an `@page <name>` at-rule. When a block has a non-`auto`
        // `page` value, its first fragment forces a page break and
        // that page picks up the named rule's margins / background /
        // margin-boxes. Non-inherited per spec.
        $r->register($initial('page', new Keyword('auto')));
        // CSS Transforms 2 §6 — `transform` accepts a list of
        // transform functions (translate / rotate / scale / skew /
        // matrix and 3D variants); `transform-origin` picks the
        // pivot point. Both are non-inherited per spec. Phase-2
        // implementation honours the 2D subset.
        $r->register($initial('transform', new Keyword('none')));
        $r->register($initial('transform-origin', new ValueList(
            [new Percentage(50.0), new Percentage(50.0)],
            ListSeparator::Space,
        )));
        // Filter Effects 1 — `filter` is non-inherited; initial value
        // is `none`. The painter honours the `drop-shadow()` primitive
        // (CSS Filter Effects 1 §16.1) by emitting a hard-edged
        // offset rect behind the box. Other primitives (`blur`,
        // `grayscale`, `brightness`, etc.) parse but no-op since they
        // require raster pre-painting.
        $r->register($initial('filter', new Keyword('none')));
        // CSS Transforms 2 §15 — `backface-visibility: hidden`
        // suppresses paint when the cumulative 3D rotation flips the
        // box past 90° on the X or Y axis (cos(θ) < 0). Print
        // approximates this by checking if the composed rotation
        // would result in a negative scale factor.
        $r->register($initial('backface-visibility', new Keyword('visible')));
        // CSS Fragmentation 4 §4: orphans / widows. Initial 2, both
        // inherit; layout uses them to gate where a paragraph may split
        // across a page boundary.
        $r->register($initial('orphans', new \Phpdftk\Css\Value\Integer(2), true));
        $r->register($initial('widows', new \Phpdftk\Css\Value\Integer(2), true));

        // Lists. All three inherit per CSS Lists 3 §1.
        $r->register($initial('list-style-type', new Keyword('disc'), true));
        $r->register($initial('list-style-position', new Keyword('outside'), true));
        $r->register($initial('list-style-image', new Keyword('none'), true));

        // CSS Backgrounds 3 §6 — `box-shadow` doesn't inherit.
        $r->register($initial('box-shadow', new Keyword('none')));

        // CSS 2.1 §9.5 — floats. Neither inherits.
        $r->register($initial('float', new Keyword('none')));
        $r->register($initial('clear', new Keyword('none')));

        // CSS Flexible Box Layout 1 — flex container + item
        // properties. None inherit per spec.
        $r->register($initial('flex-direction', new Keyword('row')));
        $r->register($initial('flex-wrap', new Keyword('nowrap')));
        $r->register($initial('justify-content', new Keyword('flex-start')));
        $r->register($initial('align-items', new Keyword('stretch')));
        $r->register($initial('align-self', new Keyword('auto')));
        // CSS Box Alignment 3 §6.2 — `justify-self` aligns an item
        // along the inline axis of its containing block. Initial
        // `auto` defers to the container's `justify-items` (which we
        // don't model yet — Phase-2 just treats `auto` as `stretch`
        // in a grid context, the spec default).
        $r->register($initial('justify-self', new Keyword('auto')));
        $r->register($initial('align-content', new Keyword('stretch')));
        $r->register($initial('flex-grow', new Number(0)));
        $r->register($initial('flex-shrink', new Number(1)));
        $r->register($initial('flex-basis', new Keyword('auto')));
        $r->register($initial('order', new \Phpdftk\Css\Value\Integer(0)));

        // CSS Multi-column 1 §2-3. None inherit. `column-gap` initial is
        // `normal`, which Multi-column 1 §3.1 resolves to `1em`.
        $r->register($initial('column-count', new Keyword('auto')));
        $r->register($initial('column-width', new Keyword('auto')));
        $r->register($initial('column-gap', new Keyword('normal')));
        // CSS Box Alignment 3 §8.3 — `row-gap` for flex / grid /
        // multi-column rows. Initial `normal`, non-inheriting. The
        // `gap` shorthand sets both row-gap and column-gap (handled
        // in ShorthandExpander).
        $r->register($initial('row-gap', new Keyword('normal')));
        $r->register($initial('column-rule-width', new Length(3.0, LengthUnit::Px))); // medium
        $r->register($initial('column-rule-style', new Keyword('none')));
        $r->register($initial('column-rule-color', new Keyword('currentcolor')));
        $r->register($initial('column-fill', new Keyword('balance')));
        $r->register($initial('column-span', new Keyword('none')));

        // CSS Grid Layout 2 — initial values per §7 / §8 / §9.
        // Phase-2 MVP supports explicit-placement layout with
        // `<length>` track lists; `fr` units, `repeat()`, `auto`
        // track sizing, and template-areas land in follow-ups.
        $r->register($initial('grid-template-columns', new Keyword('none')));
        $r->register($initial('grid-template-rows', new Keyword('none')));
        $r->register($initial('grid-template-areas', new Keyword('none')));
        $r->register($initial('grid-auto-columns', new Keyword('auto')));
        $r->register($initial('grid-auto-rows', new Keyword('auto')));
        $r->register($initial('grid-auto-flow', new Keyword('row')));
        $r->register($initial('grid-column-start', new Keyword('auto')));
        $r->register($initial('grid-column-end', new Keyword('auto')));
        $r->register($initial('grid-row-start', new Keyword('auto')));
        $r->register($initial('grid-row-end', new Keyword('auto')));

        return $r;
    }
}
