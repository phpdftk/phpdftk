<?php

declare(strict_types=1);

namespace ApprLabs\Pdf\Writer\Tests;

use ApprLabs\Pdf\Writer\Alignment;
use ApprLabs\Pdf\Writer\PageSize;
use PHPUnit\Framework\TestCase;

class PageSizeAndAlignmentTest extends TestCase
{
    public function testLetterIsPointAccurate(): void
    {
        self::assertSame(612.0, PageSize::Letter->width());
        self::assertSame(792.0, PageSize::Letter->height());
    }

    public function testLegalIsTallerThanLetter(): void
    {
        self::assertGreaterThan(PageSize::Letter->height(), PageSize::Legal->height());
        self::assertSame(PageSize::Letter->width(), PageSize::Legal->width());
    }

    public function testA4RoundtrippingDimensions(): void
    {
        // A4 is 210 × 297 mm ≈ 595.28 × 841.89 pt
        self::assertEqualsWithDelta(595.28, PageSize::A4->width(), 0.01);
        self::assertEqualsWithDelta(841.89, PageSize::A4->height(), 0.01);
    }

    public function testEveryPageSizeReturnsPositiveDimensions(): void
    {
        foreach (PageSize::cases() as $size) {
            self::assertGreaterThan(0, $size->width(), $size->name);
            self::assertGreaterThan(0, $size->height(), $size->name);
        }
    }

    public function testAlignmentHasThreeCases(): void
    {
        self::assertCount(3, Alignment::cases());
    }
}
