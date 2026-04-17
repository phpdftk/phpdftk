<?php

declare(strict_types=1);

namespace ApprLabs\Pdf\Core\Tests\File;

use ApprLabs\Pdf\Core\Content\ContentStream;
use ApprLabs\Pdf\Core\Content\Resources;
use ApprLabs\Pdf\Core\Document\Catalog;
use ApprLabs\Pdf\Core\Document\Page;
use ApprLabs\Pdf\Core\Document\PageTree;
use ApprLabs\Pdf\Core\File\PdfFileWriter;
use ApprLabs\Pdf\Core\PdfArray;
use ApprLabs\Pdf\Core\PdfNumber;
use ApprLabs\Pdf\Core\PdfReference;
use ApprLabs\Pdf\Reader\PdfReader;
use PHPUnit\Framework\TestCase;

class XRefStreamOutputTest extends TestCase
{
    public function testXRefStreamOutputIsValidPdf(): void
    {
        $pdf = $this->generatePdf(useXRefStream: true);

        $this->assertStringStartsWith('%PDF-', $pdf);
        $this->assertStringEndsWith('%%EOF', $pdf);
        $this->assertStringContainsString('/Type /XRef', $pdf);
        $this->assertStringNotContainsString("xref\n0 ", $pdf);
    }

    public function testClassicXRefOutputIsDefault(): void
    {
        $pdf = $this->generatePdf(useXRefStream: false);

        $this->assertStringContainsString("xref\n", $pdf);
        $this->assertStringContainsString('trailer', $pdf);
        $this->assertStringNotContainsString('/Type /XRef', $pdf);
    }

    public function testXRefStreamRoundTrip(): void
    {
        $pdf = $this->generatePdf(useXRefStream: true);

        // PdfReader should be able to parse xref streams
        $reader = PdfReader::fromString($pdf);
        $this->assertSame(1, $reader->getPageCount());
        $this->assertSame('1.7', $reader->getVersion());
    }

    public function testXRefStreamWithMultiplePages(): void
    {
        $writer = new PdfFileWriter(compressStreams: false, useXRefStream: true);

        $catalog = new Catalog();
        $writer->setCatalog($catalog);
        $pageTree = new PageTree();
        $writer->register($pageTree);
        $catalog->pages = new PdfReference($pageTree->objectNumber);

        $kids = [];
        for ($i = 0; $i < 5; $i++) {
            $page = new Page();
            $writer->register($page);
            $page->parent = new PdfReference($pageTree->objectNumber);
            $page->mediaBox = new PdfArray([
                new PdfNumber(0), new PdfNumber(0),
                new PdfNumber(612), new PdfNumber(792),
            ]);
            $page->resources = new Resources();
            $kids[] = new PdfReference($page->objectNumber);
        }
        $pageTree->kids = $kids;
        $pageTree->count = 5;

        $pdf = $writer->generate();

        $reader = PdfReader::fromString($pdf);
        $this->assertSame(5, $reader->getPageCount());
    }

    public function testXRefStreamWithCompression(): void
    {
        $pdf = $this->generatePdf(useXRefStream: true, compress: true);

        $reader = PdfReader::fromString($pdf);
        $this->assertSame(1, $reader->getPageCount());
    }

    public function testXRefStreamIsSmallerThanClassic(): void
    {
        // With many objects, xref stream should be smaller than classic xref
        $writer1 = new PdfFileWriter(compressStreams: false, useXRefStream: false);
        $writer2 = new PdfFileWriter(compressStreams: false, useXRefStream: true);

        foreach ([$writer1, $writer2] as $w) {
            $catalog = new Catalog();
            $w->setCatalog($catalog);
            $pageTree = new PageTree();
            $w->register($pageTree);
            $catalog->pages = new PdfReference($pageTree->objectNumber);

            $kids = [];
            for ($i = 0; $i < 20; $i++) {
                $page = new Page();
                $w->register($page);
                $page->parent = new PdfReference($pageTree->objectNumber);
                $page->mediaBox = new PdfArray([
                    new PdfNumber(0), new PdfNumber(0),
                    new PdfNumber(612), new PdfNumber(792),
                ]);
                $page->resources = new Resources();
                $kids[] = new PdfReference($page->objectNumber);
            }
            $pageTree->kids = $kids;
            $pageTree->count = 20;
        }

        $classic = $writer1->generate();
        $stream = $writer2->generate();

        // XRef stream version should be smaller (binary entries vs text entries)
        $this->assertLessThan(strlen($classic), strlen($stream));
    }

    private function generatePdf(bool $useXRefStream, bool $compress = false): string
    {
        $writer = new PdfFileWriter(compressStreams: $compress, useXRefStream: $useXRefStream);

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
            ->showText('Hello XRef Stream')
            ->endText();
        $page->contents = [new PdfReference($cs->objectNumber)];

        return $writer->generate();
    }
}
