<?php

declare(strict_types=1);

namespace Phpdftk\Pdf\Conformance\Tests\Profile;

use Phpdftk\Pdf\Conformance\Profile\PdfXProfile;
use Phpdftk\Pdf\Core\PdfVersion;
use PHPUnit\Framework\TestCase;

class PdfXProfileTest extends TestCase
{
    public function testX1a2003Properties(): void
    {
        $p = PdfXProfile::X1a2003;
        self::assertSame('PDF/X', $p->getFamily());
        self::assertSame('X-1a:2003', $p->getLevel());
        self::assertSame(PdfVersion::V1_3, $p->getPdfVersion());
        self::assertTrue($p->prohibitsTransparency());
        self::assertSame('GTS_PDFX', $p->getOutputIntentSubtype());
        self::assertSame('pdfxid', $p->getXmpPrefix());
        self::assertSame(['GTS_PDFXVersion' => 'PDF/X-1a:2003'], $p->getXmpProperties());
    }

    public function testX32003Properties(): void
    {
        $p = PdfXProfile::X32003;
        self::assertSame('X-3:2003', $p->getLevel());
        self::assertSame(PdfVersion::V1_3, $p->getPdfVersion());
        self::assertTrue($p->prohibitsTransparency());
        self::assertSame(['GTS_PDFXVersion' => 'PDF/X-3:2003'], $p->getXmpProperties());
    }

    public function testX4Properties(): void
    {
        $p = PdfXProfile::X4;
        self::assertSame('X-4', $p->getLevel());
        self::assertSame(PdfVersion::V1_6, $p->getPdfVersion());
        self::assertFalse($p->prohibitsTransparency());
        self::assertSame(['GTS_PDFXVersion' => 'PDF/X-4'], $p->getXmpProperties());
    }

    public function testX5gProperties(): void
    {
        $p = PdfXProfile::X5g;
        self::assertSame('PDF/X', $p->getFamily());
        self::assertSame('X-5g', $p->getLevel());
        self::assertSame(PdfVersion::V1_6, $p->getPdfVersion());
        self::assertFalse($p->prohibitsTransparency());
        self::assertTrue($p->supportsReferenceXObjects());
        self::assertSame('GTS_PDFX', $p->getOutputIntentSubtype());
        self::assertSame(['GTS_PDFXVersion' => 'PDF/X-5g'], $p->getXmpProperties());
    }

    public function testX5pgProperties(): void
    {
        $p = PdfXProfile::X5pg;
        self::assertSame('X-5pg', $p->getLevel());
        self::assertSame(PdfVersion::V1_6, $p->getPdfVersion());
        self::assertFalse($p->prohibitsTransparency());
        self::assertTrue($p->supportsReferenceXObjects());
        self::assertSame(['GTS_PDFXVersion' => 'PDF/X-5pg'], $p->getXmpProperties());
    }

    public function testX5nProperties(): void
    {
        $p = PdfXProfile::X5n;
        self::assertSame('X-5n', $p->getLevel());
        self::assertSame(PdfVersion::V1_6, $p->getPdfVersion());
        self::assertFalse($p->prohibitsTransparency());
        self::assertTrue($p->supportsReferenceXObjects());
        self::assertSame(['GTS_PDFXVersion' => 'PDF/X-5n'], $p->getXmpProperties());
    }

    public function testNonX5ProfilesDoNotSupportReferenceXObjects(): void
    {
        self::assertFalse(PdfXProfile::X1a2003->supportsReferenceXObjects());
        self::assertFalse(PdfXProfile::X32003->supportsReferenceXObjects());
        self::assertFalse(PdfXProfile::X4->supportsReferenceXObjects());
    }

    public function testAllCasesHaveXmpNamespace(): void
    {
        foreach (PdfXProfile::cases() as $p) {
            self::assertSame('http://www.npes.org/pdfx/ns/id/', $p->getXmpNamespace());
        }
    }
}
