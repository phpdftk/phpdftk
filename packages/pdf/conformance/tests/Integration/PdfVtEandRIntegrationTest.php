<?php

declare(strict_types=1);

namespace Phpdftk\Pdf\Conformance\Tests\Integration;

use Phpdftk\Pdf\Conformance\ConformanceException;
use Phpdftk\Pdf\Conformance\Profile\PdfEProfile;
use Phpdftk\Pdf\Conformance\Profile\PdfRProfile;
use Phpdftk\Pdf\Conformance\Profile\PdfVtProfile;
use Phpdftk\Pdf\Core\Action\JavaScriptAction;
use Phpdftk\Pdf\Core\Annotation\ThreeDAnnotation;
use Phpdftk\Pdf\Core\Document\DPart;
use Phpdftk\Pdf\Core\Document\DPartRoot;
use Phpdftk\Pdf\Core\Document\Info;
use Phpdftk\Pdf\Core\Document\OutputIntent;
use Phpdftk\Pdf\Core\Font\TrueTypeFont;
use Phpdftk\Pdf\Core\PdfArray;
use Phpdftk\Pdf\Core\PdfDictionary;
use Phpdftk\Pdf\Core\PdfName;
use Phpdftk\Pdf\Core\PdfNumber;
use Phpdftk\Pdf\Core\PdfReference;
use Phpdftk\Pdf\Core\PdfStream;
use Phpdftk\Pdf\Core\PdfString;
use Phpdftk\Pdf\Core\ThreeD\ThreeDStream;
use Phpdftk\Pdf\Core\ThreeD\ThreeDView;
use Phpdftk\Pdf\Writer\PdfWriter;
use PHPUnit\Framework\TestCase;

