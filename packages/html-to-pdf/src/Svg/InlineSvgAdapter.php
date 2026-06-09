<?php

declare(strict_types=1);

namespace Phpdftk\HtmlToPdf\Svg;

use Phpdftk\Html\Dom\Element as HtmlElement;
use Phpdftk\Html\Dom\Text as HtmlText;
use Phpdftk\Svg\Parser as SvgParser;
use Phpdftk\Svg\SvgDocument;

/**
 * Convert an inline-SVG subtree from the HTML DOM into a typed
 * {@see SvgDocument} so the existing SVG renderer can paint it.
 *
 * The HTML parser tags `<svg>` subtrees with `namespaceURI === SVG_NS`
 * but produces nodes in its own type system ({@see HtmlElement}, not
 * `\DOMElement`). The Svg package's parser only accepts XML strings.
 * This adapter bridges the gap by serialising the HTML DOM subtree
 * into a `\DOMDocument`, calling `saveXML()` on it, and handing the
 * result to {@see SvgParser::parse()}.
 *
 * Why this roundtrip and not a direct HTML-DOM-walking variant of the
 * SVG parser:
 *
 *   - Keeps the SVG package's public API stable — no new entry point.
 *   - Doesn't couple the SVG package to the HTML DOM types.
 *   - Reuses the SVG parser's full validation (root element, viewBox,
 *     unknown-element fallback) at no extra cost.
 *
 * The cost is one extra parse pass per inline SVG. We memoise the
 * result keyed on element identity so a fixture with the same `<svg>`
 * painted on N pages only pays the cost once.
 */
final class InlineSvgAdapter
{
    /**
     * Cache: SplObjectStorage keyed on the HTML DOM element identity.
     * Identity (===), NOT equality — two distinct `<svg>` elements
     * with identical markup get parsed once each.
     *
     * @var \SplObjectStorage<HtmlElement, SvgDocument>
     */
    private \SplObjectStorage $cache;

    public function __construct(private readonly SvgParser $parser = new SvgParser())
    {
        $this->cache = new \SplObjectStorage();
    }

    /**
     * Adapt an HTML DOM `<svg>` element into a typed SvgDocument.
     *
     * @throws \InvalidArgumentException When the element isn't an
     *   `<svg>` in the SVG namespace.
     */
    public function adapt(HtmlElement $element): SvgDocument
    {
        if (strtolower($element->localName) !== 'svg') {
            throw new \InvalidArgumentException(sprintf(
                'InlineSvgAdapter expects an <svg> element, got <%s>.',
                $element->localName,
            ));
        }
        if ($element->namespaceUri() !== SvgParser::SVG_NS) {
            throw new \InvalidArgumentException(sprintf(
                'InlineSvgAdapter expects namespace %s, got %s.',
                SvgParser::SVG_NS,
                $element->namespaceUri() ?? '(null)',
            ));
        }
        if ($this->cache->contains($element)) {
            return $this->cache[$element];
        }
        $xml = $this->serialise($element);
        $svg = $this->parser->parse($xml);
        $this->cache[$element] = $svg;
        return $svg;
    }

    /**
     * Build a `\DOMDocument` mirroring the HTML DOM subtree, with the
     * SVG namespace declared explicitly on the root, then serialise.
     *
     * We walk via `\DOMDocument::createElementNS()` to keep the
     * namespace coherent across nested elements — sibling-package
     * namespaces (MathML's `<annotation-xml>`, etc.) inside a
     * `<foreignObject>` are deliberately collapsed into the SVG
     * namespace because the SVG parser falls back to GenericElement
     * for anything it doesn't recognise.
     */
    private function serialise(HtmlElement $root): string
    {
        $dom = new \DOMDocument('1.0', 'UTF-8');
        $dom->preserveWhiteSpace = true;
        $dom->formatOutput = false;
        $domRoot = $this->buildElement($dom, $root);
        $dom->appendChild($domRoot);
        $xml = $dom->saveXML($domRoot);
        if ($xml === false) {
            throw new \RuntimeException('Failed to serialise inline-SVG subtree to XML.');
        }
        return $xml;
    }

    private function buildElement(\DOMDocument $dom, HtmlElement $src): \DOMElement
    {
        $node = $dom->createElementNS(SvgParser::SVG_NS, $src->localName);
        // attributes() returns a list of Attr objects in source order.
        // (allAttributes() returns a name=>value map but loses prefix
        // info, which we need to distinguish e.g. xlink:href from href.)
        foreach ($src->attributes() as $attr) {
            // Skip xmlns redeclarations — saveXML adds the inherited
            // one on the root and we don't want duplicate declarations
            // peppered through the tree.
            if ($attr->localName === 'xmlns'
                || $attr->prefix === 'xmlns'
            ) {
                continue;
            }
            // Keep prefixed attrs (e.g. xlink:href) intact via qualified
            // name. setAttribute() takes a qualified name; libxml will
            // create the prefix declaration if it's new.
            $node->setAttribute($attr->qualifiedName(), $attr->value);
        }
        // children() filters to Element only, but we need text nodes
        // too (e.g. `<text>hello</text>`). Walk the live sibling chain
        // instead so Text + Element nodes both arrive in document order.
        for ($child = $src->firstChild; $child !== null; $child = $child->nextSibling) {
            if ($child instanceof HtmlText) {
                if ($child->data !== '') {
                    $node->appendChild($dom->createTextNode($child->data));
                }
                continue;
            }
            if ($child instanceof HtmlElement) {
                $node->appendChild($this->buildElement($dom, $child));
                continue;
            }
            // Other node types (Comment, DocumentType, …) are skipped —
            // the SVG parser drops them too.
        }
        return $node;
    }
}
