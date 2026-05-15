<?php

declare(strict_types=1);

namespace Phpdftk\Pdf\Core\Tests\Interactive\Signature;

use Phpdftk\Pdf\Core\Interactive\Signature\SignatureValue;
use Phpdftk\Pdf\Core\PdfArray;
use Phpdftk\Pdf\Core\PdfDictionary;
use Phpdftk\Pdf\Core\PdfName;
use Phpdftk\Pdf\Core\PdfNumber;
use Phpdftk\Pdf\Core\PdfString;
use PHPUnit\Framework\TestCase;

class SignatureValueTest extends TestCase
{
    public function testMinimalSignatureValue(): void
    {
        $sv = new SignatureValue();
        $pdf = $sv->toPdf();
        $this->assertStringContainsString('/Type /Sig', $pdf);
        $this->assertStringContainsString('/Filter /Adobe.PPKLite', $pdf);
        $this->assertStringContainsString('/SubFilter /adbe.pkcs7.detached', $pdf);
        $this->assertStringContainsString('/Contents', $pdf);
    }

    public function testCustomFilterAndContents(): void
    {
        $sv = new SignatureValue(
            filter: 'Custom.Signer',
            subFilter: null,
            contents: new PdfString("\xFF", hex: true),
        );
        $pdf = $sv->toPdf();
        $this->assertStringContainsString('/Filter /Custom.Signer', $pdf);
        $this->assertStringNotContainsString('/SubFilter', $pdf);
        $this->assertStringContainsString('/Contents <ff>', $pdf);
    }

    public function testAllOptionalFields(): void
    {
        $sv = new SignatureValue();
        $sv->cert = new PdfArray([new PdfString('cert-bytes', hex: true)]);
        $sv->byteRange = new PdfArray([
            new PdfNumber(0),
            new PdfNumber(100),
            new PdfNumber(200),
            new PdfNumber(50),
        ]);
        $sv->reference = new PdfArray([]);
        $sv->changes = new PdfArray([]);
        $sv->name = new PdfString('Signer Name');
        $sv->m = new PdfString('D:20260101000000Z');
        $sv->location = new PdfString('Country');
        $sv->reason = new PdfString('Approval');
        $sv->contactInfo = new PdfString('me@example.com');
        $sv->r = 1;
        $sv->v = 2;
        $sv->propBuild = new PdfDictionary();
        $sv->propAuthTime = 1234;
        $sv->propAuthType = new PdfName('PIN');

        $pdf = $sv->toPdf();
        foreach (['/Cert', '/ByteRange', '/Reference', '/Changes', '/Name', '/M', '/Location',
            '/Reason', '/ContactInfo', '/R 1', '/V 2', '/Prop_Build',
            '/Prop_AuthTime 1234', '/Prop_AuthType /PIN'] as $needle) {
            $this->assertStringContainsString($needle, $pdf);
        }
    }
}
