<?php

declare(strict_types=1);

namespace Phpdftk\Pdf\Toolkit\Tests;

use Phpdftk\Pdf\Core\Font\StandardFont;
use Phpdftk\Pdf\Core\Font\Type1Font;
use Phpdftk\Pdf\Core\PdfArray;
use Phpdftk\Pdf\Core\PdfNumber;
use Phpdftk\Pdf\Reader\PdfReader;
use Phpdftk\Pdf\Toolkit\PageSelector;
use Phpdftk\Pdf\Toolkit\PageTransformer;
use Phpdftk\Pdf\Writer\PdfWriter;
use Phpdftk\Tests\Support\QpdfValidationTrait;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

#[Group("qpdf")]
class PageTransformerTest extends TestCase
{
    use QpdfValidationTrait;
    /**
     * Generate a multi-page test PDF with known dimensions.
     */
    private function generateTestPdf(int $pageCount = 3): string
    {
        $writer = new PdfWriter(compressStreams: false);
        $font = $writer->addFont(new Type1Font(StandardFont::Helvetica));

        for ($i = 1; $i <= $pageCount; $i++) {
            $page = $writer->addPage(612, 792); // US Letter
            $cs = $writer->addContentStream($page);
            $cs->beginText()
                ->setFont($font->getResourceName(), 12)
                ->moveTextPosition(72, 720)
                ->showText("Page $i")
                ->endText();
        }

        return $writer->generate();
    }

    // -----------------------------------------------------------------------
    // Rotate
    // -----------------------------------------------------------------------

    public function testRotateAllPages(): void
    {
        $pdf = $this->generateTestPdf();
        $result = PageTransformer::openString($pdf)
            ->rotate(90)
            ->toBytes();

        $this->assertStringStartsWith('%PDF', $result);
        $this->assertQpdfValidBytes($result);

        $reader = PdfReader::fromString($result);
        $this->assertSame(3, $reader->getPageCount());

        for ($i = 0; $i < 3; $i++) {
            $page = $reader->getPage($i);
            $rotate = $page->get('Rotate');
            $this->assertInstanceOf(PdfNumber::class, $rotate, "Page $i should have Rotate");
            $this->assertSame(90, (int) $rotate->toPdf(), "Page $i should be rotated 90 degrees");
        }
    }

    public function testRotate180(): void
    {
        $pdf = $this->generateTestPdf(1);
        $result = PageTransformer::openString($pdf)
            ->rotate(180)
            ->toBytes();

        $this->assertQpdfValidBytes($result);
        $reader = PdfReader::fromString($result);
        $page = $reader->getPage(0);
        $rotate = $page->get('Rotate');
        $this->assertInstanceOf(PdfNumber::class, $rotate);
        $this->assertSame(180, (int) $rotate->toPdf());
    }

    public function testRotate270(): void
    {
        $pdf = $this->generateTestPdf(1);
        $result = PageTransformer::openString($pdf)
            ->rotate(270)
            ->toBytes();

        $this->assertQpdfValidBytes($result);
        $reader = PdfReader::fromString($result);
        $page = $reader->getPage(0);
        $rotate = $page->get('Rotate');
        $this->assertInstanceOf(PdfNumber::class, $rotate);
        $this->assertSame(270, (int) $rotate->toPdf());
    }

    public function testRotateSpecificPages(): void
    {
        $pdf = $this->generateTestPdf(3);
        $result = PageTransformer::openString($pdf)
            ->rotate(90, PageSelector::pages(2))
            ->toBytes();

        $this->assertQpdfValidBytes($result);
        $reader = PdfReader::fromString($result);

        // Page 1 — no rotation
        $page1 = $reader->getPage(0);
        $rotate1 = $page1->get('Rotate');
        $this->assertTrue(
            $rotate1 === null || ($rotate1 instanceof PdfNumber && (int) $rotate1->toPdf() === 0),
            'Page 1 should not be rotated'
        );

        // Page 2 — rotated
        $page2 = $reader->getPage(1);
        $rotate2 = $page2->get('Rotate');
        $this->assertInstanceOf(PdfNumber::class, $rotate2);
        $this->assertSame(90, (int) $rotate2->toPdf());

        // Page 3 — no rotation
        $page3 = $reader->getPage(2);
        $rotate3 = $page3->get('Rotate');
        $this->assertTrue(
            $rotate3 === null || ($rotate3 instanceof PdfNumber && (int) $rotate3->toPdf() === 0),
            'Page 3 should not be rotated'
        );
    }

