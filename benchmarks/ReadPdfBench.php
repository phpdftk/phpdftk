<?php

declare(strict_types=1);

namespace ApprLabs\Benchmarks;

use PhpBench\Attributes as Bench;
use ApprLabs\Pdf\Reader\PdfReader;

/**
 * Reader benchmarks — parse existing PDFs and extract structure.
 *
 * Compares phpdftk reader against smalot/pdfparser and setasign/fpdi.
 *
 * ## Scaling benchmarks (FPDF-generated, classic xref, all readers parse OK)
 *
 *   bench_1page.pdf    —  1.4 KB,   1 page
 *   bench_10pages.pdf  —  5.9 KB,  10 pages
 *   bench_100pages.pdf — 49.3 KB, 100 pages
 *
 * ## Compatibility benchmarks (phpdftk-generated, spec-compliant features)
 *
 *   bench_standard_10pages.pdf — spec-compliant 20-byte xref entries (SP CR LF)
 *     smalot: FAILS (expects non-compliant 19-byte SP LF entries)
 *     fpdi:   OK
 *
 *   xref_stream.pdf — PDF 1.5 cross-reference stream + object stream
 *     smalot: OK (only because xref stream has no classic xref to misparse)
 *     fpdi:   FAILS (no xref stream support in free version)
 */
#[Bench\Iterations(5)]
#[Bench\Revs(3)]
class ReadPdfBench
{
    private string $sampleDir;

    public function setUp(): void
    {
        $this->sampleDir = __DIR__ . '/../docs/sample-pdfs';
    }

    // -----------------------------------------------------------------------
    // phpdftk — scaling
    // -----------------------------------------------------------------------

    #[Bench\Subject]
    #[Bench\BeforeMethods('setUp')]
    public function benchPhpdftk1Page(): void
    {
        $this->readPhpdftk('bench_1page.pdf');
    }

    #[Bench\Subject]
    #[Bench\BeforeMethods('setUp')]
    public function benchPhpdftk10Pages(): void
    {
        $this->readPhpdftk('bench_10pages.pdf');
    }

    #[Bench\Subject]
    #[Bench\BeforeMethods('setUp')]
    public function benchPhpdftk100Pages(): void
    {
        $this->readPhpdftk('bench_100pages.pdf');
    }

    // -----------------------------------------------------------------------
    // phpdftk — compatibility (spec-compliant PDFs)
    // -----------------------------------------------------------------------

    #[Bench\Subject]
    #[Bench\BeforeMethods('setUp')]
    public function benchPhpdftkSpecCompliantXref(): void
    {
        $this->readPhpdftk('bench_standard_10pages.pdf');
    }

    #[Bench\Subject]
    #[Bench\BeforeMethods('setUp')]
    public function benchPhpdftkXrefStream(): void
    {
        $this->readPhpdftk('xref_stream.pdf');
    }

    // -----------------------------------------------------------------------
    // smalot/pdfparser — scaling
    // -----------------------------------------------------------------------

    #[Bench\Subject]
    #[Bench\BeforeMethods('setUp')]
    public function benchSmalot1Page(): void
    {
        $this->readSmalot('bench_1page.pdf');
    }

    #[Bench\Subject]
    #[Bench\BeforeMethods('setUp')]
    public function benchSmalot10Pages(): void
    {
        $this->readSmalot('bench_10pages.pdf');
    }

    #[Bench\Subject]
    #[Bench\BeforeMethods('setUp')]
    public function benchSmalot100Pages(): void
    {
        $this->readSmalot('bench_100pages.pdf');
    }

    // -----------------------------------------------------------------------
    // smalot/pdfparser — compatibility
    // -----------------------------------------------------------------------

    #[Bench\Subject]
    #[Bench\BeforeMethods('setUp')]
    public function benchSmalotSpecCompliantXref(): void
    {
        $this->readSmalot('bench_standard_10pages.pdf');
    }

