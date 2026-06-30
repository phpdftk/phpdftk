# §10.3.8 absolutely-positioned replaced elements (+ prefixed foreign content)

Target: the ~60 `css/CSS2/{positioning,normal-flow,floats-clear}/{absolute,
inline,float}-replaced-{width,height}` fixtures (the densest CSS2 cluster).
Branch `feat/abspos-replaced-sizing` (off `css-coverage-push`).

Status: **scoped, codex-reviewed, NOT landed.** A first implementation was
written then reverted — it didn't move the fixtures because the target
SVGs reach layout via a path that wasn't wired (see Part 4). This is a
FOUR-part feature; all parts are needed together for a net gain.

## The fixtures
Most use an XHTML `<svg:svg>`/`<svg:rect>` with the SVG sized by CSS
(`svg { width:200px; position:absolute; ... }`) or by attrs, compared to
an orange marker of the same width. They fail because the SVG box ends up
0×0 (renders empty) and/or positioned wrong.

## Part 1 — normalize prefixed foreign content (REQUIRED prerequisite)
`<svg:svg>` keeps localName `"svg:svg"` (HTML ns), so NO CSS selector
matches it — `svg{position:absolute}` doesn't apply, so it isn't even
abspos. A DOM pre-pass in `Renderer::render` (after `applyBaseHref`,
before `collectStylesheets`) must rebuild `<svg:svg>`/`<svg:rect>`
subtrees as `<svg>`/`<rect>` in SVG_NS, prefix stripped (`Node::replaceChild`
exists; recurse copying attrs minus `xmlns*`, and Text nodes). Verified in
isolation: `<svg:svg width=200 height=100>` renders after this. Implemented
+ reverted once (worked, but inert without Parts 2-4). Note: HTML lowercases
attrs, so `viewBox`→`viewbox` — the SVG parser already tolerates `viewbox`.

## Part 2 — §10.3.8 replaced sizing helper (codex-validated, was written)
`layoutAbsoluteReplacedAtomic(AtomicInlineBox, LayoutContext): float` near
`applyAbsoluteCornerAnchorSize` (~2041). Resolve adornment onto geometry,
then used content size via §10.3.2: CSS length/% wins; else intrinsic; else
ratio transfer from the definite opposite axis; else 300×150. `<svg>`
intrinsic = `width`/`height` attrs (px or %) + `viewBox` ratio; `<img>`
carries its ratio via the `aspect-ratio` cascade. Write the resolved size
back as definite `Length`s so `resolveAbsoluteOffsets` sees it. Helpers:
`resolveReplacedUsedSize`, `replacedIntrinsicSize`, `definiteReplacedLength`,
`parseReplacedAttrLength`. (Full code is in the session transcript — re-create.)
Do NOT call `applyAbsoluteCornerAnchorSize` for replaced atomics (its
both-insets `width:auto` is §10.3.7 non-replaced stretch).

## Part 3 — wire the BLOCK abspos paths
Replace `applyAbsoluteCornerAnchorSize + layoutBox` with the new helper for
`$child instanceof AtomicInlineBox` at all three block abspos call sites:
`stackChildrenList` (~6384), `stackChildrenListVertical` (~6600),
`layoutFlexAbsoluteChildren` (~2966). Leave the shared `layoutBox` atomic
fallback (lines 384-387) UNTOUCHED — sizing it there regressed ~18 flex
items previously (see [[feedback-aspect-ratio-lever]]).

## Part 4 — inline-level abspos extraction (THE MISSING PIECE)
**Root cause the first attempt missed:** the target SVGs are inline-level
(`display:inline-block`, foreign roots aren't blockified), so a box whose
children are all inline-level routes to `layoutInlineChildren` →
`InlineLayout`, which sizes the abspos atomic as a line atom (width 0) and
never pulls it out for §10.3.8. So the block-path helper (Part 3) is never
reached for these. Need: in the inline-formatting-context path, detect
inline-level out-of-flow atomics, REMOVE them from the line flow, and run
the §10.3.8 helper + `resolveAbsoluteOffsets` against the positioned
ancestor (capturing the inline static position first — cf. the existing
`inlineStaticPositionY` + `applyRelativeOffsetsToInlineAtomics` work). This
is the substantial part.

## Verify
After all four: `absolute-replaced-width-002/006/009`, the `inline-` and
`float-replaced-width/height` families. Watch for flex/grid regressions
(Part 3 must not touch the shared fallback). Pair the WPT delta with a
cross-browser-oracle spot check.

Related: [[feedback-aspect-ratio-lever]] (the reverted flex layoutAtomicReplaced),
[[feedback-css-coverage-loop]], `docs/plans/css-coverage-targets.md`.
