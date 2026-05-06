<?php

declare(strict_types=1);

namespace Phpdftk\Pdf\Core\Tests\File;

use Phpdftk\Pdf\Core\Content\ContentStream;
use Phpdftk\Pdf\Core\Content\Resources;
use Phpdftk\Pdf\Core\Document\Catalog;
use Phpdftk\Pdf\Core\Document\Page;
use Phpdftk\Pdf\Core\Document\PageTree;
use Phpdftk\Pdf\Core\File\IncrementalWriter;
use Phpdftk\Pdf\Core\File\PdfFileWriter;
use Phpdftk\Pdf\Core\PdfArray;
use Phpdftk\Pdf\Core\PdfNumber;
use Phpdftk\Pdf\Core\PdfReference;
use Phpdftk\Pdf\Reader\PdfReader;
use PHPUnit\Framework\TestCase;

class IncrementalWriterTest extends TestCase
{
    private function generateBasePdf(): string
    {
        $writer = new PdfFileWriter(compressStreams: false);
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
            ->showText('Original content')
            ->endText();
        $page->contents = [new PdfReference($cs->objectNumber)];

        return $writer->generate();
    }

    public function testIncrementalUpdatePreservesOriginalBytes(): void
    {
        $original = $this->generateBasePdf();
        $reader = PdfReader::fromString($original);

        $incWriter = IncrementalWriter::fromReader($reader, $original, compressStreams: false);

        // Add a new content stream as a new object
        $newCs = new ContentStream();
        $incWriter->addNewObject($newCs);
        $newCs->beginText()
            ->setFont('F1', 12)
            ->moveTextPosition(72, 600)
            ->showText('Incremental addition')
            ->endText();

        $result = $incWriter->generate();

        // Result should start with the original bytes
        $this->assertStringStartsWith(substr($original, 0, 50), $result);
        // Result should be longer than original
        $this->assertGreaterThan(strlen($original), strlen($result));
        // Should contain the new text
        $this->assertStringContainsString('Incremental addition', $result);
        // Should still contain original text
        $this->assertStringContainsString('Original content', $result);
    }

    public function testIncrementalUpdateHasPrevInTrailer(): void
    {
        $original = $this->generateBasePdf();
        $reader = PdfReader::fromString($original);

        $incWriter = IncrementalWriter::fromReader($reader, $original, compressStreams: false);

        $newCs = new ContentStream();
        $incWriter->addNewObject($newCs);
        $newCs->beginText()->setFont('F1', 12)->moveTextPosition(72, 600)
            ->showText('Test')->endText();

        $result = $incWriter->generate();

        // Should have /Prev in the new trailer
        $this->assertStringContainsString('/Prev', $result);
    }

    public function testIncrementalUpdateReadableByReader(): void
    {
        $original = $this->generateBasePdf();
        $reader = PdfReader::fromString($original);

        $incWriter = IncrementalWriter::fromReader($reader, $original, compressStreams: false);

        $newCs = new ContentStream();
        $incWriter->addNewObject($newCs);
        $newCs->beginText()->setFont('F1', 12)->moveTextPosition(72, 600)
            ->showText('Readable test')->endText();

        $result = $incWriter->generate();

        // Reader should be able to parse the incremental update
        $newReader = PdfReader::fromString($result);
        $this->assertSame(1, $newReader->getPageCount());
        $this->assertSame('1.7', $newReader->getVersion());
    }

    public function testModifyExistingObject(): void
    {
        $original = $this->generateBasePdf();
        $reader = PdfReader::fromString($original);

        $incWriter = IncrementalWriter::fromReader($reader, $original, compressStreams: false);

        // Create a new content stream to replace the existing one
        // Object 4 was the original content stream
        $newCs = new ContentStream();
        $newCs->objectNumber = 4; // overwrite existing object 4
        $newCs->beginText()
            ->setFont('F1', 12)
            ->moveTextPosition(72, 720)
            ->showText('Modified content')
            ->endText();
        $incWriter->addModifiedObject($newCs);

        $result = $incWriter->generate();

        // Should contain the modified text
        $this->assertStringContainsString('Modified content', $result);
        // Original bytes still present in the file
        $this->assertStringContainsString('Original content', $result);

        // Reader should get the modified version (newer xref takes precedence)
        $newReader = PdfReader::fromString($result);
        $this->assertSame(1, $newReader->getPageCount());
    }

    public function testEmptyUpdateReturnsOriginal(): void
    {
        $original = $this->generateBasePdf();
        $reader = PdfReader::fromString($original);

        $incWriter = IncrementalWriter::fromReader($reader, $original);
        $result = $incWriter->generate();

        $this->assertSame($original, $result);
    }

