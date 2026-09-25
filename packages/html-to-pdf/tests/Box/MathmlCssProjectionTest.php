<?php

declare(strict_types=1);

namespace Phpdftk\HtmlToPdf\Tests\Box;

use Phpdftk\Css\Cascade\Cascade;
use Phpdftk\Css\Cascade\PropertyRegistry;
use Phpdftk\Css\Parser as CssParser;
use Phpdftk\Html\Dom\Element;
use Phpdftk\Html\Parser as HtmlParser;
use Phpdftk\HtmlToPdf\Box\BoxGenerator;
use PHPUnit\Framework\TestCase;

/**
 * MathML Core §2.1.1 / §3.1.4 — MathML elements participate in the
 * CSS cascade, and `mathcolor` is the same thing as the CSS `color`
 * property. An inline `<math>` subtree generates no boxes of its own
 * (the painter serialises it for the MathML renderer), so BoxGenerator
 * projects the cascaded value onto each element's inline `style`,
 * which is the one path `Phpdftk\Mathml\Element` already reads.
 */
final class MathmlCssProjectionTest extends TestCase
{
    private BoxGenerator $generator;
    private CssParser $css;
    private HtmlParser $html;

    protected function setUp(): void
    {
        $this->css = new CssParser();
        $this->html = new HtmlParser();
        $this->generator = new BoxGenerator(new Cascade(PropertyRegistry::default()));
    }

    /**
     * Generate boxes for `$body` under `$extraCss` and return the DOM
     * element with the given local name — the projection mutates the
     * DOM, so the assertions read it back off the element.
     */
    private function project(string $body, string $extraCss, string $tag): ?Element
    {
        $sheet = $this->css->parseStylesheet(
            'html, body, p { display: block; } math { display: inline-block; } ' . $extraCss,
        );
        $doc = $this->html->parseDocument('<html><body>' . $body . '</body></html>');
        $this->generator->generate($doc, [$sheet]);
        return $this->find($doc->documentElement, $tag);
    }

    private function find(?Element $root, string $tag): ?Element
    {
        if ($root === null) {
            return null;
        }
        $stack = [$root];
        while ($stack !== []) {
            $node = array_shift($stack);
            if (strtolower($node->localName) === $tag) {
                return $node;
            }
            foreach ($node->children() as $c) {
                $stack[] = $c;
            }
        }
        return null;
    }

    private const string MATH = '<math><mrow><mi>a</mi><mo>+</mo></mrow></math>';

    // ---------------------------------------------------------------
    // Negative cases first: what must NOT be projected.
    // ---------------------------------------------------------------

    /**
     * `color` is an INHERITED property, so every element answers
     * `has('color')`. Projecting on that basis would stamp the
     * document default onto every descendant and shadow a
     * `mathcolor` set on an ancestor, because the MathML painter
     * consults the element's own `style` before its inherited
     * cascade. Only a declaration that won ON THIS ELEMENT projects.
     */
    public function testUndeclaredColourIsNotProjectedOntoDescendants(): void
    {
        $mo = $this->project(self::MATH, '', 'mo');
        self::assertNotNull($mo);
        self::assertNull($mo->getAttribute('style'));
    }

    public function testColourDeclaredOnAnAncestorOnlyIsNotStampedOntoDescendants(): void
    {
        $mo = $this->project(self::MATH, 'math { color: red; }', 'mo');
        self::assertNotNull($mo);
        self::assertNull(
            $mo->getAttribute('style'),
            'the <math> root is styled via its own box, and inheritance to <mo> '
                . 'is the painter\'s job — stamping it here would beat mathcolor',
        );
    }

