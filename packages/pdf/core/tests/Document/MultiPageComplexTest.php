<?php

declare(strict_types=1);

namespace ApprLabs\Pdf\Core\Tests\Document;

use PHPUnit\Framework\TestCase;
use ApprLabs\Pdf\Core\PdfName;
use ApprLabs\Pdf\Core\PdfString;
use ApprLabs\Pdf\Core\Document\Info;
use ApprLabs\Pdf\Core\Document\ViewerPreferences;
use ApprLabs\Pdf\Core\Font\StandardFont;
use ApprLabs\Pdf\Core\Font\Type1Font;
use ApprLabs\Pdf\Writer\PdfWriter;

/**
 * Generates a complex 10-page PDF with varied content, viewer preferences,
 * and document information.
 */
class MultiPageComplexTest extends TestCase
{
    private const OUTPUT_FILE = __DIR__ . '/../../../../../docs/sample-pdfs/multi_page_complex.pdf';
    private const PAGE_COUNT  = 10;

    public function testGeneratesMultiPageComplexPdf(): void
    {
        $writer = new PdfWriter();

        // ----------------------------------------------------------------
        // Document info
        // ----------------------------------------------------------------
        $info = new Info();
        $info->title        = new PdfString('Multi-Page Complex Test');
        $info->author       = new PdfString('phpdftk Test Suite');
        $info->subject      = new PdfString('Testing multi-page PDF generation');
        $info->keywords     = new PdfString('pdf, test, phpdftk');
        $info->creator      = new PdfString('phpdftk');
        $info->producer     = new PdfString('phpdftk v1.0');
        $info->creationDate = new PdfString('D:20260316120000+00\'00\'');
        $writer->setInfo($info);

        // ----------------------------------------------------------------
        // Viewer preferences
        // ----------------------------------------------------------------
        $prefs = new ViewerPreferences();
        $prefs->hideToolbar    = true;
        $prefs->displayDocTitle = true;
        $prefs->fitWindow      = true;

        // We embed viewer preferences inline in the catalog
        $writer->getCatalog()->viewerPreferences = $prefs->toPdf() !== '<<\n>>'
            ? $this->buildViewerPrefsDict($prefs)
            : null;

        // ----------------------------------------------------------------
        // Fonts
        // ----------------------------------------------------------------
        $helveticaName = $writer->addFont(new Type1Font(StandardFont::Helvetica))->getResourceName();
        $boldName      = $writer->addFont(new Type1Font(StandardFont::HelveticaBold))->getResourceName();
        $courierName   = $writer->addFont(new Type1Font(StandardFont::Courier))->getResourceName();
        $timesName     = $writer->addFont(new Type1Font(StandardFont::TimesRoman))->getResourceName();

        // ----------------------------------------------------------------
        // Pages
        // ----------------------------------------------------------------
        $pageSizes = [
            [612, 792],   // Letter
            [595, 842],   // A4
            [612, 792],   // Letter
            [792, 612],   // Letter landscape
            [612, 792],   // Letter
            [595, 842],   // A4
            [612, 792],   // Letter
            [612, 792],   // Letter
            [595, 842],   // A4
            [612, 792],   // Letter
        ];

        for ($i = 0; $i < self::PAGE_COUNT; $i++) {
            $pageNum = $i + 1;
            [$w, $h] = $pageSizes[$i];

            $page = $writer->addPage((float)$w, (float)$h);
            $cs   = $writer->addContentStream($page);

            // Every page: a header bar
            $cs->saveGraphicsState()
               ->setFillColorRGB(0.1 + ($i * 0.08), 0.2, 0.8 - ($i * 0.06))
               ->rectangle(0, $h - 50, $w, 50)
               ->fill()
               ->restoreGraphicsState();

            // Page number in header
            $cs->beginText()
               ->setFont($boldName, 14)
               ->setFillColorRGB(1.0, 1.0, 1.0)  // white text in header
               ->moveTextPosition(20, $h - 32)
               ->showText(sprintf('Page %d of %d', $pageNum, self::PAGE_COUNT))
               ->endText();

            // Reset color and draw body content
            $cs->beginText()
               ->setFont($helveticaName, 12)
               ->setFillColorGray(0.0)
               ->moveTextPosition(72, $h - 100)
               ->showText(sprintf('Multi-Page Complex Test — Page %d', $pageNum))
               ->endText();

            // Varied content per page
            match ($pageNum % 5) {
                1 => $this->addTextContent($cs, $helveticaName, $timesName, $h),
                2 => $this->addGraphicsContent($cs, $w, $h),
                3 => $this->addMonospaceContent($cs, $courierName, $h),
                4 => $this->addMixedContent($cs, $boldName, $timesName, $h),
                0 => $this->addGeometricContent($cs, $w, $h),
            };

            // Footer
            $cs->beginText()
               ->setFont($helveticaName, 8)
               ->setFillColorGray(0.5)
               ->moveTextPosition(72, 20)
               ->showText(sprintf('phpdftk Test Suite — Page %d', $pageNum))
               ->endText();
        }

        // ----------------------------------------------------------------
        // Save and validate
        // ----------------------------------------------------------------
        $writer->save(self::OUTPUT_FILE);

        self::assertFileExists(self::OUTPUT_FILE);

        $content = file_get_contents(self::OUTPUT_FILE);
        self::assertNotFalse($content);
        self::assertStringStartsWith('%PDF-', $content);
        self::assertStringContainsString('%%EOF', $content);
        self::assertStringContainsString('/Count ' . self::PAGE_COUNT, $content);
        self::assertStringContainsString('/Title', $content);
        self::assertStringContainsString('/Author', $content);

        // Verify file size is reasonable
        self::assertGreaterThan(5000, strlen($content), 'PDF should be larger than 5KB for 10 pages');
    }