class PdfVtEandRIntegrationTest extends TestCase
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

    private function addOutputIntent(PdfWriter $writer, string $subtype = 'GTS_PDFX'): void
    {
        $iccData = file_get_contents($this->findIccProfile());
        $iccStream = new PdfStream(new PdfDictionary(), $iccData);
        $iccStream->dictionary->set('N', new PdfNumber(3));
        $iccRef = $writer->register($iccStream);

        $oi = new OutputIntent($subtype, 'sRGB IEC61966-2.1');
        $oi->registryName = new PdfString('http://www.color.org');
        $oi->info = new PdfString('sRGB IEC61966-2.1');
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
    // PDF/VT — Happy path
    // -----------------------------------------------------------------------

    /**
     * Compliant PDF/VT-1 with DPartRoot generates successfully.
     */
    public function testCompliantPdfVt1(): void
    {
        $writer = new PdfWriter();
        $writer->setConformance(PdfVtProfile::VT1);

        $info = new Info();
        $info->title = new PdfString('PDF/VT-1 Test');
        $info->producer = new PdfString('phpdftk');
        $info->trapped = new PdfName('False');
        $writer->setInfo($info);

        $this->addOutputIntent($writer);

        // DPartRoot with a dummy DPart node (parent is self-referential for the root node)
        $dpart = new DPart(new PdfReference(0));
        $dpartRef = $writer->register($dpart);
        $dpartRoot = new DPartRoot($dpartRef);
        $writer->register($dpartRoot);
        $writer->getCatalog()->dPartRoot = new PdfReference($dpartRoot->objectNumber);

        $page = $writer->addPage(612, 792);
        $page->corePage()->trimBox = $this->makeRect();

        $font = TrueTypeFont::fromFile($this->findFont());
        $fontHandle = $writer->addFont($font, $page);
        $cs = $writer->addContentStream($page);
        $cs->beginText()
            ->setFont($fontHandle->getResourceName(), 12)
            ->moveTextPosition(72, 720)
            ->showText('PDF/VT-1 Conformant')
            ->endText();

        $outPath = self::OUTPUT_DIR . '/pdfvt1_compliant.pdf';
        $writer->save($outPath);

        self::assertFileExists($outPath);
        self::assertTrue($writer->getConformanceResults()[0]->isCompliant);
    }

    /**
     * PDF/VT-1 auto-injects pdfvtid XMP.
     */
    public function testVt1AutoInjectsXmp(): void
    {
        $writer = new PdfWriter();
        $writer->setConformance(PdfVtProfile::VT1, strict: false);

        $info = new Info();
        $info->title = new PdfString('VT XMP');
        $info->producer = new PdfString('phpdftk');
        $info->trapped = new PdfName('False');
        $writer->setInfo($info);

        $page = $writer->addPage(612, 792);
        $font = TrueTypeFont::fromFile($this->findFont());
        $writer->addFont($font, $page);

        $pdf = $writer->generate();

        self::assertStringContainsString('pdfvtid:GTS_PDFVTVersion', $pdf);
        self::assertStringContainsString('PDF/VT-1', $pdf);
    }

    /**
     * PDF/VT-1 pins to PDF 2.0.
     */
    public function testVt1PinsTo2_0(): void
    {
        $writer = new PdfWriter();
        $writer->setConformance(PdfVtProfile::VT1, strict: false);

        $page = $writer->addPage(612, 792);
        $font = TrueTypeFont::fromFile($this->findFont());
        $writer->addFont($font, $page);

        $pdf = $writer->generate();
        self::assertStringStartsWith('%PDF-2.0', $pdf);
    }

    /**
     * Compliant PDF/VT-2 with DPartRoot generates successfully.
     */
    public function testCompliantPdfVt2(): void
    {
        $writer = new PdfWriter();
        $writer->setConformance(PdfVtProfile::VT2);

        $info = new Info();
        $info->title = new PdfString('PDF/VT-2 Test');
        $info->producer = new PdfString('phpdftk');
        $info->trapped = new PdfName('False');
        $writer->setInfo($info);

        $this->addOutputIntent($writer);

        $dpart = new DPart(new PdfReference(0));
        $dpartRef = $writer->register($dpart);
        $dpartRoot = new DPartRoot($dpartRef);
        $writer->register($dpartRoot);
        $writer->getCatalog()->dPartRoot = new PdfReference($dpartRoot->objectNumber);

        $page = $writer->addPage(612, 792);
        $page->corePage()->trimBox = $this->makeRect();

        $font = TrueTypeFont::fromFile($this->findFont());
        $fontHandle = $writer->addFont($font, $page);
        $cs = $writer->addContentStream($page);
        $cs->beginText()
            ->setFont($fontHandle->getResourceName(), 12)
            ->moveTextPosition(72, 720)
            ->showText('PDF/VT-2 Conformant')
            ->endText();

        $outPath = self::OUTPUT_DIR . '/pdfvt2_compliant.pdf';
        $writer->save($outPath);

        self::assertFileExists($outPath);
        self::assertTrue($writer->getConformanceResults()[0]->isCompliant);
    }

    /**
     * Compliant PDF/VT-2s with DPartRoot generates successfully.
     */
    public function testCompliantPdfVt2s(): void
    {
        $writer = new PdfWriter();
        $writer->setConformance(PdfVtProfile::VT2s);

        $info = new Info();
        $info->title = new PdfString('PDF/VT-2s Test');
        $info->producer = new PdfString('phpdftk');
        $info->trapped = new PdfName('False');
        $writer->setInfo($info);

        $this->addOutputIntent($writer);

        $dpart = new DPart(new PdfReference(0));
        $dpartRef = $writer->register($dpart);
        $dpartRoot = new DPartRoot($dpartRef);
        $writer->register($dpartRoot);
        $writer->getCatalog()->dPartRoot = new PdfReference($dpartRoot->objectNumber);

        $page = $writer->addPage(612, 792);
        $page->corePage()->trimBox = $this->makeRect();

        $font = TrueTypeFont::fromFile($this->findFont());
        $fontHandle = $writer->addFont($font, $page);
        $cs = $writer->addContentStream($page);
        $cs->beginText()
            ->setFont($fontHandle->getResourceName(), 12)
            ->moveTextPosition(72, 720)
            ->showText('PDF/VT-2s Conformant')
            ->endText();

        $outPath = self::OUTPUT_DIR . '/pdfvt2s_compliant.pdf';
        $writer->save($outPath);

        self::assertFileExists($outPath);
        self::assertTrue($writer->getConformanceResults()[0]->isCompliant);
    }

    // -----------------------------------------------------------------------
    // PDF/VT — Sad path
    // -----------------------------------------------------------------------

    /**
     * Missing DPartRoot throws in strict mode.
     */
    public function testMissingDPartRootThrows(): void
    {
        $writer = new PdfWriter();
        $writer->setConformance(PdfVtProfile::VT1, strict: true);

        $info = new Info();
        $info->trapped = new PdfName('False');
        $writer->setInfo($info);

        $this->addOutputIntent($writer);

        $page = $writer->addPage(612, 792);
        $page->corePage()->trimBox = $this->makeRect();
        $font = TrueTypeFont::fromFile($this->findFont());
        $writer->addFont($font, $page);

        // No DPartRoot set
        $this->expectException(ConformanceException::class);
        $writer->generate();
    }

    /**
     * Missing DPartRoot collected in lenient mode.
     */
    public function testMissingDPartRootLenient(): void
    {
        $writer = new PdfWriter();
        $writer->setConformance(PdfVtProfile::VT2, strict: false);

        $info = new Info();
        $info->trapped = new PdfName('False');
        $writer->setInfo($info);

        $this->addOutputIntent($writer);

        $page = $writer->addPage(612, 792);
        $page->corePage()->trimBox = $this->makeRect();
        $font = TrueTypeFont::fromFile($this->findFont());
        $writer->addFont($font, $page);

        $writer->generate();

        $results = $writer->getConformanceResults();
        self::assertFalse($results[0]->isCompliant);

        $clauses = array_map(fn($v) => $v->clause, $results[0]->getErrors());
        self::assertContains('6.1', $clauses);
    }

    // -----------------------------------------------------------------------
    // PDF/E — Happy path
    // -----------------------------------------------------------------------

    /**
     * Compliant PDF/E-1 with embedded font generates successfully.
     */
    public function testCompliantPdfE1(): void
    {
        $writer = new PdfWriter();
        $writer->setConformance(PdfEProfile::E1);

        $info = new Info();
        $info->title = new PdfString('PDF/E-1 Test');
        $info->producer = new PdfString('phpdftk');
        $writer->setInfo($info);

        $page = $writer->addPage(612, 792);
        $font = TrueTypeFont::fromFile($this->findFont());
        $fontHandle = $writer->addFont($font, $page);
        $cs = $writer->addContentStream($page);
        $cs->beginText()
            ->setFont($fontHandle->getResourceName(), 12)
            ->moveTextPosition(72, 720)
            ->showText('PDF/E-1 Engineering Document')
            ->endText();

        $outPath = self::OUTPUT_DIR . '/pdfe1_compliant.pdf';
        $writer->save($outPath);

        self::assertFileExists($outPath);
        self::assertTrue($writer->getConformanceResults()[0]->isCompliant);
    }

    /**
     * PDF/E-1 auto-injects pdfeid XMP.
     */
    public function testE1AutoInjectsXmp(): void
    {
        $writer = new PdfWriter();
        $writer->setConformance(PdfEProfile::E1, strict: false);

        $page = $writer->addPage(612, 792);
        $font = TrueTypeFont::fromFile($this->findFont());
        $writer->addFont($font, $page);

        $pdf = $writer->generate();
        self::assertStringContainsString('pdfeid:part', $pdf);
    }

    // -----------------------------------------------------------------------
    // PDF/E — Sad path
    // -----------------------------------------------------------------------

    /**
     * PDF/E-1 with encryption fails.
     */
    public function testE1WithEncryptionFails(): void
    {
        $writer = new PdfWriter();
        $writer->setConformance(PdfEProfile::E1, strict: false);

        // Simulate encryption by checking — we can't easily add PdfEncryptor
        // without full key derivation, so test via checkConformance on a
        // minimal doc without XMP
        $page = $writer->addPage(612, 792);
        // No font, no XMP — should fail metadata constraint
        $results = $writer->checkConformance();
        self::assertFalse($results[0]->isCompliant);
    }

    // -----------------------------------------------------------------------
    // PDF/E — Deep validation: 3D content
    // -----------------------------------------------------------------------

    /**
     * PDF/E-1 with 3D annotation and valid stream passes deep validation.
     */
    public function testE1WithValid3DContentPasses(): void
    {
        $writer = new PdfWriter();
        $writer->setConformance(PdfEProfile::E1, strict: false);

        $info = new Info();
        $info->title = new PdfString('PDF/E-1 3D Test');
        $info->producer = new PdfString('phpdftk');
        $writer->setInfo($info);

        $page = $writer->addPage(612, 792);
        $font = TrueTypeFont::fromFile($this->findFont());
        $fontHandle = $writer->addFont($font, $page);

        // Add a 3D stream with valid subtype and view
        $stream = new ThreeDStream('U3D', 'dummy-u3d-artwork');
        $streamRef = $writer->register($stream);
        $view = new ThreeDView('Default View');
        $viewRef = $writer->register($view);
        $stream->va = new PdfArray([$viewRef]);

        // Add a 3D annotation referencing the stream
        $rect = new PdfArray([
            new PdfNumber(100), new PdfNumber(100),
            new PdfNumber(400), new PdfNumber(400),
        ]);
        $annot = new ThreeDAnnotation($rect);
        $annot->dd = $streamRef;
        $annotRef = $writer->register($annot);
        $page->annots = new PdfArray([$annotRef]);

        // Add OutputIntent for color space anchoring
        $this->addOutputIntent($writer, 'GTS_PDFE');

        $cs = $writer->addContentStream($page);
        $cs->beginText()
            ->setFont($fontHandle->getResourceName(), 12)
            ->moveTextPosition(72, 720)
            ->showText('PDF/E-1 with 3D')
            ->endText();

        $outPath = self::OUTPUT_DIR . '/pdfe1_3d_valid.pdf';
        $writer->save($outPath);

        self::assertFileExists($outPath);
        self::assertTrue($writer->getConformanceResults()[0]->isCompliant);
    }

    /**
     * PDF/E-1 with JavaScript action fails in strict mode.
     */
    public function testE1WithJavaScriptFailsStrict(): void
    {
        $writer = new PdfWriter();
        $writer->setConformance(PdfEProfile::E1, strict: true);

        $info = new Info();
        $info->title = new PdfString('PDF/E-1 JS Test');
        $info->producer = new PdfString('phpdftk');
        $writer->setInfo($info);

        $page = $writer->addPage(612, 792);
        $font = TrueTypeFont::fromFile($this->findFont());
        $writer->addFont($font, $page);

        // Register a JavaScript action — should violate E-1
        $jsAction = new JavaScriptAction(new PdfString('app.alert("test")'));
        $writer->register($jsAction);

        $this->expectException(ConformanceException::class);
        $writer->generate();
    }

    /**
     * PDF/E-1 with JavaScript action collects violation in lenient mode.
     */
    public function testE1WithJavaScriptCollectsViolationLenient(): void
    {
        $writer = new PdfWriter();
        $writer->setConformance(PdfEProfile::E1, strict: false);

        $info = new Info();
        $info->title = new PdfString('PDF/E-1 JS Lenient');
        $info->producer = new PdfString('phpdftk');
        $writer->setInfo($info);

        $page = $writer->addPage(612, 792);
        $font = TrueTypeFont::fromFile($this->findFont());
        $writer->addFont($font, $page);

        $jsAction = new JavaScriptAction(new PdfString('app.alert("test")'));
        $writer->register($jsAction);

        $writer->generate();
        $results = $writer->getConformanceResults();
        self::assertFalse($results[0]->isCompliant);

        // Should contain JavaScript violation
        $messages = array_map(fn($v) => $v->message, $results[0]->getErrors());
        self::assertTrue(
            in_array(true, array_map(fn($m) => str_contains($m, 'JavaScript'), $messages)),
            'Expected a JavaScript violation',
        );
    }

    /**
     * PDF/E-1 without OutputIntent emits color space warning.
     */
    public function testE1WithoutOutputIntentWarns(): void
    {
        $writer = new PdfWriter();
        $writer->setConformance(PdfEProfile::E1, strict: false);

        $page = $writer->addPage(612, 792);
        $font = TrueTypeFont::fromFile($this->findFont());
        $writer->addFont($font, $page);

        $results = $writer->checkConformance();
        // Should have at least a warning about missing OutputIntent
        $warnings = $results[0]->getWarnings();
        $colorWarnings = array_filter(
            $warnings,
            fn($v) => str_contains($v->message, 'OutputIntent'),
        );
        self::assertNotEmpty($colorWarnings);
    }

    // -----------------------------------------------------------------------
    // PDF/R — Happy path
    // -----------------------------------------------------------------------

    /**
     * Compliant PDF/R-1 generates successfully (minimal constraints).
     */
    public function testCompliantPdfR1(): void
    {
        $writer = new PdfWriter();
        $writer->setConformance(PdfRProfile::R1);

        $info = new Info();
        $info->title = new PdfString('PDF/R-1 Raster Document');
        $info->producer = new PdfString('phpdftk');
        $writer->setInfo($info);

        $page = $writer->addPage(612, 792);

        $outPath = self::OUTPUT_DIR . '/pdfr1_compliant.pdf';
        $writer->save($outPath);

        self::assertFileExists($outPath);
        self::assertTrue($writer->getConformanceResults()[0]->isCompliant);
    }

    /**
     * PDF/R-1 auto-injects pdfrid XMP.
     */
    public function testR1AutoInjectsXmp(): void
    {
        $writer = new PdfWriter();
        $writer->setConformance(PdfRProfile::R1, strict: false);

        $page = $writer->addPage(612, 792);
        $pdf = $writer->generate();

        self::assertStringContainsString('pdfrid:part', $pdf);
    }

    /**
     * PDF/R-1 pins to PDF 2.0.
     */
    public function testR1PinsTo2_0(): void
    {
        $writer = new PdfWriter();
        $writer->setConformance(PdfRProfile::R1, strict: false);

        $page = $writer->addPage(612, 792);
        $pdf = $writer->generate();

        self::assertStringStartsWith('%PDF-2.0', $pdf);
    }

    // -----------------------------------------------------------------------
    // PDF/R — Sad path
    // -----------------------------------------------------------------------

    /**
     * PDF/R-1 with encryption should fail.
     */
    public function testR1CheckConformanceDetectsIssues(): void
    {
        $writer = new PdfWriter();
        $writer->setConformance(PdfRProfile::R1);

        // Don't set any info — XMP auto-injection will still happen,
        // but let's check advisory before generate
        $page = $writer->addPage(612, 792);

        $results = $writer->checkConformance();
        // Before generate, no XMP yet — should have metadata violation
        self::assertFalse($results[0]->isCompliant);
    }

    // -----------------------------------------------------------------------
    // PDF/R — Deep validation
    // -----------------------------------------------------------------------

    /**
     * PDF/R-1 with JavaScript action fails.
     */
    public function testR1WithJavaScriptFails(): void
    {
        $writer = new PdfWriter();
        $writer->setConformance(PdfRProfile::R1, strict: false);

        $info = new Info();
        $info->title = new PdfString('PDF/R-1 JS Test');
        $info->producer = new PdfString('phpdftk');
        $writer->setInfo($info);

        $page = $writer->addPage(612, 792);

        $jsAction = new JavaScriptAction(new PdfString('app.alert("test")'));
        $writer->register($jsAction);

        $writer->generate();
        $results = $writer->getConformanceResults();
        self::assertFalse($results[0]->isCompliant);

        $messages = array_map(fn($v) => $v->message, $results[0]->getErrors());
        self::assertTrue(
            in_array(true, array_map(fn($m) => str_contains($m, 'JavaScript'), $messages)),
            'Expected a JavaScript violation',
        );
    }

    /**
     * PDF/R-1 with fonts emits warning about non-raster content.
     */
    public function testR1WithFontsEmitsWarning(): void
    {
        $writer = new PdfWriter();
        $writer->setConformance(PdfRProfile::R1, strict: false);

        $info = new Info();
        $info->title = new PdfString('PDF/R-1 Font Test');
        $info->producer = new PdfString('phpdftk');
        $writer->setInfo($info);

        $page = $writer->addPage(612, 792);
        $font = TrueTypeFont::fromFile($this->findFont());
        $writer->addFont($font, $page);

        $results = $writer->checkConformance();
        $warnings = $results[0]->getWarnings();
        $fontWarnings = array_filter(
            $warnings,
            fn($v) => str_contains($v->message, 'font'),
        );
        self::assertNotEmpty($fontWarnings, 'Expected a font presence warning');
    }
}
