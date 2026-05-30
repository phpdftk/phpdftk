<?php

declare(strict_types=1);

namespace Phpdftk\Css\Tests;

use Phpdftk\Css\Value\Angle;
use Phpdftk\Css\Value\AngleUnit;
use Phpdftk\Css\Value\Calc;
use Phpdftk\Css\Value\CalcBinary;
use Phpdftk\Css\Value\CalcFunc;
use Phpdftk\Css\Value\CalcFunction;
use Phpdftk\Css\Value\CalcLeaf;
use Phpdftk\Css\Value\CalcOp;
use Phpdftk\Css\Value\Color;
use Phpdftk\Css\Value\ColorSpace;
use Phpdftk\Css\Value\CustomProperty;
use Phpdftk\Css\Value\GradientShape;
use Phpdftk\Css\Value\Integer;
use Phpdftk\Css\Value\Length;
use Phpdftk\Css\Value\LengthUnit;
use Phpdftk\Css\Value\LinearGradient;
use Phpdftk\Css\Value\MatrixTransform;
use Phpdftk\Css\Value\Number;
use Phpdftk\Css\Value\Percentage;
use Phpdftk\Css\Value\RadialGradient;
use Phpdftk\Css\Value\RotateTransform;
use Phpdftk\Css\Value\ScaleTransform;
use Phpdftk\Css\Value\SkewTransform;
use Phpdftk\Css\Value\Transform;
use Phpdftk\Css\Value\TranslateTransform;
use Phpdftk\Css\ValueParser;
use PHPUnit\Framework\TestCase;

/**
 * Phase 1A.2-bis: deferred value parsers — calc / gradient / transform /
 * color() / var() / angles.
 */
final class AdvancedValueParserTest extends TestCase
{
    private ValueParser $parser;

    protected function setUp(): void
    {
        $this->parser = new ValueParser();
    }

    // ============================================================
    // Angle dimensions
    // ============================================================

    public function testAngleDegrees(): void
    {
        $v = $this->parser->parseFromString('45deg');
        self::assertInstanceOf(Angle::class, $v);
        self::assertSame(45.0, $v->value);
        self::assertSame(AngleUnit::Deg, $v->unit);
    }

    public function testAngleRadians(): void
    {
        $v = $this->parser->parseFromString('1.5708rad');
        self::assertInstanceOf(Angle::class, $v);
        self::assertEqualsWithDelta(90.0, $v->toDegrees(), 0.01);
    }

    public function testAngleTurns(): void
    {
        $v = $this->parser->parseFromString('0.25turn');
        self::assertInstanceOf(Angle::class, $v);
        self::assertEqualsWithDelta(90.0, $v->toDegrees(), 0.01);
    }

    public function testAngleGrads(): void
    {
        $v = $this->parser->parseFromString('100grad');
        self::assertInstanceOf(Angle::class, $v);
        self::assertEqualsWithDelta(90.0, $v->toDegrees(), 0.01);
    }

    // ============================================================
    // var()
    // ============================================================

    public function testVarReferenceOnly(): void
    {
        $v = $this->parser->parseFromString('var(--primary)');
        self::assertInstanceOf(CustomProperty::class, $v);
        self::assertSame('--primary', $v->name);
        self::assertNull($v->fallback);
    }

    public function testVarWithFallback(): void
    {
        $v = $this->parser->parseFromString('var(--primary, blue)');
        self::assertInstanceOf(CustomProperty::class, $v);
        self::assertSame('--primary', $v->name);
        self::assertInstanceOf(Color::class, $v->fallback);
    }

    public function testVarWithComplexFallback(): void
    {
        $v = $this->parser->parseFromString('var(--space, 1rem 2rem)');
        self::assertInstanceOf(CustomProperty::class, $v);
        self::assertNotNull($v->fallback);
    }

    // ============================================================
    // color() function
    // ============================================================

    public function testColorFunctionSrgb(): void
    {
        $v = $this->parser->parseFromString('color(srgb 1 0 0.5)');
        self::assertInstanceOf(Color::class, $v);
        self::assertSame(1.0, $v->r);
        self::assertSame(0.0, $v->g);
        self::assertSame(0.5, $v->b);
        self::assertSame(ColorSpace::sRGB, $v->space);
    }

    public function testColorFunctionDisplayP3(): void
    {
        $v = $this->parser->parseFromString('color(display-p3 0.8 0.2 0.4)');
        self::assertInstanceOf(Color::class, $v);
        self::assertSame(ColorSpace::DisplayP3, $v->space);
    }

