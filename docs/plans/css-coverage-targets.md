# CSS coverage targets (loop working notes)

Live target list for the push toward >90% CSS WPT. Updated 2026-06-30.
Current: **67.77%** (14,415 / 21,270, settler-off).

## Landed this loop (branch `css-coverage-push`, ~+413 net)

- **inline-atomic line wrapping (no-font path)** — **+20 net** (CSS2/
  positioning +10, css-flexbox +9, css-tables +2, css-backgrounds −1).
  `layoutAtomicOnly` laid inline-blocks/replaced boxes left-to-right and
  never wrapped; now tracks a line box and wraps on overflow (CSS 2.1
  §9.4.2). Also fixed the reference side of the abspos-height cluster.
- **abspos margin-top:auto vertical slack (§10.6.5)** — **+2 net**
  (CSS2/positioning; absolute-non-replaced-{,max-}height margin-top:auto).
  Over-constrained abspos now lets an auto margin-top absorb the slack.
- **out-of-flow last-child bottom-margin collapse** — WPT-neutral defensive
  fix (symmetric to the top-edge one).


- **out-of-flow first-child margin-collapse** — **+20 net** (CSS2/
  positioning +17, margin-padding-clear +3, zero regressions). Parent-child
  top-margin collapse took `children[0]` unconditionally; an abs-pos first
  child's margin-top wrongly collapsed through the parent (CSS 2.1 §8.3.1),
  doubling a negative margin and shoving in-flow siblings off-page. Won the
  `top-*` unit reftests. AGGRESSIVE version (substitute first IN-FLOW child +
  skip out-of-flow in sibling cascade) regressed css-grid −36 — the minimal
  `!isOutOfFlow(first)` guard is the safe fix.
- **abspos content inside an inline-block + image(<color>)** — **+7 net**
  (css-images +6 (image-color 6/7), positioning +1, backgrounds +1, multicol
  −1). A positioned inline-block now hosts + paints its abs-pos descendants
  (`layoutAbsposInInlineAtomics`), skips blockification for abspos (not
  float) children, and `image(<color>)` incl. currentcolor + layered.


- **`<video poster>` object-fit rendering** — **+24 net** (css-images
  273→297). The last 18 object-fit-svg fails were the `*-p` variants using
  `<video poster="x.svg">`; painting the poster frame (from the `poster`
  attr) through the img path completed object-fit-*-svg at **120/120**.
- **floated / block replaced-element rendering** — **~+80 net**
  (css-images +72, css-backgrounds +9, CSS2/positioning +9, css-grid ±0,
  css-writing-modes −10 follow-up). `paintImage` only rendered
  `AtomicInlineBox`, so a FLOATED `<img>`/`<embed>`/`<object>` (blockified
  into a BlockBox) painted nothing — the css-images `object-fit-*-svg`
  (120) / `object-position` clusters float their replaced elements. Now
  paints floated replaced BlockBoxes (gated OUT of vertical WM + abspos/grid
  paths whose positioning isn't correct); added `<embed src>` / `<object
  data>` to the img SVG/raster path. Remaining: object-fit contain/cover/
  fill MATH for the last ~18 object-fit-svg, and embed/object WM
  positioning (−10 writing-modes).
- **CSS-wide keyword distribution across shorthands** — **+9 net**
  (borders +4, CSS2/backgrounds +1+4). `border: inherit` / `background:
  inherit` etc. now fan the keyword out to every longhand (CSS Cascade 5
  §3.2) instead of the component parser dropping it. Covers border family,
  margin, padding, outline, background, list-style.

### earlier this loop
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
- **table shrink-to-fit auto width (CSS 2.1 §17.5.2)** — **+42 net**
  (CSS2 tables +19, margin-padding-clear +5, floats-clear +2,
  normal-flow +1; css-tables +14, css-flexbox +1), zero regressions across
  17 dirs. Auto block tables now shrink-wrap (real `measureTableMinMax`
  intrinsic routed through the existing shrink-to-fit path), box-sizing /
  cell-padding / margin:auto handled. Design + codex review:
  `docs/plans/table-shrink-to-fit.md`. v1 gaps: border-spacing, caption
  CAPMIN, percentage columns, and inline-table (an AtomicInlineBox — the
  atomic path's separate gap).
- **table extra-height distribution (CSS 2.1 §17.5.3)** — **+29 net**
  (backgrounds +28, css-tables +1, box-display +1, normal-flow −1
  unattributable). A table taller than its rows distributes the surplus so
  cell bg/border fill the box. Definite `<length>` heights only.
- **replaced-element percentage width (`<img width="100%">`)** — **+43 net**
  (backgrounds +15, normal-flow +14, borders +12, positioning +2), zero
  regressions. `width="N%"` now maps to a CSS Percentage (was dropped by
  the px-only attribute parser) AND `layoutAtomicOnly` resolves a
  percentage width against the IFC available width (was Length-only → 0 →
  invisible). Recurring CSS2 ref pattern (`<img width="100%" height="N">`).
- **propagated root-background positioning** — **+2 net** (background-root
  -002/-016). A propagated root bg-image now anchors at the source
  element's padding box (was the page corner) and tiles across the whole
  canvas (was confined to the positioning area). Root propagation was
  already implemented (17/28 background-root pass); remaining fails are JS
  (101/102/103) + box-model border/margin-frame edge cases — not
  propagation bugs.

## ✅ LANDED — table shrink-to-fit auto width + extra-height distribution

- shrink-to-fit (commit `0e512cc04`): +42 net, zero regressions.
- extra-height distribution CSS 2.1 §17.5.3 (commit `ad44df2a1`): +29 net
  (backgrounds +28 — the `background` shorthand cluster's refs use
  `<table height>`; css-tables +1, box-display +1, normal-flow −1
  unattributable). Table taller than its rows distributes the surplus so
  cell bg/border fill the box. Definite `<length>` heights only (percentage
  deferred).

See `docs/plans/table-shrink-to-fit.md`. Follow-ups: border-spacing layout,
caption CAPMIN, percentage columns + percentage table height, and
**inline-table** (AtomicInlineBox — investigated, block-promotion makes any
fix net-zero; needs true inline-level FC layout — see memory).

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
