<?php

declare(strict_types=1);

namespace Phpdftk\Css\Value;

/**
 * CSS Scroll-driven Animations 1 §3.2 — `scroll(<scroller>?
 * <axis>?)`. Anonymous scroll-progress timeline tied to a scroll
 * container's progress along an axis.
 *
 *   animation-timeline: scroll();
 *   animation-timeline: scroll(root);
 *   animation-timeline: scroll(nearest block);
 *   animation-timeline: scroll(self inline);
 *
 * Scroller is one of `nearest | root | self` (or omitted = nearest);
 * axis is `block | inline | x | y` (or omitted = block).
 */
final readonly class ScrollTimeline extends Value
{
    public function __construct(
        public ?string $scroller = null,
        public ?string $axis = null,
    ) {}

    public function toCss(): string
    {
        $parts = [];
        if ($this->scroller !== null) {
            $parts[] = $this->scroller;
        }
        if ($this->axis !== null) {
            $parts[] = $this->axis;
        }
        return $parts === []
            ? 'scroll()'
            : 'scroll(' . implode(' ', $parts) . ')';
    }
}
