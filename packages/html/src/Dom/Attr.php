<?php

declare(strict_types=1);

namespace Phpdftk\Html\Dom;

/**
 * Attribute value object. Attributes are immutable — to change an attribute,
 * call Element::setAttribute() which constructs a new Attr and replaces the
 * existing entry on the element's attribute map.
 *
 * Namespace defaults to the HTML namespace; foreign-element attributes (SVG,
 * MathML) carry their own namespace and optionally a prefix.
 */
final readonly class Attr
{
    public function __construct(
        public string $localName,
        public string $value,
        public string $namespaceURI = Document::HTML_NS,
        public ?string $prefix = null,
    ) {}

    /**
     * Qualified name: "prefix:localName" if prefix is set, otherwise localName.
     */
    public function qualifiedName(): string
    {
        return $this->prefix !== null ? $this->prefix . ':' . $this->localName : $this->localName;
    }
}
