<?php

declare(strict_types=1);

namespace Phpdftk\HtmlToPdf\Tests\ForeignContent;

use Phpdftk\Html\Dom\Attr;
use Phpdftk\Html\Dom\Document as HtmlDocument;
use Phpdftk\Html\Dom\Element as HtmlElement;
use Phpdftk\Html\Dom\Text as HtmlText;
use Phpdftk\HtmlToPdf\ForeignContent\DomXmlSerializer;
use PHPUnit\Framework\TestCase;

/**
 * Unit coverage for the HTML-DOM → XML serializer. This is the
 * shared bridge that both {@see \Phpdftk\HtmlToPdf\Svg\InlineSvgAdapter}
 * and {@see \Phpdftk\HtmlToPdf\Mathml\InlineMathmlAdapter} hand
 * their subtrees to before parsing.
 *
 * Coverage bias is on the walking edge cases: text nodes,
 * whitespace, prefixed attrs, xmlns declarations, namespace
 * declaration on the root.
 */
final class DomXmlSerializerTest extends TestCase
{
    private DomXmlSerializer $serializer;
    private HtmlDocument $doc;

    private const string SVG_NS = 'http://www.w3.org/2000/svg';
    private const string MATHML_NS = 'http://www.w3.org/1998/Math/MathML';

    protected function setUp(): void
    {
        $this->serializer = new DomXmlSerializer();
        $this->doc = new HtmlDocument();
    }

    // -----------------------------------------------------------------
    // Negative + edge cases
    // -----------------------------------------------------------------

    public function testSkipsXmlnsAttributesToAvoidDuplicateDeclarations(): void
    {
        // Author markup with an xmlns attribute that the HTML parser
        // forwarded onto the foreign-namespace element. saveXML adds
        // its own xmlns based on createElementNS; we must NOT also
        // surface the author's literal one or libxml emits a parse
        // error on the round-trip.
        $root = $this->newElement('svg', self::SVG_NS);
        $root->setAttributeNode(new Attr(
            localName: 'xmlns',
            value: self::SVG_NS,
            namespaceURI: HtmlDocument::XMLNS_NS,
        ));

        $xml = $this->serializer->serialize($root, self::SVG_NS);
        // The output should have exactly one xmlns declaration —
        // the one createElementNS produced.
        $matches = [];
        preg_match_all('/xmlns(?:=|:)[^"]*"[^"]*"/', $xml, $matches);
        self::assertCount(1, $matches[0], "got: $xml");
    }

    public function testSkipsXmlnsPrefixedAttributesToo(): void
    {
        // xmlns:foo declarations are equally a forwarding artefact.
        $root = $this->newElement('svg', self::SVG_NS);
        $root->setAttributeNode(new Attr(
            localName: 'foo',
            value: 'urn:foo',
            namespaceURI: HtmlDocument::XMLNS_NS,
            prefix: 'xmlns',
        ));
        $xml = $this->serializer->serialize($root, self::SVG_NS);
        self::assertStringNotContainsString('xmlns:foo', $xml);
    }

    public function testIgnoresEmptyTextNodes(): void
    {
        // Empty text nodes show up between sibling elements in HTML
        // DOM that's been canonicalised; they shouldn't bloat the
        // serialised XML with empty text content.
        $root = $this->newElement('svg', self::SVG_NS);
        $root->appendChild(new HtmlText($this->doc, ''));
        $rect = $this->newElement('rect', self::SVG_NS);
        $root->appendChild($rect);
        $root->appendChild(new HtmlText($this->doc, ''));

        $xml = $this->serializer->serialize($root, self::SVG_NS);
        // No spurious `<text></text>`-like artefacts.
        self::assertStringNotContainsString('><text/>', $xml);
        self::assertStringContainsString('<rect/>', $xml);
    }

    public function testFlattensSiblingNamespaceIntoFormatNamespace(): void
    {
        // Document with a MathML-namespaced child inside an SVG
        // subtree — `<svg><foo xmlns="math-ns"/></svg>`. The
        // serializer collapses everything into the requested
        // namespace, so the round-trip yields an SVG-namespaced
        // tree. The consumer parser then treats `foo` as
        // GenericElement.
        $svg = $this->newElement('svg', self::SVG_NS);
        $mathChild = $this->newElement('foo', self::MATHML_NS);
        $svg->appendChild($mathChild);

        $xml = $this->serializer->serialize($svg, self::SVG_NS);
        // The output's root namespace is SVG.
        self::assertStringContainsString('xmlns="' . self::SVG_NS . '"', $xml);
        // And the `<foo>` child is also SVG-namespaced (the original
        // MathML namespace is collapsed away).
        self::assertStringNotContainsString(self::MATHML_NS, $xml);
    }

