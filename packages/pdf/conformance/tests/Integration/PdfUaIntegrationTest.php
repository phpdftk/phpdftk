<?php

declare(strict_types=1);

namespace ApprLabs\Pdf\Conformance\Tests\Integration;

use ApprLabs\Pdf\Conformance\ConformanceException;
use ApprLabs\Pdf\Conformance\Profile\PdfAProfile;
use ApprLabs\Pdf\Conformance\Profile\PdfUaProfile;
use ApprLabs\Pdf\Core\Annotation\TextAnnotation;
use ApprLabs\Pdf\Core\Document\Info;
use ApprLabs\Pdf\Core\Document\MarkInfo;
use ApprLabs\Pdf\Core\Document\StructTreeRoot;
use ApprLabs\Pdf\Core\Document\ViewerPreferences;
use ApprLabs\Pdf\Core\Font\TrueTypeFont;
use ApprLabs\Pdf\Core\PdfArray;
use ApprLabs\Pdf\Core\PdfDictionary;
use ApprLabs\Pdf\Core\PdfName;
use ApprLabs\Pdf\Core\PdfNumber;
use ApprLabs\Pdf\Core\PdfReference;
use ApprLabs\Pdf\Core\PdfString;
use ApprLabs\Pdf\Writer\PdfWriter;
use PHPUnit\Framework\TestCase;

