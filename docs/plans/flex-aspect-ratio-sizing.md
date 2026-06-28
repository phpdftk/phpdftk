# Flex aspect-ratio cross/main sizing

Scope for implementing CSS Flexbox §9.4/§9.7 + Sizing 4 §4.2 aspect-ratio
sizing for flex items. This is the prerequisite that unblocks
`flex-minimum-width-flex-items-009`, the block-level replaced-sizing
feature (see [[feedback-aspect-ratio-lever]]), and a large flex-aspect-ratio
fixture cluster.

## Value

- **~70 failing fixtures** in scope: `css/css-sizing/aspect-ratio/flex-aspect-ratio-*`
  (33 failing) + `css/css-flexbox/*aspect-ratio*` (37 failing).
- Unblocks the block-level replaced-sizing feature (+18 css-flexbox base)
  by giving flex items a correct base-size vs min/max separation.
- All in `packages/html-to-pdf/src/Layout/BlockLayout.php`
  (`layoutFlexBox` + `resolveFlexLineMainSizes`); no cross-package work.

## Current pipeline (what exists today)

`layoutFlexBox` (~2284) runs:

1. **Item layout loop** (~2458–2518): each item laid out via `layoutBox`;
   `$basis` from `resolveFlexBasis`/main-size; `geometry` main set to
   `$basis`; `$itemMains[]` = outer main, `$itemCrosses[]` = outer cross.
2. **`resolveFlexLineMainSizes`** (~4730): §4.5 automatic minimum
   (`flexItemContentMainMin` + `aspectRatioTransfer`) + §9.7 grow/shrink →
   `$finalMains` (outer main per item).
3. **Apply finalMains** (~2591–2606): adjusts `geometry` main by delta.
   **The cross dimension is NOT touched here.**
4. **Per-line cross extents** (~2613–2619): `max($itemCrosses)` — computed
   from the pre-grow cross, never updated for aspect-ratio.
5. **Cross sizing / align** (~2749–2778): `align-items: stretch` sets the
   cross to the line cross when the cross property is `auto`. No
   aspect-ratio path.

## The three gaps (the actual work)

### 1. Base size vs min/max separation
A flex item's **flex base size** must be its natural/explicit size
*before* min/max; the min/max + automatic-minimum apply inside the flex
algorithm (steps 2). The reverted block-level-sizing attempt baked the
min/max-clamped geometry into the basis (so a `flex:1 0 auto` img with
`min-width:100` grew 100→149.5 instead of staying proportional). Fix: for
an `AtomicInlineBox` flex item, take the base size from its **natural**
size (the `width`/`height` BoxGenerator records, pre-clamp), not the
clamped geometry. `layoutAtomicReplaced` (the new block-level branch) keeps
applying clamps for *non-flex* contexts.

### 2. Cross-min → main transferred suggestion (§4.5) — fixes 009
`aspectRatioTransfer` (~5340) returns null when the cross size *property*
is `auto`, so `min-height:100` + `height:auto` on a ratio img yields no
main floor → 009's img sizes 60 wide instead of 100. Extend the transferred
suggestion to use the cross size **clamped by min/max cross** even when the
base cross is auto: the effective cross input = `max(autoCross, minCross)`
capped by `maxCross`, then × ratio. (`resolveCrossSizeClamp` already exists.)

