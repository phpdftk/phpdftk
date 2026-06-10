<?php

declare(strict_types=1);

namespace Phpdftk\Mathml\Tests;

use Phpdftk\Mathml\Element;
use Phpdftk\Mathml\Mi;
use Phpdftk\Mathml\Mpadded;
use Phpdftk\Mathml\Mphantom;
use Phpdftk\Mathml\Mspace;
use Phpdftk\Mathml\Parser;
use PHPUnit\Framework\TestCase;

/**
 * Parser-layer coverage for the spacing primitives:
 * `<mspace>`, `<mpadded>`, `<mphantom>`.
 *
 * Also exercises the CSS-length parsing on `<mspace>` and `<mpadded>`
 * since those typed accessors are the contract the painter relies on.
 */
final class SpacingTest extends TestCase
{
    private Parser $parser;

    protected function setUp(): void
    {
        $this->parser = new Parser();
    }

    public function testParsesMspaceAsTyped(): void
    {
        $doc = $this->parser->parse(
            '<math xmlns="http://www.w3.org/1998/Math/MathML">'
                . '<mspace width="1em"/>'
                . '</math>',
        );
        $el = $this->firstElement($doc->children);
        self::assertInstanceOf(Mspace::class, $el);
    }

    public function testParsesMpaddedAsTyped(): void
    {
        $doc = $this->parser->parse(
            '<math xmlns="http://www.w3.org/1998/Math/MathML">'
                . '<mpadded width="3em"><mi>x</mi></mpadded>'
                . '</math>',
        );
        $el = $this->firstElement($doc->children);
        self::assertInstanceOf(Mpadded::class, $el);
        // Children preserve typed identity.
        $child = $this->firstChildElement($el);
        self::assertInstanceOf(Mi::class, $child);
    }

    public function testParsesMphantomAsTyped(): void
    {
        $doc = $this->parser->parse(
            '<math xmlns="http://www.w3.org/1998/Math/MathML">'
                . '<mphantom><mi>x</mi></mphantom>'
                . '</math>',
        );
        $el = $this->firstElement($doc->children);
        self::assertInstanceOf(Mphantom::class, $el);
    }

    public function testMspaceWidthReturnsEmsForVariousUnits(): void
    {
        $mspace = $this->parseMspace('1em');
        self::assertSame(1.0, $mspace->widthEm());

        $mspace = $this->parseMspace('2');  // unitless = em
        self::assertSame(2.0, $mspace->widthEm());

        $mspace = $this->parseMspace('1ex');
        self::assertSame(0.5, $mspace->widthEm());

        $mspace = $this->parseMspace('16px');
        self::assertSame(1.0, $mspace->widthEm());

        $mspace = $this->parseMspace('12pt');
        self::assertSame(1.0, $mspace->widthEm());
    }

    public function testMspaceWidthAcceptsNegative(): void
    {
        $mspace = $this->parseMspace('-0.5em');
        self::assertSame(-0.5, $mspace->widthEm());
    }

    public function testMspaceWidthReturnsNullWhenAbsentOrUnparseable(): void
    {
        // No width attribute at all.
        $doc = $this->parser->parse(
            '<math xmlns="http://www.w3.org/1998/Math/MathML"><mspace/></math>',
        );
        $first = $this->firstElement($doc->children);
        self::assertInstanceOf(Mspace::class, $first);
        self::assertNull($first->widthEm());

        // Junk value.
        $mspace = $this->parseMspace('not-a-length');
        self::assertNull($mspace->widthEm());

        // Absolute lengths the painter has no DPI context for.
        $mspace = $this->parseMspace('1in');
        self::assertNull($mspace->widthEm());
    }

    public function testMpaddedExposesVoffset(): void
    {
        $doc = $this->parser->parse(
            '<math xmlns="http://www.w3.org/1998/Math/MathML">'
                . '<mpadded voffset="0.3em"><mi>x</mi></mpadded>'
                . '</math>',
        );
        $el = $this->firstElement($doc->children);
        self::assertInstanceOf(Mpadded::class, $el);
        self::assertSame(0.3, $el->voffsetEm());
    }

    public function testMpaddedVoffsetSupportsNegativeAndPx(): void
    {
        $doc = $this->parser->parse(
            '<math xmlns="http://www.w3.org/1998/Math/MathML">'
                . '<mpadded voffset="-16px"><mi>x</mi></mpadded>'
                . '</math>',
        );
        $el = $this->firstElement($doc->children);
        self::assertInstanceOf(Mpadded::class, $el);
        // 16px = 1em (CSS px / 16). Negative drops the content.
        self::assertSame(-1.0, $el->voffsetEm());
    }

    public function testMpaddedVoffsetAbsentReturnsNull(): void
    {
        $doc = $this->parser->parse(
            '<math xmlns="http://www.w3.org/1998/Math/MathML">'
                . '<mpadded><mi>x</mi></mpadded>'
                . '</math>',
        );
        $el = $this->firstElement($doc->children);
        self::assertInstanceOf(Mpadded::class, $el);
        self::assertNull($el->voffsetEm());
    }

    public function testMpaddedExposesLspaceAndWidth(): void
    {
        $doc = $this->parser->parse(
            '<math xmlns="http://www.w3.org/1998/Math/MathML">'
                . '<mpadded width="4em" lspace="0.5em"><mi>x</mi></mpadded>'
                . '</math>',
        );
        $el = $this->firstElement($doc->children);
        self::assertInstanceOf(Mpadded::class, $el);
        self::assertSame(4.0, $el->widthEm());
        self::assertSame(0.5, $el->lspaceEm());
    }

    private function parseMspace(string $widthAttr): Mspace
    {
        $doc = $this->parser->parse(
            '<math xmlns="http://www.w3.org/1998/Math/MathML">'
                . '<mspace width="' . $widthAttr . '"/>'
                . '</math>',
        );
        $el = $this->firstElement($doc->children);
        self::assertInstanceOf(Mspace::class, $el);
        return $el;
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

    private function firstChildElement(Element $parent): Element
    {
        return $this->firstElement($parent->children);
    }
}
