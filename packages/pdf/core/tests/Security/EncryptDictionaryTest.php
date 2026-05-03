<?php

declare(strict_types=1);

namespace Phpdftk\Pdf\Core\Tests\Security;

use Phpdftk\Pdf\Core\PdfArray;
use Phpdftk\Pdf\Core\PdfDictionary;
use Phpdftk\Pdf\Core\PdfName;
use Phpdftk\Pdf\Core\PdfString;
use Phpdftk\Pdf\Core\Security\CryptFilter;
use Phpdftk\Pdf\Core\Security\EncryptDictionary;
use Phpdftk\Pdf\Core\Security\PublicKeyRecipient;
use PHPUnit\Framework\TestCase;

class EncryptDictionaryTest extends TestCase
{
    public function testStandardHandlerV2Rev3(): void
    {
        $enc = new EncryptDictionary('Standard', 2);
        $enc->objectNumber = 1;
        $enc->length = 128;
        $enc->r = 3;
        $enc->o = new PdfString(str_repeat("\x00", 32));
        $enc->u = new PdfString(str_repeat("\x00", 32));
        $enc->p = -1;
        $enc->encryptMetadata = true;
        $pdf = $enc->toPdf();
        self::assertStringContainsString('/Filter /Standard', $pdf);
        self::assertStringContainsString('/V 2', $pdf);
        self::assertStringContainsString('/Length 128', $pdf);
        self::assertStringContainsString('/R 3', $pdf);
        self::assertStringContainsString('/P', $pdf);
        self::assertStringContainsString('/EncryptMetadata true', $pdf);
    }

    public function testAES256StandardR6(): void
    {
        $stdCf = new CryptFilter('AESV3');
        $stdCf->length = 32;
        $stdCf->authEvent = new PdfName('DocOpen');

        $enc = new EncryptDictionary('Standard', 5);
        $enc->objectNumber = 1;
        $enc->length = 256;
        $enc->r = 6;
        $enc->cf = new PdfDictionary(['StdCF' => $stdCf]);
        $enc->stmF = new PdfName('StdCF');
        $enc->strF = new PdfName('StdCF');
        $enc->oe = new PdfString(str_repeat("\x00", 32));
        $enc->ue = new PdfString(str_repeat("\x00", 32));
        $enc->perms = new PdfString(str_repeat("\x00", 16));
        $pdf = $enc->toPdf();
        self::assertStringContainsString('/R 6', $pdf);
        self::assertStringContainsString('/CFM /AESV3', $pdf);
        self::assertStringContainsString('/StmF /StdCF', $pdf);
        self::assertStringContainsString('/StrF /StdCF', $pdf);
        self::assertStringContainsString('/OE', $pdf);
        self::assertStringContainsString('/UE', $pdf);
        self::assertStringContainsString('/Perms', $pdf);
    }

    public function testPublicKeyHandler(): void
    {
        $recipient = new PublicKeyRecipient(new PdfString("\x30\x82fake", hex: true));
        $recipient->p = -3904;

        $enc = new EncryptDictionary('Adobe.PubSec', 4);
        $enc->objectNumber = 1;
        $enc->subFilter = new PdfName('adbe.pkcs7.s5');
        $enc->recipients = new PdfArray([$recipient]);
        $pdf = $enc->toPdf();
        self::assertStringContainsString('/Filter /Adobe.PubSec', $pdf);
        self::assertStringContainsString('/SubFilter /adbe.pkcs7.s5', $pdf);
        self::assertStringContainsString('/Recipients', $pdf);
        self::assertStringContainsString('/PKCS7', $pdf);
    }

    public function testCryptFilter(): void
    {
        $cf = new CryptFilter('V2');
        $cf->type = new PdfName('CryptFilter');
        $cf->length = 16;
        $cf->authEvent = new PdfName('EFOpen');
        $pdf = $cf->toPdf();
        self::assertStringContainsString('/Type /CryptFilter', $pdf);
        self::assertStringContainsString('/CFM /V2', $pdf);
        self::assertStringContainsString('/Length 16', $pdf);
        self::assertStringContainsString('/AuthEvent /EFOpen', $pdf);
    }

    public function testPublicKeyRecipientPermissions(): void
    {
        $r = new PublicKeyRecipient(new PdfString('test'));
        $r->p = -1;
        self::assertStringContainsString('/P -1', $r->toPdf());
    }
}