    public function testRotateCumulative(): void
    {
        $pdf = $this->generateTestPdf(1);
        $result = PageTransformer::openString($pdf)
            ->rotate(90)
            ->rotate(90)
            ->toBytes();

        $this->assertQpdfValidBytes($result);
        $reader = PdfReader::fromString($result);
        $page = $reader->getPage(0);
        $rotate = $page->get('Rotate');
        $this->assertInstanceOf(PdfNumber::class, $rotate);
        $this->assertSame(180, (int) $rotate->toPdf());
    }

    public function testRotateInvalidAngle(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        PageTransformer::openString($this->generateTestPdf(1))->rotate(45);
    }

    // -----------------------------------------------------------------------
    // SetCropBox
    // -----------------------------------------------------------------------

    public function testSetCropBox(): void
    {
        $pdf = $this->generateTestPdf(1);
        $result = PageTransformer::openString($pdf)
            ->setCropBox(10, 20, 300, 400)
            ->toBytes();

        $this->assertStringStartsWith('%PDF', $result);
        $this->assertQpdfValidBytes($result);

        $reader = PdfReader::fromString($result);
        $page = $reader->getPage(0);
        $cropBox = $page->get('CropBox');
        $this->assertInstanceOf(PdfArray::class, $cropBox);
        $this->assertBoxValues($cropBox, 10, 20, 310, 420);
    }

    public function testSetCropBoxSpecificPage(): void
    {
        $pdf = $this->generateTestPdf(3);
        $result = PageTransformer::openString($pdf)
            ->setCropBox(0, 0, 200, 300, PageSelector::pages(2))
            ->toBytes();

        $this->assertQpdfValidBytes($result);
        $reader = PdfReader::fromString($result);

        // Page 1 — no CropBox
        $page1 = $reader->getPage(0);
        $this->assertNull($page1->get('CropBox'), 'Page 1 should not have CropBox');

        // Page 2 — has CropBox
        $page2 = $reader->getPage(1);
        $cropBox = $page2->get('CropBox');
        $this->assertInstanceOf(PdfArray::class, $cropBox);
        $this->assertBoxValues($cropBox, 0, 0, 200, 300);
    }

    // -----------------------------------------------------------------------
    // SetMediaBox
    // -----------------------------------------------------------------------

    public function testSetMediaBox(): void
    {
        $pdf = $this->generateTestPdf(1);
        $result = PageTransformer::openString($pdf)
            ->setMediaBox(0, 0, 400, 600)
            ->toBytes();

        $this->assertQpdfValidBytes($result);
        $reader = PdfReader::fromString($result);
        $page = $reader->getPage(0);
        $mediaBox = $page->get('MediaBox');
        $this->assertInstanceOf(PdfArray::class, $mediaBox);
        $this->assertBoxValues($mediaBox, 0, 0, 400, 600);
    }

    // -----------------------------------------------------------------------
    // SetTrimBox
    // -----------------------------------------------------------------------

    public function testSetTrimBox(): void
    {
        $pdf = $this->generateTestPdf(1);
        $result = PageTransformer::openString($pdf)
            ->setTrimBox(10, 10, 590, 770)
            ->toBytes();

        $this->assertQpdfValidBytes($result);
        $reader = PdfReader::fromString($result);
        $page = $reader->getPage(0);
        $trimBox = $page->get('TrimBox');
        $this->assertInstanceOf(PdfArray::class, $trimBox);
        $this->assertBoxValues($trimBox, 10, 10, 600, 780);
    }

    // -----------------------------------------------------------------------
    // SetBleedBox
    // -----------------------------------------------------------------------

    public function testSetBleedBox(): void
    {
        $pdf = $this->generateTestPdf(1);
        $result = PageTransformer::openString($pdf)
            ->setBleedBox(5, 5, 600, 780)
            ->toBytes();

        $this->assertQpdfValidBytes($result);
        $reader = PdfReader::fromString($result);
        $page = $reader->getPage(0);
        $bleedBox = $page->get('BleedBox');
        $this->assertInstanceOf(PdfArray::class, $bleedBox);
        $this->assertBoxValues($bleedBox, 5, 5, 605, 785);
    }

    // -----------------------------------------------------------------------
    // Scale
    // -----------------------------------------------------------------------

    public function testScale(): void
    {
        $pdf = $this->generateTestPdf(1);
        $result = PageTransformer::openString($pdf)
            ->scale(0.5)
            ->toBytes();

        $this->assertQpdfValidBytes($result);
        $reader = PdfReader::fromString($result);
        $page = $reader->getPage(0);
        $mediaBox = $page->get('MediaBox');
        $this->assertInstanceOf(PdfArray::class, $mediaBox);
        // Original: [0 0 612 792] -> scaled: [0 0 306 396]
        $this->assertBoxValues($mediaBox, 0, 0, 306, 396);
    }

