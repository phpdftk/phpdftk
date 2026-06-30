# CSS coverage targets (loop working notes)

Live target list for the push toward >90% CSS WPT. Updated 2026-06-30.
Current: **67.77%** (14,415 / 21,270, settler-off).

## Landed this loop (branch `css-coverage-push`, +121 net)
- `clip` property (CSS 2.1 §11.1.2) — **+43**
- single-value `background-position` centres the missing axis — **+33**
- `clip-path` basic shapes (inset/circle/ellipse/polygon) — **+39 / +13 net**
  (−26 are `clip-path/animations/*`, JS-driven; correct under the settler)
- inline-block border/padding in the no-font atomic path
  (`layoutAtomicOnly`) — **+38 net** (borders +32, positioning +4,
  tables +6, floats-clear −4). The fallback ignored border/padding, so an
  empty inline-block sized by a thick `border-{top,bottom}-width` (the
  CSS2 border-width tests) squared to its width and painted no border.
  Now folds both axes' inset into the geometry (box-sizing-aware, explicit
  `height:0` honoured). The −4 are float-context artifacts in a path that
  doesn't model floats. See `border-{top,bottom}-width-*` cluster.

## CSS2 (working in bucket order) — status

Densest CSS2 near-miss families and their nature (after sampling):
- `positioning/absolute-replaced-width` (40), `normal-flow/inline-replaced-width`, `floats-clear/float-replaced-width`, `positioning/absolute-replaced-height` — ALL the **replaced-element (SVG) sizing** area (see below). ~60+ CSS2 fixtures, deep.
- `backgrounds/background` (37) — heterogeneous background paint.
- `backgrounds/background-position-applies-to` (22) — table-display internals.
- `borders/border-{top,bottom}-width` (38) — edge-case length values (tiny/huge/inch).
- `margin-padding-clear/padding-{top,bottom}` (37) — mostly pass; failures are edge cases.
- `floats-clear/floats` (24) — inline reflow around floats.
- `tables/*` — real table layout (fixed-table-layout, collapsing-border).

**Done in CSS2 this loop:** `clip` (+43, visufx), single-value `background-position` (+33). The remaining top CSS2 clusters need real feature work, not near-miss cleanup.

## Deep area: replaced-element (SVG) sizing + prefixed foreign content

The biggest CSS2 cluster (`absolute-replaced-width` 40) + the inline/float
replaced-width clusters (~60+ total) all hinge on TWO gaps:

1. **Prefixed foreign content not normalized.** `<svg:svg>` keeps
   localName `"svg:svg"` (HTML ns), so NO CSS selector matches it — not
   the UA `svg{display:inline-block}` rule, not author `svg{height:100px}`,
   and `applyPresentationalAttributes` doesn't map svg width/height attrs.
   The box ends up 0×0 and renders empty. PARTIAL fix shipped
   (`de80ad18e`: foreign-root gate recognition + forced inline-block) —
   makes it render IF sized, but selectors/CSS still don't match.
   **Complete fix = DOM normalization:** rebuild `<svg:svg>`/`<svg:rect>`
   subtrees as `<svg>`/`<rect>` in SVG_NS (prefix stripped) BEFORE box-gen,
   so selectors + UA sheet + attrs all apply. `Node::replaceChild` exists,
   so a recursive subtree rebuild in a Renderer pre-pass is feasible.
2. **SVG replaced-element intrinsic/default sizing.** An SVG with no
   intrinsic width/ratio + `width:auto` should default to 300×150 (CSS
   Images 4 §8 / CSS2 §10.3.2 last-resort), with CSS/attr overrides and
   ratio transfer. Today the SVG atomic box isn't sized from this path.
   `applyPresentationalAttributes` handles img/embed/iframe/video — extend
   to svg (width/height attrs) + add the 300×150 default + ratio.

Land (1)+(2) together → unlocks the ~60 replaced-width/height fixtures.
The committed `de80ad18e` is a correct prerequisite (WPT-neutral alone).

## (earlier) Next concrete target — namespaced `<svg:svg>` foreign content (~44 fixtures)

**Root cause (confirmed):** XHTML-style fixtures (DOCTYPE XHTML, `<html
xmlns>`) use `<svg:svg>` / `<svg:rect>` with an `xmlns:svg` prefix. Parsed
by the HTML parser, foreign-content detection fails — `<svg:svg>` is not
recognised as the SVG root, so the whole SVG renders empty. Verified:
`<svg:svg>…</svg:svg>` → 0 painted px; the same markup unprefixed renders.
Affects `CSS2/positioning/absolute-replaced-width` (40) + ~4 others.

**Fix shape (3 sites — resolve the DOM-API question first):**
1. Determine what the HTML parser produces for `<svg:svg>`: `localName`
   (`"svg"` vs `"svg:svg"`), `prefix`, `namespaceURI`. (Element fields are
   readonly: `localName`, `namespaceURI`, `prefix`.)
2. Detection: `BoxGenerator::isForeignContentRoot` (~1623) and
   `Painter` atomic dispatch (~1232) must recognise the prefixed/namespace-
   less foreign root (e.g. accept `localName` ending `:svg` / a `svg:`
   prefix, and don't require `namespaceURI === SVG_NS` when the prefix
   already identifies it).
3. `InlineSvgAdapter::adapt` (~47/53) + the serializer it calls
   (`serialize($element, SVG_NS)`) must strip the `svg:` prefix from
   descendant element names so the SVG parser sees `<svg>`/`<rect>`.
   The adapter already serialises→re-parses, so stripping in the
   serializer is the single cleanest point.

Guard: don't break unprefixed inline SVG (the common path) or MathML.

## Cross-cluster theme — SVG content rendering in context (~100+)

Several clusters fail because SVG content renders empty/wrong in specific
contexts. Distinct sub-causes (don't assume one fix):
- **`<pattern>` fills + `transform` attr** — `css-transforms/matrix/
  svg-matrix` (56), `svg-origin` (46). `<pattern>` is unimplemented; the
  pattern-filled rect renders only the fallback. Big SVG feature.
- **`<img src=*.svg>` + object-fit** — `object-fit-{fill,cover,contain}-
  svg` (72). The minimal case RENDERS correctly; the fixtures fail for a
  fixture-specific reason (not yet isolated — likely object-position
  classes tr/bl/tl or multi-image layout). Re-diagnose per-fixture.
- **namespaced `<svg:svg>`** — see above (the tractable one).

## Other mapped clusters
- `CSS2/floats-clear/floats` (35, static) — inline reflow around floats.
- `css-shapes/shape-outside` circle/ellipse/inset/polygon (~96) — float
  exclusion shapes; real CSS Shapes layout.
- `css-sizing/aspect-ratio` flex/grid/replaced (~70) — see
  `feedback-aspect-ratio-lever`; multi-day.
- `css-writing-modes/abs-pos-non-replaced-v{lr,rl}` (174) — blocked on
  `docs/plans/vertical-inline-layout.md` (the foundation).
- `css-contain/content-visibility` (32) — **27/32 JS-driven** (settler);
  a `content-visibility:hidden` paint-skip is correct but near-inert
  settler-off. Skip unless doing settler-on work.

## Methodology note
Check JS-dependence (`classList`/`<script>`/`reftest-wait`) BEFORE
implementing a cluster — content-visibility (JS) and clip-001 /
clip-path-animations (JS) were false-passes that a correct static fix
flips. A spec-correct fix can be WPT-neutral or net-negative settler-off
when the cluster needs the DOM settler.
