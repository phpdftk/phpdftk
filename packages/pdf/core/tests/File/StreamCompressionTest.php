<?php

declare(strict_types=1);

namespace Phpdftk\Pdf\Core\Tests\File;

use Phpdftk\Pdf\Core\Document\Catalog;
use Phpdftk\Pdf\Core\Document\Page;
use Phpdftk\Pdf\Core\Document\PageTree;
use Phpdftk\Pdf\Core\Content\ContentStream;
use Phpdftk\Pdf\Core\Content\Resources;
use Phpdftk\Pdf\Core\File\PdfFileWriter;
use Phpdftk\Pdf\Core\Font\StandardFont;
use Phpdftk\Pdf\Core\Font\Type1Font;
use Phpdftk\Pdf\Core\PdfArray;
use Phpdftk\Pdf\Core\PdfNumber;
use Phpdftk\Pdf\Core\PdfReference;
use Phpdftk\Pdf\Reader\PdfReader;
use PHPUnit\Framework\TestCase;

class StreamCompressionTest extends TestCase
{
    public function testCompressionEnabledByDefault(): void
    {
        $pdf = $this->generatePdf(compressStreams: true);
        $this->assertStringContainsString('/Filter /FlateDecode', $pdf);
    }

    public function testCompressionCanBeDisabled(): void
    {
        $pdf = $this->generatePdf(compressStreams: false);
        $this->assertStringNotContainsString('/Filter /FlateDecode', $pdf);
        // Uncompressed content should be visible
        $this->assertStringContainsString('BT', $pdf);
        $this->assertStringContainsString('Hello', $pdf);
    }

    public function testCompressedPdfIsSmallerWithLargerContent(): void
    {
        $compressed = $this->generatePdf(compressStreams: true, lines: 50);
        $uncompressed = $this->generatePdf(compressStreams: false, lines: 50);
        $this->assertLessThan(strlen($uncompressed), strlen($compressed));
    }

    public function testCompressedPdfIsReadable(): void
    {
        $pdf = $this->generatePdf(compressStreams: true);
        $reader = PdfReader::fromString($pdf);
        $this->assertSame(1, $reader->getPageCount());
        $this->assertSame('1.7', $reader->getVersion());
    }

    public function testCompressedPdfRoundTrips(): void
    {
        $pdf = $this->generatePdf(compressStreams: true);
        $reader = PdfReader::fromString($pdf);
        $pages = $reader->getPages();
        $this->assertCount(1, $pages);
        $this->assertTrue($pages[0]->has('Contents'));
    }

    private function generatePdf(bool $compressStreams, int $lines = 1): string
    {
        $writer = new PdfFileWriter($compressStreams);

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
            ->moveTextPosition(72, 720);
        for ($i = 0; $i < $lines; $i++) {
            $cs->showText("Hello World line $i — the quick brown fox jumps over the lazy dog.")
                ->moveTextPosition(0, -14);
        }
        $cs->endText();
        $page->contents = [new PdfReference($cs->objectNumber)];

        return $writer->generate();
    }
}