    public function testScaleSpecificPages(): void
    {
        $pdf = $this->generateTestPdf(2);
        $result = PageTransformer::openString($pdf)
            ->scale(2.0, PageSelector::pages(1))
            ->toBytes();

        $this->assertQpdfValidBytes($result);
        $reader = PdfReader::fromString($result);

        // Page 1 — scaled
        $mediaBox1 = $reader->getPage(0)->get('MediaBox');
        $this->assertInstanceOf(PdfArray::class, $mediaBox1);
        $this->assertBoxValues($mediaBox1, 0, 0, 1224, 1584);

        // Page 2 — unchanged
        $mediaBox2 = $reader->getPage(1)->get('MediaBox');
        $this->assertInstanceOf(PdfArray::class, $mediaBox2);
        $this->assertBoxValues($mediaBox2, 0, 0, 612, 792);
    }

    public function testScaleInvalidFactor(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        PageTransformer::openString($this->generateTestPdf(1))->scale(0);
    }

    // -----------------------------------------------------------------------
    // ScaleTo
    // -----------------------------------------------------------------------

    public function testScaleTo(): void
    {
        $pdf = $this->generateTestPdf(1);
        // Scale letter (612x792) to fit in 306x396 (factor = 0.5)
        $result = PageTransformer::openString($pdf)
            ->scaleTo(306, 396)
            ->toBytes();

        $this->assertQpdfValidBytes($result);
        $reader = PdfReader::fromString($result);
        $page = $reader->getPage(0);
        $mediaBox = $page->get('MediaBox');
        $this->assertInstanceOf(PdfArray::class, $mediaBox);
        $this->assertBoxValues($mediaBox, 0, 0, 306, 396);
    }

    public function testScaleToNonUniform(): void
    {
        $pdf = $this->generateTestPdf(1);
        // Scale letter (612x792) to fit in 612x612 -> factor = 612/792 ~= 0.7727
        $result = PageTransformer::openString($pdf)
            ->scaleTo(612, 612)
            ->toBytes();

        $this->assertQpdfValidBytes($result);
        $reader = PdfReader::fromString($result);
        $page = $reader->getPage(0);
        $mediaBox = $page->get('MediaBox');
        $this->assertInstanceOf(PdfArray::class, $mediaBox);

        $w = $this->getBoxVal($mediaBox, 2);
        $h = $this->getBoxVal($mediaBox, 3);
        // Width and height should be scaled uniformly by min(612/612, 612/792) = 612/792
        $this->assertEqualsWithDelta(612 * 612 / 792, $w, 0.01);
        $this->assertEqualsWithDelta(612.0, $h, 0.01);
    }

    // -----------------------------------------------------------------------
    // Combined operations
    // -----------------------------------------------------------------------

    public function testMultipleOperations(): void
    {
        $pdf = $this->generateTestPdf(2);
        $result = PageTransformer::openString($pdf)
            ->rotate(90, PageSelector::pages(1))
            ->setCropBox(0, 0, 300, 400, PageSelector::pages(2))
            ->toBytes();

        $this->assertStringStartsWith('%PDF', $result);
        $this->assertQpdfValidBytes($result);

        $reader = PdfReader::fromString($result);
        $this->assertSame(2, $reader->getPageCount());

        // Page 1: rotated
        $page1 = $reader->getPage(0);
        $rotate = $page1->get('Rotate');
        $this->assertInstanceOf(PdfNumber::class, $rotate);
        $this->assertSame(90, (int) $rotate->toPdf());

        // Page 2: cropped
        $page2 = $reader->getPage(1);
        $cropBox = $page2->get('CropBox');
        $this->assertInstanceOf(PdfArray::class, $cropBox);
        $this->assertBoxValues($cropBox, 0, 0, 300, 400);
    }

    // -----------------------------------------------------------------------
    // No-op
    // -----------------------------------------------------------------------

    public function testNoBytesChangedWithoutOperations(): void
    {
        $pdf = $this->generateTestPdf(1);
        $result = PageTransformer::openString($pdf)->toBytes();
        $this->assertSame($pdf, $result);
    }

    // -----------------------------------------------------------------------
    // PageSelector integration
    // -----------------------------------------------------------------------

