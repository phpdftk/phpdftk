<?php

declare(strict_types=1);

namespace Phpdftk\Html\Dom;

/**
 * Text node. Holds character data; mutable post-parse via $data.
 */
final class Text extends Node
{
    public string $data;

    public function __construct(Document $ownerDocument, string $data = '')
    {
        parent::__construct($ownerDocument);
        $this->data = $data;
    }

    public function nodeType(): NodeType
    {
        return NodeType::Text;
    }

    public function nodeName(): string
    {
        return '#text';
    }

    public function textContent(): string
    {
        return $this->data;
    }

    public function setTextContent(string $text): void
    {
        $this->data = $text;
    }

    /**
     * Split this text node at $offset. The current node keeps the prefix; a
     * new sibling text node is inserted after it with the suffix, and returned.
     *
     * @throws \OutOfRangeException if offset is outside [0, length].
     */
    public function splitText(int $offset): Text
    {
        $length = strlen($this->data);
        if ($offset < 0 || $offset > $length) {
            throw new \OutOfRangeException(sprintf('splitText offset %d outside [0, %d]', $offset, $length));
        }
        $suffix = substr($this->data, $offset);
        $this->data = substr($this->data, 0, $offset);
        $sibling = new self($this->ownerDocument, $suffix);
        $parent = $this->parentNode;
        if ($parent !== null) {
            $parent->insertBefore($sibling, $this->nextSibling);
        }
        return $sibling;
    }

    public function length(): int
    {
        return strlen($this->data);
    }

    protected function shallowClone(): static
    {
        $copy = new self($this->ownerDocument, $this->data);
        /** @var static $copy */
        return $copy;
    }
}
