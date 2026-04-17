<?php

declare(strict_types=1);

namespace ApprLabs\Pdf\Core\Tests\Security;

use ApprLabs\Pdf\Core\Content\ContentStream;
use ApprLabs\Pdf\Core\Content\Resources;
use ApprLabs\Pdf\Core\Document\Catalog;
use ApprLabs\Pdf\Core\Document\Page;
use ApprLabs\Pdf\Core\Document\PageTree;
use ApprLabs\Pdf\Core\File\PdfFileWriter;
use ApprLabs\Pdf\Core\PdfArray;
use ApprLabs\Pdf\Core\PdfNumber;
use ApprLabs\Pdf\Core\PdfReference;
use ApprLabs\Pdf\Core\Security\PdfEncryptor;
use ApprLabs\Pdf\Reader\PdfReader;
use PHPUnit\Framework\TestCase;

class PdfEncryptorTest extends TestCase
{
    public function testRc4128EncryptedPdfIsValid(): void
    {
        $pdf = $this->generateEncryptedPdf('rc4128', 'user', 'owner');

        $this->assertStringStartsWith('%PDF-', $pdf);
        $this->assertStringEndsWith('%%EOF', $pdf);
        $this->assertStringContainsString('/Encrypt', $pdf);
        $this->assertStringContainsString('/Filter /Standard', $pdf);
    }

    public function testAes128EncryptedPdfIsValid(): void
    {
        $pdf = $this->generateEncryptedPdf('aes128', 'user', 'owner');

        $this->assertStringStartsWith('%PDF-', $pdf);
        $this->assertStringEndsWith('%%EOF', $pdf);
        $this->assertStringContainsString('/Encrypt', $pdf);
        $this->assertStringContainsString('/Filter /Standard', $pdf);
        $this->assertStringContainsString('AESV2', $pdf);
    }

    public function testRc4128RoundTripWithUserPassword(): void
    {
        $pdf = $this->generateEncryptedPdf('rc4128', 'mypass', 'ownerpass');

        $reader = PdfReader::fromString($pdf, 'mypass');
        $this->assertSame(1, $reader->getPageCount());
        $this->assertSame('1.7', $reader->getVersion());
    }

    public function testRc4128RoundTripWithOwnerPassword(): void
    {
        $pdf = $this->generateEncryptedPdf('rc4128', 'mypass', 'ownerpass');

        $reader = PdfReader::fromString($pdf, 'ownerpass');
        $this->assertSame(1, $reader->getPageCount());
    }

    public function testRc4128FailsWithWrongPassword(): void
    {
        $pdf = $this->generateEncryptedPdf('rc4128', 'mypass', 'ownerpass');

        $this->expectException(\ApprLabs\Pdf\Reader\Exception\InvalidPdfException::class);
        $this->expectExceptionMessage('Invalid password');
        PdfReader::fromString($pdf, 'wrongpass');
    }

    public function testAes128RoundTripWithUserPassword(): void
    {
        $pdf = $this->generateEncryptedPdf('aes128', 'secret', 'admin');

        $reader = PdfReader::fromString($pdf, 'secret');
        $this->assertSame(1, $reader->getPageCount());
    }

    public function testAes128RoundTripWithOwnerPassword(): void
    {
        $pdf = $this->generateEncryptedPdf('aes128', 'secret', 'admin');

        $reader = PdfReader::fromString($pdf, 'admin');
        $this->assertSame(1, $reader->getPageCount());
    }

    public function testAes128FailsWithWrongPassword(): void
    {
        $pdf = $this->generateEncryptedPdf('aes128', 'secret', 'admin');

        $this->expectException(\ApprLabs\Pdf\Reader\Exception\InvalidPdfException::class);
        PdfReader::fromString($pdf, 'wrong');
    }

    public function testRc440RoundTripWithUserPassword(): void
    {
        $pdf = $this->generateEncryptedPdf('rc440', 'mypass', 'ownerpass');

        $reader = PdfReader::fromString($pdf, 'mypass');
        $this->assertSame(1, $reader->getPageCount());
    }

    public function testRc440RoundTripWithOwnerPassword(): void
    {
        $pdf = $this->generateEncryptedPdf('rc440', 'mypass', 'ownerpass');

        $reader = PdfReader::fromString($pdf, 'ownerpass');
        $this->assertSame(1, $reader->getPageCount());
    }

    public function testEncryptedPdfWithEmptyUserPassword(): void
    {
        $pdf = $this->generateEncryptedPdf('rc4128', '', 'owneronly');

        // Should be readable with empty password
        $reader = PdfReader::fromString($pdf, '');
        $this->assertSame(1, $reader->getPageCount());

        // Should also work with owner password
        $reader = PdfReader::fromString($pdf, 'owneronly');
        $this->assertSame(1, $reader->getPageCount());
    }

