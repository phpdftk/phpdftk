<?php

declare(strict_types=1);

namespace Phpdftk\Html\Dom;

final class Comment extends Node
{
    public string $data;

    public function __construct(Document $ownerDocument, string $data = '')
    {
        parent::__construct($ownerDocument);
        $this->data = $data;
    }

    public function nodeType(): NodeType
    {
        return NodeType::Comment;
    }

    public function nodeName(): string
    {
        return '#comment';
    }

    public function textContent(): string
    {
        return $this->data;
    }

    public function setTextContent(string $text): void
    {
        $this->data = $text;
    }

    protected function shallowClone(): static
    {
        $copy = new self($this->ownerDocument, $this->data);
        /** @var static $copy */
        return $copy;
    }
}
