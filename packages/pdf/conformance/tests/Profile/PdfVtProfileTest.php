<?php

declare(strict_types=1);

namespace ApprLabs\Pdf\Conformance\Tests\Profile;

use ApprLabs\Pdf\Conformance\Profile\PdfVtProfile;
use ApprLabs\Pdf\Core\PdfVersion;
use PHPUnit\Framework\TestCase;

class PdfVtProfileTest extends TestCase
{
    public function testVt1Properties(): void
    {
        $p = PdfVtProfile::VT1;
        self::assertSame('PDF/VT', $p->getFamily());
        self::assertSame('VT-1', $p->getLevel());
        self::assertSame(PdfVersion::V2_0, $p->getPdfVersion());
        self::assertSame('pdfvtid', $p->getXmpPrefix());
        self::assertSame('http://www.npes.org/pdfvt/ns/id/', $p->getXmpNamespace());
        self::assertSame(['GTS_PDFVTVersion' => 'PDF/VT-1'], $p->getXmpProperties());
    }

    public function testVt2Properties(): void
    {
        self::assertSame('VT-2', PdfVtProfile::VT2->getLevel());
        self::assertSame(['GTS_PDFVTVersion' => 'PDF/VT-2'], PdfVtProfile::VT2->getXmpProperties());
    }

    public function testVt2sProperties(): void
    {
        self::assertSame('VT-2s', PdfVtProfile::VT2s->getLevel());
        self::assertSame(['GTS_PDFVTVersion' => 'PDF/VT-2s'], PdfVtProfile::VT2s->getXmpProperties());
    }
}
