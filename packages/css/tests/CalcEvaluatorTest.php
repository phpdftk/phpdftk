<?php

declare(strict_types=1);

namespace Phpdftk\Css\Tests;

use Phpdftk\Css\Cascade\CalcEvaluator;
use Phpdftk\Css\Cascade\LengthContext;
use Phpdftk\Css\Value\Calc;
use Phpdftk\Css\Value\CalcBinary;
use Phpdftk\Css\Value\CalcExpression;
use Phpdftk\Css\Value\CalcFunc;
use Phpdftk\Css\Value\CalcFunction;
use Phpdftk\Css\Value\CalcLeaf;
use Phpdftk\Css\Value\CalcOp;
use Phpdftk\Css\Value\Integer;
use Phpdftk\Css\Value\Length;
use Phpdftk\Css\Value\LengthUnit;
use Phpdftk\Css\Value\Number;
use Phpdftk\Css\Value\Percentage;
use Phpdftk\Css\Value\Value;
use PHPUnit\Framework\TestCase;

/**
 * Covers {@see CalcEvaluator} — leaf resolution, the four binary
 * operators, every supported math function, NaN propagation / deferral,
 * and {@see CalcEvaluator::resolveValue}. NaN here means "unresolvable —
 * leave the Calc for later resolution", so the failure modes are as
 * important as the happy paths.
 */
final class CalcEvaluatorTest extends TestCase
{
    private static function ctx(float $percentageBasis = 0.0): LengthContext
    {
        return new LengthContext(percentageBasis: $percentageBasis);
    }

    private static function num(float $n): CalcLeaf
    {
        return new CalcLeaf(new Number($n));
    }

    /** @param list<CalcExpression> $args */
    private static function fn(CalcFunction $f, array $args): CalcFunc
    {
        return new CalcFunc($f, $args);
    }

    private static function evalExpr(CalcExpression $e, float $basis = 0.0): float
    {
        return CalcEvaluator::eval($e, self::ctx($basis));
    }

    // ---- Failure modes / deferral ----------------------------------------

    public function testDivisionByZeroIsNan(): void
    {
        $e = new CalcBinary(self::num(10.0), CalcOp::Div, self::num(0.0));
        self::assertNan(self::evalExpr($e));
    }

    public function testPercentageWithoutBasisDefersAsNan(): void
    {
        // Zero basis = "unknown to us": NAN so the caller leaves the Calc
        // for paint-time resolution.
        $e = new CalcLeaf(new Percentage(50.0));
        self::assertNan(self::evalExpr($e, 0.0));
    }

    public function testNanOperandPropagatesThroughBinary(): void
    {
        // left is an unresolvable percentage; the sum must stay NAN, not
        // silently treat it as 0.
        $e = new CalcBinary(new CalcLeaf(new Percentage(50.0)), CalcOp::Add, self::num(5.0));
        self::assertNan(self::evalExpr($e, 0.0));
    }

    public function testEmptyMinMaxHypotAreNan(): void
    {
        self::assertNan(self::evalExpr(self::fn(CalcFunction::Min, [])));
        self::assertNan(self::evalExpr(self::fn(CalcFunction::Max, [])));
        self::assertNan(self::evalExpr(self::fn(CalcFunction::Hypot, [])));
    }

    public function testNanArgumentShortCircuitsFunction(): void
    {
        // A NAN arg anywhere makes the whole function NAN.
        $e = self::fn(CalcFunction::Max, [self::num(1.0), new CalcLeaf(new Percentage(10.0))]);
        self::assertNan(self::evalExpr($e, 0.0));
    }

    public function testUnsupportedFunctionsAreNan(): void
    {
        foreach ([CalcFunction::Round, CalcFunction::Mod, CalcFunction::Rem] as $f) {
            self::assertNan(self::evalExpr(self::fn($f, [self::num(1.0), self::num(2.0)])), $f->name);
        }
    }

