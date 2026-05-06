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
use Phpdftk\Pdf\Core\Security\PdfEncryptor;
use Phpdftk\Pdf\Reader\PdfReader;
use PHPUnit\Framework\TestCase;

class IncrementalWriterExtendedTest extends TestCase
{
    public function testIncrementalEncryptionRoundTrip(): void
    {
        // Generate an encrypted base PDF
        $fileId = md5('test-incr-encrypt', true);
        $encryptor = PdfEncryptor::aes128('user', 'owner', $fileId);

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

        $cs = new ContentStream();
        $writer->register($cs);
        $cs->beginText()
            ->setFont('F1', 12)
            ->moveTextPosition(72, 720)
            ->showText('Original page')
            ->endText();
        $page->contents = [new PdfReference($cs->objectNumber)];
        $pageTree->kids = [new PdfReference($page->objectNumber)];
        $pageTree->count = 1;

        $writer->setEncryption($encryptor);
        $basePdf = $writer->generate();

        // Read the encrypted PDF
        $reader = PdfReader::fromString($basePdf, 'user');
        $this->assertSame(1, $reader->getPageCount());

        // Create incremental update with encryption
        $incWriter = IncrementalWriter::fromReader($reader, $basePdf, compressStreams: false);

        // Create a new encryptor with the same credentials for the incremental update
        $incEncryptor = PdfEncryptor::aes128('user', 'owner', $fileId);
        $incWriter->setEncryption($incEncryptor);

        // Add a new content stream
        $newCs = new ContentStream();
        $newCs->beginText()
            ->setFont('F1', 12)
            ->moveTextPosition(72, 700)
            ->showText('Incrementally added text')
            ->endText();
        $incWriter->addNewObject($newCs);

        $updatedPdf = $incWriter->generate();

        // The new text should not be visible in raw bytes (encrypted)
        $appendedBytes = substr($updatedPdf, strlen($basePdf));
        $this->assertStringNotContainsString('Incrementally added text', $appendedBytes);

        // The updated PDF should still be readable
        $reader2 = PdfReader::fromString($updatedPdf, 'user');
        $this->assertSame(1, $reader2->getPageCount());
    }

    public function testIncrementalDeleteWithXRefStream(): void
    {
        // Generate base PDF with xref stream
        $writer = new PdfFileWriter(compressStreams: false, useXRefStream: true);
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

        $basePdf = $writer->generate();
        $reader = PdfReader::fromString($basePdf);

        // Incremental update with xref stream + deletion
        $incWriter = IncrementalWriter::fromReader(
            $reader,
            $basePdf,
            compressStreams: false,
            useXRefStream: true,
        );

        // Add a new object then delete it (simulates remove workflow)
        $newCs = new ContentStream();
        $newCs->beginText()->setFont('F1', 12)->moveTextPosition(72, 700)->showText('temp')->endText();
        $ref = $incWriter->addNewObject($newCs);

        // Also delete the content stream we just added
        $incWriter->deleteObject($ref->objectNumber);

        $updatedPdf = $incWriter->generate();
        $this->assertStringStartsWith('%PDF-', $updatedPdf);
        $this->assertStringEndsWith('%%EOF', $updatedPdf);
    }

    public function testIncrementalXRefStreamRoundTrip(): void
    {
        // Generate base PDF
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

        $basePdf = $writer->generate();
        $reader = PdfReader::fromString($basePdf);

        // Incremental update with xref stream mode
        $incWriter = IncrementalWriter::fromReader(
            $reader,
            $basePdf,
            compressStreams: false,
            useXRefStream: true,
        );

        $newCs = new ContentStream();
        $newCs->beginText()->setFont('F1', 12)->moveTextPosition(72, 700)->showText('New text')->endText();
        $incWriter->addNewObject($newCs);

        $updatedPdf = $incWriter->generate();

        // Should contain xref stream (not classic xref for the update section)
        $appendedSection = substr($updatedPdf, strlen($basePdf));
        $this->assertStringContainsString('/Type /XRef', $appendedSection);

        // Should be readable
        $reader2 = PdfReader::fromString($updatedPdf);
        $this->assertSame(1, $reader2->getPageCount());
    }
}
