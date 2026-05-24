<?php

declare(strict_types=1);

namespace Phpdftk\Html\Dom;

/**
 * The root of a parsed HTML document.
 *
 * Per the contract decision (Q1), Document is mutable post-parse.
 */
final class Document extends Node
{
    public const string HTML_NS = 'http://www.w3.org/1999/xhtml';
    public const string SVG_NS = 'http://www.w3.org/2000/svg';
    public const string MATHML_NS = 'http://www.w3.org/1998/Math/MathML';
    public const string XML_NS = 'http://www.w3.org/XML/1998/namespace';
    public const string XMLNS_NS = 'http://www.w3.org/2000/xmlns/';
    public const string XLINK_NS = 'http://www.w3.org/1999/xlink';

    public DocumentMode $mode = DocumentMode::NoQuirks;
    public string $characterSet = 'UTF-8';

    public ?DocumentType $doctype {
        get {
            for ($n = $this->firstChild; $n !== null; $n = $n->nextSibling) {
                if ($n instanceof DocumentType) {
                    return $n;
                }
            }
            return null;
        }
    }

    public ?Element $documentElement {
        get {
            for ($n = $this->firstChild; $n !== null; $n = $n->nextSibling) {
                if ($n instanceof Element) {
                    return $n;
                }
            }
            return null;
        }
    }

    public ?Element $head {
        get => $this->findHtmlChild('head');
    }

    public ?Element $body {
        get => $this->findHtmlChild('body');
    }

    public ?string $title {
        get {
            $head = $this->head;
            if ($head === null) {
                return null;
            }
            for ($n = $head->firstChild; $n !== null; $n = $n->nextSibling) {
                if ($n instanceof Element && $n->localName === 'title') {
                    return $n->textContent();
                }
            }
            return null;
        }
    }

    public function __construct()
    {
        // Document has no owner — passing null is allowed; the ownerDocument
        // accessor on Node returns $this for Document instances.
        parent::__construct(null);
    }

    public function nodeType(): NodeType
    {
        return NodeType::Document;
    }

    public function nodeName(): string
    {
        return '#document';
    }

    public function createElement(string $localName, string $namespace = self::HTML_NS): Element
    {
        // Only lower-case for HTML — SVG and MathML are case-sensitive
        // (linearGradient, foreignObject, etc.). The parser hands us the
        // canonical case in those namespaces via its case-correction tables.
        $name = $namespace === self::HTML_NS ? strtolower($localName) : $localName;
        // Dispatch to specialised element classes for HTML elements with
        // dedicated DOM behaviour (slot distribution, template content).
        if ($namespace === self::HTML_NS) {
            return match ($name) {
                'template' => new HTMLTemplateElement($this, $name, $namespace),
                'slot' => new HTMLSlotElement($this, $name, $namespace),
                default => new Element($this, $name, $namespace),
            };
        }
        return new Element($this, $name, $namespace);
    }

    public function createTextNode(string $data): Text
    {
        return new Text($this, $data);
    }

    public function createComment(string $data): Comment
    {
        return new Comment($this, $data);
    }

    public function createDocumentFragment(): DocumentFragment
    {
        return new DocumentFragment($this);
    }

    /**
     * Find descendants by lower-cased HTML tag name. Depth-first traversal.
     *
     * @return list<Element>
     */
    public function getElementsByTagName(string $localName): array
    {
        $localName = strtolower($localName);
        $out = [];
        $this->collectByTagName($this, $localName, $out);
        return $out;
    }

    public function getElementById(string $id): ?Element
    {
        return $this->findById($this, $id);
    }

    private function findHtmlChild(string $localName): ?Element
    {
        $root = $this->documentElement;
        if ($root === null) {
            return null;
        }
        for ($n = $root->firstChild; $n !== null; $n = $n->nextSibling) {
            if ($n instanceof Element && $n->localName === $localName && $n->namespaceURI === self::HTML_NS) {
                return $n;
            }
        }
        return null;
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

    private function findById(Node $scope, string $id): ?Element
    {
        for ($n = $scope->firstChild; $n !== null; $n = $n->nextSibling) {
            if ($n instanceof Element && $n->id === $id) {
                return $n;
            }
            if ($n->hasChildNodes()) {
                $found = $this->findById($n, $id);
                if ($found !== null) {
                    return $found;
                }
            }
        }
        return null;
    }

    protected function shallowClone(): static
    {
        $copy = new self();
        $copy->mode = $this->mode;
        $copy->characterSet = $this->characterSet;
        /** @var static $copy */
        return $copy;
    }
}
