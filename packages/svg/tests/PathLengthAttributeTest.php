<?php

declare(strict_types=1);

namespace Phpdftk\Svg\Tests;

use Phpdftk\Svg\Element;
use Phpdftk\Svg\Parser;
use PHPUnit\Framework\TestCase;

/**
 * SVG 2 §9.6 / §6.7 — reading the `pathLength` declaration off an
 * element, including the CSS-over-presentation-attribute precedence a
 * presentation attribute's zero specificity implies.
 */
final class PathLengthAttributeTest extends TestCase
{
    private function firstShape(string $body): Element
    {
        $doc = (new Parser())->parse(
            '<svg xmlns="http://www.w3.org/2000/svg">' . $body . '</svg>',
        );
        foreach ($doc->children as $child) {
            if ($child instanceof Element) {
                return $child;
            }
        }
        self::fail('no element parsed');
    }

    public function testAbsentAttributeIsNull(): void
    {
        self::assertNull($this->firstShape('<rect width="10" height="10"/>')->pathLength());
    }

    public function testAttributeIsRead(): void
    {
        self::assertSame(4.0, $this->firstShape('<rect pathLength="4"/>')->pathLength());
    }

    public function testZeroIsValidAndNotConflatedWithAbsent(): void
    {
        // Zero means "scaling factor of infinity", so it must survive
        // as 0.0 rather than collapsing into the null/no-op branch.
        self::assertSame(0.0, $this->firstShape('<path pathLength="0"/>')->pathLength());
    }

    public function testNegativeIsInvalidAndIgnored(): void
    {
        self::assertNull($this->firstShape('<path pathLength="-4"/>')->pathLength());
    }

    public function testNonNumericIsIgnored(): void
    {
        self::assertNull($this->firstShape('<path pathLength="auto"/>')->pathLength());
        self::assertNull($this->firstShape('<path pathLength=""/>')->pathLength());
    }

    public function testCssPropertySpellingIsRead(): void
    {
        self::assertSame(
            10.0,
            $this->firstShape('<rect style="path-length: 10"/>')->pathLength(),
        );
    }

    public function testCssPropertyOutranksThePresentationAttribute(): void
    {
        // §6.7 puts a presentation attribute at specificity 0, so any
        // declaration that reaches `style` wins.
        self::assertSame(
            10.0,
            $this->firstShape('<rect pathLength="100" style="path-length: 10"/>')->pathLength(),
        );
    }

    public function testLowerCaseAttributeSpellingIsNotTheSvgAttribute(): void
    {
        // Guard: in XML, attribute names are case-sensitive and the SVG
        // attribute is `pathLength`. (The HTML tree builder maps
        // `pathlength` to `pathLength` before an SVG subtree ever
        // reaches this parser, so accepting it here too would only
        // legitimise invalid XML.)
        self::assertNull($this->firstShape('<rect pathlength="4"/>')->pathLength());
    }
}
