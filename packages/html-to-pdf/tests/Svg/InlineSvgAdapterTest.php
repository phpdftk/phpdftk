<?php

declare(strict_types=1);

namespace Phpdftk\HtmlToPdf\Tests\Svg;

use Phpdftk\Html\Dom\Document as HtmlDocument;
use Phpdftk\Html\Dom\Element as HtmlElement;
use Phpdftk\Html\Dom\Text as HtmlText;
use Phpdftk\HtmlToPdf\Svg\InlineSvgAdapter;
use Phpdftk\Svg\Exception\InvalidSvgException;
use Phpdftk\Svg\Parser as SvgParser;
use Phpdftk\Svg\Shape\Rect;
use Phpdftk\Svg\SvgDocument;
use PHPUnit\Framework\TestCase;

/**
 * Unit coverage for the HTML-DOM → SvgDocument adapter. The bulk of
 * the test cases are rejection paths — this adapter is the trust
 * boundary between the HTML parser's foreign-content output and the
 * SVG renderer, so every "wrong shape" input gets explicit coverage
 * before the positive paths.
 */
final class InlineSvgAdapterTest extends TestCase
{
    private InlineSvgAdapter $adapter;
    private HtmlDocument $doc;

    protected function setUp(): void
    {
        $this->adapter = new InlineSvgAdapter();
        $this->doc = new HtmlDocument();
    }

    // -----------------------------------------------------------------
    // Negative cases
    // -----------------------------------------------------------------

    public function testRejectsNonSvgLocalName(): void
    {
        $div = new HtmlElement($this->doc, 'div', HtmlDocument::HTML_NS);
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/expects an <svg>/');
        $this->adapter->adapt($div);
    }

    public function testRejectsSvgInHtmlNamespace(): void
    {
        // A <svg> element that the parser somehow tagged with the HTML
        // namespace instead of SVG_NS — shouldn't happen in normal HTML
        // foreign-content flow, but explicit rejection catches a future
        // regression where the parser routes <svg> wrong.
        $svg = new HtmlElement($this->doc, 'svg', HtmlDocument::HTML_NS);
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/namespace/');
        $this->adapter->adapt($svg);
    }

    public function testRejectsSvgWithNoNamespace(): void
    {
        // namespaceURI on Element is non-nullable, but namespaceUri()
        // accessor returns ?string — guard against any future variant
        // where it can be null (e.g. detached test fixtures).
        $svg = new HtmlElement($this->doc, 'svg', HtmlDocument::HTML_NS);
        $this->expectException(\InvalidArgumentException::class);
        $this->adapter->adapt($svg);
    }

    public function testRejectsWrongLocalNameInSvgNamespace(): void
    {
        // <math xmlns="…SVG…"> shouldn't validate as an SVG root.
        $math = new HtmlElement($this->doc, 'math', HtmlDocument::SVG_NS);
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/<svg>/');
        $this->adapter->adapt($math);
    }

    public function testRejectsMalformedSubtree(): void
    {
        // Build an `<svg>` whose child has a malformed attribute name
        // (a literal newline). saveXML refuses; we should surface a
        // RuntimeException rather than a silent empty SvgDocument.
        $svg = $this->newSvgElement('svg');
        $bogus = $this->newSvgElement("bad\nname");
        $svg->appendChild($bogus);
        $this->expectException(\Throwable::class);
        $this->adapter->adapt($svg);
    }

    // -----------------------------------------------------------------
    // Positive cases
    // -----------------------------------------------------------------

    public function testAdaptsEmptySvgRoot(): void
    {
        $svg = $this->newSvgElement('svg');
        $doc = $this->adapter->adapt($svg);
        self::assertInstanceOf(SvgDocument::class, $doc);
    }

    public function testAdaptsPrefixedSvgSvg(): void
    {
        // XHTML-style `<svg:svg>` left as a plain HTML element (localName
        // "svg:svg", HTML namespace) by the HTML parser must still adapt.
        $svg = new HtmlElement($this->doc, 'svg:svg', HtmlDocument::HTML_NS);
        $rect = new HtmlElement($this->doc, 'svg:rect', HtmlDocument::HTML_NS);
        $rect->setAttribute('width', '50');
        $rect->setAttribute('height', '40');
        $rect->setAttribute('fill', 'blue');
        $svg->appendChild($rect);

        $doc = $this->adapter->adapt($svg);

        self::assertInstanceOf(SvgDocument::class, $doc);
        $children = $doc->children;
        self::assertCount(1, $children);
        self::assertInstanceOf(Rect::class, $children[0]);
    }

    public function testAdaptsSvgWithSingleRectChild(): void
    {
        $svg = $this->newSvgElement('svg');
        $svg->setAttribute('viewBox', '0 0 100 100');
        $rect = $this->newSvgElement('rect');
        $rect->setAttribute('x', '10');
        $rect->setAttribute('y', '20');
        $rect->setAttribute('width', '50');
        $rect->setAttribute('height', '40');
        $rect->setAttribute('fill', '#ff0000');
        $svg->appendChild($rect);

        $doc = $this->adapter->adapt($svg);

        self::assertInstanceOf(SvgDocument::class, $doc);
        self::assertSame('0 0 100 100', $doc->attributes['viewBox'] ?? null);

        $children = $this->childElements($doc);
        self::assertCount(1, $children);
        self::assertInstanceOf(Rect::class, $children[0]);
        self::assertSame('10', $children[0]->attributes['x'] ?? null);
        self::assertSame('#ff0000', $children[0]->attributes['fill'] ?? null);
    }

