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
| 5A | CSS Syntax 3 | 🟨 | 85% | — | Tokenizer round-trips most input; serialisation edge cases pending WPT signal |
| 5B | CSS Values + Units 4 | 🟨 | 65% | — | `calc()`, `min()`, `max()`, `clamp()` land; nested calc, `mod()`, `rem()`, `round()`, trig functions partial |
| 5C | CSS Cascade + Inheritance 5, 6 | 🟨 | 70% | — | `@import`, specificity, `!important` done; cascade layers (5), scope (6) pending |
| 5D | CSS Custom Properties 1 | 🟨 | 60% | — | `--foo` declaration + `var()` reading land; `@property` typed registration, animation, `var()` fallback edge cases pending |
| 5E | CSS Selectors 4 | 🟨 | 55% | — | Type / class / id / attribute / structural pseudos done; `:has()`, `:is()`, `:where()` partial; functional `:nth-child(an+b of S)` pending |
| 5F | CSS Pseudo-Elements 4 | 🟨 | 50% | — | `::before` / `::after` done; `::marker`, `::placeholder`, `::file-selector-button`, `::backdrop`, `::part`, `::slotted` pending |
| 5G | CSS Conditional Rules 3, 4, 5 | 🟨 | 45% | — | `@media`, `@supports` (boolean) done; `@supports selector()`, value queries, `@supports font-format()`, `@container` partial |
| 5H | CSS Media Queries 5 | 🟨 | 55% | — | `print`, `screen`, dimension queries done; `prefers-*` defaults conservative; `pointer`, `hover`, `inverted-colors`, `update` out-of-scope |
| — | CSS Cascading and Inheritance — `revert` / `revert-layer` | 🟨 | 60% | — | `unset`, `initial`, `inherit` land; `revert` / `revert-layer` need layer engine |

### Color + paint

| Sub | Module + version | Status | Est % | Substrate dep | Notes |
|---|---|---|---|---|---|
| 5I | CSS Color 4 | 🟨 | 50% | 4E | sRGB / hex / `rgb()` / `hsl()` / named colors done; `lab()`, `lch()`, `oklab()`, `oklch()`, `color(display-p3)`, `color(rec2020)` etc. need color engine; gamut mapping pending |
| 5J | CSS Color 5 | ⬜ | 0% | 4E | `color-mix()`, relative color syntax |
| — | CSS Color HDR | ⬜ | 0% | 4E | Gated on 5J + HDR profile work |
| 5P | CSS Backgrounds 3 | 🟨 | 70% | — | `background-color`, `background-image`, `background-position`, `background-size`, `background-repeat`, `border-radius` done; `background-blend-mode` punts to 4C; `background-clip: text` punts to 5Q |
| 5Q | CSS Backgrounds 4 | ⬜ | 0% | 4C | Conic gradients, `background-clip: text`, multi-position backgrounds |
| 5R | CSS Borders 4 | 🟨 | 55% | — | Long-hand borders done; logical borders pending; `border-image` round-trips parse but not all rendering modes |

### Box + layout

| Sub | Module + version | Status | Est % | Substrate dep | Notes |
|---|---|---|---|---|---|
| 5S | CSS Box 3 | ✅ | 90% | — | Box model + corners + recent 1E.2 row + `box-sizing: border-box` propagated through min/max clamps |
| 5T | CSS Sizing 3 | 🟨 | 75% | — | `width`, `height`, `min/max-*-content`, `aspect-ratio` done; `fit-content()` edge cases pending |
| 5U | CSS Sizing 4 | ⬜ | 10% | — | `contain-intrinsic-size`, additional intrinsic-size keywords |
| 5V | CSS Logical Properties 1 | 🟨 | 40% | — | Subset of logical equivalents done; full surface (logical scroll, logical viewport, all overflow logical) pending |
| 5W | CSS Display 3 | 🟨 | 65% | — | block / inline / inline-block / flex / grid / table done; `display: contents`, `display: inline list-item`, `display: math` pending |
| 5X | CSS Positioned Layout 3 | 🟨 | 60% | — | `relative`, `absolute`, `fixed` (page-relative) done; `sticky` → out of scope; containing-block edge cases pending |
| 5Y | CSS Anchor Positioning 1 | ⬜ | 0% | — | Static anchor positioning is in scope; interactive flip is out |

### Typography

