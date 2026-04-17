<?php

declare(strict_types=1);

namespace ApprLabs\Pdf\Writer\Tests;

use PHPUnit\Framework\TestCase;
use ApprLabs\Pdf\Writer\PdfWriter;
use ApprLabs\Pdf\Core\Document\Info;
use ApprLabs\Pdf\Core\Font\StandardFont;
use ApprLabs\Pdf\Core\Font\Type1Font;
use ApprLabs\Pdf\Core\PdfName;
use ApprLabs\Pdf\Core\PdfString;

class WriterTest extends TestCase
{
    public function testPdfWriterGeneratesValidPdfHeader(): void
    {
        $writer = new PdfWriter();
        $pdf = $writer->generate();
        self::assertStringStartsWith('%PDF-', $pdf);
        self::assertStringContainsString('%PDF-1.7', $pdf);
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
    }

    public function testPdfWriterAddFont(): void
    {
        $writer = new PdfWriter();
        $writer->addPage(612, 792);
        $name = $writer->addFont(new Type1Font(StandardFont::Helvetica));
        self::assertSame('F1', $name);
    }

    public function testPdfWriterMultipleFontsIncrement(): void
    {
        $writer = new PdfWriter();
        $writer->addPage(612, 792);
        $name1 = $writer->addFont(new Type1Font(StandardFont::Helvetica));
        $name2 = $writer->addFont(new Type1Font(StandardFont::Courier));
        self::assertSame('F1', $name1);
        self::assertSame('F2', $name2);
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
    }

    public function testPdfWriterGetContentStreams(): void
    {
        $writer = new PdfWriter();
        $page = $writer->addPage(612, 792);
        $cs = $writer->addContentStream($page);
        $streams = $writer->getContentStreams();
        self::assertCount(1, $streams);
        self::assertSame($cs, $streams[0]);
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
    }

    public function testPdfWriterGetCatalog(): void
    {
        $writer = new PdfWriter();
        $catalog = $writer->getCatalog();
        self::assertInstanceOf(\ApprLabs\Pdf\Core\Document\Catalog::class, $catalog);
    }

    public function testPdfWriterGetPageTree(): void
    {
        $writer = new PdfWriter();
        $pt = $writer->getPageTree();
        self::assertInstanceOf(\ApprLabs\Pdf\Core\Document\PageTree::class, $pt);
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
        unlink($outputPath);
    }

    public function testPdfWriterContainsXref(): void
    {
        $writer = new PdfWriter();
        $writer->addPage();
        $pdf = $writer->generate();
        self::assertStringContainsString('xref', $pdf);
        self::assertStringContainsString('startxref', $pdf);
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
        $rect = new \ApprLabs\Geometry\Rectangle(0, 0, 595, 842);
        $page = $writer->addPage($rect);
        $pdf = $writer->generate();
        self::assertStringContainsString('/MediaBox', $pdf);
    }

    public function testPdfWriterRegisterObject(): void
    {
        $writer = new PdfWriter();
        $page = $writer->addPage();
        $action = new \ApprLabs\Pdf\Core\Action\GoToAction(new PdfName('First'));
        $ref = $writer->register($action);
        $pdf = $writer->generate();
        self::assertStringContainsString('/S /GoTo', $pdf);
    }

    public function testPdfWriterFontAddedToPage(): void
    {
        $writer = new PdfWriter();
        $page = $writer->addPage(612, 792);
        $writer->addFont(new Type1Font(StandardFont::Helvetica), $page);
        $pdf = $writer->generate();
        self::assertStringContainsString('/Font', $pdf);
    }

    public function testSetNamedDestinations(): void
    {
        $writer = new PdfWriter();
        $page = $writer->addPage(612, 792);
        $pageRef = new \ApprLabs\Pdf\Core\PdfReference($page->objectNumber);
        $dest = \ApprLabs\Pdf\Core\Document\Destination::fit($pageRef);
        $writer->setNamedDestinations(['chapter1' => $dest]);
        $pdf = $writer->generate();
        self::assertStringContainsString('chapter1', $pdf);
        self::assertStringContainsString('/Names', $pdf);
        self::assertStringContainsString('/Dests', $pdf);
    }
}