    private function addTextContent(\ApprLabs\Pdf\Core\Content\ContentStream $cs, string $font1, string $font2, float $h): void
    {
        $lines = [
            'The quick brown fox jumps over the lazy dog.',
            'Pack my box with five dozen liquor jugs.',
            'How vexingly quick daft zebras jump!',
            'The five boxing wizards jump quickly.',
            'Sphinx of black quartz, judge my vow.',
        ];

        $cs->beginText()->setFont($font1, 11)->moveTextPosition(72, $h - 140);
        foreach ($lines as $line) {
            $cs->showText($line)->moveTextPosition(0, -18);
        }
        $cs->endText();

        $cs->beginText()->setFont($font2, 10)->moveTextPosition(72, $h - 250)
           ->showText('Times Roman paragraph:')
           ->moveTextPosition(0, -16)
           ->showText('Lorem ipsum dolor sit amet, consectetur adipiscing elit.')
           ->moveTextPosition(0, -16)
           ->showText('Sed do eiusmod tempor incididunt ut labore et dolore magna.')
           ->endText();
    }

    private function addGraphicsContent(\ApprLabs\Pdf\Core\Content\ContentStream $cs, float $w, float $h): void
    {
        // Color bars
        $colors = [
            [1.0, 0.0, 0.0],
            [0.0, 1.0, 0.0],
            [0.0, 0.0, 1.0],
            [1.0, 1.0, 0.0],
            [1.0, 0.0, 1.0],
            [0.0, 1.0, 1.0],
        ];
        $barW = ($w - 144) / count($colors);
        foreach ($colors as $idx => [$r, $g, $b]) {
            $cs->saveGraphicsState()
               ->setFillColorRGB($r, $g, $b)
               ->rectangle(72 + $idx * $barW, $h - 280, $barW - 2, 80)
               ->fill()
               ->restoreGraphicsState();
        }

        // Gray gradient
        for ($i = 0; $i < 20; $i++) {
            $gray = $i / 20.0;
            $cs->saveGraphicsState()
               ->setFillColorGray($gray)
               ->rectangle(72 + $i * (($w - 144) / 20), $h - 380, ($w - 144) / 20, 60)
               ->fill()
               ->restoreGraphicsState();
        }
    }

    private function addMonospaceContent(\ApprLabs\Pdf\Core\Content\ContentStream $cs, string $courier, float $h): void
    {
        $codeLines = [
            '<?php',
            '',
            'declare(strict_types=1);',
            '',
            'class PdfWriter {',
            '    public function generate(): string {',
            '        $output = \'%PDF-1.7\n\';',
            '        // ... build PDF structure',
            '        return $output;',
            '    }',
            '}',
        ];

        $cs->saveGraphicsState()
           ->setFillColorRGB(0.95, 0.95, 0.95)
           ->rectangle(60, $h - 300, 480, count($codeLines) * 14 + 20)
           ->fill()
           ->restoreGraphicsState();

        $cs->beginText()->setFont($courier, 10)->moveTextPosition(72, $h - 150);
        foreach ($codeLines as $line) {
            $cs->showText($line)->moveTextPosition(0, -14);
        }
        $cs->endText();
    }

    private function addMixedContent(\ApprLabs\Pdf\Core\Content\ContentStream $cs, string $bold, string $times, float $h): void
    {
        $cs->beginText()
           ->setFont($bold, 14)
           ->moveTextPosition(72, $h - 140)
           ->showText('Bold Heading')
           ->moveTextPosition(0, -24)
           ->setFont($times, 11)
           ->showText('Regular paragraph text follows the heading.')
           ->moveTextPosition(0, -16)
           ->showText('More regular text here with Times Roman font.')
           ->moveTextPosition(0, -30)
           ->setFont($bold, 12)
           ->showText('Another Bold Section')
           ->moveTextPosition(0, -20)
           ->setFont($times, 11)
           ->showText('And more body text below.')
           ->endText();
    }

    private function addGeometricContent(\ApprLabs\Pdf\Core\Content\ContentStream $cs, float $w, float $h): void
    {
        $centerX = $w / 2;
        $centerY = $h / 2;

        // Nested rectangles
        for ($i = 5; $i >= 1; $i--) {
            $sz = $i * 40.0;
            $shade = ($i - 1) / 4.0;
            $cs->saveGraphicsState()
               ->setFillColorRGB(1.0 - $shade * 0.8, $shade * 0.5, $shade)
               ->setStrokeColorRGB(0.0, 0.0, 0.0)
               ->setLineWidth(0.5)
               ->rectangle($centerX - $sz, $centerY - 100 - $sz, $sz * 2, $sz * 2)
               ->fillAndStroke()
               ->restoreGraphicsState();
        }
    }

    /**
     * Build a PdfDictionary from ViewerPreferences for direct inline use.
     */
    private function buildViewerPrefsDict(ViewerPreferences $prefs): \ApprLabs\Pdf\Core\PdfDictionary
    {
        // We can re-use toPdf() result by embedding it as raw dict
        // Parse back is not needed — instead we return a pre-built dict
        $dict = new \ApprLabs\Pdf\Core\PdfDictionary();
        if ($prefs->hideToolbar !== null) {
            $dict->set('HideToolbar', new \ApprLabs\Pdf\Core\PdfBoolean($prefs->hideToolbar));
        }
        if ($prefs->displayDocTitle !== null) {
            $dict->set('DisplayDocTitle', new \ApprLabs\Pdf\Core\PdfBoolean($prefs->displayDocTitle));
        }
        if ($prefs->fitWindow !== null) {
            $dict->set('FitWindow', new \ApprLabs\Pdf\Core\PdfBoolean($prefs->fitWindow));
        }
        return $dict;
    }
}
