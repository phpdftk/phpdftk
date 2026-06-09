<?php

declare(strict_types=1);

namespace Phpdftk\Mathml\Tests;

use Phpdftk\Mathml\Element;
use Phpdftk\Mathml\Mn;
use Phpdftk\Mathml\Mo;
use Phpdftk\Mathml\Mroot;
use Phpdftk\Mathml\Msqrt;
use Phpdftk\Mathml\Parser;
use PHPUnit\Framework\TestCase;

/**
 * Coverage for the radical elements `<msqrt>` and `<mroot>`. Both
 * are container elements so the parser test surface is small —
 * confirm they round-trip as their typed classes (not GenericElement)
 * and that the parser doesn't constrain child counts (the renderer
 * handles invalid arity).
 */
final class MsqrtTest extends TestCase
{
    private Parser $parser;

    protected function setUp(): void
    {
        $this->parser = new Parser();
    }

    public function testParsesMsqrtAsTypedNotGeneric(): void
    {
        $doc = $this->parser->parse(
            '<math xmlns="http://www.w3.org/1998/Math/MathML">'
                . '<msqrt><mn>2</mn></msqrt>'
                . '</math>',
        );
        $msqrt = $this->firstElement($doc->children);
        self::assertInstanceOf(Msqrt::class, $msqrt);
    }

    public function testMsqrtPreservesChildrenInOrder(): void
    {
        $doc = $this->parser->parse(
            '<math xmlns="http://www.w3.org/1998/Math/MathML">'
                . '<msqrt><mn>1</mn><mo>+</mo><mn>2</mn></msqrt>'
                . '</math>',
        );
        $msqrt = $this->firstElement($doc->children);
        $kids = array_values(array_filter(
            $msqrt->children,
            static fn($c) => $c instanceof Element,
        ));
        self::assertCount(3, $kids);
        self::assertInstanceOf(Mn::class, $kids[0]);
        self::assertInstanceOf(Mo::class, $kids[1]);
        self::assertInstanceOf(Mn::class, $kids[2]);
    }

    public function testEmptyMsqrtParsesCleanly(): void
    {
        // Author error (msqrt should have content) but the parser
        // doesn't validate arity — that's the renderer's call.
        $doc = $this->parser->parse(
            '<math xmlns="http://www.w3.org/1998/Math/MathML">'
                . '<msqrt/></math>',
        );
        $msqrt = $this->firstElement($doc->children);
        self::assertInstanceOf(Msqrt::class, $msqrt);
        self::assertCount(0, $msqrt->children);
    }

    public function testParsesMrootAsTypedNotGeneric(): void
    {
        $doc = $this->parser->parse(
            '<math xmlns="http://www.w3.org/1998/Math/MathML">'
                . '<mroot><mn>8</mn><mn>3</mn></mroot>'
                . '</math>',
        );
        $mroot = $this->firstElement($doc->children);
        self::assertInstanceOf(Mroot::class, $mroot);
    }

    public function testMrootPreservesBaseAndIndexInOrder(): void
    {
        $doc = $this->parser->parse(
            '<math xmlns="http://www.w3.org/1998/Math/MathML">'
                . '<mroot><mn>8</mn><mn>3</mn></mroot>'
                . '</math>',
        );
        $mroot = $this->firstElement($doc->children);
        $kids = array_values(array_filter(
            $mroot->children,
            static fn($c) => $c instanceof Element,
        ));
        self::assertCount(2, $kids);
        // Per Core: base first, index second.
        self::assertInstanceOf(Mn::class, $kids[0]);
        self::assertSame('8', $kids[0]->textContent());
        self::assertInstanceOf(Mn::class, $kids[1]);
        self::assertSame('3', $kids[1]->textContent());
    }

    public function testMrootWithWrongArityStillParses(): void
    {
        // Renderer falls back on bad arity; parser doesn't.
        $doc = $this->parser->parse(
            '<math xmlns="http://www.w3.org/1998/Math/MathML">'
                . '<mroot><mn>1</mn></mroot>'
                . '</math>',
        );
        $mroot = $this->firstElement($doc->children);
        self::assertInstanceOf(Mroot::class, $mroot);
        $kids = array_values(array_filter(
            $mroot->children,
            static fn($c) => $c instanceof Element,
        ));
        self::assertCount(1, $kids);
    }

    public function testNestedRadicalsRoundTrip(): void
    {
        // <msqrt><mroot><mn>2</mn><mn>3</mn></mroot></msqrt> —
        // root inside a square root. Parser preserves the typed
        // identity at every level.
        $doc = $this->parser->parse(
            '<math xmlns="http://www.w3.org/1998/Math/MathML">'
                . '<msqrt><mroot><mn>2</mn><mn>3</mn></mroot></msqrt>'
                . '</math>',
        );
        $msqrt = $this->firstElement($doc->children);
        self::assertInstanceOf(Msqrt::class, $msqrt);
        $inner = $this->firstElement($msqrt->children);
        self::assertInstanceOf(Mroot::class, $inner);
    }

    /**
     * @param list<\Phpdftk\Mathml\Node> $nodes
     */
    private function firstElement(array $nodes): Element
    {
        foreach ($nodes as $n) {
            if ($n instanceof Element) {
                return $n;
            }
        }
        self::fail('No element child found.');
    }
}
