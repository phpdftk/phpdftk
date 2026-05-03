<?php

declare(strict_types=1);

namespace Phpdftk\Pdf\Core\Tests\Document;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Phpdftk\Pdf\Core\Document\Destination;
use Phpdftk\Pdf\Core\Document\MarkInfo;
use Phpdftk\Pdf\Core\Document\OCG;
use Phpdftk\Pdf\Core\Document\OutputIntent;
use Phpdftk\Pdf\Core\Document\StructElem;
use Phpdftk\Pdf\Core\Document\StructTreeRoot;
use Phpdftk\Pdf\Core\Font\StandardFont;
use Phpdftk\Pdf\Core\Font\TrueTypeFont;
use Phpdftk\Pdf\Core\Font\Type1Font;
use Phpdftk\Pdf\Core\PdfArray;
use Phpdftk\Pdf\Core\PdfDictionary;
use Phpdftk\Pdf\Core\PdfNumber;
use Phpdftk\Pdf\Core\PdfReference;
use Phpdftk\Pdf\Core\PdfString;
use Phpdftk\Pdf\Writer\PdfWriter;
use Phpdftk\Tests\Support\Arlington\ArlingtonValidationTrait;
use Phpdftk\Tests\Support\QpdfValidationTrait;

/**
 * Generates a PDF exercising document-level features: OutputIntent, page boxes,
 * named destinations, OCG, tagged PDF structure, and MarkInfo.
 */
#[Group("qpdf")]
#[Group("arlington")]
class DocumentFeaturesTest extends TestCase
{
    use ArlingtonValidationTrait;
    use QpdfValidationTrait;
    private const OUTPUT_FILE = __DIR__ . '/../../../../../docs/sample-pdfs/document_features.pdf';

    public function testGeneratesDocumentFeaturesPdf(): void
    {
        $writer = new PdfWriter();
        $fontName = $writer->addFont(new Type1Font(StandardFont::Helvetica))->getResourceName();

        // ----------------------------------------------------------------
        // OutputIntent for PDF/X
        // ----------------------------------------------------------------
        $outputIntent = new OutputIntent('GTS_PDFX', 'CGATS TR 001');
        $outputIntent->registryName = new PdfString('http://www.color.org');
        $outputIntent->info = new PdfString('sRGB IEC61966-2.1');
        $writer->register($outputIntent);
        $writer->getCatalog()->outputIntents = new PdfArray([
            new PdfReference($outputIntent->objectNumber),
        ]);

        // ----------------------------------------------------------------
        // Page 1 — with extra page boxes
        // ----------------------------------------------------------------
        $page1 = $writer->addPage(612, 792);
        $page1->corePage()->cropBox = new PdfArray([
            new PdfNumber(18), new PdfNumber(18), new PdfNumber(594), new PdfNumber(774),
        ]);
        $page1->corePage()->bleedBox = new PdfArray([
            new PdfNumber(9), new PdfNumber(9), new PdfNumber(603), new PdfNumber(783),
        ]);
        $page1->corePage()->trimBox = new PdfArray([
            new PdfNumber(24), new PdfNumber(24), new PdfNumber(588), new PdfNumber(768),
        ]);

        $cs1 = $writer->addContentStream($page1);
        $cs1->beginText()
            ->setFont($fontName, 16)
            ->moveTextPosition(72, 720)
            ->showText('Document Features - Page 1: Page Boxes & OutputIntent')
            ->endText();

        // ----------------------------------------------------------------
        // Pages 2 and 3 — simple text
        // ----------------------------------------------------------------
        $page2 = $writer->addPage(612, 792);
        $cs2 = $writer->addContentStream($page2);
        $cs2->beginText()
            ->setFont($fontName, 16)
            ->moveTextPosition(72, 720)
            ->showText('Document Features - Page 2: Named Destinations')
            ->endText();

        $page3 = $writer->addPage(612, 792);
        $cs3 = $writer->addContentStream($page3);
        $cs3->beginText()
            ->setFont($fontName, 16)
            ->moveTextPosition(72, 720)
            ->showText('Document Features - Page 3: Tagged PDF')
            ->endText();

        // ----------------------------------------------------------------
        // Named destinations
        // ----------------------------------------------------------------
        $writer->setNamedDestinations([
            'intro' => Destination::fit(new PdfReference($page1->corePage()->objectNumber)),
            'chapter1' => Destination::xyz(new PdfReference($page2->corePage()->objectNumber), 72, 720, 1.0),
            'chapter2' => Destination::fitH(new PdfReference($page3->corePage()->objectNumber), 500),
        ]);

        // ----------------------------------------------------------------
        // OCG (Optional Content Group)
        // ----------------------------------------------------------------
        $ocg = new OCG('Layer 1');
        $writer->register($ocg);

        // ----------------------------------------------------------------
        // StructTreeRoot and StructElem (tagged PDF)
        // ----------------------------------------------------------------
        $structRoot = new StructTreeRoot();
        $structRoot->roleMap = new PdfDictionary();
        $writer->register($structRoot);

        $structElem = new StructElem('Document');
        $structElem->p = new PdfReference($structRoot->objectNumber);
        $writer->register($structElem);

        $structRoot->k = new PdfReference($structElem->objectNumber);

        $writer->getCatalog()->structTreeRoot = new PdfReference($structRoot->objectNumber);

        // ----------------------------------------------------------------
        // MarkInfo on catalog
        // ----------------------------------------------------------------
        $markInfo = new MarkInfo();
        $markInfo->marked = true;
        $writer->getCatalog()->markInfo = $markInfo;

        // ----------------------------------------------------------------
        // Save and validate
        // ----------------------------------------------------------------
        $writer->save(self::OUTPUT_FILE);

        self::assertFileExists(self::OUTPUT_FILE);
        $this->assertQpdfValid(self::OUTPUT_FILE);
        $this->assertArlingtonValid(self::OUTPUT_FILE);

        $content = file_get_contents(self::OUTPUT_FILE);
        self::assertNotFalse($content);
        self::assertStringStartsWith('%PDF-', $content);
        self::assertStringContainsString('/OutputConditionIdentifier', $content);
        self::assertStringContainsString('/CropBox', $content);
        self::assertStringContainsString('/BleedBox', $content);
        self::assertStringContainsString('/TrimBox', $content);
        self::assertStringContainsString('intro', $content);
        self::assertStringContainsString('/Type /OCG', $content);
        self::assertStringContainsString('/Type /StructTreeRoot', $content);
        self::assertStringContainsString('/Type /StructElem', $content);
        self::assertStringContainsString('/Marked true', $content);
        self::assertStringContainsString('%%EOF', $content);
    }

