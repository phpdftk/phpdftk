<?php

declare(strict_types=1);

namespace Phpdftk\Pdf\Core\Tests\Interactive;

use Phpdftk\Pdf\Core\Interactive\Form\SeedValueDictionary;
use Phpdftk\Pdf\Core\Interactive\Form\SigFieldLock;
use Phpdftk\Pdf\Core\Interactive\Signature\IdentityTransformParams;
use Phpdftk\Pdf\Core\PdfArray;
use Phpdftk\Pdf\Core\PdfName;
use Phpdftk\Pdf\Core\PdfString;
use PHPUnit\Framework\TestCase;

class SignatureHelpersTest extends TestCase
{
    public function testSigFieldLockAll(): void
    {
        $lock = new SigFieldLock('All');
        $lock->objectNumber = 1;
        $lock->p = 1;
        $pdf = $lock->toPdf();
        self::assertStringContainsString('/Type /SigFieldLock', $pdf);
        self::assertStringContainsString('/Action /All', $pdf);
        self::assertStringContainsString('/P 1', $pdf);
    }

    public function testSigFieldLockInclude(): void
    {
        $lock = new SigFieldLock(
            'Include',
            new PdfArray([new PdfString('name'), new PdfString('email')]),
        );
        $lock->objectNumber = 1;
        $pdf = $lock->toPdf();
        self::assertStringContainsString('/Action /Include', $pdf);
        self::assertStringContainsString('/Fields', $pdf);
    }

    public function testSeedValueDictionary(): void
    {
        $sv = new SeedValueDictionary();
        $sv->objectNumber = 1;
        $sv->ff = 7;
        $sv->filter = new PdfName('Adobe.PPKLite');
        $sv->subFilter = new PdfArray([new PdfName('adbe.pkcs7.detached')]);
        $sv->digestMethod = new PdfArray([new PdfName('SHA256'), new PdfName('SHA384')]);
        $sv->reasons = new PdfArray([new PdfString('Approval'), new PdfString('Certification')]);
        $sv->addRevInfo = true;
        $sv->lockDocument = true;
        $pdf = $sv->toPdf();
        self::assertStringContainsString('/Type /SV', $pdf);
        self::assertStringContainsString('/Ff 7', $pdf);
        self::assertStringContainsString('/Filter /Adobe.PPKLite', $pdf);
        self::assertStringContainsString('/SubFilter', $pdf);
        self::assertStringContainsString('/DigestMethod', $pdf);
        self::assertStringContainsString('/Reasons', $pdf);
        self::assertStringContainsString('/AddRevInfo true', $pdf);
        self::assertStringContainsString('/LockDocument true', $pdf);
    }

    public function testIdentityTransformParams(): void
    {
        $p = new IdentityTransformParams();
        $p->objectNumber = 1;
        $p->v = new PdfName('2.2');
        $pdf = $p->toPdf();
        self::assertStringContainsString('/Type /TransformParams', $pdf);
        self::assertStringContainsString('/V /2.2', $pdf);
    }
}
