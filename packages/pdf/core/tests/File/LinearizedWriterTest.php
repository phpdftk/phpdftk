<?php

declare(strict_types=1);

namespace ApprLabs\Pdf\Core\Tests\File;

use ApprLabs\Pdf\Core\File\BitWriter;
use ApprLabs\Pdf\Core\File\PdfFileWriter;
use ApprLabs\Pdf\Core\Document\Catalog;
use ApprLabs\Pdf\Core\Document\Page;
use ApprLabs\Pdf\Core\Document\PageTree;
use ApprLabs\Pdf\Core\Content\ContentStream;
use ApprLabs\Pdf\Core\Font\StandardFont;
use ApprLabs\Pdf\Core\Font\Type1Font;
use ApprLabs\Pdf\Reader\PdfReader;
use ApprLabs\Pdf\Writer\PdfWriter;
use PHPUnit\Framework\TestCase;

class LinearizedWriterTest extends TestCase
{
    // --- BitWriter unit tests ---

    public function testBitWriterSingleByte(): void
    {
        $bw = new BitWriter();
        $bw->writeBits(0xFF, 8);
        $this->assertSame("\xFF", $bw->getData());
    }

    public function testBitWriterPartialByte(): void
    {
        $bw = new BitWriter();
        $bw->writeBits(0b101, 3);
        $bw->alignToByte();
        // 101 + 00000 padding = 10100000 = 0xA0
        $this->assertSame("\xA0", $bw->getData());
    }

    public function testBitWriterMultipleFields(): void
    {
        $bw = new BitWriter();
        $bw->writeBits(0b1111, 4);
        $bw->writeBits(0b0000, 4);
        $this->assertSame("\xF0", $bw->getData());
    }

    public function testBitWriterUint32(): void
    {
        $bw = new BitWriter();
        $bw->writeUint32(0x01020304);
        $this->assertSame("\x01\x02\x03\x04", $bw->getData());
    }

    public function testBitWriterGetBitPosition(): void
    {
        $bw = new BitWriter();
        $this->assertSame(0, $bw->getBitPosition());
        $bw->writeBits(1, 3);
        $this->assertSame(3, $bw->getBitPosition());
        $bw->writeBits(0, 5);
        $this->assertSame(8, $bw->getBitPosition());
    }

    public function testBitWriterRoundTripsWithBitReader(): void
    {
        $bw = new BitWriter();
        $bw->writeUint32(42);
        $bw->writeUint32(1000);
        $bw->writeBits(7, 4);
        $bw->alignToByte();

        $data = $bw->getData();
        $br = new \ApprLabs\Pdf\Reader\Parser\BitReader($data);
        $this->assertSame(42, $br->readBits(32));
        $this->assertSame(1000, $br->readBits(32));
        $this->assertSame(7, $br->readBits(4));
    }

    // --- PdfFileWriter linearized output tests ---

    public function testGenerateLinearizedProducesValidPdf(): void
    {
        $writer = new PdfFileWriter();

        $catalog = new Catalog();
        $writer->setCatalog($catalog);

        $pageTree = new PageTree();
        $writer->register($pageTree);
        $catalog->pages = new \ApprLabs\Pdf\Core\PdfReference($pageTree->objectNumber);

        $page = new Page();
        $writer->register($page);
        $page->parent = new \ApprLabs\Pdf\Core\PdfReference($pageTree->objectNumber);
        $page->mediaBox = new \ApprLabs\Pdf\Core\PdfArray([
            new \ApprLabs\Pdf\Core\PdfNumber(0),
            new \ApprLabs\Pdf\Core\PdfNumber(0),
            new \ApprLabs\Pdf\Core\PdfNumber(612),
            new \ApprLabs\Pdf\Core\PdfNumber(792),
        ]);

        $pageTree->kids = [new \ApprLabs\Pdf\Core\PdfReference($page->objectNumber)];
        $pageTree->count = 1;

        $cs = new ContentStream();
        $writer->register($cs);
        $cs->beginText()
            ->setFont('F1', 12)
            ->moveTextPosition(72, 720)
            ->showText('Hello')
            ->endText();

        $page->contents = [new \ApprLabs\Pdf\Core\PdfReference($cs->objectNumber)];

        $pdf = $writer->generateLinearized();

        $this->assertStringStartsWith('%PDF-', $pdf);
        $this->assertStringContainsString('/Linearized', $pdf);
        $this->assertStringContainsString('%%EOF', $pdf);
    }

    public function testLinearizedPdfHasCorrectStructure(): void
    {
        $writer = new PdfFileWriter();

        $catalog = new Catalog();
        $writer->setCatalog($catalog);

        $pageTree = new PageTree();
        $writer->register($pageTree);
        $catalog->pages = new \ApprLabs\Pdf\Core\PdfReference($pageTree->objectNumber);

        $page = new Page();
        $writer->register($page);
        $page->parent = new \ApprLabs\Pdf\Core\PdfReference($pageTree->objectNumber);
        $page->mediaBox = new \ApprLabs\Pdf\Core\PdfArray([
            new \ApprLabs\Pdf\Core\PdfNumber(0),
            new \ApprLabs\Pdf\Core\PdfNumber(0),
            new \ApprLabs\Pdf\Core\PdfNumber(612),
            new \ApprLabs\Pdf\Core\PdfNumber(792),
        ]);

        $pageTree->kids = [new \ApprLabs\Pdf\Core\PdfReference($page->objectNumber)];
        $pageTree->count = 1;

        $pdf = $writer->generateLinearized();

        // /Linearized dict must appear early in the file
        $linPos = strpos($pdf, '/Linearized');
        $this->assertNotFalse($linPos);
        $this->assertLessThan(200, $linPos, '/Linearized should appear near the start');

        // Must have /L (file length) matching actual length
        $this->assertStringContainsString('/L ', $pdf);

        // Must have /H (hint stream offsets)
        $this->assertStringContainsString('/H [', $pdf);

        // Must have /N (page count)
        $this->assertStringContainsString('/N 1', $pdf);

        // Must have two %%EOF markers (first-page section + main section)
        $eofCount = substr_count($pdf, '%%EOF');
        $this->assertSame(2, $eofCount, 'Linearized PDF must have exactly two %%EOF markers');

        // Must have two startxref markers (one per xref section)
        $startxrefCount = substr_count($pdf, "startxref\n");
        $this->assertSame(2, $startxrefCount, 'Linearized PDF must have exactly two startxref markers');
    }