    public function testSqrtOfNegativeIsNan(): void
    {
        self::assertNan(self::evalExpr(self::fn(CalcFunction::Sqrt, [self::num(-4.0)])));
    }

    public function testWrongArityIsNan(): void
    {
        self::assertNan(self::evalExpr(self::fn(CalcFunction::Clamp, [self::num(1.0), self::num(2.0)])));
        self::assertNan(self::evalExpr(self::fn(CalcFunction::Pow, [self::num(2.0)])));
        self::assertNan(self::evalExpr(self::fn(CalcFunction::Abs, [self::num(1.0), self::num(2.0)])));
        self::assertNan(self::evalExpr(self::fn(CalcFunction::Atan2, [self::num(1.0)])));
    }

    public function testResolveValueKeepsUnresolvableCalcUntouched(): void
    {
        $calc = new Calc(new CalcBinary(self::num(1.0), CalcOp::Div, self::num(0.0)));
        self::assertSame($calc, CalcEvaluator::resolveValue($calc, self::ctx()));
    }

    public function testResolveValueReturnsNonCalcUntouched(): void
    {
        $len = new Length(5.0, LengthUnit::Px);
        self::assertSame($len, CalcEvaluator::resolveValue($len, self::ctx()));
    }

    // ---- Binary arithmetic ------------------------------------------------

    public function testFourOperators(): void
    {
        self::assertSame(7.0, self::evalExpr(new CalcBinary(self::num(3.0), CalcOp::Add, self::num(4.0))));
        self::assertSame(-1.0, self::evalExpr(new CalcBinary(self::num(3.0), CalcOp::Sub, self::num(4.0))));
        self::assertSame(12.0, self::evalExpr(new CalcBinary(self::num(3.0), CalcOp::Mul, self::num(4.0))));
        self::assertSame(2.5, self::evalExpr(new CalcBinary(self::num(10.0), CalcOp::Div, self::num(4.0))));
    }

    public function testNestedExpressionEvaluatesInnerFirst(): void
    {
        // (2 + 3) * 4 = 20
        $inner = new CalcBinary(self::num(2.0), CalcOp::Add, self::num(3.0));
        $e = new CalcBinary($inner, CalcOp::Mul, self::num(4.0));
        self::assertSame(20.0, self::evalExpr($e));
    }

    // ---- Leaf resolution --------------------------------------------------

    public function testLeafResolvesLengthNumberIntegerAndPercentage(): void
    {
        self::assertSame(10.0, self::evalExpr(new CalcLeaf(new Length(10.0, LengthUnit::Px))));
        self::assertSame(2.5, self::evalExpr(new CalcLeaf(new Number(2.5))));
        self::assertSame(3.0, self::evalExpr(new CalcLeaf(new Integer(3))));
        // 50% of basis 200 = 100.
        self::assertSame(100.0, self::evalExpr(new CalcLeaf(new Percentage(50.0)), 200.0));
    }

    public function testNestedCalcLeafEvaluates(): void
    {
        // A Calc value nested inside a leaf recurses through evaluate().
        $nested = new Calc(new CalcBinary(self::num(6.0), CalcOp::Mul, self::num(7.0)));
        self::assertSame(42.0, self::evalExpr(new CalcLeaf($nested)));
    }

    // ---- Math functions ---------------------------------------------------

