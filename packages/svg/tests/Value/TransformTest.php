<?php

declare(strict_types=1);

namespace Phpdftk\Svg\Tests\Value;

use Phpdftk\Svg\Value\Transform;
use Phpdftk\Svg\Value\Transform\Matrix;
use Phpdftk\Svg\Value\Transform\Rotate;
use Phpdftk\Svg\Value\Transform\Scale;
use Phpdftk\Svg\Value\Transform\SkewX;
use Phpdftk\Svg\Value\Transform\SkewY;
use Phpdftk\Svg\Value\Transform\Translate;
use PHPUnit\Framework\TestCase;

final class TransformTest extends TestCase
{
    private const float DELTA = 1.0e-12;

    public function testEmptyListProducesIdentityMatrix(): void
    {
        self::assertSame(
            [1.0, 0.0, 0.0, 1.0, 0.0, 0.0],
            (new Transform([]))->toMatrix(),
        );
    }

    public function testParsesMatrixSixArgumentsCommaSeparated(): void
    {
        $t = Transform::parse('matrix(1, 2, 3, 4, 5, 6)');
        self::assertCount(1, $t->functions);
        self::assertInstanceOf(Matrix::class, $t->functions[0]);
        self::assertSame([1.0, 2.0, 3.0, 4.0, 5.0, 6.0], $t->toMatrix());
    }

    public function testParsesMatrixSpaceSeparated(): void
    {
        $t = Transform::parse('matrix(1 0 0 1 0 0)');
        self::assertSame([1.0, 0.0, 0.0, 1.0, 0.0, 0.0], $t->toMatrix());
    }

    public function testParsesTranslateSingleArgument(): void
    {
        $t = Transform::parse('translate(10)');
        self::assertSame([1.0, 0.0, 0.0, 1.0, 10.0, 0.0], $t->toMatrix());
    }

    public function testParsesTranslateTwoArguments(): void
    {
        $t = Transform::parse('translate(10, 20)');
        self::assertSame([1.0, 0.0, 0.0, 1.0, 10.0, 20.0], $t->toMatrix());
    }

    public function testParsesScaleUniform(): void
    {
        $t = Transform::parse('scale(2)');
        self::assertSame([2.0, 0.0, 0.0, 2.0, 0.0, 0.0], $t->toMatrix());
    }

    public function testParsesScaleNonUniform(): void
    {
        $t = Transform::parse('scale(2, 3)');
        self::assertSame([2.0, 0.0, 0.0, 3.0, 0.0, 0.0], $t->toMatrix());
    }

    public function testParsesRotate90Degrees(): void
    {
        $t = Transform::parse('rotate(90)');
        $m = $t->toMatrix();
        // cos(90°) = 0, sin(90°) = 1 modulo floating-point noise.
        self::assertEqualsWithDelta(0.0, $m[0], self::DELTA);
        self::assertEqualsWithDelta(1.0, $m[1], self::DELTA);
        self::assertEqualsWithDelta(-1.0, $m[2], self::DELTA);
        self::assertEqualsWithDelta(0.0, $m[3], self::DELTA);
        self::assertSame(0.0, $m[4]);
        self::assertSame(0.0, $m[5]);
    }

    public function testRotateAroundCenterIsFixedPointAtCenter(): void
    {
        // A rotation around (cx, cy) by any angle must leave (cx, cy)
        // invariant — the canonical test of correct compose order for
        // T(cx,cy) · R(θ) · T(-cx,-cy).
        $cx = 100.0;
        $cy = 200.0;
        $t = Transform::parse('rotate(73, 100, 200)');
        [$a, $b, $c, $d, $e, $f] = $t->toMatrix();
        $xPrime = $a * $cx + $c * $cy + $e;
        $yPrime = $b * $cx + $d * $cy + $f;
        self::assertEqualsWithDelta($cx, $xPrime, self::DELTA);
        self::assertEqualsWithDelta($cy, $yPrime, self::DELTA);
    }

    public function testParsesSkewX45(): void
    {
        $t = Transform::parse('skewX(45)');
        $m = $t->toMatrix();
        self::assertSame(1.0, $m[0]);
        self::assertSame(0.0, $m[1]);
        self::assertEqualsWithDelta(1.0, $m[2], self::DELTA);
        self::assertSame(1.0, $m[3]);
        self::assertSame(0.0, $m[4]);
        self::assertSame(0.0, $m[5]);
    }

