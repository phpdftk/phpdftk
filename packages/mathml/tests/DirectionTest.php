<?php

declare(strict_types=1);

namespace Phpdftk\Mathml\Tests;

use Phpdftk\Mathml\Element;
use Phpdftk\Mathml\Parser;
use PHPUnit\Framework\TestCase;

/**
 * Parser-layer coverage for the generic `dir` attribute on
 * MathML elements per Core §3.1.5.4.
 *
 * The accessor lives on the base {@see Element} so any element type
 * can expose its directionality - the painter consults it when
 * descending into a subtree.
 */
final class DirectionTest extends TestCase
{
    private Parser $parser;

    protected function setUp(): void
    {
        $this->parser = new Parser();
    }

    public function testDirAttributeOnMathRootRecognised(): void
    {
        $doc = $this->parser->parse(
            '<math xmlns="http://www.w3.org/1998/Math/MathML" dir="rtl">'
                . '<mi>x</mi>'
                . '</math>',
        );
        self::assertSame('rtl', $doc->dir());
    }

    public function testDirAttributeOnInnerElementRecognised(): void
    {
        $doc = $this->parser->parse(
            '<math xmlns="http://www.w3.org/1998/Math/MathML">'
                . '<mrow dir="rtl"><mi>x</mi></mrow>'
                . '</math>',
        );
        $row = $this->firstElement($doc->children);
        self::assertSame('rtl', $row->dir());
    }

    public function testDirAttributeCaseFolded(): void
    {
        $doc = $this->parser->parse(
            '<math xmlns="http://www.w3.org/1998/Math/MathML" dir="RTL">'
                . '<mi>x</mi></math>',
        );
        self::assertSame('rtl', $doc->dir());
    }

    public function testDirAttributeAbsentReturnsNull(): void
    {
        $doc = $this->parser->parse(
            '<math xmlns="http://www.w3.org/1998/Math/MathML">'
                . '<mi>x</mi></math>',
        );
        self::assertNull($doc->dir());
    }

    public function testDirAttributeInvalidValueReturnsNull(): void
    {
        // Garbage isn't `ltr` or `rtl` - accessor returns null so
        // the painter falls back to inheritance.
        $doc = $this->parser->parse(
            '<math xmlns="http://www.w3.org/1998/Math/MathML" dir="auto">'
                . '<mi>x</mi></math>',
        );
        self::assertNull($doc->dir());
    }

    public function testDirAttributeLtrExplicitlyReturnsLtr(): void
    {
        // Explicit `ltr` is meaningful when an outer ancestor was
        // RTL - it pins this subtree back to LTR.
        $doc = $this->parser->parse(
            '<math xmlns="http://www.w3.org/1998/Math/MathML" dir="ltr">'
                . '<mi>x</mi></math>',
        );
        self::assertSame('ltr', $doc->dir());
    }

    // ---------------------------------------------------------------
    // MathML Core §3.1.5.4 — `dir` is a presentational hint mapping
    // onto the CSS `direction` property, so the property is the
    // authority. direction-010 renders `dir="rtl"` and matches a
    // reference written as `style="direction: rtl"`.
    // ---------------------------------------------------------------

    public function testCssDirectionOnTheRootIsRecognised(): void
    {
        $doc = $this->parser->parse(
            '<math xmlns="http://www.w3.org/1998/Math/MathML" style="direction: rtl">'
                . '<mi>x</mi></math>',
        );
        self::assertSame('rtl', $doc->dir());
    }

    public function testCssDirectionOnAnInnerElementIsRecognised(): void
    {
        $doc = $this->parser->parse(
            '<math xmlns="http://www.w3.org/1998/Math/MathML">'
                . '<mrow style="direction: rtl"><mi>x</mi></mrow></math>',
        );
        self::assertSame('rtl', $this->firstElement($doc->children)->dir());
    }

    public function testCssDirectionOutranksTheAttributeHint(): void
    {
        $doc = $this->parser->parse(
            '<math xmlns="http://www.w3.org/1998/Math/MathML"'
                . ' dir="ltr" style="direction: rtl"><mi>x</mi></math>',
        );
        self::assertSame('rtl', $doc->dir());
    }

    public function testCssDirectionIsCaseFoldedAndTrimmed(): void
    {
        $doc = $this->parser->parse(
            '<math xmlns="http://www.w3.org/1998/Math/MathML" style="direction:  RTL  ">'
                . '<mi>x</mi></math>',
        );
        self::assertSame('rtl', $doc->dir());
    }

    /**
     * Negative guard: an unrelated declaration must not be read as a
     * direction, and the attribute must still work when the style
     * carries other properties.
     */
    public function testAnUnrelatedStyleDeclarationDoesNotSetDirection(): void
    {
        $doc = $this->parser->parse(
            '<math xmlns="http://www.w3.org/1998/Math/MathML" style="color: red">'
                . '<mi>x</mi></math>',
        );
        self::assertNull($doc->dir());
    }

    public function testTheAttributeStillAppliesAlongsideOtherStyleDeclarations(): void
    {
        $doc = $this->parser->parse(
            '<math xmlns="http://www.w3.org/1998/Math/MathML"'
                . ' dir="rtl" style="color: red"><mi>x</mi></math>',
        );
        self::assertSame('rtl', $doc->dir());
    }

    public function testAnInvalidCssDirectionFallsBackToNull(): void
    {
        $doc = $this->parser->parse(
            '<math xmlns="http://www.w3.org/1998/Math/MathML" style="direction: sideways">'
                . '<mi>x</mi></math>',
        );
        self::assertNull($doc->dir());
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
