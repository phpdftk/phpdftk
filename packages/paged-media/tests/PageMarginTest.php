<?php

declare(strict_types=1);

namespace Phpdftk\PagedMedia\Tests;

use Phpdftk\PagedMedia\PageMargin;
use PHPUnit\Framework\TestCase;

final class PageMarginTest extends TestCase
{
    public function testConstructorAcceptsFourValues(): void
    {
        $margin = new PageMargin(top: 10.0, right: 20.0, bottom: 30.0, left: 40.0);
        self::assertSame(10.0, $margin->top);
        self::assertSame(20.0, $margin->right);
        self::assertSame(30.0, $margin->bottom);
        self::assertSame(40.0, $margin->left);
    }

    public function testConstructorRejectsNegativeMargin(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new PageMargin(top: -1.0, right: 0.0, bottom: 0.0, left: 0.0);
    }

    public function testUniformFactory(): void
    {
        $margin = PageMargin::uniform(72.0);
        self::assertSame(72.0, $margin->top);
        self::assertSame(72.0, $margin->right);
        self::assertSame(72.0, $margin->bottom);
        self::assertSame(72.0, $margin->left);
    }

    public function testSymmetricFactory(): void
    {
        // `margin: 72pt 36pt` → vertical 72, horizontal 36
        $margin = PageMargin::symmetric(vertical: 72.0, horizontal: 36.0);
        self::assertSame(72.0, $margin->top);
        self::assertSame(72.0, $margin->bottom);
        self::assertSame(36.0, $margin->right);
        self::assertSame(36.0, $margin->left);
    }
}
