<?php

declare(strict_types=1);

namespace Phpdftk\Mathml\Tests;

use Phpdftk\Mathml\Exception\InvalidMathmlException;
use Phpdftk\Mathml\GenericElement;
use Phpdftk\Mathml\MathmlDocument;
use Phpdftk\Mathml\Mi;
use Phpdftk\Mathml\Mn;
use Phpdftk\Mathml\Mo;
use Phpdftk\Mathml\Mrow;
use Phpdftk\Mathml\Ms;
use Phpdftk\Mathml\Mtext;
use Phpdftk\Mathml\Parser;
use Phpdftk\Mathml\Text;
use PHPUnit\Framework\TestCase;

/**
 * Boundary coverage for {@see Parser}. The parser is the trust
 * boundary between author markup and the typed model the painter
 * consumes — every rejection path gets explicit coverage before the
 * positive paths.
 *
 * Cases bias toward negative inputs: malformed XML, wrong root,
 * cross-namespace roots, missing root, XXE attempts. The positive
 * suite then confirms the round-trip for each token class plus the
 * `<mrow>` container.
 */
final class ParserTest extends TestCase
{
    private Parser $parser;

    protected function setUp(): void
    {
        $this->parser = new Parser();
    }

    // -----------------------------------------------------------------
    // Negative cases
    // -----------------------------------------------------------------

    public function testRejectsEmptyInput(): void
    {
        $this->expectException(InvalidMathmlException::class);
        $this->expectExceptionMessageMatches('/empty/');
        $this->parser->parse('');
    }

    public function testRejectsWhitespaceOnlyInput(): void
    {
        $this->expectException(InvalidMathmlException::class);
        $this->parser->parse("   \n\t  ");
    }

    public function testRejectsMalformedXml(): void
    {
        $this->expectException(InvalidMathmlException::class);
        $this->expectExceptionMessageMatches('/parse MathML XML/');
        $this->parser->parse('<math><mn>2</mn>');
    }

    public function testRejectsNonMathRoot(): void
    {
        $this->expectException(InvalidMathmlException::class);
        $this->expectExceptionMessageMatches('/<math> root/');
        $this->parser->parse(
            '<not-math xmlns="http://www.w3.org/1998/Math/MathML"/>',
        );
    }

    public function testRejectsRootInWrongNamespace(): void
    {
        $this->expectException(InvalidMathmlException::class);
        $this->expectExceptionMessageMatches('/unexpected namespace/');
        $this->parser->parse(
            '<math xmlns="http://www.w3.org/2000/svg"><mn>2</mn></math>',
        );
    }

    public function testIgnoresExternalEntities(): void
    {
        // XXE attempt: declare an external entity referencing a local
        // file, then use it inside the math. The parser MUST treat
        // the reference as inert (the parsed Mn carries the literal
        // `&xxe;` text, not the file contents).
        $xml = <<<XML
        <?xml version="1.0"?>
        <!DOCTYPE math [ <!ENTITY xxe SYSTEM "file:///etc/hostname"> ]>
        <math xmlns="http://www.w3.org/1998/Math/MathML">
          <mn>&xxe;</mn>
        </math>
        XML;
        $doc = $this->parser->parse($xml);
        $mn = $this->firstElement($doc->children);
        self::assertInstanceOf(Mn::class, $mn);
        // The text content must not contain anything that looks like
        // the contents of /etc/hostname — typically alphanumerics. We
        // assert the substituted content is empty (entity stripped)
        // or remains as `&xxe;` (entity preserved literally).
        $text = $mn->textContent();
        self::assertSame(
            '',
            $text,
            "XXE entity should not be substituted; got literal: $text",
        );
    }

    public function testUnknownElementBecomesGeneric(): void
    {
        // <mblah> isn't in the v1 typed set — should round-trip
        // through GenericElement rather than throwing.
        $xml = '<math xmlns="http://www.w3.org/1998/Math/MathML">'
            . '<mblah/></math>';
        $doc = $this->parser->parse($xml);
        $child = $this->firstElement($doc->children);
        self::assertInstanceOf(GenericElement::class, $child);
        self::assertSame('mblah', $child->localName);
    }

    public function testRejectsXIncludeAtRoot(): void
    {
        // XInclude reference at root — we don't call xinclude(), so
        // the include element is preserved literally. The root is
        // still <math>, so we don't expect a parse failure; we DO
        // expect the xi:include element to come through as a generic.
        $xml = <<<XML
        <math xmlns="http://www.w3.org/1998/Math/MathML"
              xmlns:xi="http://www.w3.org/2001/XInclude">
          <xi:include href="file:///etc/hostname"/>
        </math>
        XML;
        $doc = $this->parser->parse($xml);
        $child = $this->firstElement($doc->children);
        self::assertInstanceOf(GenericElement::class, $child);
        self::assertSame('include', $child->localName);
    }

    public function testRejectsMissingRoot(): void
    {
        // Comment-only XML has no root element.
        $this->expectException(InvalidMathmlException::class);
        $this->parser->parse("<?xml version='1.0'?><!-- just a comment -->");
    }

    // -----------------------------------------------------------------
    // Positive cases — token + container round-trip
    // -----------------------------------------------------------------

