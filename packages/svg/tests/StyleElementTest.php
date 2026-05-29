<?php

declare(strict_types=1);

namespace Phpdftk\Svg\Tests;

use Phpdftk\Svg\Parser;
use Phpdftk\Svg\StyleElement;
use PHPUnit\Framework\TestCase;

final class StyleElementTest extends TestCase
{
    private Parser $parser;

    protected function setUp(): void
    {
        $this->parser = new Parser();
    }

    public function testParsesStyleAsTypedElement(): void
    {
        $doc = $this->parser->parse(
            '<svg xmlns="http://www.w3.org/2000/svg"><style>rect { fill: red; }</style></svg>',
        );
        $style = $doc->children[0];
        self::assertInstanceOf(StyleElement::class, $style);
        self::assertSame('rect { fill: red; }', $style->cssText());
    }

    public function testCssTextEmptyWhenStyleHasNoChildren(): void
    {
        $doc = $this->parser->parse(
            '<svg xmlns="http://www.w3.org/2000/svg"><style></style></svg>',
        );
        $style = $doc->children[0];
        self::assertInstanceOf(StyleElement::class, $style);
        self::assertSame('', $style->cssText());
    }

    public function testCssTextHandlesCdataSection(): void
    {
        // CSS containing characters that would otherwise need entity
        // escaping (`<`, `>`, `&`) is commonly wrapped in CDATA.
        // PHP's DOMCdataSection extends DOMText so the parser preserves
        // it as a Text data node.
        $doc = $this->parser->parse(
            '<svg xmlns="http://www.w3.org/2000/svg"><style><![CDATA[ rect.hi { fill: blue; } ]]></style></svg>',
        );
        $style = $doc->children[0];
        self::assertInstanceOf(StyleElement::class, $style);
        self::assertStringContainsString('rect.hi { fill: blue; }', $style->cssText());
    }

    public function testElementClassListParsesWhitespaceSeparated(): void
    {
        $doc = $this->parser->parse(
            '<svg xmlns="http://www.w3.org/2000/svg"><rect class="hi  there   friend"/></svg>',
        );
        $rect = $doc->children[0];
        self::assertInstanceOf(\Phpdftk\Svg\Shape\Rect::class, $rect);
        self::assertSame(['hi', 'there', 'friend'], $rect->classList());
    }

    public function testElementClassListEmptyWhenAbsent(): void
    {
        $doc = $this->parser->parse(
            '<svg xmlns="http://www.w3.org/2000/svg"><rect/></svg>',
        );
        $rect = $doc->children[0];
        self::assertInstanceOf(\Phpdftk\Svg\Shape\Rect::class, $rect);
        self::assertSame([], $rect->classList());
    }

    public function testElementInlineStyleTextRoundTrip(): void
    {
        $doc = $this->parser->parse(
            '<svg xmlns="http://www.w3.org/2000/svg"><rect style="fill: red; stroke: blue"/></svg>',
        );
        $rect = $doc->children[0];
        self::assertInstanceOf(\Phpdftk\Svg\Shape\Rect::class, $rect);
        self::assertSame('fill: red; stroke: blue', $rect->inlineStyleText());
    }

    public function testElementInlineStyleTextNullWhenAbsentOrEmpty(): void
    {
        $doc = $this->parser->parse(
            '<svg xmlns="http://www.w3.org/2000/svg"><rect/><rect style=""/></svg>',
        );
        $first = $doc->children[0];
        $second = $doc->children[1];
        self::assertInstanceOf(\Phpdftk\Svg\Shape\Rect::class, $first);
        self::assertInstanceOf(\Phpdftk\Svg\Shape\Rect::class, $second);
        self::assertNull($first->inlineStyleText());
        self::assertNull($second->inlineStyleText());
    }
}