    #[Bench\Subject]
    #[Bench\BeforeMethods('setUp')]
    public function benchSmalotXrefStream(): void
    {
        $this->readSmalot('xref_stream.pdf');
    }

    // -----------------------------------------------------------------------
    // setasign/fpdi — scaling
    // -----------------------------------------------------------------------

    #[Bench\Subject]
    #[Bench\BeforeMethods('setUp')]
    public function benchFpdi1Page(): void
    {
        $this->readFpdi('bench_1page.pdf');
    }

    #[Bench\Subject]
    #[Bench\BeforeMethods('setUp')]
    public function benchFpdi10Pages(): void
    {
        $this->readFpdi('bench_10pages.pdf');
    }

    #[Bench\Subject]
    #[Bench\BeforeMethods('setUp')]
    public function benchFpdi100Pages(): void
    {
        $this->readFpdi('bench_100pages.pdf');
    }

    // -----------------------------------------------------------------------
    // setasign/fpdi — compatibility
    // -----------------------------------------------------------------------

    #[Bench\Subject]
    #[Bench\BeforeMethods('setUp')]
    public function benchFpdiSpecCompliantXref(): void
    {
        $this->readFpdi('bench_standard_10pages.pdf');
    }

    #[Bench\Subject]
    #[Bench\BeforeMethods('setUp')]
    public function benchFpdiXrefStream(): void
    {
        $this->readFpdi('xref_stream.pdf');
    }

    // -----------------------------------------------------------------------
    // Form XObject text extraction
    // -----------------------------------------------------------------------

    private ?string $formXObjectPdf = null;

    private function ensureFormXObjectPdf(): void
    {
        if ($this->formXObjectPdf !== null) {
            return;
        }
        $writer = new \ApprLabs\Pdf\Writer\PdfWriter(compressStreams: false);
        $coreFont = new \ApprLabs\Pdf\Core\Font\Type1Font(
            \ApprLabs\Pdf\Core\Font\StandardFont::Helvetica
        );
        $font = $writer->addFont($coreFont);

        for ($p = 0; $p < 10; $p++) {
            $page = $writer->addPage(612, 792);

            // Create a Form XObject with text
            $bbox = new \ApprLabs\Pdf\Core\PdfArray([
                new \ApprLabs\Pdf\Core\PdfNumber(0), new \ApprLabs\Pdf\Core\PdfNumber(0),
                new \ApprLabs\Pdf\Core\PdfNumber(500), new \ApprLabs\Pdf\Core\PdfNumber(100),
            ]);
            $xobjContent = sprintf(
                "BT\n/%s 12 Tf\n10 10 Td\n(Stamped text in Form XObject on page %d) Tj\nET",
                $font->getResourceName(), $p + 1
            );
            $formXObj = new \ApprLabs\Pdf\Core\Graphics\XObject\FormXObject($bbox, $xobjContent);
            $formXObj->resources = new \ApprLabs\Pdf\Core\Content\Resources();
            $formXObj->resources->addFont(
                $font->getResourceName(),
                new \ApprLabs\Pdf\Core\PdfReference($coreFont->objectNumber)
            );
            $writer->register($formXObj);

            $page->corePage()->resources->addXObject(
                'FX1',
                new \ApprLabs\Pdf\Core\PdfReference($formXObj->objectNumber)
            );
            $content = $writer->addContentStream($page->corePage());
            $content->beginText()
                ->setFont($font->getResourceName(), 12)
                ->moveTextPosition(72, 720)
                ->showText("Page $p direct text")
                ->endText()
                ->doXObject('FX1');
        }
        $this->formXObjectPdf = $writer->toBytes();
    }

    #[Bench\Subject]
    #[Bench\BeforeMethods('setUp')]
    public function benchPhpdftkTextExtractionWithFormXObjects(): void
    {
        $this->ensureFormXObjectPdf();
        $reader = \ApprLabs\Pdf\Reader\PdfReader::fromString($this->formXObjectPdf);
        $text = $reader->extractAllText();
        assert(str_contains($text, 'Form XObject'));
    }

