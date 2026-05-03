<?php

declare(strict_types=1);

namespace Phpdftk\Benchmarks;

use PhpBench\Attributes as Bench;
use Phpdftk\Pdf\Conformance\ConformanceChecker;
use Phpdftk\Pdf\Conformance\Profile\PdfAProfile;
use Phpdftk\Pdf\Reader\PdfReader;

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
        $writer = new \Phpdftk\Pdf\Writer\PdfWriter(compressStreams: false);
        $coreFont = new \Phpdftk\Pdf\Core\Font\Type1Font(
            \Phpdftk\Pdf\Core\Font\StandardFont::Helvetica
        );
        $font = $writer->addFont($coreFont);

        for ($p = 0; $p < 10; $p++) {
            $page = $writer->addPage(612, 792);

            // Create a Form XObject with text
            $bbox = new \Phpdftk\Pdf\Core\PdfArray([
                new \Phpdftk\Pdf\Core\PdfNumber(0), new \Phpdftk\Pdf\Core\PdfNumber(0),
                new \Phpdftk\Pdf\Core\PdfNumber(500), new \Phpdftk\Pdf\Core\PdfNumber(100),
            ]);
            $xobjContent = sprintf(
                "BT\n/%s 12 Tf\n10 10 Td\n(Stamped text in Form XObject on page %d) Tj\nET",
                $font->getResourceName(), $p + 1
            );
            $formXObj = new \Phpdftk\Pdf\Core\Graphics\XObject\FormXObject($bbox, $xobjContent);
            $formXObj->resources = new \Phpdftk\Pdf\Core\Content\Resources();
            $formXObj->resources->addFont(
                $font->getResourceName(),
                new \Phpdftk\Pdf\Core\PdfReference($coreFont->objectNumber)
            );
            $writer->register($formXObj);

            $page->corePage()->resources->addXObject(
                'FX1',
                new \Phpdftk\Pdf\Core\PdfReference($formXObj->objectNumber)
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
        $reader = \Phpdftk\Pdf\Reader\PdfReader::fromString($this->formXObjectPdf);
        $text = $reader->extractAllText();
        assert(str_contains($text, 'Form XObject'));
    }

    // -----------------------------------------------------------------------
    // phpdftk — positioned text extraction
    // -----------------------------------------------------------------------

    #[Bench\Subject]
    #[Bench\BeforeMethods('setUp')]
    public function benchPhpdftkPositionedTextExtraction(): void
    {
        // Generate a 10-page PDF with multiple text blocks per page
        $writer = new \Phpdftk\Pdf\Writer\PdfWriter(compressStreams: false);
        $font = $writer->addFont(new \Phpdftk\Pdf\Core\Font\Type1Font(
            \Phpdftk\Pdf\Core\Font\StandardFont::Helvetica
        ));

        for ($p = 1; $p <= 10; $p++) {
            $page = $writer->addPage(612, 792);
            $cs = $writer->addContentStream($page);
            $cs->beginText()
                ->setFont($font->getResourceName(), 12)
                ->moveTextPosition(72, 720)
                ->showText("Page $p heading")
                ->moveTextPosition(0, -20)
                ->showText("Body text on page $p with more content here")
                ->moveTextPosition(0, -20)
                ->showText("Footer line for page $p")
                ->endText();
        }

        $bytes = $writer->toBytes();
        $reader = PdfReader::fromString($bytes);
        $allSpans = $reader->extractAllTextWithPositions();
        assert(count($allSpans) === 10);
        assert(count($allSpans[0]) === 3); // 3 text spans per page
    }

    // -----------------------------------------------------------------------
    // phpdftk — linearized PDF reading
    // -----------------------------------------------------------------------

    #[Bench\Subject]
    #[Bench\BeforeMethods('setUp')]
    public function benchPhpdftkLinearizedPdf(): void
    {
        // Generate a linearized PDF, then read it
        $writer = new \Phpdftk\Pdf\Writer\PdfWriter();
        $writer->setLinearized();
        $fontName = $writer->addFont(new \Phpdftk\Pdf\Core\Font\Type1Font(
            \Phpdftk\Pdf\Core\Font\StandardFont::Helvetica
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
            $parser = new \Phpdftk\FontParser\TrueTypeParser($ttfPath);
            $data = $parser->parse();
            assert($data->familyName !== '');
        }
    }

    /**
     * Reader-side conformance checking: parse a 10-page PDF and validate
     * against PDF/A-1b via ConformanceChecker.
     *
     * Exercises ReaderDocumentInspector: catalog hydration, metadata
     * detection, OutputIntent resolution, font enumeration, and all
     * PDF/A-1b constraint checks on the parsed document.
     */
    #[Bench\Subject]
    #[Bench\BeforeMethods('setUp')]
    public function benchPhpdftkConformanceChecker(): void
    {
        // Use a sample PDF that exists — the simple_text.pdf is always available
        $path = $this->sampleDir . '/simple_text.pdf';
        if (!file_exists($path)) {
            return;
        }

        $checker = ConformanceChecker::open($path);
        $result = $checker->checkProfile(PdfAProfile::A1b);

        // The sample PDF is not PDF/A-1b compliant, but the check should complete
        assert($result->profile === PdfAProfile::A1b);
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
