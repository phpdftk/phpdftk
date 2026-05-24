<?php

declare(strict_types=1);

namespace Phpdftk\Html\Tests\TreeConstruction;

use Phpdftk\Html\Dom\Document;
use Phpdftk\Html\Dom\Element;
use Phpdftk\Html\Parser;
use PHPUnit\Framework\TestCase;

/**
 * Coverage for foreign content insertion per WHATWG §13.2.6.5 — inline
 * SVG and MathML inside HTML documents.
 */
final class ForeignContentTest extends TestCase
{
    private function parse(string $html): Document
    {
        return (new Parser())->parseDocument($html);
    }

    public function testInlineSvgIsInSvgNamespace(): void
    {
        $html = '<!DOCTYPE html><body><svg><rect width="100" height="50"/></svg>';
        $doc = $this->parse($html);
        $svg = $doc->getElementsByTagName('svg')[0] ?? null;
        self::assertNotNull($svg);
        self::assertSame(Document::SVG_NS, $svg->namespaceURI);

        $rect = $svg->children()[0] ?? null;
        self::assertNotNull($rect);
        self::assertSame('rect', $rect->localName);
        self::assertSame(Document::SVG_NS, $rect->namespaceURI);
        self::assertSame('100', $rect->getAttribute('width'));
    }

    public function testSvgCaseCorrectionForLinearGradient(): void
    {
        // The tokenizer lower-cases tag names; SVG requires the canonical
        // camelCase form to be restored on insertion.
        $html = '<!DOCTYPE html><body><svg><lineargradient id="g1"/></svg>';
        $doc = $this->parse($html);
        $svg = $doc->getElementsByTagName('svg')[0] ?? null;
        self::assertNotNull($svg);
        $gradient = $svg->children()[0] ?? null;
        self::assertNotNull($gradient);
        self::assertSame('linearGradient', $gradient->localName);
        self::assertSame(Document::SVG_NS, $gradient->namespaceURI);
    }

    public function testSvgClipPathCaseCorrection(): void
    {
        $html = '<!DOCTYPE html><body><svg><clippath id="cp"><rect/></clippath></svg>';
        $doc = $this->parse($html);
        $svg = $doc->getElementsByTagName('svg')[0] ?? null;
        self::assertNotNull($svg);
        $clip = $svg->children()[0] ?? null;
        self::assertNotNull($clip);
        self::assertSame('clipPath', $clip->localName);
    }

    public function testNestedSvgElementsAllInSvgNamespace(): void
    {
        $html = '<!DOCTYPE html><body><svg><g><circle cx="10" cy="10" r="5"/></g></svg>';
        $doc = $this->parse($html);
        $svg = $doc->getElementsByTagName('svg')[0] ?? null;
        self::assertNotNull($svg);
        $g = $svg->children()[0] ?? null;
        self::assertNotNull($g);
        self::assertSame(Document::SVG_NS, $g->namespaceURI);
        $circle = $g->children()[0] ?? null;
        self::assertNotNull($circle);
        self::assertSame(Document::SVG_NS, $circle->namespaceURI);
        self::assertSame('5', $circle->getAttribute('r'));
    }

    public function testBreakoutToHtmlOnP(): void
    {
        // <svg>...<p> — <p> is a break-out tag; SVG is closed, <p> goes to InBody.
        $html = '<!DOCTYPE html><body><svg><g></g></svg><p>after</p>';
        $doc = $this->parse($html);
        $body = $doc->body;
        self::assertNotNull($body);
        $kids = $body->children();
        self::assertCount(2, $kids);
        self::assertSame('svg', $kids[0]->localName);
        self::assertSame(Document::SVG_NS, $kids[0]->namespaceURI);
        self::assertSame('p', $kids[1]->localName);
        self::assertSame(Document::HTML_NS, $kids[1]->namespaceURI);
    }

    public function testBreakoutInsideSvg(): void
    {
        // <svg><div> — <div> is a breakout tag; SVG pops, <div> goes to InBody as a sibling.
        $html = '<!DOCTYPE html><body><svg><div>html</div></svg>';
        $doc = $this->parse($html);
        $body = $doc->body;
        self::assertNotNull($body);
        $div = $body->getElementsByTagName('div')[0] ?? null;
        self::assertNotNull($div);
        self::assertSame(Document::HTML_NS, $div->namespaceURI);
        self::assertSame('html', $div->textContent());
    }

    public function testInlineMathmlIsInMathmlNamespace(): void
    {
        $html = '<!DOCTYPE html><body><math><mi>x</mi><mo>+</mo><mn>1</mn></math>';
        $doc = $this->parse($html);
        $math = $doc->getElementsByTagName('math')[0] ?? null;
        self::assertNotNull($math);
        self::assertSame(Document::MATHML_NS, $math->namespaceURI);
        foreach ($math->children() as $child) {
            self::assertSame(Document::MATHML_NS, $child->namespaceURI);
        }
    }

    public function testSvgEndTagPopsCorrectly(): void
    {
        // Properly nested SVG should close cleanly with the </svg> end tag.
        $html = '<!DOCTYPE html><body>before<svg><rect/></svg>after';
        $doc = $this->parse($html);
        $body = $doc->body;
        self::assertNotNull($body);
        // "before" and "after" should both be in body, with SVG between.
        self::assertStringContainsString('before', $body->textContent());
        self::assertStringContainsString('after', $body->textContent());
        // Only one SVG child.
        self::assertCount(1, $body->getElementsByTagName('svg'));
    }

    public function testInlineSvgInsideTableCell(): void
    {
        $html = '<!DOCTYPE html><body><table><tr><td><svg><rect width="50"/></svg></td></tr></table>';
        $doc = $this->parse($html);
        $svg = $doc->getElementsByTagName('svg')[0] ?? null;
        self::assertNotNull($svg);
        self::assertSame(Document::SVG_NS, $svg->namespaceURI);
        // SVG should be inside <td>.
        self::assertSame('td', $svg->parentNode?->localName);
    }
}