    public function testGeneratesPdfWithEmbeddedTrueTypeFont(): void
    {
        $fontPath = $this->findFont();
        if ($fontPath === null) {
            $this->markTestSkipped('No TTF font found on this system');
        }

        $writer = new PdfWriter();
        $font = TrueTypeFont::fromFile($fontPath);

        $pages = [];
        $sizes = [12, 18, 24];
        for ($i = 0; $i < 3; $i++) {
            $pages[$i] = $writer->addPage(612, 792);
        }

        $fontResourceName = $writer->addFont($font)->getResourceName();

        for ($i = 0; $i < 3; $i++) {
            $cs = $writer->addContentStream($pages[$i]);
            $cs->beginText()
                ->setFont($fontResourceName, $sizes[$i])
                ->moveTextPosition(72, 720)
                ->showText(sprintf('Embedded TrueType Font - Page %d at %dpt', $i + 1, $sizes[$i]))
                ->moveTextPosition(0, -30)
                ->showText('The quick brown fox jumps over the lazy dog.')
                ->moveTextPosition(0, -30)
                ->showText('ABCDEFGHIJKLMNOPQRSTUVWXYZ 0123456789')
                ->endText();
        }

        $outPath = __DIR__ . '/../../../../../docs/sample-pdfs/embedded_truetype_multi.pdf';
        $writer->save($outPath);

        self::assertFileExists($outPath);
        $this->assertQpdfValid($outPath);
        $this->assertArlingtonValid($outPath);

        $content = file_get_contents($outPath);
        self::assertNotFalse($content);
        self::assertStringStartsWith('%PDF-', $content);
        self::assertStringContainsString('/FontFile2', $content);
        self::assertStringContainsString('/ToUnicode', $content);
        self::assertStringContainsString('/FontDescriptor', $content);
    }

    private function findFont(): ?string
    {
        foreach ([
            '/System/Library/Fonts/Supplemental/Arial.ttf',
            '/System/Library/Fonts/Supplemental/Georgia.ttf',
            '/System/Library/Fonts/Supplemental/Verdana.ttf',
            '/usr/share/fonts/truetype/liberation/LiberationSans-Regular.ttf',
            '/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf',
        ] as $path) {
            if (file_exists($path)) {
                return $path;
            }
        }
        return null;
    }
}
