<?php

declare(strict_types=1);

namespace Phpdftk\Html\Dom;

use Phpdftk\Css\Selector\MatchableElement;
use Phpdftk\Css\Selector\Matcher;
use Phpdftk\Css\Selector\SelectorParser;

/**
 * An HTML, SVG, or MathML element.
 *
 * Per Q1, the mutation surface (setAttribute, appendChild, attachShadow, ...)
 * is public by design — used both by the parser during tree construction and
 * by author code performing post-parse transformations.
 *
 * Tag names are normalised: HTML elements expose lower-case `localName` and
 * upper-case `tagName` (matching WHATWG); foreign-namespace elements keep
 * the case the parser saw them in.
 *
 * @phpstan-consistent-constructor HTMLSlotElement (the only subclass) keeps the
 *   constructor signature compatible. Required so {@see Element::shallowClone()}
 *   can safely call `new static()`.
 */
class Element extends Node implements MatchableElement
{
    public readonly string $localName;
    public readonly string $namespaceURI;
    public readonly ?string $prefix;

    /** @var array<string, Attr> keyed by qualified name (prefix:localName or localName) */
    private array $attributes = [];

    private ?ClassList $classListInstance = null;
    private ?ShadowRoot $shadowRef = null;

    public string $tagName {
        get {
            $name = $this->prefix !== null
                ? $this->prefix . ':' . $this->localName
                : $this->localName;
            return $this->namespaceURI === Document::HTML_NS ? strtoupper($name) : $name;
        }
    }

    public ?string $id {
        get => $this->getAttribute('id');
    }

    public ClassList $classList {
        get => $this->classListInstance ??= new ClassList($this);
    }

    public ?ShadowRoot $shadowRoot {
        get => $this->shadowRef;
    }

    public function __construct(
        Document $ownerDocument,
        string $localName,
        string $namespaceURI = Document::HTML_NS,
        ?string $prefix = null,
    ) {
        parent::__construct($ownerDocument);
        $this->localName = $localName;
        $this->namespaceURI = $namespaceURI;
        $this->prefix = $prefix;
    }

    public function nodeType(): NodeType
    {
        return NodeType::Element;
    }

    public function nodeName(): string
    {
        return $this->tagName;
    }

    /** @return list<Attr> */
    public function attributes(): array
    {
        return array_values($this->attributes);
    }

    public function hasAttribute(string $name): bool
    {
        return isset($this->attributes[$this->canonicalAttrKey($name)]);
    }

    public function getAttribute(string $name): ?string
    {
        return $this->attributes[$this->canonicalAttrKey($name)]->value ?? null;
    }

    public function getAttributeNode(string $name): ?Attr
    {
        return $this->attributes[$this->canonicalAttrKey($name)] ?? null;
    }

    public function setAttribute(string $name, string $value): void
    {
        $key = $this->canonicalAttrKey($name);
        // Preserve namespace/prefix of existing attribute if present.
        $existing = $this->attributes[$key] ?? null;
        $this->attributes[$key] = new Attr(
            localName: $existing !== null ? $existing->localName : $this->splitLocalName($name),
            value: $value,
            namespaceURI: $existing !== null ? $existing->namespaceURI : Document::HTML_NS,
            prefix: $existing !== null ? $existing->prefix : $this->splitPrefix($name),
        );
    }

    public function setAttributeNode(Attr $attr): void
    {
        $this->attributes[$attr->qualifiedName()] = $attr;
    }

    public function removeAttribute(string $name): void
    {
        unset($this->attributes[$this->canonicalAttrKey($name)]);
    }

    /** @return list<Element> direct element children only */
    public function children(): array
    {
        $out = [];
        for ($n = $this->firstChild; $n !== null; $n = $n->nextSibling) {
            if ($n instanceof Element) {
                $out[] = $n;
            }
        }
        return $out;
    }

