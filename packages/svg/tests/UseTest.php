<?php

declare(strict_types=1);

namespace Phpdftk\Svg\Tests;

use Phpdftk\Svg\Defs;
use Phpdftk\Svg\Parser;
use Phpdftk\Svg\Shape\Rect;
use Phpdftk\Svg\Symbol;
use Phpdftk\Svg\Use_;
use PHPUnit\Framework\TestCase;

final class UseTest extends TestCase
{
    private Parser $parser;

    protected function setUp(): void
    {
        $this->parser = new Parser();
    }

    public function testParsesDefsAsTypedElement(): void
    {
        $doc = $this->parser->parse(
            '<svg xmlns="http://www.w3.org/2000/svg"><defs><rect id="r" width="1" height="1"/></defs></svg>',
        );
        $defs = $doc->children[0];
        self::assertInstanceOf(Defs::class, $defs);
        self::assertInstanceOf(Rect::class, $defs->children[0]);
    }

    public function testParsesSymbolAsTypedElement(): void
    {
        $doc = $this->parser->parse(
            '<svg xmlns="http://www.w3.org/2000/svg">'
            . '<symbol id="s" viewBox="0 0 10 10" width="100" height="100">'
            . '<rect width="10" height="10"/></symbol></svg>',
        );
        $symbol = $doc->children[0];
        self::assertInstanceOf(Symbol::class, $symbol);
        self::assertSame([0.0, 0.0, 10.0, 10.0], $symbol->viewBox());
        self::assertSame('100', $symbol->widthAttribute());
        self::assertSame('100', $symbol->heightAttribute());
    }

    public function testParsesUseAsTypedElement(): void
    {
        $doc = $this->parser->parse(
            '<svg xmlns="http://www.w3.org/2000/svg">'
            . '<defs><rect id="box" width="10" height="10"/></defs>'
            . '<use href="#box" x="50" y="50"/></svg>',
        );
        $use = $doc->children[1];
        self::assertInstanceOf(Use_::class, $use);
        self::assertSame(50.0, $use->x());
        self::assertSame(50.0, $use->y());
    }

    public function testUseHrefStripsHashPrefix(): void
    {
        $doc = $this->parser->parse(
            '<svg xmlns="http://www.w3.org/2000/svg"><use href="#myref"/></svg>',
        );
        $use = $doc->children[0];
        self::assertInstanceOf(Use_::class, $use);
        self::assertSame('myref', $use->href());
    }

    public function testUseFallsBackToXlinkHref(): void
    {
        // Older content uses `xlink:href`; SVG 2 keeps it as a legacy
        // synonym.
        $doc = $this->parser->parse(
            '<svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink">'
            . '<use xlink:href="#legacy"/></svg>',
        );
        $use = $doc->children[0];
        self::assertInstanceOf(Use_::class, $use);
        self::assertSame('legacy', $use->href());
    }

    public function testHrefPrefersHrefOverXlinkHref(): void
    {
        $doc = $this->parser->parse(
            '<svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink">'
            . '<use href="#new" xlink:href="#old"/></svg>',
        );
        $use = $doc->children[0];
        self::assertInstanceOf(Use_::class, $use);
        self::assertSame('new', $use->href());
    }

    public function testExternalHrefReturnsNullAtV1(): void
    {
        // Security posture from 3A: no implicit cross-document loads.
        $doc = $this->parser->parse(
            '<svg xmlns="http://www.w3.org/2000/svg"><use href="other.svg#foo"/></svg>',
        );
        $use = $doc->children[0];
        self::assertInstanceOf(Use_::class, $use);
        self::assertNull($use->href());
    }

    public function testEmptyHrefReturnsNull(): void
    {
        $doc = $this->parser->parse(
            '<svg xmlns="http://www.w3.org/2000/svg"><use href="#"/></svg>',
        );
        $use = $doc->children[0];
        self::assertInstanceOf(Use_::class, $use);
        self::assertNull($use->href());
    }

    public function testUseWidthAndHeightNullWhenAbsent(): void
    {
        $doc = $this->parser->parse(
            '<svg xmlns="http://www.w3.org/2000/svg"><use href="#a"/></svg>',
        );
        $use = $doc->children[0];
        self::assertInstanceOf(Use_::class, $use);
        self::assertNull($use->width());
        self::assertNull($use->height());
    }

    public function testUseWidthRejectsNegative(): void
    {
        $doc = $this->parser->parse(
            '<svg xmlns="http://www.w3.org/2000/svg"><use href="#a" width="-1"/></svg>',
        );
        $use = $doc->children[0];
        self::assertInstanceOf(Use_::class, $use);
        self::assertNull($use->width());
    }

    public function testSvgDocumentFindByIdLocatesNestedElement(): void
    {
        $doc = $this->parser->parse(
            '<svg xmlns="http://www.w3.org/2000/svg">'
            . '<g><defs><rect id="target" width="1" height="1"/></defs></g></svg>',
        );
        $found = $doc->findById('target');
        self::assertInstanceOf(Rect::class, $found);
    }

    public function testFindByIdReturnsNullForMissingId(): void
    {
        $doc = $this->parser->parse('<svg xmlns="http://www.w3.org/2000/svg"/>');
        self::assertNull($doc->findById('nope'));
        self::assertNull($doc->findById(''));
    }

    public function testFindByIdReturnsFirstOnDuplicate(): void
    {
        // SVG 2: duplicate ids are technically invalid; pick the first
        // in document order — same shape browsers use.
        $doc = $this->parser->parse(
            '<svg xmlns="http://www.w3.org/2000/svg">'
            . '<rect id="dup" width="1" height="1"/>'
            . '<rect id="dup" width="2" height="2"/></svg>',
        );
        $found = $doc->findById('dup');
        self::assertInstanceOf(Rect::class, $found);
        self::assertSame(1.0, $found->width());
    }

    public function testUseResolveWalksTheIdIndex(): void
    {
        $doc = $this->parser->parse(
            '<svg xmlns="http://www.w3.org/2000/svg">'
            . '<defs><rect id="box" width="42" height="42"/></defs>'
            . '<use href="#box"/></svg>',
        );
        $use = $doc->children[1];
        self::assertInstanceOf(Use_::class, $use);
        $resolved = $use->resolve($doc);
        self::assertInstanceOf(Rect::class, $resolved);
        self::assertSame(42.0, $resolved->width());
    }

    public function testUseResolveReturnsNullForExternalRef(): void
    {
        $doc = $this->parser->parse(
            '<svg xmlns="http://www.w3.org/2000/svg"><use href="other.svg#x"/></svg>',
        );
        $use = $doc->children[0];
        self::assertInstanceOf(Use_::class, $use);
        self::assertNull($use->resolve($doc));
    }

    public function testIdIndexInvalidationRescansTree(): void
    {
        $doc = $this->parser->parse(
            '<svg xmlns="http://www.w3.org/2000/svg"><rect id="r1" width="1" height="1"/></svg>',
        );
        self::assertInstanceOf(Rect::class, $doc->findById('r1'));

        $newRect = new Rect();
        $newRect->setAttribute('id', 'r2');
        $doc->appendChild($newRect);

        // Cached index doesn't see the new id …
        self::assertNull($doc->findById('r2'));
        // … until we invalidate.
        $doc->invalidateIdIndex();
        self::assertInstanceOf(Rect::class, $doc->findById('r2'));
    }
}
