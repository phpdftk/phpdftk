<?php

declare(strict_types=1);

namespace Phpdftk\Css\Selector;

/**
 * One selector in a SelectorList — a sequence of compound selectors joined
 * by combinators (descendant / `>` / `+` / `~` / `||`).
 *
 * Holds the parsed `compounds` list per the cross-package contract. The
 * right-most compound's `combinatorToNext` is `null`. The raw source text
 * is preserved for diagnostics / serialization.
 */
final readonly class ComplexSelector
{
    /**
     * @param list<CompoundSelectorWithCombinator> $compounds
     * @param ?Combinator $leadingCombinator When non-null, this is
     *     a CSS Selectors 4 §17.5 *relative selector* — the
     *     combinator binds the selector against an implicit
     *     subject (used inside `:has(...)`). For example
     *     `:has(> .child)` parses to a relative selector with
     *     `leadingCombinator = Combinator::Child` and one compound
     *     `.child`. The Matcher's `hasMatches` dispatches on this
     *     field to decide whether to walk descendants, just
     *     children, the next sibling, or subsequent siblings.
     */
    public function __construct(
        public array $compounds,
        public string $text = '',
        public ?Combinator $leadingCombinator = null,
    ) {}

    public function specificity(): Specificity
    {
        $total = new Specificity();
        foreach ($this->compounds as $c) {
            $total = $total->add($c->compound->specificity());
        }
        return $total;
    }

    public function toString(): string
    {
        $parts = [];
        foreach ($this->compounds as $c) {
            $parts[] = $c->compound->toString();
            if ($c->combinatorToNext !== null) {
                if ($c->combinatorToNext === Combinator::Descendant) {
                    $parts[] = ' ';
                } else {
                    $parts[] = ' ' . $c->combinatorToNext->value . ' ';
                }
            }
        }
        return implode('', $parts);
    }
}