    /** @return list<Element> depth-first traversal */
    public function getElementsByTagName(string $localName): array
    {
        $localName = $this->namespaceURI === Document::HTML_NS ? strtolower($localName) : $localName;
        $out = [];
        $this->collectByTagName($this, $localName, $out);
        return $out;
    }

    /**
     * Depth-first descendant traversal returning every element under this
     * node that matches the selector. Per WHATWG `Document::querySelectorAll`
     * doesn't include the host element itself.
     *
     * @return list<Element>
     */
    public function querySelectorAll(string $selector): array
    {
        $list = SelectorParser::parse($selector);
        $matcher = new Matcher();
        $out = [];
        $stack = $this->children();
        while ($stack !== []) {
            $node = array_shift($stack);
            if ($matcher->listMatches($list, $node)) {
                $out[] = $node;
            }
            foreach ($node->children() as $c) {
                $stack[] = $c;
            }
        }
        return $out;
    }

    public function querySelector(string $selector): ?Element
    {
        $matches = $this->querySelectorAll($selector);
        return $matches[0] ?? null;
    }

    public function matches(string $selector): bool
    {
        $list = SelectorParser::parse($selector);
        $matcher = new Matcher();
        return $matcher->listMatches($list, $this);
    }

    public function closest(string $selector): ?Element
    {
        $list = SelectorParser::parse($selector);
        $matcher = new Matcher();
        for ($n = $this; $n !== null; $n = $n->parentNode) {
            if ($n instanceof Element && $matcher->listMatches($list, $n)) {
                return $n;
            }
        }
        return null;
    }

    // ------------------------------------------------------------------
    // MatchableElement implementation — adapts the WHATWG DOM to the
    // structural traversal the CSS selector engine consumes.
    // ------------------------------------------------------------------

    public function localName(): string
    {
        return $this->localName;
    }

    public function namespaceUri(): ?string
    {
        return $this->namespaceURI;
    }

    public function elementId(): ?string
    {
        $id = $this->getAttribute('id');
        return $id === null || $id === '' ? null : $id;
    }

    /** @return list<string> */
    public function classes(): array
    {
        return $this->classList->values();
    }

    public function getAttributeValue(string $name): ?string
    {
        return $this->getAttribute($name);
    }

    /** @return array<string, string> */
    public function allAttributes(): array
    {
        $out = [];
        foreach ($this->attributes() as $attr) {
            $out[$attr->qualifiedName()] = $attr->value;
        }
        return $out;
    }

    public function parentElement(): ?MatchableElement
    {
        $p = $this->parentNode;
        return $p instanceof Element ? $p : null;
    }

    public function previousElementSibling(): ?MatchableElement
    {
        for ($n = $this->previousSibling; $n !== null; $n = $n->previousSibling) {
            if ($n instanceof Element) {
                return $n;
            }
        }
        return null;
    }

    public function nextElementSibling(): ?MatchableElement
    {
        for ($n = $this->nextSibling; $n !== null; $n = $n->nextSibling) {
            if ($n instanceof Element) {
                return $n;
            }
        }
        return null;
    }

    /** @return list<MatchableElement> */
    public function elementChildren(): array
    {
        return $this->children();
    }

    public function indexAmongSiblings(): int
    {
        $i = 1;
        for ($n = $this->previousSibling; $n !== null; $n = $n->previousSibling) {
            if ($n instanceof Element) {
                $i++;
            }
        }
        return $i;
    }

    public function indexAmongSiblingsFromEnd(): int
    {
        $i = 1;
        for ($n = $this->nextSibling; $n !== null; $n = $n->nextSibling) {
            if ($n instanceof Element) {
                $i++;
            }
        }
        return $i;
    }

    public function indexAmongTypeSiblings(): int
    {
        $i = 1;
        for ($n = $this->previousSibling; $n !== null; $n = $n->previousSibling) {
            if ($n instanceof Element
                && $n->localName === $this->localName
                && $n->namespaceURI === $this->namespaceURI
            ) {
                $i++;
            }
        }
        return $i;
    }