### 3. Cross size from resolved main (§9.4) — the grow/shrink cluster
After step 3 (finalMains applied), a flex item **with an aspect ratio and
an auto cross size** must recompute its cross dimension from the resolved
main via the ratio (CSS Flexbox §9.4 "Cross Size Determination" + Sizing 4).
This must run *before* per-line cross extents (step 4) so the line cross
reflects the ratio-derived cross. Interaction with `align-items: stretch`:
stretch only applies when the cross is auto AND there is no aspect ratio
(a ratio item's cross is determined, not stretched).

## Spec references

- CSS Flexbox 1 §9.2 (flex base size), §9.4 (cross size determination,
  step 7 — aspect-ratio cross), §9.7 (resolve flexible lengths), §4.5
  (automatic minimum: content/specified/transferred suggestions).
- CSS Sizing 4 §4.2 (aspect-ratio), §5.1 (automatic minimum).
- CSS Box Alignment 3 (the `start`/`flex-start` distinction — see "rider").

## Open questions / hard cases (study before coding)

- **`flex-aspect-ratio-img-row-007`**: `flex:1 0 auto` ratio img in a 200px
  row, expected 100×100 (NOT grown to 149.5 — the fixture even notes Chrome
  86 got 149.5). Whether a correct UA grows it depends on whether the
  ratio-derived cross hits a constraint that freezes the main in §9.7. Needs
  a careful read of §9.7 freezing + §9.4 with aspect ratio. May be deferred
  if it requires a cross-then-main feedback loop we don't model.
- **Definite vs indefinite cross**: the transferred suggestion is only
  defined for a *definite* cross. Treating `min-height` as making the cross
  "definite enough" (gap 2) is pragmatic, not strictly spec — verify it
  doesn't over-apply.

## Rider (lands with this, inert until then)

The `justify-content: start`/`end` vs `flex-start`/`flex-end` flip under
`*-reverse` is wrong (both flip today; only the flex-relative pair should).
One-line fix in the `if ($reverseDirection)` remap (~2566). **WPT-inert
standalone** — only matters once items size correctly — so commit it with
this feature, not separately. Fixes `flexbox_justifycontent-{start,end}{,-rtl}`.

## Verification

- Per [[feedback-layout-wpt-verify]]: WPT-verify each commit one-by-one via
  fail-list `comm` diffs (not pass-count deltas — ±1 raster jitter).
- Buckets to diff every commit: **css-flexbox, css-sizing, css-grid**
  (grid containers act as flex items), and **css-images** (replaced-heavy).
- Unit tests: extend `BlockLayoutTest` flex section (82 tests today) with
  negative-first cases — ratio item grows cross with main; `min-cross`
  drives main via ratio; `align-items:stretch` does NOT override a ratio
  cross.
- Gate: `composer wpt:gate` after, and `composer analyse` (18 pre-existing).

## Phasing — REVISED after a failed Phase-1 attempt (2026-06-28)

**The gaps are NOT independently shippable.** Gap 2 was implemented alone
(extend `aspectRatioTransfer` to use the min/max-clamped cross when the
cross property is auto) and measured **completely WPT-inert** — 0 fixed /
0 regressed across css-sizing, css-flexbox, css-grid. Reverted. Reason: the
fixtures that would exercise it either size their cross explicitly already
(replaced items get a cross from BoxGenerator) or don't *render* correctly
until Gaps 1+3 + block-level sizing are also present. The justify-content
rider is likewise inert standalone (confirmed earlier). So incremental,
each-piece-WPT-verified commits do NOT work here — there is no
positive-delta subset smaller than "the whole feature".

The only piece that moves WPT *positively on its own* is the block-level
`layoutAtomicReplaced` sizing (+18 css-flexbox) — but it ships with 18
regressions (flex grow/cross + grid alignment) that only Gaps 1+3 + grid
alignment resolve. So the realistic plan is a **single feature branch**
landing all of: block-level atomic sizing + Gap 1 (base-size vs min/max
separation) + Gap 3 (cross-from-resolved-main) + Gap 2 (cross-min
transferred) + the justify-content rider, verified as ONE net-positive
change against css-flexbox/sizing/grid/images. Grid replaced-item alignment
(`replaced-alignment-with-aspect-ratio-*`, `grid-item-content-baseline-*`,
10 fixtures) is a separable follow-up — restrict the block-level sizing to
flex/block context first if grid alignment can't land in the same pass.

Recommended build order *within that one branch* (so intermediate states
are debuggable, even though none is individually shippable): Gap 1 → Gap 3
→ block-level sizing → Gap 2 → justify rider → grid alignment (or defer).

## Out of scope

- Grid replaced-item alignment (baseline / place-self consuming replaced
  sizes) — separate effort, surfaces in Phase 3.
- Non-replaced aspect-ratio block boxes (already handled by
  `aspectRatioBlockSize`, commit `a97471225`).
