<?php

declare(strict_types=1);

namespace Phpdftk\Html\Dom;

/**
 * Abstract DOM node. Concrete subclasses: Document, DocumentFragment,
 * DocumentType, Element, Text, Comment.
 *
 * Mutation methods (appendChild, insertBefore, removeChild, replaceChild)
 * maintain the parent / sibling pointer invariants. They're public — per the
 * Q1 contract decision, post-parse mutation is a supported feature (server-
 * side rewriting, sanitization, etc.) rather than an internal-only operation.
 */
abstract class Node
{
    /** Set in constructor; never reassigned. */
    protected readonly ?Document $documentRef;

    public ?Node $parentNode { get => $this->parentRef; }
    private ?Node $parentRef = null;

    public ?Node $previousSibling { get => $this->prevRef; }
    private ?Node $prevRef = null;

    public ?Node $nextSibling { get => $this->nextRef; }
    private ?Node $nextRef = null;

    public ?Node $firstChild { get => $this->firstRef; }
    private ?Node $firstRef = null;

    public ?Node $lastChild { get => $this->lastRef; }
    private ?Node $lastRef = null;

    public ?Element $parentElement {
        get => $this->parentRef instanceof Element ? $this->parentRef : null;
    }

    public Document $ownerDocument {
        get => $this instanceof Document ? $this : ($this->documentRef ?? throw new \LogicException(
            sprintf('Node of type %s has no owner document', static::class),
        ));
    }

    public function __construct(?Document $ownerDocument)
    {
        $this->documentRef = $ownerDocument;
    }

    abstract public function nodeType(): NodeType;

    /**
     * Per WHATWG: uppercase tag name for HTML elements, "#text", "#comment",
     * "#document", "#document-fragment", or the doctype name.
     */
    abstract public function nodeName(): string;

    /** @return list<Node> snapshot of direct children at the time of the call */
    public function childNodes(): array
    {
        $out = [];
        for ($n = $this->firstRef; $n !== null; $n = $n->nextRef) {
            $out[] = $n;
        }
        return $out;
    }

    public function hasChildNodes(): bool
    {
        return $this->firstRef !== null;
    }

    /**
     * Concatenation of descendant Text node data. Per WHATWG: for Document,
     * DocumentType, Comment, ProcessingInstruction this is implementation-
     * defined; for our purposes, Document/DocumentFragment/Element return the
     * concatenation, Text returns its data, Comment returns its data,
     * DocumentType returns ''.
     */
    public function textContent(): string
    {
        $out = '';
        for ($n = $this->firstRef; $n !== null; $n = $n->nextRef) {
            $out .= $n->textContent();
        }
        return $out;
    }

    /**
     * Replace all children with a single Text node containing $text.
     * Empty string clears all children with no replacement.
     */
    public function setTextContent(string $text): void
    {
        while ($this->firstRef !== null) {
            $this->removeChild($this->firstRef);
        }
        if ($text !== '') {
            $this->appendChild(new Text($this->ownerDocument, $text));
        }
    }

    public function appendChild(Node $child): Node
    {
        return $this->insertBefore($child, null);
    }

    public function insertBefore(Node $child, ?Node $reference): Node
    {
        if ($reference !== null && $reference->parentRef !== $this) {
            throw new \InvalidArgumentException('Reference node is not a child of this parent');
        }
        if ($child === $this || $child->isAncestorOf($this)) {
            throw new \InvalidArgumentException('Cannot insert a node into one of its own descendants');
        }

        // Detach from previous parent if any.
        if ($child->parentRef !== null) {
            $child->parentRef->removeChild($child);
        }

        $child->parentRef = $this;

        if ($reference === null) {
            // Append.
            $child->prevRef = $this->lastRef;
            $child->nextRef = null;
            if ($this->lastRef !== null) {
                $this->lastRef->nextRef = $child;
            } else {
                $this->firstRef = $child;
            }
            $this->lastRef = $child;
        } else {
            // Insert before reference.
            $child->prevRef = $reference->prevRef;
            $child->nextRef = $reference;
            if ($reference->prevRef !== null) {
                $reference->prevRef->nextRef = $child;
            } else {
                $this->firstRef = $child;
            }
            $reference->prevRef = $child;
        }

        return $child;
    }

    public function removeChild(Node $child): Node
    {
        if ($child->parentRef !== $this) {
            throw new \InvalidArgumentException('Node is not a child of this parent');
        }
        if ($child->prevRef !== null) {
            $child->prevRef->nextRef = $child->nextRef;
        } else {
            $this->firstRef = $child->nextRef;
        }
        if ($child->nextRef !== null) {
            $child->nextRef->prevRef = $child->prevRef;
        } else {
            $this->lastRef = $child->prevRef;
        }
        $child->parentRef = null;
        $child->prevRef = null;
        $child->nextRef = null;
        return $child;
    }

    public function replaceChild(Node $newChild, Node $oldChild): Node
    {
        if ($oldChild->parentRef !== $this) {
            throw new \InvalidArgumentException('Node to be replaced is not a child of this parent');
        }
        $this->insertBefore($newChild, $oldChild);
        $this->removeChild($oldChild);
        return $oldChild;
    }

    /**
     * Deep- or shallow-clone the node. Per Q1, mutation post-parse is a
     * feature; deep clones produce an independent subtree with no shared
     * parent linkage.
     */
    public function cloneNode(bool $deep = true): static
    {
        $copy = $this->shallowClone();
        if ($deep) {
            for ($n = $this->firstRef; $n !== null; $n = $n->nextRef) {
                $copy->appendChild($n->cloneNode(true));
            }
        }
        /** @var static $copy */
        return $copy;
    }

    /** Subclass-specific construction of a child-less copy. */
    abstract protected function shallowClone(): static;

    protected function isAncestorOf(Node $other): bool
    {
        for ($n = $other->parentRef; $n !== null; $n = $n->parentRef) {
            if ($n === $this) {
                return true;
            }
        }
        return false;
    }
}
