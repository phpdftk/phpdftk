<?php

declare(strict_types=1);

namespace Phpdftk\Mathml\Tests;

use Phpdftk\Mathml\Parser;
use PHPUnit\Framework\TestCase;

/**
 * MathML Core §3.1.3 — a MathML layout algorithm considers its
 * IN-FLOW children only. A `display: none` child generates no box at
 * all, and an absolutely positioned one is taken out of flow, so
 * neither may occupy a slot in a construct like `<mfrac>`'s
 * numerator/denominator or `<munder>`'s base/script.
 *
 * The values arrive on the inline `style` attribute, which
 * `HtmlToPdf\Box\BoxGenerator` projects the document cascade onto.
 */
final class InFlowChildrenTest extends TestCase
{
    private Parser $parser;

    protected function setUp(): void
    {
        $this->parser = new Parser();
    }

    private function firstChild(string $style): \Phpdftk\Mathml\Element
    {
        $doc = $this->parser->parse(
            '<math xmlns="http://www.w3.org/1998/Math/MathML">'
                . '<mo style="' . $style . '">+</mo>'
                . '</math>',
        );
        $child = $doc->children[0];
        self::assertInstanceOf(\Phpdftk\Mathml\Element::class, $child);
        return $child;
    }

    // Out of flow / no box.

    public function testDisplayNoneGeneratesNoBox(): void
    {
        self::assertFalse($this->firstChild('display: none')->generatesInFlowBox());
    }

    public function testPositionAbsoluteIsOutOfFlow(): void
    {
        self::assertFalse($this->firstChild('position: absolute')->generatesInFlowBox());
    }

    public function testPositionFixedIsOutOfFlow(): void
    {
        self::assertFalse($this->firstChild('position: fixed')->generatesInFlowBox());
    }

    public function testTheCheckIsCaseInsensitiveAndTolerantOfSpacing(): void
    {
        self::assertFalse($this->firstChild('display:NONE')->generatesInFlowBox());
        self::assertFalse($this->firstChild('position:   ABSOLUTE   ')->generatesInFlowBox());
    }

    // In flow.

    public function testAnElementWithNoStyleIsInFlow(): void
    {
        $doc = $this->parser->parse(
            '<math xmlns="http://www.w3.org/1998/Math/MathML"><mo>+</mo></math>',
        );
        $child = $doc->children[0];
        self::assertInstanceOf(\Phpdftk\Mathml\Element::class, $child);
        self::assertTrue($child->generatesInFlowBox());
    }

    public function testPositionRelativeAndStickyStayInFlow(): void
    {
        self::assertTrue($this->firstChild('position: relative')->generatesInFlowBox());
        self::assertTrue($this->firstChild('position: sticky')->generatesInFlowBox());
    }

    public function testANonNoneDisplayStaysInFlow(): void
    {
        self::assertTrue($this->firstChild('display: block')->generatesInFlowBox());
        self::assertTrue($this->firstChild('display: inline')->generatesInFlowBox());
    }

    /**
     * `display: none` must not be confused with a value that merely
     * CONTAINS it, and an unrelated property must not be read as one.
     */
    public function testUnrelatedDeclarationsDoNotRemoveTheElement(): void
    {
        self::assertTrue($this->firstChild('color: none')->generatesInFlowBox());
        self::assertTrue($this->firstChild('display: inline-block')->generatesInFlowBox());
        self::assertTrue($this->firstChild('background-position: absolute')->generatesInFlowBox());
    }
}