    /**
     * A `mathcolor` attribute on the element itself must survive when
     * no author rule declared `color` for it.
     */
    public function testMathcolorAttributeSurvivesWhenNoCssDeclaresColour(): void
    {
        $mo = $this->project(
            '<math><mrow><mo mathcolor="blue">+</mo></mrow></math>',
            '',
            'mo',
        );
        self::assertNotNull($mo);
        self::assertSame('blue', $mo->getAttribute('mathcolor'));
        self::assertNull($mo->getAttribute('style'));
    }

    /**
     * A non-MathML subtree must be left alone — the projection is
     * keyed off the `<math>` foreign-content root, and ordinary HTML
     * descendants get real boxes that carry their own style.
     */
    public function testOrdinaryHtmlDescendantsAreNotProjectedOnto(): void
    {
        $span = $this->project(
            '<p><span>x</span></p>' . self::MATH,
            'span { color: red; }',
            'span',
        );
        self::assertNotNull($span);
        self::assertNull($span->getAttribute('style'));
    }

    // ---------------------------------------------------------------
    // Positive cases.
    // ---------------------------------------------------------------

    public function testTypeSelectorColourReachesATokenElement(): void
    {
        $mo = $this->project(self::MATH, 'mo { color: black; }', 'mo');
        self::assertNotNull($mo);
        self::assertSame('color: #000000', $mo->getAttribute('style'));
    }

    public function testProjectionResolvesDescendantSelectorsAgainstRealAncestry(): void
    {
        $mi = $this->project(self::MATH, 'mrow > mi { color: lime; }', 'mi');
        self::assertNotNull($mi);
        self::assertSame('color: #00ff00', $mi->getAttribute('style'));
    }

    /**
     * The element's own inline style is authored intent and outranks
     * a type-selector rule. The cascade run by the projector has
     * already seen that inline style, so the value it stamps IS the
     * winner (`blue`, not `red`); re-appending the original
     * declaration afterwards is belt-and-braces, and the element
     * still ends up blue either way.
     */
    public function testElementInlineStyleStillWinsOverATypeSelectorRule(): void
    {
        $mo = $this->project(
            '<math><mrow><mo style="color: blue">+</mo></mrow></math>',
            'mo { color: red; }',
            'mo',
        );
        self::assertNotNull($mo);
        self::assertSame('color: #0000ff; color: blue', $mo->getAttribute('style'));
    }

    // ---------------------------------------------------------------
    // MathML Core §3.1.3 — MathML layout considers IN-FLOW children
    // only, so `display` and `position` have to cross the boundary
    // for the painter to know which children dropped out.
    // ---------------------------------------------------------------

    public function testDisplayNoneReachesATokenElement(): void
    {
        $mo = $this->project(self::MATH, 'mo { display: none; }', 'mo');
        self::assertNotNull($mo);
        self::assertSame('display: none', $mo->getAttribute('style'));
    }

    public function testOutOfFlowPositionReachesATokenElement(): void
    {
        $mo = $this->project(self::MATH, 'mo { position: absolute; }', 'mo');
        self::assertNotNull($mo);
        self::assertSame('position: absolute', $mo->getAttribute('style'));
    }

    /**
     * Negative guard: `display` and `position` are NOT inherited, so
     * an undeclared element must stay clean — otherwise every MathML
     * element would carry `display: inline` and `position: static`.
     */
    public function testUndeclaredDisplayAndPositionAreNotStamped(): void
    {
        $mo = $this->project(self::MATH, 'mrow { position: relative; }', 'mo');
        self::assertNotNull($mo);
        self::assertNull($mo->getAttribute('style'));
    }

    /**
     * MathML Core §2.1.1 makes `mathcolor` a presentational hint,
     * which loses to any author declaration.
     */
    public function testAuthorCssOutranksTheMathcolorPresentationHint(): void
    {
        $mo = $this->project(
            '<math><mrow><mo mathcolor="blue">+</mo></mrow></math>',
            'mo { color: red; }',
            'mo',
        );
        self::assertNotNull($mo);
        self::assertSame('color: #ff0000', $mo->getAttribute('style'));
    }
}
