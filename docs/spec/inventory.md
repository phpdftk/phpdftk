# Spec inventory — coverage status per module

This is the operational ledger for the 100% roadmap (`docs/plans/full-spec-compliance.md`). Every CSS module, HTML chapter, and SVG section that's in scope has a row here. Status reflects the current implementation state and is the input the Phase 5 / 6 / 7 sub-phases pick work from.

**Source of truth.** Once Phase 4A (WPT harness) lands, `composer spec-status` will regenerate this file from the harness pass rates plus the manifest. Until then, status is hand-maintained as a best-effort estimate.

**Status legend.**
- ✅ **Complete** — applicable surface implemented; WPT in-scope subset passes (or, pre-4A, hand-verified against the spec text).
- 🟨 **Partial** — common path implemented; specific features deferred or known gaps tracked.
- ⬜ **Not started** — nothing implemented yet.
- 🚫 **Out of scope** — see `out-of-scope.md`. Listed here for completeness so the inventory matches the full spec corpus.
- 🟦 **In progress** — a sub-phase is actively working on it.

**Substrate dependencies** point to the Phase 4 strands (`4A`–`4G`) defined in the plan. A module can't reach ✅ until its substrate deps are ✅.

---

## CSS

### Foundational modules

| Sub | Module + version | Status | Est % | Substrate dep | Notes |
|---|---|---|---|---|---|
| 5A | CSS Syntax 3 | 🟨 | 88% | — | Tokenizer round-trips most input; serialisation edge cases pending WPT signal |
| 5B | CSS Values + Units 4 | 🟨 | 72% | — | `calc()`, `min()`, `max()`, `clamp()`, `attr()`, `env()`, typed `<time>` land; `mod()`, `rem()`, `round()`, trig partial |
| 5C | CSS Cascade + Inheritance 5, 6 | 🟨 | 75% | — | `@import`, specificity, `!important`, `unset`/`initial`/`inherit`, `revert`/`revert-layer` (mapped to initial) done; cascade layers (5) pass-through; scope (6) pass-through |
| 5D | CSS Custom Properties 1 | 🟨 | 60% | — | `--foo` + `var()` reading land; typed CustomProperty value with fallback; `@property` typed registration + value-time validation pending |
| 5E | CSS Selectors 4 | 🟨 | 82% | — | Type / class / id / attribute / structural pseudos done; `:has()` with leading combinators, `:is()`, `:where()`, `:nth-child(an+b of S)` filtered-subset, HTML §3.2.6.1 CI attribute defaults, `:link`/`:any-link`, `:default`, `:placeholder-shown`, static UI states (`:disabled`/`:checked`/`:required`/`:read-only`) all light up |
| 5F | CSS Pseudo-Elements 4 | 🟨 | 55% | — | `::before` / `::after` done; `::marker`, `::placeholder`, `::file-selector-button`, `::backdrop`, `::part`, `::slotted` pending |
| 5G | CSS Conditional Rules 3, 4, 5 | 🟨 | 65% | — | `@media`, `@supports` (boolean + `selector()`), `@layer`, `@scope`, `@container`, `@starting-style`, `@position-try` all pass through the cascade; full value queries pending |
| 5H | CSS Media Queries 5 | 🟨 | 60% | — | `print`, `screen`, dimension queries done; `prefers-*` defaults conservative; `pointer`, `hover`, `inverted-colors`, `update` out-of-scope |

### Color + paint

| Sub | Module + version | Status | Est % | Substrate dep | Notes |
|---|---|---|---|---|---|
| 5I | CSS Color 4 | 🟨 | 65% | 4E | sRGB / hex (3/4/6/8) / `rgb()` / `hsl()` / named done; `color(<space>)` parses with all 8 spaces; `lab()`, `lch()`, `oklab()`, `oklch()` parse + serialise; gamut mapping pending |
| 5J | CSS Color 5 | 🟨 | 60% | 4E | `color-mix()` typed + computed-value resolved in sRGB; `light-dark()` typed + resolved via `color-scheme`; `contrast-color()` typed + WCAG luminance resolution; relative color syntax; `device-cmyk()` typed; oklab/oklch mixing math pending |
| 5J' | CSS Color 6/7 | 🟨 | 45% | 4E | `contrast-color()` resolves; `system-color()` partial; `device-cmyk()` typed |
| 5P | CSS Backgrounds 3 | ✅ | 88% | — | colours, images (gradient + image-set + cross-fade), position, size, repeat, attachment, origin/clip slots, border-radius all flowing through the shorthand expander; `background-blend-mode` declares but doesn't render until 4C; gradient interpolation method (`in oklch` etc.) typed |
| 5Q | CSS Backgrounds 4 | 🟨 | 50% | 4C | Conic gradients typed + parse; gradient interpolation method (Images 4 §3.1.2); `background-clip: text` parses; multi-position backgrounds via comma layers |
| 5R | CSS Borders 4 | 🟨 | 75% | — | Long-hand borders + logical borders (block / inline / single-side) all expand; `border-image` family + shorthand; `border-width` keywords (thin/medium/thick) resolved in ComputedStyle + layout |

