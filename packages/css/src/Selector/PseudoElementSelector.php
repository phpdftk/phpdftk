<?php

declare(strict_types=1);

namespace Phpdftk\Css\Selector;

/**
 * Pseudo-element selector per Selectors 4 §3.5 / §12: `::before`, `::after`,
 * `::marker`, `::first-line`, `::first-letter`, `::placeholder`,
 * `::slotted(...)`, `::part(...)`, `::theme(...)`, etc.
 *
 * Specificity (0, 0, 1) — same as a type selector. Functional pseudo-elements
 * like `::slotted()` and `::part()` carry their argument selectors but their
 * inner arguments add to specificity as if they were `:is()` (max of inner).
 */
final readonly class PseudoElementSelector extends SimpleSelector
{
    public function __construct(
        public string $name,
        public ?SelectorList $arguments = null,
    ) {}

    public function specificity(): Specificity
    {
        $base = new Specificity(0, 0, 1);
        if ($this->arguments === null || $this->arguments->selectors === []) {
            return $base;
        }
        $max = $this->arguments->selectors[0]->specificity();
        foreach (array_slice($this->arguments->selectors, 1) as $sel) {
            $max = $max->max($sel->specificity());
        }
        return $base->add($max);
    }

    public function toString(): string
    {
        if ($this->arguments !== null) {
            $parts = [];
            foreach ($this->arguments->selectors as $sel) {
                $parts[] = $sel->toString();
            }
            return '::' . $this->name . '(' . implode(', ', $parts) . ')';
        }
        return '::' . $this->name;
    }
}
