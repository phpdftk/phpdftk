<?php

declare(strict_types=1);

namespace Phpdftk\Pdf\Reader\Tests\Integration;

use Phpdftk\Pdf\Core\Content\ContentStream;
use Phpdftk\Pdf\Core\Content\Resources;
use Phpdftk\Pdf\Core\Document\Catalog;
use Phpdftk\Pdf\Core\Document\Page;
use Phpdftk\Pdf\Core\Document\PageTree;
use Phpdftk\Pdf\Core\File\PdfFileWriter;
use Phpdftk\Pdf\Core\PdfArray;
use Phpdftk\Pdf\Core\PdfNumber;
use Phpdftk\Pdf\Core\PdfReference;
use Phpdftk\Pdf\Core\Security\PdfEncryptor;
use Phpdftk\Pdf\Reader\PdfReader;
use PHPUnit\Framework\TestCase;

/**
 * Tests decryption of PDFs encrypted with different algorithms and password
 * combinations, verifying cross-compatibility between writer and reader.
 *
 * These serve as "external" fixture tests — the PDFs are generated fresh
 * each run but exercise the full encrypt→serialize→parse→decrypt pipeline.
 */
class ExternalEncryptedPdfTest extends TestCase
{
    private function generateEncryptedPdf(string $method, string $userPass, string $ownerPass, string $text = 'Encrypted test'): string
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
        $cs->beginText()->setFont('F1', 12)->moveTextPosition(72, 720)->showText($text)->endText();
        $page->contents = [new PdfReference($cs->objectNumber)];

        $fileId = md5("fixture-$method-$userPass-$ownerPass", true);
        $encryptor = match ($method) {
            'rc440'  => PdfEncryptor::rc440($userPass, $ownerPass, $fileId),
            'rc4128' => PdfEncryptor::rc4128($userPass, $ownerPass, $fileId),
            'aes128' => PdfEncryptor::aes128($userPass, $ownerPass, $fileId),
        };
        $writer->setEncryption($encryptor);

        return $writer->generate();
    }

    // -----------------------------------------------------------------------
    // RC4-40
    // -----------------------------------------------------------------------

    public function testRc440UserPassword(): void
    {
        $pdf = $this->generateEncryptedPdf('rc440', 'user40', 'owner40');
        $reader = PdfReader::fromString($pdf, 'user40');
        $this->assertSame(1, $reader->getPageCount());
    }

    public function testRc440OwnerPassword(): void
    {
        $pdf = $this->generateEncryptedPdf('rc440', 'user40', 'owner40');
        $reader = PdfReader::fromString($pdf, 'owner40');
        $this->assertSame(1, $reader->getPageCount());
    }

    public function testRc440WrongPasswordRejects(): void
    {
        $pdf = $this->generateEncryptedPdf('rc440', 'user40', 'owner40');
        $this->expectException(\Phpdftk\Pdf\Reader\Exception\InvalidPdfException::class);
        PdfReader::fromString($pdf, 'wrong');
    }

    public function testRc440EmptyUserPassword(): void
    {
        $pdf = $this->generateEncryptedPdf('rc440', '', 'owner40');
        $reader = PdfReader::fromString($pdf, '');
        $this->assertSame(1, $reader->getPageCount());
    }

    // -----------------------------------------------------------------------
    // RC4-128
    // -----------------------------------------------------------------------

    public function testRc4128UserPassword(): void
    {
        $pdf = $this->generateEncryptedPdf('rc4128', 'user128', 'owner128');
        $reader = PdfReader::fromString($pdf, 'user128');
        $this->assertSame(1, $reader->getPageCount());
    }

    public function testRc4128OwnerPassword(): void
    {
        $pdf = $this->generateEncryptedPdf('rc4128', 'user128', 'owner128');
        $reader = PdfReader::fromString($pdf, 'owner128');
        $this->assertSame(1, $reader->getPageCount());
    }

    public function testRc4128SpecialCharsInPassword(): void
    {
        $pdf = $this->generateEncryptedPdf('rc4128', 'p@ss(w0rd)\\!', 'own€r');
        $reader = PdfReader::fromString($pdf, 'p@ss(w0rd)\\!');
        $this->assertSame(1, $reader->getPageCount());
    }

    public function testRc4128LongPassword(): void
    {
        $longPass = str_repeat('A', 64); // longer than 32-byte padding limit
        $pdf = $this->generateEncryptedPdf('rc4128', $longPass, 'short');
        $reader = PdfReader::fromString($pdf, $longPass);
        $this->assertSame(1, $reader->getPageCount());
    }

    // -----------------------------------------------------------------------
    // AES-128
    // -----------------------------------------------------------------------

    public function testAes128UserPassword(): void
    {
        $pdf = $this->generateEncryptedPdf('aes128', 'aesuser', 'aesowner');
        $reader = PdfReader::fromString($pdf, 'aesuser');
        $this->assertSame(1, $reader->getPageCount());
    }

    public function testAes128OwnerPassword(): void
    {
        $pdf = $this->generateEncryptedPdf('aes128', 'aesuser', 'aesowner');
        $reader = PdfReader::fromString($pdf, 'aesowner');
        $this->assertSame(1, $reader->getPageCount());
    }

    public function testAes128WrongPasswordRejects(): void
    {
        $pdf = $this->generateEncryptedPdf('aes128', 'aesuser', 'aesowner');
        $this->expectException(\Phpdftk\Pdf\Reader\Exception\InvalidPdfException::class);
        PdfReader::fromString($pdf, 'wrong');
    }

    public function testAes128SameUserAndOwnerPassword(): void
    {
        $pdf = $this->generateEncryptedPdf('aes128', 'samepass', 'samepass');
        $reader = PdfReader::fromString($pdf, 'samepass');
        $this->assertSame(1, $reader->getPageCount());
    }

    // -----------------------------------------------------------------------
    // Cross-algorithm verification
    // -----------------------------------------------------------------------

    public function testEncryptedContentNotVisibleInRawBytes(): void
    {
        $pdf = $this->generateEncryptedPdf('rc4128', 'secure', 'admin', 'Secret Message XYZ');
        $this->assertStringNotContainsString('Secret Message XYZ', $pdf);
    }

    public function testDecryptedPageStructureValid(): void
    {
        $pdf = $this->generateEncryptedPdf('aes128', 'test', 'test');
        $reader = PdfReader::fromString($pdf, 'test');
        $pages = $reader->getPages();
        $this->assertCount(1, $pages);
        $this->assertTrue($pages[0]->has('Contents'));
        $this->assertTrue($pages[0]->has('MediaBox'));
    }
}