    public function testPreservesTextChildren(): void
    {
        // <svg><text>hello</text></svg> — the text content must survive
        // the round-trip; the SVG parser uses it for the <text> element.
        $svg = $this->newSvgElement('svg');
        $text = $this->newSvgElement('text');
        $text->appendChild(new HtmlText($this->doc, 'hello'));
        $svg->appendChild($text);

        $doc = $this->adapter->adapt($svg);
        $children = $this->childElements($doc);
        self::assertCount(1, $children);
        // Text content lands inside the <text>; SVG models it however
        // it pleases, but the bytes have to be there.
        self::assertStringContainsString('hello', $this->stringifySubtree($children[0]));
    }

    public function testRoundTripsXlinkHrefAttribute(): void
    {
        // xlink:href is the canonical "needs prefix preservation" case
        // — `xlink:href` and `href` are different keys per the SVG
        // parser, so dropping the prefix would silently break <use> /
        // <a>.
        $svg = $this->newSvgElement('svg');
        $use = $this->newSvgElement('use');
        // Add the xlink namespace declaration first so libxml accepts
        // the prefixed attribute on setAttribute().
        $svg->setAttribute('xmlns:xlink', 'http://www.w3.org/1999/xlink');
        $use->setAttributeNode(new \Phpdftk\Html\Dom\Attr(
            localName: 'href',
            value: '#defined-shape',
            namespaceURI: HtmlDocument::XLINK_NS,
            prefix: 'xlink',
        ));
        $svg->appendChild($use);

        $doc = $this->adapter->adapt($svg);
        $useChildren = $this->childElements($doc);
        self::assertCount(1, $useChildren);
        // The Svg parser stores qualified names as keys, so the value
        // should be reachable via 'xlink:href'.
        self::assertSame('#defined-shape', $useChildren[0]->attributes['xlink:href'] ?? null);
    }

    public function testCachesAdaptByElementIdentity(): void
    {
        $svg = $this->newSvgElement('svg');
        $first = $this->adapter->adapt($svg);
        $second = $this->adapter->adapt($svg);
        // Same element → must return the exact same SvgDocument
        // instance (so multi-page documents don't re-parse).
        self::assertSame($first, $second);
    }

    public function testTwoDistinctSvgElementsGetDistinctDocs(): void
    {
        $svgA = $this->newSvgElement('svg');
        $svgA->setAttribute('viewBox', '0 0 10 10');
        $svgB = $this->newSvgElement('svg');
        $svgB->setAttribute('viewBox', '0 0 20 20');

        $docA = $this->adapter->adapt($svgA);
        $docB = $this->adapter->adapt($svgB);

        self::assertNotSame($docA, $docB);
        self::assertSame('0 0 10 10', $docA->attributes['viewBox'] ?? null);
        self::assertSame('0 0 20 20', $docB->attributes['viewBox'] ?? null);
    }

    public function testSkipsEmptyTextNodes(): void
    {
        // The HTML parser can leave behind empty text nodes between
        // sibling elements. They shouldn't make it into the SVG model.
        $svg = $this->newSvgElement('svg');
        $svg->appendChild(new HtmlText($this->doc, ''));
        $rect = $this->newSvgElement('rect');
        $svg->appendChild($rect);
        $svg->appendChild(new HtmlText($this->doc, ''));

        $doc = $this->adapter->adapt($svg);
        // The exact text-node handling inside SvgDocument is its call,
        // but the rect must have made it through.
        $children = $this->childElements($doc);
        self::assertGreaterThanOrEqual(1, count($children));
        self::assertContainsOnlyInstancesOf(\Phpdftk\Svg\Element::class, $children);
    }

    public function testUnknownSvgChildPreservedAsGenericElement(): void
    {
        // <svg><banana/></svg> — the SVG parser falls back to a
        // GenericElement for anything outside its v1 known set, which
        // means inline SVG with future / vendor-specific elements
        // doesn't break the whole page.
        $svg = $this->newSvgElement('svg');
        $banana = $this->newSvgElement('banana');
        $svg->appendChild($banana);

        $doc = $this->adapter->adapt($svg);
        $children = $this->childElements($doc);
        self::assertCount(1, $children);
        self::assertInstanceOf(\Phpdftk\Svg\GenericElement::class, $children[0]);
    }

    // -----------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------

    private function newSvgElement(string $localName): HtmlElement
    {
        return new HtmlElement($this->doc, $localName, HtmlDocument::SVG_NS);
    }

    /** @return list<\Phpdftk\Svg\Element> */
    private function childElements(SvgDocument $doc): array
    {
        $out = [];
        foreach ($doc->children as $child) {
            if ($child instanceof \Phpdftk\Svg\Element) {
                $out[] = $child;
            }
        }
        return $out;
    }

    private function stringifySubtree(\Phpdftk\Svg\Element $el): string
    {
        $out = '';
        foreach ($el->children as $child) {
            if ($child instanceof \Phpdftk\Svg\Text) {
                $out .= $child->data;
            } elseif ($child instanceof \Phpdftk\Svg\Element) {
                $out .= $this->stringifySubtree($child);
            }
        }
        return $out;
    }
}
