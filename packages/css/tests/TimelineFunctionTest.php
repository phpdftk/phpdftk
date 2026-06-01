<?php

declare(strict_types=1);

namespace Phpdftk\Css\Tests;

use Phpdftk\Css\Value\CssFunction;
use Phpdftk\Css\Value\ScrollTimeline;
use Phpdftk\Css\Value\ViewTimeline;
use Phpdftk\Css\ValueParser;
use PHPUnit\Framework\TestCase;

/**
 * CSS Scroll-driven Animations 1 §3.2 (`scroll()`) + §4.2
 * (`view()`) typed parsers for the anonymous timeline functions
 * inside `animation-timeline`.
 */
final class TimelineFunctionTest extends TestCase
{
    private ValueParser $parser;

    protected function setUp(): void
    {
        $this->parser = new ValueParser();
    }

    public function testViewEmpty(): void
    {
        $v = $this->parser->parseFromString('view()');
        self::assertInstanceOf(ViewTimeline::class, $v);
        self::assertNull($v->axis);
        self::assertNull($v->insetStart);
        self::assertNull($v->insetEnd);
    }

    public function testViewAxisOnly(): void
    {
        $v = $this->parser->parseFromString('view(block)');
        self::assertInstanceOf(ViewTimeline::class, $v);
        self::assertSame('block', $v->axis);
    }

    public function testViewAxisAndInset(): void
    {
        $v = $this->parser->parseFromString('view(inline 20%)');
        self::assertInstanceOf(ViewTimeline::class, $v);
        self::assertSame('inline', $v->axis);
        self::assertInstanceOf(\Phpdftk\Css\Value\Percentage::class, $v->insetStart);
    }

    public function testViewTwoInsets(): void
    {
        $v = $this->parser->parseFromString('view(20% 50%)');
        self::assertInstanceOf(ViewTimeline::class, $v);
        self::assertInstanceOf(\Phpdftk\Css\Value\Percentage::class, $v->insetStart);
        self::assertInstanceOf(\Phpdftk\Css\Value\Percentage::class, $v->insetEnd);
    }

    public function testScrollEmpty(): void
    {
        $v = $this->parser->parseFromString('scroll()');
        self::assertInstanceOf(ScrollTimeline::class, $v);
        self::assertNull($v->scroller);
        self::assertNull($v->axis);
    }

    public function testScrollScrollerOnly(): void
    {
        $v = $this->parser->parseFromString('scroll(root)');
        self::assertInstanceOf(ScrollTimeline::class, $v);
        self::assertSame('root', $v->scroller);
    }

    public function testScrollScrollerAndAxis(): void
    {
        $v = $this->parser->parseFromString('scroll(nearest block)');
        self::assertInstanceOf(ScrollTimeline::class, $v);
        self::assertSame('nearest', $v->scroller);
        self::assertSame('block', $v->axis);
    }

    public function testScrollRejectsUnknownKeyword(): void
    {
        $v = $this->parser->parseFromString('scroll(weird)');
        self::assertNotInstanceOf(ScrollTimeline::class, $v);
        self::assertInstanceOf(CssFunction::class, $v);
    }

    public function testViewRoundTrip(): void
    {
        $v = $this->parser->parseFromString('view(inline 20%)');
        self::assertSame('view(inline 20%)', $v->toCss());
    }

    public function testScrollRoundTrip(): void
    {
        $v = $this->parser->parseFromString('scroll(nearest block)');
        self::assertSame('scroll(nearest block)', $v->toCss());
    }
}
