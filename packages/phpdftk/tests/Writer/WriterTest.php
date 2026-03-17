<?php

declare(strict_types=1);

namespace Phpdftk\Tests\Writer;

use PHPUnit\Framework\TestCase;
use Phpdftk\Writer\CrossReferenceTable;
use Phpdftk\Writer\ObjectRegistry;
use Phpdftk\Writer\PdfWriter;
use Phpdftk\Document\Info;
use Phpdftk\Font\StandardFont;
use Phpdftk\Font\Type1Font;
use Phpdftk\Core\PdfDictionary;
use Phpdftk\Core\PdfName;
use Phpdftk\Core\PdfObject;
use Phpdftk\Core\PdfString;

class WriterTest extends TestCase
{
    // -----------------------------------------------------------------------
    // CrossReferenceTable
    // -----------------------------------------------------------------------

    public function testCrossReferenceTableBuildStartsWithXref(): void
    {
        $xref = new CrossReferenceTable();
        $result = $xref->build(1);
        self::assertStringStartsWith("xref\n", $result);
    }

    public function testCrossReferenceTableSizeInHeader(): void
    {
        $xref = new CrossReferenceTable();
        $result = $xref->build(5);
        self::assertStringContainsString("0 5\n", $result);
    }

    public function testCrossReferenceTableFreeListHead(): void
    {
        $xref = new CrossReferenceTable();
        $result = $xref->build(1);
        self::assertStringContainsString('0000000000 65535 f', $result);
    }

    public function testCrossReferenceTableWithEntry(): void
    {
        $xref = new CrossReferenceTable();
        $xref->add(1, 15);
        $result = $xref->build(2);
        self::assertStringContainsString('0000000015 00000 n', $result);
    }

    public function testCrossReferenceTableWithMultipleEntries(): void
    {
        $xref = new CrossReferenceTable();
        $xref->add(1, 15);
        $xref->add(2, 200);
        $xref->add(3, 512);
        $result = $xref->build(4);
        self::assertStringContainsString('0000000015 00000 n', $result);
        self::assertStringContainsString('0000000200 00000 n', $result);
        self::assertStringContainsString('0000000512 00000 n', $result);
    }

    public function testCrossReferenceTableEntryFormat(): void
    {
        $xref = new CrossReferenceTable();
        $xref->add(1, 9999);
        $result = $xref->build(2);
        // Entry must be exactly formatted as 10-digit offset
        self::assertStringContainsString('0000009999', $result);
    }

    public function testCrossReferenceTableMissingEntryDefaultsToZero(): void
    {
        $xref = new CrossReferenceTable();
        // Don't add entry for object 1
        $result = $xref->build(2);
        self::assertStringContainsString('0000000000 00000 n', $result);
    }

    // -----------------------------------------------------------------------
    // ObjectRegistry
    // -----------------------------------------------------------------------

    public function testObjectRegistryInitialSizeIsOne(): void
    {
        $reg = new ObjectRegistry();
        self::assertSame(1, $reg->getSize());
    }

    public function testObjectRegistryRegisterReturnsObjectNumber(): void
    {
        $reg = new ObjectRegistry();
        $obj = new Type1Font(StandardFont::Helvetica);
        $num = $reg->register($obj);
        self::assertSame(1, $num);
        self::assertSame(1, $obj->objectNumber);
    }

    public function testObjectRegistrySequentialNumbering(): void
    {
        $reg = new ObjectRegistry();
        $obj1 = new Type1Font(StandardFont::Helvetica);
        $obj2 = new Type1Font(StandardFont::Courier);
        $num1 = $reg->register($obj1);
        $num2 = $reg->register($obj2);
        self::assertSame(1, $num1);
        self::assertSame(2, $num2);
    }

    public function testObjectRegistryGetAll(): void
    {
        $reg = new ObjectRegistry();
        $obj = new Type1Font(StandardFont::Helvetica);
        $reg->register($obj);
        $all = $reg->getAll();
        self::assertCount(1, $all);
        self::assertSame($obj, $all[1]);
    }

    public function testObjectRegistrySizeAfterRegistration(): void
    {
        $reg = new ObjectRegistry();
        $obj1 = new Type1Font(StandardFont::Helvetica);
        $obj2 = new Type1Font(StandardFont::Courier);
        $obj3 = new Type1Font(StandardFont::TimesRoman);
        $reg->register($obj1);
        $reg->register($obj2);
        $reg->register($obj3);
        self::assertSame(4, $reg->getSize()); // 0 (free head) + 3 objects
    }

    public function testObjectRegistryGenerationNumberIsZero(): void
    {
        $reg = new ObjectRegistry();
        $obj = new Type1Font(StandardFont::Helvetica);
        $reg->register($obj);
        self::assertSame(0, $obj->generationNumber);
    }

    // -----------------------------------------------------------------------
    // PdfWriter
    // -----------------------------------------------------------------------

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
        $writer = new PdfWriter();
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
        self::assertInstanceOf(\Phpdftk\Document\Catalog::class, $catalog);
    }

    public function testPdfWriterGetPageTree(): void
    {
        $writer = new PdfWriter();
        $pt = $writer->getPageTree();
        self::assertInstanceOf(\Phpdftk\Document\PageTree::class, $pt);
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
        $rect = new \Phpdftk\Geometry\Rectangle(0, 0, 595, 842);
        $page = $writer->addPage($rect);
        $pdf = $writer->generate();
        self::assertStringContainsString('/MediaBox', $pdf);
    }

    public function testPdfWriterRegisterObject(): void
    {
        $writer = new PdfWriter();
        $page = $writer->addPage();
        $action = new \Phpdftk\Action\GoToAction(new PdfName('First'));
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
}
