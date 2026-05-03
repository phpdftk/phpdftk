<?php

declare(strict_types=1);

namespace Phpdftk\Pdf\Conformance\Tests\Profile;

use Phpdftk\Pdf\Conformance\Profile\PdfAProfile;
use Phpdftk\Pdf\Conformance\Profile\ZugferdProfile;
use Phpdftk\Pdf\Core\PdfVersion;
use PHPUnit\Framework\TestCase;

class ZugferdProfileTest extends TestCase
{
    public function testAllCasesHaveCorrectFamily(): void
    {
        foreach (ZugferdProfile::cases() as $p) {
            self::assertSame('Factur-X', $p->getFamily());
        }
    }

    public function testAllCasesRequirePdf17(): void
    {
        foreach (ZugferdProfile::cases() as $p) {
            self::assertSame(PdfVersion::V1_7, $p->getPdfVersion());
        }
    }

    public function testAllCasesHaveXmpNamespace(): void
    {
        foreach (ZugferdProfile::cases() as $p) {
            self::assertSame(
                'urn:factur-x:pdfa:CrossIndustryDocument:invoice:1p0#',
                $p->getXmpNamespace(),
            );
            self::assertSame('fx', $p->getXmpPrefix());
        }
    }

    public function testMinimumLevel(): void
    {
        $p = ZugferdProfile::MINIMUM;
        self::assertSame('MINIMUM', $p->getLevel());
        self::assertSame('MINIMUM', $p->getXmpProperties()['ConformanceLevel']);
        self::assertSame('INVOICE', $p->getXmpProperties()['DocumentType']);
        self::assertSame('factur-x.xml', $p->getXmpProperties()['DocumentFileName']);
    }

    public function testBasicWlLevel(): void
    {
        $p = ZugferdProfile::BASIC_WL;
        self::assertSame('BASIC WL', $p->getLevel());
        self::assertSame('BASIC WL', $p->getXmpProperties()['ConformanceLevel']);
    }

    public function testEn16931Level(): void
    {
        $p = ZugferdProfile::EN16931;
        self::assertSame('EN 16931', $p->getLevel());
        self::assertSame('EN 16931', $p->getXmpProperties()['ConformanceLevel']);
    }

    public function testXrechnungLevel(): void
    {
        $p = ZugferdProfile::XRECHNUNG;
        self::assertSame('XRECHNUNG', $p->getLevel());
    }

    public function testBaseProfileIsPdfA3b(): void
    {
        foreach (ZugferdProfile::cases() as $p) {
            self::assertSame(PdfAProfile::A3b, $p->getBaseProfile());
        }
    }
}
