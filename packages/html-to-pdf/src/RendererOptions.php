<?php

declare(strict_types=1);

namespace Phpdftk\HtmlToPdf;

use Phpdftk\FontParser\OpenTypeData;
use Phpdftk\HtmlToPdf\Layout\FontFace;

/**
 * Configuration for the {@see Renderer}. Immutable; mutate via `with*()`.
 *
 * Phase-1 minimum surface: page size (width × height in PDF points),
 * default font (optional `OpenTypeData` — when set, text emission works
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
        public ?OpenTypeData $defaultFont = null,
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
         * Additional fonts available for `font-family` selection, keyed
         * by family name (case-insensitive). When a cascaded `font-family`
         * names one of these, that font shapes the run; otherwise the
         * Renderer falls back to `defaultFont`. The map is normalised to
         * lower-case keys on construction.
         *
         * @var array<string, OpenTypeData>
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
            $this->fontMap,
            $this->faceMap,
        );
    }

    public function withDefaultFont(?OpenTypeData $font): self
    {
        return new self(
            $this->pageWidth,
            $this->pageHeight,
            $font,
            $this->userAgentStylesheet,
            $this->strict,
            $this->baseDir,
            $this->fontMap,
            $this->faceMap,
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
            $this->fontMap,
            $this->faceMap,
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
            $this->fontMap,
            $this->faceMap,
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
            $this->fontMap,
            $this->faceMap,
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
     * Replace the font map with the given `family-name → OpenTypeData`
     * mapping. Keys are normalised to lower-case so `font-family: Inter`
     * and `font-family: inter` resolve the same way.
     *
     * Generic-family keywords (`serif`, `sans-serif`, `monospace`,
     * `cursive`, `fantasy`, `system-ui`, …) are valid keys: a binding for
     * `monospace` makes the UA stylesheet's `<code>` / `<pre>` rules pick
     * up that font without the document having to opt in. See
     * {@see withGenericFamilies()} for a stricter helper.
     *
     * @param array<string, OpenTypeData> $fonts
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
            $normalised,
            $this->faceMap,
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
            $this->fontMap,
            $merged,
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
     * @param array<string, OpenTypeData> $generics
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
            $merged,
            $this->faceMap,
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
            /* HTML 5 §3.2.6.1: the `hidden` attribute hides any element. */
            [hidden] { display: none; }
            /* HTML 5 §4.12.3: `<template>` content is inert and never
               renders directly. */
            template { display: none; }
            menu { display: block; padding-left: 24pt; }
            ul, ol { display: block; padding-left: 24pt; }
            li { display: list-item; }
            span, a, b, i, em, strong, code, small, big, sub, sup, label, mark,
            del, ins, q, abbr, cite, var, kbd, samp, time, output {
                display: inline;
            }
            img, button, input, select, textarea, svg { display: inline-block; }
            input, select, textarea {
                border: 1px solid #888;
                padding: 2pt 4pt;
                font-family: monospace;
            }
            button {
                border: 1px solid #888;
                background-color: #eee;
                padding: 2pt 8pt;
                border-radius: 3pt;
            }
            table { display: table; }
            tr { display: table-row; }
            td, th { display: table-cell; padding: 2pt; vertical-align: top; }
            th { font-weight: bold; text-align: center; }
            thead, tbody, tfoot, caption { display: block; }
            colgroup, col { display: none; }
            head, script, style, title, meta, link, base { display: none; }

            /* Headings — sizes / margins per browsers' html.css. */
            h1 { font-size: 32px; font-weight: bold; margin: 21px 0; }
            h2 { font-size: 24px; font-weight: bold; margin: 19px 0; }
            h3 { font-size: 19px; font-weight: bold; margin: 19px 0; }
            h4 { font-size: 16px; font-weight: bold; margin: 21px 0; }
            h5 { font-size: 13px; font-weight: bold; margin: 22px 0; }
            h6 { font-size: 11px; font-weight: bold; margin: 25px 0; }

            /* Paragraph and inline emphasis. */
            p { margin: 16px 0; }
            b, strong { font-weight: bold; }
            i, em, cite, var, dfn { font-style: italic; }
            small { font-size: 0.83em; }
            big { font-size: 1.17em; }
            sub { vertical-align: sub; font-size: 0.83em; }
            sup { vertical-align: super; font-size: 0.83em; }

            /* Code & preformatted. */
            code, kbd, samp, tt { font-family: monospace; }
            pre { font-family: monospace; margin: 16px 0; white-space: pre; }

            /* Lists. */
            ul, ol { margin: 16px 0; }
            ol { list-style-type: decimal; }
            ul ul, ol ul { list-style-type: circle; }
            ul ul ul, ol ul ul { list-style-type: square; }

            /* Block-level wrappers. */
            blockquote { margin: 16px 40px; }
            hr { display: block; border-top: 1px solid; margin: 8px 0; }

            /* Anchors. */
            a { color: #0033cc; text-decoration: underline; }

            /* Other inline semantics. */
            mark { background-color: #ffff00; color: #000; }
            u, ins { text-decoration: underline; }
            s, strike, del { text-decoration: line-through; }
            abbr { text-decoration: underline; text-decoration-style: dotted; }

            /* Definition lists. */
            dl { margin: 16px 0; }
            dt { font-weight: bold; }
            dd { margin-left: 40px; }

            /* Figure / figcaption. */
            figure { margin: 16px 40px; }
            figcaption { font-size: 0.9em; }

            /* Details / summary — content always visible (no JS to hide). */
            details, summary { display: block; }
            summary { font-weight: bold; }

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
        CSS;
    }
}
