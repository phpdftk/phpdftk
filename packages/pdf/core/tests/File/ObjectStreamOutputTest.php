<?php

declare(strict_types=1);

namespace Phpdftk\Pdf\Core\Tests\File;

use Phpdftk\Pdf\Core\Content\ContentStream;
use Phpdftk\Pdf\Core\Content\Resources;
use Phpdftk\Pdf\Core\Document\Catalog;
use Phpdftk\Pdf\Core\Document\Page;
use Phpdftk\Pdf\Core\Document\PageTree;
use Phpdftk\Pdf\Core\File\PdfFileWriter;
use Phpdftk\Pdf\Core\PdfArray;
use Phpdftk\Pdf\Core\PdfNumber;
use Phpdftk\Pdf\Core\PdfReference;
use Phpdftk\Pdf\Reader\PdfReader;
use PHPUnit\Framework\TestCase;

class ObjectStreamOutputTest extends TestCase
{
    public function testObjectStreamProducesSmallerOutput(): void
    {
        $pdfNormal = $this->generatePdf(useObjectStreams: false);
        $pdfPacked = $this->generatePdf(useObjectStreams: true);

        // Both must be valid PDFs
        $this->assertStringStartsWith('%PDF-', $pdfNormal);
        $this->assertStringStartsWith('%PDF-', $pdfPacked);

        // Packed version should be smaller (objects compressed into stream)
        $this->assertLessThan(
            strlen($pdfNormal),
            strlen($pdfPacked),
            'Object stream packed PDF should be smaller than normal'
        );
    }

    public function testObjectStreamPdfContainsObjStm(): void
    {
        $pdf = $this->generatePdf(useObjectStreams: true);

        $this->assertStringContainsString('/Type /ObjStm', $pdf);
        $this->assertStringContainsString('/Type /XRef', $pdf);
    }

    public function testObjectStreamPdfReadableByReader(): void
    {
        $pdf = $this->generatePdf(useObjectStreams: true);

        $reader = PdfReader::fromString($pdf);
        $this->assertSame(3, $reader->getPageCount());
        $this->assertSame('1.7', $reader->getVersion());
    }

    public function testObjectStreamWithMultiplePages(): void
    {
        $pdf = $this->generatePdf(useObjectStreams: true, pages: 10);

        $reader = PdfReader::fromString($pdf);
        $this->assertSame(10, $reader->getPageCount());
    }

    public function testObjectStreamImpliesXRefStream(): void
    {
        $pdf = $this->generatePdf(useObjectStreams: true);

        // Should NOT have classic xref table
        $this->assertStringNotContainsString("\nxref\n", $pdf);
        // Should have xref stream
        $this->assertStringContainsString('/Type /XRef', $pdf);
    }

    private function generatePdf(bool $useObjectStreams, int $pages = 3): string
    {
        $writer = new PdfFileWriter(
            compressStreams: true,
            useObjectStreams: $useObjectStreams,
        );

        $catalog = new Catalog();
        $writer->setCatalog($catalog);

        $pageTree = new PageTree();
        $writer->register($pageTree);
        $catalog->pages = new PdfReference($pageTree->objectNumber);

        $kids = [];
        for ($i = 1; $i <= $pages; $i++) {
            $page = new Page();
            $writer->register($page);
            $page->parent = new PdfReference($pageTree->objectNumber);
            $page->mediaBox = new PdfArray([
                new PdfNumber(0), new PdfNumber(0),
                new PdfNumber(612), new PdfNumber(792),
            ]);
            $page->resources = new Resources();

            $cs = new ContentStream();
            $writer->register($cs);
            $cs->beginText()
                ->setFont('F1', 12)
                ->moveTextPosition(72, 720)
                ->showText("Page $i content with enough text to make compression worthwhile")
                ->endText();
            $page->contents = [new PdfReference($cs->objectNumber)];
            $kids[] = new PdfReference($page->objectNumber);
        }

        $pageTree->kids = $kids;
        $pageTree->count = $pages;

        return $writer->generate();
    }
}