| Sub | Module + version | Status | Est % | Substrate dep | Notes |
|---|---|---|---|---|---|
| 5K | CSS Fonts 4 | 🟨 | 35% | 4D, 4F | 14 standard PDF fonts + system family resolution land; `@font-face` partial; `font-variation-settings`, `font-feature-settings` pending |
| 5L | CSS Fonts 5 | ⬜ | 0% | 4D, 4F | `@font-palette-values`, font palette indexing |
| 5M | CSS Text 3 | 🟨 | 45% | 4D | `text-align`, `letter-spacing`, `word-spacing`, `text-transform` done; full justification, `tab-size`, `text-justify` pending |
| 5N | CSS Text 4 | ⬜ | 5% | 4D | `text-wrap: balance`, `hyphenate-character`, `text-spacing-trim` |
| 5O | CSS Text Decoration 3, 4 | 🟨 | 50% | 4D | `text-decoration-line / -color / -style` done; `text-decoration-skip-ink`, multi-line decoration, `text-emphasis-*` pending |
| 5Z | CSS Inline Layout 3 | 🟨 | 40% | 4D | Baseline positioning partial; `text-box-trim`, `initial-letter`, full vertical-align lookup pending |
| 5LL | CSS Writing Modes 4 | 🟨 | 30% | 4D | `direction`, `writing-mode: horizontal-tb` works; vertical writing modes, `text-orientation` pending |
| 5AA | CSS Lists 3 | 🟨 | 55% | — | `list-style-type`, `list-style-position`, marker rendering done; `list-style-image`, `::marker` pseudo, `marker-side` pending |
| 5BB | CSS Counter Styles 3 | 🟨 | 50% | — | Predefined styles done; `@counter-style` parsing partial |
| 5CC | CSS Generated Content 3 | 🟨 | 60% | — | `content:` with strings, `attr()`, `counter()` done; `content-list` with quotes, `element()`, `target-counter()` pending |

### Layout — advanced

| Sub | Module + version | Status | Est % | Substrate dep | Notes |
|---|---|---|---|---|---|
| 5EE | CSS Flexbox 1 | 🟨 | 75% | — | Main flex semantics land; edge cases (auto-margin, baseline alignment, multi-line) via WPT |
| 5FF | CSS Grid 1 | 🟨 | 65% | — | Explicit / implicit grids, gaps, areas done; `auto-fit` / `auto-fill`, subgrid edge cases pending |
| 5GG | CSS Grid 2 | 🟨 | 25% | — | Subgrid declarative parsing in; layout pending |
| 5HH | CSS Grid 3 | ⬜ | 0% | — | Masonry layout |
| 5II | CSS Multi-column 1 | 🟨 | 40% | 4G | `column-count`, `column-width` partial; `column-span: all`, fragmentation across columns pending |
| 5JJ | CSS Multi-column 2 | ⬜ | 10% | 4G | Column rules, `column-fill: balance` algorithm |
| 5KK | CSS Tables 3 | 🟨 | 50% | — | Table layout (auto + fixed) basic; `border-collapse`, `vertical-align`, `caption-side`, subgrid-on-tables pending |
| 5DD | CSS Containment 3 | 🟨 | 30% | — | Size containment partial; container queries (size + style) pending |
| — | CSS Cascade Layers (5KK supplement) | ⬜ | 15% | — | `@layer` parses; cascade selection by layer pending |

### Effects + transforms

| Sub | Module + version | Status | Est % | Substrate dep | Notes |
|---|---|---|---|---|---|
| 5RR | CSS Transforms 2 — 2D | ✅ | 95% | — | All 2D transforms land (Phase-2 2B); transform-origin, multi-transform composition complete |
| 5SS | CSS Transforms 3 — 3D | ⬜ | 5% | 4C | `perspective`, `transform-style: preserve-3d`, backface-visibility |
| 5TT | CSS Masking 1 | 🟨 | 25% | 4C | `clip-path` shapes basic; `mask-image`, `mask-composite`, `mask-mode` pending |
| 5UU | CSS Filter Effects 1 | ⬜ | 0% | 4C | All `filter:` functions need 4C raster compositor |
| 5VV | CSS Compositing + Blending 1 | 🟨 | 40% | 4C | PDF-native blend modes (`Normal`, `Multiply`, `Screen`, `Overlay`, `Darken`, `Lighten`, `ColorDodge`, `ColorBurn`, `HardLight`, `SoftLight`, `Difference`, `Exclusion`, `Hue`, `Saturation`, `Color`, `Luminosity`) wired; `isolation: isolate` plumbing pending |
| 5WW | CSS Compositing 2 | ⬜ | 0% | 4C | Edge-case blend mode interactions across stacking contexts |

