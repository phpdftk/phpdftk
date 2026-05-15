<?php

declare(strict_types=1);

namespace Phpdftk\Pdf\Toolkit\Tests;

use Phpdftk\Pdf\Core\Font\StandardFont;
use Phpdftk\Pdf\Core\Font\Type1Font;
use Phpdftk\Pdf\Toolkit\AnnotationFlattener;
use Phpdftk\Pdf\Toolkit\BookmarkEditor;
use Phpdftk\Pdf\Toolkit\FormFiller;
use Phpdftk\Pdf\Toolkit\PageLabeler;
use Phpdftk\Pdf\Toolkit\PageSelector;
use Phpdftk\Pdf\Toolkit\PageSlicer;
use Phpdftk\Pdf\Toolkit\PageTransformer;
use Phpdftk\Pdf\Toolkit\PdfMerger;
use Phpdftk\Pdf\Toolkit\PdfStamper;
use Phpdftk\Pdf\Toolkit\TextRedactor;
use Phpdftk\Pdf\Writer\PdfWriter;
use PHPUnit\Framework\TestCase;

/**
 * Lightweight smoke tests for toolkit facade methods that are otherwise
 * uncovered — `open()` file-based wrappers and `getVersionWarnings()`
 * accessors.
 */
class ToolkitSmokeTest extends TestCase
{
    private string $samplePdfPath;

    protected function setUp(): void
    {
        $writer = new PdfWriter(compressStreams: false);
        $font = $writer->addFont(new Type1Font(StandardFont::Helvetica));
        $page = $writer->addPage(612, 792);
        $cs = $writer->addContentStream($page);
        $cs->beginText()
            ->setFont($font->getResourceName(), 12)
            ->moveTextPosition(72, 720)
            ->showText('Sample text')
            ->endText();

        $this->samplePdfPath = tempnam(sys_get_temp_dir(), 'phpdftk_smoke_') . '.pdf';
        file_put_contents($this->samplePdfPath, $writer->generate());
    }

    protected function tearDown(): void
    {
        @unlink($this->samplePdfPath);
    }

    public function testFormFillerOpenAndVersionWarnings(): void
    {
        $filler = FormFiller::open($this->samplePdfPath);
        $this->assertIsArray($filler->getVersionWarnings());
    }

    public function testPdfStamperOpenAndVersionWarnings(): void
    {
        $stamper = PdfStamper::open($this->samplePdfPath);
        $this->assertIsArray($stamper->getVersionWarnings());
    }

    public function testPageSlicerOpenSaveVersionWarnings(): void
    {
        $slicer = PageSlicer::open($this->samplePdfPath);
        $this->assertIsArray($slicer->getVersionWarnings());

        $outPath = tempnam(sys_get_temp_dir(), 'phpdftk_slice_') . '.pdf';
        try {
            $slicer->keep(PageSelector::all())->save($outPath);
            $this->assertFileExists($outPath);
        } finally {
            @unlink($outPath);
        }
    }

    public function testPageTransformerOpenAndVersionWarnings(): void
    {
        $t = PageTransformer::open($this->samplePdfPath);
        $this->assertIsArray($t->getVersionWarnings());
    }

    public function testTextRedactorOpenAndVersionWarnings(): void
    {
        $r = TextRedactor::open($this->samplePdfPath);
        $this->assertIsArray($r->getVersionWarnings());
    }

    public function testBookmarkEditorGetVersionWarnings(): void
    {
        $b = BookmarkEditor::openString(file_get_contents($this->samplePdfPath));
        $this->assertIsArray($b->getVersionWarnings());
    }

    public function testPageLabelerGetVersionWarnings(): void
    {
        $l = PageLabeler::openString(file_get_contents($this->samplePdfPath));
        $this->assertIsArray($l->getVersionWarnings());
    }

    public function testPdfMergerAddFileAndVersionWarnings(): void
    {
        $merger = PdfMerger::create()->addFile($this->samplePdfPath);
        $this->assertSame(1, $merger->getSourceCount());
        $this->assertIsArray($merger->getVersionWarnings());
    }

    public function testAnnotationFlattenerOpenFromDiskAndWarnings(): void
    {
        $flattener = AnnotationFlattener::open($this->samplePdfPath);
        $this->assertIsArray($flattener->getVersionWarnings());
    }
}
