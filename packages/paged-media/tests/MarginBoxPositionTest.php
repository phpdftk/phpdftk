<?php

declare(strict_types=1);

namespace Phpdftk\PagedMedia\Tests;

use Phpdftk\PagedMedia\MarginBoxPosition;
use PHPUnit\Framework\TestCase;

final class MarginBoxPositionTest extends TestCase
{
    public function testSpecKeywordsMatchCssNames(): void
    {
        // CSS Paged Media 3 §5.2 — exact at-rule prelude keywords.
        self::assertSame('top-left-corner', MarginBoxPosition::TopLeftCorner->value);
        self::assertSame('top-center', MarginBoxPosition::TopCenter->value);
        self::assertSame('bottom-right-corner', MarginBoxPosition::BottomRightCorner->value);
        self::assertSame('left-middle', MarginBoxPosition::LeftMiddle->value);
    }

    public function testSixteenPositionsExist(): void
    {
        self::assertCount(16, MarginBoxPosition::cases());
    }

    public function testIsCornerReturnsTrueForFourCorners(): void
    {
        $corners = array_filter(
            MarginBoxPosition::cases(),
            static fn(MarginBoxPosition $p) => $p->isCorner(),
        );
        self::assertCount(4, $corners);
        self::assertContainsEquals(MarginBoxPosition::TopLeftCorner, $corners);
        self::assertContainsEquals(MarginBoxPosition::TopRightCorner, $corners);
        self::assertContainsEquals(MarginBoxPosition::BottomLeftCorner, $corners);
        self::assertContainsEquals(MarginBoxPosition::BottomRightCorner, $corners);
    }

    public function testCornerEdgeIsNull(): void
    {
        self::assertNull(MarginBoxPosition::TopLeftCorner->edge());
        self::assertNull(MarginBoxPosition::BottomRightCorner->edge());
    }

    public function testEdgeClassifiesTopRow(): void
    {
        self::assertSame('top', MarginBoxPosition::TopLeft->edge());
        self::assertSame('top', MarginBoxPosition::TopCenter->edge());
        self::assertSame('top', MarginBoxPosition::TopRight->edge());
    }

    public function testEdgeClassifiesBottomRow(): void
    {
        self::assertSame('bottom', MarginBoxPosition::BottomLeft->edge());
        self::assertSame('bottom', MarginBoxPosition::BottomCenter->edge());
        self::assertSame('bottom', MarginBoxPosition::BottomRight->edge());
    }

    public function testEdgeClassifiesLeftAndRightColumns(): void
    {
        self::assertSame('left', MarginBoxPosition::LeftTop->edge());
        self::assertSame('left', MarginBoxPosition::LeftMiddle->edge());
        self::assertSame('left', MarginBoxPosition::LeftBottom->edge());

        self::assertSame('right', MarginBoxPosition::RightTop->edge());
        self::assertSame('right', MarginBoxPosition::RightMiddle->edge());
        self::assertSame('right', MarginBoxPosition::RightBottom->edge());
    }
}