    public function testColorFunctionWithAlpha(): void
    {
        $v = $this->parser->parseFromString('color(srgb 1 0 0 / 0.5)');
        self::assertInstanceOf(Color::class, $v);
        self::assertSame(0.5, $v->a);
    }

    public function testColorFunctionWithPercentages(): void
    {
        $v = $this->parser->parseFromString('color(rec2020 50% 0% 25%)');
        self::assertInstanceOf(Color::class, $v);
        self::assertSame(ColorSpace::Rec2020, $v->space);
        self::assertEqualsWithDelta(0.5, $v->r, 0.001);
        self::assertEqualsWithDelta(0.25, $v->b, 0.001);
    }

    public function testColorFunctionSrgbLinear(): void
    {
        $v = $this->parser->parseFromString('color(srgb-linear 0.5 0.25 0.75)');
        self::assertInstanceOf(Color::class, $v);
        self::assertSame(ColorSpace::sRGBLinear, $v->space);
        self::assertEqualsWithDelta(0.5, $v->r, 1e-9);
        self::assertEqualsWithDelta(0.25, $v->g, 1e-9);
        self::assertEqualsWithDelta(0.75, $v->b, 1e-9);
    }

    public function testColorFunctionXyzD65(): void
    {
        $v = $this->parser->parseFromString('color(xyz-d65 0.4 0.5 0.6)');
        self::assertInstanceOf(Color::class, $v);
        self::assertSame(ColorSpace::XYZD65, $v->space);
    }

    public function testColorFunctionXyzAliasMapsToD65(): void
    {
        // CSS Color 4 §10.7 — `xyz` is an alias for `xyz-d65`.
        $v = $this->parser->parseFromString('color(xyz 0.3 0.5 0.4)');
        self::assertInstanceOf(Color::class, $v);
        self::assertSame(ColorSpace::XYZD65, $v->space);
    }

    public function testColorFunctionXyzD50(): void
    {
        $v = $this->parser->parseFromString('color(xyz-d50 0.4 0.5 0.6)');
        self::assertInstanceOf(Color::class, $v);
        self::assertSame(ColorSpace::XYZD50, $v->space);
    }

    public function testColorFunctionXyzPreservesValuesAboveOne(): void
    {
        // XYZ Y can exceed 1 for HDR / out-of-gamut colors.
        $v = $this->parser->parseFromString('color(xyz 0.4 1.5 0.6)');
        self::assertInstanceOf(Color::class, $v);
        self::assertEqualsWithDelta(1.5, $v->g, 1e-9);
    }

    public function testColorFunctionPreservesOutOfGamutSrgb(): void
    {
        // CSS Color 4 §6 allows out-of-gamut sRGB; values are
        // preserved for the gamut-mapping algorithm to handle.
        $v = $this->parser->parseFromString('color(srgb 1.2 -0.1 0.5)');
        self::assertInstanceOf(Color::class, $v);
        self::assertEqualsWithDelta(1.2, $v->r, 1e-9);
        self::assertEqualsWithDelta(-0.1, $v->g, 1e-9);
    }

    public function testColorFunctionNoneResolvesToZero(): void
    {
        $v = $this->parser->parseFromString('color(srgb none none none)');
        self::assertInstanceOf(Color::class, $v);
        self::assertSame(0.0, $v->r);
        self::assertSame(0.0, $v->g);
        self::assertSame(0.0, $v->b);
    }

    // ============================================================
    // calc()
    // ============================================================

    public function testCalcAddition(): void
    {
        $v = $this->parser->parseFromString('calc(10px + 20px)');
        self::assertInstanceOf(Calc::class, $v);
        self::assertInstanceOf(CalcBinary::class, $v->expression);
        self::assertSame(CalcOp::Add, $v->expression->op);
        self::assertInstanceOf(CalcLeaf::class, $v->expression->left);
        self::assertInstanceOf(Length::class, $v->expression->left->value);
    }

    public function testCalcMultiplication(): void
    {
        $v = $this->parser->parseFromString('calc(10px * 2)');
        self::assertInstanceOf(Calc::class, $v);
        self::assertInstanceOf(CalcBinary::class, $v->expression);
        self::assertSame(CalcOp::Mul, $v->expression->op);
    }

