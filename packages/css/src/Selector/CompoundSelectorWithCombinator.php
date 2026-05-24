<?php

declare(strict_types=1);

namespace Phpdftk\Css\Selector;

/**
 * A compound paired with the combinator that joins it to the next compound
 * to its right. The right-most compound of a complex selector has a `null`
 * combinator since nothing follows it.
 *
 * Per the cross-package contract documented in `docs/plans/contracts.md`,
 * `ComplexSelector::$compounds` is the canonical exposed shape. This wrapper
 * is the element of that list.
 */
final readonly class CompoundSelectorWithCombinator
{
    public function __construct(
        public CompoundSelector $compound,
        public ?Combinator $combinatorToNext,
    ) {}
}
