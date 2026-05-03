<?php

declare(strict_types=1);

namespace Phpdftk\Pdf\Core\Tests\Security;

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

class PublicKeyEncryptorTest extends TestCase
{
    private static ?array $credentials = null;
    private static ?array $credentials2 = null;

    public static function setUpBeforeClass(): void
    {
        $config = [
            'digest_alg' => 'sha256',
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ];

        // First recipient
        $key1 = openssl_pkey_new($config);
        $csr1 = openssl_csr_new(['commonName' => 'recipient1'], $key1, $config);
        $cert1 = openssl_csr_sign($csr1, null, $key1, 365, $config);
        openssl_x509_export($cert1, $certPem1);
        openssl_pkey_export($key1, $keyPem1);
        self::$credentials = ['cert' => $certPem1, 'key' => $keyPem1];

        // Second recipient
        $key2 = openssl_pkey_new($config);
        $csr2 = openssl_csr_new(['commonName' => 'recipient2'], $key2, $config);
        $cert2 = openssl_csr_sign($csr2, null, $key2, 365, $config);
        openssl_x509_export($cert2, $certPem2);
        openssl_pkey_export($key2, $keyPem2);
        self::$credentials2 = ['cert' => $certPem2, 'key' => $keyPem2];
    }

    public function testPublicKeyEncryptedPdfIsValid(): void
    {
        $pdf = $this->generatePublicKeyPdf([self::$credentials['cert']]);

        $this->assertStringStartsWith('%PDF-', $pdf);
        $this->assertStringEndsWith('%%EOF', $pdf);
        $this->assertStringContainsString('/Filter /Adobe.PubSec', $pdf);
        $this->assertStringContainsString('AESV2', $pdf);
    }

    public function testPublicKeyRoundTripWithRecipient(): void
    {
        $pdf = $this->generatePublicKeyPdf([self::$credentials['cert']]);

        $reader = PdfReader::fromStringPublicKey(
            $pdf, self::$credentials['cert'], self::$credentials['key']
        );
        $this->assertSame(1, $reader->getPageCount());
        $this->assertSame('1.7', $reader->getVersion());
    }

    public function testPublicKeyFailsWithWrongCertificate(): void
    {
        $pdf = $this->generatePublicKeyPdf([self::$credentials['cert']]);

        $this->expectException(\Phpdftk\Pdf\Reader\Exception\InvalidPdfException::class);
        $this->expectExceptionMessage('No matching recipient');
        PdfReader::fromStringPublicKey(
            $pdf, self::$credentials2['cert'], self::$credentials2['key']
        );
    }

    public function testPublicKeyMultipleRecipients(): void
    {
        $pdf = $this->generatePublicKeyPdf([
            self::$credentials['cert'],
            self::$credentials2['cert'],
        ]);

        // Both recipients should be able to decrypt
        $reader1 = PdfReader::fromStringPublicKey(
            $pdf, self::$credentials['cert'], self::$credentials['key']
        );
        $this->assertSame(1, $reader1->getPageCount());

        $reader2 = PdfReader::fromStringPublicKey(
            $pdf, self::$credentials2['cert'], self::$credentials2['key']
        );
        $this->assertSame(1, $reader2->getPageCount());
    }

    public function testPublicKeyContentIsEncrypted(): void
    {
        $pdf = $this->generatePublicKeyPdf([self::$credentials['cert']]);

        // Plaintext should not be visible
        $this->assertStringNotContainsString('Hello Public Key World', $pdf);

        // But structure is accessible after decryption
        $reader = PdfReader::fromStringPublicKey(
            $pdf, self::$credentials['cert'], self::$credentials['key']
        );
        $pages = $reader->getPages();
        $this->assertCount(1, $pages);
        $this->assertTrue($pages[0]->has('Contents'));
    }

    public function testPublicKeyEncryptedPdfSavesToFile(): void
    {
        $pdf = $this->generatePublicKeyPdf([self::$credentials['cert']]);

        $outputDir = dirname(__DIR__, 2) . '/tests/output';
        if (!is_dir($outputDir)) {
            mkdir($outputDir, 0o755, true);
        }
        $path = $outputDir . '/public_key_encrypted.pdf';
        file_put_contents($path, $pdf);

        $this->assertFileExists($path);

        $reader = PdfReader::fromFilePublicKey(
            $path, self::$credentials['cert'], self::$credentials['key']
        );
        $this->assertSame(1, $reader->getPageCount());
    }

    public function testPasswordReaderDetectsPublicKeyAndThrows(): void
    {
        $pdf = $this->generatePublicKeyPdf([self::$credentials['cert']]);

        $this->expectException(\Phpdftk\Pdf\Reader\Exception\InvalidPdfException::class);
        $this->expectExceptionMessage('public-key encryption');
        PdfReader::fromString($pdf, 'somepassword');
    }

    public function testPublicKeyAes256EncryptedPdfIsValid(): void
    {
        $pdf = $this->generatePublicKeyPdf([self::$credentials['cert']], 'aes256');

        $this->assertStringStartsWith('%PDF-', $pdf);
        $this->assertStringContainsString('/Filter /Adobe.PubSec', $pdf);
        $this->assertStringContainsString('AESV3', $pdf);
    }

    public function testPublicKeyAes256RoundTrip(): void
    {
        $pdf = $this->generatePublicKeyPdf([self::$credentials['cert']], 'aes256');

        $reader = PdfReader::fromStringPublicKey(
            $pdf, self::$credentials['cert'], self::$credentials['key']
        );
        $this->assertSame(1, $reader->getPageCount());
    }

    public function testPublicKeyAes256ContentIsEncrypted(): void
    {
        $pdf = $this->generatePublicKeyPdf([self::$credentials['cert']], 'aes256');

        $this->assertStringNotContainsString('Hello Public Key World', $pdf);
    }

    /**
     * @param string[] $certificates
     */
    private function generatePublicKeyPdf(array $certificates, string $mode = 'aes128'): string
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
            ->showText('Hello Public Key World')
            ->endText();
        $page->contents = [new PdfReference($cs->objectNumber)];

        // Set up public-key encryption
        $fileId = md5('test-public-key', true);
        $recipientList = array_map(
            fn(string $cert) => ['cert' => $cert],
            $certificates
        );
        $encryptor = match ($mode) {
            'aes256' => PdfEncryptor::publicKeyAes256($recipientList, $fileId),
            default => PdfEncryptor::publicKeyAes128($recipientList, $fileId),
        };
        $writer->setEncryption($encryptor);

        return $writer->generate();
    }
}
