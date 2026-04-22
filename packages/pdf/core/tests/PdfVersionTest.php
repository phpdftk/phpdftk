<?php

declare(strict_types=1);

namespace ApprLabs\Pdf\Core\Tests;

use ApprLabs\Pdf\Core\PdfVersion;
use PHPUnit\Framework\TestCase;

class PdfVersionTest extends TestCase
{
    public function testAllCasesExist(): void
    {
        $cases = PdfVersion::cases();
        $this->assertCount(9, $cases);
        $this->assertSame('1.0', PdfVersion::V1_0->value);
        $this->assertSame('2.0', PdfVersion::V2_0->value);
    }

    public function testIsAtLeast(): void
    {
        $this->assertTrue(PdfVersion::V1_7->isAtLeast(PdfVersion::V1_5));
        $this->assertTrue(PdfVersion::V1_7->isAtLeast(PdfVersion::V1_7));
        $this->assertFalse(PdfVersion::V1_4->isAtLeast(PdfVersion::V1_5));
        $this->assertTrue(PdfVersion::V2_0->isAtLeast(PdfVersion::V1_7));
        $this->assertFalse(PdfVersion::V1_7->isAtLeast(PdfVersion::V2_0));
    }

    public function testIsGreaterThan(): void
    {
        $this->assertTrue(PdfVersion::V1_7->isGreaterThan(PdfVersion::V1_5));
        $this->assertFalse(PdfVersion::V1_7->isGreaterThan(PdfVersion::V1_7));
        $this->assertTrue(PdfVersion::V2_0->isGreaterThan(PdfVersion::V1_7));
    }

    public function testMax(): void
    {
        $this->assertSame(PdfVersion::V1_7, PdfVersion::V1_5->max(PdfVersion::V1_7));
        $this->assertSame(PdfVersion::V1_7, PdfVersion::V1_7->max(PdfVersion::V1_5));
        $this->assertSame(PdfVersion::V2_0, PdfVersion::V1_7->max(PdfVersion::V2_0));
        $this->assertSame(PdfVersion::V1_3, PdfVersion::V1_3->max(PdfVersion::V1_3));
    }

    public function testFromString(): void
    {
        $this->assertSame(PdfVersion::V1_7, PdfVersion::fromString('1.7'));
        $this->assertSame(PdfVersion::V2_0, PdfVersion::fromString('2.0'));
        $this->assertNull(PdfVersion::fromString('3.0'));
        $this->assertNull(PdfVersion::fromString('invalid'));
    }

    public function testTryFrom(): void
    {
        $this->assertSame(PdfVersion::V1_0, PdfVersion::tryFrom('1.0'));
        $this->assertNull(PdfVersion::tryFrom('9.9'));
    }
}
