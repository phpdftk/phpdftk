<?php

declare(strict_types=1);

namespace ApprLabs\Pdf\Conformance\Tests\Profile;

use ApprLabs\Pdf\Conformance\Profile\PdfUaProfile;
use ApprLabs\Pdf\Core\PdfVersion;
use PHPUnit\Framework\TestCase;

class PdfUaProfileTest extends TestCase
{
    public function testUa1Properties(): void
    {
        $profile = PdfUaProfile::UA1;
        self::assertSame('PDF/UA', $profile->getFamily());
        self::assertSame('UA-1', $profile->getLevel());
        self::assertSame(1, $profile->getPart());
        self::assertSame(PdfVersion::V1_7, $profile->getPdfVersion());
        self::assertSame('pdfuaid', $profile->getXmpPrefix());
        self::assertSame('http://www.aiim.org/pdfua/ns/id/', $profile->getXmpNamespace());
        self::assertSame(['part' => '1'], $profile->getXmpProperties());
    }

    public function testUa2Properties(): void
    {
        $profile = PdfUaProfile::UA2;
        self::assertSame('PDF/UA', $profile->getFamily());
        self::assertSame('UA-2', $profile->getLevel());
        self::assertSame(2, $profile->getPart());
        self::assertSame(PdfVersion::V2_0, $profile->getPdfVersion());
        self::assertSame(['part' => '2'], $profile->getXmpProperties());
    }

    public function testAllCasesHaveXmpNamespace(): void
    {
        foreach (PdfUaProfile::cases() as $profile) {
            self::assertSame('http://www.aiim.org/pdfua/ns/id/', $profile->getXmpNamespace());
        }
    }
}
