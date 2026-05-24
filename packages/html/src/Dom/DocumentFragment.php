<?php

declare(strict_types=1);

namespace Phpdftk\Html\Dom;

/**
 * A lightweight container that holds a subtree without participating in the
 * main document tree. Used by Parser::parseFragment and by ShadowRoot.
 */
class DocumentFragment extends Node
{
    public function __construct(Document $ownerDocument)
    {
        parent::__construct($ownerDocument);
    }

    public function nodeType(): NodeType
    {
        return NodeType::DocumentFragment;
    }

    public function nodeName(): string
    {
        return '#document-fragment';
    }

    protected function shallowClone(): static
    {
        // ShadowRoot, the only subclass with an incompatible constructor,
        // overrides shallowClone to throw — so new static() here is reachable
        // only for DocumentFragment itself.
        /** @phpstan-ignore-next-line new.static */
        $copy = new static($this->ownerDocument);
        /** @var static $copy */
        return $copy;
    }
}
