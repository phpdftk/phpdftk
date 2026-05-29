<?php

declare(strict_types=1);

namespace Phpdftk\Svg\Tests\Path;

use Phpdftk\Svg\Path\ArcTo;
use Phpdftk\Svg\Path\ClosePath;
use Phpdftk\Svg\Path\CurveTo;
use Phpdftk\Svg\Path\HorizontalLineTo;
use Phpdftk\Svg\Path\LineTo;
use Phpdftk\Svg\Path\MoveTo;
use Phpdftk\Svg\Path\PathData;
use Phpdftk\Svg\Path\QuadraticCurveTo;
use Phpdftk\Svg\Path\SmoothCurveTo;
use Phpdftk\Svg\Path\SmoothQuadraticCurveTo;
use Phpdftk\Svg\Path\VerticalLineTo;
use PHPUnit\Framework\TestCase;

final class PathDataTest extends TestCase
{
    public function testEmptyInputProducesEmptyCommandList(): void
    {
        self::assertSame([], PathData::parse('')->commands);
        self::assertSame([], PathData::parse('   ')->commands);
    }

    public function testParsesAbsoluteMoveTo(): void
    {
        $cmds = PathData::parse('M 10 20')->commands;
        self::assertCount(1, $cmds);
        self::assertInstanceOf(MoveTo::class, $cmds[0]);
        self::assertTrue($cmds[0]->absolute);
        self::assertSame(10.0, $cmds[0]->x);
        self::assertSame(20.0, $cmds[0]->y);
    }

    public function testParsesRelativeMoveToLowercase(): void
    {
        $cmds = PathData::parse('m 10 20')->commands;
        self::assertInstanceOf(MoveTo::class, $cmds[0]);
        self::assertFalse($cmds[0]->absolute);
    }

    public function testImplicitLineToAfterMoveTo(): void
    {
        // `M 0 0 10 10 20 20` is `M 0 0` followed by `L 10 10 L 20 20`.
        $cmds = PathData::parse('M 0 0 10 10 20 20')->commands;
        self::assertCount(3, $cmds);
        self::assertInstanceOf(MoveTo::class, $cmds[0]);
        $first = $cmds[1];
        $second = $cmds[2];
        self::assertInstanceOf(LineTo::class, $first);
        self::assertInstanceOf(LineTo::class, $second);
        self::assertTrue($first->absolute);
        self::assertSame([10.0, 10.0], [$first->x, $first->y]);
        self::assertSame([20.0, 20.0], [$second->x, $second->y]);
    }

    public function testImplicitLineToAfterLowercaseMoveStaysRelative(): void
    {
        $cmds = PathData::parse('m 0 0 10 10')->commands;
        $line = $cmds[1];
        self::assertInstanceOf(LineTo::class, $line);
        self::assertFalse($line->absolute);
    }

    public function testParsesAllLinearCommands(): void
    {
        $cmds = PathData::parse('M 0 0 L 1 2 H 3 V 4 Z')->commands;
        self::assertInstanceOf(MoveTo::class, $cmds[0]);
        self::assertInstanceOf(LineTo::class, $cmds[1]);
        $h = $cmds[2];
        self::assertInstanceOf(HorizontalLineTo::class, $h);
        self::assertSame(3.0, $h->x);
        $v = $cmds[3];
        self::assertInstanceOf(VerticalLineTo::class, $v);
        self::assertSame(4.0, $v->y);
        self::assertInstanceOf(ClosePath::class, $cmds[4]);
    }

    public function testParsesCubicAndSmoothCubic(): void
    {
        $cmds = PathData::parse('M 0 0 C 1 2 3 4 5 6 S 7 8 9 10')->commands;
        $c = $cmds[1];
        self::assertInstanceOf(CurveTo::class, $c);
        self::assertSame(
            [1.0, 2.0, 3.0, 4.0, 5.0, 6.0],
            [$c->x1, $c->y1, $c->x2, $c->y2, $c->x, $c->y],
        );
        $s = $cmds[2];
        self::assertInstanceOf(SmoothCurveTo::class, $s);
        self::assertSame([7.0, 8.0, 9.0, 10.0], [$s->x2, $s->y2, $s->x, $s->y]);
    }

    public function testParsesQuadraticAndSmoothQuadratic(): void
    {
        $cmds = PathData::parse('M 0 0 Q 1 2 3 4 T 5 6')->commands;
        $q = $cmds[1];
        self::assertInstanceOf(QuadraticCurveTo::class, $q);
        self::assertSame([1.0, 2.0, 3.0, 4.0], [$q->x1, $q->y1, $q->x, $q->y]);
        $t = $cmds[2];
        self::assertInstanceOf(SmoothQuadraticCurveTo::class, $t);
        self::assertSame([5.0, 6.0], [$t->x, $t->y]);
    }

    public function testParsesArcToWithCommaSeparatedFlags(): void
    {
        $cmds = PathData::parse('M 0 0 A 25 25 -30 0 1 50 -25')->commands;
        $a = $cmds[1];
        self::assertInstanceOf(ArcTo::class, $a);
        self::assertSame(25.0, $a->rx);
        self::assertSame(25.0, $a->ry);
        self::assertSame(-30.0, $a->xAxisRotation);
        self::assertFalse($a->largeArc);
        self::assertTrue($a->sweep);
        self::assertSame(50.0, $a->x);
        self::assertSame(-25.0, $a->y);
    }