    public function testHandlesDeeplyNestedSubtree(): void
    {
        // 30-deep nested elements. Confirms the recursive walk
        // doesn't trip the default xdebug.max_nesting_level (256)
        // and produces a well-formed XML.
        $root = $this->newElement('svg', self::SVG_NS);
        $cursor = $root;
        for ($i = 0; $i < 30; $i++) {
            $child = $this->newElement('g', self::SVG_NS);
            $cursor->appendChild($child);
            $cursor = $child;
        }
        // Sanity: a real element at the leaf with attributes.
        $leaf = $this->newElement('rect', self::SVG_NS);
        $leaf->setAttribute('width', '10');
        $cursor->appendChild($leaf);

        $xml = $this->serializer->serialize($root, self::SVG_NS);
        // Output should contain 30 `<g>` opens, 30 `<g>` closes, plus
        // the rect with the width attr.
        self::assertSame(30, substr_count($xml, '<g>'));
        self::assertSame(30, substr_count($xml, '</g>'));
        self::assertStringContainsString('<rect width="10"/>', $xml);
    }

    // -----------------------------------------------------------------
    // Positive cases
    // -----------------------------------------------------------------

    public function testWrapsRootWithRequestedNamespace(): void
    {
        $root = $this->newElement('svg', self::SVG_NS);
        $xml = $this->serializer->serialize($root, self::SVG_NS);
        self::assertStringContainsString('xmlns="' . self::SVG_NS . '"', $xml);
        self::assertStringStartsWith('<svg', $xml);
    }

    public function testMathmlRootGetsMathmlNamespace(): void
    {
        // Confirms the namespace plumbing is parameterised — the
        // serializer works for any foreign namespace, not just SVG.
        $root = $this->newElement('math', self::MATHML_NS);
        $xml = $this->serializer->serialize($root, self::MATHML_NS);
        self::assertStringContainsString('xmlns="' . self::MATHML_NS . '"', $xml);
        self::assertStringStartsWith('<math', $xml);
    }

    public function testPreservesAttributesInSourceOrder(): void
    {
        $root = $this->newElement('rect', self::SVG_NS);
        $root->setAttribute('x', '1');
        $root->setAttribute('y', '2');
        $root->setAttribute('width', '3');
        $root->setAttribute('height', '4');
        $root->setAttribute('fill', '#000');

        $xml = $this->serializer->serialize($root, self::SVG_NS);
        // libxml's saveXML emits attributes in insertion order; we
        // assert the relative ordering rather than exact positions.
        $xPos = strpos($xml, 'x="1"');
        $widthPos = strpos($xml, 'width="3"');
        $fillPos = strpos($xml, 'fill="#000"');
        self::assertNotFalse($xPos);
        self::assertNotFalse($widthPos);
        self::assertNotFalse($fillPos);
        self::assertLessThan($widthPos, $xPos);
        self::assertLessThan($fillPos, $widthPos);
    }

    public function testPreservesPrefixedAttributesViaQualifiedName(): void
    {
        // xlink:href and href must remain distinct keys in the output —
        // the consumer parser distinguishes them.
        $root = $this->newElement('svg', self::SVG_NS);
        $root->setAttribute('xmlns:xlink', 'http://www.w3.org/1999/xlink');
        $use = $this->newElement('use', self::SVG_NS);
        $use->setAttributeNode(new Attr(
            localName: 'href',
            value: '#defined',
            namespaceURI: HtmlDocument::XLINK_NS,
            prefix: 'xlink',
        ));
        $root->appendChild($use);

        $xml = $this->serializer->serialize($root, self::SVG_NS);
        self::assertStringContainsString('xlink:href="#defined"', $xml);
    }

    public function testPreservesTextChildren(): void
    {
        // <math><mn>2</mn></math> — the MathML use case that needs
        // text content surviving the round-trip.
        $math = $this->newElement('math', self::MATHML_NS);
        $mn = $this->newElement('mn', self::MATHML_NS);
        $mn->appendChild(new HtmlText($this->doc, '2'));
        $math->appendChild($mn);

        $xml = $this->serializer->serialize($math, self::MATHML_NS);
        self::assertStringContainsString('<mn>2</mn>', $xml);
    }

    public function testInterleavesTextAndElementChildren(): void
    {
        // Some MathML containers mix text + tokens: e.g. a hand-
        // authored `<mrow>x<mn>2</mn></mrow>` would have a raw text
        // sibling of the `<mn>`. The serializer preserves order.
        $mrow = $this->newElement('mrow', self::MATHML_NS);
        $mrow->appendChild(new HtmlText($this->doc, 'before'));
        $mrow->appendChild($this->newElement('mn', self::MATHML_NS));
        $mrow->appendChild(new HtmlText($this->doc, 'after'));

        $xml = $this->serializer->serialize($mrow, self::MATHML_NS);
        $beforePos = strpos($xml, 'before');
        $mnPos = strpos($xml, '<mn');
        $afterPos = strpos($xml, 'after');
        self::assertLessThan($mnPos, $beforePos);
        self::assertLessThan($afterPos, $mnPos);
    }

    // -----------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------

    private function newElement(string $localName, string $namespace): HtmlElement
    {
        return new HtmlElement($this->doc, $localName, $namespace);
    }
}
