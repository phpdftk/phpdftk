# Table shrink-to-fit auto width — design

Status: draft (design-doc-first, pre-implementation). 2026-06-30.
Branch: `css-coverage-push`.

## Problem

An auto-width table-root (`display: table` / `inline-table`, no explicit
`width`) does not shrink-wrap to its content. Two distinct broken cases:

- **block-level `display: table`** fills its containing block. Minimal
  repro (`table{border;padding} td{padding}` 2 cells "A"/"B") →
  table `w=584` (full body width), cells `w=272` each; expected
  ~240px table / ~30px cells.
- **`inline-table`** *collapses*: same repro with `display:inline-table`
  → table `w=6` (≈border only), cells `w=0`.

Why they differ: `BlockLayout::blockNeedsShrinkToFit` already returns
`true` for `inline-table` (and floats / abspos / inline-block), so an
inline-table takes the shrink-to-fit branch in `layoutBlock`
(`min(max-content, max(min-content, available))`). But
`measureContentMinMax` has **no `TableBox` case** — it falls through to
`aggregateChildrenMinMax(inline:false)`, which takes the *widest stacked
child* (treating rows as blocks) and yields ~0. So inline-table shrinks to
~0. Block-level `display:table` is **not** in `blockNeedsShrinkToFit` at
all, so it stretch-fills.

Per CSS 2.1 §17.5.2.2 an auto-width table is a shrink-to-fit box: used
width = `max(min-table-width, min(available, preferred-table-width))`,
where preferred ≈ Σ column max-content and min ≈ Σ column min-content.

## Confirmed facts (code reading + probes)

- `layoutBox` `TableBox` branch (~L307): sets `currentTableCellGrid` =
  `precomputeTableCellGrid($box)` (L323) and `currentColumnWidths` =
  `collectColumnWidths(...)` (L333) **before** `layoutBlock($box)` (L335).
  `precomputeTableCellGrid` populates `resolvedCellReferences` eagerly.
  So at the moment `layoutBlock`→`blockNeedsShrinkToFit`→
  `measureContentMinMax` runs for the table, the grid + cell refs + column
  widths are all available.
- `resolveAutoColumnContentWidths(totalColumns, tableContentWidth, ctx)`
  (L7212) already measures per-column max-content (`measureMinMaxContent`
  per cell, colspan distributed) and *scales auto columns to fill*
  `tableContentWidth`. If we shrink `tableContentWidth` to ≈Σ colMax, the
  scale factor → 1 and columns land at max-content. The existing
  distribution composes with a narrower table width — no rewrite needed.
- `resolveAutoColumnContentWidths` has a deliberate guard: when every auto
  column measures **0** content, it leaves the nulls so the equal-share
  fill in `resolveColumnWidthGrid` takes over (empty grid scaffolds fill).
  Shrink-to-fit must preserve this — an empty table must NOT collapse to 0.
- `border-spacing` is **not referenced anywhere in BlockLayout** — cells
  are positioned by a running prefix-sum of column widths with no spacing
  gaps. So border-spacing is out of scope here (deferred); the intrinsic
  measurement excludes it to stay consistent with how cells are laid out.

## Approach (Option B — reuse the existing shrink-to-fit machinery)

1. **`measureTableMinMax(TableBox $table, LayoutContext): {min,max}`** —
   content-box intrinsic widths:
   - Build the cell grid for `$table` *without clobbering* the active
     table's shared state: save `resolvedCellReferences`, call
     `precomputeTableCellGrid($table)` (or factor a pure grid builder),
     restore afterward. (When `$table` IS the current table, the grid is
     already correct, but measuring a nested/child table via a parent's
     shrink-to-fit must not corrupt the parent's cell refs.)
   - Per column, accumulate `colMin[c]` / `colMax[c]` from each anchored
     cell's `measureMinMaxContent` (`mm.min` / `mm.max`), distributing a
     colspan cell's share as `mm/colspan` across its columns (mirrors
     `resolveAutoColumnContentWidths`).
   - Honour explicit column widths from `collectColumnWidths`: a column
     with an explicit px width contributes that fixed width to both min
     and max.
   - `min = Σ colMin`, `max = Σ colMax`. No border-spacing (deferred).
   - Returns `{0,0}` when there is no measurable content.

2. **`measureContentMinMax`**: add a `TableBox` case routing to
   `measureTableMinMax` (before the generic block aggregation).

3. **`blockNeedsShrinkToFit`**: add the auto-width table-root case —
   `true` when `$box instanceof TableBox`, `width` is auto, AND
   `measureTableMinMax(...).max > 0` (the guard preserves empty-table
   fill). To avoid measuring twice (predicate + the layoutBlock shrink
   branch), cache the per-table intrinsic by `spl_object_id` for the
   duration of the table's layout (cleared in the branch `finally`), or
   stash `currentTableIntrinsic` in the `TableBox` branch and read it.