    public function indexAmongTypeSiblingsFromEnd(): int
    {
        $i = 1;
        for ($n = $this->nextSibling; $n !== null; $n = $n->nextSibling) {
            if ($n instanceof Element
                && $n->localName === $this->localName
                && $n->namespaceURI === $this->namespaceURI
            ) {
                $i++;
            }
        }
        return $i;
    }

    /**
     * Attach a shadow root to this element. Used by the parser when handling
     * <template shadowrootmode> (declarative shadow DOM). Available publicly
     * but rarely needed by user code — DSD is the documented path.
     *
     * @throws \LogicException if a shadow root is already attached or this
     *         element is not shadow-host-eligible.
     */
    public function attachShadow(ShadowRootMode $mode, ShadowRootInit $init = new ShadowRootInit()): ShadowRoot
    {
        if ($this->shadowRef !== null) {
            throw new \LogicException(sprintf('Element <%s> already has a shadow root', $this->localName));
        }
        if (!$this->isShadowHostEligible()) {
            throw new \LogicException(
                sprintf('Element <%s> is not shadow-host-eligible per WHATWG', $this->localName),
            );
        }
        $this->shadowRef = new ShadowRoot($this, $mode, $init);
        return $this->shadowRef;
    }

    /**
     * Per WHATWG: shadow-host-eligible HTML elements are valid custom-element
     * names plus the explicit allow-list. Foreign elements are not eligible.
     */
    public function isShadowHostEligible(): bool
    {
        if ($this->namespaceURI !== Document::HTML_NS) {
            return false;
        }
        $allowed = [
            'article', 'aside', 'blockquote', 'body', 'div', 'footer', 'h1', 'h2', 'h3',
            'h4', 'h5', 'h6', 'header', 'main', 'nav', 'p', 'section', 'span',
        ];
        if (in_array($this->localName, $allowed, true)) {
            return true;
        }
        // Custom-element names: contain a hyphen, start with [a-z], match PCEN.
        if (str_contains($this->localName, '-') && preg_match('/^[a-z][a-z0-9_.\-]*$/', $this->localName)) {
            return true;
        }
        return false;
    }

    /** @param list<Element> $out */
    private function collectByTagName(Node $scope, string $localName, array &$out): void
    {
        for ($n = $scope->firstChild; $n !== null; $n = $n->nextSibling) {
            if ($n instanceof Element && ($localName === '*' || $n->localName === $localName)) {
                $out[] = $n;
            }
            if ($n->hasChildNodes()) {
                $this->collectByTagName($n, $localName, $out);
            }
        }
    }

    private function canonicalAttrKey(string $name): string
    {
        return $this->namespaceURI === Document::HTML_NS ? strtolower($name) : $name;
    }

    private function splitPrefix(string $qualified): ?string
    {
        $i = strpos($qualified, ':');
        return $i === false ? null : substr($qualified, 0, $i);
    }

    private function splitLocalName(string $qualified): string
    {
        $i = strpos($qualified, ':');
        return $i === false ? $qualified : substr($qualified, $i + 1);
    }

    protected function shallowClone(): static
    {
        $copy = new static($this->ownerDocument, $this->localName, $this->namespaceURI, $this->prefix);
        foreach ($this->attributes as $attr) {
            $copy->setAttributeNode($attr);
        }
        if ($this->shadowRef !== null && $this->shadowRef->clonable) {
            $cloneInit = new ShadowRootInit(
                delegatesFocus: $this->shadowRef->delegatesFocus,
                clonable: true,
                serializable: $this->shadowRef->serializable,
                slotAssignment: $this->shadowRef->slotAssignment,
            );
            $newShadow = $copy->attachShadow($this->shadowRef->mode, $cloneInit);
            for ($n = $this->shadowRef->firstChild; $n !== null; $n = $n->nextSibling) {
                $newShadow->appendChild($n->cloneNode(true));
            }
        }
        /** @var static $copy */
        return $copy;
    }
}
