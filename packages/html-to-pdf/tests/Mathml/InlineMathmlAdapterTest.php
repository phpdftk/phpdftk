<?php

declare(strict_types=1);

namespace Phpdftk\HtmlToPdf\Tests\Mathml;

use Phpdftk\Html\Dom\Document as HtmlDocument;
use Phpdftk\Html\Dom\Element as HtmlElement;
use Phpdftk\Html\Dom\Text as HtmlText;
use Phpdftk\HtmlToPdf\Mathml\InlineMathmlAdapter;
use Phpdftk\Mathml\MathmlDocument;
use Phpdftk\Mathml\Mn;
use Phpdftk\Mathml\Parser as MathmlParser;
use PHPUnit\Framework\TestCase;

/**
 * Unit coverage for the HTML DOM → MathmlDocument adapter. Sibling
 * to {@see \Phpdftk\HtmlToPdf\Tests\Svg\InlineSvgAdapterTest} —
 * both adapters share the {@see \Phpdftk\HtmlToPdf\ForeignContent\
 * DomXmlSerializer} so a passing adapter test on one engine plus the
 * serializer's own tests is enough coverage for the shared walk.
 * What this file adds is MathML-specific: the namespace check, the
 * `<math>` localName check, and the cache identity behaviour against
 * MathmlDocument objects.
 */
final class InlineMathmlAdapterTest extends TestCase
{
    private InlineMathmlAdapter $adapter;
    private HtmlDocument $doc;

    protected function setUp(): void
    {
        $this->adapter = new InlineMathmlAdapter();
        $this->doc = new HtmlDocument();
    }

    // -----------------------------------------------------------------
    // Negative cases
    // -----------------------------------------------------------------

    public function testRejectsNonMathLocalName(): void
    {
        $svg = new HtmlElement($this->doc, 'svg', HtmlDocument::MATHML_NS);
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/<math>/');
        $this->adapter->adapt($svg);
    }

    public function testRejectsMathInWrongNamespace(): void
    {
        // A <math> element somehow tagged with the SVG namespace —
        // shouldn't happen in normal HTML foreign-content flow, but
        // explicit rejection catches a future regression.
        $math = new HtmlElement($this->doc, 'math', HtmlDocument::SVG_NS);
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/namespace/');
        $this->adapter->adapt($math);
    }

    public function testRejectsMathInHtmlNamespace(): void
    {
        $math = new HtmlElement($this->doc, 'math', HtmlDocument::HTML_NS);
        $this->expectException(\InvalidArgumentException::class);
        $this->adapter->adapt($math);
    }

    public function testRejectsWrongLocalNameInMathmlNamespace(): void
    {
        // <mrow> isn't a valid root — only <math> is.
        $mrow = new HtmlElement($this->doc, 'mrow', HtmlDocument::MATHML_NS);
        $this->expectException(\InvalidArgumentException::class);
        $this->adapter->adapt($mrow);
    }

    // -----------------------------------------------------------------
    // Positive cases
    // -----------------------------------------------------------------

    public function testAdaptsEmptyMathRoot(): void
    {
        $math = $this->newMathmlElement('math');
        $doc = $this->adapter->adapt($math);
        self::assertInstanceOf(MathmlDocument::class, $doc);
    }

    public function testAdaptsMathWithMnChild(): void
    {
        // Issue #30's tracer-bullet: <math><mn>2</mn></math>.
        $math = $this->newMathmlElement('math');
        $mn = $this->newMathmlElement('mn');
        $mn->appendChild(new HtmlText($this->doc, '2'));
        $math->appendChild($mn);

        $doc = $this->adapter->adapt($math);

        $children = $this->childElements($doc);
        self::assertCount(1, $children);
        self::assertInstanceOf(Mn::class, $children[0]);
        self::assertSame('2', $children[0]->textContent());
    }

    public function testCachesByElementIdentity(): void
    {
        $math = $this->newMathmlElement('math');
        $first = $this->adapter->adapt($math);
        $second = $this->adapter->adapt($math);
        self::assertSame($first, $second);
    }

    public function testDistinctElementsGetDistinctDocs(): void
    {
        $a = $this->newMathmlElement('math');
        $a->setAttribute('display', 'block');
        $b = $this->newMathmlElement('math');
        $b->setAttribute('display', 'inline');

        $docA = $this->adapter->adapt($a);
        $docB = $this->adapter->adapt($b);
        self::assertNotSame($docA, $docB);
        self::assertSame('block', $docA->display());
        self::assertSame('inline', $docB->display());
    }

    public function testPreservesAttributesOnRoot(): void
    {
        $math = $this->newMathmlElement('math');
        $math->setAttribute('display', 'block');
        $doc = $this->adapter->adapt($math);
        self::assertSame('block', $doc->display());
    }

    public function testRoundTripsAllTokenTypes(): void
    {
        // mn + mo + mi + ms + mtext — confirm the serializer's text
        // preservation works for every token element.
        $math = $this->newMathmlElement('math');
        $mrow = $this->newMathmlElement('mrow');
        foreach (['mn' => '2', 'mo' => '+', 'mi' => 'x', 'ms' => 's', 'mtext' => 'm'] as $tag => $text) {
            $el = $this->newMathmlElement($tag);
            $el->appendChild(new HtmlText($this->doc, $text));
            $mrow->appendChild($el);
        }
        $math->appendChild($mrow);

        $doc = $this->adapter->adapt($math);
        // Text-content flattening walks the whole tree.
        self::assertSame('2+xsm', $doc->textContent());
    }

    public function testAcceptsAnAlreadyParsedMathmlParser(): void
    {
        // Sanity check that construction with an explicit parser
        // works — used by callers wanting to share a parser instance
        // across adapters for memory pressure reasons.
        $adapter = new InlineMathmlAdapter(new MathmlParser());
        $math = $this->newMathmlElement('math');
        self::assertInstanceOf(MathmlDocument::class, $adapter->adapt($math));
    }

    // -----------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------

    private function newMathmlElement(string $localName): HtmlElement
    {
        return new HtmlElement($this->doc, $localName, HtmlDocument::MATHML_NS);
    }

    /** @return list<\Phpdftk\Mathml\Element> */
    private function childElements(MathmlDocument $doc): array
    {
        $out = [];
        foreach ($doc->children as $child) {
            if ($child instanceof \Phpdftk\Mathml\Element) {
                $out[] = $child;
            }
        }
        return $out;
    }
}
