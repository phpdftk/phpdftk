# CSS coverage targets (loop working notes)

Live target list for the push toward >90% CSS WPT. Updated 2026-06-30.
Current: **67.77%** (14,415 / 21,270, settler-off).

## Landed this loop (branch `css-coverage-push`, +83 net)
- `clip` property (CSS 2.1 §11.1.2) — **+43**
- single-value `background-position` centres the missing axis — **+33**
- `clip-path` basic shapes (inset/circle/ellipse/polygon) — **+39 / +13 net**
  (−26 are `clip-path/animations/*`, JS-driven; correct under the settler)

## Next concrete target — namespaced `<svg:svg>` foreign content (~44 fixtures)

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
