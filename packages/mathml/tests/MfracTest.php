<?php

declare(strict_types=1);

namespace Phpdftk\Mathml\Tests;

use Phpdftk\Mathml\Element;
use Phpdftk\Mathml\Mfrac;
use Phpdftk\Mathml\Mn;
use Phpdftk\Mathml\Parser;
use PHPUnit\Framework\TestCase;

/**
 * Coverage for the typed `<mfrac>` element + its attribute accessors.
 * Negative-first: every malformed attribute value must fall back to
 * null so the painter applies the spec default.
 */
final class MfracTest extends TestCase
{
    private Parser $parser;

    protected function setUp(): void
    {
        $this->parser = new Parser();
    }

    public function testParsesAsMfracNotGeneric(): void
    {
        $doc = $this->parser->parse(
            '<math xmlns="http://www.w3.org/1998/Math/MathML">'
                . '<mfrac><mn>1</mn><mn>2</mn></mfrac>'
                . '</math>',
        );
        $mfrac = $this->firstElement($doc->children);
        self::assertInstanceOf(Mfrac::class, $mfrac);
    }

    public function testPreservesTwoChildrenInDocumentOrder(): void
    {
        $doc = $this->parser->parse(
            '<math xmlns="http://www.w3.org/1998/Math/MathML">'
                . '<mfrac><mn>3</mn><mn>4</mn></mfrac>'
                . '</math>',
        );
        $mfrac = $this->firstElement($doc->children);
        $children = array_values(array_filter(
            $mfrac->children,
            static fn($c) => $c instanceof Element,
        ));
        self::assertCount(2, $children);
        self::assertInstanceOf(Mn::class, $children[0]);
        self::assertSame('3', $children[0]->textContent());
        self::assertInstanceOf(Mn::class, $children[1]);
        self::assertSame('4', $children[1]->textContent());
    }

    // -----------------------------------------------------------------
    // linethickness attribute
    // -----------------------------------------------------------------

    public function testLinethicknessAbsentReturnsNull(): void
    {
        $mfrac = new Mfrac();
        self::assertNull($mfrac->linethickness());
    }

    public function testLinethicknessEmptyReturnsNull(): void
    {
        $mfrac = new Mfrac();
        $mfrac->setAttribute('linethickness', '');
        self::assertNull($mfrac->linethickness());
    }

    public function testLinethicknessNonNumericReturnsNull(): void
    {
        $mfrac = new Mfrac();
        $mfrac->setAttribute('linethickness', 'thick');
        self::assertNull($mfrac->linethickness());
    }

    public function testLinethicknessNegativeReturnsNull(): void
    {
        $mfrac = new Mfrac();
        $mfrac->setAttribute('linethickness', '-1');
        self::assertNull($mfrac->linethickness());
    }

    public function testLinethicknessZeroIsValid(): void
    {
        // linethickness="0" is the binomial coefficient form — the
        // spec allows it explicitly, must not collapse to null.
        $mfrac = new Mfrac();
        $mfrac->setAttribute('linethickness', '0');
        self::assertSame(0.0, $mfrac->linethickness());
    }

    public function testLinethicknessPositiveReturnsValue(): void
    {
        $mfrac = new Mfrac();
        $mfrac->setAttribute('linethickness', '2.5');
        self::assertSame(2.5, $mfrac->linethickness());
    }

    // -----------------------------------------------------------------
    // displaystyle attribute
    // -----------------------------------------------------------------

    public function testDisplaystyleAbsentReturnsNull(): void
    {
        $mfrac = new Mfrac();
        self::assertNull($mfrac->displaystyle());
    }

    public function testDisplaystyleTrue(): void
    {
        $mfrac = new Mfrac();
        $mfrac->setAttribute('displaystyle', 'true');
        self::assertTrue($mfrac->displaystyle());
    }

    public function testDisplaystyleFalse(): void
    {
        $mfrac = new Mfrac();
        $mfrac->setAttribute('displaystyle', 'false');
        self::assertFalse($mfrac->displaystyle());
    }

    public function testDisplaystyleUnknownValueReturnsNull(): void
    {
        $mfrac = new Mfrac();
        $mfrac->setAttribute('displaystyle', 'maybe');
        self::assertNull($mfrac->displaystyle());
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
