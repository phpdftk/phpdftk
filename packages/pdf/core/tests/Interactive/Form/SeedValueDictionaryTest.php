<?php

declare(strict_types=1);

namespace Phpdftk\Pdf\Core\Tests\Interactive\Form;

use Phpdftk\Pdf\Core\Interactive\Form\SeedValueDictionary;
use Phpdftk\Pdf\Core\PdfArray;
use Phpdftk\Pdf\Core\PdfDictionary;
use Phpdftk\Pdf\Core\PdfName;
use Phpdftk\Pdf\Core\PdfString;
use PHPUnit\Framework\TestCase;

class SeedValueDictionaryTest extends TestCase
{
    public function testMinimal(): void
    {
        $sv = new SeedValueDictionary();
        $pdf = $sv->toPdf();
        $this->assertStringContainsString('/Type /SV', $pdf);
    }

    public function testAllFields(): void
    {
        $sv = new SeedValueDictionary();
        $sv->ff = 4;
        $sv->filter = new PdfName('Adobe.PPKLite');
        $sv->subFilter = new PdfArray([new PdfName('adbe.pkcs7.detached')]);
        $sv->digestMethod = new PdfArray([new PdfName('SHA256')]);
        $sv->v = 2.0;
        $sv->cert = new PdfDictionary();
        $sv->reasons = new PdfArray([new PdfString('Approve')]);
        $sv->mdp = new PdfDictionary();
        $sv->timeStamp = new PdfDictionary();
        $sv->legalAttestation = new PdfArray([new PdfString('attest')]);
        $sv->addRevInfo = true;
        $sv->lockDocument = false;
        $sv->appearanceFilter = new PdfString('app filter');

        $pdf = $sv->toPdf();
        foreach (['/Ff 4', '/Filter /Adobe.PPKLite', '/SubFilter', '/DigestMethod', '/V 2',
            '/Cert', '/Reasons', '/MDP', '/TimeStamp', '/LegalAttestation',
            '/AddRevInfo true', '/LockDocument false', '/AppearanceFilter'] as $needle) {
            $this->assertStringContainsString($needle, $pdf);
        }
    }
}
