<?php

declare(strict_types=1);

namespace Phpdftk\Css\Value;

/**
 * CSS Generated Content for Paged Media 3 §4.2 — `element(<name>
 * [, <fetch-target>]?)`. Emits a copy of a "running element"
 * (one positioned via `position: running(name)`) inside a page
 * margin box. Companion to the §5 `string()` function for the
 * print-PDF running-header pattern, but carries arbitrary
 * element content rather than a flat text string.
 *
 *   header { position: running(page-header); }
 *   @page { @top-center { content: element(page-header); } }
 *
 * Fetch targets match §5.2:
 *
 *   - first        — first running entry on this page (default).
 *   - start        — value at page start (last entry from
 *                    earlier pages, or empty).
 *   - last         — last entry on this page.
 *   - first-except — like first but empty if the entry begins
 *                    on this page.
 */
final readonly class ElementFunction extends Value
{
    public function __construct(
        public string $name,
        public string $target = 'first',
    ) {}

    public function toCss(): string
    {
        return $this->target === 'first'
            ? sprintf('element(%s)', $this->name)
            : sprintf('element(%s, %s)', $this->name, $this->target);
    }
}
