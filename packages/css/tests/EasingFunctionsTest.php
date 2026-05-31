<?php

declare(strict_types=1);

namespace Phpdftk\Css\Tests;

use Phpdftk\Css\Value\CssFunction;
use Phpdftk\Css\Value\CubicBezier;
use Phpdftk\Css\Value\StepsEasing;
use Phpdftk\Css\Value\StepsJumpTerm;
use Phpdftk\Css\ValueParser;
use PHPUnit\Framework\TestCase;

/**
 * CSS Easing 1 §3.4/§3.5 — cubic-bezier() + steps() typed values.
 */
final class EasingFunctionsTest extends TestCase
{
    private ValueParser $parser;

    protected function setUp(): void
    {
        $this->parser = new ValueParser();
    }

    // -----------------------------------------------------------------------
    // cubic-bezier()
    // -----------------------------------------------------------------------

    public function testCubicBezierBasic(): void
    {
        $v = $this->parser->parseFromString('cubic-bezier(0.25, 0.1, 0.25, 1)');
        self::assertInstanceOf(CubicBezier::class, $v);
        self::assertSame(0.25, $v->x1);
        self::assertSame(0.1, $v->y1);
        self::assertSame(0.25, $v->x2);
        self::assertSame(1.0, $v->y2);
    }

    public function testCubicBezierWithOvershootY(): void
    {
        // y components may be outside [0,1] for spring-like easings.
        $v = $this->parser->parseFromString('cubic-bezier(0.5, -0.3, 0.5, 1.3)');
        self::assertInstanceOf(CubicBezier::class, $v);
        self::assertSame(-0.3, $v->y1);
        self::assertSame(1.3, $v->y2);
    }

    public function testCubicBezierXOutOfRangeRejected(): void
    {
        // x1 > 1 — invalid per §3.4.
        $v = $this->parser->parseFromString('cubic-bezier(1.5, 0, 0.5, 1)');
        self::assertNotInstanceOf(CubicBezier::class, $v);
        self::assertInstanceOf(CssFunction::class, $v);
    }

    public function testCubicBezierTooFewArgsRejected(): void
    {
        $v = $this->parser->parseFromString('cubic-bezier(0, 0, 1)');
        self::assertNotInstanceOf(CubicBezier::class, $v);
    }

    public function testCubicBezierToCss(): void
    {
        $v = $this->parser->parseFromString('cubic-bezier(0.25, 0.1, 0.25, 1)');
        self::assertInstanceOf(CubicBezier::class, $v);
        self::assertSame('cubic-bezier(0.25, 0.1, 0.25, 1)', $v->toCss());
    }

    // -----------------------------------------------------------------------
    // steps()
    // -----------------------------------------------------------------------

    public function testStepsCountOnlyDefaultsToEnd(): void
    {
        $v = $this->parser->parseFromString('steps(4)');
        self::assertInstanceOf(StepsEasing::class, $v);
        self::assertSame(4, $v->count);
        self::assertSame(StepsJumpTerm::End, $v->jumpTerm);
    }

    public function testStepsWithJumpStart(): void
    {
        $v = $this->parser->parseFromString('steps(3, start)');
        self::assertInstanceOf(StepsEasing::class, $v);
        self::assertSame(StepsJumpTerm::Start, $v->jumpTerm);
    }

    public function testStepsWithJumpNone(): void
    {
        $v = $this->parser->parseFromString('steps(5, jump-none)');
        self::assertInstanceOf(StepsEasing::class, $v);
        self::assertSame(StepsJumpTerm::JumpNone, $v->jumpTerm);
    }

    public function testStepsWithJumpBoth(): void
    {
        $v = $this->parser->parseFromString('steps(2, jump-both)');
        self::assertInstanceOf(StepsEasing::class, $v);
        self::assertSame(StepsJumpTerm::JumpBoth, $v->jumpTerm);
    }

    public function testStepsNonIntegerCountRejected(): void
    {
        $v = $this->parser->parseFromString('steps(2.5)');
        self::assertNotInstanceOf(StepsEasing::class, $v);
    }

    public function testStepsZeroCountRejected(): void
    {
        $v = $this->parser->parseFromString('steps(0)');
        self::assertNotInstanceOf(StepsEasing::class, $v);
    }

    public function testStepsNegativeCountRejected(): void
    {
        $v = $this->parser->parseFromString('steps(-1)');
        self::assertNotInstanceOf(StepsEasing::class, $v);
    }

    public function testStepsUnknownJumpTermRejected(): void
    {
        $v = $this->parser->parseFromString('steps(4, sideways)');
        self::assertNotInstanceOf(StepsEasing::class, $v);
    }

    public function testStepsToCss(): void
    {
        $v = $this->parser->parseFromString('steps(4)');
        self::assertInstanceOf(StepsEasing::class, $v);
        self::assertSame('steps(4)', $v->toCss());

        $v = $this->parser->parseFromString('steps(3, jump-none)');
        self::assertInstanceOf(StepsEasing::class, $v);
        self::assertSame('steps(3, jump-none)', $v->toCss());
    }
}
