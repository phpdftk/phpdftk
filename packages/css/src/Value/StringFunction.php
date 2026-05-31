<?php

declare(strict_types=1);

namespace Phpdftk\Css\Value;

/**
 * CSS Generated Content for Paged Media 3 §5.2 — `string(<name>
 * [, <fetch-target>]?)`. Returns the current value of a named
 * string defined via `string-set`. The fetch-target keyword
 * selects which page's value to use:
 *
 *   - `first`  — first assignment on the page (default).
 *   - `start`  — value at page start (i.e. last assignment from
 *                an earlier page, or null when nothing yet).
 *   - `last`   — last assignment on the page.
 *   - `first-except` — same as `first` but null if the entry
 *                element starts on this page.
 *
 * The renderer resolves the value at paint time once the
 * page-by-page string store is built up from cascade
 * `string-set` assignments.
 */
final readonly class StringFunction extends Value
{
    public function __construct(
        public string $name,
        public string $target = 'first',
    ) {}

    public function toCss(): string
    {
        return $this->target === 'first'
            ? sprintf('string(%s)', $this->name)
            : sprintf('string(%s, %s)', $this->name, $this->target);
    }
}