    public function testLinearizedPdfFileLengthIsCorrect(): void
    {
        $writer = new PdfFileWriter();

        $catalog = new Catalog();
        $writer->setCatalog($catalog);

        $pageTree = new PageTree();
        $writer->register($pageTree);
        $catalog->pages = new \ApprLabs\Pdf\Core\PdfReference($pageTree->objectNumber);

        $page = new Page();
        $writer->register($page);
        $page->parent = new \ApprLabs\Pdf\Core\PdfReference($pageTree->objectNumber);
        $page->mediaBox = new \ApprLabs\Pdf\Core\PdfArray([
            new \ApprLabs\Pdf\Core\PdfNumber(0),
            new \ApprLabs\Pdf\Core\PdfNumber(0),
            new \ApprLabs\Pdf\Core\PdfNumber(612),
            new \ApprLabs\Pdf\Core\PdfNumber(792),
        ]);

        $pageTree->kids = [new \ApprLabs\Pdf\Core\PdfReference($page->objectNumber)];
        $pageTree->count = 1;

        $pdf = $writer->generateLinearized();

        // Extract the /L value and verify it matches actual file length
        preg_match('/\/L\s+(\d+)/', $pdf, $m);
        $this->assertNotEmpty($m);
        $declaredLength = (int) $m[1];
        $this->assertSame(strlen($pdf), $declaredLength, '/L must match actual file length');
    }

    // --- PdfWriter facade linearized tests ---

    public function testPdfWriterLinearizedOutput(): void
    {
        $writer = new PdfWriter();
        $writer->setLinearized(true);

        $page = $writer->addPage(612, 792);
        $font = $writer->addFont(new Type1Font(StandardFont::Helvetica));
        $cs = $writer->addContentStream($page);
        $cs->beginText()
            ->setFont($font->getResourceName(), 12)
            ->moveTextPosition(72, 720)
            ->showText('Hello Linearized World')
            ->endText();

        $pdf = $writer->toBytes();

        $this->assertStringStartsWith('%PDF-', $pdf);
        $this->assertStringContainsString('/Linearized', $pdf);
    }

    public function testPdfWriterLinearizedRoundTrip(): void
    {
        $writer = new PdfWriter();
        $writer->setLinearized(true);

        $page = $writer->addPage(612, 792);
        $font = $writer->addFont(new Type1Font(StandardFont::Helvetica));
        $cs = $writer->addContentStream($page);
        $cs->beginText()
            ->setFont($font->getResourceName(), 12)
            ->moveTextPosition(72, 720)
            ->showText('Linearized round-trip test')
            ->endText();

        $pdf = $writer->toBytes();

        // Read it back
        $reader = PdfReader::fromString($pdf, '', false);
        $this->assertSame(1, $reader->getPageCount());
        $this->assertTrue($reader->isLinearized());
    }

    public function testPdfWriterLinearizedSavesToFile(): void
    {
        $writer = new PdfWriter();
        $writer->setLinearized(true);

        $page = $writer->addPage(612, 792);
        $font = $writer->addFont(new Type1Font(StandardFont::Helvetica));
        $cs = $writer->addContentStream($page);
        $cs->beginText()
            ->setFont($font->getResourceName(), 12)
            ->moveTextPosition(72, 720)
            ->showText('File output test')
            ->endText();

        $outPath = __DIR__ . '/../../tests/output/linearized_test.pdf';
        $writer->save($outPath);

        $this->assertFileExists($outPath);
        $content = file_get_contents($outPath);
        $this->assertStringStartsWith('%PDF-', $content);
        $this->assertStringContainsString('/Linearized', $content);
    }

    public function testLinearizedMultiPagePdf(): void
    {
        $writer = new PdfWriter();
        $writer->setLinearized(true);

        // Create 3 pages
        for ($i = 1; $i <= 3; $i++) {
            $page = $writer->addPage(612, 792);
            $font = $writer->addFont(new Type1Font(StandardFont::Helvetica), $page);
            $cs = $writer->addContentStream($page);
            $cs->beginText()
                ->setFont($font->getResourceName(), 12)
                ->moveTextPosition(72, 720)
                ->showText("Page $i")
                ->endText();
        }

        $pdf = $writer->toBytes();

        $this->assertStringContainsString('/Linearized', $pdf);
        $this->assertStringContainsString('/N 3', $pdf); // 3 pages

        // Round-trip
        $reader = PdfReader::fromString($pdf, '', false);
        $this->assertSame(3, $reader->getPageCount());
        $this->assertTrue($reader->isLinearized());
    }
}
