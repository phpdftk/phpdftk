<?php

declare(strict_types=1);

namespace Phpdftk\Html\Dom;

/**
 * The DOCTYPE node. Per HTML5, only one DOCTYPE per document is supported and
 * it must precede the documentElement.
 */
final class DocumentType extends Node
{
    public string $name;
    public string $publicId;
    public string $systemId;

    public function __construct(
        Document $ownerDocument,
        string $name,
        string $publicId = '',
        string $systemId = '',
    ) {
        parent::__construct($ownerDocument);
        $this->name = $name;
        $this->publicId = $publicId;
        $this->systemId = $systemId;
    }

    public function nodeType(): NodeType
    {
        return NodeType::DocumentType;
    }

    public function nodeName(): string
    {
        return $this->name;
    }

    public function textContent(): string
    {
        return '';
    }

    public function setTextContent(string $text): void
    {
        // DocumentType has no editable text content per WHATWG.
    }

    protected function shallowClone(): static
    {
        $copy = new self($this->ownerDocument, $this->name, $this->publicId, $this->systemId);
        /** @var static $copy */
        return $copy;
    }
}
