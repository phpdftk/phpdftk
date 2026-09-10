<?php

declare(strict_types=1);

namespace Phpdftk\HtmlToPdf;

use Phpdftk\FontParser\FontFaceData;
use Phpdftk\HtmlToPdf\Layout\FontFace;
use Phpdftk\ResourceLoader\ResourceLoader as HttpResourceLoader;

/**
 * Configuration for the {@see Renderer}. Immutable; mutate via `with*()`.
 *
 * Phase-1 minimum surface: page size (width × height in PDF points),
 * default font (optional `FontFaceData` — when set, text emission works
 * end-to-end; when null the painter emits no text, useful for headless
 * tests), an override for the built-in UA stylesheet, and the strict
 * mode toggle that promotes `Error`-severity warnings to thrown
 * exceptions.
 *
 * Fields from `docs/plans/contracts.md` deferred to later phases:
 * `baseUrl` / `securityPolicy` (Phase 1L — image / `@font-face` resolution),
 * `conformance` (Phase 1N-bis — wire to existing PDF/A profiles),
 * `cursorAfterAddHtml` (Phase 1N - `Pdf::addHtml` sugar).
 */
final readonly class RendererOptions
{
    public function __construct(
        public float $pageWidth = 612.0,
        public float $pageHeight = 792.0,
        public ?FontFaceData $defaultFont = null,
        public ?string $userAgentStylesheet = null,
        public bool $strict = false,
        /**
         * Base directory for resolving relative `<img src>` paths. Required
         * for local-file image references — without it the painter only
         * accepts `data:image/{png,jpeg}` URLs. The painter rejects
         * resolved paths that escape this directory (no `..` walks) so
         * authors can render templated HTML without arbitrary disk access.
         */
        public ?string $baseDir = null,
        /**
         * Broader filesystem sandbox the resolved path must remain
         * under. Defaults to `$baseDir`. Setting it wider (e.g. an
         * entire test-corpus root while `$baseDir` is the individual
         * test's directory) lets `../sibling-dir/x.png` resolve
         * correctly — the WPT pattern where a ref under `reference/`
         * loads `../support/img.png`. Authors using the public
         * Renderer API should leave this as null; the harness sets
         * it explicitly.
         */
        public ?string $sandboxRoot = null,
        /**
         * Additional fonts available for `font-family` selection, keyed
         * by family name (case-insensitive). When a cascaded `font-family`
         * names one of these, that font shapes the run; otherwise the
         * Renderer falls back to `defaultFont`. The map is normalised to
         * lower-case keys on construction.
         *
         * @var array<string, FontFaceData>
         */
        public array $fontMap = [],
        /**
         * Multi-face per-family map for CSS Fonts 4 §6 weight/style
         * matching. Each family name maps to a list of `FontFace`s tagged
         * with their weight (1-1000) and style (normal|italic|oblique).
         * When the resolver picks a face from this map, the painter
         * suppresses the synthetic fake-bold / fake-italic fallbacks that
         * would otherwise apply over a real face. Populated via
         * {@see withFontFaces()}; the single-face `$fontMap` continues to
         * cover the simple case.
         *
         * @var array<string, list<FontFace>>
         */
        public array $faceMap = [],
        /**
         * Optional `phpdftk/resource-loader` for network resource
         * resolution. When supplied, `<img src="http(s)://...">`,
         * `<picture><source>`, and (4F.5.1+) `@font-face url()`,
         * `@import url()`, `background-image: url()`, and
         * `<iframe>` / `<object>` will resolve through this loader
         * — which runs the SSRF guard, follows redirects up to
         * `maxRedirects`, enforces the body cap, strips
         * Authorization across cross-host hops per RFC 9110 §15.4,
         * and MIME-sniffs the response. When null (the default),
         * network hrefs drop silently per the same no-image
         * outcome that was the pre-4F posture — preserves
         * existing call-site behaviour byte-for-byte.
         */
        public ?HttpResourceLoader $resourceLoader = null,
        /**
         * Media types this rendering context matches in `@media`
         * queries. Defaults to `['print']` (PDF-output target). The
         * WPT harness sets this to `['print', 'screen']` so author
         * CSS gated on `@media screen` — by far the most common
         * shape in browser-targeted test corpora — actually applies
         * during reftest scoring. The universal `all` always
         * matches; the type list governs which named buckets do.
         *
         * @var list<string>
         */
        public array $matchingMediaTypes = ['print'],
    ) {}

    public function withPageSize(float $width, float $height): self
    {
        return new self(
            $width,
            $height,
            $this->defaultFont,
            $this->userAgentStylesheet,
            $this->strict,
            $this->baseDir,
            $this->sandboxRoot,
            $this->fontMap,
            $this->faceMap,
            $this->resourceLoader,
            $this->matchingMediaTypes,
        );
    }

    public function withDefaultFont(?FontFaceData $font): self
    {
        return new self(
            $this->pageWidth,
            $this->pageHeight,
            $font,
            $this->userAgentStylesheet,
            $this->strict,
            $this->baseDir,
            $this->sandboxRoot,
            $this->fontMap,
            $this->faceMap,
            $this->resourceLoader,
            $this->matchingMediaTypes,
        );
    }

    public function withUserAgentStylesheet(?string $css): self
    {
        return new self(
            $this->pageWidth,
            $this->pageHeight,
            $this->defaultFont,
            $css,
            $this->strict,
            $this->baseDir,
            $this->sandboxRoot,
            $this->fontMap,
            $this->faceMap,
            $this->resourceLoader,
            $this->matchingMediaTypes,
        );
    }

    public function withStrict(bool $strict): self
    {
        return new self(
            $this->pageWidth,
            $this->pageHeight,
            $this->defaultFont,
            $this->userAgentStylesheet,
            $strict,
            $this->baseDir,
            $this->sandboxRoot,
            $this->fontMap,
            $this->faceMap,
            $this->resourceLoader,
            $this->matchingMediaTypes,
        );
    }

    public function withBaseDir(?string $baseDir): self
    {
        return new self(
            $this->pageWidth,
            $this->pageHeight,
            $this->defaultFont,
            $this->userAgentStylesheet,
            $this->strict,
            $baseDir,
            $this->sandboxRoot,
            $this->fontMap,
            $this->faceMap,
            $this->resourceLoader,
            $this->matchingMediaTypes,
        );
    }

    public function withSandboxRoot(?string $sandboxRoot): self
    {
        return new self(
            $this->pageWidth,
            $this->pageHeight,
            $this->defaultFont,
            $this->userAgentStylesheet,
            $this->strict,
            $this->baseDir,
            $sandboxRoot,
            $this->fontMap,
            $this->faceMap,
            $this->resourceLoader,
            $this->matchingMediaTypes,
        );
    }

    /**
     * Configure which `@media` media types this rendering context
     * matches. Defaults to `['print']`. Pass `['print', 'screen']`
     * for a browser-like context (e.g. the WPT harness) where author
     * CSS gated on `@media screen` should apply. The universal
     * `all` always matches; this list governs the named ones.
     *
     * @param list<string> $types
     */
    public function withMatchingMediaTypes(array $types): self
    {
        return new self(
            $this->pageWidth,
            $this->pageHeight,
            $this->defaultFont,
            $this->userAgentStylesheet,
            $this->strict,
            $this->baseDir,
            $this->sandboxRoot,
            $this->fontMap,
            $this->faceMap,
            $this->resourceLoader,
            $types,
        );
    }

    /**
     * Attach a {@see HttpResourceLoader} for network resource resolution.
     * Without one, `<img src="https://...">` and other URL-form
     * references drop silently. When supplied, the loader runs the
     * SSRF guard, follows redirects, enforces the body cap, and
     * MIME-sniffs the response — call sites in the painter integrate
     * via `Painter::resolveImageSrc`.
     */
    public function withResourceLoader(?HttpResourceLoader $loader): self
    {
        return new self(
            $this->pageWidth,
            $this->pageHeight,
            $this->defaultFont,
            $this->userAgentStylesheet,
            $this->strict,
            $this->baseDir,
            $this->sandboxRoot,
            $this->fontMap,
            $this->faceMap,
            $loader,
        );
    }

    /**
     * The set of CSS Fonts 4 §6.1 generic family keywords that callers
     * may bind a concrete font to. Lower-case to match the resolver's
     * lookup convention; any non-listed key passed to
     * {@see withGenericFamilies()} raises an exception so typos surface
     * at configuration time instead of silently going unmatched.
     */
    public const GENERIC_FAMILIES = [
        'serif',
        'sans-serif',
        'monospace',
        'cursive',
        'fantasy',
        'system-ui',
        'ui-serif',
        'ui-sans-serif',
        'ui-monospace',
        'ui-rounded',
        'emoji',
        'math',
        'fangsong',
    ];

    /**
     * Replace the font map with the given `family-name → FontFaceData`
     * mapping. Keys are normalised to lower-case so `font-family: Inter`
     * and `font-family: inter` resolve the same way.
     *
     * Generic-family keywords (`serif`, `sans-serif`, `monospace`,
     * `cursive`, `fantasy`, `system-ui`, …) are valid keys: a binding for
     * `monospace` makes the UA stylesheet's `<code>` / `<pre>` rules pick
     * up that font without the document having to opt in. See
     * {@see withGenericFamilies()} for a stricter helper.
     *
     * @param array<string, FontFaceData> $fonts
     */
    public function withFonts(array $fonts): self
    {
        $normalised = [];
        foreach ($fonts as $name => $data) {
            $normalised[strtolower($name)] = $data;
        }
        return new self(
            $this->pageWidth,
            $this->pageHeight,
            $this->defaultFont,
            $this->userAgentStylesheet,
            $this->strict,
            $this->baseDir,
            $this->sandboxRoot,
            $normalised,
            $this->faceMap,
            $this->resourceLoader,
            $this->matchingMediaTypes,
        );
    }

    /**
     * Bind one or more `FontFace` lists to family names for CSS Fonts 4
     * §6 weight + style matching. Each family maps to either a single
     * `FontFace` or a list of them; the value is normalised to a list so
     * the resolver always iterates uniformly. Family-name keys are
     * lower-cased on intake to match the resolver's case-insensitive
     * lookup. Merges into the existing `faceMap` (so multiple calls add
     * faces rather than replacing the whole family).
     *
     * Pairs with {@see withFonts()} / {@see withGenericFamilies()}: the
     * resolver checks `faceMap` first (for proper weight/style matching);
     * if no family there matches, it falls back to the single-face
     * `fontMap` (treating that face as 400-normal).
     *
     * @param array<string, FontFace|list<FontFace>> $families
     */
    public function withFontFaces(array $families): self
    {
        $merged = $this->faceMap;
        foreach ($families as $name => $entry) {
            $key = strtolower($name);
            $list = is_array($entry) ? array_values($entry) : [$entry];
            foreach ($list as $face) {
                if (!$face instanceof FontFace) {
                    throw new \InvalidArgumentException(sprintf(
                        'withFontFaces expects FontFace instances; got %s for family "%s"',
                        get_debug_type($face),
                        $name,
                    ));
                }
            }
            $merged[$key] = array_merge($merged[$key] ?? [], $list);
        }
        return new self(
            $this->pageWidth,
            $this->pageHeight,
            $this->defaultFont,
            $this->userAgentStylesheet,
            $this->strict,
            $this->baseDir,
            $this->sandboxRoot,
            $this->fontMap,
            $merged,
            $this->resourceLoader,
            $this->matchingMediaTypes,
        );
    }

    /**
     * Bind one or more CSS generic-family keywords (`serif`, `sans-serif`,
     * `monospace`, …) to concrete fonts, *merging* into the existing font
     * map (unlike {@see withFonts()} which replaces it). Rejects any key
     * outside {@see GENERIC_FAMILIES} so typos surface immediately —
     * `withGenericFamilies(['mono' => $f])` raises, forcing the caller to
     * either fix the spelling or fall back to `withFonts()` for ad-hoc
     * family names.
     *
     * The UA stylesheet maps `<code>` / `<pre>` / `<kbd>` / `<samp>` /
     * `<tt>` to `font-family: monospace` out of the box, so binding
     * `monospace` here is the lowest-effort way to switch code blocks to
     * a fixed-width font without rewriting markup.
     *
     * @param array<string, FontFaceData> $generics
     */
    public function withGenericFamilies(array $generics): self
    {
        $merged = $this->fontMap;
        foreach ($generics as $name => $data) {
            $key = strtolower($name);
            if (!in_array($key, self::GENERIC_FAMILIES, true)) {
                throw new \InvalidArgumentException(sprintf(
                    'withGenericFamilies expects a CSS generic family keyword '
                    . '(%s); got "%s". Use withFonts() for arbitrary family names.',
                    implode(', ', self::GENERIC_FAMILIES),
                    $name,
                ));
            }
            $merged[$key] = $data;
        }
        return new self(
            $this->pageWidth,
            $this->pageHeight,
            $this->defaultFont,
            $this->userAgentStylesheet,
            $this->strict,
            $this->baseDir,
            $this->sandboxRoot,
            $merged,
            $this->faceMap,
            $this->resourceLoader,
            $this->matchingMediaTypes,
        );
    }

    /**
     * Pragmatic built-in UA stylesheet covering the elements the box
     * generator dispatches on. Returns the override when one was set, or
     * the built-in default. Phase 1N-bis will grow this to match
     * browsers' html.css more closely.
     */
    public function effectiveUserAgentStylesheet(): string
    {
        return $this->userAgentStylesheet ?? <<<'CSS'
            html, body, address, blockquote, dl, dd, div, fieldset, figcaption, figure,
            footer, form, h1, h2, h3, h4, h5, h6, header, hr, main, nav, p, pre,
            section {
                display: block;
            }
            article, aside, hgroup, search { display: block; }
            /* HTML 5 §4.11.6: `<dialog>` is hidden unless the `open`
               attribute is set; opening is JS-driven so a static print
               render shows nothing by default. */
            dialog { display: none; }
            dialog[open] { display: block; }
            /* HTML 5 §3.2.6.1: the `hidden` attribute hides any element.
               `hidden="until-found"` is the find-in-page reveal mode —
               it stays hidden in static print just like the bare form. */
            [hidden] { display: none; }
            [hidden="until-found"] { display: none; }
            /* HTML 5 §4.12.3: `<template>` content is inert and never
               renders directly. */
            template { display: none; }
            /* HTML §15.3.7 — list indentation is 40px, not 24pt (32px). */
            menu { display: block; padding-left: 40px; }
            ul, ol { display: block; padding-left: 40px; }
            li { display: list-item; }
            span, a, b, i, em, strong, code, small, big, sub, sup, label, mark,
            del, ins, q, abbr, cite, var, kbd, samp, time, output {
                display: inline;
            }
            img, button, input, select, textarea, svg, math { display: inline-block; }
            /* SVG 2 §8.2 / CSS Overflow 3 — the SVG viewport establishes a
               clipping region: content outside the `width`×`height` box is
               clipped by default. Modelled as `overflow: clip` so authored
               per-axis `overflow-x/y: visible` and `overflow-clip-margin`
               compose through the standard overflow machinery. */
            svg { overflow: clip; }
            input, select, textarea {
                border: 1px solid #888;
                padding: 2pt 4pt;
                font-family: monospace;
            }
            /* HTML 5 §4.10.11: `<textarea>` preserves its whitespace
               (including line breaks) and renders as a multi-line text
               area. `pre-wrap` preserves runs of whitespace and wraps
               long lines at the element's content edge. */
            textarea { white-space: pre-wrap; }
            /* HTML 5 §4.10.7: `<option>` content is rendered by the
               `<select>` host (BoxGenerator picks the selected one);
               options never paint on their own. */
            option { display: none; }
            button {
                border: 1px solid #888;
                background-color: #eee;
                padding: 2pt 8pt;
                border-radius: 3pt;
            }
            /* HTML §15.3.8 (Tables) — the UA defaults are
               `border-spacing: 2px` on the table and `padding: 1px` on
               cells. The `cellspacing` / `cellpadding` presentational
               attributes override them (mapped in BoxGenerator). A
               larger cell padding here silently offset every `<td>` by
               ~3px against an equivalent `display: table-cell` box,
               which is exactly what the CSS2 anonymous-table reftests
               overlay to detect. */
            table { display: table; border-spacing: 2px; }
            tr { display: table-row; }
            td, th { display: table-cell; padding: 1px; vertical-align: top; }
            th { font-weight: bold; text-align: center; }
            thead, tbody, tfoot, caption { display: block; }
            /* HTML 5 §4.9.1-3 — `<col>` / `<colgroup>` are layout-only
               carriers for per-column declarations (width, background,
               border). They never paint their own content, but their
               cascaded values feed the table column-width pass —
               BlockLayout's `collectColumnWidths` reads them when
               `table-layout: fixed`. Generating a `TableColumnBox`
               keeps the cascade reachable; the box itself is a layout
               no-op. */
            colgroup { display: table-column-group; }
            col { display: table-column; }
            head, script, style, title, meta, link, base { display: none; }
            /* HTML 5 §4.5.27 — `<wbr>` (Word Break Opportunity) is a
               zero-width inline that just marks a permissible line
               break. Rendering it as `inline` with zero content keeps
               the inline flow intact; the U+200B zero-width-space
               line-break opportunity emitted by the BoxGenerator does
               the actual break. */
            wbr { display: inline; }
            /* HTML 5 §4.8.13 — `<map>` defines image-clickable regions
               via nested `<area>` children. The map itself is an
               inline container; the area elements never render in
               print (interactive hotspots are display-time concerns). */
            map { display: inline; }
            area { display: none; }
            /* HTML 5 §4.12.1 — `<noscript>` content is meant for UAs
               with scripting disabled. Static print is effectively
               script-less, so the children render as inline content. */
            noscript { display: inline; }

            /* Headings — HTML §15.3.6. The sizes and margins are `em`,
               not px: a heading's font-size is relative to its parent's
               and its margins to its OWN font-size, so both scale with
               the document. Hard-coded px matched a 16px base and
               silently stopped scaling for anything else. */
            h1 { font-size: 2em; font-weight: bold; margin: 0.67em 0; }
            h2 { font-size: 1.5em; font-weight: bold; margin: 0.83em 0; }
            h3 { font-size: 1.17em; font-weight: bold; margin: 1em 0; }
            h4 { font-weight: bold; margin: 1.33em 0; }
            h5 { font-size: 0.83em; font-weight: bold; margin: 1.67em 0; }
            h6 { font-size: 0.67em; font-weight: bold; margin: 2.33em 0; }

            /* Paragraph and inline emphasis. */
            p { margin: 1em 0; }
            b, strong { font-weight: bold; }
            i, em, cite, var, dfn { font-style: italic; }
            small { font-size: 0.83em; }
            big { font-size: 1.17em; }
            sub { vertical-align: sub; font-size: 0.83em; }
            sup { vertical-align: super; font-size: 0.83em; }

            /* Code & preformatted. */
            code, kbd, samp, tt { font-family: monospace; }
            pre { font-family: monospace; margin: 1em 0; white-space: pre; }

            /* Lists. */
            ul, ol { margin: 1em 0; }
            ol { list-style-type: decimal; }
            ul ul, ol ul { list-style-type: circle; }
            ul ul ul, ol ul ul { list-style-type: square; }

            /* Block-level wrappers. */
            blockquote { margin: 1em 40px; }
            /* HTML §15.3.5 — an `<hr>` is a zero-height box with a 1px
               INSET border on all four sides (which is what gives the
               familiar two-tone rule), not a single top border. */
            hr { display: block; color: gray; border: 1px inset; margin: 0.5em auto; overflow: hidden; }

            /* Anchors. */
            a { color: #0033cc; text-decoration: underline; }

            /* Other inline semantics. */
            mark { background-color: #ffff00; color: #000; }
            u, ins { text-decoration: underline; }
            s, strike, del { text-decoration: line-through; }
            abbr { text-decoration: underline; text-decoration-style: dotted; }

            /* HTML 5 §4.5.6 — `<address>` carries contact info; the
               typographic convention is italic. */
            address { font-style: italic; }

            /* HTML 5 §15.3 — bidi element UA defaults. `<bdo>`
               overrides the bidi algorithm for its descendants;
               `<bdi>` isolates them so surrounding text's bidi
               doesn't bleed in / out. */
            bdo { unicode-bidi: bidi-override; }
            bdi { unicode-bidi: isolate; }
            /* HTML 5 §15.3 maps the `dir` attribute to CSS
               direction + bidi isolation. `:where(...)` keeps the
               attribute-selector specificity at 0 so the bdo rule
               above still wins for `<bdo>`. */
            [dir="ltr"] { direction: ltr; }
            [dir="rtl"] { direction: rtl; }
            :where([dir="ltr"], [dir="rtl"]) { unicode-bidi: isolate; }
            :where([dir="auto"]) { unicode-bidi: plaintext; }

            /* Definition lists. */
            dl { margin: 1em 0; }
            dt { font-weight: bold; }
            dd { margin-left: 40px; }

            /* Figure / figcaption. */
            figure { margin: 1em 40px; }
            /* No UA font-size on figcaption — HTML §15.3.9 gives it
               `display: block` only, so it inherits its parent's size. */
            figcaption { display: block; }

            /* Details / summary (HTML 5 §4.11.1). Closed by default —
               only the summary renders — until the [open] attribute
               flips the visibility. Print authors who want a permanent
               open disclosure either set [open] on the tag or override
               with their own CSS.

               The `▶ ` / `▼ ` triangle markers come from the
               `summary::before` pseudo-element. Browsers render this
               as a real `::marker` box, but our pseudo-element pipeline
               already handles `::before`, so the visual outcome is the
               same: a triangle prefix on the summary text. Authors
               can hide it via `summary::before { content: none; }`. */
            details, summary { display: block; }
            summary { font-weight: bold; }
            summary::before { content: "\25B6  "; }
            details[open] > summary::before { content: "\25BC  "; }
            details > * { display: none; }
            details > summary { display: block; }
            details[open] > * { display: block; }

            /* `<q>` inline quotes — wrap content in straight double quotes
               per the open-quote / close-quote Phase-1 simplification. */
            q::before { content: open-quote; }
            q::after { content: close-quote; }

            /* `<picture>` is a transparent wrapper around an `<img>`;
               `<source>` carries media-query metadata we can't evaluate
               without JS, so it's hidden. The contained `<img>` renders
               normally. */
            picture { display: inline; }
            source, track, param { display: none; }

            /* HTML 5 §4.10.10 — `<datalist>` is a typeahead helper
               for `<input>` and never renders on its own. */
            datalist { display: none; }

            /* HTML 5 §4.10.13 + §4.10.14 — `<meter>` and `<progress>`
               are inline-block widgets. We don't paint the actual
               bar / gauge (Phase 2 with proper widget rendering),
               but the inline-block treatment ensures any text
               children (the fallback value) flow inline. */
            meter, progress { display: inline-block; }

            /* HTML 5 §4.10.15 — `<fieldset>` is a labelled form
               group with a thin border + small inset padding.
               Legend positioning over the top border is Phase 2;
               Phase 1 renders legend as a regular block child. */
            /* HTML §15.3.8 — fieldset padding is em-relative and its
               inline margin 2px; legend takes 2px of inline padding. */
            fieldset { border: 1px solid #888; padding: 0.35em 0.75em 0.625em; margin: 0 2px; }
            legend { display: block; padding: 0 2px; }

            /* HTML 5 §4.12.5 — `<canvas>` is a script-driven raster
               surface. With no scripting it renders its fallback
               children inline-block. */
            canvas { display: inline-block; }
            /* HTML 5 §4.5.21 — `<rp>` is the ruby-parenthesis
               fallback for browsers without ruby layout support; in
               browsers that DO support ruby it's `display: none`.
               Phase 1 doesn't paint ruby annotations yet, so we keep
               `<rp>` hidden to match the spec convention rather than
               showing it as visible parentheses. */
            rp { display: none; }

            /* CSS Fragmentation 4 §3.2 — `break-inside: avoid` on
               atomic content so a single row / quote / heading / image
               that fits on a single page never straddles a page
               boundary. Authors override with `break-inside: auto` on
               structurally tall content (e.g. a multi-page <pre> block). */
            tr, figure, blockquote, pre, img,
            h1, h2, h3, h4, h5, h6 { break-inside: avoid; }
        CSS;
    }
}
