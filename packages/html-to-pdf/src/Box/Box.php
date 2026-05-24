<?php

declare(strict_types=1);

namespace Phpdftk\HtmlToPdf\Box;

use Phpdftk\Css\Cascade\CascadedValues;
use Phpdftk\Html\Dom\Element;
use Phpdftk\HtmlToPdf\Layout\BoxGeometry;
use Phpdftk\HtmlToPdf\Layout\LineBox;

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
