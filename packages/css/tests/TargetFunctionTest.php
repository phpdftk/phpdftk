<?php

declare(strict_types=1);

namespace Phpdftk\Css\Tests;

use Phpdftk\Css\Value\CssFunction;
use Phpdftk\Css\Value\Keyword;
use Phpdftk\Css\Value\StringValue;
use Phpdftk\Css\Value\TargetFunction;
use Phpdftk\Css\Value\TargetFunctionKind;
use Phpdftk\Css\Value\Url;
use Phpdftk\Css\ValueParser;
use PHPUnit\Framework\TestCase;

/**
 * CSS Generated Content for Paged Media 3 §3 — target-counter,
 * target-counters, target-text typed parser surface.
 */
final class TargetFunctionTest extends TestCase
{
    private ValueParser $parser;

    protected function setUp(): void
    {
        $this->parser = new ValueParser();
    }

    public function testTargetCounterBare(): void
    {
        $v = $this->parser->parseFromString('target-counter(url(#chap1), page)');
        self::assertInstanceOf(TargetFunction::class, $v);
        self::assertSame(TargetFunctionKind::Counter, $v->kind);
        self::assertInstanceOf(Url::class, $v->target);
        self::assertSame('#chap1', $v->target->url);
        self::assertInstanceOf(Keyword::class, $v->name);
        self::assertSame('page', $v->name->name);
        self::assertNull($v->extra);
        self::assertNull($v->style);
    }

    public function testTargetCounterWithStyle(): void
    {
        $v = $this->parser->parseFromString('target-counter(url(#x), page, lower-roman)');
        self::assertInstanceOf(TargetFunction::class, $v);
        self::assertInstanceOf(Keyword::class, $v->style);
        self::assertSame('lower-roman', $v->style->name);
    }

    public function testTargetCountersWithSeparator(): void
    {
        $v = $this->parser->parseFromString('target-counters(url(#sec), section, ".")');
        self::assertInstanceOf(TargetFunction::class, $v);
        self::assertSame(TargetFunctionKind::Counters, $v->kind);
        self::assertInstanceOf(StringValue::class, $v->extra);
        self::assertSame('.', $v->extra->value);
        self::assertNull($v->style);
    }

    public function testTargetCountersWithSeparatorAndStyle(): void
    {
        $v = $this->parser->parseFromString('target-counters(url(#sec), section, ".", decimal-leading-zero)');
        self::assertInstanceOf(TargetFunction::class, $v);
        self::assertSame(TargetFunctionKind::Counters, $v->kind);
        self::assertInstanceOf(Keyword::class, $v->style);
        self::assertSame('decimal-leading-zero', $v->style->name);
    }

    public function testTargetTextBare(): void
    {
        $v = $this->parser->parseFromString('target-text(url(#chap1))');
        self::assertInstanceOf(TargetFunction::class, $v);
        self::assertSame(TargetFunctionKind::Text, $v->kind);
        self::assertNull($v->name);
        self::assertNull($v->extra);
    }

    public function testTargetTextWithContentKeyword(): void
    {
        foreach (['content', 'before', 'after', 'first-letter'] as $kw) {
            $v = $this->parser->parseFromString("target-text(url(#x), $kw)");
            self::assertInstanceOf(TargetFunction::class, $v, "kw=$kw");
            self::assertInstanceOf(Keyword::class, $v->extra);
            self::assertSame($kw, $v->extra->name);
        }
    }

    public function testTargetTextRejectsUnknownKeyword(): void
    {
        $v = $this->parser->parseFromString('target-text(url(#x), nope)');
        self::assertNotInstanceOf(TargetFunction::class, $v);
    }

    public function testTargetCounterRejectsMissingCounterName(): void
    {
        $v = $this->parser->parseFromString('target-counter(url(#x))');
        self::assertNotInstanceOf(TargetFunction::class, $v);
        self::assertInstanceOf(CssFunction::class, $v);
    }

    public function testTargetCountersRejectsMissingSeparator(): void
    {
        $v = $this->parser->parseFromString('target-counters(url(#x), section)');
        self::assertNotInstanceOf(TargetFunction::class, $v);
    }

    public function testTargetCountersRejectsNonStringSeparator(): void
    {
        $v = $this->parser->parseFromString('target-counters(url(#x), section, dot)');
        self::assertNotInstanceOf(TargetFunction::class, $v);
    }

    public function testTargetCounterRoundTrip(): void
    {
        $v = $this->parser->parseFromString('target-counter(url(#chap), page, lower-roman)');
        self::assertInstanceOf(TargetFunction::class, $v);
        self::assertSame('target-counter(url("#chap"), page, lower-roman)', $v->toCss());
    }

    public function testTargetCountersRoundTrip(): void
    {
        $v = $this->parser->parseFromString('target-counters(url(#sec), section, ".")');
        self::assertInstanceOf(TargetFunction::class, $v);
        self::assertSame('target-counters(url("#sec"), section, ".")', $v->toCss());
    }

    public function testTargetTextRoundTrip(): void
    {
        $v = $this->parser->parseFromString('target-text(url(#chap), content)');
        self::assertInstanceOf(TargetFunction::class, $v);
        self::assertSame('target-text(url("#chap"), content)', $v->toCss());
    }
}
