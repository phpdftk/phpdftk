<?php

declare(strict_types=1);

namespace Phpdftk\Svg;

/**
 * An SVG element with a tag name, attributes, and a child list. Concrete
 * subclasses (`Shape\Rect`, `Group`, `SvgDocument`, …) add typed accessors
 * over the raw attribute strings stored here, so callers never have to
 * remember whether `cx` is a length or a number.
 *
 * Attribute names are case-sensitive per the SVG spec — `viewBox` and
 * `clipPathUnits` etc. keep their camelCase. The parser passes them
 * through verbatim.
 */
abstract class Element extends Node
{
    /** @var array<string, string> */
    public array $attributes = [];

    /** @var list<Node> */
    public array $children = [];

    public function __construct(public readonly string $localName) {}

    public function getAttribute(string $name): ?string
    {
        return $this->attributes[$name] ?? null;
    }

    public function hasAttribute(string $name): bool
    {
        return isset($this->attributes[$name]);
    }

    public function setAttribute(string $name, string $value): void
    {
        $this->attributes[$name] = $value;
    }

    public function appendChild(Node $node): void
    {
        $node->parent = $this;
        $this->children[] = $node;
    }

    /** @return list<Element> elements with the given local name in document order. */
    public function findByTag(string $localName): array
    {
        $out = [];
        foreach ($this->children as $child) {
            if ($child instanceof Element) {
                if ($child->localName === $localName) {
                    $out[] = $child;
                }
                foreach ($child->findByTag($localName) as $nested) {
                    $out[] = $nested;
                }
            }
        }
        return $out;
    }
}