    public function testMinMaxClampAbsSign(): void
    {
        self::assertSame(1.0, self::evalExpr(self::fn(CalcFunction::Min, [self::num(3.0), self::num(1.0), self::num(2.0)])));
        self::assertSame(3.0, self::evalExpr(self::fn(CalcFunction::Max, [self::num(3.0), self::num(1.0), self::num(2.0)])));
        // single-arg min/max return the lone value (PHP min() would throw).
        self::assertSame(5.0, self::evalExpr(self::fn(CalcFunction::Min, [self::num(5.0)])));
        self::assertSame(5.0, self::evalExpr(self::fn(CalcFunction::Max, [self::num(5.0)])));
        // clamp(min, val, max): below, within, above.
        self::assertSame(0.0, self::evalExpr(self::fn(CalcFunction::Clamp, [self::num(0.0), self::num(-5.0), self::num(10.0)])));
        self::assertSame(4.0, self::evalExpr(self::fn(CalcFunction::Clamp, [self::num(0.0), self::num(4.0), self::num(10.0)])));
        self::assertSame(10.0, self::evalExpr(self::fn(CalcFunction::Clamp, [self::num(0.0), self::num(50.0), self::num(10.0)])));
        self::assertSame(7.0, self::evalExpr(self::fn(CalcFunction::Abs, [self::num(-7.0)])));
        self::assertSame(-1.0, self::evalExpr(self::fn(CalcFunction::Sign, [self::num(-3.0)])));
        self::assertSame(1.0, self::evalExpr(self::fn(CalcFunction::Sign, [self::num(3.0)])));
        self::assertSame(0.0, self::evalExpr(self::fn(CalcFunction::Sign, [self::num(0.0)])));
    }

    public function testExponentialFunctions(): void
    {
        self::assertSame(5.0, self::evalExpr(self::fn(CalcFunction::Hypot, [self::num(3.0), self::num(4.0)])));
        self::assertSame(3.0, self::evalExpr(self::fn(CalcFunction::Sqrt, [self::num(9.0)])));
        self::assertSame(1024.0, self::evalExpr(self::fn(CalcFunction::Pow, [self::num(2.0), self::num(10.0)])));
        self::assertSame(1.0, self::evalExpr(self::fn(CalcFunction::Exp, [self::num(0.0)])));
        self::assertEqualsWithDelta(1.0, self::evalExpr(self::fn(CalcFunction::Log, [self::num(M_E)])), 1e-9);
        // two-arg log is log base: log(8, 2) = 3.
        self::assertEqualsWithDelta(3.0, self::evalExpr(self::fn(CalcFunction::Log, [self::num(8.0), self::num(2.0)])), 1e-9);
    }

    public function testTrigFunctionsTakeRadians(): void
    {
        self::assertEqualsWithDelta(0.0, self::evalExpr(self::fn(CalcFunction::Sin, [self::num(0.0)])), 1e-9);
        self::assertEqualsWithDelta(1.0, self::evalExpr(self::fn(CalcFunction::Cos, [self::num(0.0)])), 1e-9);
        self::assertEqualsWithDelta(1.0, self::evalExpr(self::fn(CalcFunction::Tan, [self::num(M_PI / 4.0)])), 1e-9);
        self::assertEqualsWithDelta(M_PI / 2.0, self::evalExpr(self::fn(CalcFunction::Asin, [self::num(1.0)])), 1e-9);
        self::assertEqualsWithDelta(0.0, self::evalExpr(self::fn(CalcFunction::Acos, [self::num(1.0)])), 1e-9);
        self::assertEqualsWithDelta(M_PI / 4.0, self::evalExpr(self::fn(CalcFunction::Atan, [self::num(1.0)])), 1e-9);
        self::assertEqualsWithDelta(M_PI / 4.0, self::evalExpr(self::fn(CalcFunction::Atan2, [self::num(1.0), self::num(1.0)])), 1e-9);
    }

    // ---- Top-level evaluate / resolveValue -------------------------------

    public function testEvaluateAndResolveValueReduceToLength(): void
    {
        $calc = new Calc(new CalcBinary(
            new CalcLeaf(new Length(10.0, LengthUnit::Px)),
            CalcOp::Add,
            new CalcLeaf(new Length(5.0, LengthUnit::Px)),
        ));
        self::assertSame(15.0, CalcEvaluator::evaluate($calc, self::ctx()));

        $resolved = CalcEvaluator::resolveValue($calc, self::ctx());
        self::assertInstanceOf(Length::class, $resolved);
        self::assertSame(15.0, $resolved->value);
        self::assertSame(LengthUnit::Px, $resolved->unit);
    }
}
