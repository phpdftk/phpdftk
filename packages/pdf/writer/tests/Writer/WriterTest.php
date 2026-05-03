<?php

declare(strict_types=1);

namespace Phpdftk\Pdf\Writer\Tests;

use Phpdftk\Tests\Support\QpdfValidationTrait;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Phpdftk\Pdf\Writer\PdfWriter;
use Phpdftk\Pdf\Core\Document\Info;
use Phpdftk\Pdf\Core\Font\StandardFont;
use Phpdftk\Pdf\Core\Font\Type1Font;
use Phpdftk\Pdf\Core\PdfName;
use Phpdftk\Pdf\Core\PdfString;

#[Group("qpdf")]
class WriterTest extends TestCase
{
    use QpdfValidationTrait;

    public function testPdfWriterGeneratesValidPdfHeader(): void
    {
        $writer = new PdfWriter();
        $pdf = $writer->generate();
        self::assertStringStartsWith('%PDF-', $pdf);
        self::assertStringContainsString('%PDF-1.7', $pdf);
        $this->assertQpdfValidBytes($pdf);
    }

    public function testPdfWriterGeneratesWithEndMarker(): void
    {
        $writer = new PdfWriter();
        $pdf = $writer->generate();
        self::assertStringEndsWith('%%EOF', $pdf);
    }

    public function testPdfWriterContainsCatalog(): void
    {
        $writer = new PdfWriter();
        $pdf = $writer->generate();
        self::assertStringContainsString('/Type /Catalog', $pdf);
    }

    public function testPdfWriterContainsPageTree(): void
    {
        $writer = new PdfWriter();
        $writer->addPage(612, 792);
        $pdf = $writer->generate();
        self::assertStringContainsString('/Type /Pages', $pdf);
    }

    public function testPdfWriterContainsPage(): void
    {
        $writer = new PdfWriter();
        $writer->addPage(612, 792);
        $pdf = $writer->generate();
        self::assertStringContainsString('/Type /Page', $pdf);
        $this->assertQpdfValidBytes($pdf);
    }

    public function testPdfWriterAddFont(): void
    {
        $writer = new PdfWriter();
        $writer->addPage(612, 792);
        $font = $writer->addFont(new Type1Font(StandardFont::Helvetica));
        self::assertInstanceOf(\Phpdftk\Pdf\Writer\Font::class, $font);
        self::assertSame('F1', $font->getResourceName());
        self::assertSame('Helvetica', $font->getFamily());
    }

    public function testPdfWriterMultipleFontsIncrement(): void
    {
        $writer = new PdfWriter();
        $writer->addPage(612, 792);
        $font1 = $writer->addFont(new Type1Font(StandardFont::Helvetica));
        $font2 = $writer->addFont(new Type1Font(StandardFont::Courier));
        self::assertSame('F1', $font1->getResourceName());
        self::assertSame('F2', $font2->getResourceName());
    }

    public function testPdfWriterGetFonts(): void
    {
        $writer = new PdfWriter();
        $writer->addPage(612, 792);
        $font = new Type1Font(StandardFont::Helvetica);
        $writer->addFont($font);
        $fonts = $writer->getFonts();
        self::assertArrayHasKey('F1', $fonts);
        self::assertSame($font, $fonts['F1']);
    }

    public function testPdfWriterAddContentStream(): void
    {
        $writer = new PdfWriter(compressStreams: false);
        $page = $writer->addPage(612, 792);
        $cs = $writer->addContentStream($page);
        $cs->beginText()->setFont('F1', 12)->showText('Hello')->endText();
        $pdf = $writer->generate();
        self::assertStringContainsString('BT', $pdf);
        self::assertStringContainsString('ET', $pdf);
        $this->assertQpdfValidBytes($pdf);
    }

    public function testPdfWriterGetContentStreams(): void
    {
        $writer = new PdfWriter();
        $page = $writer->addPage(612, 792);
        $cs = $writer->addContentStream($page);
        // addContentStream creates a stream via the writer; the page's own
        // internal stream (from Page::contentStream()) is separate.
        self::assertGreaterThanOrEqual(1, count($writer->getContentStreams()));
        self::assertSame($cs, $writer->getContentStreams()[0]);
    }

    public function testPdfWriterWithInfo(): void
    {
        $writer = new PdfWriter();
        $info = new Info();
        $info->title = new PdfString('Test PDF');
        $info->author = new PdfString('Test Suite');
        $writer->setInfo($info);
        $pdf = $writer->generate();
        self::assertStringContainsString('/Title', $pdf);
        self::assertStringContainsString('/Info', $pdf);
        $this->assertQpdfValidBytes($pdf);
    }

    public function testPdfWriterGetCatalog(): void
    {
        $writer = new PdfWriter();
        $catalog = $writer->getCatalog();
        self::assertInstanceOf(\Phpdftk\Pdf\Core\Document\Catalog::class, $catalog);
    }

    public function testPdfWriterGetPageTree(): void
    {
        $writer = new PdfWriter();
        $pt = $writer->getPageTree();
        self::assertInstanceOf(\Phpdftk\Pdf\Core\Document\PageTree::class, $pt);
    }

