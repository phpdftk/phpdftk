<?php

declare(strict_types=1);

namespace Phpdftk\Pdf\Conformance\Tests\Profile;

use Phpdftk\Pdf\Conformance\Profile\PdfAProfile;
use Phpdftk\Pdf\Core\PdfVersion;
use PHPUnit\Framework\TestCase;

class PdfAProfileTest extends TestCase
{
    public function testA1bProperties(): void
    {
        $profile = PdfAProfile::A1b;
        self::assertSame('PDF/A', $profile->getFamily());
        self::assertSame('1b', $profile->getLevel());
        self::assertSame(1, $profile->getPart());
        self::assertSame('B', $profile->getConformanceLevel());
        self::assertSame(PdfVersion::V1_4, $profile->getPdfVersion());
        self::assertSame('pdfaid', $profile->getXmpPrefix());
        self::assertSame(['part' => '1', 'conformance' => 'B'], $profile->getXmpProperties());
        self::assertFalse($profile->requiresTaggedStructure());
        self::assertTrue($profile->prohibitsTransparency());
        self::assertFalse($profile->allowsEmbeddedFiles());
    }

    public function testA1aRequiresTaggedStructure(): void
    {
        self::assertTrue(PdfAProfile::A1a->requiresTaggedStructure());
    }

    public function testA2bPdfVersion(): void
    {
        self::assertSame(PdfVersion::V1_7, PdfAProfile::A2b->getPdfVersion());
    }

    public function testA2bDoesNotProhibitTransparency(): void
    {
        self::assertFalse(PdfAProfile::A2b->prohibitsTransparency());
    }

    public function testA3bAllowsEmbeddedFiles(): void
    {
        self::assertTrue(PdfAProfile::A3b->allowsEmbeddedFiles());
    }

    public function testA4PdfVersion(): void
    {
        self::assertSame(PdfVersion::V2_0, PdfAProfile::A4->getPdfVersion());
        self::assertNull(PdfAProfile::A4->getConformanceLevel());
    }

    public function testA4eAndA4f(): void
    {
        self::assertSame('E', PdfAProfile::A4e->getConformanceLevel());
        self::assertSame('F', PdfAProfile::A4f->getConformanceLevel());
    }

    public function testAllCasesHaveXmpNamespace(): void
    {
        foreach (PdfAProfile::cases() as $profile) {
            self::assertSame('http://www.aiim.org/pdfa/ns/id/', $profile->getXmpNamespace());
        }
    }
}
