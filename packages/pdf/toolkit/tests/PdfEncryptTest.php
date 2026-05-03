<?php

declare(strict_types=1);

namespace Phpdftk\Pdf\Toolkit\Tests;

use Phpdftk\Pdf\Core\Font\StandardFont;
use Phpdftk\Pdf\Core\Font\Type1Font;
use Phpdftk\Pdf\Reader\PdfReader;
use Phpdftk\Pdf\Toolkit\Encryption\EncryptionMethod;
use Phpdftk\Pdf\Toolkit\Encryption\Permission;
use Phpdftk\Pdf\Toolkit\PdfEncrypt;
use Phpdftk\Pdf\Writer\PdfWriter;
use Phpdftk\Tests\Support\QpdfValidationTrait;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

#[Group("qpdf")]
class PdfEncryptTest extends TestCase
{
    use QpdfValidationTrait;
    private function generatePdf(): string
    {
        $writer = new PdfWriter(compressStreams: false);
        $font = $writer->addFont(new Type1Font(StandardFont::Helvetica));

        $page = $writer->addPage(612, 792);
        $cs = $writer->addContentStream($page);
        $cs->beginText()
            ->setFont($font->getResourceName(), 12)
            ->moveTextPosition(72, 720)
            ->showText('Encrypt test content')
            ->endText();

        return $writer->generate();
    }

    public function testEncryptAes128(): void
    {
        $pdf = $this->generatePdf();
        $result = PdfEncrypt::openString($pdf)
            ->encrypt('user', 'owner', EncryptionMethod::Aes128)
            ->toBytes();

        $this->assertStringStartsWith('%PDF', $result);
        $this->assertQpdfValidBytes($result);

        // Should be readable with password
        $reader = PdfReader::fromString($result, 'user');
        $this->assertSame(1, $reader->getPageCount());
    }

    public function testEncryptAes256(): void
    {
        $pdf = $this->generatePdf();
        $result = PdfEncrypt::openString($pdf)
            ->encrypt('pass', 'owner', EncryptionMethod::Aes256)
            ->toBytes();

        $this->assertStringStartsWith('%PDF', $result);
        $this->assertQpdfValidBytes($result);

        $reader = PdfReader::fromString($result, 'pass');
        $this->assertSame(1, $reader->getPageCount());
    }

    public function testDecrypt(): void
    {
        // First encrypt
        $pdf = $this->generatePdf();
        $encrypted = PdfEncrypt::openString($pdf)
            ->encrypt('secret', 'owner', EncryptionMethod::Aes128)
            ->toBytes();

        // Then decrypt
        $decrypted = PdfEncrypt::openString($encrypted, 'secret')
            ->decrypt()
            ->toBytes();

        $this->assertStringStartsWith('%PDF', $decrypted);
        $this->assertQpdfValidBytes($decrypted);

        // Should be readable without password
        $reader = PdfReader::fromString($decrypted);
        $this->assertSame(1, $reader->getPageCount());
        $text = $reader->extractText(0);
        $this->assertStringContainsString('Encrypt test', $text);
    }

    public function testIsEncrypted(): void
    {
        $pdf = $this->generatePdf();
        $encryptor = PdfEncrypt::openString($pdf);
        $this->assertFalse($encryptor->isEncrypted());
    }

    public function testNoOpsReturnsOriginal(): void
    {
        $pdf = $this->generatePdf();
        $result = PdfEncrypt::openString($pdf)->toBytes();
        $this->assertSame($pdf, $result);
    }

    public function testPageCount(): void
    {
        $pdf = $this->generatePdf();
        $encryptor = PdfEncrypt::openString($pdf);
        $this->assertSame(1, $encryptor->getPageCount());
    }

    public function testEscapeHatch(): void
    {
        $pdf = $this->generatePdf();
        $encryptor = PdfEncrypt::openString($pdf);
        $this->assertInstanceOf(PdfReader::class, $encryptor->getReader());
    }
}