    public function testCalcPrecedence(): void
    {
        // calc(10 + 2 * 3) → 10 + (2 * 3)
        $v = $this->parser->parseFromString('calc(10 + 2 * 3)');
        self::assertInstanceOf(Calc::class, $v);
        self::assertInstanceOf(CalcBinary::class, $v->expression);
        self::assertSame(CalcOp::Add, $v->expression->op);
        // Right side should be the 2*3 product.
        self::assertInstanceOf(CalcBinary::class, $v->expression->right);
        self::assertSame(CalcOp::Mul, $v->expression->right->op);
    }

    public function testCalcParentheses(): void
    {
        // calc((10 + 2) * 3) — explicit grouping inverts default precedence.
        $v = $this->parser->parseFromString('calc((10 + 2) * 3)');
        self::assertInstanceOf(Calc::class, $v);
        self::assertInstanceOf(CalcBinary::class, $v->expression);
        self::assertSame(CalcOp::Mul, $v->expression->op);
        self::assertInstanceOf(CalcBinary::class, $v->expression->left);
        self::assertSame(CalcOp::Add, $v->expression->left->op);
    }

    public function testCalcMixedUnits(): void
    {
        $v = $this->parser->parseFromString('calc(100% - 20px)');
        self::assertInstanceOf(Calc::class, $v);
        self::assertInstanceOf(CalcBinary::class, $v->expression);
        self::assertSame(CalcOp::Sub, $v->expression->op);
    }

    public function testMinFunction(): void
    {
        $v = $this->parser->parseFromString('min(10px, 50%)');
        self::assertInstanceOf(Calc::class, $v);
        self::assertInstanceOf(CalcFunc::class, $v->expression);
        self::assertSame(CalcFunction::Min, $v->expression->func);
        self::assertCount(2, $v->expression->args);
    }

    public function testMaxFunction(): void
    {
        $v = $this->parser->parseFromString('max(10px, 1rem, 1em)');
        self::assertInstanceOf(Calc::class, $v);
        self::assertInstanceOf(CalcFunc::class, $v->expression);
        self::assertSame(CalcFunction::Max, $v->expression->func);
        self::assertCount(3, $v->expression->args);
    }

    public function testClampFunction(): void
    {
        $v = $this->parser->parseFromString('clamp(1rem, 2.5vw, 3rem)');
        self::assertInstanceOf(Calc::class, $v);
        self::assertInstanceOf(CalcFunc::class, $v->expression);
        self::assertSame(CalcFunction::Clamp, $v->expression->func);
        self::assertCount(3, $v->expression->args);
    }

    public function testNestedCalc(): void
    {
        $v = $this->parser->parseFromString('calc(min(10px, 20px) * 2)');
        self::assertInstanceOf(Calc::class, $v);
        self::assertInstanceOf(CalcBinary::class, $v->expression);
        self::assertSame(CalcOp::Mul, $v->expression->op);
        self::assertInstanceOf(CalcFunc::class, $v->expression->left);
        self::assertSame(CalcFunction::Min, $v->expression->left->func);
    }

    // ============================================================
    // linear-gradient
    // ============================================================

    public function testLinearGradientWithStops(): void
    {
        $v = $this->parser->parseFromString('linear-gradient(red, blue)');
        self::assertInstanceOf(LinearGradient::class, $v);
        self::assertSame(180.0, $v->angleDeg);
        self::assertCount(2, $v->stops);
    }

    public function testLinearGradientWithAngle(): void
    {
        $v = $this->parser->parseFromString('linear-gradient(45deg, red, blue)');
        self::assertInstanceOf(LinearGradient::class, $v);
        self::assertSame(45.0, $v->angleDeg);
    }

    public function testLinearGradientWithSide(): void
    {
        $v = $this->parser->parseFromString('linear-gradient(to right, red, blue)');
        self::assertInstanceOf(LinearGradient::class, $v);
        self::assertSame(90.0, $v->angleDeg);
    }

    public function testLinearGradientWithStopPositions(): void
    {
        $v = $this->parser->parseFromString('linear-gradient(red 0%, yellow 50%, blue 100%)');
        self::assertInstanceOf(LinearGradient::class, $v);
        self::assertCount(3, $v->stops);
        self::assertInstanceOf(Percentage::class, $v->stops[1]->position);
        self::assertSame(50.0, $v->stops[1]->position->value);
    }

    public function testRepeatingLinearGradient(): void
    {
        $v = $this->parser->parseFromString('repeating-linear-gradient(45deg, red 0px, red 10px, blue 10px, blue 20px)');
        self::assertInstanceOf(LinearGradient::class, $v);
        self::assertTrue($v->repeating);
    }

    // ============================================================
    // radial-gradient
    // ============================================================

