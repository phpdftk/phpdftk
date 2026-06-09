<?php

declare(strict_types=1);

namespace Phpdftk\HtmlToPdf\ForeignContent;

use Phpdftk\Html\Dom\Element as HtmlElement;
use Phpdftk\Html\Dom\Text as HtmlText;

/**
 * Serialise an HTML-DOM subtree into an XML string the foreign-content
 * parsers ({@see \Phpdftk\Svg\Parser}, {@see \Phpdftk\Mathml\Parser})
 * can ingest.
 *
 * The HTML parser produces nodes in its own type system
 * ({@see HtmlElement} and friends, not `\DOMElement`), but the SVG
 * and MathML parsers only accept XML strings. This class bridges the
 * gap once per inline-foreign subtree:
 *
 *   1. Build a fresh `\DOMDocument` mirroring the HTML DOM subtree.
 *   2. Declare the foreign namespace explicitly on the root via
 *      `createElementNS()` so the parser's namespace check passes
 *      (Svg / MathML parsers reject documents whose root carries
 *      the wrong namespace, with an error message that includes
 *      `unexpected namespace …`).
 *   3. Call `saveXML()` and hand the string back.
 *
 * The shared shape is just the walk + the namespace plumbing.
 * Adapters that wrap this class (`InlineSvgAdapter`,
 * `InlineMathmlAdapter`) keep format-specific concerns — namespace /
 * localName validation, caching, parser dispatch — close to their
 * call sites.
 *
 * Design note: this lives inside `html-to-pdf` rather than a new
 * shared package because (a) it depends on `Phpdftk\Html\Dom`, which
 * is already a dependency of `html-to-pdf`, and (b) `Phpdftk\Svg` /
 * `Phpdftk\Mathml` shouldn't grow a dep on `phpdftk/html`. Lifting
 * this to a new package would force inter-package coupling for no
 * measurable gain.
 */
final class DomXmlSerializer
{
    /**
     * Serialise `$root` into an XML string, declaring `$namespaceUri`
     * as the default namespace on the root element.
     *
     * Sibling namespaces inside the subtree (e.g. MathML's
     * `<annotation-xml>` inside a `<foreignObject>`) are deliberately
     * collapsed into `$namespaceUri` — the consumer parser falls back
     * to its `GenericElement` for anything it doesn't recognise, so
     * preserving the original namespace would only complicate the
     * round-trip without changing the rendered output.
     *
     * @throws \RuntimeException When libxml's `saveXML()` refuses
     *   (malformed attribute name, invalid character data, …). The
     *   message preserves enough context to identify the foreign
     *   subtree.
     */
    public function serialize(HtmlElement $root, string $namespaceUri): string
    {
        $dom = new \DOMDocument('1.0', 'UTF-8');
        $dom->preserveWhiteSpace = true;
        $dom->formatOutput = false;
        $domRoot = $this->buildElement($dom, $root, $namespaceUri);
        $dom->appendChild($domRoot);
        $xml = $dom->saveXML($domRoot);
        if ($xml === false) {
            throw new \RuntimeException(sprintf(
                'Failed to serialise inline-<%s> subtree to XML.',
                $root->localName,
            ));
        }
        return $xml;
    }

    private function buildElement(
        \DOMDocument $dom,
        HtmlElement $src,
        string $namespaceUri,
    ): \DOMElement {
        $node = $dom->createElementNS($namespaceUri, $src->localName);
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
        // too (e.g. `<text>hello</text>` for SVG, `<mn>2</mn>` for
        // MathML). Walk the live sibling chain so Text + Element nodes
        // both arrive in document order.
        for ($child = $src->firstChild; $child !== null; $child = $child->nextSibling) {
            if ($child instanceof HtmlText) {
                if ($child->data !== '') {
                    $node->appendChild($dom->createTextNode($child->data));
                }
                continue;
            }
            if ($child instanceof HtmlElement) {
                $node->appendChild($this->buildElement($dom, $child, $namespaceUri));
                continue;
            }
            // Other node types (Comment, DocumentType, processing
            // instructions, …) are skipped — both SVG and MathML
            // parsers drop them at their tree-walk layer.
        }
        return $node;
    }
}
