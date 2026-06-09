<?php

declare(strict_types=1);

namespace Phpdftk\Mathml\Tests;

use Phpdftk\Mathml\Element;
use Phpdftk\Mathml\Mi;
use Phpdftk\Mathml\Mmultiscripts;
use Phpdftk\Mathml\Mn;
use Phpdftk\Mathml\Mprescripts;
use Phpdftk\Mathml\NoneElement;
use Phpdftk\Mathml\Parser;
use PHPUnit\Framework\TestCase;

/**
 * Parser-layer coverage for the multi-script triumvirate:
 * `<mmultiscripts>`, `<mprescripts/>`, `<none/>`.
 *
 * All three need typed identity so the painter can:
 *   - dispatch `<mmultiscripts>` to the right paint method,
 *   - scan its children for the `<mprescripts/>` boundary,
 *   - recognise `<none/>` as an empty script-slot placeholder.
 */
final class MmultiscriptsTest extends TestCase
{
    private Parser $parser;

    protected function setUp(): void
    {
        $this->parser = new Parser();
    }

    public function testParsesMmultiscriptsAsTyped(): void
    {
        $doc = $this->parser->parse(
            '<math xmlns="http://www.w3.org/1998/Math/MathML">'
                . '<mmultiscripts>'
                . '<mi>X</mi>'
                . '<mn>1</mn><mn>2</mn>'
                . '</mmultiscripts>'
                . '</math>',
        );
        $mu = $this->firstElement($doc->children);
        self::assertInstanceOf(Mmultiscripts::class, $mu);
    }

    public function testParsesMprescriptsSeparatorAsTyped(): void
    {
        $doc = $this->parser->parse(
            '<math xmlns="http://www.w3.org/1998/Math/MathML">'
                . '<mmultiscripts>'
                . '<mi>X</mi>'
                . '<mprescripts/>'
                . '<mn>3</mn><mn>4</mn>'
                . '</mmultiscripts>'
                . '</math>',
        );
        $mu = $this->firstElement($doc->children);
        // The mprescripts should appear at index 1 in the typed child
        // list (after the base), as a Mprescripts instance.
        $kids = $this->childElements($mu);
        self::assertGreaterThanOrEqual(2, count($kids));
        $separator = $kids[1];
        self::assertInstanceOf(Mprescripts::class, $separator);
    }

    public function testParsesNoneAsTyped(): void
    {
        // Subscript-only pair: <mn>1</mn><none/> means there's a
        // postsubscript but no postsuperscript at this pair slot.
        $doc = $this->parser->parse(
            '<math xmlns="http://www.w3.org/1998/Math/MathML">'
                . '<mmultiscripts>'
                . '<mi>X</mi>'
                . '<mn>1</mn><none/>'
                . '</mmultiscripts>'
                . '</math>',
        );
        $mu = $this->firstElement($doc->children);
        $kids = $this->childElements($mu);
        // [base, post-sub, post-sup]
        self::assertCount(3, $kids);
        self::assertInstanceOf(NoneElement::class, $kids[2]);
    }

    public function testCanonicalChristoffelStructureRoundTrips(): void
    {
        // Christoffel symbol Γᵏ_ij — base Γ, post pair (i, j) means
        // sub-i sup-j, then a presub-only via none.
        $doc = $this->parser->parse(
            '<math xmlns="http://www.w3.org/1998/Math/MathML">'
                . '<mmultiscripts>'
                . '<mi>Γ</mi>'
                . '<mi>i</mi><mi>j</mi>'
                . '<mprescripts/>'
                . '<none/><mi>k</mi>'
                . '</mmultiscripts>'
                . '</math>',
        );
        $mu = $this->firstElement($doc->children);
        $kids = $this->childElements($mu);
        // base, post-sub, post-sup, mprescripts, pre-sub, pre-sup
        self::assertCount(6, $kids);
        self::assertSame('Γ', $kids[0]->textContent());
        self::assertInstanceOf(Mi::class, $kids[1]);
        self::assertSame('i', $kids[1]->textContent());
        self::assertSame('j', $kids[2]->textContent());
        self::assertInstanceOf(Mprescripts::class, $kids[3]);
        self::assertInstanceOf(NoneElement::class, $kids[4]);
        self::assertSame('k', $kids[5]->textContent());
    }

    public function testBaseOnlyMmultiscriptsParses(): void
    {
        // No script pairs at all — degenerate but valid; equivalent
        // to just <mi>X</mi>.
        $doc = $this->parser->parse(
            '<math xmlns="http://www.w3.org/1998/Math/MathML">'
                . '<mmultiscripts><mi>X</mi></mmultiscripts>'
                . '</math>',
        );
        $mu = $this->firstElement($doc->children);
        self::assertInstanceOf(Mmultiscripts::class, $mu);
        $kids = $this->childElements($mu);
        self::assertCount(1, $kids);
    }

    public function testMprescriptsOutsideMmultiscriptsStillParses(): void
    {
        // The parser doesn't validate placement — that's the
        // renderer's call. Author error, but well-formed XML.
        $doc = $this->parser->parse(
            '<math xmlns="http://www.w3.org/1998/Math/MathML">'
                . '<mprescripts/>'
                . '</math>',
        );
        $sep = $this->firstElement($doc->children);
        self::assertInstanceOf(Mprescripts::class, $sep);
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

    /** @return list<Element> */
    private function childElements(Element $parent): array
    {
        return array_values(array_filter(
            $parent->children,
            static fn($c) => $c instanceof Element,
        ));
    }
}
