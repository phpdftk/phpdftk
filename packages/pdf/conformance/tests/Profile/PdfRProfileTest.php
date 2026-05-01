<?php

declare(strict_types=1);

namespace ApprLabs\Pdf\Conformance\Tests\Profile;

use ApprLabs\Pdf\Conformance\Profile\PdfRProfile;
use ApprLabs\Pdf\Core\PdfVersion;
use PHPUnit\Framework\TestCase;

class PdfRProfileTest extends TestCase
{
    public function testR1Properties(): void
    {
        $p = PdfRProfile::R1;
        self::assertSame('PDF/R', $p->getFamily());
        self::assertSame('R-1', $p->getLevel());
        self::assertSame(PdfVersion::V2_0, $p->getPdfVersion());
        self::assertSame('pdfrid', $p->getXmpPrefix());
        self::assertSame('http://www.pdfa.org/pdfr/ns/id/', $p->getXmpNamespace());
        self::assertSame(['part' => '1'], $p->getXmpProperties());
    }
}
