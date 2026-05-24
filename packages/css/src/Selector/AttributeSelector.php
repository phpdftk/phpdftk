<?php

declare(strict_types=1);

namespace Phpdftk\Css\Selector;

/**
 * Attribute selector per Selectors 4 §6.5: `[name op value flag]`.
 *
 * Specificity (0, 1, 0). The optional ASCII-case-insensitive flag `i` (or
 * case-sensitive `s`) modifies the value comparison.
 */
final readonly class AttributeSelector extends SimpleSelector
{
    public function __construct(
        public string $name,
        public AttributeMatchType $matchType = AttributeMatchType::Exists,
        public ?string $value = null,
        public ?string $namespacePrefix = null,
        public bool $caseInsensitive = false,
    ) {}

    public function specificity(): Specificity
    {
        return new Specificity(0, 1, 0);
    }

    public function toString(): string
    {
        $name = $this->namespacePrefix !== null
            ? $this->namespacePrefix . '|' . $this->name
            : $this->name;
        if ($this->matchType === AttributeMatchType::Exists) {
            return '[' . $name . ']';
        }
        $value = $this->value ?? '';
        $needsQuoting = preg_match('/[^A-Za-z0-9_-]/', $value) === 1 || $value === '';
        $quoted = $needsQuoting ? '"' . str_replace('"', '\\"', $value) . '"' : $value;
        $flag = $this->caseInsensitive ? ' i' : '';
        return '[' . $name . $this->matchType->value . $quoted . $flag . ']';
    }
}