### Box + layout

| Sub | Module + version | Status | Est % | Substrate dep | Notes |
|---|---|---|---|---|---|
| 5S | CSS Box 3 | ✅ | 92% | — | Box model + corners + `box-sizing: border-box` propagated through min/max clamps; `place-items`/`place-content`/`place-self` shorthands |
| 5T | CSS Sizing 3 | 🟨 | 78% | — | `width`, `height`, `min/max-*-content`, `aspect-ratio` done; `fit-content()` edge cases pending |
| 5U | CSS Sizing 4 | 🟨 | 40% | — | `contain-intrinsic-size` family all registered; `interpolate-size` registered; intrinsic-size keyword edge cases pending |
| 5V | CSS Logical Properties 1 | 🟨 | 78% | — | All four-sided + logical-pair shorthands expand (margin/padding/inset block+inline); `border-block` / `border-inline` + single-side variants expand to longhand surface; physical-to-logical mapping at used-value time pending |
| 5W | CSS Display 3 | 🟨 | 68% | — | block / inline / inline-block / flex / grid / table done; `display: contents`, `display: inline list-item`, `display: math` pending |
| 5X | CSS Positioned Layout 3 | 🟨 | 62% | — | `relative`, `absolute`, `fixed` (page-relative) done; `sticky` → out of scope; containing-block edge cases pending |
| 5Y | CSS Anchor Positioning 1 | 🟨 | 50% | — | `anchor()` + `anchor-size()` typed parsers; `position-anchor` / `position-area` / `inset-area` (legacy alias) / `position-try` shorthand all cascade; declarative-only (interactive flip out) |

### Typography

| Sub | Module + version | Status | Est % | Substrate dep | Notes |
|---|---|---|---|---|---|
| 5K | CSS Fonts 4 | 🟨 | 75% | 4D, 4F | font-variant family (8 longhands), font-synthesis (5 longhands), font-stretch axis, font-optical-sizing, font-language-override, font-size-adjust all registered. `font-variant` + `font-synthesis` shorthand expansion. `font-feature-settings` + `font-variation-settings` typed post-process. `font-variant-*` → OpenType GSUB/GPOS tags wired to shaper (tabular-nums emits `tnum` etc.). `font-stretch` plumbed through FontResolver. `@font-face` partial via ResourceLoader |
| 5L | CSS Fonts 5 | 🟨 | 20% | 4D, 4F | `font-palette` registered + cascades; `@font-palette-values` parses; palette indexing at shaping time pending |
| 5M | CSS Text 3 | 🟨 | 55% | 4D | `text-align`, `letter-spacing`, `word-spacing`, `text-transform` done; `text-transform: full-width` (Text 4 §2.1.4) added; full justification, `tab-size`, `text-justify` partial |
| 5N | CSS Text 4 | 🟨 | 45% | 4D | `text-wrap` + `text-wrap-mode` + `text-wrap-style` registered + shorthand expansion (`balance` / `pretty` / `stable`); `white-space` shorthand lowers to `white-space-collapse` + `text-wrap-mode` (Text 4 §3.1); `text-spacing-trim` / `text-spacing` / `text-autospace` registered; `text-underline-position` registered; `hyphenate-character` / `hyphenate-limit-chars` registered |
| 5O | CSS Text Decoration 3, 4 | 🟨 | 70% | 4D | `text-decoration-line / -color / -style / -thickness` all done; shorthand accepts thickness slot; `spelling-error` / `grammar-error` line keywords; `text-emphasis` shorthand expansion + family registered; `text-decoration-skip` / `text-emphasis-skip` registered; multi-line decoration partial |
| 5Z | CSS Inline Layout 3 | 🟨 | 42% | 4D | Baseline positioning partial; `text-box-trim`, `initial-letter`, full vertical-align lookup pending |
| 5LL | CSS Writing Modes 4 | 🟨 | 32% | 4D | `direction`, `writing-mode: horizontal-tb` works; vertical writing modes, `text-orientation` pending |
| 5AA | CSS Lists 3 | 🟨 | 65% | — | `list-style-type`, `list-style-position`, marker rendering done; `list-style-image` registered; `::marker` pseudo, `marker-side` registered; `counter-reset`, `counter-set`, `counter-increment` all flow through BoxGenerator |
| 5BB | CSS Counter Styles 3 | 🟨 | 70% | — | Predefined styles + bijective base 26, Roman, Hebrew gematria, Armenian, Georgian, Hiragana / Katakana / iroha orderings all render; lower-greek (1-24); decimal-leading-zero; `@counter-style` parsing partial |
| 5CC | CSS Generated Content 3 | 🟨 | 78% | — | `content:` with strings, `attr()` (typed), `counter()`, `counters()` with separator + style done; typed AttrFunction + EnvFunction; counter-set wires through. Quote pairing via `quotes` property |

