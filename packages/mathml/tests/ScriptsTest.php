<?php

declare(strict_types=1);

namespace Phpdftk\Mathml\Tests;

use Phpdftk\Mathml\Element;
use Phpdftk\Mathml\Mi;
use Phpdftk\Mathml\Mn;
use Phpdftk\Mathml\Mo;
use Phpdftk\Mathml\Mover;
use Phpdftk\Mathml\Msub;
use Phpdftk\Mathml\Msubsup;
use Phpdftk\Mathml\Msup;
use Phpdftk\Mathml\Munder;
use Phpdftk\Mathml\Munderover;
use Phpdftk\Mathml\Parser;
use PHPUnit\Framework\TestCase;

/**
 * Parser-layer coverage for the script-attachment elements:
 * `<msub>`, `<msup>`, `<msubsup>`, `<munder>`, `<mover>`,
 * `<munderover>`.
 *
 * Each new typed class gets a round-trip test confirming it parses
 * as the typed class (not `GenericElement`). The under/over family
 * additionally exposes `accent` / `accentunder` attribute accessors
 * which get negative-first coverage.
 */
final class ScriptsTest extends TestCase
{
    private Parser $parser;

    protected function setUp(): void
    {
        $this->parser = new Parser();
    }

    // -----------------------------------------------------------------
    // msub / msup / msubsup
    // -----------------------------------------------------------------

    public function testParsesMsubAsTyped(): void
    {
        $doc = $this->parser->parse(
            '<math xmlns="http://www.w3.org/1998/Math/MathML">'
                . '<msub><mi>x</mi><mn>0</mn></msub>'
                . '</math>',
        );
        $msub = $this->firstElement($doc->children);
        self::assertInstanceOf(Msub::class, $msub);
        $kids = $this->childElements($msub);
        self::assertCount(2, $kids);
        self::assertInstanceOf(Mi::class, $kids[0]);
        self::assertInstanceOf(Mn::class, $kids[1]);
    }

    public function testParsesMsupAsTyped(): void
    {
        // x² — the canonical superscript example.
        $doc = $this->parser->parse(
            '<math xmlns="http://www.w3.org/1998/Math/MathML">'
                . '<msup><mi>x</mi><mn>2</mn></msup>'
                . '</math>',
        );
        $msup = $this->firstElement($doc->children);
        self::assertInstanceOf(Msup::class, $msup);
    }

    public function testParsesMsubsupAsTyped(): void
    {
        // Definite integral bounds — three children, order is base / sub / sup.
        $doc = $this->parser->parse(
            '<math xmlns="http://www.w3.org/1998/Math/MathML">'
                . '<msubsup><mi>x</mi><mn>0</mn><mn>2</mn></msubsup>'
                . '</math>',
        );
        $msubsup = $this->firstElement($doc->children);
        self::assertInstanceOf(Msubsup::class, $msubsup);
        $kids = $this->childElements($msubsup);
        self::assertCount(3, $kids);
        self::assertSame('x', $kids[0]->textContent());
        self::assertSame('0', $kids[1]->textContent());
        self::assertSame('2', $kids[2]->textContent());
    }

    public function testMsupWithWrongArityStillParses(): void
    {
        // Author error (one child) but the parser doesn't validate
        // arity — that's the renderer's call.
        $doc = $this->parser->parse(
            '<math xmlns="http://www.w3.org/1998/Math/MathML">'
                . '<msup><mi>x</mi></msup>'
                . '</math>',
        );
        $msup = $this->firstElement($doc->children);
        self::assertInstanceOf(Msup::class, $msup);
        self::assertCount(1, $this->childElements($msup));
    }

    // -----------------------------------------------------------------
    // munder / mover / munderover
    // -----------------------------------------------------------------

    public function testParsesMunderAsTyped(): void
    {
        $doc = $this->parser->parse(
            '<math xmlns="http://www.w3.org/1998/Math/MathML">'
                . '<munder><mo>lim</mo><mi>x</mi></munder>'
                . '</math>',
        );
        $munder = $this->firstElement($doc->children);
        self::assertInstanceOf(Munder::class, $munder);
    }

    public function testParsesMoverAsTyped(): void
    {
        // x with overline accent
        $doc = $this->parser->parse(
            '<math xmlns="http://www.w3.org/1998/Math/MathML">'
                . '<mover><mi>x</mi><mo>¯</mo></mover>'
                . '</math>',
        );
        $mover = $this->firstElement($doc->children);
        self::assertInstanceOf(Mover::class, $mover);
    }

    public function testParsesMunderoverAsTyped(): void
    {
        // Definite integral with both bounds positioned over/under.
        $doc = $this->parser->parse(
            '<math xmlns="http://www.w3.org/1998/Math/MathML">'
                . '<munderover><mo>∫</mo><mn>0</mn><mi>∞</mi></munderover>'
                . '</math>',
        );
        $mu = $this->firstElement($doc->children);
        self::assertInstanceOf(Munderover::class, $mu);
        $kids = $this->childElements($mu);
        self::assertCount(3, $kids);
    }

    // -----------------------------------------------------------------
    // accent / accentunder accessors
    // -----------------------------------------------------------------

    public function testMoverAccentAbsentReturnsNull(): void
    {
        $mover = new Mover();
        self::assertNull($mover->accent());
    }

    public function testMoverAccentTrue(): void
    {
        $mover = new Mover();
        $mover->setAttribute('accent', 'true');
        self::assertTrue($mover->accent());
    }

    public function testMoverAccentFalse(): void
    {
        $mover = new Mover();
        $mover->setAttribute('accent', 'false');
        self::assertFalse($mover->accent());
    }

    public function testMoverAccentUnknownReturnsNull(): void
    {
        $mover = new Mover();
        $mover->setAttribute('accent', 'maybe');
        self::assertNull($mover->accent());
    }

    public function testMunderAccentunderAccessors(): void
    {
        $munder = new Munder();
        self::assertNull($munder->accentunder());
        $munder->setAttribute('accentunder', 'true');
        self::assertTrue($munder->accentunder());
        $munder->setAttribute('accentunder', 'false');
        self::assertFalse($munder->accentunder());
    }

    public function testMunderoverHasBothAccentAccessors(): void
    {
        $mu = new Munderover();
        self::assertNull($mu->accent());
        self::assertNull($mu->accentunder());
        $mu->setAttribute('accent', 'true');
        $mu->setAttribute('accentunder', 'false');
        self::assertTrue($mu->accent());
        self::assertFalse($mu->accentunder());
    }

    // -----------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------

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