    public function testEvenPages(): void
    {
        $pdf = $this->generateTestPdf(4);
        $result = PageTransformer::openString($pdf)
            ->rotate(90, PageSelector::even())
            ->toBytes();

        $this->assertQpdfValidBytes($result);
        $reader = PdfReader::fromString($result);
        for ($i = 0; $i < 4; $i++) {
            $page = $reader->getPage($i);
            $rotate = $page->get('Rotate');
            $isEven = ($i + 1) % 2 === 0;
            if ($isEven) {
                $this->assertInstanceOf(PdfNumber::class, $rotate, "Page " . ($i + 1) . " should be rotated");
                $this->assertSame(90, (int) $rotate->toPdf());
            } else {
                $this->assertTrue(
                    $rotate === null || ($rotate instanceof PdfNumber && (int) $rotate->toPdf() === 0),
                    "Page " . ($i + 1) . " should not be rotated"
                );
            }
        }
    }

    public function testOddPages(): void
    {
        $pdf = $this->generateTestPdf(4);
        $result = PageTransformer::openString($pdf)
            ->rotate(270, PageSelector::odd())
            ->toBytes();

        $this->assertQpdfValidBytes($result);
        $reader = PdfReader::fromString($result);
        for ($i = 0; $i < 4; $i++) {
            $page = $reader->getPage($i);
            $rotate = $page->get('Rotate');
            $isOdd = ($i + 1) % 2 === 1;
            if ($isOdd) {
                $this->assertInstanceOf(PdfNumber::class, $rotate);
                $this->assertSame(270, (int) $rotate->toPdf());
            } else {
                $this->assertTrue(
                    $rotate === null || ($rotate instanceof PdfNumber && (int) $rotate->toPdf() === 0),
                    "Page " . ($i + 1) . " should not be rotated"
                );
            }
        }
    }

    public function testRange(): void
    {
        $pdf = $this->generateTestPdf(5);
        $result = PageTransformer::openString($pdf)
            ->rotate(90, PageSelector::range(2, 4))
            ->toBytes();

        $this->assertQpdfValidBytes($result);
        $reader = PdfReader::fromString($result);
        for ($i = 0; $i < 5; $i++) {
            $page = $reader->getPage($i);
            $rotate = $page->get('Rotate');
            $inRange = ($i + 1) >= 2 && ($i + 1) <= 4;
            if ($inRange) {
                $this->assertInstanceOf(PdfNumber::class, $rotate);
                $this->assertSame(90, (int) $rotate->toPdf());
            } else {
                $this->assertTrue(
                    $rotate === null || ($rotate instanceof PdfNumber && (int) $rotate->toPdf() === 0),
                    "Page " . ($i + 1) . " should not be rotated"
                );
            }
        }
    }

    // -----------------------------------------------------------------------
    // Escape hatches
    // -----------------------------------------------------------------------

    public function testGetReader(): void
    {
        $pdf = $this->generateTestPdf(1);
        $transformer = PageTransformer::openString($pdf);
        $this->assertInstanceOf(PdfReader::class, $transformer->getReader());
    }

    public function testGetPageCount(): void
    {
        $pdf = $this->generateTestPdf(3);
        $transformer = PageTransformer::openString($pdf);
        $this->assertSame(3, $transformer->getPageCount());
    }

    // -----------------------------------------------------------------------
    // File I/O
    // -----------------------------------------------------------------------

    public function testSaveToFile(): void
    {
        $pdf = $this->generateTestPdf(1);
        $outputPath = sys_get_temp_dir() . '/phpdftk_page_transformer_test_' . uniqid() . '.pdf';

        try {
            PageTransformer::openString($pdf)
                ->rotate(90)
                ->save($outputPath);

            $this->assertFileExists($outputPath);
            $this->assertStringStartsWith('%PDF', file_get_contents($outputPath));
            $this->assertQpdfValid($outputPath);

            $reader = PdfReader::fromString(file_get_contents($outputPath));
            $page = $reader->getPage(0);
            $rotate = $page->get('Rotate');
            $this->assertInstanceOf(PdfNumber::class, $rotate);
            $this->assertSame(90, (int) $rotate->toPdf());
        } finally {
            @unlink($outputPath);
        }
    }

    // -----------------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------------

    private function assertBoxValues(PdfArray $box, float $x1, float $y1, float $x2, float $y2): void
    {
        $this->assertCount(4, $box->items);
        $this->assertEqualsWithDelta($x1, $this->getBoxVal($box, 0), 0.01, "llx mismatch");
        $this->assertEqualsWithDelta($y1, $this->getBoxVal($box, 1), 0.01, "lly mismatch");
        $this->assertEqualsWithDelta($x2, $this->getBoxVal($box, 2), 0.01, "urx mismatch");
        $this->assertEqualsWithDelta($y2, $this->getBoxVal($box, 3), 0.01, "ury mismatch");
    }

    private function getBoxVal(PdfArray $box, int $index): float
    {
        $item = $box->items[$index];
        if ($item instanceof PdfNumber) {
            return (float) $item->value;
        }
        return (float) (string) $item;
    }
}