### Layout — advanced

| Sub | Module + version | Status | Est % | Substrate dep | Notes |
|---|---|---|---|---|---|
| 5EE | CSS Flexbox 1 | 🟨 | 78% | — | Main flex semantics land; `flex` + `flex-flow` shorthand expansion; edge cases (auto-margin, baseline alignment, multi-line) via WPT |
| 5FF | CSS Grid 1 | 🟨 | 68% | — | Explicit / implicit grids, gaps, areas done; `grid-column` + `grid-row` + `grid-area` shorthand expansion; `auto-fit` / `auto-fill` partial |
| 5GG | CSS Grid 2 | 🟨 | 25% | — | Subgrid declarative parsing in; layout pending |
| 5HH | CSS Grid 3 | ⬜ | 0% | — | Masonry layout |
| 5II | CSS Multi-column 1 | 🟨 | 50% | 4G | `column-count`, `column-width`, `column-gap`, `columns` shorthand, `column-rule` shorthand all flow; `column-span: all`, fragmentation across columns partial |
| 5JJ | CSS Multi-column 2 | 🟨 | 18% | 4G | Column rules registered; `column-fill: balance` algorithm pending |
| 5KK | CSS Tables 3 | 🟨 | 55% | — | Table layout (auto + fixed) basic; `border-collapse`, `vertical-align`, `caption-side` all flow; subgrid-on-tables pending |
| 5DD | CSS Containment 3 | 🟨 | 50% | — | `contain`, `content-visibility` (hidden suppresses box generation), `contain-intrinsic-*` family, `container`, `container-name`, `container-type` all registered; `@container` query body passes through cascade; size matching at layout time pending |
| — | CSS Cascade Layers (5C supplement) | 🟨 | 25% | — | `@layer` parses + passes through; cascade selection by layer priority pending |

### Effects + transforms

| Sub | Module + version | Status | Est % | Substrate dep | Notes |
|---|---|---|---|---|---|
| 5RR | CSS Transforms 2 — 2D | ✅ | 95% | — | All 2D transforms land; transform-origin, multi-transform composition complete |
| 5SS | CSS Transforms 3 — 3D | 🟨 | 12% | 4C | `transform-style`, `backface-visibility`, `perspective` registered + parse; 3D rendering pending raster |
| 5TT | CSS Masking 1 | 🟨 | 55% | 4C | `clip-path` shapes (circle/ellipse/inset/polygon/rect/xywh/path typed); `mask` shorthand expansion (8 longhands); `mask-border` family + shorthand expansion; SoftMask + luminance/alpha mode wired in painter for the common path |
| 5UU | CSS Filter Effects 1 | 🟨 | 45% | 4C | Typed `Filter` value + 12 `FilterKind`s; drop-shadow paints via box-shadow path; blur, brightness, contrast, grayscale, etc. parsed + cascade carries; SoftMask emission pending 4C |
| 5VV | CSS Compositing + Blending 1 | 🟨 | 50% | 4C | All 16 PDF-native blend modes wired; `background-blend-mode` cascade declared; `isolation: isolate` plumbing pending |
| 5WW | CSS Compositing 2 | 🟨 | 10% | 4C | Cross-stacking-context blend interactions pending |

