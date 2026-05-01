<?php

declare(strict_types=1);

namespace ApprLabs\Pdf\Toolkit\Tests;

use ApprLabs\Pdf\Core\Font\StandardFont;
use ApprLabs\Pdf\Core\Font\Type1Font;
use ApprLabs\Pdf\Reader\PdfReader;
use ApprLabs\Pdf\Toolkit\PageSelector;
use ApprLabs\Pdf\Toolkit\PdfMerger;
use ApprLabs\Pdf\Writer\PdfWriter;
use ApprLabs\Tests\Support\QpdfValidationTrait;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

#[Group("qpdf")]
class PdfMergerTest extends TestCase
{
    use QpdfValidationTrait;
    private function generatePdf(int $pages, string $label = 'Doc'): string
    {
        $writer = new PdfWriter(compressStreams: false);
        $font = $writer->addFont(new Type1Font(StandardFont::Helvetica));

        for ($i = 1; $i <= $pages; $i++) {
            $page = $writer->addPage(612, 792);
            $cs = $writer->addContentStream($page);
            $cs->beginText()
                ->setFont($font->getResourceName(), 12)
                ->moveTextPosition(72, 720)
                ->showText("$label Page $i")
                ->endText();
        }

        return $writer->generate();
    }

    public function testMergeTwoPdfs(): void
    {
        $pdf1 = $this->generatePdf(2, 'First');
        $pdf2 = $this->generatePdf(3, 'Second');

        $result = PdfMerger::create()
            ->addString($pdf1)
            ->addString($pdf2)
            ->toBytes();

        $this->assertStringStartsWith('%PDF', $result);
        $this->assertQpdfValidBytes($result);
        $reader = PdfReader::fromString($result);
        $this->assertSame(5, $reader->getPageCount());

        $text1 = $reader->extractText(0);
        $this->assertStringContainsString('First', $text1);

        $text3 = $reader->extractText(2);
        $this->assertStringContainsString('Second', $text3);
    }

    public function testMergeWithPageSelection(): void
    {
        $pdf1 = $this->generatePdf(3, 'A');

        $path = sys_get_temp_dir() . '/phpdftk_merge_src_' . uniqid() . '.pdf';
        file_put_contents($path, $pdf1);

        try {
            $result = PdfMerger::create()
                ->addPages($path, PageSelector::pages(1, 3))
                ->toBytes();

            $this->assertQpdfValidBytes($result);
            $reader = PdfReader::fromString($result);
            $this->assertSame(2, $reader->getPageCount());
        } finally {
            @unlink($path);
        }
    }

    public function testSourceCount(): void
    {
        $pdf1 = $this->generatePdf(1);
        $pdf2 = $this->generatePdf(1);

        $merger = PdfMerger::create()
            ->addString($pdf1)
            ->addString($pdf2);

        $this->assertSame(2, $merger->getSourceCount());
    }

    public function testTotalPageCount(): void
    {
        $pdf1 = $this->generatePdf(2);
        $pdf2 = $this->generatePdf(3);

        $merger = PdfMerger::create()
            ->addString($pdf1)
            ->addString($pdf2);

        $this->assertSame(5, $merger->getTotalPageCount());
    }

    public function testNoSourcesThrows(): void
    {
        $this->expectException(\RuntimeException::class);
        PdfMerger::create()->toBytes();
    }

    public function testSaveToFile(): void
    {
        $pdf1 = $this->generatePdf(1);
        $pdf2 = $this->generatePdf(1);
        $path = sys_get_temp_dir() . '/phpdftk_merge_test_' . uniqid() . '.pdf';

        try {
            PdfMerger::create()
                ->addString($pdf1)
                ->addString($pdf2)
                ->save($path);

            $this->assertFileExists($path);
            $this->assertStringStartsWith('%PDF', file_get_contents($path));
            $this->assertQpdfValid($path);
        } finally {
            @unlink($path);
        }
    }
}
