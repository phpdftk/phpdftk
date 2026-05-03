<?php

declare(strict_types=1);

namespace Phpdftk\Pdf\Conformance\Tests\Profile;

use Phpdftk\Pdf\Conformance\Profile\PdfMailProfile;
use Phpdftk\Pdf\Core\PdfVersion;
use PHPUnit\Framework\TestCase;

class PdfMailProfileTest extends TestCase
{
    public function testMail1Properties(): void
    {
        $p = PdfMailProfile::Mail1;
        self::assertSame('PDF/mail', $p->getFamily());
        self::assertSame('1', $p->getLevel());
        self::assertSame(PdfVersion::V2_0, $p->getPdfVersion());
        self::assertSame('http://www.pdfa.org/pdfmail/ns/id/', $p->getXmpNamespace());
        self::assertSame('pdfmailid', $p->getXmpPrefix());
        self::assertSame(['part' => '1'], $p->getXmpProperties());
    }
}
