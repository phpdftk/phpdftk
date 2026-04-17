<?php

declare(strict_types=1);

namespace ApprLabs\Pdf\Core\Tests\File;

use ApprLabs\Pdf\Core\Document\Catalog;
use ApprLabs\Pdf\Core\Document\Info;
use ApprLabs\Pdf\Core\File\PdfFileWriter;
use ApprLabs\Pdf\Core\Font\StandardFont;
use ApprLabs\Pdf\Core\Font\Type1Font;
use ApprLabs\Pdf\Core\PdfReference;
use ApprLabs\Pdf\Core\PdfString;
use PHPUnit\Framework\TestCase;

class PdfFileWriterTest extends TestCase
{
    public function testGenerateRequiresCatalog(): void
    {
        $writer = new PdfFileWriter();
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('setCatalog');
        $writer->generate();
    }

    public function testEmptyDocumentEmitsHeaderAndEof(): void
    {
        $writer = new PdfFileWriter();
        $writer->setCatalog(new Catalog());
        $pdf = $writer->generate();
        self::assertStringStartsWith('%PDF-1.7', $pdf);
        // Binary comment line: % + four >127 bytes on the second line.
        self::assertStringContainsString("%\xE2\xE3\xCF\xD3", $pdf);
        self::assertStringContainsString('xref', $pdf);
        self::assertStringContainsString('trailer', $pdf);
        self::assertStringContainsString('startxref', $pdf);
        self::assertStringEndsWith('%%EOF', $pdf);
    }

    public function testSetCatalogReturnsReference(): void
    {
        $writer = new PdfFileWriter();
        $cat = new Catalog();
        $ref = $writer->setCatalog($cat);
        self::assertInstanceOf(PdfReference::class, $ref);
        self::assertSame($cat->objectNumber, 1);
    }

    public function testRegisterReturnsReferenceWithObjectNumber(): void
    {
        $writer = new PdfFileWriter();
        $writer->setCatalog(new Catalog());
        $font = new Type1Font(StandardFont::Helvetica);
        $ref = $writer->register($font);
        self::assertInstanceOf(PdfReference::class, $ref);
        self::assertSame(2, $font->objectNumber);
    }

    public function testSetInfoAddsToTrailer(): void
    {
        $writer = new PdfFileWriter();
        $writer->setCatalog(new Catalog());
        $info = new Info();
        $info->title = new PdfString('T');
        $writer->setInfo($info);
        $pdf = $writer->generate();
        self::assertStringContainsString('/Info', $pdf);
        self::assertStringContainsString('/Title', $pdf);
    }

    public function testSaveWritesFile(): void
    {
        $writer = new PdfFileWriter();
        $writer->setCatalog(new Catalog());
        $path = sys_get_temp_dir() . '/phpdftk_filewriter_test_' . uniqid() . '.pdf';
        try {
            $writer->save($path);
            self::assertFileExists($path);
            self::assertStringStartsWith('%PDF-', (string) file_get_contents($path));
        } finally {
            @unlink($path);
        }
    }

    public function testGetRegistryIsLive(): void
    {
        $writer = new PdfFileWriter();
        $cat = new Catalog();
        $writer->setCatalog($cat);
        self::assertCount(1, $writer->getRegistry()->getAll());
        $writer->register(new Type1Font(StandardFont::Courier));
        self::assertCount(2, $writer->getRegistry()->getAll());
    }
}
