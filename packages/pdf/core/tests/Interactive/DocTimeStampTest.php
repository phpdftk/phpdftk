<?php

declare(strict_types=1);

namespace ApprLabs\Pdf\Core\Tests\Interactive;

use ApprLabs\Pdf\Core\Interactive\Signature\DocTimeStamp;
use ApprLabs\Pdf\Core\PdfArray;
use ApprLabs\Pdf\Core\PdfNumber;
use ApprLabs\Pdf\Core\PdfString;
use PHPUnit\Framework\TestCase;

class DocTimeStampTest extends TestCase
{
    public function testType(): void
    {
        $ts = new DocTimeStamp();
        $ts->objectNumber = 1;
        $pdf = $ts->toPdf();
        self::assertStringContainsString('/Type /DocTimeStamp', $pdf);
        self::assertStringContainsString('/Filter /Adobe.PPKLite', $pdf);
        self::assertStringContainsString('/SubFilter /ETSI.RFC3161', $pdf);
        // Inherits the same hex /Contents placeholder
        self::assertStringContainsString('/Contents <', $pdf);
    }

    public function testByteRangeAndContentsPatching(): void
    {
        $ts = new DocTimeStamp(
            contents: new PdfString("\xDE\xAD\xBE\xEF", hex: true)
        );
        $ts->objectNumber = 1;
        $ts->byteRange = new PdfArray([
            new PdfNumber(0), new PdfNumber(100),
            new PdfNumber(200), new PdfNumber(300),
        ]);
        $pdf = $ts->toPdf();
        self::assertStringContainsString('/ByteRange', $pdf);
        self::assertStringContainsString('/Contents <deadbeef>', $pdf);
    }

    public function testCustomSubFilter(): void
    {
        // RFC 5652 CMS-based timestamp alternative
        $ts = new DocTimeStamp(subFilter: 'ETSI.CAdES.detached');
        $ts->objectNumber = 1;
        self::assertStringContainsString('/SubFilter /ETSI.CAdES.detached', $ts->toPdf());
    }
}
