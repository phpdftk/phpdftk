<?php

declare(strict_types=1);

namespace Phpdftk\Svg\Tests;

use Phpdftk\Svg\Exception\InvalidSvgException;
use Phpdftk\Svg\GenericElement;
use Phpdftk\Svg\Parser;
use Phpdftk\Svg\Shape\Rect;
use Phpdftk\Svg\SvgDocument;
use Phpdftk\Svg\Text;
use PHPUnit\Framework\TestCase;

final class ParserTest extends TestCase
{
    private Parser $parser;

    protected function setUp(): void
    {
        $this->parser = new Parser();
    }

    public function testParsesMinimalSvgRoot(): void
    {
        $doc = $this->parser->parse('<svg xmlns="http://www.w3.org/2000/svg"/>');
        self::assertInstanceOf(SvgDocument::class, $doc);
        self::assertSame('svg', $doc->localName);
        self::assertSame([], $doc->children);
    }

    public function testParsesRootAttributes(): void
    {
        $doc = $this->parser->parse(
            '<svg xmlns="http://www.w3.org/2000/svg" width="200" height="100" viewBox="0 0 200 100"/>',
        );
        self::assertSame('200', $doc->widthAttribute());
        self::assertSame('100', $doc->heightAttribute());
        self::assertSame([0.0, 0.0, 200.0, 100.0], $doc->viewBox());
    }

    public function testViewBoxAcceptsCommaSeparated(): void
    {
        $doc = $this->parser->parse(
            '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0,0,100,100"/>',
        );
        self::assertSame([0.0, 0.0, 100.0, 100.0], $doc->viewBox());
    }

    public function testViewBoxRejectsNegativeDimensions(): void
    {
        $doc = $this->parser->parse(
            '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 -10 100"/>',
        );
        self::assertNull($doc->viewBox());
    }

    public function testViewBoxRejectsWrongCount(): void
    {
        $doc = $this->parser->parse(
            '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100"/>',
        );
        self::assertNull($doc->viewBox());
    }

    public function testViewBoxRejectsNonNumeric(): void
    {
        $doc = $this->parser->parse(
            '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 abc"/>',
        );
        self::assertNull($doc->viewBox());
    }

    public function testParsesRectChildAsTypedElement(): void
    {
        $doc = $this->parser->parse(
            '<svg xmlns="http://www.w3.org/2000/svg"><rect x="10" y="20" width="30" height="40"/></svg>',
        );
        self::assertCount(1, $doc->children);
        $rect = $doc->children[0];
        self::assertInstanceOf(Rect::class, $rect);
        self::assertSame(10.0, $rect->x());
        self::assertSame(20.0, $rect->y());
        self::assertSame(30.0, $rect->width());
        self::assertSame(40.0, $rect->height());
    }

    public function testRectDefaultsXYToZero(): void
    {
        $doc = $this->parser->parse(
            '<svg xmlns="http://www.w3.org/2000/svg"><rect width="30" height="40"/></svg>',
        );
        $rect = $doc->children[0];
        self::assertInstanceOf(Rect::class, $rect);
        self::assertSame(0.0, $rect->x());
        self::assertSame(0.0, $rect->y());
    }

    public function testRectStripsUnitFromLength(): void
    {
        $doc = $this->parser->parse(
            '<svg xmlns="http://www.w3.org/2000/svg"><rect width="30px" height="40mm"/></svg>',
        );
        $rect = $doc->children[0];
        self::assertInstanceOf(Rect::class, $rect);
        self::assertSame(30.0, $rect->width());
        self::assertSame(40.0, $rect->height());
    }

    public function testRectInvalidLengthFallsBackToZero(): void
    {
        $doc = $this->parser->parse(
            '<svg xmlns="http://www.w3.org/2000/svg"><rect width="not-a-length" height="40"/></svg>',
        );
        $rect = $doc->children[0];
        self::assertInstanceOf(Rect::class, $rect);
        self::assertSame(0.0, $rect->width());
        self::assertSame(40.0, $rect->height());
    }