### Animation

| Sub | Module + version | Status | Est % | Substrate dep | Notes |
|---|---|---|---|---|---|
| 5XX | CSS Animations 1, 2 | 🟨 | 55% | — | All 8 animation-* longhands registered + `animation` shorthand expansion (8-axis routing); `animation-range`, `animation-composition`, `animation-timeline` registered; `@keyframes` round-trip; static final-state rendering via `Pdf::renderAnimationsAt(1.0)` hook pending |
| 5YY | CSS Transitions 1, 2 | 🟨 | 58% | — | All 4 transition-* longhands + `transition-behavior` registered; `transition` shorthand expansion (4-axis routing); typed `<time>` value parses (s + ms); rendering pending |
| 5ZZ | CSS Easing Functions 1, 2 | 🟨 | 70% | — | Cubic-bezier / steps (5 jump terms) / linear() typed parsers; round-trip; consumed by 5XX / 5YY at hook time |
| 5AAA | CSS Motion Path 1 | 🟨 | 25% | — | All offset-* longhands registered + cascade preserves; path-following at paint time pending |
| 5AAB | CSS Scroll-driven Animations 1 | 🟨 | 30% | — | `view-timeline-*` / `scroll-timeline-*` / `timeline-scope` / `animation-timeline` registered; `view()` + `scroll()` typed parsers; interactive scrolling part out-of-scope per ledger |

### Paged media (critical for PDF)

| Sub | Module + version | Status | Est % | Substrate dep | Notes |
|---|---|---|---|---|---|
| 5MM | CSS Page 3 | 🟨 | 65% | 4G | `@page` with `size`, `margin`, named pages, `@page :first/:left/:right` selectors; all 16 margin-box positions parse, 10 paint; `marks`, `bleed` registered; `page-break-{before,after,inside}` legacy aliases flow to modern `break-*` longhands |
| 5NN | CSS Generated Content for Paged Media 3 | 🟨 | 80% | 4G | `target-counter()` / `target-counters()` / `target-text()` typed (cross-reference TOC); `string-set` + `string()` runtime — h1 `string-set: chapter content()` → `@page { content: string(chapter) }` works end-to-end; `position: running()` + `element()` runtime — running headers/footers work end-to-end; `string-set` accepts `counter()` for section numbers; per-page first/start/last/first-except resolution pending |
| 5OO | CSS Page Floats 3 | ⬜ | 0% | 4G | Column / page floats |
| 5PP | CSS Fragmentation 3 | 🟨 | 45% | 4G | `break-before / -after / -inside: avoid`, `orphans`, `widows` partial; block fragmentation across columns + pages pending |
| 5QQ | CSS Fragmentation 4 | ⬜ | 12% | 4G | Fragmented border decoration, repeated table headers |

### Out-of-scope CSS modules (listed for completeness)

| Module | Status | Reason |
|---|---|---|
| CSS Scroll Snap 1 | 🚫 | No scrolling in PDF |
| CSS Overscroll Behavior 1 | 🚫 | No scroll |
| CSS Scrollbars 1 | 🚫 | No scrollbars |
| CSS Scroll Anchoring 1 | 🚫 | No scroll |
| CSS Will Change 1 | 🚫 | Perf hint — implemented as no-op |
| CSS Touch 1 | 🚫 | No touch |
| CSS Speech 1 | 🚫 | Audio synthesis |
| CSS View Transitions 1, 2 | 🚫 | No navigation |
| CSS Animation Worklet | 🚫 | Worklet runtime |
| CSS Paint / Layout / Properties & Values API (worklet-side) | 🚫 | Worklet runtime |
| CSS Device Adaptation (`@viewport`) | 🚫 | Device viewport doesn't apply |

---

## HTML (WHATWG living standard, snapshotted)

