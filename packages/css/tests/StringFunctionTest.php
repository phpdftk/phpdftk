<?php

declare(strict_types=1);

namespace Phpdftk\Css\Tests;

use Phpdftk\Css\Value\CssFunction;
use Phpdftk\Css\Value\StringFunction;
use Phpdftk\Css\ValueParser;
use PHPUnit\Framework\TestCase;

/**
 * CSS Generated Content for Paged Media 3 §5.2 — `string()`
 * typed parser. Used in @page margin boxes to emit the current
 * value of a string-set name, the canonical running-header
 * pattern.
 */
final class StringFunctionTest extends TestCase
{
    private ValueParser $parser;

    protected function setUp(): void
    {
        $this->parser = new ValueParser();
    }

    public function testBareName(): void
    {
        $v = $this->parser->parseFromString('string(chapter)');
        self::assertInstanceOf(StringFunction::class, $v);
        self::assertSame('chapter', $v->name);
        self::assertSame('first', $v->target);
    }

    public function testWithStartTarget(): void
    {
        $v = $this->parser->parseFromString('string(chapter, start)');
        self::assertInstanceOf(StringFunction::class, $v);
        self::assertSame('start', $v->target);
    }

    public function testWithLastTarget(): void
    {
        $v = $this->parser->parseFromString('string(chapter, last)');
        self::assertInstanceOf(StringFunction::class, $v);
        self::assertSame('last', $v->target);
    }

    public function testWithFirstExceptTarget(): void
    {
        $v = $this->parser->parseFromString('string(chapter, first-except)');
        self::assertInstanceOf(StringFunction::class, $v);
        self::assertSame('first-except', $v->target);
    }

    public function testMissingNameRejected(): void
    {
        $v = $this->parser->parseFromString('string()');
        self::assertNotInstanceOf(StringFunction::class, $v);
        self::assertInstanceOf(CssFunction::class, $v);
    }

    public function testUnknownTargetKeywordRejected(): void
    {
        $v = $this->parser->parseFromString('string(chapter, sometimes)');
        self::assertNotInstanceOf(StringFunction::class, $v);
    }

    public function testTooManyArgsRejected(): void
    {
        $v = $this->parser->parseFromString('string(a, first, extra)');
        self::assertNotInstanceOf(StringFunction::class, $v);
    }

    public function testRoundTripBare(): void
    {
        $v = $this->parser->parseFromString('string(chapter)');
        self::assertInstanceOf(StringFunction::class, $v);
        self::assertSame('string(chapter)', $v->toCss());
    }

    public function testRoundTripWithTarget(): void
    {
        $v = $this->parser->parseFromString('string(chapter, last)');
        self::assertInstanceOf(StringFunction::class, $v);
        self::assertSame('string(chapter, last)', $v->toCss());
    }
}
