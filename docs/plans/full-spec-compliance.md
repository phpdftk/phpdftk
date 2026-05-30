# Full HTML / CSS / SVG spec compliance — roadmap to 100%

## Context

This plan supersedes the open-ended "browser print-stylesheet parity" framing in `docs/plans/html-and-svg.md` with a concrete, measurable, finishable target: **100% of the applicable HTML / CSS / SVG spec surface, frozen annually, measured against the in-scope subset of Web Platform Tests plus a custom PDF-conformance suite.**

Calibration. WeasyPrint after ~13 years ≈ 75% of applicable surface. Prince (commercial, 20+ years) ≈ 87%. Headless Chromium (thousands of engineer-years) ≈ 99%. **No one is at 100%.** The gap to closing is finite, observable, and CI-trackable; this plan structures the work so progress is visible week-by-week and a finite end-state exists.

## Definitions

**"100%."** 100% of the spec surface that applies to a static, server-side, headless PDF renderer at a given annual snapshot. The applicable surface is finite because all runtime / interaction / network surfaces are out-of-scope by construction (see ledger below) and don't count against the target.

**"Applicable."** The MUST/SHOULD/MAY normative requirements in scope, *minus* the out-of-scope ledger. Determined per-test in the WPT harness manifest (Phase 4A) and per-MUST in the spec inventory (Phase 4B).

**"Frozen."** The target spec snapshot rolls forward once per year on a published re-sync date. Default: **2027-01-01** for the v1 100% claim (gives ~7 months runway from today for Phase 4 substrate); thereafter each January. A re-sync changelog ships with each annual roll documenting what was added / withdrawn / re-classified.

