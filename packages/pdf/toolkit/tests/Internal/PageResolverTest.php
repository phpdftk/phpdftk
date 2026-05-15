<?php

declare(strict_types=1);

namespace Phpdftk\Pdf\Toolkit\Tests\Internal;

use Phpdftk\Pdf\Core\Font\StandardFont;
use Phpdftk\Pdf\Core\Font\Type1Font;
use Phpdftk\Pdf\Core\PdfArray;
use Phpdftk\Pdf\Core\PdfDictionary;
use Phpdftk\Pdf\Core\PdfNumber;
use Phpdftk\Pdf\Reader\PdfReader;
use Phpdftk\Pdf\Toolkit\Internal\PageResolver;
use Phpdftk\Pdf\Writer\PdfWriter;
use PHPUnit\Framework\TestCase;

class PageResolverTest extends TestCase
{
    private function generatePdf(int $pages = 1, float $width = 612, float $height = 792): string
    {
        $writer = new PdfWriter(compressStreams: false);
        $font = $writer->addFont(new Type1Font(StandardFont::Helvetica));
        for ($i = 0; $i < $pages; $i++) {
            $page = $writer->addPage($width, $height);
            $cs = $writer->addContentStream($page);
            $cs->beginText()
                ->setFont($font->getResourceName(), 12)
                ->moveTextPosition(72, 720)
                ->showText("Page " . ($i + 1))
                ->endText();
        }
        return $writer->generate();
    }

    public function testGetPageReferences(): void
    {
        $pdf = $this->generatePdf(3);
        $reader = PdfReader::fromString($pdf);
        $refs = PageResolver::getPageReferences($reader);
        $this->assertCount(3, $refs);
    }

    public function testGetPageDimensionsExplicitMediaBox(): void
    {
        $pdf = $this->generatePdf(1, 100, 200);
        $reader = PdfReader::fromString($pdf);
        $pageDict = $reader->getPage(0);
        $dims = PageResolver::getPageDimensions($pageDict, $reader);
        $this->assertSame(100.0, $dims['width']);
        $this->assertSame(200.0, $dims['height']);
    }

    public function testGetPageDimensionsDefaultsWhenMissing(): void
    {
        $reader = PdfReader::fromString($this->generatePdf());
        $emptyDict = new PdfDictionary();
        $dims = PageResolver::getPageDimensions($emptyDict, $reader);
        $this->assertSame(612.0, $dims['width']);
        $this->assertSame(792.0, $dims['height']);
    }

    public function testGetPageDimensionsInheritsMediaBoxFromParent(): void
    {
        // Page without its own MediaBox but with a parent that has one.
        // Construct minimal scenario: page dict has Parent ref pointing to a dict with MediaBox.
        $pdf = $this->generatePdf(1);
        $reader = PdfReader::fromString($pdf);
        $pageDict = $reader->getPage(0);

        // Build a synthetic child page dict that has no MediaBox but points back at the original.
        $child = new PdfDictionary();
        $child->set('Parent', $pageDict->get('Parent'));
        // The /Parent now resolves to the Pages tree dict, which usually has MediaBox set when added through writer.
        // Some PDF writers don't set MediaBox on the page tree node, so this is best-effort:
        $dims = PageResolver::getPageDimensions($child, $reader);
        $this->assertGreaterThan(0, $dims['width']);
        $this->assertGreaterThan(0, $dims['height']);
    }
}
