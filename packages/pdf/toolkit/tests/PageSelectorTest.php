<?php

declare(strict_types=1);

namespace ApprLabs\Pdf\Toolkit\Tests;

use ApprLabs\Pdf\Toolkit\PageSelector;
use PHPUnit\Framework\TestCase;

class PageSelectorTest extends TestCase
{
    public function testAll(): void
    {
        $sel = PageSelector::all();
        for ($i = 1; $i <= 10; $i++) {
            $this->assertTrue($sel->matches($i, 10));
        }
    }

    public function testPages(): void
    {
        $sel = PageSelector::pages(1, 3, 5);
        $this->assertTrue($sel->matches(1, 10));
        $this->assertFalse($sel->matches(2, 10));
        $this->assertTrue($sel->matches(3, 10));
        $this->assertFalse($sel->matches(4, 10));
        $this->assertTrue($sel->matches(5, 10));
    }

    public function testRange(): void
    {
        $sel = PageSelector::range(3, 7);
        $this->assertFalse($sel->matches(2, 10));
        $this->assertTrue($sel->matches(3, 10));
        $this->assertTrue($sel->matches(5, 10));
        $this->assertTrue($sel->matches(7, 10));
        $this->assertFalse($sel->matches(8, 10));
    }

    public function testEven(): void
    {
        $sel = PageSelector::even();
        $this->assertFalse($sel->matches(1, 10));
        $this->assertTrue($sel->matches(2, 10));
        $this->assertFalse($sel->matches(3, 10));
        $this->assertTrue($sel->matches(4, 10));
    }

    public function testOdd(): void
    {
        $sel = PageSelector::odd();
        $this->assertTrue($sel->matches(1, 10));
        $this->assertFalse($sel->matches(2, 10));
        $this->assertTrue($sel->matches(3, 10));
        $this->assertFalse($sel->matches(4, 10));
    }

    public function testResolve(): void
    {
        $sel = PageSelector::range(2, 4);
        $indices = $sel->resolve(5);
        $this->assertSame([1, 2, 3], $indices); // 0-based: pages 2,3,4
    }

    public function testResolveAll(): void
    {
        $sel = PageSelector::all();
        $indices = $sel->resolve(3);
        $this->assertSame([0, 1, 2], $indices);
    }

    public function testResolveEven(): void
    {
        $sel = PageSelector::even();
        $indices = $sel->resolve(6);
        $this->assertSame([1, 3, 5], $indices); // 0-based: pages 2,4,6
    }
}