    public function testPdfWriterSavesToFile(): void
    {
        $writer = new PdfWriter();
        $writer->addPage(612, 792);
        $outputPath = sys_get_temp_dir() . '/phpdftk_test_' . uniqid() . '.pdf';
        $writer->save($outputPath);
        self::assertFileExists($outputPath);
        $content = file_get_contents($outputPath);
        self::assertIsString($content);
        self::assertStringStartsWith('%PDF-', $content);
        $this->assertQpdfValid($outputPath);
        unlink($outputPath);
    }

    public function testPdfWriterContainsXref(): void
    {
        $writer = new PdfWriter();
        $writer->addPage();
        $pdf = $writer->generate();
        self::assertStringContainsString('xref', $pdf);
        self::assertStringContainsString('startxref', $pdf);
        $this->assertQpdfValidBytes($pdf);
    }

    public function testPdfWriterContainsTrailer(): void
    {
        $writer = new PdfWriter();
        $writer->addPage();
        $pdf = $writer->generate();
        self::assertStringContainsString('trailer', $pdf);
        self::assertStringContainsString('/Size', $pdf);
        self::assertStringContainsString('/Root', $pdf);
    }

    public function testPdfWriterAddPageWithRectangle(): void
    {
        $writer = new PdfWriter();
        $rect = new \Phpdftk\Geometry\Rectangle(0, 0, 595, 842);
        $page = $writer->addPage($rect);
        $pdf = $writer->generate();
        self::assertStringContainsString('/MediaBox', $pdf);
        $this->assertQpdfValidBytes($pdf);
    }

    public function testPdfWriterRegisterObject(): void
    {
        $writer = new PdfWriter();
        $page = $writer->addPage();
        $action = new \Phpdftk\Pdf\Core\Action\GoToAction(new PdfName('First'));
        $ref = $writer->register($action);
        $pdf = $writer->generate();
        self::assertStringContainsString('/S /GoTo', $pdf);
        $this->assertQpdfValidBytes($pdf);
    }

    public function testPdfWriterFontAddedToPage(): void
    {
        $writer = new PdfWriter();
        $page = $writer->addPage(612, 792);
        $writer->addFont(new Type1Font(StandardFont::Helvetica), $page);
        $pdf = $writer->generate();
        self::assertStringContainsString('/Font', $pdf);
        $this->assertQpdfValidBytes($pdf);
    }

    public function testSetNamedDestinations(): void
    {
        $writer = new PdfWriter();
        $page = $writer->addPage(612, 792);
        $pageRef = new \Phpdftk\Pdf\Core\PdfReference($page->corePage()->objectNumber);
        $dest = \Phpdftk\Pdf\Core\Document\Destination::fit($pageRef);
        $writer->setNamedDestinations(['chapter1' => $dest]);
        $pdf = $writer->generate();
        self::assertStringContainsString('chapter1', $pdf);
        self::assertStringContainsString('/Names', $pdf);
        self::assertStringContainsString('/Dests', $pdf);
        $this->assertQpdfValidBytes($pdf);
    }

    public function testSetEncryptionProducesEncryptedPdf(): void
    {
        $writer = new PdfWriter(compressStreams: false);
        $page = $writer->addPage(612, 792);
        $font = $writer->addFont(new Type1Font(StandardFont::Helvetica));

        $cs = $writer->addContentStream($page);
        $cs->beginText()
            ->setFont($font->getResourceName(), 12)
            ->moveTextPosition(72, 720)
            ->showText('Encrypted via PdfWriter')
            ->endText();

        $fileId = md5('encryption-test', true);
        $encryptor = \Phpdftk\Pdf\Core\Security\PdfEncryptor::aes128('user', 'owner', $fileId);
        $writer->setEncryption($encryptor);

        $pdf = $writer->generate();

        self::assertStringStartsWith('%PDF-', $pdf);
        self::assertStringContainsString('/Encrypt', $pdf);
        self::assertStringContainsString('/Filter /Standard', $pdf);

        // Round-trip: decrypt with user password and verify
        $reader = \Phpdftk\Pdf\Reader\PdfReader::fromString($pdf, 'user');
        self::assertSame(1, $reader->getPageCount());
        $this->assertQpdfValidBytes($pdf);
    }

    public function testSetEncryptionAes256RoundTrip(): void
    {
        $writer = new PdfWriter(compressStreams: false);
        $page = $writer->addPage(612, 792);
        $font = $writer->addFont(new Type1Font(StandardFont::Helvetica));

        $cs = $writer->addContentStream($page);
        $cs->beginText()
            ->setFont($font->getResourceName(), 12)
            ->moveTextPosition(72, 720)
            ->showText('AES-256 encrypted')
            ->endText();

        $fileId = md5('aes256-test', true);
        $encryptor = \Phpdftk\Pdf\Core\Security\PdfEncryptor::aes256('pass', 'owner', $fileId);
        $writer->setEncryption($encryptor);

        $pdf = $writer->generate();

        self::assertStringContainsString('/Encrypt', $pdf);
        self::assertStringContainsString('AESV3', $pdf);

        $reader = \Phpdftk\Pdf\Reader\PdfReader::fromString($pdf, 'pass');
        self::assertSame(1, $reader->getPageCount());
        $this->assertQpdfValidBytes($pdf);
    }
}
