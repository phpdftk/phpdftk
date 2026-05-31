<?php

declare(strict_types=1);

namespace Phpdftk\Css\Tests;

use Phpdftk\Css\Value\CssFunction;
use Phpdftk\Css\Value\ElementFunction;
use Phpdftk\Css\ValueParser;
use PHPUnit\Framework\TestCase;

/**
 * CSS Generated Content for Paged Media 3 §4.2 — `element()`
 * typed parser. Companion of `string()` for running-element
 * insertion into page margin boxes.
 */
final class ElementFunctionTest extends TestCase
{
    private ValueParser $parser;

    protected function setUp(): void
    {
        $this->parser = new ValueParser();
    }

    public function testBareName(): void
    {
        $v = $this->parser->parseFromString('element(page-header)');
        self::assertInstanceOf(ElementFunction::class, $v);
        self::assertSame('page-header', $v->name);
        self::assertSame('first', $v->target);
    }

    public function testWithFetchTarget(): void
    {
        $v = $this->parser->parseFromString('element(page-header, last)');
        self::assertInstanceOf(ElementFunction::class, $v);
        self::assertSame('last', $v->target);
    }

    public function testMissingNameRejected(): void
    {
        $v = $this->parser->parseFromString('element()');
        self::assertNotInstanceOf(ElementFunction::class, $v);
        self::assertInstanceOf(CssFunction::class, $v);
    }

    public function testUnknownTargetRejected(): void
    {
        $v = $this->parser->parseFromString('element(name, occasionally)');
        self::assertNotInstanceOf(ElementFunction::class, $v);
    }

    public function testTooManyArgsRejected(): void
    {
        $v = $this->parser->parseFromString('element(a, first, extra)');
        self::assertNotInstanceOf(ElementFunction::class, $v);
    }

    public function testRoundTrip(): void
    {
        $v = $this->parser->parseFromString('element(page-header, start)');
        self::assertInstanceOf(ElementFunction::class, $v);
        self::assertSame('element(page-header, start)', $v->toCss());
    }
}
