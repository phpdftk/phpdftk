<?php

declare(strict_types=1);

namespace Phpdftk\Pdf\Writer\Tests\Writer;

use Phpdftk\Pdf\Core\Content\ContentStream;
use Phpdftk\Pdf\Writer\PathBuilder;
use PHPUnit\Framework\TestCase;

class PathBuilderTest extends TestCase
{
    private function replay(callable $build): string
    {
        $builder = new PathBuilder();
        $build($builder);
        $cs = new ContentStream();
        $builder->replayTo($cs);
        return implode("\n", $cs->getOperators());
    }

    public function testFluentReturnsSelf(): void
    {
        $b = new PathBuilder();
        $this->assertSame($b, $b->moveTo(0, 0));
        $this->assertSame($b, $b->lineTo(1, 1));
        $this->assertSame($b, $b->curveTo(1, 1, 2, 2, 3, 3));
        $this->assertSame($b, $b->quadCurveTo(1, 1, 2, 2));
        $this->assertSame($b, $b->arcTo(0, 0, 1, 0, 90));
        $this->assertSame($b, $b->close());
    }

    public function testMoveToEmitsMoveOperator(): void
    {
        $out = $this->replay(fn(PathBuilder $p) => $p->moveTo(10.5, 20.25));
        $this->assertStringContainsString('10.5 20.25 m', $out);
    }

    public function testLineToEmitsLineOperator(): void
    {
        $out = $this->replay(fn(PathBuilder $p) => $p->moveTo(0, 0)->lineTo(100, 200));
        $this->assertStringContainsString('100 200 l', $out);
    }

    public function testCurveToEmitsCubicCurveOperator(): void
    {
        $out = $this->replay(
            fn(PathBuilder $p) => $p->moveTo(0, 0)->curveTo(10, 20, 30, 40, 50, 60),
        );
        $this->assertStringContainsString('10 20 30 40 50 60 c', $out);
    }

    public function testQuadCurveToConvertsToCubic(): void
    {
        // Quad with cp (60, 120), end (90, 0), from current pt (0, 0).
        // Expected: cp1 = (40, 80), cp2 = (70, 80), end = (90, 0)
        $out = $this->replay(
            fn(PathBuilder $p) => $p->moveTo(0, 0)->quadCurveTo(60, 120, 90, 0),
        );
        $this->assertMatchesRegularExpression('/40 80 70 80 90 0 c/', $out);
    }

    public function testArcToFullCircleEmitsCurves(): void
    {
        // Full circle (0..360) at origin radius 50
        $out = $this->replay(
            fn(PathBuilder $p) => $p->moveTo(50, 0)->arcTo(0, 0, 50, 0, 360),
        );
        // Should emit 4 cubic curves (one per 90-degree segment)
        $cCount = substr_count($out, ' c');
        $this->assertGreaterThanOrEqual(4, $cCount);
    }

    public function testArcToZeroAngleNoCurvesEmitted(): void
    {
        // start == end means total angle 0 → segments rounds to 0 → return early
        $out = $this->replay(
            fn(PathBuilder $p) => $p->moveTo(0, 0)->arcTo(0, 0, 10, 0, 0),
        );
        $this->assertSame(0, substr_count($out, ' c'));
    }

    public function testArcToEmitsLineToStartWhenCurrentPointDiffers(): void
    {
        $out = $this->replay(
            // Move pen to a point far from the arc's start, then arc.
            fn(PathBuilder $p) => $p->moveTo(999, 999)->arcTo(0, 0, 10, 0, 90),
        );
        // The replay should emit a lineTo (l) to the arc's starting point before drawing curves.
        $this->assertMatchesRegularExpression('/10 0 l/', $out);
    }

    public function testArcToCounterclockwiseWraps(): void
    {
        // endDeg less than startDeg: parser adds 2pi so still positive arc.
        $out = $this->replay(
            fn(PathBuilder $p) => $p->moveTo(10, 0)->arcTo(0, 0, 10, 350, 10),
        );
        // total angle = 2pi - (350-10)deg = 20 degrees → 1 segment
        $this->assertSame(1, substr_count($out, ' c'));
    }

    public function testCloseEmitsHOperator(): void
    {
        $out = $this->replay(fn(PathBuilder $p) => $p->moveTo(0, 0)->lineTo(10, 10)->close());
        $this->assertStringContainsString("\nh", "\n" . $out);
    }

    public function testEmptyBuilderEmitsNothing(): void
    {
        $out = $this->replay(fn(PathBuilder $p) => $p);
        $this->assertSame('', $out);
    }

    public function testChainedComplexPath(): void
    {
        $out = $this->replay(function (PathBuilder $p) {
            $p->moveTo(0, 0)
                ->lineTo(100, 0)
                ->curveTo(100, 50, 50, 100, 0, 100)
                ->quadCurveTo(-25, 50, 0, 0)
                ->close();
        });
        $this->assertStringContainsString('0 0 m', $out);
        $this->assertStringContainsString('100 0 l', $out);
        $this->assertStringContainsString('100 50 50 100 0 100 c', $out);
        $this->assertStringContainsString(' c', $out);
        $this->assertMatchesRegularExpression('/\bh\b/', $out);
    }

    public function testNegativeCoordinatesArePreserved(): void
    {
        $out = $this->replay(
            fn(PathBuilder $p) => $p->moveTo(-50, -100)->lineTo(-25.5, -75.5),
        );
        $this->assertStringContainsString('-50 -100 m', $out);
        $this->assertStringContainsString('-25.5 -75.5 l', $out);
    }

    public function testLargeNumberOfOperationsReplay(): void
    {
        $builder = new PathBuilder();
        for ($i = 0; $i < 50; $i++) {
            $builder->lineTo((float) $i, (float) $i);
        }
        $cs = new ContentStream();
        $builder->replayTo($cs);
        $this->assertCount(50, $cs->getOperators());
    }

    public function testArcToHalfCircleEmitsTwoSegments(): void
    {
        $out = $this->replay(
            fn(PathBuilder $p) => $p->moveTo(10, 0)->arcTo(0, 0, 10, 0, 180),
        );
        $this->assertSame(2, substr_count($out, ' c'));
    }
}