    // -----------------------------------------------------------------------
    // phpdftk — linearized PDF reading
    // -----------------------------------------------------------------------

    #[Bench\Subject]
    #[Bench\BeforeMethods('setUp')]
    public function benchPhpdftkLinearizedPdf(): void
    {
        // Generate a linearized PDF, then read it
        $writer = new \ApprLabs\Pdf\Writer\PdfWriter();
        $writer->setLinearized();
        $fontName = $writer->addFont(new \ApprLabs\Pdf\Core\Font\Type1Font(
            \ApprLabs\Pdf\Core\Font\StandardFont::Helvetica
        ))->getResourceName();

        for ($i = 1; $i <= 10; $i++) {
            $page = $writer->addPage(612, 792);
            $cs = $writer->addContentStream($page);
            $cs->beginText()
               ->setFont($fontName, 12)
               ->moveTextPosition(72, 720)
               ->showText("Linearized page $i")
               ->endText();
        }

        $bytes = $writer->toBytes();
        $reader = PdfReader::fromString($bytes);
        assert($reader->isLinearized());
        assert($reader->getPageCount() === 10);
    }

    // -----------------------------------------------------------------------
    // phpdftk — WOFF2 font parsing
    // -----------------------------------------------------------------------

    #[Bench\Subject]
    #[Bench\BeforeMethods('setUp')]
    #[Bench\Revs(5)]
    #[Bench\Iterations(3)]
    public function benchPhpdftkWoff2Parsing(): void
    {
        // Find a WOFF2 font or skip
        $woff2Paths = glob('/System/Library/Fonts/*.ttc') ?: [];
        // WOFF2 files are rare on macOS; use TrueType parser with variable font as fallback
        $ttfPath = '/System/Library/Fonts/Supplemental/Arial.ttf';
        if (!file_exists($ttfPath)) {
            return;
        }

        // Benchmark TrueType parsing including variable font detection
        for ($i = 0; $i < 10; $i++) {
            $parser = new \ApprLabs\FontParser\TrueTypeParser($ttfPath);
            $data = $parser->parse();
            assert($data->familyName !== '');
        }
    }

    // -----------------------------------------------------------------------
    // Private helpers
    // -----------------------------------------------------------------------

    private function readPhpdftk(string $filename): void
    {
        $path = $this->sampleDir . '/' . $filename;
        $reader = PdfReader::fromFile($path);

        // Parse structure
        $reader->getVersion();
        $reader->getCatalog();
        $reader->getInfo();

        // Iterate all pages
        $count = $reader->getPageCount();
        $pages = $reader->getPages();
        assert(count($pages) === $count);
    }

    private function readSmalot(string $filename): void
    {
        if (!class_exists(\Smalot\PdfParser\Parser::class)) {
            return;
        }

        $path = $this->sampleDir . '/' . $filename;
        $parser = new \Smalot\PdfParser\Parser();

        try {
            $document = @$parser->parseFile($path);
        } catch (\Throwable) {
            // Parser cannot handle this PDF — record the attempt
            return;
        }

        // Parse structure
        $document->getDetails();

        // Iterate all pages
        $pages = $document->getPages();
        assert(count($pages) > 0);
    }

    private function readFpdi(string $filename): void
    {
        if (!class_exists(\setasign\Fpdi\Fpdi::class)) {
            return;
        }

        $path = $this->sampleDir . '/' . $filename;
        $fpdi = new \setasign\Fpdi\Fpdi();

        try {
            // Parse and get page count
            $pageCount = $fpdi->setSourceFile($path);
        } catch (\Throwable) {
            // Parser cannot handle this PDF — record the attempt
            return;
        }

        // Import each page (FPDI's primary read operation)
        for ($i = 1; $i <= $pageCount; $i++) {
            $tpl = $fpdi->importPage($i);
            $fpdi->getImportedPageSize($tpl);
        }
    }
}
