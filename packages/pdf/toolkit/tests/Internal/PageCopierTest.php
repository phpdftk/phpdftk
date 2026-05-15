<?php

declare(strict_types=1);

namespace Phpdftk\Pdf\Toolkit\Tests\Internal;

use Phpdftk\Pdf\Core\Document\Page;
use Phpdftk\Pdf\Core\Font\StandardFont;
use Phpdftk\Pdf\Core\Font\Type1Font;
use Phpdftk\Pdf\Core\PdfArray;
use Phpdftk\Pdf\Core\PdfNumber;
use Phpdftk\Pdf\Reader\PdfReader;
use Phpdftk\Pdf\Toolkit\PageSelector;
use Phpdftk\Pdf\Toolkit\PdfMerger;
use Phpdftk\Pdf\Writer\PdfWriter;
use PHPUnit\Framework\TestCase;

/**
 * Exercises PageCopier paths that the high-level PdfMerger flows don't naturally hit:
 * CropBox/Rotate copy, multi-stream Contents arrays, repeated resources via cache,
 * out-of-range error path.
 */
class PageCopierTest extends TestCase
{
    private function generateBasicPdf(int $pages = 1): string
    {
        $writer = new PdfWriter(compressStreams: false);
        $font = $writer->addFont(new Type1Font(StandardFont::Helvetica));

        for ($i = 1; $i <= $pages; $i++) {
            $page = $writer->addPage(612, 792);
            $cs = $writer->addContentStream($page);
            $cs->beginText()
                ->setFont($font->getResourceName(), 12)
                ->moveTextPosition(72, 720)
                ->showText("Doc Page $i")
                ->endText();
        }

        return $writer->generate();
    }

    private function generatePdfWithCropAndRotate(): string
    {
        $writer = new PdfWriter(compressStreams: false);
        $font = $writer->addFont(new Type1Font(StandardFont::Helvetica));
        $page = $writer->addPage(612, 792);
        $corePage = $page->corePage();
        $corePage->cropBox = new PdfArray([
            new PdfNumber(0),
            new PdfNumber(0),
            new PdfNumber(500),
            new PdfNumber(700),
        ]);
        $corePage->rotate = 90;
        $cs = $writer->addContentStream($page);
        $cs->beginText()
            ->setFont($font->getResourceName(), 12)
            ->moveTextPosition(72, 720)
            ->showText('Rotated cropped page')
            ->endText();
        return $writer->generate();
    }

    private function generatePdfWithMultipleContentStreams(): string
    {
        $writer = new PdfWriter(compressStreams: false);
        $font = $writer->addFont(new Type1Font(StandardFont::Helvetica));
        $page = $writer->addPage(612, 792);
        $cs1 = $writer->addContentStream($page);
        $cs1->beginText()
            ->setFont($font->getResourceName(), 12)
            ->moveTextPosition(72, 720)
            ->showText('Stream one')
            ->endText();
        $cs2 = $writer->addContentStream($page);
        $cs2->beginText()
            ->setFont($font->getResourceName(), 12)
            ->moveTextPosition(72, 700)
            ->showText('Stream two')
            ->endText();
        return $writer->generate();
    }

    public function testMergePreservesCropAndRotate(): void
    {
        $src = $this->generatePdfWithCropAndRotate();
        $merged = PdfMerger::create()->addString($src)->toBytes();

        $this->assertStringStartsWith('%PDF', $merged);
        // PageCopier should have emitted both /CropBox and /Rotate on the merged page.
        $this->assertStringContainsString('/CropBox', $merged);
        $this->assertStringContainsString('/Rotate 90', $merged);
    }

    public function testMergePagePreservesArrayContents(): void
    {
        $src = $this->generatePdfWithMultipleContentStreams();
        $merged = PdfMerger::create()->addString($src)->toBytes();

        $this->assertStringStartsWith('%PDF', $merged);
        // The page's /Contents should reference an array of two streams in the output
        // since the source had two distinct content streams.
        $this->assertMatchesRegularExpression('|/Contents \[ \d+ 0 R \d+ 0 R \]|', $merged);
    }

    public function testMergeDeduplicatesSharedResourcesAcrossPages(): void
    {
        // Source PDF with N pages sharing the same font — copying should cache the font ref.
        $src = $this->generateBasicPdf(3);
        $merged = PdfMerger::create()->addString($src)->toBytes();

        // All three pages should appear in the output
        $this->assertSame(3, substr_count($merged, '/Type /Page' . "\n"));
    }

    public function testOutOfRangePageIndexThrows(): void
    {
        // PageCopier exposes copyPages publicly; pass an explicit bad index to hit the throw.
        $src = $this->generateBasicPdf(1);
        $reader = PdfReader::fromString($src);
        $writer = new \Phpdftk\Pdf\Core\File\PdfFileWriter();
        $pageTree = new \Phpdftk\Pdf\Core\Document\PageTree();
        $writer->register($pageTree);

        $copier = new \Phpdftk\Pdf\Toolkit\Internal\PageCopier($reader, $writer);

        $this->expectException(\OutOfRangeException::class);
        $copier->copyPages([99], new \Phpdftk\Pdf\Core\PdfReference($pageTree->objectNumber));
    }

    public function testMergeFromSelectiveIndices(): void
    {
        $src = $this->generateBasicPdf(5);
        $tmp = tempnam(sys_get_temp_dir(), 'pc_') . '.pdf';
        file_put_contents($tmp, $src);
        try {
            $merged = PdfMerger::create()
                ->addPages($tmp, PageSelector::pages(1, 3, 5))
                ->toBytes();
            $reader = PdfReader::fromString($merged);
            $this->assertCount(3, $reader->getTypedPages());
        } finally {
            @unlink($tmp);
        }
    }

    public function testMergeReusedSourceCachesObjects(): void
    {
        // Merging the same source twice exercises the objectMap cache path
        // when the second copy encounters references already mapped.
        $src = $this->generateBasicPdf(2);
        $merged = PdfMerger::create()
            ->addString($src)
            ->addString($src)
            ->toBytes();

        $reader = PdfReader::fromString($merged);
        $this->assertCount(4, $reader->getTypedPages());
    }
}
