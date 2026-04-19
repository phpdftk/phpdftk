<?php

declare(strict_types=1);

namespace ApprLabs\Pdf\Toolkit\Tests;

use ApprLabs\Pdf\Core\Font\StandardFont;
use ApprLabs\Pdf\Core\Font\Type1Font;
use ApprLabs\Pdf\Core\PdfArray;
use ApprLabs\Pdf\Core\PdfDictionary;
use ApprLabs\Pdf\Core\PdfName;
use ApprLabs\Pdf\Core\PdfNumber;
use ApprLabs\Pdf\Core\PdfReference;
use ApprLabs\Pdf\Reader\PdfReader;
use ApprLabs\Pdf\Toolkit\Label\LabelStyle;
use ApprLabs\Pdf\Toolkit\PageLabeler;
use ApprLabs\Pdf\Writer\PdfWriter;
use PHPUnit\Framework\TestCase;

class PageLabelerTest extends TestCase
{
    private function generateMultiPagePdf(int $pages = 10): string
    {
        $writer = new PdfWriter(compressStreams: false);
        $font = $writer->addFont(new Type1Font(StandardFont::Helvetica));

        for ($i = 1; $i <= $pages; $i++) {
            $page = $writer->addPage(612, 792);
            $cs = $writer->addContentStream($page);
            $cs->beginText()
                ->setFont($font->getResourceName(), 12)
                ->moveTextPosition(72, 720)
                ->showText("Page $i")
                ->endText();
        }

        return $writer->generate();
    }

    public function testSetLabelsWithArabic(): void
    {
        $pdf = $this->generateMultiPagePdf();

        $result = PageLabeler::openString($pdf)
            ->setLabels(1, LabelStyle::Arabic)
            ->toBytes();

        $this->assertStringStartsWith('%PDF', $result);

        // Verify the PageLabels number tree was added to the catalog
        $reader = PdfReader::fromString($result);
        $catalog = $reader->getCatalog();
        $labelsRef = $catalog->get('PageLabels');
        $this->assertInstanceOf(PdfReference::class, $labelsRef);

        $labelsDict = $reader->resolveReference($labelsRef);
        $this->assertInstanceOf(PdfDictionary::class, $labelsDict);
        $nums = $labelsDict->get('Nums');
        $this->assertInstanceOf(PdfArray::class, $nums);

        // First entry: page index 0 with style /D
        $this->assertInstanceOf(PdfNumber::class, $nums->items[0]);
        $this->assertSame('0', $nums->items[0]->toPdf());
    }

    public function testSetRomanNumerals(): void
    {
        $pdf = $this->generateMultiPagePdf();

        $result = PageLabeler::openString($pdf)
            ->setRomanNumerals(1, 4)
            ->toBytes();

        $reader = PdfReader::fromString($result);
        $catalog = $reader->getCatalog();
        $labelsRef = $catalog->get('PageLabels');
        $this->assertInstanceOf(PdfReference::class, $labelsRef);

        $labelsDict = $reader->resolveReference($labelsRef);
        $this->assertInstanceOf(PdfDictionary::class, $labelsDict);
        $nums = $labelsDict->get('Nums');
        $this->assertInstanceOf(PdfArray::class, $nums);

        // Should have two entries: page 0 (roman) and page 4 (arabic)
        $this->assertGreaterThanOrEqual(4, count($nums->items));

        // First entry: index 0
        $this->assertSame('0', $nums->items[0]->toPdf());
        // Second entry: label dict with /S /r
        $labelDict = $nums->items[1];
        $this->assertInstanceOf(PdfDictionary::class, $labelDict);
        $style = $labelDict->get('S');
        $this->assertInstanceOf(PdfName::class, $style);
        $this->assertSame('r', $style->value);
    }

    public function testSetRomanNumeralsUppercase(): void
    {
        $pdf = $this->generateMultiPagePdf();

        $result = PageLabeler::openString($pdf)
            ->setRomanNumerals(1, 3, uppercase: true)
            ->toBytes();

        $reader = PdfReader::fromString($result);
        $labelsDict = $reader->resolveReference($reader->getCatalog()->get('PageLabels'));
        $nums = $labelsDict->get('Nums');

        $labelDict = $nums->items[1];
        $this->assertInstanceOf(PdfDictionary::class, $labelDict);
        $style = $labelDict->get('S');
        $this->assertInstanceOf(PdfName::class, $style);
        $this->assertSame('R', $style->value);
    }

    public function testSetAlphabetic(): void
    {
        $pdf = $this->generateMultiPagePdf();

        $result = PageLabeler::openString($pdf)
            ->setAlphabetic(1, 5, uppercase: true)
            ->toBytes();

        $reader = PdfReader::fromString($result);
        $labelsDict = $reader->resolveReference($reader->getCatalog()->get('PageLabels'));
        $nums = $labelsDict->get('Nums');

        $labelDict = $nums->items[1];
        $this->assertInstanceOf(PdfDictionary::class, $labelDict);
        $style = $labelDict->get('S');
        $this->assertInstanceOf(PdfName::class, $style);
        $this->assertSame('A', $style->value);
    }

