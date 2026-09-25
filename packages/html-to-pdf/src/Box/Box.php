<?php

declare(strict_types=1);

namespace Phpdftk\HtmlToPdf\Box;

use Phpdftk\Css\Cascade\CascadedValues;
use Phpdftk\Html\Dom\Element;
use Phpdftk\HtmlToPdf\Layout\BoxGeometry;
use Phpdftk\HtmlToPdf\Layout\LineBox;
use Phpdftk\HtmlToPdf\Layout\MultiColumnLayout;

/**
 * Base of the box tree produced by {@see BoxGenerator}.
 *
 * Each non-anonymous box carries its originating DOM element + the
 * cascade's resolved property bag for that element. Anonymous boxes
 * (wrappers synthesised by CSS Display 3 box-generation rules) have a
 * null `$element` but inherit a style derived from their parent.
 *
 * `$children` is in document / logical order; layout (Phase 1F) walks the
 * tree to compute positions and dimensions without re-ordering.
 *
 * Phase 1E.1 ships the structural box tree only — every box's geometry
 * (`x`, `y`, `width`, `height`) is layout's responsibility, not box
 * generation's. Keep that boundary sharp; this class doesn't track
 * positions or sizes.
 */
abstract class Box
{
    /** @var list<Box> */
    public array $children = [];

    /** Resolved geometry — populated by layout, blank until {@see \Phpdftk\HtmlToPdf\Layout\BlockLayout} runs. */
    public BoxGeometry $geometry;

    /**
     * Line boxes produced by {@see \Phpdftk\HtmlToPdf\Layout\InlineLayout}
     * when this box's children form an inline formatting context. Empty
     * for block-context parents and for boxes without inline content.
     *
     * @var list<LineBox>
     */
    public array $lineBoxes = [];

    /**
     * Set by {@see \Phpdftk\HtmlToPdf\Layout\BlockLayout} when this box's
     * cascade declares a multi-column container (CSS Multi-column 1).
     * Null on every other box. The painter reads this to stroke
     * `column-rule` between adjacent columns.
     */
    public ?MultiColumnLayout $multiColumn = null;

    /**
     * Set by {@see \Phpdftk\HtmlToPdf\Layout\BlockLayout} on every box
     * that falls entirely after a `line-clamp` container's clamp point.
     * CSS Overflow 4 §6 discards that content: the boxes still exist in
     * the tree (their geometry is what located the clamp point in the
     * first place) but nothing about them is painted — not their
     * background, not their borders, not their out-of-flow descendants.
     */
    public bool $hiddenByLineClamp = false;

    /**
     * True when this box is an out-of-flow (abs-pos / fixed) box whose
     * computed `display` was inline-level BEFORE the CSS Display §2.7
     * blockification that rewrites out-of-flow displays to `block` (set by
     * {@see \Phpdftk\HtmlToPdf\Box\BoxGenerator}). It preserves the
     * discriminator the cascade otherwise loses, so the static-position
     * recovery in {@see \Phpdftk\HtmlToPdf\Layout\BlockLayout} can apply the
     * CSS 2.1 §10.6.4 inline-continuation static position (end/top of the
     * previous sibling's last line box) ONLY to boxes that would have laid
     * out inline in normal flow. A box whose static display was block-level
     * (this flag false) uses the ordinary block-flow static position
     * (container inline-start / next block position) instead.
     */
    public bool $wasInlineLevel = false;

    /**
     * The HTML "ordinal value" this box's `::marker` counts with, when the
     * box is a `display: list-item` one. Null on every other box.
     *
     * Assigned by {@see BoxGenerator} during the generation walk rather
     * than derived from the DOM on demand, because the answer depends on
     * facts only box generation knows: HTML's "list owner" is the nearest
     * ancestor `ol` / `ul` / `menu` THAT GENERATES BOXES, and an item that
     * generates no boxes consumes no ordinal. A DOM-only walk can see
     * neither, and cannot see that a `display: list-item` `<span>` is an
     * item at all.
     *
     * Both marker paths read it, which is what keeps an `inside` marker
     * (materialised as inline content by BoxGenerator) and an `outside`
     * one (painted beside the box by
     * {@see \Phpdftk\HtmlToPdf\Painter\Painter}) numbering the same list
     * identically.
     */
    public ?int $listItemOrdinal = null;

    /**
     * True when this box must not draw a `::marker`, even though its
     * `display` is `list-item`.
     *
     * HTML §15.3.9 says a `<fieldset>` "is expected to not generate a
     * `::marker` pseudo-element" — its contents render through an
     * anonymous content box that owns no marker position. The suppression
     * is unconditional, so it cannot be spelled as a `list-style-type:
     * none` UA rule an author could override with `list-style-type:
     * decimal`.
     *
     * The counter still advances: `display: list-item` implies
     * `counter-increment: list-item 1` whether or not anything is drawn,
     * so a marker-less fieldset still consumes an ordinal from the list
     * around it.
     */
    public bool $suppressesListMarker = false;

    /**
     * Set by {@see \Phpdftk\HtmlToPdf\Layout\BlockLayout} on an
     * out-of-flow box that CSS Anchor Positioning 1 §10
     * (`position-visibility`) makes *strongly hidden*. A strongly hidden
     * box is skipped by the painter along with its whole subtree —
     * including descendants that set `visibility: visible` and
     * out-of-flow descendants that escape it — which is what separates
     * it from ordinary `visibility: hidden`.
     */
    public bool $hiddenByPositionVisibility = false;

    /**
     * `'before'` / `'after'` when this box was generated for a
     * pseudo-element of {@see $element}, null when it is the element's
     * own box. A pseudo-element shares its originating element's
     * `Element` instance, so this is the only thing that tells the two
     * apart — which CSS Anchor Positioning 1 §3.2 needs, because a
     * pseudo-element's IMPLICIT anchor is its originating element.
     */
    public ?string $pseudoElement = null;

    public function __construct(
        public readonly ?Element $element,
        public readonly CascadedValues $style,
    ) {
        $this->geometry = new BoxGeometry();
    }

    public function addChild(self $child): void
    {
        $this->children[] = $child;
    }
}
