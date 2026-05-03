<?php

declare(strict_types=1);

namespace Phpdftk\Pdf\Conformance\Tests\Profile;

use Phpdftk\Pdf\Conformance\Profile\PdfEProfile;
use Phpdftk\Pdf\Core\PdfVersion;
use PHPUnit\Framework\TestCase;

class PdfEProfileTest extends TestCase
{
    public function testE1Properties(): void
    {
        $p = PdfEProfile::E1;
        self::assertSame('PDF/E', $p->getFamily());
        self::assertSame('E-1', $p->getLevel());
        self::assertSame(PdfVersion::V1_6, $p->getPdfVersion());
        self::assertSame('pdfeid', $p->getXmpPrefix());
        self::assertSame('http://www.aiim.org/pdfe/ns/id/', $p->getXmpNamespace());
        self::assertSame(['part' => '1'], $p->getXmpProperties());
    }
}