With (1)-(3): `layoutBlock` computes the table content width as
`min(max, max(min, available))`; `resolveAutoColumnContentWidths` then
scales columns to that width (≈1× → max-content when unconstrained, or
proportionally smaller when `available < preferred`). Block tables stop
filling; inline-tables stop collapsing.

## Open questions for review

1. **Empty-table guard**: gate via `max > 0` in `blockNeedsShrinkToFit`
   (double measure or cache) vs a sentinel return. Preference: cache the
   intrinsic by object id (cheap, no double recurse).
2. **border-spacing deferral**: OK to exclude from the intrinsic (cells
   aren't spaced in layout yet), accepting that `border-spacing>0` tables
   render slightly narrow until spacing layout lands? floats-014/015 ref
   uses `border-spacing:0`, so unaffected.
3. **Percentage columns / percentage table width**: keep current behaviour
   (explicit/percentage width tables are NOT shrink-to-fit; only `auto`).
   Percentage *column* widths inside an auto table — treat as 0 intrinsic
   contribution for v1 (resolve against a 0 basis → 0), matching today?
4. **`margin: auto` centring**: a shrink-to-fit table with `margin:auto`
   should centre. `layoutBlock` already redistributes margin slack after
   width (L824+), so this should fall out for free — verify.
5. **Caption width**: a `<caption>` can be wider than the columns and
   widen the table. Out of scope for v1? (Note as follow-up.)

## Review resolutions (codex, 2026-06-30)

Verdict: **proceed with Option B**, with these corrections:

- **Scope = block-level `display: table` only.** `inline-table` is built as
  an `AtomicInlineBox` (BoxGenerator `makeBox`), NOT a `TableBox`, so it
  never reaches the `TableBox` branch — its collapse is the atomic-inline
  path's separate gap (follow-up). Every `TableBox` reaching `layoutBlock`
  is block-level, so auto-margin centring applies cleanly.
- **Same-unit measurement (the riskiest point).** Column widths must be the
  cell's *border-box* contribution. `measureMinMaxContent` deliberately
  excludes the box's own padding/border, and `resolveAutoColumnContentWidths`
  uses it raw (L7239) — so columns currently exclude cell padding (masked
  because fill-tables scale up). Add a shared
  `cellColumnContribution(cell) = measureMinMaxContent(cell) + cell
  horizontal border+padding` and use it in BOTH `measureTableMinMax` and
  `resolveAutoColumnContentWidths`. This is the change that makes the
  narrowed table width *compose* with column distribution.
- **Empty guard via explicit flag**, not `max>0`. `measureTableMinMax`
  returns `{min,max,hasContent}`; `hasContent` = any cell contributed
  content OR any explicit column width. Shrink only when `hasContent`.
- **Margin centring.** After the shrink branch computes a table content
  width, set `widthAuto = false` (local) so the §10.3.3 auto-margin block
  (L823) centres `margin:auto` tables. Scoped to block `TableBox`.
- **Pure grid builder.** Factor `precomputeTableCellGrid` into a pure
  `buildCellGrid(table): [grid, cellRefs]` (no shared-state mutation);
  `measureTableMinMax` builds its own grid so it's correct even when the
  table is measured as a *descendant* of another box's intrinsic pass
  (nested-table / float-containing-table). Do not touch `currentTableCellGrid`,
  `currentColumnWidths`, `currentAutoWidthsResolved`, `currentTableRowHeights`
  during measurement.
- **Explicit column widths** are min constraints (not fixed min=max); for
  v1, treat an explicit px column as contributing its width to both min and
  max (close enough; full constraint solving deferred). **Percentage
  columns** in an auto table → treat as auto/0-intrinsic for v1 (note gap).
- **border-spacing** and **caption CAPMIN** excluded from the intrinsic for
  v1 (border-spacing isn't laid out; captions are a separate widener) —
  documented spec gaps.

## Verification plan

- Unit: `BlockLayoutTest` — auto block-table shrink-wraps to content;
  inline-table no longer collapses; explicit-width table unchanged;
  `available < preferred` clamps proportionally; empty table still fills.
- WPT gate (settler-off, before/after): `tables` bucket (225 pass / 372
  in-scope today) — must be net-positive with no large regression;
  `floats-clear` (floats-014/015 should flip green via their table refs).
  Also spot-check `backgrounds/*-applies-to` (table-internal) for movement.
- `composer analyse` + html-to-pdf suite green.