    public function testEncryptedContentStreamIsDecrypted(): void
    {
        $pdf = $this->generateEncryptedPdf('rc4128', 'test', 'test');

        // The literal text should NOT be visible in the raw encrypted bytes
        // (compressed + encrypted)
        $this->assertStringNotContainsString('Hello Encrypted World', $pdf);

        // But after reading with the correct password, we should be able
        // to access the page structure
        $reader = PdfReader::fromString($pdf, 'test');
        $pages = $reader->getPages();
        $this->assertCount(1, $pages);
        $this->assertTrue($pages[0]->has('Contents'));
    }

    public function testAes256EncryptedPdfIsValid(): void
    {
        $pdf = $this->generateEncryptedPdf('aes256', 'user', 'owner');

        $this->assertStringStartsWith('%PDF-', $pdf);
        $this->assertStringEndsWith('%%EOF', $pdf);
        $this->assertStringContainsString('/Encrypt', $pdf);
        $this->assertStringContainsString('/Filter /Standard', $pdf);
        $this->assertStringContainsString('AESV3', $pdf);
        $this->assertStringContainsString('/V 5', $pdf);
        $this->assertStringContainsString('/R 6', $pdf);
    }

    public function testAes256RoundTripWithUserPassword(): void
    {
        $pdf = $this->generateEncryptedPdf('aes256', 'secret256', 'admin256');

        $reader = PdfReader::fromString($pdf, 'secret256');
        $this->assertSame(1, $reader->getPageCount());
        $this->assertSame('1.7', $reader->getVersion());
    }

    public function testAes256RoundTripWithOwnerPassword(): void
    {
        $pdf = $this->generateEncryptedPdf('aes256', 'secret256', 'admin256');

        $reader = PdfReader::fromString($pdf, 'admin256');
        $this->assertSame(1, $reader->getPageCount());
    }

    public function testAes256FailsWithWrongPassword(): void
    {
        $pdf = $this->generateEncryptedPdf('aes256', 'secret256', 'admin256');

        $this->expectException(\ApprLabs\Pdf\Reader\Exception\InvalidPdfException::class);
        $this->expectExceptionMessage('Invalid password');
        PdfReader::fromString($pdf, 'wrongpass');
    }

    public function testAes256EncryptedContentNotVisible(): void
    {
        $pdf = $this->generateEncryptedPdf('aes256', 'test256', 'test256');

        // The literal text should NOT be visible in the raw encrypted bytes
        $this->assertStringNotContainsString('Hello Encrypted World', $pdf);

        // But after reading with the correct password, we should be able
        // to access the page structure
        $reader = PdfReader::fromString($pdf, 'test256');
        $pages = $reader->getPages();
        $this->assertCount(1, $pages);
        $this->assertTrue($pages[0]->has('Contents'));
    }

    public function testPermissionsArePreserved(): void
    {
        $fileId = md5('test-perms', true);
        $encryptor = PdfEncryptor::rc4128(
            'user', 'owner', $fileId,
            PdfEncryptor::PERM_PRINT | PdfEncryptor::PERM_COPY
        );
        $dict = $encryptor->getEncryptDictionary();
        $this->assertNotNull($dict->p);
        // The low bits should contain the permission flags
        $this->assertTrue(($dict->p & PdfEncryptor::PERM_PRINT) !== 0);
        $this->assertTrue(($dict->p & PdfEncryptor::PERM_COPY) !== 0);
    }

    private function generateEncryptedPdf(string $method, string $userPass, string $ownerPass): string
    {
        $writer = new PdfFileWriter(compressStreams: false);

        $catalog = new Catalog();
        $writer->setCatalog($catalog);

        $pageTree = new PageTree();
        $writer->register($pageTree);
        $catalog->pages = new PdfReference($pageTree->objectNumber);

        $page = new Page();
        $writer->register($page);
        $page->parent = new PdfReference($pageTree->objectNumber);
        $page->mediaBox = new PdfArray([
            new PdfNumber(0), new PdfNumber(0),
            new PdfNumber(612), new PdfNumber(792),
        ]);
        $page->resources = new Resources();

        $pageTree->kids = [new PdfReference($page->objectNumber)];
        $pageTree->count = 1;

        $cs = new ContentStream();
        $writer->register($cs);
        $cs->beginText()
            ->setFont('F1', 12)
            ->moveTextPosition(72, 720)
            ->showText('Hello Encrypted World')
            ->endText();
        $page->contents = [new PdfReference($cs->objectNumber)];

        // Set up encryption
        $fileId = md5("test-$method-$userPass", true);
        $encryptor = match ($method) {
            'rc440'  => PdfEncryptor::rc440($userPass, $ownerPass, $fileId),
            'rc4128' => PdfEncryptor::rc4128($userPass, $ownerPass, $fileId),
            'aes128' => PdfEncryptor::aes128($userPass, $ownerPass, $fileId),
            'aes256' => PdfEncryptor::aes256($userPass, $ownerPass, $fileId),
        };
        $writer->setEncryption($encryptor);

        return $writer->generate();
    }
}
