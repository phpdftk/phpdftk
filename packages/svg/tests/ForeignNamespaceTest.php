<?php

declare(strict_types=1);

namespace Phpdftk\Svg\Tests;

use Phpdftk\Svg\ForeignElement;
use Phpdftk\Svg\NestedSvg;
use Phpdftk\Svg\Parser;
use Phpdftk\Svg\Shape\Rect;
use PHPUnit\Framework\TestCase;

/**
 * SVG 2 §5.7 — elements from a foreign namespace inside an SVG
 * document are "private data": neither they nor their descendants are
 * rendered.
 *
 * The XML tree walker dropped `namespaceURI` on the way in, so an
 * XHTML `<div>` arrived as an ordinary unknown SVG element and the
 * painter's "recurse into children" fallback happily descended into
 * whatever it wrapped.
 */
final class ForeignNamespaceTest extends TestCase
{
    public function testAnXhtmlElementBecomesAForeignElement(): void
    {
        $doc = (new Parser())->parse(
            '<svg xmlns="http://www.w3.org/2000/svg" xmlns:h="http://www.w3.org/1999/xhtml">'
            . '<h:div><rect width="10" height="10"/></h:div></svg>',
        );
        $child = $doc->children[0];
        self::assertInstanceOf(ForeignElement::class, $child);
        self::assertSame('div', $child->localName);
        self::assertSame('http://www.w3.org/1999/xhtml', $child->namespaceUri);
    }

    public function testAnSvgDescendantOfAForeignElementIsStillParsed(): void
    {
        // The subtree is retained in the tree (scripts and tooling may
        // want it); it is the PAINTER that must skip it. Parsing it
        // away would make the two concerns impossible to tell apart.
        $doc = (new Parser())->parse(
            '<svg xmlns="http://www.w3.org/2000/svg" xmlns:h="http://www.w3.org/1999/xhtml">'
            . '<h:div><svg width="30" height="30"/></h:div></svg>',
        );
        $foreign = $doc->children[0];
        self::assertInstanceOf(ForeignElement::class, $foreign);
        self::assertInstanceOf(NestedSvg::class, $foreign->children[0]);
    }

    public function testSvgElementsAreUnaffected(): void
    {
        // Guard: the namespace test must not reclassify ordinary
        // content. An element inheriting the root's default namespace
        // is SVG.
        $doc = (new Parser())->parse(
            '<svg xmlns="http://www.w3.org/2000/svg"><rect width="10" height="10"/></svg>',
        );
        self::assertInstanceOf(Rect::class, $doc->children[0]);
    }

    public function testAnExplicitlySvgNamespacedPrefixIsNotForeign(): void
    {
        $doc = (new Parser())->parse(
            '<s:svg xmlns:s="http://www.w3.org/2000/svg"><s:rect width="10" height="10"/></s:svg>',
        );
        self::assertInstanceOf(Rect::class, $doc->children[0]);
    }

    public function testADocumentWithNoNamespaceAtAllIsTreatedAsSvg(): void
    {
        // Guard: hand-written SVG that declares no namespace must keep
        // working — a null namespace is "unspecified", not "foreign".
        $doc = (new Parser())->parse('<svg><rect width="10" height="10"/></svg>');
        self::assertInstanceOf(Rect::class, $doc->children[0]);
    }
}
