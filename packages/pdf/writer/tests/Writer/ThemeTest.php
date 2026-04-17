<?php

declare(strict_types=1);

namespace ApprLabs\Pdf\Writer\Tests;

use ApprLabs\Pdf\Writer\Theme;
use PHPUnit\Framework\TestCase;

class ThemeTest extends TestCase
{
    public function testDefaults(): void
    {
        $theme = new Theme();
        self::assertSame('Helvetica', $theme->family);
        self::assertSame(11.0, $theme->fontSize);
        self::assertSame(72.0, $theme->margin);
        self::assertSame([0.0, 0.0, 0.0], $theme->color);
    }

    public function testWithFontReturnsNewInstance(): void
    {
        $original = new Theme();
        $modified = $original->withFont('Times', 14);
        self::assertNotSame($original, $modified);
        self::assertSame('Helvetica', $original->family);
        self::assertSame('Times', $modified->family);
        self::assertSame(14.0, $modified->fontSize);
    }

    public function testWithColor(): void
    {
        $theme = (new Theme())->withColor([1.0, 0.0, 0.0]);
        self::assertSame([1.0, 0.0, 0.0], $theme->color);
    }

    public function testWithMargin(): void
    {
        $theme = (new Theme())->withMargin(50);
        self::assertSame(50.0, $theme->margin);
    }

    public function testHeadingLevelsOneThroughSix(): void
    {
        $theme = new Theme();
        foreach (range(1, 6) as $level) {
            $style = $theme->heading($level);
            self::assertArrayHasKey('size', $style);
            self::assertArrayHasKey('bold', $style);
            self::assertArrayHasKey('spaceAbove', $style);
            self::assertArrayHasKey('spaceBelow', $style);
        }
    }

    public function testHeadingSevenRejected(): void
    {
        $theme = new Theme();
        $this->expectException(\InvalidArgumentException::class);
        $theme->heading(7);
    }

    public function testH1LargerThanH6(): void
    {
        $theme = new Theme();
        self::assertGreaterThan($theme->heading(6)['size'], $theme->heading(1)['size']);
    }
}
