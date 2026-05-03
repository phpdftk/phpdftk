<?php

declare(strict_types=1);

namespace Phpdftk\Pdf\Toolkit\Tests;

use Phpdftk\Pdf\Core\Font\StandardFont;
use Phpdftk\Pdf\Core\Font\Type1Font;
use Phpdftk\Pdf\Reader\PdfReader;
use Phpdftk\Pdf\Toolkit\PageSelector;
use Phpdftk\Pdf\Toolkit\PageSlicer;
use Phpdftk\Pdf\Writer\PdfWriter;
use Phpdftk\Tests\Support\QpdfValidationTrait;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

#[Group("qpdf")]
class PageSlicerTest extends TestCase
{
    use QpdfValidationTrait;
    private function generatePdf(int $pages = 5): string
    {
        $writer = new PdfWriter(compressStreams: false);
        $font = $writer->addFont(new Type1Font(StandardFont::Helvetica));

        for ($i = 1; $i <= $pages; $i++) {
            $page = $writer->addPage(612, 792);
            $cs = $writer->addContentStream($page);
            $cs->beginText()
                ->setFont($font->getResourceName(), 12)
                ->moveTextPosition(72, 720)
                ->showText("Page $i content")
                ->endText();
        }

        return $writer->generate();
    }

    public function testKeepPages(): void
    {
        $pdf = $this->generatePdf(5);
        $result = PageSlicer::openString($pdf)
            ->keepPages(1, 3, 5)
            ->toBytes();

        $this->assertStringStartsWith('%PDF', $result);
        $this->assertQpdfValidBytes($result);
        $reader = PdfReader::fromString($result);
        $this->assertSame(3, $reader->getPageCount());
    }

    public function testKeepRange(): void
    {
        $pdf = $this->generatePdf(5);
        $result = PageSlicer::openString($pdf)
            ->keepRange(2, 4)
            ->toBytes();

        $this->assertQpdfValidBytes($result);
        $reader = PdfReader::fromString($result);
        $this->assertSame(3, $reader->getPageCount());
    }

    public function testRemovePages(): void
    {
        $pdf = $this->generatePdf(5);
        $result = PageSlicer::openString($pdf)
            ->removePages(2, 4)
            ->toBytes();

        $this->assertQpdfValidBytes($result);
        $reader = PdfReader::fromString($result);
        $this->assertSame(3, $reader->getPageCount());
    }

    public function testReorder(): void
    {
        $pdf = $this->generatePdf(3);
        $result = PageSlicer::openString($pdf)
            ->reorder(3, 1, 2)
            ->toBytes();

        $this->assertQpdfValidBytes($result);
        $reader = PdfReader::fromString($result);
        $this->assertSame(3, $reader->getPageCount());
        // Page 3's content should now be first
        $text = $reader->extractText(0);
        $this->assertStringContainsString('Page 3', $text);
    }

    public function testReverse(): void
    {
        $pdf = $this->generatePdf(3);
        $result = PageSlicer::openString($pdf)
            ->reverse()
            ->toBytes();

        $this->assertQpdfValidBytes($result);
        $reader = PdfReader::fromString($result);
        $text = $reader->extractText(0);
        $this->assertStringContainsString('Page 3', $text);
    }

    public function testSplit(): void
    {
        $pdf = $this->generatePdf(4);
        [$first, $second] = PageSlicer::openString($pdf)->split(3);

        $this->assertQpdfValidBytes($first);
        $this->assertQpdfValidBytes($second);
        $reader1 = PdfReader::fromString($first);
        $reader2 = PdfReader::fromString($second);

        $this->assertSame(2, $reader1->getPageCount());
        $this->assertSame(2, $reader2->getPageCount());
    }

    public function testKeepWithPageSelector(): void
    {
        $pdf = $this->generatePdf(6);
        $result = PageSlicer::openString($pdf)
            ->keep(PageSelector::even())
            ->toBytes();

        $this->assertQpdfValidBytes($result);
        $reader = PdfReader::fromString($result);
        $this->assertSame(3, $reader->getPageCount());
    }

    public function testNoOpsKeepsAllPages(): void
    {
        $pdf = $this->generatePdf(3);
        $result = PageSlicer::openString($pdf)->toBytes();

        $this->assertQpdfValidBytes($result);
        $reader = PdfReader::fromString($result);
        $this->assertSame(3, $reader->getPageCount());
    }

    public function testPageCount(): void
    {
        $pdf = $this->generatePdf(5);
        $slicer = PageSlicer::openString($pdf);
        $this->assertSame(5, $slicer->getPageCount());
    }

    public function testEscapeHatch(): void
    {
        $pdf = $this->generatePdf();
        $slicer = PageSlicer::openString($pdf);
        $this->assertInstanceOf(PdfReader::class, $slicer->getReader());
    }
}
