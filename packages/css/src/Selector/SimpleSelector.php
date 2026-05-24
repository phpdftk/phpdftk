<?php

declare(strict_types=1);

namespace Phpdftk\Css\Selector;

/**
 * Base for the single-component selectors per Selectors 4 §3.5: type,
 * universal, id, class, attribute, pseudo-class, pseudo-element. Compound
 * selectors are sequences of these with no intervening whitespace.
 */
abstract readonly class SimpleSelector
{
    abstract public function specificity(): Specificity;

    abstract public function toString(): string;
}