    public function testIncrementalUpdateWithCompression(): void
    {
        $original = $this->generateBasePdf();
        $reader = PdfReader::fromString($original);

        $incWriter = IncrementalWriter::fromReader($reader, $original, compressStreams: true);

        $newCs = new ContentStream();
        $incWriter->addNewObject($newCs);
        $newCs->beginText()->setFont('F1', 12)->moveTextPosition(72, 600)
            ->showText('Compressed incremental')->endText();

        $result = $incWriter->generate();

        // Should be readable
        $newReader = PdfReader::fromString($result);
        $this->assertSame(1, $newReader->getPageCount());
    }

    public function testAddModifiedObjectRejectsZeroObjNum(): void
    {
        $original = $this->generateBasePdf();
        $reader = PdfReader::fromString($original);
        $incWriter = IncrementalWriter::fromReader($reader, $original);

        $obj = new ContentStream();
        // objectNumber defaults to 0

        $this->expectException(\InvalidArgumentException::class);
        $incWriter->addModifiedObject($obj);
    }

    public function testMultipleIncrementalUpdates(): void
    {
        // First update
        $original = $this->generateBasePdf();
        $reader1 = PdfReader::fromString($original);
        $inc1 = IncrementalWriter::fromReader($reader1, $original, compressStreams: false);

        $cs1 = new ContentStream();
        $inc1->addNewObject($cs1);
        $cs1->beginText()->setFont('F1', 10)->moveTextPosition(72, 600)
            ->showText('First update')->endText();
        $pdf1 = $inc1->generate();

        // Second update on top of first
        $reader2 = PdfReader::fromString($pdf1);
        $inc2 = IncrementalWriter::fromReader($reader2, $pdf1, compressStreams: false);

        $cs2 = new ContentStream();
        $inc2->addNewObject($cs2);
        $cs2->beginText()->setFont('F1', 10)->moveTextPosition(72, 500)
            ->showText('Second update')->endText();
        $pdf2 = $inc2->generate();

        // Final PDF should be readable and contain all content
        $finalReader = PdfReader::fromString($pdf2);
        $this->assertSame(1, $finalReader->getPageCount());

        // Both updates should be in the file
        $this->assertStringContainsString('First update', $pdf2);
        $this->assertStringContainsString('Second update', $pdf2);
        $this->assertStringContainsString('Original content', $pdf2);
    }

    public function testIncrementalSaveToFile(): void
    {
        $original = $this->generateBasePdf();
        $reader = PdfReader::fromString($original);
        $incWriter = IncrementalWriter::fromReader($reader, $original, compressStreams: false);

        $cs = new ContentStream();
        $incWriter->addNewObject($cs);
        $cs->beginText()->setFont('F1', 12)->moveTextPosition(72, 600)
            ->showText('File save test')->endText();

        $tmpFile = tempnam(sys_get_temp_dir(), 'inc_pdf_');
        try {
            $incWriter->save($tmpFile);
            $this->assertFileExists($tmpFile);

            $savedReader = PdfReader::fromFile($tmpFile);
            $this->assertSame(1, $savedReader->getPageCount());
        } finally {
            @unlink($tmpFile);
        }
    }

    public function testIncrementalXRefStream(): void
    {
        $original = $this->generateBasePdf();
        $reader = PdfReader::fromString($original);

        $incWriter = IncrementalWriter::fromReader(
            $reader,
            $original,
            compressStreams: false,
            useXRefStream: true,
        );

        $newCs = new ContentStream();
        $incWriter->addNewObject($newCs);
        $newCs->beginText()->setFont('F1', 12)->moveTextPosition(72, 600)
            ->showText('XRef stream incremental')->endText();

        $result = $incWriter->generate();

        // Should contain xref stream, not classic xref
        $this->assertStringContainsString('/Type /XRef', $result);
        $this->assertStringContainsString('/Prev', $result);
        $this->assertStringContainsString('/Index', $result);

        // Should be readable
        $newReader = PdfReader::fromString($result);
        $this->assertSame(1, $newReader->getPageCount());
    }

    public function testDeleteObjectEmitsFreeEntry(): void
    {
        $original = $this->generateBasePdf();
        $reader = PdfReader::fromString($original);

        $incWriter = IncrementalWriter::fromReader($reader, $original, compressStreams: false);
        $incWriter->deleteObject(4); // delete the content stream

        $result = $incWriter->generate();

        // Should have a free entry in the xref
        $this->assertMatchesRegularExpression('/\d{10} \d{5} f /', $result);

        // Still readable (page will just have no visible content)
        $newReader = PdfReader::fromString($result);
        $this->assertSame(1, $newReader->getPageCount());
    }

    public function testDeleteObjectRejectsZero(): void
    {
        $original = $this->generateBasePdf();
        $reader = PdfReader::fromString($original);
        $incWriter = IncrementalWriter::fromReader($reader, $original);

        $this->expectException(\InvalidArgumentException::class);
        $incWriter->deleteObject(0);
    }
}
