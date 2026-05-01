<?php

declare(strict_types=1);

namespace ApprLabs\Pdf\Conformance\Tests\Integration;

use ApprLabs\Pdf\Conformance\ConformanceException;
use ApprLabs\Pdf\Conformance\Profile\PdfXProfile;
use ApprLabs\Pdf\Core\Document\Info;
use ApprLabs\Pdf\Core\Document\OutputIntent;
use ApprLabs\Pdf\Core\Font\TrueTypeFont;
use ApprLabs\Pdf\Core\PdfArray;
use ApprLabs\Pdf\Core\PdfDictionary;
use ApprLabs\Pdf\Core\PdfName;
use ApprLabs\Pdf\Core\PdfNumber;
use ApprLabs\Pdf\Core\PdfStream;
use ApprLabs\Pdf\Core\PdfString;
use ApprLabs\Pdf\Writer\PdfWriter;
use PHPUnit\Framework\TestCase;

class PdfXIntegrationTest extends TestCase
{
    private const OUTPUT_DIR = __DIR__ . '/../../tests/output';

    protected function setUp(): void
    {
        if (!is_dir(self::OUTPUT_DIR)) {
            mkdir(self::OUTPUT_DIR, 0o755, true);
        }
    }

    private function findFont(): string
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
        $this->markTestSkipped('No TTF font found on this system');
    }

    private function findIccProfile(): string
    {
        foreach ([
            '/System/Library/ColorSync/Profiles/sRGB Profile.icc',
            '/usr/share/color/icc/colord/sRGB.icc',
            '/usr/share/color/icc/sRGB.icc',
        ] as $path) {
            if (file_exists($path)) {
                return $path;
            }
        }
        $this->markTestSkipped('No sRGB ICC profile found on this system');
    }

    private function addPdfXOutputIntent(PdfWriter $writer): void
    {
        $iccData = file_get_contents($this->findIccProfile());
        $iccStream = new PdfStream(new PdfDictionary(), $iccData);
        $iccStream->dictionary->set('N', new PdfNumber(3));
        $iccRef = $writer->register($iccStream);

        $oi = new OutputIntent('GTS_PDFX', 'CGATS TR 001');
        $oi->registryName = new PdfString('http://www.color.org');
        $oi->info = new PdfString('CGATS TR 001');
        $oi->destOutputProfile = $iccRef;
        $oiRef = $writer->register($oi);
        $writer->getCatalog()->outputIntents = new PdfArray([$oiRef]);
    }

    private function makeRect(): PdfArray
    {
        return new PdfArray([
            new PdfNumber(0), new PdfNumber(0),
            new PdfNumber(612), new PdfNumber(792),
        ]);
    }

    // -----------------------------------------------------------------------
    // Happy path
    // -----------------------------------------------------------------------

    /**
     * Compliant PDF/X-4 document generates successfully.
     */
    public function testCompliantPdfX4(): void
    {
        $writer = new PdfWriter();
        $writer->setConformance(PdfXProfile::X4);

        $info = new Info();
        $info->title = new PdfString('PDF/X-4 Test');
        $info->producer = new PdfString('phpdftk');
        $info->trapped = new PdfName('False');
        $writer->setInfo($info);

        $this->addPdfXOutputIntent($writer);

        $page = $writer->addPage(612, 792);
        $page->corePage()->trimBox = $this->makeRect();

        $font = TrueTypeFont::fromFile($this->findFont());
        $fontHandle = $writer->addFont($font, $page);

        $cs = $writer->addContentStream($page);
        $cs->beginText()
            ->setFont($fontHandle->getResourceName(), 12)
            ->moveTextPosition(72, 720)
            ->showText('PDF/X-4 Conformant Document')
            ->endText();

        $outPath = self::OUTPUT_DIR . '/pdfx4_compliant.pdf';
        $writer->save($outPath);

        self::assertFileExists($outPath);
        self::assertStringStartsWith('%PDF', file_get_contents($outPath));

        $results = $writer->getConformanceResults();
        self::assertTrue($results[0]->isCompliant);
    }

    /**
     * PDF/X-4 auto-injects pdfxid XMP identification.
     */
    public function testAutoInjectsPdfxidXmp(): void
    {
        $writer = new PdfWriter();
        $writer->setConformance(PdfXProfile::X4, strict: false);

        $info = new Info();
        $info->title = new PdfString('XMP test');
        $info->producer = new PdfString('phpdftk');
        $info->trapped = new PdfName('False');
        $writer->setInfo($info);

        $page = $writer->addPage(612, 792);
        $font = TrueTypeFont::fromFile($this->findFont());
        $writer->addFont($font, $page);

        $pdf = $writer->generate();

        self::assertStringContainsString('pdfxid:GTS_PDFXVersion', $pdf);
        self::assertStringContainsString('PDF/X-4', $pdf);
    }

    /**
     * PDF/X-1a:2003 with Trapped=True passes.
     */
    public function testX1aWithTrappedTruePasses(): void
    {
        $writer = new PdfWriter();
        $writer->setConformance(PdfXProfile::X1a2003);

        $info = new Info();
        $info->title = new PdfString('PDF/X-1a Test');
        $info->producer = new PdfString('phpdftk');
        $info->trapped = new PdfName('True');
        $writer->setInfo($info);

        $this->addPdfXOutputIntent($writer);

        $page = $writer->addPage(612, 792);
        $page->corePage()->trimBox = $this->makeRect();

        $font = TrueTypeFont::fromFile($this->findFont());
        $fontHandle = $writer->addFont($font, $page);

        $cs = $writer->addContentStream($page);
        $cs->beginText()
            ->setFont($fontHandle->getResourceName(), 12)
            ->moveTextPosition(72, 720)
            ->showText('PDF/X-1a:2003')
            ->endText();

        $outPath = self::OUTPUT_DIR . '/pdfx1a_compliant.pdf';
        $writer->save($outPath);

        self::assertFileExists($outPath);
        self::assertTrue($writer->getConformanceResults()[0]->isCompliant);
    }

    // -----------------------------------------------------------------------
    // Sad path
    // -----------------------------------------------------------------------

    /**
     * Missing OutputIntent throws in strict mode.
     */
    public function testMissingOutputIntentThrows(): void
    {
        $writer = new PdfWriter();
        $writer->setConformance(PdfXProfile::X4, strict: true);

        $info = new Info();
        $info->trapped = new PdfName('False');
        $writer->setInfo($info);

        $page = $writer->addPage(612, 792);
        $page->corePage()->trimBox = $this->makeRect();

        $font = TrueTypeFont::fromFile($this->findFont());
        $writer->addFont($font, $page);

        $this->expectException(ConformanceException::class);
        $writer->generate();
    }

    /**
     * Missing TrimBox fails.
     */
    public function testMissingTrimBoxFails(): void
    {
        $writer = new PdfWriter();
        $writer->setConformance(PdfXProfile::X4, strict: false);

        $info = new Info();
        $info->title = new PdfString('No TrimBox');
        $info->producer = new PdfString('phpdftk');
        $info->trapped = new PdfName('False');
        $writer->setInfo($info);

        $this->addPdfXOutputIntent($writer);

        $page = $writer->addPage(612, 792);
        // No TrimBox set

        $font = TrueTypeFont::fromFile($this->findFont());
        $writer->addFont($font, $page);

        $writer->generate();

        $results = $writer->getConformanceResults();
        self::assertFalse($results[0]->isCompliant);

        $clauses = array_map(fn($v) => $v->clause, $results[0]->getErrors());
        self::assertContains('6.2', $clauses);
    }

    /**
     * Missing /Trapped fails.
     */
    public function testMissingTrappedFails(): void
    {
        $writer = new PdfWriter();
        $writer->setConformance(PdfXProfile::X1a2003, strict: false);

        $info = new Info();
        $info->title = new PdfString('No Trapped');
        $info->producer = new PdfString('phpdftk');
        // No trapped set
        $writer->setInfo($info);

        $this->addPdfXOutputIntent($writer);

        $page = $writer->addPage(612, 792);
        $page->corePage()->trimBox = $this->makeRect();

        $font = TrueTypeFont::fromFile($this->findFont());
        $writer->addFont($font, $page);

        $writer->generate();

        $clauses = array_map(
            fn($v) => $v->clause,
            $writer->getConformanceResults()[0]->getErrors(),
        );
        self::assertContains('6.3', $clauses);
    }

    /**
     * Trapped=Unknown fails.
     */
    public function testTrappedUnknownFails(): void
    {
        $writer = new PdfWriter();
        $writer->setConformance(PdfXProfile::X4, strict: false);

        $info = new Info();
        $info->title = new PdfString('Trapped Unknown');
        $info->producer = new PdfString('phpdftk');
        $info->trapped = new PdfName('Unknown');
        $writer->setInfo($info);

        $this->addPdfXOutputIntent($writer);

        $page = $writer->addPage(612, 792);
        $page->corePage()->trimBox = $this->makeRect();

        $font = TrueTypeFont::fromFile($this->findFont());
        $writer->addFont($font, $page);

        $writer->generate();

        self::assertFalse($writer->getConformanceResults()[0]->isCompliant);
    }

    /**
     * checkConformance() works before generate() for PDF/X.
     */
    public function testCheckConformanceBeforeGenerate(): void
    {
        $writer = new PdfWriter();
        $writer->setConformance(PdfXProfile::X4);

        $page = $writer->addPage(612, 792);
        $font = TrueTypeFont::fromFile($this->findFont());
        $writer->addFont($font, $page);

        $results = $writer->checkConformance();
        self::assertCount(1, $results);
        self::assertFalse($results[0]->isCompliant);
    }

    /**
     * PDF/X-1a pins version correctly (1.3 minimum, but version gating may bump).
     */
    public function testX1aXmpIdentification(): void
    {
        $writer = new PdfWriter();
        $writer->setConformance(PdfXProfile::X1a2003, strict: false);

        $info = new Info();
        $info->title = new PdfString('X-1a XMP');
        $info->producer = new PdfString('phpdftk');
        $info->trapped = new PdfName('False');
        $writer->setInfo($info);

        $page = $writer->addPage(612, 792);
        $font = TrueTypeFont::fromFile($this->findFont());
        $writer->addFont($font, $page);

        $pdf = $writer->generate();

        self::assertStringContainsString('PDF/X-1a:2003', $pdf);
    }
}
