<?php

declare(strict_types=1);

namespace Phpdftk\Css\Tests;

use Phpdftk\Css\Value\AttrFunction;
use Phpdftk\Css\Value\CssFunction;
use Phpdftk\Css\Value\EnvFunction;
use Phpdftk\Css\Value\Keyword;
use Phpdftk\Css\Value\Length;
use Phpdftk\Css\Value\Percentage;
use Phpdftk\Css\Value\StringValue;
use Phpdftk\Css\ValueParser;
use PHPUnit\Framework\TestCase;

/**
 * CSS Values 5 §11 `attr()` + Environment Variables 1 §3 `env()`
 * — typed parser surface. Both functions resolve at computed-
 * value time; this layer is the declarative storage so the
 * cascade preserves the declaration for the engine to consume.
 */
final class AttrEnvTest extends TestCase
{
    private ValueParser $parser;

    protected function setUp(): void
    {
        $this->parser = new ValueParser();
    }

    // -----------------------------------------------------------------------
    // attr()
    // -----------------------------------------------------------------------

    public function testAttrBareName(): void
    {
        $v = $this->parser->parseFromString('attr(data-name)');
        self::assertInstanceOf(AttrFunction::class, $v);
        self::assertSame('data-name', $v->attributeName);
        self::assertNull($v->typeOrUnit);
        self::assertNull($v->fallback);
    }

    public function testAttrWithStringType(): void
    {
        $v = $this->parser->parseFromString('attr(data-name string)');
        self::assertInstanceOf(AttrFunction::class, $v);
        self::assertSame('string', $v->typeOrUnit);
    }

    public function testAttrWithUnit(): void
    {
        $v = $this->parser->parseFromString('attr(data-width px)');
        self::assertInstanceOf(AttrFunction::class, $v);
        self::assertSame('px', $v->typeOrUnit);
    }

    public function testAttrWithFallback(): void
    {
        $v = $this->parser->parseFromString('attr(data-name string, "(none)")');
        self::assertInstanceOf(AttrFunction::class, $v);
        self::assertInstanceOf(StringValue::class, $v->fallback);
        self::assertSame('(none)', $v->fallback->value);
    }

    public function testAttrWithUnitAndLengthFallback(): void
    {
        $v = $this->parser->parseFromString('attr(data-w px, 100px)');
        self::assertInstanceOf(AttrFunction::class, $v);
        self::assertSame('px', $v->typeOrUnit);
        self::assertInstanceOf(Length::class, $v->fallback);
        self::assertSame(100.0, $v->fallback->value);
    }

    public function testAttrWithColorTypeAndKeywordFallback(): void
    {
        $v = $this->parser->parseFromString('attr(data-color color, currentcolor)');
        self::assertInstanceOf(AttrFunction::class, $v);
        self::assertSame('color', $v->typeOrUnit);
        self::assertInstanceOf(Keyword::class, $v->fallback);
        self::assertSame('currentcolor', $v->fallback->name);
    }

    public function testAttrEmptyArgsRejected(): void
    {
        $v = $this->parser->parseFromString('attr()');
        self::assertNotInstanceOf(AttrFunction::class, $v);
        self::assertInstanceOf(CssFunction::class, $v);
    }

    public function testAttrTooManyArgsRejected(): void
    {
        $v = $this->parser->parseFromString('attr(a, b, c)');
        self::assertNotInstanceOf(AttrFunction::class, $v);
    }

    // -----------------------------------------------------------------------
    // env()
    // -----------------------------------------------------------------------

    public function testEnvBareName(): void
    {
        $v = $this->parser->parseFromString('env(safe-area-inset-top)');
        self::assertInstanceOf(EnvFunction::class, $v);
        self::assertSame('safe-area-inset-top', $v->name);
        self::assertSame([], $v->indices);
        self::assertNull($v->fallback);
    }

    public function testEnvWithFallback(): void
    {
        $v = $this->parser->parseFromString('env(safe-area-inset-top, 12px)');
        self::assertInstanceOf(EnvFunction::class, $v);
        self::assertInstanceOf(Length::class, $v->fallback);
        self::assertSame(12.0, $v->fallback->value);
    }

    public function testEnvWithIndexedReference(): void
    {
        $v = $this->parser->parseFromString('env(viewport-segment-width 0 1)');
        self::assertInstanceOf(EnvFunction::class, $v);
        self::assertSame([0, 1], $v->indices);
    }

    public function testEnvWithIndexAndFallback(): void
    {
        $v = $this->parser->parseFromString('env(viewport-segment-width 0 1, 100%)');
        self::assertInstanceOf(EnvFunction::class, $v);
        self::assertSame([0, 1], $v->indices);
        self::assertInstanceOf(Percentage::class, $v->fallback);
        self::assertSame(100.0, $v->fallback->value);
    }

    public function testEnvNonIntegerIndexRejected(): void
    {
        $v = $this->parser->parseFromString('env(viewport-segment 1.5)');
        self::assertNotInstanceOf(EnvFunction::class, $v);
    }

    public function testEnvNoNameRejected(): void
    {
        $v = $this->parser->parseFromString('env()');
        self::assertNotInstanceOf(EnvFunction::class, $v);
    }

    // -----------------------------------------------------------------------
    // Round-trip
    // -----------------------------------------------------------------------

    public function testAttrToCssRoundTripsAll(): void
    {
        $css = 'attr(data-name string, "fallback")';
        $v = $this->parser->parseFromString($css);
        self::assertInstanceOf(AttrFunction::class, $v);
        self::assertSame('attr(data-name string, "fallback")', $v->toCss());
    }

    public function testEnvToCssRoundTripsWithFallback(): void
    {
        $v = $this->parser->parseFromString('env(safe-area-inset-top, 12px)');
        self::assertInstanceOf(EnvFunction::class, $v);
        self::assertSame('env(safe-area-inset-top, 12px)', $v->toCss());
    }
}
