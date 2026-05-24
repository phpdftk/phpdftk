<?php

declare(strict_types=1);

namespace Phpdftk\Css\Selector;

/**
 * Type selector per Selectors 4 §6: `tagname`, `*|tagname`, `ns|tagname`,
 * `|tagname`. The universal selector `*` is represented by
 * {@see UniversalSelector}.
 *
 * `namespacePrefix` semantics:
 *  - `null` — no `|`; matches in default namespace (or any namespace if no
 *    default-ns rule has been declared; the matcher resolves this).
 *  - `''` — explicit empty prefix `|tag`, matches only the null namespace.
 *  - `'*'` — `*|tag`, matches in any namespace.
 *  - any other string — matches the namespace registered for that prefix.
 */
final readonly class TypeSelector extends SimpleSelector
{
    public function __construct(
        public string $localName,
        public ?string $namespacePrefix = null,
    ) {}

    public function specificity(): Specificity
    {
        return new Specificity(0, 0, 1);
    }

    public function toString(): string
    {
        if ($this->namespacePrefix === null) {
            return $this->localName;
        }
        return $this->namespacePrefix . '|' . $this->localName;
    }
}