    public function testParsesSkewY45(): void
    {
        $t = Transform::parse('skewY(45)');
        $m = $t->toMatrix();
        self::assertSame(1.0, $m[0]);
        self::assertEqualsWithDelta(1.0, $m[1], self::DELTA);
        self::assertSame(0.0, $m[2]);
        self::assertSame(1.0, $m[3]);
        self::assertSame(0.0, $m[4]);
        self::assertSame(0.0, $m[5]);
    }

    public function testComposesMultipleFunctionsLeftToRight(): void
    {
        // `translate(10, 20) scale(2)` maps a unit-square point (1, 1)
        // → first scale (2, 2), then translate → (12, 22).
        $t = Transform::parse('translate(10, 20) scale(2)');
        [$a, $b, $c, $d, $e, $f] = $t->toMatrix();
        $x = 1.0;
        $y = 1.0;
        self::assertEqualsWithDelta(12.0, $a * $x + $c * $y + $e, self::DELTA);
        self::assertEqualsWithDelta(22.0, $b * $x + $d * $y + $f, self::DELTA);
    }

    public function testCommaSeparatorBetweenFunctionsTolerated(): void
    {
        $t = Transform::parse('translate(10, 20),rotate(0)');
        self::assertCount(2, $t->functions);
    }

    public function testDecompositionEquivalenceWithExplicitMatrices(): void
    {
        // `translate(10, 0) scale(2)` must equal the same composition
        // expressed via raw matrix() functions, modulo floating-point.
        $a = Transform::parse('translate(10, 0) scale(2)')->toMatrix();
        $b = Transform::parse('matrix(1, 0, 0, 1, 10, 0) matrix(2, 0, 0, 2, 0, 0)')->toMatrix();
        for ($i = 0; $i < 6; $i++) {
            self::assertEqualsWithDelta($a[$i], $b[$i], self::DELTA);
        }
    }

    public function testTranslateTyDefaultsToZero(): void
    {
        $fn = (new Translate(5.0))->toMatrix();
        self::assertSame([1.0, 0.0, 0.0, 1.0, 5.0, 0.0], $fn);
    }

    public function testScaleSyDefaultsToSx(): void
    {
        $fn = (new Scale(3.0))->toMatrix();
        self::assertSame([3.0, 0.0, 0.0, 3.0, 0.0, 0.0], $fn);
    }

    public function testRotateWithoutCenterRotatesAroundOrigin(): void
    {
        $fn = (new Rotate(0.0))->toMatrix();
        // 0° rotation is the identity rotation (the e/f columns stay 0).
        self::assertEqualsWithDelta(1.0, $fn[0], self::DELTA);
        self::assertEqualsWithDelta(0.0, $fn[1], self::DELTA);
        self::assertEqualsWithDelta(0.0, $fn[2], self::DELTA);
        self::assertEqualsWithDelta(1.0, $fn[3], self::DELTA);
        self::assertSame(0.0, $fn[4]);
        self::assertSame(0.0, $fn[5]);
    }

    public function testParsesNumberFormsWithExponentAndLeadingDot(): void
    {
        $t = Transform::parse('matrix(1e2 .5 -.5 1 0 0)');
        self::assertSame([100.0, 0.5, -0.5, 1.0, 0.0, 0.0], $t->toMatrix());
    }

    public function testMatrixWrongArgumentCountThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        Transform::parse('matrix(1, 2, 3)');
    }

    public function testUnknownFunctionThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        Transform::parse('rotate3d(1, 0, 0, 45)');
    }

    public function testUnterminatedFunctionThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        Transform::parse('translate(10');
    }

    public function testRotateWrongArgumentCountThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        Transform::parse('rotate(10, 20)'); // 2 args is invalid; spec requires 1 or 3
    }

    public function testSkewXClassDirectInstantiation(): void
    {
        self::assertEqualsWithDelta(1.0, (new SkewX(45.0))->toMatrix()[2], self::DELTA);
    }

    public function testSkewYClassDirectInstantiation(): void
    {
        self::assertEqualsWithDelta(1.0, (new SkewY(45.0))->toMatrix()[1], self::DELTA);
    }

    public function testMatrixClassDirectInstantiation(): void
    {
        $m = new Matrix(1.0, 2.0, 3.0, 4.0, 5.0, 6.0);
        self::assertSame([1.0, 2.0, 3.0, 4.0, 5.0, 6.0], $m->toMatrix());
    }
}
