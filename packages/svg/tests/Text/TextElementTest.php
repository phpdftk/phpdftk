<?php

declare(strict_types=1);

namespace Phpdftk\Svg\Tests\Text;

use Phpdftk\Svg\Parser;
use Phpdftk\Svg\Text as TextNode;
use Phpdftk\Svg\Text\TextElement;
use Phpdftk\Svg\Text\Tspan;
use PHPUnit\Framework\TestCase;

final class TextElementTest extends TestCase
{
    private Parser $parser;

    protected function setUp(): void
    {
        $this->parser = new Parser();
    }

    public function testParsesTextElementAsTypedClass(): void
    {
        $doc = $this->parser->parse(
            '<svg xmlns="http://www.w3.org/2000/svg"><text>Hello</text></svg>',
        );
        $text = $doc->children[0];
        self::assertInstanceOf(TextElement::class, $text);
        self::assertSame('text', $text->localName);
    }

    public function testTextElementPreservesTextDataChild(): void
    {
        $doc = $this->parser->parse(
            '<svg xmlns="http://www.w3.org/2000/svg"><text>Hello world</text></svg>',
        );
        $text = $doc->children[0];
        self::assertInstanceOf(TextElement::class, $text);
        self::assertCount(1, $text->children);
        $leaf = $text->children[0];
        self::assertInstanceOf(TextNode::class, $leaf);
        self::assertSame('Hello world', $leaf->data);
    }

    public function testTextElementXYAsSingleValues(): void
    {
        $doc = $this->parser->parse(
            '<svg xmlns="http://www.w3.org/2000/svg"><text x="10" y="20">A</text></svg>',
        );
        $text = $doc->children[0];
        self::assertInstanceOf(TextElement::class, $text);
        self::assertSame([10.0], $text->x());
        self::assertSame([20.0], $text->y());
    }

    public function testTextElementXAsListOfValues(): void
    {
        // SVG 2 §11.6: `x` may be a list — one value per glyph.
        $doc = $this->parser->parse(
            '<svg xmlns="http://www.w3.org/2000/svg"><text x="10 20 30, 40">ABCD</text></svg>',
        );
        $text = $doc->children[0];
        self::assertInstanceOf(TextElement::class, $text);
        self::assertSame([10.0, 20.0, 30.0, 40.0], $text->x());
    }

    public function testTextElementDxDyRotateAccessors(): void
    {
        $doc = $this->parser->parse(
            '<svg xmlns="http://www.w3.org/2000/svg"><text dx="1 2" dy="-3" rotate="0 90 180">!</text></svg>',
        );
        $text = $doc->children[0];
        self::assertInstanceOf(TextElement::class, $text);
        self::assertSame([1.0, 2.0], $text->dx());
        self::assertSame([-3.0], $text->dy());
        self::assertSame([0.0, 90.0, 180.0], $text->rotate());
    }

    public function testTextElementAbsentPositioningReturnsEmptyLists(): void
    {
        $doc = $this->parser->parse(
            '<svg xmlns="http://www.w3.org/2000/svg"><text>X</text></svg>',
        );
        $text = $doc->children[0];
        self::assertInstanceOf(TextElement::class, $text);
        self::assertSame([], $text->x());
        self::assertSame([], $text->y());
        self::assertSame([], $text->dx());
        self::assertSame([], $text->dy());
        self::assertSame([], $text->rotate());
    }

    public function testTextLengthAndLengthAdjust(): void
    {
        $doc = $this->parser->parse(
            '<svg xmlns="http://www.w3.org/2000/svg">'
            . '<text textLength="100" lengthAdjust="spacingAndGlyphs">A</text>'
            . '</svg>',
        );
        $text = $doc->children[0];
        self::assertInstanceOf(TextElement::class, $text);
        self::assertSame(100.0, $text->textLength());
        self::assertSame('spacingAndGlyphs', $text->lengthAdjust());
    }

    public function testTextLengthRejectsNegative(): void
    {
        $doc = $this->parser->parse(
            '<svg xmlns="http://www.w3.org/2000/svg"><text textLength="-10">A</text></svg>',
        );
        $text = $doc->children[0];
        self::assertInstanceOf(TextElement::class, $text);
        self::assertNull($text->textLength());
    }

    public function testLengthAdjustUnknownValueIsNull(): void
    {
        $doc = $this->parser->parse(
            '<svg xmlns="http://www.w3.org/2000/svg"><text lengthAdjust="weird">A</text></svg>',
        );
        $text = $doc->children[0];
        self::assertInstanceOf(TextElement::class, $text);
        self::assertNull($text->lengthAdjust());
    }

    public function testParsesTspanAsTypedClass(): void
    {
        $doc = $this->parser->parse(
            '<svg xmlns="http://www.w3.org/2000/svg">'
            . '<text>Hello <tspan x="50" font-weight="bold">world</tspan>!</text>'
            . '</svg>',
        );
        $text = $doc->children[0];
        self::assertInstanceOf(TextElement::class, $text);
        // children: Text("Hello "), Tspan, Text("!")
        self::assertCount(3, $text->children);
        $tspan = $text->children[1];
        self::assertInstanceOf(Tspan::class, $tspan);
        self::assertSame([50.0], $tspan->x());
        self::assertSame('bold', $tspan->fontWeight());
    }

    public function testNestedTspansAreReachable(): void
    {
        $doc = $this->parser->parse(
            '<svg xmlns="http://www.w3.org/2000/svg">'
            . '<text><tspan><tspan>deep</tspan></tspan></text>'
            . '</svg>',
        );
        $text = $doc->children[0];
        self::assertInstanceOf(TextElement::class, $text);
        $outer = $text->children[0];
        self::assertInstanceOf(Tspan::class, $outer);
        $inner = $outer->children[0];
        self::assertInstanceOf(Tspan::class, $inner);
        $leaf = $inner->children[0];
        self::assertInstanceOf(TextNode::class, $leaf);
        self::assertSame('deep', $leaf->data);
    }
}
