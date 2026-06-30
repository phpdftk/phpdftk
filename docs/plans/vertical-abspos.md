# Vertical-writing-mode absolutely-positioned non-replaced elements

Tracks the `css/css-writing-modes/abs-pos-non-replaced-v{lr,rl}-NNN`
family (~224 fixtures, gtalbot CSS2.1 abspos suite re-expressed in
vertical writing modes). As of 2026-06-29 these exercise the CSS 2.1
§10.3.7 / §10.6.4 absolute-positioning algorithms with a vertical
containing block and/or a vertical abspos box.

## Done

1. **`color: transparent` text** painted opaque black → invisible mode 3
   (`Painter::paintFragment`). Fixed the reference side's filler text.
2. **`position: relative` on inline atomics** (`BlockLayout`). Fixed the
   reference side's relatively-positioned green-swatch `<img>`.
3. **Explicit line-height** (no 1.2 floor) + **inline-abspos static
   position on the preceding line** (`InlineLayout` / `BlockLayout`).
   Net +79 CSS WPT.
4. **Vertical glyph placement** — baseline a `descent` in from the
   column's line-under edge, centred in the cross-size
   (`Painter::paintFragment`). `vlr-053` 0.0266 → 0.0001.
5. **Vertical-CB abspos axis mapping** — §10.3.7 on physical Y, §10.6.4
   on physical X when the CB is vertical (`resolveAbsoluteOffsetsVertical`).

(4) and (5) are correct but **WPT-neutral on their own** — the family is
gated on the item below.

## Remaining blocker — rich vertical static position

When an abspos box has both insets `auto` on an axis it keeps its STATIC
position. In a vertical CB:

- **block-axis static (physical X)**: e.g. `vlr-063` has `left/right:
  auto`; the box should land on the column it sits on in the block flow
  (ref x=80 = 2nd column), but the vertical stacker puts it at `cursorX`
  (x=320, past all columns).
- **inline-axis static (physical Y)**: e.g. `vlr-205` has `top/bottom:
  auto`; the box should land at its inline offset within its column
  (ref y=212), but the stacker uses `originY` (CB content top, y=65).

This is the vertical analogue of the horizontal inline-abspos static
position fix (done — last line top), but along BOTH vertical axes.

### Root blocker (investigated 2026-06-29): no vertical column geometry

The static position cannot be *read* from anywhere, because the vertical
inline layout does not break content into positioned columns:

- A vertical-mode CB with inline filler + an abspos span has the
  structure `AnonymousBlockBox(vertical) → [InlineBox(filler),
  BlockBox(abspos)]` and is laid out by `stackChildrenListVertical`
  (the abspos blockifies, so `allInlineLevel` is false).
- The `InlineBox` filler reports a block extent of the FULL CB width
  (e.g. 320), not the sum of the columns it actually occupies, and
  exposes NO per-column `lineBoxes` to locate the span's column.
- So the abspos static block-X = `cursorX` AFTER the filler (=320, past
  the CB) instead of the column it sits on (ref x=80 = column 2); the
  static inline-Y = `originY` (CB top) instead of the inline offset
  within that column.

There is no analytic shortcut: deriving "the span is on column 2 at
inline offset N" requires shaping the filler and breaking it into
columns by the inline (block-axis) extent — i.e. doing the vertical
inline layout. The current `InlineLayout` vertical path is a paint-time
rotation scaffold (`applyVerticalLineShift`), not a real column layout.

### Real prerequisite: vertical inline column layout

Before the static position can land, `InlineLayout` needs to lay inline
content out into real columns for vertical writing modes:
- shape runs, break into columns by the container's inline (block-axis)
  extent, position each column along the block axis (left→right for
  vlr, right→left for vrl), and expose their geometry as `lineBoxes`
  (or an equivalent) with usable block + inline coordinates;
- size the `InlineBox` block extent to the columns actually used, not
  the full CB.

Then mirror `inlineStaticPositionY` for vertical: record the abspos
box's static position from the preceding content's last column (block
axis) + its inline offset within that column (inline axis). This is the
core of CSS Writing Modes Phase 4 vertical inline layout — a large
foundational effort, not an incremental fix.

### Other known gaps

- `PositionedAncestor` does not carry a `WritingMode`; a non-immediate
  positioned ancestor whose writing mode differs from the immediate
  parent is solved against the wrong axes. Add the field + thread it.
- `applyAbsoluteCornerAnchorSize` percentage-padding basis should be
  audited under a vertical CB (the both-sides size formula itself is
  symmetric and fine).

Land (static position) together with the already-committed (4)+(5) as a
single net-positive change verified across the whole family.