    public function testSetArabicWithStartNumber(): void
    {
        $pdf = $this->generateMultiPagePdf();

        $result = PageLabeler::openString($pdf)
            ->setArabic(1, null, startNumber: 42)
            ->toBytes();

        $reader = PdfReader::fromString($result);
        $labelsDict = $reader->resolveReference($reader->getCatalog()->get('PageLabels'));
        $nums = $labelsDict->get('Nums');

        $labelDict = $nums->items[1];
        $this->assertInstanceOf(PdfDictionary::class, $labelDict);

        // Should have /St 42
        $st = $labelDict->get('St');
        $this->assertInstanceOf(PdfNumber::class, $st);
        $this->assertSame('42', $st->toPdf());
    }

    public function testSetLabelsWithPrefix(): void
    {
        $pdf = $this->generateMultiPagePdf();

        $result = PageLabeler::openString($pdf)
            ->setLabels(1, LabelStyle::Arabic, prefix: 'A-')
            ->toBytes();

        $reader = PdfReader::fromString($result);
        $labelsDict = $reader->resolveReference($reader->getCatalog()->get('PageLabels'));
        $nums = $labelsDict->get('Nums');

        $labelDict = $nums->items[1];
        $this->assertInstanceOf(PdfDictionary::class, $labelDict);
        $prefix = $labelDict->get('P');
        $this->assertNotNull($prefix);
    }

    public function testRemoveLabels(): void
    {
        $pdf = $this->generateMultiPagePdf();

        // First add labels
        $withLabels = PageLabeler::openString($pdf)
            ->setLabels(1, LabelStyle::Arabic)
            ->toBytes();

        // Verify labels exist
        $reader1 = PdfReader::fromString($withLabels);
        $this->assertInstanceOf(PdfReference::class, $reader1->getCatalog()->get('PageLabels'));

        // Remove labels
        $result = PageLabeler::openString($withLabels)
            ->removeLabels()
            ->toBytes();

        $reader2 = PdfReader::fromString($result);
        $catalog = $reader2->getCatalog();
        // /PageLabels should not be present
        $this->assertNull($catalog->get('PageLabels'));
    }

    public function testMultipleRanges(): void
    {
        $pdf = $this->generateMultiPagePdf();

        $result = PageLabeler::openString($pdf)
            ->setLabels(1, LabelStyle::RomanLower)
            ->setLabels(5, LabelStyle::Arabic, startNumber: 1)
            ->toBytes();

        $reader = PdfReader::fromString($result);
        $labelsDict = $reader->resolveReference($reader->getCatalog()->get('PageLabels'));
        $nums = $labelsDict->get('Nums');

        // Should have 4 items: [0 <<label1>> 4 <<label2>>]
        $this->assertCount(4, $nums->items);
        $this->assertSame('0', $nums->items[0]->toPdf());
        $this->assertSame('4', $nums->items[2]->toPdf());
    }

    public function testGetPageCount(): void
    {
        $pdf = $this->generateMultiPagePdf(7);
        $labeler = PageLabeler::openString($pdf);
        $this->assertSame(7, $labeler->getPageCount());
    }

    public function testGetReader(): void
    {
        $pdf = $this->generateMultiPagePdf();
        $labeler = PageLabeler::openString($pdf);
        $this->assertInstanceOf(PdfReader::class, $labeler->getReader());
    }

    public function testSaveToFile(): void
    {
        $pdf = $this->generateMultiPagePdf();
        $outputPath = sys_get_temp_dir() . '/phpdftk_labeler_test_' . uniqid() . '.pdf';

        try {
            PageLabeler::openString($pdf)
                ->setLabels(1, LabelStyle::Arabic)
                ->save($outputPath);

            $this->assertFileExists($outputPath);
            $this->assertStringStartsWith('%PDF', file_get_contents($outputPath));
        } finally {
            if (file_exists($outputPath)) {
                unlink($outputPath);
            }
        }
    }

    public function testNoBytesChangedWhenNoOperations(): void
    {
        $pdf = $this->generateMultiPagePdf();
        $labeler = PageLabeler::openString($pdf);
        $this->assertSame($pdf, $labeler->toBytes());
    }

    public function testOpenFromFile(): void
    {
        $pdf = $this->generateMultiPagePdf();
        $tmpPath = sys_get_temp_dir() . '/phpdftk_labeler_open_' . uniqid() . '.pdf';
        file_put_contents($tmpPath, $pdf);

        try {
            $labeler = PageLabeler::open($tmpPath);
            $this->assertSame(10, $labeler->getPageCount());
        } finally {
            unlink($tmpPath);
        }
    }
}
