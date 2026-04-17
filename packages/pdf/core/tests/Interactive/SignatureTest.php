<?php

declare(strict_types=1);

namespace ApprLabs\Pdf\Core\Tests\Interactive;

use ApprLabs\Pdf\Core\Interactive\Form\SignatureField;
use ApprLabs\Pdf\Core\Interactive\Signature\DocMDPTransformParams;
use ApprLabs\Pdf\Core\Interactive\Signature\FieldMDPTransformParams;
use ApprLabs\Pdf\Core\Interactive\Signature\SignatureReference;
use ApprLabs\Pdf\Core\Interactive\Signature\SignatureValue;
use ApprLabs\Pdf\Core\Interactive\Signature\UR3TransformParams;
use ApprLabs\Pdf\Core\PdfArray;
use ApprLabs\Pdf\Core\PdfName;
use ApprLabs\Pdf\Core\PdfNumber;
use ApprLabs\Pdf\Core\PdfReference;
use ApprLabs\Pdf\Core\PdfString;
use PHPUnit\Framework\TestCase;

class SignatureTest extends TestCase
{
    public function testSignatureValueDefaults(): void
    {
        $sig = new SignatureValue();
        $sig->objectNumber = 1;
        $pdf = $sig->toPdf();
        self::assertStringContainsString('/Type /Sig', $pdf);
        self::assertStringContainsString('/Filter /Adobe.PPKLite', $pdf);
        self::assertStringContainsString('/SubFilter /adbe.pkcs7.detached', $pdf);
        self::assertStringContainsString('/Contents <', $pdf);  // hex-encoded placeholder
    }

    public function testSignatureValueCAdES(): void
    {
        $sig = new SignatureValue(
            filter: 'Adobe.PPKLite',
            subFilter: 'ETSI.CAdES.detached',
            contents: new PdfString(str_repeat("\x42", 16), hex: true)
        );
        $sig->objectNumber = 1;
        $sig->name = new PdfString('Alice Signer');
        $sig->m = new PdfString("D:20260410120000Z");
        $sig->location = new PdfString('Somewhere');
        $sig->reason = new PdfString('I approve this document');
        $sig->byteRange = new PdfArray([
            new PdfNumber(0), new PdfNumber(100),
            new PdfNumber(200), new PdfNumber(300),
        ]);
        $pdf = $sig->toPdf();
        self::assertStringContainsString('/SubFilter /ETSI.CAdES.detached', $pdf);
        self::assertStringContainsString('/Name (Alice Signer)', $pdf);
        self::assertStringContainsString('/Reason', $pdf);
        self::assertStringContainsString('/ByteRange', $pdf);
    }

    public function testSignatureReference(): void
    {
        $ref = new SignatureReference('DocMDP');
        $ref->objectNumber = 1;
        $ref->transformParams = new PdfReference(7);
        $ref->digestMethod = new PdfName('SHA256');
        $pdf = $ref->toPdf();
        self::assertStringContainsString('/Type /SigRef', $pdf);
        self::assertStringContainsString('/TransformMethod /DocMDP', $pdf);
        self::assertStringContainsString('/TransformParams 7 0 R', $pdf);
        self::assertStringContainsString('/DigestMethod /SHA256', $pdf);
    }

    public function testDocMDPTransformParams(): void
    {
        $p = new DocMDPTransformParams(p: 2);
        $p->objectNumber = 1;
        $p->v = new PdfName('1.2');
        $pdf = $p->toPdf();
        self::assertStringContainsString('/Type /TransformParams', $pdf);
        self::assertStringContainsString('/P 2', $pdf);
        self::assertStringContainsString('/V /1.2', $pdf);
    }

    public function testFieldMDPTransformParamsAll(): void
    {
        $p = new FieldMDPTransformParams('All');
        $p->objectNumber = 1;
        $pdf = $p->toPdf();
        self::assertStringContainsString('/Action /All', $pdf);
        self::assertStringNotContainsString('/Fields', $pdf);
    }

    public function testFieldMDPTransformParamsInclude(): void
    {
        $p = new FieldMDPTransformParams(
            'Include',
            new PdfArray([new PdfString('name'), new PdfString('email')])
        );
        $p->objectNumber = 1;
        $pdf = $p->toPdf();
        self::assertStringContainsString('/Action /Include', $pdf);
        self::assertStringContainsString('/Fields', $pdf);
    }

    public function testUR3TransformParams(): void
    {
        $p = new UR3TransformParams();
        $p->objectNumber = 1;
        $p->form = new PdfArray([new PdfName('FillIn'), new PdfName('Import'), new PdfName('Export')]);
        $p->annots = new PdfArray([new PdfName('Create'), new PdfName('Modify')]);
        $p->msg = new PdfString('Rights granted');
        $pdf = $p->toPdf();
        self::assertStringContainsString('/Type /TransformParams', $pdf);
        self::assertStringContainsString('/Form', $pdf);
        self::assertStringContainsString('/Annots', $pdf);
        self::assertStringContainsString('/FillIn', $pdf);
    }

    public function testSignatureFieldWithSignatureValue(): void
    {
        $sig = new SignatureValue();
        $field = new SignatureField();
        $field->objectNumber = 1;
        $field->t = new PdfString('Signature1');
        $field->setSignatureValue($sig);
        $pdf = $field->toPdf();
        self::assertStringContainsString('/FT /Sig', $pdf);
        self::assertStringContainsString('/T (Signature1)', $pdf);
        // inline signature dict with /Type /Sig
        self::assertStringContainsString('/Type /Sig', $pdf);
        self::assertStringContainsString('/Filter /Adobe.PPKLite', $pdf);
    }

    public function testSignatureFieldWithLockAndSeedValue(): void
    {
        $field = new SignatureField();
        $field->objectNumber = 1;
        $field->t = new PdfString('Signature2');
        $field->lock = new PdfReference(20);
        $field->sv = new PdfReference(21);
        $field->sigFlags = 3;
        $pdf = $field->toPdf();
        self::assertStringContainsString('/Lock 20 0 R', $pdf);
        self::assertStringContainsString('/SV 21 0 R', $pdf);
        self::assertStringContainsString('/SigFlags 3', $pdf);
    }

    public function testSignatureFieldWithIndirectSignatureValue(): void
    {
        $field = new SignatureField();
        $field->objectNumber = 1;
        $field->t = new PdfString('Signature3');
        $field->setSignatureValue(new PdfReference(55));
        $pdf = $field->toPdf();
        self::assertStringContainsString('/V 55 0 R', $pdf);
    }
}
