<?php

declare(strict_types=1);

namespace Phpdftk\Pdf\Toolkit\Tests;

use Phpdftk\Pdf\Core\Font\StandardFont;
use Phpdftk\Pdf\Core\Font\Type1Font;
use Phpdftk\Pdf\Reader\PdfReader;
use Phpdftk\Pdf\Toolkit\TextExtractor;
use Phpdftk\Pdf\Toolkit\TextMatch;
use Phpdftk\Pdf\Toolkit\TextSearchResults;
use Phpdftk\Pdf\Writer\PdfWriter;
use PHPUnit\Framework\TestCase;

class TextExtractorTest extends TestCase
{
    private function generateSimplePdf(): string
    {
        $writer = new PdfWriter(compressStreams: false);
        $page1 = $writer->addPage(612, 792);
        $page2 = $writer->addPage(612, 792);
        $font = $writer->addFont(new Type1Font(StandardFont::Helvetica));

        $cs1 = $writer->addContentStream($page1);
        $cs1->beginText()
            ->setFont($font->getResourceName(), 12)
            ->moveTextPosition(72, 720)
            ->showText('Hello World from page one')
            ->endText();

        $cs2 = $writer->addContentStream($page2);
        $cs2->beginText()
            ->setFont($font->getResourceName(), 12)
            ->moveTextPosition(72, 720)
            ->showText('Goodbye World from page two')
            ->endText();

        return $writer->generate();
    }

    public function testOpenString(): void
    {
        $pdf = $this->generateSimplePdf();
        $extractor = TextExtractor::openString($pdf);
        $this->assertSame(2, $extractor->getPageCount());
    }

    public function testPageExtraction(): void
    {
        $pdf = $this->generateSimplePdf();
        $extractor = TextExtractor::openString($pdf);

        $text = $extractor->page(1);
        $this->assertStringContainsString('Hello World', $text);
        $this->assertStringNotContainsString('Goodbye', $text);
    }

    public function testAllPages(): void
    {
        $pdf = $this->generateSimplePdf();
        $extractor = TextExtractor::openString($pdf);

        $text = $extractor->allPages();
        $this->assertStringContainsString('Hello World', $text);
        $this->assertStringContainsString('Goodbye World', $text);
    }

    public function testPerPage(): void
    {
        $pdf = $this->generateSimplePdf();
        $extractor = TextExtractor::openString($pdf);

        $pages = $extractor->perPage();
        $this->assertCount(2, $pages);
        $this->assertArrayHasKey(1, $pages);
        $this->assertArrayHasKey(2, $pages);
        $this->assertStringContainsString('Hello', $pages[1]);
        $this->assertStringContainsString('Goodbye', $pages[2]);
    }

    public function testContains(): void
    {
        $pdf = $this->generateSimplePdf();
        $extractor = TextExtractor::openString($pdf);

        $this->assertTrue($extractor->contains('Hello World'));
        $this->assertTrue($extractor->contains('Goodbye'));
        $this->assertFalse($extractor->contains('nonexistent text'));
    }

    public function testSearch(): void
    {
        $pdf = $this->generateSimplePdf();
        $extractor = TextExtractor::openString($pdf);

        $results = $extractor->search('World');
        $this->assertInstanceOf(TextSearchResults::class, $results);
        $this->assertSame(2, $results->count());

        $all = $results->all();
        $this->assertSame(1, $all[0]->pageNumber);
        $this->assertSame('World', $all[0]->text);
        $this->assertSame(2, $all[1]->pageNumber);
    }

    public function testSearchFirst(): void
    {
        $pdf = $this->generateSimplePdf();
        $extractor = TextExtractor::openString($pdf);

        $result = $extractor->search('Hello')->first();
        $this->assertInstanceOf(TextMatch::class, $result);
        $this->assertSame(1, $result->pageNumber);
    }

    public function testSearchNoResults(): void
    {
        $pdf = $this->generateSimplePdf();
        $extractor = TextExtractor::openString($pdf);

        $results = $extractor->search('nonexistent');
        $this->assertSame(0, $results->count());
        $this->assertNull($results->first());
    }

    public function testSearchPattern(): void
    {
        $pdf = $this->generateSimplePdf();
        $extractor = TextExtractor::openString($pdf);

        $results = $extractor->searchPattern('/page (one|two)/');
        $this->assertSame(2, $results->count());

        $all = $results->all();
        $this->assertSame('page one', $all[0]->text);
        $this->assertSame(1, $all[0]->pageNumber);
        $this->assertSame('page two', $all[1]->text);
        $this->assertSame(2, $all[1]->pageNumber);
    }

    public function testSearchResultsIterable(): void
    {
        $pdf = $this->generateSimplePdf();
        $extractor = TextExtractor::openString($pdf);

        $results = $extractor->search('World');
        $count = 0;
        foreach ($results as $match) {
            $this->assertInstanceOf(TextMatch::class, $match);
            $count++;
        }
        $this->assertSame(2, $count);
    }

    public function testEscapeHatch(): void
    {
        $pdf = $this->generateSimplePdf();
        $extractor = TextExtractor::openString($pdf);

        $reader = $extractor->getReader();
        $this->assertInstanceOf(PdfReader::class, $reader);
        $this->assertSame(2, $reader->getPageCount());
    }

    public function testOpenFile(): void
    {
        $sampleDir = dirname(__DIR__, 4) . '/docs/sample-pdfs';
        $path = $sampleDir . '/bench_1page.pdf';
        if (!file_exists($path)) {
            $this->markTestSkipped('Sample PDF not found');
        }

        $extractor = TextExtractor::open($path);
        $this->assertSame(1, $extractor->getPageCount());
    }
}
