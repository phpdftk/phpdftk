<?php

declare(strict_types=1);

namespace Phpdftk\Css\Tests;

use Phpdftk\Css\Value\CssFunction;
use Phpdftk\Css\Value\LinearEasing;
use Phpdftk\Css\Value\LinearEasingStop;
use Phpdftk\Css\ValueParser;
use PHPUnit\Framework\TestCase;

/**
 * CSS Easing 2 §3.1/§3.2 `linear()` easing function parser.
 * Stored as a list of (output, optional input %) stops; the
 * animation/transition engine builds the piecewise-linear ease
 * function from these at evaluation time.
 */
final class LinearEasingTest extends TestCase
{
    private ValueParser $parser;

    protected function setUp(): void
    {
        $this->parser = new ValueParser();
    }

    private function parseLinear(string $css): LinearEasing
    {
        $value = $this->parser->parseFromString($css);
        self::assertInstanceOf(LinearEasing::class, $value, "expected LinearEasing, got " . get_debug_type($value));
        return $value;
    }

    public function testBasicTwoStop(): void
    {
        $l = $this->parseLinear('linear(0, 1)');
        self::assertCount(2, $l->stops);
        self::assertSame(0.0, $l->stops[0]->output);
        self::assertNull($l->stops[0]->inputPercent);
        self::assertSame(1.0, $l->stops[1]->output);
    }

    public function testThreeStopWithMiddleAnchor(): void
    {
        $l = $this->parseLinear('linear(0, 0.5 50%, 1)');
        self::assertCount(3, $l->stops);
        self::assertSame(0.5, $l->stops[1]->output);
        self::assertSame(50.0, $l->stops[1]->inputPercent);
        self::assertNull($l->stops[0]->inputPercent);
        self::assertNull($l->stops[2]->inputPercent);
    }

    public function testExplicitAnchors(): void
    {
        $l = $this->parseLinear('linear(0 0%, 1 100%)');
        self::assertCount(2, $l->stops);
        self::assertSame(0.0, $l->stops[0]->inputPercent);
        self::assertSame(100.0, $l->stops[1]->inputPercent);
    }

    public function testRangeForm(): void
    {
        // CSS Easing 2 §3.2 — `<output> <from%> <to%>` emits two
        // stops sharing the output (horizontal segment).
        $l = $this->parseLinear('linear(0, 0.25 25% 50%, 1)');
        // 4 stops: 0, 0.25@25%, 0.25@50%, 1.
        self::assertCount(4, $l->stops);
        self::assertSame(0.25, $l->stops[1]->output);
        self::assertSame(25.0, $l->stops[1]->inputPercent);
        self::assertSame(0.25, $l->stops[2]->output);
        self::assertSame(50.0, $l->stops[2]->inputPercent);
    }

    public function testNegativeOutputAccepted(): void
    {
        // Output values can overshoot — common for spring-like
        // easings.
        $l = $this->parseLinear('linear(0, -0.2 25%, 1.2 75%, 1)');
        self::assertSame(-0.2, $l->stops[1]->output);
        self::assertSame(1.2, $l->stops[2]->output);
    }

    public function testNonNumericOutputRejected(): void
    {
        $value = $this->parser->parseFromString('linear(a, b)');
        self::assertNotInstanceOf(LinearEasing::class, $value);
        self::assertInstanceOf(CssFunction::class, $value);
    }

    public function testEmptyArgsRejected(): void
    {
        $value = $this->parser->parseFromString('linear()');
        self::assertNotInstanceOf(LinearEasing::class, $value);
    }

    public function testTooManyAnchorsInStopRejected(): void
    {
        // 4 parts in a stop (output + 3 anchors) is malformed.
        $value = $this->parser->parseFromString('linear(0 0% 25% 50%)');
        self::assertNotInstanceOf(LinearEasing::class, $value);
    }

    public function testToCssRoundTrip(): void
    {
        $l = $this->parseLinear('linear(0, 0.5 50%, 1)');
        self::assertSame('linear(0, 0.5 50%, 1)', $l->toCss());
    }
}