class PdfUaIntegrationTest extends TestCase
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

    private function makeCompliantWriter(): PdfWriter
    {
        $writer = new PdfWriter();

        $info = new Info();
        $info->title = new PdfString('Accessible Document');
        $info->producer = new PdfString('phpdftk');
        $writer->setInfo($info);

        // Tagged structure
        $catalog = $writer->getCatalog();
        $markInfo = new MarkInfo();
        $markInfo->marked = true;
        $catalog->markInfo = $markInfo;
        $catalog->lang = new PdfString('en-US');

        $structRoot = new StructTreeRoot();
        $writer->register($structRoot);
        $catalog->structTreeRoot = new PdfReference($structRoot->objectNumber);

        // ViewerPreferences with DisplayDocTitle
        $vp = new ViewerPreferences();
        $vp->displayDocTitle = true;
        $writer->register($vp);
        $catalog->viewerPreferences = new PdfDictionary([
            'DisplayDocTitle' => new \ApprLabs\Pdf\Core\PdfBoolean(true),
        ]);

        return $writer;
    }

    // -----------------------------------------------------------------------
    // Happy path
    // -----------------------------------------------------------------------

    /**
     * A fully compliant PDF/UA-1 document generates successfully.
     */
    public function testCompliantPdfUa1GeneratesSuccessfully(): void
    {
        $writer = $this->makeCompliantWriter();
        $writer->setConformance(PdfUaProfile::UA1);

        $page = $writer->addPage(612, 792);
        $page->corePage()->tabs = new PdfName('S');

        $font = TrueTypeFont::fromFile($this->findFont());
        $fontHandle = $writer->addFont($font, $page);

        $cs = $writer->addContentStream($page);
        $cs->beginText()
            ->setFont($fontHandle->getResourceName(), 12)
            ->moveTextPosition(72, 720)
            ->showText('PDF/UA-1 Accessible Document')
            ->endText();

        $outPath = self::OUTPUT_DIR . '/pdfua1_compliant.pdf';
        $writer->save($outPath);

        self::assertFileExists($outPath);
        self::assertStringStartsWith('%PDF', file_get_contents($outPath));

        $results = $writer->getConformanceResults();
        self::assertCount(1, $results);
        self::assertTrue($results[0]->isCompliant);
    }

    /**
     * PDF/UA-1 auto-injects pdfuaid XMP identification.
     */
    public function testAutoInjectsPdfuaidXmp(): void
    {
        $writer = $this->makeCompliantWriter();
        $writer->setConformance(PdfUaProfile::UA1, strict: false);

        $page = $writer->addPage(612, 792);
        $font = TrueTypeFont::fromFile($this->findFont());
        $writer->addFont($font, $page);

        $pdf = $writer->generate();

        self::assertStringContainsString('pdfuaid:part', $pdf);
        self::assertStringContainsString('>1<', $pdf);
    }

    /**
     * PDF/UA-2 pins version to PDF 2.0.
     */
    public function testUa2PinsVersionTo2_0(): void
    {
        $writer = $this->makeCompliantWriter();
        $writer->setConformance(PdfUaProfile::UA2, strict: false);

        $page = $writer->addPage(612, 792);
        $font = TrueTypeFont::fromFile($this->findFont());
        $writer->addFont($font, $page);

        $pdf = $writer->generate();

        self::assertStringStartsWith('%PDF-2.0', $pdf);
    }

    /**
     * Dual profile: PDF/A-2a + PDF/UA-1.
     */
    public function testDualProfilePdfA2aAndPdfUa1(): void
    {
        $writer = $this->makeCompliantWriter();
        $writer->setConformanceProfiles([PdfAProfile::A2a, PdfUaProfile::UA1], strict: false);

        $page = $writer->addPage(612, 792);
        $font = TrueTypeFont::fromFile($this->findFont());
        $writer->addFont($font, $page);

        $writer->generate();

        $results = $writer->getConformanceResults();
        self::assertCount(2, $results);
        self::assertSame('PDF/A', $results[0]->profile->getFamily());
        self::assertSame('PDF/UA', $results[1]->profile->getFamily());
    }

    // -----------------------------------------------------------------------
    // Sad path
    // -----------------------------------------------------------------------

    /**
     * Missing tagged structure throws in strict mode.
     */
    public function testMissingTaggedStructureThrows(): void
    {
        $writer = new PdfWriter();
        $writer->setConformance(PdfUaProfile::UA1, strict: true);

        $page = $writer->addPage(612, 792);
        $font = TrueTypeFont::fromFile($this->findFont());
        $writer->addFont($font, $page);

        $this->expectException(ConformanceException::class);
        $writer->generate();
    }

    /**
     * Missing DisplayDocTitle fails.
     */
    public function testMissingDisplayDocTitleFails(): void
    {
        $writer = new PdfWriter();
        $writer->setConformance(PdfUaProfile::UA1, strict: false);

        // Add tagged structure but no ViewerPreferences
        $catalog = $writer->getCatalog();
        $markInfo = new MarkInfo();
        $markInfo->marked = true;
        $catalog->markInfo = $markInfo;
        $catalog->lang = new PdfString('en-US');
        $structRoot = new StructTreeRoot();
        $writer->register($structRoot);
        $catalog->structTreeRoot = new PdfReference($structRoot->objectNumber);

        $page = $writer->addPage(612, 792);
        $font = TrueTypeFont::fromFile($this->findFont());
        $writer->addFont($font, $page);

        $writer->generate();

        $results = $writer->getConformanceResults();
        self::assertFalse($results[0]->isCompliant);

        $clauses = array_map(fn($v) => $v->clause, $results[0]->getErrors());
        self::assertContains('7.18.1', $clauses);
    }

    /**
     * Annotation without /Contents fails.
     */
    public function testAnnotationWithoutContentsFails(): void
    {
        $writer = $this->makeCompliantWriter();
        $writer->setConformance(PdfUaProfile::UA1, strict: false);

        $page = $writer->addPage(612, 792);
        $page->corePage()->tabs = new PdfName('S');

        $font = TrueTypeFont::fromFile($this->findFont());
        $fontHandle = $writer->addFont($font, $page);

        $cs = $writer->addContentStream($page);
        $cs->beginText()
            ->setFont($fontHandle->getResourceName(), 12)
            ->moveTextPosition(72, 720)
            ->showText('Has inaccessible annotation')
            ->endText();

        // Add an annotation WITHOUT /Contents
        $annot = new TextAnnotation(new PdfArray([
            new PdfNumber(72), new PdfNumber(600),
            new PdfNumber(200), new PdfNumber(650),
        ]));
        // No $annot->contents set
        $annotRef = $writer->register($annot);
        $page->corePage()->annots[] = $annotRef;

        $writer->generate();

        $results = $writer->getConformanceResults();
        self::assertFalse($results[0]->isCompliant);

        $errorMessages = array_map(fn($v) => $v->message, $results[0]->getErrors());
        $found = false;
        foreach ($errorMessages as $msg) {
            if (str_contains($msg, 'Text annotation')) {
                $found = true;
                break;
            }
        }
        self::assertTrue($found, 'Expected annotation accessibility violation');
    }

    /**
     * Annotation WITH /Contents passes.
     */
    public function testAnnotationWithContentsPasses(): void
    {
        $writer = $this->makeCompliantWriter();
        $writer->setConformance(PdfUaProfile::UA1);

        $page = $writer->addPage(612, 792);
        $page->corePage()->tabs = new PdfName('S');

        $font = TrueTypeFont::fromFile($this->findFont());
        $fontHandle = $writer->addFont($font, $page);

        $cs = $writer->addContentStream($page);
        $cs->beginText()
            ->setFont($fontHandle->getResourceName(), 12)
            ->moveTextPosition(72, 720)
            ->showText('Has accessible annotation')
            ->endText();

        $annot = new TextAnnotation(new PdfArray([
            new PdfNumber(72), new PdfNumber(600),
            new PdfNumber(200), new PdfNumber(650),
        ]));
        $annot->contents = new PdfString('This is an accessible note');
        $annotRef = $writer->register($annot);
        $page->corePage()->annots[] = $annotRef;

        $outPath = self::OUTPUT_DIR . '/pdfua1_annotation.pdf';
        $writer->save($outPath);

        self::assertFileExists($outPath);
        self::assertTrue($writer->getConformanceResults()[0]->isCompliant);
    }

    /**
     * Missing /Lang on Catalog fails.
     */
    public function testMissingLangFails(): void
    {
        $writer = new PdfWriter();
        $writer->setConformance(PdfUaProfile::UA1, strict: false);

        $catalog = $writer->getCatalog();
        $markInfo = new MarkInfo();
        $markInfo->marked = true;
        $catalog->markInfo = $markInfo;
        // No $catalog->lang set
        $structRoot = new StructTreeRoot();
        $writer->register($structRoot);
        $catalog->structTreeRoot = new PdfReference($structRoot->objectNumber);

        $page = $writer->addPage(612, 792);
        $font = TrueTypeFont::fromFile($this->findFont());
        $writer->addFont($font, $page);

        $writer->generate();

        $clauses = array_map(
            fn($v) => $v->clause,
            $writer->getConformanceResults()[0]->getErrors(),
        );
        self::assertContains('7.2', $clauses);
    }

    /**
     * checkConformance() works before generate() for PDF/UA.
     */
    public function testCheckConformanceBeforeGenerate(): void
    {
        $writer = new PdfWriter();
        $writer->setConformance(PdfUaProfile::UA1);

        $page = $writer->addPage(612, 792);
        $font = TrueTypeFont::fromFile($this->findFont());
        $writer->addFont($font, $page);

        $results = $writer->checkConformance();
        self::assertCount(1, $results);
        self::assertFalse($results[0]->isCompliant);
    }
}
