<?php

declare(strict_types=1);

namespace ApprLabs\Encoding\Tests;

use ApprLabs\Encoding\PredefinedCMap;
use PHPUnit\Framework\TestCase;

class PredefinedCMapTest extends TestCase
{
    public function testJapaneseCMapReturnsJapan1(): void
    {
        $info = PredefinedCMap::getCIDSystemInfo(PredefinedCMap::JAPAN_UCS2_H);

        $this->assertNotNull($info);
        $this->assertSame('Adobe', $info['registry']);
        $this->assertSame('Japan1', $info['ordering']);
    }

    public function testKoreanCMapReturnsKorea1(): void
    {
        $info = PredefinedCMap::getCIDSystemInfo(PredefinedCMap::KOREA_UCS2_H);

        $this->assertNotNull($info);
        $this->assertSame('Korea1', $info['ordering']);
    }

    public function testSimplifiedChineseCMapReturnsGB1(): void
    {
        $info = PredefinedCMap::getCIDSystemInfo(PredefinedCMap::GB_UCS2_H);

        $this->assertNotNull($info);
        $this->assertSame('GB1', $info['ordering']);
    }

    public function testTraditionalChineseCMapReturnsCNS1(): void
    {
        $info = PredefinedCMap::getCIDSystemInfo(PredefinedCMap::CNS_UCS2_H);

        $this->assertNotNull($info);
        $this->assertSame('CNS1', $info['ordering']);
    }

    public function testIdentityHReturnsIdentity(): void
    {
        $info = PredefinedCMap::getCIDSystemInfo(PredefinedCMap::IDENTITY_H);

        $this->assertNotNull($info);
        $this->assertSame('Identity', $info['ordering']);
    }

    public function testIsPredefined(): void
    {
        $this->assertTrue(PredefinedCMap::isPredefined('UniJIS-UCS2-H'));
        $this->assertTrue(PredefinedCMap::isPredefined('Identity-H'));
        $this->assertTrue(PredefinedCMap::isPredefined('Identity-V'));
        $this->assertFalse(PredefinedCMap::isPredefined('CustomCMap'));
    }

    public function testUnknownCMapReturnsNull(): void
    {
        $this->assertNull(PredefinedCMap::getCIDSystemInfo('NotACMap'));
    }

    public function testVerticalVariants(): void
    {
        // All vertical CMap names should be recognized
        $this->assertTrue(PredefinedCMap::isPredefined(PredefinedCMap::JAPAN_SJIS_V));
        $this->assertTrue(PredefinedCMap::isPredefined(PredefinedCMap::KOREA_UCS2_V));
        $this->assertTrue(PredefinedCMap::isPredefined(PredefinedCMap::GB_UCS2_V));
        $this->assertTrue(PredefinedCMap::isPredefined(PredefinedCMap::CNS_UCS2_V));
    }
}