### Animation

| Sub | Module + version | Status | Est % | Substrate dep | Notes |
|---|---|---|---|---|---|
| 5XX | CSS Animations 1 | 🟨 | 30% | — | Parser + `@keyframes` round-trip; static final-state rendering via `Pdf::renderAnimationsAt(1.0)` hook pending |
| 5YY | CSS Transitions 1 | 🟨 | 30% | — | Parser round-trip; transition target value rendering pending — same hook as 5XX |
| 5ZZ | CSS Easing Functions 1, 2 | 🟨 | 50% | — | Cubic / steps / linear-easing parse; consumed by 5XX / 5YY at hook time |
| 5AAA | CSS Motion Path 1 | ⬜ | 0% | — | Path-following animation, gated on 5XX hook |

### Paged media (critical for PDF)

| Sub | Module + version | Status | Est % | Substrate dep | Notes |
|---|---|---|---|---|---|
| 5MM | CSS Page 3 | 🟨 | 45% | 4G | `@page`, page selectors (`:first`, `:left`, `:right`) basic; named pages, `@page :nth()`, page marks pending |
| 5NN | CSS Generated Content for Paged Media 3 | 🟨 | 30% | 4G | Running elements partial; cross-references, leaders, lists of figures pending |
| 5OO | CSS Page Floats 3 | ⬜ | 0% | 4G | Column / page floats |
| 5PP | CSS Fragmentation 3 | 🟨 | 40% | 4G | `break-before / -after / -inside: avoid`, `orphans`, `widows` partial; block fragmentation across columns + pages pending |
| 5QQ | CSS Fragmentation 4 | ⬜ | 10% | 4G | Fragmented border decoration, repeated table headers |

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
| 7A | §6 Coordinate systems, transforms, viewports | ✅ | 95% | — | Phase 3M + 3R+6 (full preserveAspectRatio) |
| 7B | §7 Document structure, `<svg>`, `<g>`, `<defs>`, `<symbol>` | ✅ | 90% | — | Phase 3Q done; `<use>` external href still pending (gated on 4F) |
| 7C | §9 Paths | ✅ | 95% | — | Full path grammar + arc-to-cubic (3L); per-path bounding box (3R+9) |
| 7D | §10 Basic shapes | ✅ | 100% | — | Phase 3K complete |
| 7E | §11 Text | 🟨 | 65% | 4D | Standard PDF fonts + per-glyph x/y/rotate (3R+5) + dx/dy (3R+17); per-`<tspan>` font overrides, `<textPath>` pending |
| 7F | §12 Embedded content (`<image>`) | 🟨 | 70% | 4F | Filesystem + data: URIs (3R+18); http(s) gated on 4F |
| 7G | §13 Painting: filling, stroking, and marker symbols | 🟨 | 75% | — | Strokes, fills, gradients land; `<marker>` element pending |
| 7H | §14 Clipping, masking and compositing | 🟨 | 70% | 4C | `<clipPath>` + `<mask>` land (3R+8, 3R+10, 3R+11); per-child `clip-rule` needs 4C |
| 7I | §15 Filter Effects | ⬜ | 0% | 4C | All `<filter>` primitives need raster compositor |
| 7J | §16 Interactivity, scripting, animation | 🚫 / 🟨 | — | — | Interactivity / scripting out; SMIL `<animate>` resolves to t=1 declared state via shared animation hook |
| 7K | §17 Linking (`<a>`) | 🟨 | 60% | — | `<a>` → PDF `/Link` annotation; in-document refs partial |
| 7L | §22 foreignObject | ⬜ | 5% | 4C, all HTML | Renders HTML inside SVG — closes the loop |
| 7M | §13.7 Gradients — `spreadMethod` | 🟨 | 50% | — | `pad` lands at 3R+16 via PDF `/Extend [true true]`; `reflect`, `repeat` need synthesised wider function domain |

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

The aggregate "% complete" number on the project landing page is the weighted average of every in-scope row, weighted by the WPT test count for that module. Until 4A.4 (manifest classifier) lands, the weighting is uniform across rows and gives a rough headline number only.

**Current rough headline** (uniform-weighted estimate): **~33% applicable surface.**

Headline updates each weekly CI run once 4A.5 is live.
