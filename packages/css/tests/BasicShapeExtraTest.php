<?php

declare(strict_types=1);

namespace Phpdftk\Css\Tests;

use Phpdftk\Css\Value\CssFunction;
use Phpdftk\Css\Value\Keyword;
use Phpdftk\Css\Value\Length;
use Phpdftk\Css\Value\Percentage;
use Phpdftk\Css\Value\PathShape;
use Phpdftk\Css\Value\RectShape;
use Phpdftk\Css\Value\XywhShape;
use Phpdftk\Css\ValueParser;
use PHPUnit\Framework\TestCase;

/**
 * CSS Shapes 1/2 — rect(), xywh(), path() typed parsers.
 * Complementary to BasicShapeTest which covers circle / ellipse
 * / inset / polygon.
 */
final class BasicShapeExtraTest extends TestCase
{
    private ValueParser $parser;

    protected function setUp(): void
    {
        $this->parser = new ValueParser();
    }

    // -----------------------------------------------------------------------
    // rect()
    // -----------------------------------------------------------------------

    public function testRectFourEdges(): void
    {
        $v = $this->parser->parseFromString('rect(0 100% 100% 0)');
        self::assertInstanceOf(RectShape::class, $v);
        self::assertCount(4, $v->edges);
        self::assertInstanceOf(Percentage::class, $v->edges[1]);
        self::assertSame(100.0, $v->edges[1]->value);
    }

    public function testRectWithAutoEdge(): void
    {
        $v = $this->parser->parseFromString('rect(0 auto 100px 0)');
        self::assertInstanceOf(RectShape::class, $v);
        self::assertInstanceOf(Keyword::class, $v->edges[1]);
        self::assertSame('auto', $v->edges[1]->name);
    }

    public function testRectWithBorderRadius(): void
    {
        $v = $this->parser->parseFromString('rect(10px 90% 90% 10px round 5px)');
        self::assertInstanceOf(RectShape::class, $v);
        self::assertNotNull($v->borderRadius);
        self::assertCount(1, $v->borderRadius);
    }

    public function testRectThreeEdgesRejected(): void
    {
        // rect() requires exactly 4 edges per CSS Shapes 2.
        $v = $this->parser->parseFromString('rect(0 10px 20px)');
        self::assertNotInstanceOf(RectShape::class, $v);
        self::assertInstanceOf(CssFunction::class, $v);
    }

    public function testRectToCssRoundTrip(): void
    {
        $v = $this->parser->parseFromString('rect(0 100% 100% 0)');
        self::assertInstanceOf(RectShape::class, $v);
        self::assertSame('rect(0 100% 100% 0)', $v->toCss());
    }

    // -----------------------------------------------------------------------
    // xywh()
    // -----------------------------------------------------------------------

    public function testXywhBasic(): void
    {
        $v = $this->parser->parseFromString('xywh(10px 20px 100% 80%)');
        self::assertInstanceOf(XywhShape::class, $v);
        self::assertSame(10.0, $v->x->value);
        self::assertSame(20.0, $v->y->value);
        self::assertInstanceOf(Percentage::class, $v->width);
        self::assertInstanceOf(Percentage::class, $v->height);
    }

    public function testXywhAllZeros(): void
    {
        $v = $this->parser->parseFromString('xywh(0 0 100% 100%)');
        self::assertInstanceOf(XywhShape::class, $v);
    }

    public function testXywhWithBorderRadius(): void
    {
        $v = $this->parser->parseFromString('xywh(10px 10px 90% 80% round 5px)');
        self::assertInstanceOf(XywhShape::class, $v);
        self::assertNotNull($v->borderRadius);
    }

    public function testXywhTooFewArgsRejected(): void
    {
        $v = $this->parser->parseFromString('xywh(0 0 100%)');
        self::assertNotInstanceOf(XywhShape::class, $v);
    }

    public function testXywhToCssRoundTrip(): void
    {
        $v = $this->parser->parseFromString('xywh(10px 10px 90% 80% round 5px)');
        self::assertInstanceOf(XywhShape::class, $v);
        self::assertSame('xywh(10px 10px 90% 80% round 5px)', $v->toCss());
    }

    // -----------------------------------------------------------------------
    // path()
    // -----------------------------------------------------------------------

    public function testPathBasic(): void
    {
        $v = $this->parser->parseFromString('path("M 0 0 L 100 100 Z")');
        self::assertInstanceOf(PathShape::class, $v);
        self::assertSame('nonzero', $v->fillRule);
        self::assertSame('M 0 0 L 100 100 Z', $v->pathData);
    }

    public function testPathWithEvenoddFillRule(): void
    {
        $v = $this->parser->parseFromString('path(evenodd, "M 0 0 L 100 0 L 0 100 Z")');
        self::assertInstanceOf(PathShape::class, $v);
        self::assertSame('evenodd', $v->fillRule);
    }

    public function testPathMissingStringRejected(): void
    {
        $v = $this->parser->parseFromString('path(evenodd, M 0 0)');
        // Path data must be a string literal.
        self::assertNotInstanceOf(PathShape::class, $v);
    }

    public function testPathEmptyRejected(): void
    {
        $v = $this->parser->parseFromString('path()');
        self::assertNotInstanceOf(PathShape::class, $v);
    }

    public function testPathToCssRoundTripsString(): void
    {
        $v = $this->parser->parseFromString('path("M 0 0 L 100 100")');
        self::assertInstanceOf(PathShape::class, $v);
        self::assertSame('path("M 0 0 L 100 100")', $v->toCss());
    }
}