    public function testParsesEmptyMathRoot(): void
    {
        $doc = $this->parser->parse(
            '<math xmlns="http://www.w3.org/1998/Math/MathML"/>',
        );
        self::assertInstanceOf(MathmlDocument::class, $doc);
        self::assertSame('math', $doc->localName);
        self::assertCount(0, $doc->children);
    }

    public function testParsesEachTokenElementToItsTypedClass(): void
    {
        $xml = <<<XML
        <math xmlns="http://www.w3.org/1998/Math/MathML">
          <mrow>
            <mn>2</mn>
            <mo>+</mo>
            <mi>x</mi>
            <ms>str</ms>
            <mtext>where</mtext>
          </mrow>
        </math>
        XML;
        $doc = $this->parser->parse($xml);

        $mrow = $this->firstElement($doc->children);
        self::assertInstanceOf(Mrow::class, $mrow);

        $tokens = array_values(array_filter(
            $mrow->children,
            static fn($n) => $n instanceof \Phpdftk\Mathml\Element,
        ));
        self::assertCount(5, $tokens);
        self::assertInstanceOf(Mn::class, $tokens[0]);
        self::assertSame('2', $tokens[0]->textContent());
        self::assertInstanceOf(Mo::class, $tokens[1]);
        self::assertSame('+', $tokens[1]->textContent());
        self::assertInstanceOf(Mi::class, $tokens[2]);
        self::assertSame('x', $tokens[2]->textContent());
        self::assertInstanceOf(Ms::class, $tokens[3]);
        self::assertSame('str', $tokens[3]->textContent());
        self::assertInstanceOf(Mtext::class, $tokens[4]);
        self::assertSame('where', $tokens[4]->textContent());
    }

    public function testPreservesAttributesAndCasing(): void
    {
        // MathML keeps camelCase / case-sensitive attribute names.
        // The parser must round-trip `mathvariant`, `displaystyle`,
        // etc. exactly as the author wrote them.
        $doc = $this->parser->parse(
            '<math xmlns="http://www.w3.org/1998/Math/MathML" display="block">'
                . '<mi mathvariant="bold-italic">y</mi>'
                . '</math>',
        );
        self::assertSame('block', $doc->display());
        $mi = $this->firstElement($doc->children);
        self::assertInstanceOf(Mi::class, $mi);
        self::assertSame('bold-italic', $mi->mathvariant());
    }

    public function testTextNodesArePreservedVerbatim(): void
    {
        // Token elements preserve whitespace exactly — `<mn> 2 </mn>`
        // and `<mn>2</mn>` are distinct documents at the parser
        // layer (the painter may decide later whether to collapse).
        $doc = $this->parser->parse(
            '<math xmlns="http://www.w3.org/1998/Math/MathML">'
                . '<mn> 2 </mn></math>',
        );
        $mn = $this->firstElement($doc->children);
        self::assertSame(' 2 ', $mn->textContent());
    }

    public function testMoFormAttributeIsTyped(): void
    {
        $doc = $this->parser->parse(
            '<math xmlns="http://www.w3.org/1998/Math/MathML">'
                . '<mo form="infix">+</mo>'
                . '<mo form="bogus">?</mo>'
                . '<mo>=</mo>'
                . '</math>',
        );
        $children = array_values(array_filter(
            $doc->children,
            static fn($n) => $n instanceof \Phpdftk\Mathml\Element,
        ));
        self::assertSame('infix', $children[0]->form());
        // Unknown values → null so the painter applies position-based
        // heuristics rather than honoring nonsense.
        self::assertNull($children[1]->form());
        // Absent → null.
        self::assertNull($children[2]->form());
    }

    public function testMsLquoteRquoteFallbackToAsciiDoubleQuote(): void
    {
        $doc = $this->parser->parse(
            '<math xmlns="http://www.w3.org/1998/Math/MathML">'
                . '<ms>plain</ms>'
                . '<ms lquote="«" rquote="»">euro</ms>'
                . '</math>',
        );
        $children = array_values(array_filter(
            $doc->children,
            static fn($n) => $n instanceof \Phpdftk\Mathml\Element,
        ));
        self::assertSame('"', $children[0]->lquote());
        self::assertSame('"', $children[0]->rquote());
        self::assertSame('«', $children[1]->lquote());
        self::assertSame('»', $children[1]->rquote());
    }

    public function testTextContentFlattensNestedTokens(): void
    {
        $doc = $this->parser->parse(
            '<math xmlns="http://www.w3.org/1998/Math/MathML">'
                . '<mrow><mn>2</mn><mo>+</mo><mi>x</mi></mrow>'
                . '</math>',
        );
        // <math>'s textContent walks the whole tree.
        self::assertSame('2+x', $doc->textContent());
    }

    /**
     * @param list<\Phpdftk\Mathml\Node> $nodes
     */
    private function firstElement(array $nodes): \Phpdftk\Mathml\Element
    {
        foreach ($nodes as $n) {
            if ($n instanceof \Phpdftk\Mathml\Element) {
                return $n;
            }
        }
        self::fail('No element child found.');
    }
}
