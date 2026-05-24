<?php

declare(strict_types=1);

namespace Phpdftk\Css\Selector;

/**
 * A compound selector per Selectors 4 §3.5: a sequence of simple selectors
 * with no intervening whitespace. Matches one element if every component
 * matches.
 *
 * Pseudo-elements (when present) must be the last simple selector in the
 * compound and are not interleaved with other simple selectors per spec.
 */
final readonly class CompoundSelector
{
    /** @param list<SimpleSelector> $components */
    public function __construct(public array $components) {}

    public function specificity(): Specificity
    {
        $total = new Specificity();
        foreach ($this->components as $c) {
            $total = $total->add($c->specificity());
        }
        return $total;
    }

    public function toString(): string
    {
        $out = '';
        foreach ($this->components as $c) {
            $out .= $c->toString();
        }
        return $out;
    }
}