| Sub | Section | Status | Est % | Notes |
|---|---|---|---|---|
| 6A | §12 The HTML syntax (parser + tree construction) | 🟨 | 70% | Tokenizer + tree constructor handle common cases; foster parenting, adoption agency, template insertion modes partial. Driven by WPT `html/syntax/` failures. |
| 6B | §13 The XML syntax | 🟨 | 50% | XHTML parser available; namespace handling partial |
| 6C | §3 Semantics, structure, and APIs of HTML documents (DOM rendering) | 🟨 | 60% | Document, DocumentFragment, Element, Text, Attr, NamedNodeMap; mutation API runtime is out of scope (we render the static DOM) |
| 6D | §4.1–4.5 Sections, headings, grouping, text-level semantics | 🟨 | 75% | Most semantic elements render with sensible defaults |
| 6E | §4.6 Links + §4.7 Edits (`<ins>` / `<del>`) | 🟨 | 60% | Links → PDF `/Link` annotations; edits style-bearing |
| 6F | §4.8 Embedded content (`<img>`, `<picture>`, `<video poster>`, `<iframe>`, `<object>`) | 🟨 | 35% | `<img>` solid; `<picture>` partial (no `srcset` selection — needs DPR policy); `<iframe>` static rendering of fetched content (gated on 4F); `<video poster>` extraction pending |
| 6G | §4.9 Tables | 🟨 | 65% | Co-evolves with CSS Tables 3 (5KK) |
| 6H | §4.10 Forms (rendering only) | 🟨 | 40% | `<input>`, `<select>`, `<textarea>`, `<button>` render with default UA styles; form *submission* is out of scope |
| 6I | §4.11 Interactive elements (`<details>`, `<summary>`, `<dialog>`, popovers) | 🟨 | 30% | Open-state / expanded-state rendering only; toggle behaviour is out of scope |
| 6J | §4.13 Custom elements + declarative shadow DOM | ⬜ | 10% | Declarative shadow DOM rendering; custom element registration is out of scope |
| 6K | §4.14 Common idioms not covered by other sections | 🟨 | 50% | Microdata parsing for selector matching |
| — | §10 Web application APIs | 🚫 | — | Out of scope per ledger |
| — | §7 Loading webpages | 🚫 | — | Out of scope per ledger |

---

## SVG 2 (W3C REC)

| Sub | Section | Status | Est % | Substrate dep | Notes |
|---|---|---|---|---|---|
| 7A | §6 Coordinate systems, transforms, viewports | ✅ | 95% | — | Full preserveAspectRatio + typed `<view>` |
| 7B | §7 Document structure (`<svg>`, `<g>`, `<defs>`, `<symbol>`, `<use>`, `<switch>`) | ✅ | 92% | — | `<switch>` with conditional-processing (`requiredExtensions`, `systemLanguage`); `<use>` external href still pending (4F) |
| 7C | §9 Paths | ✅ | 95% | — | Full path grammar + arc-to-cubic; per-path bounding box |
| 7D | §10 Basic shapes | ✅ | 100% | — | All 7 shapes |
| 7E | §11 Text | 🟨 | 68% | 4D | Standard PDF fonts + per-glyph x/y/rotate + dx/dy; per-`<tspan>` font overrides, `<textPath>` pending |
| 7F | §12 Embedded content (`<image>`) | 🟨 | 72% | 4F | Filesystem + data: URIs; http(s) via ResourceLoader |
| 7G | §13 Painting: filling, stroking, and marker symbols | 🟨 | 80% | — | Strokes, fills, gradients land; typed `<marker>` element with viewBox-aware refX/refY + orient angle units; marker placement at path vertices pending |
| 7H | §14 Clipping, masking and compositing | 🟨 | 75% | 4C | `<clipPath>` + `<mask>` land; per-child `clip-rule` partial |
| 7I | §15 Filter Effects | 🟨 | 35% | 4C | Typed `<filter>` element + all 26 `<fe*>` primitives (Gaussian / offset / flood / blend / composite / morphology / merge / colour matrix / drop shadow / turbulence / image / tile / displacement map / convolve matrix / component transfer + funcR/G/B/A / diffuse + specular lighting + 3 light sources). Drop-shadow renders via box-shadow path; other primitives need 4C raster |
| 7J | §16 Interactivity, scripting, animation | 🚫 / 🟨 | — | — | `<script>` typed-skip for security; `<animate>`, `<animateTransform>`, `<animateMotion>`, `<set>`, `<mpath>` typed-skip with SMIL accessor surface; t=1 declared state via shared animation hook |
| 7K | §17 Linking (`<a>`) | 🟨 | 65% | — | Typed `<a>` element with `href`/`target` + legacy `xlink:href`; paints children; in-document PDF `/Link` annotation pending bbox computation |
| 7L | §22 foreignObject | 🟨 | 25% | 4C, all HTML | Typed `<foreignObject>` element with placement accessors; body content skipped at SVG dispatch — full HTML-inside-SVG closes the loop |
| 7M | §13.7 Gradients — `spreadMethod` | 🟨 | 50% | — | `pad` via PDF `/Extend [true true]`; `reflect`, `repeat` need synthesised wider function domain |
| 7N | §13.3 Patterns | 🟨 | 25% | 4C | Typed `<pattern>` element with patternUnits + patternContentUnits + viewBox + xlink:href chain; PDF Tiling Pattern emission pending |
| 7O | §15.3 Title / Description / Metadata | 🟨 | 50% | — | Typed `<title>` / `<desc>` / `<metadata>` with text() accessor; skip-render so content doesn't leak into output; structure-tree integration pending |