    public function testRadialGradientSimple(): void
    {
        $v = $this->parser->parseFromString('radial-gradient(red, blue)');
        self::assertInstanceOf(RadialGradient::class, $v);
        self::assertSame(GradientShape::Ellipse, $v->shape);
        self::assertCount(2, $v->stops);
    }

    public function testRadialGradientCircle(): void
    {
        $v = $this->parser->parseFromString('radial-gradient(circle, red, blue)');
        self::assertInstanceOf(RadialGradient::class, $v);
        self::assertSame(GradientShape::Circle, $v->shape);
    }

    public function testRadialGradientWithPosition(): void
    {
        $v = $this->parser->parseFromString('radial-gradient(circle at 50px 50px, red, blue)');
        self::assertInstanceOf(RadialGradient::class, $v);
        self::assertSame(GradientShape::Circle, $v->shape);
        self::assertNotNull($v->centerX);
        self::assertSame(50.0, $v->centerX->value);
    }

    // ============================================================
    // Transforms
    // ============================================================

    public function testTransformTranslateSingle(): void
    {
        $v = $this->parser->parseTransform('translate(10px)');
        self::assertInstanceOf(Transform::class, $v);
        self::assertCount(1, $v->functions);
        self::assertInstanceOf(TranslateTransform::class, $v->functions[0]);
        self::assertInstanceOf(Length::class, $v->functions[0]->x);
        self::assertSame(10.0, $v->functions[0]->x->value);
    }

    public function testTransformTranslatePair(): void
    {
        $v = $this->parser->parseTransform('translate(10px, 20px)');
        self::assertInstanceOf(Transform::class, $v);
        self::assertInstanceOf(TranslateTransform::class, $v->functions[0]);
        self::assertSame(20.0, $v->functions[0]->y->value);
    }

    public function testTransformRotate(): void
    {
        $v = $this->parser->parseTransform('rotate(45deg)');
        self::assertInstanceOf(Transform::class, $v);
        self::assertInstanceOf(RotateTransform::class, $v->functions[0]);
        self::assertSame(45.0, $v->functions[0]->angleDeg);
    }

    public function testTransformScale(): void
    {
        $v = $this->parser->parseTransform('scale(1.5)');
        self::assertInstanceOf(Transform::class, $v);
        self::assertInstanceOf(ScaleTransform::class, $v->functions[0]);
        self::assertSame(1.5, $v->functions[0]->sx);
        self::assertSame(1.5, $v->functions[0]->sy);
    }

    public function testTransformScaleNonUniform(): void
    {
        $v = $this->parser->parseTransform('scale(2, 0.5)');
        self::assertInstanceOf(Transform::class, $v);
        self::assertInstanceOf(ScaleTransform::class, $v->functions[0]);
        self::assertSame(2.0, $v->functions[0]->sx);
        self::assertSame(0.5, $v->functions[0]->sy);
    }

    public function testTransformSkew(): void
    {
        $v = $this->parser->parseTransform('skew(15deg, 10deg)');
        self::assertInstanceOf(Transform::class, $v);
        self::assertInstanceOf(SkewTransform::class, $v->functions[0]);
        self::assertSame(15.0, $v->functions[0]->xDeg);
        self::assertSame(10.0, $v->functions[0]->yDeg);
    }

    public function testTransformMatrix(): void
    {
        $v = $this->parser->parseTransform('matrix(1, 0, 0, 1, 10, 20)');
        self::assertInstanceOf(Transform::class, $v);
        self::assertInstanceOf(MatrixTransform::class, $v->functions[0]);
        self::assertSame(10.0, $v->functions[0]->e);
        self::assertSame(20.0, $v->functions[0]->f);
    }

    public function testTransformChain(): void
    {
        // Multiple functions compose left-to-right.
        $v = $this->parser->parseTransform('rotate(45deg) translate(10px, 20px) scale(2)');
        self::assertInstanceOf(Transform::class, $v);
        self::assertCount(3, $v->functions);
        self::assertInstanceOf(RotateTransform::class, $v->functions[0]);
        self::assertInstanceOf(TranslateTransform::class, $v->functions[1]);
        self::assertInstanceOf(ScaleTransform::class, $v->functions[2]);
    }

    public function testTransformWithRadiansAngle(): void
    {
        $v = $this->parser->parseTransform('rotate(0.5turn)');
        self::assertInstanceOf(Transform::class, $v);
        self::assertInstanceOf(RotateTransform::class, $v->functions[0]);
        self::assertEqualsWithDelta(180.0, $v->functions[0]->angleDeg, 0.01);
    }
}
