<?php

declare(strict_types=1);

namespace Phpdftk\Css\Selector;

/**
 * Universal selector per Selectors 4 §6.2: `*`, `*|*`, `ns|*`, `|*`.
 * Doesn't contribute to specificity.
 */
final readonly class UniversalSelector extends SimpleSelector
{
    public function __construct(public ?string $namespacePrefix = null) {}

    public function specificity(): Specificity
    {
        return new Specificity();
    }

    public function toString(): string
    {
        if ($this->namespacePrefix === null) {
            return '*';
        }
        return $this->namespacePrefix . '|*';
    }
}
