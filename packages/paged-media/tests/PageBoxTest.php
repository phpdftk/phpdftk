<?php

declare(strict_types=1);

namespace Phpdftk\PagedMedia\Tests;

use Phpdftk\Geometry\Rectangle;
use Phpdftk\PagedMedia\PageBox;
use Phpdftk\PagedMedia\PageMargin;
use PHPUnit\Framework\TestCase;

final class PageBoxTest extends TestCase
{
    public function testContentAreaSubtractsMargins(): void
    {
        $box = new PageBox(
            size: new Rectangle(0.0, 0.0, 612.0, 792.0),
            margin: PageMargin::uniform(72.0),
        );
        $content = $box->contentArea();
        // x = margin->left = 72, y = margin->bottom = 72
        self::assertSame(72.0, $content->x);
        self::assertSame(72.0, $content->y);
        // width = 612 - 72 - 72 = 468
        self::assertSame(468.0, $content->width);
        // height = 792 - 72 - 72 = 648
        self::assertSame(648.0, $content->height);
    }

    public function testLetterFactoryUsesUsLetterDimensions(): void
    {
        $box = PageBox::letter();
        self::assertSame(612.0, $box->size->width);
        self::assertSame(792.0, $box->size->height);
    }

    public function testA4FactoryUsesA4Dimensions(): void
    {
        $box = PageBox::a4();
        self::assertSame(595.0, $box->size->width);
        self::assertSame(842.0, $box->size->height);
    }

    public function testAsymmetricMarginsApplyCorrectly(): void
    {
        $box = new PageBox(
            size: new Rectangle(0.0, 0.0, 612.0, 792.0),
            margin: new PageMargin(top: 100.0, right: 50.0, bottom: 25.0, left: 75.0),
        );
        $content = $box->contentArea();
        self::assertSame(75.0, $content->x);
        self::assertSame(25.0, $content->y);
        self::assertSame(612.0 - 75.0 - 50.0, $content->width);
        self::assertSame(792.0 - 100.0 - 25.0, $content->height);
    }
}
