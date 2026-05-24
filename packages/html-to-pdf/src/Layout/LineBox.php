<?php

declare(strict_types=1);

namespace Phpdftk\HtmlToPdf\Layout;

/**
 * One laid-out line inside an inline formatting context.
 *
 * `y` is the line's top edge in the parent block's coordinate space;
 * `height` is the line's allocated vertical space (typically 1.2 × the
 * dominant font-size for Latin until proper baseline alignment ships).
 * `fragments` are placed left-to-right in logical order; bidi visual
 * reorder happens before this stage.
 */
final class LineBox
{
    /** @param list<InlineFragment> $fragments */
    public function __construct(
        public float $y,
        public float $height,
        public array $fragments,
    ) {}

    public function totalWidth(): float
    {
        $right = 0.0;
        foreach ($this->fragments as $f) {
            $right = max($right, $f->x + $f->width);
        }
        return $right;
    }
}
