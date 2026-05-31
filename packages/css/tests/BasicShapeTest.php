<?php

declare(strict_types=1);

namespace Phpdftk\Css\Tests;

use Phpdftk\Css\Value\CircleShape;
use Phpdftk\Css\Value\CssFunction;
use Phpdftk\Css\Value\EllipseShape;
use Phpdftk\Css\Value\InsetShape;
use Phpdftk\Css\Value\Keyword;
use Phpdftk\Css\Value\Length;
use Phpdftk\Css\Value\Percentage;
use Phpdftk\Css\Value\PolygonShape;
use Phpdftk\Css\ValueParser;
use PHPUnit\Framework\TestCase;

/**
 * CSS Shapes 1 §3 basic-shape value typed parsing. Used by
 * `clip-path`, `shape-outside`, `offset-path`.
 */
final class BasicShapeTest extends TestCase
{
    private ValueParser $parser;

    protected function setUp(): void
    {
        $this->parser = new ValueParser();
    }

    // -----------------------------------------------------------------------
    // circle()
    // -----------------------------------------------------------------------

    public function testCircleEmpty(): void
    {
        $v = $this->parser->parseFromString('circle()');
        self::assertInstanceOf(CircleShape::class, $v);
        self::assertNull($v->radius);
        self::assertNull($v->centerX);
    }

    public function testCircleWithRadius(): void
    {
        $v = $this->parser->parseFromString('circle(50px)');
        self::assertInstanceOf(CircleShape::class, $v);
        self::assertInstanceOf(Length::class, $v->radius);
        self::assertSame(50.0, $v->radius->value);
    }

    public function testCircleWithPercentageRadius(): void
    {
        $v = $this->parser->parseFromString('circle(25%)');
        self::assertInstanceOf(CircleShape::class, $v);
        self::assertInstanceOf(Percentage::class, $v->radius);
    }

    public function testCircleWithKeywordRadius(): void
    {
        $v = $this->parser->parseFromString('circle(closest-side)');
        self::assertInstanceOf(CircleShape::class, $v);
        self::assertInstanceOf(Keyword::class, $v->radius);
        self::assertSame('closest-side', $v->radius->name);
    }

    public function testCircleWithRadiusAndPosition(): void
    {
        $v = $this->parser->parseFromString('circle(50px at 25% 75%)');
        self::assertInstanceOf(CircleShape::class, $v);
        self::assertSame(50.0, $v->radius->value);
        self::assertInstanceOf(Percentage::class, $v->centerX);
        self::assertSame(25.0, $v->centerX->value);
        self::assertSame(75.0, $v->centerY->value);
    }

    public function testCircleWithPositionOnly(): void
    {
        $v = $this->parser->parseFromString('circle(at center)');
        self::assertInstanceOf(CircleShape::class, $v);
        self::assertNull($v->radius);
        self::assertInstanceOf(Keyword::class, $v->centerX);
        self::assertSame('center', $v->centerX->name);
    }

    // -----------------------------------------------------------------------
    // ellipse()
    // -----------------------------------------------------------------------

    public function testEllipseEmpty(): void
    {
        $v = $this->parser->parseFromString('ellipse()');
        self::assertInstanceOf(EllipseShape::class, $v);
    }

    public function testEllipseWithTwoRadii(): void
    {
        $v = $this->parser->parseFromString('ellipse(100px 50px)');
        self::assertInstanceOf(EllipseShape::class, $v);
        self::assertSame(100.0, $v->radiusX->value);
        self::assertSame(50.0, $v->radiusY->value);
    }

    public function testEllipseWithRadiiAndPosition(): void
    {
        $v = $this->parser->parseFromString('ellipse(50% 25% at 50% 50%)');
        self::assertInstanceOf(EllipseShape::class, $v);
        self::assertSame(50.0, $v->radiusX->value);
        self::assertSame(25.0, $v->radiusY->value);
        self::assertSame(50.0, $v->centerX->value);
    }

