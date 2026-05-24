<?php

declare(strict_types=1);

namespace Phpdftk\Css\Selector;

/**
 * A comma-separated list of selectors per CSS Selectors 4 §3.4.
 *
 * `selectors` is the parsed list of `ComplexSelector`s. `text` keeps the
 * original prelude source for diagnostics / serialization. Parser errors
 * inside one selector cause that selector to be dropped per Selectors 4
 * §3.7 forgiving / non-forgiving rules — the consumer (parser or pseudo-
 * class like `:is()` / `:where()`) decides which mode to apply.
 */
final readonly class SelectorList
{
    /** @param list<ComplexSelector> $selectors */
    public function __construct(
        public string $text,
        public array $selectors = [],
    ) {}

    public function isEmpty(): bool
    {
        return $this->selectors === [];
    }

    public function toString(): string
    {
        $parts = [];
        foreach ($this->selectors as $sel) {
            $parts[] = $sel->toString();
        }
        return implode(', ', $parts);
    }
}
