<?php

declare(strict_types=1);

namespace ApprLabs\Pdf\Reader\Tests\Integration;

use ApprLabs\Pdf\Core\Document\Catalog;
use ApprLabs\Pdf\Core\Document\Page;
use ApprLabs\Pdf\Reader\PdfReader;
use PHPUnit\Framework\TestCase;

/**
 * Tests the hydration layer: read a PDF → hydrate into typed objects.
 */
class RoundTripTest extends TestCase
{
    private function samplePath(string $name): string
    {
        return __DIR__ . '/../../../../../docs/sample-pdfs/' . $name;
    }

    public function testGetTypedCatalog(): void
    {
        $reader = PdfReader::fromFile($this->samplePath('simple_text.pdf'));
        $catalog = $reader->getTypedCatalog();

        $this->assertInstanceOf(Catalog::class, $catalog);
        $this->assertNotNull($catalog->pages, 'Catalog should have /Pages');
    }

    public function testGetTypedPage(): void
    {
        $reader = PdfReader::fromFile($this->samplePath('simple_text.pdf'));
        $page = $reader->getTypedPage(0);

        $this->assertInstanceOf(Page::class, $page);
        $this->assertNotNull($page->mediaBox, 'Page should have /MediaBox');
    }

    public function testGetTypedPages(): void
    {
        $reader = PdfReader::fromFile($this->samplePath('simple_text.pdf'));
        $pages = $reader->getTypedPages();

        $this->assertCount($reader->getPageCount(), $pages);
        foreach ($pages as $page) {
            $this->assertInstanceOf(Page::class, $page);
        }
    }

    public function testHydratedCatalogCanSerialize(): void
    {
        $reader = PdfReader::fromFile($this->samplePath('simple_text.pdf'));
        $catalog = $reader->getTypedCatalog();

        $pdf = $catalog->toPdf();
        $this->assertStringContainsString('/Type /Catalog', $pdf);
        $this->assertStringContainsString('/Pages', $pdf);
    }

    public function testHydratedPageCanSerialize(): void
    {
        $reader = PdfReader::fromFile($this->samplePath('simple_text.pdf'));
        $page = $reader->getTypedPage(0);

        $pdf = $page->toPdf();
        $this->assertStringContainsString('/Type /Page', $pdf);
        $this->assertStringContainsString('/MediaBox', $pdf);
    }

    public function testMultiPageComplex(): void
    {
        $reader = PdfReader::fromFile($this->samplePath('multi_page_complex.pdf'));
        $pages = $reader->getTypedPages();

        $this->assertSame(10, count($pages));
        foreach ($pages as $page) {
            $this->assertInstanceOf(Page::class, $page);
            $this->assertNotNull($page->mediaBox);
        }
    }

    public function testFpdfGeneratedPdf(): void
    {
        $reader = PdfReader::fromFile($this->samplePath('bench_10pages.pdf'));
        $catalog = $reader->getTypedCatalog();

        $this->assertInstanceOf(Catalog::class, $catalog);

        $pages = $reader->getTypedPages();
        $this->assertSame(10, count($pages));
    }
}
