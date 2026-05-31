<?php

declare(strict_types=1);

namespace Phpdftk\Css\Value;

/**
 * CSS Generated Content for Paged Media 3 §3 — cross-reference
 * functions for print:
 *
 *   - `target-counter(<url>, <counter>, <style>?)` — counter
 *     value at the linked target element (most commonly used for
 *     page numbers in tables of contents).
 *   - `target-counters(<url>, <counter>, <string>, <style>?)` —
 *     joined chain of nested counters at the target.
 *   - `target-text(<url>, [content | before | after |
 *     first-letter]?)` — text content at the target.
 *
 * `target` is the URL or attr() / var() reference; `name` is the
 * counter / scope name (omitted for target-text); `extra` carries
 * the optional separator (target-counters) or text-source keyword
 * (target-text); `style` is the optional counter-style name
 * (target-counter / target-counters only).
 *
 * The renderer resolves these at painting time once the target
 * element's box geometry + counter state are known.
 */
final readonly class TargetFunction extends Value
{
    public function __construct(
        public TargetFunctionKind $kind,
        public Value $target,
        public ?Value $name = null,
        public ?Value $extra = null,
        public ?Value $style = null,
    ) {}

    public function toCss(): string
    {
        $args = [$this->target->toCss()];
        if ($this->name !== null) {
            $args[] = $this->name->toCss();
        }
        if ($this->extra !== null) {
            $args[] = $this->extra->toCss();
        }
        if ($this->style !== null) {
            $args[] = $this->style->toCss();
        }
        return $this->kind->value . '(' . implode(', ', $args) . ')';
    }
}