    public function testArcFlagsCanRunTogetherWithoutSeparator(): void
    {
        // `01` is two flags: 0 then 1. Real-world generators emit this
        // form to save bytes.
        $cmds = PathData::parse('M0 0a25 25 -30 0150 -25')->commands;
        self::assertCount(2, $cmds);
        $a = $cmds[1];
        self::assertInstanceOf(ArcTo::class, $a);
        self::assertFalse($a->largeArc);
        self::assertTrue($a->sweep);
        self::assertSame(50.0, $a->x);
        self::assertSame(-25.0, $a->y);
    }

    public function testNoSeparatorRequiredBetweenCommandAndNumber(): void
    {
        $cmds = PathData::parse('M10 20L30 40')->commands;
        self::assertCount(2, $cmds);
        self::assertInstanceOf(MoveTo::class, $cmds[0]);
        self::assertInstanceOf(LineTo::class, $cmds[1]);
        self::assertSame([10.0, 20.0], [$cmds[0]->x, $cmds[0]->y]);
        self::assertSame([30.0, 40.0], [$cmds[1]->x, $cmds[1]->y]);
    }

    public function testNoSeparatorRequiredBetweenNumbersWhenSignFollows(): void
    {
        // `M10-20` is `M 10 -20` — the minus introduces the next number.
        $cmds = PathData::parse('M10-20')->commands;
        self::assertInstanceOf(MoveTo::class, $cmds[0]);
        self::assertSame([10.0, -20.0], [$cmds[0]->x, $cmds[0]->y]);
    }

    public function testNoSeparatorRequiredBetweenNumbersWhenDotFollows(): void
    {
        // `M1.5.5` is `M 1.5 .5` — first number consumes one dot, second
        // starts with the leading dot.
        $cmds = PathData::parse('M1.5.5')->commands;
        self::assertInstanceOf(MoveTo::class, $cmds[0]);
        self::assertSame([1.5, 0.5], [$cmds[0]->x, $cmds[0]->y]);
    }

    public function testExponentNotation(): void
    {
        $cmds = PathData::parse('M 1e2 1.5e-1')->commands;
        self::assertInstanceOf(MoveTo::class, $cmds[0]);
        self::assertSame([100.0, 0.15], [$cmds[0]->x, $cmds[0]->y]);
    }

    public function testCommaSeparatorsBetweenAllPieces(): void
    {
        $cmds = PathData::parse('M,1,2,L,3,4,Z')->commands;
        self::assertCount(3, $cmds);
        self::assertInstanceOf(MoveTo::class, $cmds[0]);
        self::assertInstanceOf(LineTo::class, $cmds[1]);
        self::assertInstanceOf(ClosePath::class, $cmds[2]);
    }

    public function testRelativeImplicitLineToRepeatsLowercase(): void
    {
        $cmds = PathData::parse('l 1 1 2 2 3 3')->commands;
        // No prior move — first command is `l`; subsequent are implicit `l`.
        self::assertCount(3, $cmds);
        foreach ($cmds as $cmd) {
            self::assertInstanceOf(LineTo::class, $cmd);
            self::assertFalse($cmd->absolute);
        }
    }

    public function testMalformedTailKeepsPrefixCommands(): void
    {
        // SVG 2 §9.3.9: keep what we managed to parse before the bad bit.
        $cmds = PathData::parse('M 10 20 L 30 40 BOGUS')->commands;
        self::assertCount(2, $cmds);
        self::assertInstanceOf(MoveTo::class, $cmds[0]);
        self::assertInstanceOf(LineTo::class, $cmds[1]);
    }

    public function testTruncatedCommandStopsAtError(): void
    {
        // `L 30` is missing the y coordinate — the LineTo never lands.
        $cmds = PathData::parse('M 10 20 L 30')->commands;
        self::assertCount(1, $cmds);
        self::assertInstanceOf(MoveTo::class, $cmds[0]);
    }

    public function testZAndLowercaseZBothEmitClosePath(): void
    {
        self::assertInstanceOf(ClosePath::class, PathData::parse('M0 0Z')->commands[1]);
        self::assertInstanceOf(ClosePath::class, PathData::parse('M0 0z')->commands[1]);
    }

    public function testMultipleSubpaths(): void
    {
        $cmds = PathData::parse('M 0 0 L 10 10 Z M 20 20 L 30 30 Z')->commands;
        self::assertCount(6, $cmds);
        self::assertInstanceOf(MoveTo::class, $cmds[0]);
        self::assertInstanceOf(LineTo::class, $cmds[1]);
        self::assertInstanceOf(ClosePath::class, $cmds[2]);
        self::assertInstanceOf(MoveTo::class, $cmds[3]);
        self::assertInstanceOf(LineTo::class, $cmds[4]);
        self::assertInstanceOf(ClosePath::class, $cmds[5]);
    }
}
