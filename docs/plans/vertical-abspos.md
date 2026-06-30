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
position fix (done — last line top), but along BOTH vertical axes, and
it depends on the vertical inline layout (currently a "Phase-4 scaffold"
in `InlineLayout` — glyphs lay out horizontally with a paint-time
rotation; column/inline offsets are approximate). Getting exact column
positions likely requires real vertical inline layout first.

### Approach sketch

- Teach `stackChildrenListVertical` (and the inline-formatting-context
  path for a vertical CB) to record the abspos box's static position
  from the preceding inline content's last column (block axis) + inline
  offset within it (inline axis), mirroring `inlineStaticPositionY`.
- Likely needs the vertical inline layout to produce accurate per-column
  cross positions first.

### Other known gaps

- `PositionedAncestor` does not carry a `WritingMode`; a non-immediate
  positioned ancestor whose writing mode differs from the immediate
  parent is solved against the wrong axes. Add the field + thread it.
- `applyAbsoluteCornerAnchorSize` percentage-padding basis should be
  audited under a vertical CB (the both-sides size formula itself is
  symmetric and fine).

Land (static position) together with the already-committed (4)+(5) as a
single net-positive change verified across the whole family.
