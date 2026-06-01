<?php

declare(strict_types=1);

namespace Phpdftk\Css\Value;

/**
 * CSS Scroll-driven Animations 1 §4.2 — `view(<axis>? <inset>?)`.
 * Anonymous view-progress timeline tied to the element's
 * intersection with the scroll root.
 *
 *   animation-timeline: view();
 *   animation-timeline: view(block);
 *   animation-timeline: view(inline 20%);
 *   animation-timeline: view(20% 50%);
 *
 * For static print the timeline never advances; the typed value
 * lets the cascade carry the declaration so tooling can read it
 * back.
 */
final readonly class ViewTimeline extends Value
{
    public function __construct(
        public ?string $axis = null,
        public ?Value $insetStart = null,
        public ?Value $insetEnd = null,
    ) {}

    public function toCss(): string
    {
        $parts = [];
        if ($this->axis !== null) {
            $parts[] = $this->axis;
        }
        if ($this->insetStart !== null) {
            $parts[] = $this->insetStart->toCss();
        }
        if ($this->insetEnd !== null) {
            $parts[] = $this->insetEnd->toCss();
        }
        return $parts === []
            ? 'view()'
            : 'view(' . implode(' ', $parts) . ')';
    }
}