    public function testEllipseOneRadiusRejected(): void
    {
        // Spec: must have both radii or none.
        $v = $this->parser->parseFromString('ellipse(50px)');
        self::assertNotInstanceOf(EllipseShape::class, $v);
    }

    // -----------------------------------------------------------------------
    // inset()
    // -----------------------------------------------------------------------

    public function testInsetSingleValue(): void
    {
        $v = $this->parser->parseFromString('inset(10px)');
        self::assertInstanceOf(InsetShape::class, $v);
        self::assertCount(1, $v->insets);
        self::assertSame(10.0, $v->insets[0]->value);
        self::assertNull($v->borderRadius);
    }

    public function testInsetFourValues(): void
    {
        $v = $this->parser->parseFromString('inset(10px 20px 30px 40px)');
        self::assertInstanceOf(InsetShape::class, $v);
        self::assertCount(4, $v->insets);
    }

    public function testInsetWithBorderRadius(): void
    {
        $v = $this->parser->parseFromString('inset(10px round 5px)');
        self::assertInstanceOf(InsetShape::class, $v);
        self::assertNotNull($v->borderRadius);
        self::assertCount(1, $v->borderRadius);
    }

    public function testInsetEmptyRejected(): void
    {
        $v = $this->parser->parseFromString('inset()');
        self::assertNotInstanceOf(InsetShape::class, $v);
    }

    public function testInsetTooManyValuesRejected(): void
    {
        $v = $this->parser->parseFromString('inset(1px 2px 3px 4px 5px)');
        self::assertNotInstanceOf(InsetShape::class, $v);
    }

    // -----------------------------------------------------------------------
    // polygon()
    // -----------------------------------------------------------------------

    public function testPolygonTwoVertices(): void
    {
        $v = $this->parser->parseFromString('polygon(0 0, 100% 100%)');
        self::assertInstanceOf(PolygonShape::class, $v);
        self::assertSame('nonzero', $v->fillRule);
        self::assertCount(2, $v->vertices);
    }

    public function testPolygonWithEvenoddFillRule(): void
    {
        $v = $this->parser->parseFromString('polygon(evenodd, 0 0, 100% 0, 50% 100%)');
        self::assertInstanceOf(PolygonShape::class, $v);
        self::assertSame('evenodd', $v->fillRule);
        self::assertCount(3, $v->vertices);
    }

    public function testPolygonWithNonzeroFillRule(): void
    {
        // nonzero is default but explicit form should also parse.
        $v = $this->parser->parseFromString('polygon(nonzero, 0 0, 100% 100%)');
        self::assertInstanceOf(PolygonShape::class, $v);
        self::assertSame('nonzero', $v->fillRule);
    }

    public function testPolygonInvalidVertexRejected(): void
    {
        // Single-value vertex (should be x + y).
        $v = $this->parser->parseFromString('polygon(0)');
        self::assertNotInstanceOf(PolygonShape::class, $v);
    }

    public function testPolygonEmptyRejected(): void
    {
        $v = $this->parser->parseFromString('polygon()');
        self::assertNotInstanceOf(PolygonShape::class, $v);
    }

    // -----------------------------------------------------------------------
    // toCss round-trips
    // -----------------------------------------------------------------------

    public function testCircleToCssRoundTrip(): void
    {
        $v = $this->parser->parseFromString('circle(50px at 25% 75%)');
        self::assertInstanceOf(CircleShape::class, $v);
        self::assertSame('circle(50px at 25% 75%)', $v->toCss());
    }

    public function testInsetToCssRoundTrip(): void
    {
        $v = $this->parser->parseFromString('inset(10px round 5px)');
        self::assertInstanceOf(InsetShape::class, $v);
        self::assertSame('inset(10px round 5px)', $v->toCss());
    }

    public function testPolygonToCssRoundTrip(): void
    {
        $v = $this->parser->parseFromString('polygon(0 0, 100% 0, 50% 100%)');
        self::assertInstanceOf(PolygonShape::class, $v);
        self::assertSame('polygon(0 0, 100% 0, 50% 100%)', $v->toCss());
    }
}