    public function testRectRxFallsBackToRyAndViceVersa(): void
    {
        $doc = $this->parser->parse(
            '<svg xmlns="http://www.w3.org/2000/svg"><rect width="10" height="10" rx="3"/></svg>',
        );
        $rect = $doc->children[0];
        self::assertInstanceOf(Rect::class, $rect);
        self::assertSame(3.0, $rect->rx());
        self::assertSame(3.0, $rect->ry(), 'ry should fall back to rx when not set');
    }

    public function testRectRxRyNullWhenNeitherSet(): void
    {
        $doc = $this->parser->parse(
            '<svg xmlns="http://www.w3.org/2000/svg"><rect width="10" height="10"/></svg>',
        );
        $rect = $doc->children[0];
        self::assertInstanceOf(Rect::class, $rect);
        self::assertNull($rect->rx());
        self::assertNull($rect->ry());
    }

    public function testUnknownElementBecomesGenericElement(): void
    {
        $doc = $this->parser->parse(
            '<svg xmlns="http://www.w3.org/2000/svg"><circle cx="50" cy="50" r="25"/></svg>',
        );
        self::assertCount(1, $doc->children);
        $circle = $doc->children[0];
        self::assertInstanceOf(GenericElement::class, $circle);
        self::assertSame('circle', $circle->localName);
        self::assertSame('50', $circle->getAttribute('cx'));
        self::assertSame('25', $circle->getAttribute('r'));
    }

    public function testTextDataIsPreserved(): void
    {
        $doc = $this->parser->parse(
            '<svg xmlns="http://www.w3.org/2000/svg"><title>my graphic</title></svg>',
        );
        $title = $doc->children[0];
        self::assertInstanceOf(GenericElement::class, $title);
        self::assertCount(1, $title->children);
        $text = $title->children[0];
        self::assertInstanceOf(Text::class, $text);
        self::assertSame('my graphic', $text->data);
    }

    public function testNestedFindByTagReturnsAllDescendantsInDocumentOrder(): void
    {
        $doc = $this->parser->parse(<<<XML
            <svg xmlns="http://www.w3.org/2000/svg">
              <g>
                <rect x="0" y="0" width="10" height="10"/>
                <g><rect x="10" y="10" width="10" height="10"/></g>
              </g>
              <rect x="20" y="20" width="10" height="10"/>
            </svg>
            XML);
        $rects = $doc->findByTag('rect');
        self::assertCount(3, $rects);
        $xs = [];
        foreach ($rects as $r) {
            self::assertInstanceOf(Rect::class, $r);
            $xs[] = $r->x();
        }
        self::assertSame([0.0, 10.0, 20.0], $xs);
    }

    public function testCommentsAreStripped(): void
    {
        $doc = $this->parser->parse(
            '<svg xmlns="http://www.w3.org/2000/svg"><!-- a comment --><rect width="1" height="1"/></svg>',
        );
        self::assertCount(1, $doc->children);
        self::assertInstanceOf(Rect::class, $doc->children[0]);
    }

    public function testNamespaceDeclarationsAreNotCopiedAsAttributes(): void
    {
        $doc = $this->parser->parse(
            '<svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink"/>',
        );
        self::assertFalse($doc->hasAttribute('xmlns'));
        self::assertFalse($doc->hasAttribute('xmlns:xlink'));
    }

    public function testRejectsEmptyInput(): void
    {
        $this->expectException(InvalidSvgException::class);
        $this->parser->parse('');
    }

    public function testRejectsMalformedXml(): void
    {
        $this->expectException(InvalidSvgException::class);
        $this->parser->parse('<svg><rect></svg>');
    }

    public function testRejectsNonSvgRoot(): void
    {
        $this->expectException(InvalidSvgException::class);
        $this->expectExceptionMessage('Expected <svg> root');
        $this->parser->parse('<html><body/></html>');
    }
}