**"Measured."** Two-pronged:
- **WPT pass rate on in-scope subset → 100%** (the breadth target — same standard browsers use)
- **PDF-conformance custom suite pass rate → 100%** (the depth target — covers font embedding, ICC color, PDF/A-X-UA interaction with HTML/CSS, encrypted-document rendering, etc. that WPT can't express)

## Out-of-scope ledger (the contract)

These surfaces are *permanently* out of scope because no static server-side PDF renderer can implement them. They're skipped by the WPT manifest and don't count against the target. The ledger lives at `docs/spec/out-of-scope.md` and is the user-facing contract for what "100%" means.

| Surface | Why excluded |
|---|---|
| ECMAScript / JavaScript / Web IDL / script execution | PDF is static output; no runtime |
| DOM events (UI Events, Pointer / Touch / Keyboard, Wheel, Input, Focus, Form events) | No user at render time |
| Network APIs (Fetch, XHR, WebSockets, WebRTC, Server-Sent Events, Beacon, Reporting) | Server-side render; the resource loader (Phase 4F) handles bounded resource fetching, not arbitrary network |
| Service Workers, Workers, Worklets | No runtime |
| Storage (IndexedDB, localStorage / sessionStorage, Cache API, File API, File System Access) | No runtime / no persistence |
| Audio (Web Audio, Web Speech, Media Capture, Media Session) | Out of band — PDF Sound annotations are handled by `pdf-writer`, not html/css |
| Video playback / streaming (Media Source Extensions, EME, WebCodecs) | Static frame extraction is in scope (poster frames); playback isn't |
| Sensors (Geolocation, Gyroscope, Accelerometer, Magnetometer, Battery, Vibration, Ambient Light) | No device at render time |
| Display surfaces (WebXR, WebGL, WebGPU, Canvas 2D dynamic) | No GPU; static `<canvas>` poster extraction may land via foreignObject |
| Connectivity (Web NFC, Web USB, Web HID, Web Serial, Web Bluetooth) | No device |
| Page lifecycle (Visibility, Page Lifecycle, View Transitions, Navigation API) | No navigation in a static document |
| CSS perf hints (`will-change`, `contain: paint` perf semantics) | Honoured as no-ops (lint-warn rather than implement) |
| CSS scroll family (scroll-snap, overscroll-behavior, scroll-timeline interactive part) | No scrolling in a fixed-pagination PDF |
| ARIA live regions, accessibility tree mutation events | Accessibility tree itself is in scope (Tagged PDF / PDF/UA); live-update behaviour isn't |

This list is conservative — anything not on it is *in* scope until proven otherwise. New WHATWG/CSSWG specs that publish during a snapshot window default to in-scope and get triaged at the next annual re-sync.

## Phase 4 — Substrate for the long haul

Seven concurrent strands. Each is either a new publishable package or a major existing-package extension. All must land before Phase 5 module sweeps can hit 100%.

### 4A — WPT harness (`phpdftk/wpt-harness`, new)

Pull `web-platform-tests/wpt` as a submodule under `vendor-data/wpt`. Each test renders its HTML/SVG to PDF via the pipeline, rasterises the PDF (via Ghostscript or `phpdftk/raster` once 4C lands), and visual-diffs against the expected rendering published in the WPT repo.

Substrate:
- **4A.1** harness runner (`composer wpt`)
- **4A.2** PDF → raster pipeline
- **4A.3** visual diff scorer (perceptual diff; pass threshold publishable)
- **4A.4** manifest classifier (`in-scope` / `out-of-scope` / `pending-substrate` / `known-failure-ledgered`)
- **4A.5** CI publishes `docs/site/standards/spec-coverage/wpt-report.md` per build
- **4A.6** PR comment shows per-PR delta (passes gained / lost)

### 4B — Spec inventory (`docs/spec/inventory.md`, generated)

Every CSS module, HTML chapter, SVG section gets a row: status (`not started` / `partial` / `complete`), current WPT pass rate, owner sub-phase, blockers. Generated weekly by `composer spec-status` from the WPT harness output + the substrate dependency graph. Same publishing shape as `benchmarks.md` and `coverage.md`.

### 4C — Raster compositor (`phpdftk/raster`, new)

Pure-PHP software raster. Renders a graphics primitive tree (paths, gradients, images, masks) to an RGBA pixel buffer, then embeds the buffer as an Image XObject in the resulting PDF. The translator emits raster *only* when the spec semantics can't be expressed with PDF native primitives.

Required by:
- **CSS Filter Effects 1** (`filter:`, `<filter>` element) — `feGaussianBlur`, `feColorMatrix`, `feConvolveMatrix`, `feMorphology`, `feTurbulence`, `feDisplacementMap`, etc. Most don't have PDF equivalents.
- **Compositing and Blending 1, 2** (`mix-blend-mode`, `isolation`, `background-blend-mode`) — PDF supports a subset of blend modes natively; the rest need raster.
- **CSS Masking 1** alpha + luminance masks beyond what PDF SoftMask handles (e.g. mask-composite, mask-mode).
- **`backdrop-filter`** — needs the prior page-state snapshot as a raster buffer to filter, then composite back.
- **CSS Transforms 3** (3D transforms with perspective + intersection) once the painter can't express the geometry with PDF `cm` alone.

Sub-phases: rasteriser, filter primitives, blend modes, mask compositing, transform-3D fallback, embedded-buffer optimisation (deduplicate identical buffers across pages).

### 4D — Text shaping completion (`phpdftk/text`, extension)

Already exists in plan but isn't yet wired into the translators. Finish:
- UAX #14 line breaking (with ICU integration)
- UAX #9 bidi
- UAX #29 word / sentence / grapheme cluster break
- OpenType GSUB / GPOS shaping (ligatures, kerning, contextual alts, complex scripts)
- `font-feature-settings` / `font-variation-settings`
- Vertical writing modes (Writing Modes 4)
- Hyphenation (Pattern-based, e.g. libhyphen rules)

Required by: CSS Text 3, 4 / CSS Fonts 4 / CSS Writing Modes 4 / CSS Inline 3 / SVG 2 §11 (text-on-path) / HTML §4.5 phrasing content.

### 4E — Color engine (`phpdftk/color`, extension)

ICC profile parsing exists; needs wider color-management plumbing:
- Lab / LCH / OKLab / OKLCH / color() with display-p3, rec2020, a98-rgb, prophoto-rgb, srgb-linear, xyz
- Gamut mapping (CSS Color 4 §13)
- `color-mix()` (CSS Color 5)
- Relative color syntax (CSS Color 5)
- HDR color (CSS Color HDR, gated on HDR profile readiness)

Required by: CSS Color 4 / 5 / HDR / CSS Backgrounds 3 (image color spaces) / CSS Filter Effects 1 (color-interpolation-filters).

### 4F — Resource loader (`phpdftk/resource-loader`, new)

HTTP fetcher with caching, retries, redirect handling, SSRF gate, content-length guard, MIME sniffing. The 1L item we've been deferring across html-to-pdf and svg-to-pdf.

Required by: HTML `<img srcset>`, `<picture>`, `<video poster>`, `<audio>`, `<iframe>`, `<object data>` / SVG `<image href="http(s)://">`, `<use href="external">` / CSS `@font-face url()`, `url()` in `background-image` / `mask-image` / `cursor` / `@import` / `@namespace`.

### 4G — Paged Media engine (`phpdftk/paged-media`, extract from html-to-pdf)

Page boxes, fragmentation algorithm, `@page` rules, named pages, `@page :first / :left / :right`, marginalia (running headers / footers via running elements), generated content for paged media (page counters, cross-references, lists of figures). Currently entangled with the html-to-pdf painter — extract into its own engine for clean reuse from svg-to-pdf (foreignObject) and direct API callers.

Required by: CSS Page 3 / CSS Generated Content for Paged Media 3 / CSS Page Floats 3 / CSS Fragmentation 3, 4 (paged-context behaviour).

## Phase 5 — CSS module sweep

Each sub-phase = one module to 100% of applicable surface. Ordered by dependency: foundational modules first, then layout, then effects.

| Sub | Module | Status today | Substrate dep | Notes |
|---|---|---|---|---|
| 5A | CSS Syntax 3 | mostly done | — | Tokenizer edge cases; round-trip |
| 5B | CSS Values 4 | partial | — | calc(), min(), max(), clamp(), env() in non-page context |
| 5C | CSS Cascade 5, 6 | partial | — | Cascade layers, `revert`, `revert-layer`, scope |
| 5D | CSS Custom Properties 1 | partial | — | Registered properties, `@property` |
| 5E | CSS Selectors 4 | partial | — | `:has()`, `:is()`, `:where()`, attribute matchers |
| 5F | CSS Pseudo 4 | partial | — | `::marker`, `::placeholder`, `::file-selector-button`, `::backdrop` |
| 5G | CSS Conditional Rules 3, 4, 5 | partial | — | `@supports` value queries, `@supports font-format()` |
| 5H | CSS Media Queries 5 | partial | — | `prefers-*` defaults to false; print media query critical |
| 5I | CSS Color 4 | partial | 4E | Lab / LCH / OKLab / OKLCH / color() wide gamut |
| 5J | CSS Color 5 | not started | 4E | `color-mix()`, relative colors |
| 5K | CSS Fonts 4 | partial | 4D, 4F | `font-variation-settings`, `@font-face` loading, `font-feature-settings` |
| 5L | CSS Fonts 5 | not started | 4D, 4F | `@font-palette-values`, palette indexing |
| 5M | CSS Text 3 | partial | 4D | `text-align`, justification, `text-transform`, `letter-spacing`, `word-spacing` |
| 5N | CSS Text 4 | not started | 4D | `text-wrap: balance`, `hyphenate-character`, `text-spacing-trim` |
| 5O | CSS Text Decoration 3, 4 | partial | 4D | Multi-line decoration, decoration-skip, text-emphasis |
| 5P | CSS Backgrounds 3 | mostly done | — | `background-blend-mode` punts to 4C |
| 5Q | CSS Backgrounds 4 | not started | 4C | Conic gradients, `background-clip: text` |
| 5R | CSS Borders 4 | partial | — | Logical border properties, `border-image` round-trip |
| 5S | CSS Box 3 | mostly done | — | Box model corners, the recent 1E.2 row finalised |
| 5T | CSS Sizing 3 | mostly done | — | `aspect-ratio`, intrinsic / extrinsic sizing |
| 5U | CSS Sizing 4 | not started | — | `contain-intrinsic-size`, `min-content` etc edge cases |
| 5V | CSS Logical Properties 1 | partial | — | Long-hand logical equivalents |
| 5W | CSS Display 3 | partial | — | `display: contents`, `inline list-item`, `display: math` |
| 5X | CSS Position 3 | partial | — | `sticky` is interactive (out); `fixed` page-relative is in |
| 5Y | CSS Anchor Positioning 1 | not started | — | Static anchors render; interactive anchor flips are out |
| 5Z | CSS Inline 3 | partial | 4D | `text-box-trim`, `initial-letter`, baseline alignment |
| 5AA | CSS Lists 3 | partial | — | Counter styles already partial; `list-style-image` |
| 5BB | CSS Counter Styles 3 | partial | — | All predefined styles; `@counter-style` |
| 5CC | CSS Generated Content 3 | partial | — | `content:` strings, attr(), counters; element() not in scope |
| 5DD | CSS Containment 3 | partial | — | Container queries (size + style); `contain: paint` perf is no-op |
| 5EE | CSS Flexbox 1 | mostly done | — | Edge cases via WPT |
| 5FF | CSS Grid 1 | mostly done | — | Edge cases via WPT |
| 5GG | CSS Grid 2 | partial | — | Subgrid |
| 5HH | CSS Grid 3 | not started | — | Masonry |
| 5II | CSS Multi-column 1 | partial | 4G | `column-span`, fragmentation interaction |
| 5JJ | CSS Multi-column 2 | not started | 4G | Column rules, `column-gap` interaction |
| 5KK | CSS Tables 3 | partial | — | Co-evolves with HTML §4.9; subgrid-on-tables |
| 5LL | CSS Writing Modes 4 | partial | 4D | Vertical text; `text-orientation`; logical box edges |
| 5MM | CSS Page 3 | partial | 4G | `@page`, named pages, page selectors, page marks |
| 5NN | CSS Generated Content for Paged Media 3 | partial | 4G | Running elements, cross-references, leaders |
| 5OO | CSS Page Floats 3 | not started | 4G | Column / page floats |
| 5PP | CSS Fragmentation 3 | partial | 4G | `break-before / -after / -inside`, orphans / widows |
| 5QQ | CSS Fragmentation 4 | not started | 4G | Fragmented borders, repeated headers |
| 5RR | CSS Transforms 2 | done (2D), 3D pending | 4C (3D) | Just landed for 2D; 3D needs raster fallback |
| 5SS | CSS Transforms 3 | not started | 4C | 3D, perspective, backface-visibility |
| 5TT | CSS Masking 1 | partial | 4C | Beyond PDF SoftMask via 4C |
| 5UU | CSS Filter Effects 1 | not started | 4C | All filter primitives via 4C |
| 5VV | CSS Compositing 1 | partial | 4C | PDF-native blends already in; rest via 4C |
| 5WW | CSS Compositing 2 | not started | 4C | `mix-blend-mode` corners |
| 5XX | CSS Animations | partial | — | Frame strategy: see open decision #1 |
| 5YY | CSS Transitions | partial | — | Frame strategy: see open decision #1 |
| 5ZZ | CSS Easing 1, 2 | partial | — | Used by 5XX / 5YY |
| 5AAA | CSS Motion Path 1 | not started | — | Path-following animation, gated on 5XX strategy |
| 5BBB | CSS View Transitions | n/a | — | Out of scope (interactive) |

## Phase 6 — HTML breadth

Parser edge cases driven by WPT `html/syntax/` failures; rendering surface for sections, embedded content, tables (co-evolves with CSS Tables 3), forms (render-only), `<dialog>` (open state rendering), `<details>` / `<summary>` (open state), popovers (popover-open state). Edits (`<ins>` / `<del>`) and microdata round out the surface. No scripting, no event handlers, no form submission.

## Phase 7 — SVG 2 completion

- **Filter Effects 1** via 4C raster compositor — `<filter>`, `<feGaussianBlur>`, `<feColorMatrix>`, `<feBlend>`, `<feComposite>`, `<feMorphology>`, `<feFlood>`, `<feImage>`, `<feTurbulence>`, `<feDisplacementMap>`, `<feConvolveMatrix>`, `<feSpecularLighting>`, `<feDiffuseLighting>`, `<feMerge>`, `<feTile>`, `<feOffset>`, `<feDropShadow>`
- **Text on path** via 4D (`<textPath>`)
- **foreignObject** — embeds HTML inside SVG. Closes the loop: the SVG painter delegates to the HTML painter at the foreignObject's viewport. Bidirectional reuse of the substrate.
- **`spreadMethod: reflect` / `repeat`** — synthesised wider function domain with mirrored / cycled stops
- **Per-child `clip-rule`** — transparency-group based clip via 4C
- **Markers** (`<marker>`) — replays as Form XObject at vertices
- **SVG accessibility** (Tagged PDF integration)
- **SMIL animation** — frame strategy: see open decision #1

## Phase 8 — WPT failure ledger sweep

Once Phase 5 / 6 / 7 hit nominal completion, the WPT in-scope failure list drives the remaining work. Sub-phases sized 1–2 weeks each, each closing one cluster of related failures. Each ledger entry is a labelled GitHub issue under the `wpt-100` milestone.

## Phase 9 — Conformance certification + maintenance

When WPT in-scope passes hit 100% AND the custom PDF-conformance suite passes 100%, the v1 100% claim ships. Deliverables:

- **Public conformance report** auto-generated from the WPT harness, published per release at `docs/site/standards/conformance/100-percent.md`
- **Out-of-scope ledger** snapshot published at the same path
- **Annual re-sync changelog** documenting what each year's snapshot added / withdrew / re-classified

Maintenance after v1: each January, re-sync against the new WHATWG / CSSWG / W3C snapshot; new MUST/SHOULD goes into Phase 5/6/7-extension queue with the same WPT-driven cadence.

## Open design decisions

Three calls determine concrete shape and timeline. I've shipped defaults in the doc above; each below is the place to push back.

1. **Animation / transition rendering strategy.** PDF is static; CSS Animations / Transitions / SVG SMIL declare time-varying properties. Options:
   - **Final state** (render the end keyframe / transition target). Default proposed.
   - **Initial state** (t=0).
   - **Configurable hook** `Pdf::renderAnimationsAt(float $t = 1.0)` so callers pick.
   - **Multi-page snapshot** (each animation iteration → one PDF page).
   - **Skip entirely** (treat as `animation: none`).

   The choice affects 5XX, 5YY, 5ZZ, 5AAA, and SVG SMIL in Phase 7. **Recommendation: configurable hook, default = 1.0.**

2. **Freeze date for v1.** Proposed: **2027-01-01** (7 months runway). Alternatives: 2026-07-01 (3 months — too tight for Phase 4 substrate), 2027-07-01 (12 months — slower but lets Color 5 / Fonts 5 mature). **Recommendation: 2027-01-01.**

3. **Substrate package shape.** Three new top-level packages proposed (`phpdftk/raster`, `phpdftk/resource-loader`, `phpdftk/wpt-harness`) plus extensions of existing (`phpdftk/text`, `phpdftk/color`, `phpdftk/paged-media` extract). Alternative: bundle the raster compositor into `phpdftk/pdf-core` to avoid the new package surface. **Recommendation: new packages — they're independently useful (image filters, HTTP fetching with SSRF gate, spec conformance harness) and match the existing one-package-per-domain pattern.**

## Cadence + metrics

- Weekly: `composer spec-status` regenerates the inventory; CI updates the dashboard
- Per PR: WPT delta posted as a comment (passes gained / lost on the in-scope subset)
- Monthly: published progress report (rolling 30-day delta + projected ship date for v1)
- Quarterly: substrate review (Phase 4 strands)
- Annually (January): spec re-sync + changelog ship

## Scope discipline

Net-new specs that publish during a snapshot window are *not* implemented mid-cycle. They go onto the next year's intake list. This protects the finish line from drifting and lets `% complete` actually approach 100 within the snapshot.

Net-new specs that publish *after* v1 100% are tracked the same way: queued for the next January re-sync, batched into a year's work.

## Termination criteria

The 100% claim ships when both gates pass on the **same commit**:

1. `composer wpt` returns 100% on the in-scope manifest
2. `composer compliance` returns 100% on the custom PDF-conformance suite
3. Manual sign-off on the out-of-scope ledger snapshot

Once that ships, v1 is closed. Maintenance for subsequent snapshots reuses the same gates.