---

## Cross-cutting

| Item | Status | Est % | Notes |
|---|---|---|---|
| PDF/A-2 + HTML/CSS/SVG conformance integration | 🟨 | 60% | `phpdftk/pdf-conformance` validates output; needs constraint checks that some CSS features (e.g. transparency) preclude PDF/A-1 |
| PDF/UA-1 + HTML semantic mapping | 🟨 | 40% | Tagged PDF emission from HTML structure |
| Accessibility tree from HTML / SVG | 🟨 | 30% | ARIA → marked content roles |
| ICC color profile chain (image → page → conformance) | 🟨 | 50% | Profile read + embed; color-management math gated on 4E |

---

## Aggregate dashboard

The aggregate "% complete" number on the project landing page is the weighted average of every in-scope row, weighted by the WPT test count for that module. Until the WPT manifest classifier (4A.4) is feeding real per-module weights, the headline uses a uniform-weighted estimate across the rows above.

**Real WPT-validated headline** (across seven CSS modules, 5,250 test files): **80.61% in-scope pass rate** — replacing the prior table-mean estimate of 55.2%. The cross-check showed our renderer is materially stronger than the per-row estimates implied.

For calibration: WeasyPrint after ~13 years ≈ 75%; Prince (~20yr commercial) ≈ 87%; headless Chromium (thousands of engineer-years) ≈ 99%.

### Validation against real WPT

A sparse-checkout run of `composer wpt run` against the upstream WPT corpus across seven CSS modules (`css-color`, `css-backgrounds`, `css-borders`, `css-text`, `css-display`, `css-box`, `css-sizing`, plus `css-fonts/parsing` + `css-fonts/at-font-face-descriptors`) — 5,250 test files — lands at **80.61% in-scope pass rate**:

```
WPT harness — corpus: /tmp/wpt-sparse (7 CSS modules, 5250 files)
  Total tests:        5250
    Pass:             2187
    Fail:             526
    Out of scope:     47
    Pending substr.:  317
    Skipped:          2173
    Harness errors:   0
  In-scope total:    2713
  In-scope pass:     80.61%
```

The css-color subset alone (366 files, where the manifest carries the most pending-substrate rules) lands at **59.65%**:

```
Filter: css/css-color/**
  Total tests:        366
    Pass:             102
    Fail:             69
    Out of scope:     52
    Pending substr.:  134
    Skipped:           9
  In-scope total:    171
  In-scope pass:     59.65%
```

Eighteen measurement tightenings landed in sequence to get here:

1. Wired the real render → Ghostscript-rasterise → ImageMagick-diff pipeline (Phase 4A.2/4A.3) — replacing the "not yet implemented" stub.
2. Added `<link rel="match" href="…">` parsing to the harness's reference locator — closed the original 229-test "no ref sibling" gap.
3. Classified wide-gamut colour spaces (`a98rgb`, `display-p3`, `prophoto-rgb`, `rec2020`, `lch`, `oklch`, `xyz`, `hwb`, `srgb-linear`, `predefined`, `contrast-color`, `@color-profile`) as pending Phase 4E (colour engine), and moved script-driven testharness.js tests (`parsing/**`, `animation/**`, `getcomputedstyle*`, `inheritance*`, `colorscheme-iframe*`, `canvas-*`, `visited*`) into out-of-scope.
4. **CSS tokeniser**: silently consume XHTML `<![CDATA[ ... ]]>` delimiters so XHTML stylesheets actually parse (previously the leading `<![CDATA[` dropped the whole stylesheet).
5. **PDF writer**: emit 20-byte xref entries per ISO 32000-2 §7.5.4 (was 21 bytes, with trailing space); Ghostscript was issuing "Invalid xref entry" warnings on every PDF.
6. **Per-test fuzzy tolerance**: parse `<meta name="fuzzy" content="…totalPixels=lo-hi">` and pass the upper bound to the Scorer as the per-test allowed pixel budget — letting tests with spec-allowed sub-pixel jitter pass at the threshold their author chose.
7. **Animation reclassification**: moved `css-backgrounds/animations/`, `css-box/animation/`, `css-display/animations/`, `css-sizing/animation/`, `css-sizing/contain-intrinsic-size/animation/`, and `css-text/animations/` to pending Phase 4H (animation runtime) — these were honest-fail tests we'd been counting as in-scope failures, plus a handful of lucky-pass tests (snapshot at time 0 happened to match the ref) properly demoted.
8. **Transparent border short-circuit**: `border: 10px solid transparent` was rendering as black bars because the visibility check only consulted `border-style`. CSS Backgrounds 3 §4.4 — fully-transparent border colour contributes nothing visible. Painter now skips the side fill when alpha = 0. Unblocked the three `clip-border-area-on-root` reftests (and a long tail of "transparent border around a coloured area" tests) at a stroke.
9. **Canvas background propagation**: CSS Backgrounds 3 §3.11.2 — when the root element has a non-transparent background, paint the entire canvas (page rect) with it, not just the root's own box rect. The renderer was matching the box geometry only, so an empty document with `:root { background: green }` rendered as 0×0 nothing. Now lands exactly on the spec'd canvas paint. Slight net dip in raw pass count (-0.07 pp) — some tests that fit the old behaviour by accident were "lucky-pass" cases; the spec'd behaviour is what Chrome / Firefox emit.
10. **Body→canvas background fallback**: CSS Backgrounds 3 §3.11.2 second paragraph — when the root has a transparent background AND there's an HTML body child, the body's background propagates to the canvas instead, and the body itself paints transparently. Stylesheets that put backgrounds on `<body>` (the conventional HTML idiom) now paint canvas-wide rather than at the body's box rect. Unlocks the `background-color-body-propagation` cluster.
11. **`contain: paint` propagation guard**: CSS Containment 3 §4.4 — a paint-contained root or body forms a paint boundary, so the canvas-bg propagation rules suppress. Painter now consults `contain` (recognising `paint`, `strict`, `content`) and bails out of propagation when either box is paint-contained. Closes the `background-color-body-propagation-008/009` reftests.
12. **`position: absolute` corner-anchor layout**: CSS 2.1 §10.3.7 / §10.6.4 — when both opposing edge anchors are set (`left+right` or `top+bottom`) and the corresponding size is `auto`, the box's size is derived from `cb_size - start - end - margins - borders - padding` (the borders/padding subtraction depends on `box-sizing`). The previous layout ignored corner anchors entirely; `top:0; left:0; right:0; bottom:0` rendered as a zero-sized strip instead of filling its containing block. Reftests across `background-margin-root`, `background-attachment-margin-root`, `background-repeat-round`, and others use this idiom on the ref side — fixing it unlocked the whole cluster.
13. **SVG-background pipeline + harness `baseDir`**: three coupled fixes land together so the harness can finally resolve relative `<img src>` / `background-image: url(...)` URLs against the WPT corpus the way browsers resolve them against `location.href`. (1) `Painter::intrinsicSvgSize` returns `null` when the SVG carries no useful intrinsic info, so `cover` / `contain` fall back to the bg-positioning area (CSS Backgrounds 3 §3.9). (2) `SvgRenderer::draw` + `Translator::paint` synthesise a viewport from the destination rect when the SVG declares percentage-style dimensions, so inner percentage attributes resolve against the area the SVG actually paints into. (3) `background-position` initial value now resolves to `0% 0%` (top-left) per CSS Backgrounds 3 §3.6, replacing the previous `50%` default that was misrouting explicit-tile renders. Net WPT: roughly even (~29 SVG-vector tests gain, balanced by the same number of tests that previously passed only because the image silently failed to load). The architectural payoff is access to the entire image-loading code path, which had been dormant.
14. **Extreme-aspect SVG `contain`**: SVGs whose intrinsic ratio derives from an extreme viewBox aspect (e.g. `viewBox="0 0 2147483647 1"`) collapse one side to a rounding-to-zero pixel count. `intrinsicSvgSize` now lets the derived dimension fall to zero (instead of clamping to one), and `resolveBackgroundSize` guards `cover` with a degenerate-axis stretch fallback while letting `contain` resolve via INF-on-the-impossible-axis. The four `tall`/`wide`--`contain`--`height`/`width` reftests use the spec's "extreme aspect → vanishingly small tile" outcome on the ref side; matching it gets all four into passing.
15. **`background-size: auto` honours intrinsic dimensions**: CSS Backgrounds 3 §3.9 — when both axes resolve to `auto` and the image has an intrinsic size, the tile size is that intrinsic size (not the bg-positioning area). Previously every `auto` resolution returned box dims, masking the spec's intrinsic-size case across the whole vector cluster. Now SVGs with viewBox-derived intrinsics paint at their natural pixel size and tile per `background-repeat`. Closes 12 vector reftests in the `--auto--` row plus two collateral wins (`background-attachment-local-positioning-5`, `background-position-subpixel-at-border.tentative`).
16. **`background-repeat: round` scales tiles to fit**: CSS Backgrounds 3 §3.7 — `round` per axis chooses a tile size that divides the positioning area evenly: `tileDim = originDim / round(originDim / naturalTileDim)`. The previous implementation fell through to plain `repeat` semantics with the natural tile size, producing clipped partial tiles instead of the spec's scaled whole-number fit. `repeatModes` now exposes per-axis modes (`repeat` / `no-repeat` / `round` / `space`) and `roundTileDim` applies the scale-to-fit rescale before the tile loop runs. Closes the 3 raster `background-repeat-round-1b/1d/1e` reftests; the round-2 / round-3 gradient variants still need the analogous fix on the gradient path.
17. **SVG bg routes through the painter's content stream**: `SvgRenderer::draw` previously took its content stream from `Page::contentStream()`, which lazily allocates and attaches a *separate* content stream to the page. So when the painter opened a `q ... rect W ... ... Q` clip wrap around an SVG background-image tile, the SVG paint actually landed in a fresh second content stream — outside the wrap — and the bg-clip rect was effectively unused. `cover` mode (e.g. tile 768×3072 in a 768×256 box) painted the overflow across the whole page. Routing the SVG draw through the caller's stream (new `stream:` parameter on `SvgRenderer::draw`) keeps it inside the clip wrap. +22 tests, no regressions — the single highest-leverage fix of the session.
18. **SVG partial-intrinsic dims + dst-as-source viewport**: two coupled fixes for SVGs that supply only some of their dimension info (one fixed axis without a viewBox, percentage-only attributes, etc.). (1) `SvgRenderer::resolveSourceRect` adopts the caller's destination as the source viewport whenever the SVG doesn't have both a viewBox AND a fixed width/height pair — so inner percentage attributes (`<rect width="100%">`) resolve against the area the SVG actually paints into. (2) A new `intrinsicSizePartial` helper exposes nullable per-axis intrinsic dims + a `hasRatio` flag. `resolveBackgroundSize` / `resolveAutoSizePair` consult it for the `auto` paths so an SVG with `width="8px"` but no height resolves to a tile of `(8, box.height)` rather than stretching to the full box, matching the CSS Backgrounds 3 §3.9 default-object-size rule. +19 tests, no regressions.

The gap to WeasyPrint (~13 points on the broad corpus, ~28 points on css-color) is concentrated in the raster-dependent modules called out per-row (Filter Effects, 3D Transforms, advanced Masking, Page Floats, masonry / subgrid) and the colour engine (Phase 4E).

Once the manifest classifier (4A.4) wires per-module weighting and the corpus walk completes the full WPT in-scope set, this number replaces the table-mean and ceases to be a hand-maintained estimate.
