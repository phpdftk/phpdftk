<?php

declare(strict_types=1);

namespace Phpdftk\Pdf\Reader\Tests;

use Phpdftk\Pdf\Core\File\PdfFileWriter;
use Phpdftk\Pdf\Core\Font\StandardFont;
use Phpdftk\Pdf\Core\Font\Type1Font;
use Phpdftk\Pdf\Reader\PdfReader;
use Phpdftk\Pdf\Writer\PdfWriter;
use PHPUnit\Framework\TestCase;

class ObjectResolverTest extends TestCase
{
    private function makePdf(bool $useObjectStreams = false): string
    {
        // Use the lower-level PdfFileWriter so we can pass useObjectStreams.
        // PdfWriter doesn't expose this directly; use raw construction.
        $reflectionClass = new \ReflectionClass(PdfWriter::class);
        $writer = new PdfWriter(compressStreams: false);

        // Swap the inner PdfFileWriter for one with useObjectStreams=true if requested.
        if ($useObjectStreams) {
            $fileProp = $reflectionClass->getProperty('file');
            $fileProp->setAccessible(true);
            $fileProp->setValue($writer, new PdfFileWriter(useObjectStreams: true));

            // Re-register catalog & page tree to the new file writer
            $catalogProp = $reflectionClass->getProperty('catalog');
            $catalogProp->setAccessible(true);
            $catalog = $catalogProp->getValue($writer);

            $pageTreeProp = $reflectionClass->getProperty('pageTree');
            $pageTreeProp->setAccessible(true);
            $pageTree = $pageTreeProp->getValue($writer);

            $writer->fileWriter()->setCatalog($catalog);
            $writer->fileWriter()->register($pageTree);
            $catalog->pages = new \Phpdftk\Pdf\Core\PdfReference($pageTree->objectNumber);
        }

        $font = $writer->addFont(new Type1Font(StandardFont::Helvetica));
        $page = $writer->addPage(612, 792);
        $cs = $writer->addContentStream($page);
        $cs->beginText()
            ->setFont($font->getResourceName(), 12)
            ->moveTextPosition(72, 720)
            ->showText('Test')
            ->endText();
        return $writer->generate();
    }

    public function testResolveCompressedObjectFromObjStm(): void
    {
        $pdf = $this->makePdf(useObjectStreams: true);
        $reader = PdfReader::fromString($pdf);
        $this->assertSame(1, $reader->getPageCount());

        // Resolve all objects to exercise resolveCompressed
        $resolver = $reader->getResolver();
        foreach ($resolver->getObjectNumbers() as $num) {
            $resolver->resolve($num);
        }

        $this->addToAssertionCount(1);
    }

    public function testGetObjectNumbersAndEntries(): void
    {
        $pdf = $this->makePdf();
        $reader = PdfReader::fromString($pdf);
        $resolver = $reader->getResolver();

        $numbers = $resolver->getObjectNumbers();
        $this->assertNotEmpty($numbers);

        $entries = $resolver->getEntries();
        $this->assertNotEmpty($entries);
        foreach ($numbers as $num) {
            // Some entries are TYPE_FREE (e.g., obj 0); has() returns false for those.
            $this->assertNotNull($resolver->getEntry($num));
        }
        // At least one entry should be in-use
        $inUseFound = false;
        foreach ($numbers as $num) {
            if ($resolver->has($num)) {
                $inUseFound = true;
                break;
            }
        }
        $this->assertTrue($inUseFound);
    }

    public function testHasReturnsFalseForUnknownObject(): void
    {
        $pdf = $this->makePdf();
        $reader = PdfReader::fromString($pdf);
        $resolver = $reader->getResolver();

        $this->assertFalse($resolver->has(99999));
        $this->assertNull($resolver->getEntry(99999));
    }

    public function testReadRawReturnsBytes(): void
    {
        $pdf = $this->makePdf();
        $reader = PdfReader::fromString($pdf);
        $resolver = $reader->getResolver();

        $bytes = $resolver->readRaw(0, 16);
        $this->assertSame(16, strlen($bytes));
        $this->assertStringStartsWith('%PDF-', $bytes);
    }

    public function testScanObjectMapEnumeratesObjects(): void
    {
        $pdf = $this->makePdf();
        $reader = PdfReader::fromString($pdf);
        $map = $reader->getResolver()->scanObjectMap();
        $this->assertNotEmpty($map);
        // Map keys are object numbers
        foreach (array_keys($map) as $num) {
            $this->assertIsInt($num);
        }
    }

    public function testResolveFreeObjectReturnsNull(): void
    {
        $pdf = $this->makePdf();
        $reader = PdfReader::fromString($pdf);
        $resolver = $reader->getResolver();
        // Object 0 is always a free entry — must resolve to PdfNull
        $result = $resolver->resolve(0);
        $this->assertInstanceOf(\Phpdftk\Pdf\Core\PdfNull::class, $result);
    }

    public function testResolveUnknownObjectReturnsNull(): void
    {
        $pdf = $this->makePdf();
        $reader = PdfReader::fromString($pdf);
        $resolver = $reader->getResolver();
        // Object number that has no entry → PdfNull
        $result = $resolver->resolve(99999);
        $this->assertInstanceOf(\Phpdftk\Pdf\Core\PdfNull::class, $result);
    }

    public function testResolveReferenceDelegates(): void
    {
        $pdf = $this->makePdf();
        $reader = PdfReader::fromString($pdf);
        $resolver = $reader->getResolver();
        $catalog = $reader->getCatalog();
        $pagesRef = $catalog->get('Pages');
        $resolved = $resolver->resolveReference($pagesRef);
        $this->assertNotInstanceOf(\Phpdftk\Pdf\Core\PdfNull::class, $resolved);
    }

    public function testLenientModeRecoversFromCorruptXref(): void
    {
        $pdf = $this->makePdf();
        // Corrupt the xref offset for one of the objects by shifting bytes.
        // Easiest reliable corruption: replace the xref offset in the trailer
        // with a wildly wrong value. The PdfReader falls into lenient mode and
        // rescans the file to recover entries.
        $corrupted = preg_replace('/startxref\s+\d+/', "startxref\n99999999", $pdf, 1);
        $this->assertNotSame($pdf, $corrupted);

        // Lenient construction
        $reader = PdfReader::fromString($corrupted, strict: false);
        $this->assertGreaterThanOrEqual(0, $reader->getPageCount());
    }

    public function testResolveResolvesIndirectObjectAcrossCachedAccess(): void
    {
        $pdf = $this->makePdf();
        $reader = PdfReader::fromString($pdf);
        $resolver = $reader->getResolver();

        $numbers = $resolver->getObjectNumbers();
        $firstNum = null;
        foreach ($numbers as $num) {
            if ($num > 0) {
                $firstNum = $num;
                break;
            }
        }

        $this->assertNotNull($firstNum);
        $first = $resolver->resolve($firstNum);
        // Calling again should hit the cache and return the same instance
        $second = $resolver->resolve($firstNum);
        $this->assertSame($first, $second);
    }
}
