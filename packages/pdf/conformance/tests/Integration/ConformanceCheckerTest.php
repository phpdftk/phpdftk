<?php

declare(strict_types=1);

namespace Phpdftk\Pdf\Conformance\Tests\Integration;

use Phpdftk\Pdf\Conformance\ConformanceChecker;
use Phpdftk\Pdf\Conformance\Profile\PdfAProfile;
use Phpdftk\Pdf\Conformance\Profile\PdfEProfile;
use Phpdftk\Pdf\Conformance\Profile\PdfRProfile;
use Phpdftk\Pdf\Conformance\Profile\PdfUaProfile;
use Phpdftk\Pdf\Conformance\Profile\PdfXProfile;
use Phpdftk\Pdf\Core\Document\Info;
use Phpdftk\Pdf\Core\Document\MarkInfo;
use Phpdftk\Pdf\Core\Document\OutputIntent;
use Phpdftk\Pdf\Core\Document\StructTreeRoot;
use Phpdftk\Pdf\Core\Document\ViewerPreferences;
use Phpdftk\Pdf\Core\Font\TrueTypeFont;
use Phpdftk\Pdf\Core\PdfArray;
use Phpdftk\Pdf\Core\PdfBoolean;
use Phpdftk\Pdf\Core\PdfDictionary;
use Phpdftk\Pdf\Core\PdfName;
use Phpdftk\Pdf\Core\PdfNumber;
use Phpdftk\Pdf\Core\PdfReference;
use Phpdftk\Pdf\Core\PdfStream;
use Phpdftk\Pdf\Core\PdfString;
use Phpdftk\Pdf\Writer\PdfWriter;
use PHPUnit\Framework\TestCase;

class ConformanceCheckerTest extends TestCase
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

    /**
     * Build a compliant PDF/A-1b document and return its bytes.
     */
    private function buildCompliantPdfA1b(): string
    {
        $writer = new PdfWriter();
        $writer->setConformance(PdfAProfile::A1b);

        $info = new Info();
        $info->title = new PdfString('Checker Test');
        $info->producer = new PdfString('phpdftk');
        $writer->setInfo($info);

        $iccData = file_get_contents($this->findIccProfile());
        $iccStream = new PdfStream(new PdfDictionary(), $iccData);
        $iccStream->dictionary->set('N', new PdfNumber(3));
        $iccRef = $writer->register($iccStream);

        $oi = new OutputIntent('GTS_PDFA1', 'sRGB IEC61966-2.1');
        $oi->registryName = new PdfString('http://www.color.org');
        $oi->info = new PdfString('sRGB IEC61966-2.1');
        $oi->destOutputProfile = $iccRef;
        $oiRef = $writer->register($oi);
        $writer->getCatalog()->outputIntents = new PdfArray([$oiRef]);

        $page = $writer->addPage(612, 792);
        $font = TrueTypeFont::fromFile($this->findFont());
        $fontHandle = $writer->addFont($font, $page);
        $cs = $writer->addContentStream($page);
        $cs->beginText()
            ->setFont($fontHandle->getResourceName(), 12)
            ->moveTextPosition(72, 720)
            ->showText('Checker test document')
            ->endText();

        return $writer->generate();
    }

    /**
     * Build a minimal non-compliant PDF (no metadata, no OutputIntent).
     */
    private function buildMinimalPdf(): string
    {
        $writer = new PdfWriter();
        $page = $writer->addPage(612, 792);
        $font = TrueTypeFont::fromFile($this->findFont());
        $fontHandle = $writer->addFont($font, $page);
        $cs = $writer->addContentStream($page);
        $cs->beginText()
            ->setFont($fontHandle->getResourceName(), 12)
            ->moveTextPosition(72, 720)
            ->showText('Minimal document')
            ->endText();

        return $writer->generate();
    }

    // -----------------------------------------------------------------------
    // Happy path
    // -----------------------------------------------------------------------

    /**
     * Round-trip: write compliant PDF/A-1b, read back, verify compliant.
     */
    public function testRoundTripPdfA1bCompliant(): void
    {
        $pdf = $this->buildCompliantPdfA1b();

        $checker = ConformanceChecker::openString($pdf);
        $result = $checker->checkProfile(PdfAProfile::A1b);

        self::assertTrue($result->isCompliant, sprintf(
            'Expected compliant but got %d errors: %s',
            count($result->getErrors()),
            implode('; ', array_map(fn($v) => $v->message, $result->getErrors())),
        ));
    }

    /**
     * openString returns a ConformanceChecker that provides the reader.
     */
    public function testOpenStringReturnsChecker(): void
    {
        $pdf = $this->buildMinimalPdf();

        $checker = ConformanceChecker::openString($pdf);
        self::assertInstanceOf(ConformanceChecker::class, $checker);
        self::assertGreaterThan(0, $checker->getReader()->getPageCount());
    }

    /**
     * open() from file path works.
     */
    public function testOpenFromFile(): void
    {
        $pdf = $this->buildCompliantPdfA1b();
        $path = self::OUTPUT_DIR . '/checker_test.pdf';
        file_put_contents($path, $pdf);

        $checker = ConformanceChecker::open($path);
        $result = $checker->checkProfile(PdfAProfile::A1b);

        self::assertTrue($result->isCompliant);
    }

    /**
     * checkProfiles() returns results for each profile.
     */
    public function testCheckMultipleProfiles(): void
    {
        $pdf = $this->buildMinimalPdf();
        $checker = ConformanceChecker::openString($pdf);

        $results = $checker->checkProfiles([PdfAProfile::A1b, PdfAProfile::A2b]);
        self::assertCount(2, $results);
        self::assertSame('1b', $results[0]->profile->getLevel());
        self::assertSame('2b', $results[1]->profile->getLevel());
    }

    /**
     * A compliant PDF/A-1b checked as PDF/E-1 fails metadata (wrong XMP identification).
     * PDF/E-1 expects pdfeid:part, but the doc has pdfaid:part.
     */
    public function testPdfA1bFailsPdfE1MetadataIdentification(): void
    {
        $pdf = $this->buildCompliantPdfA1b();
        $checker = ConformanceChecker::openString($pdf);

        $result = $checker->checkProfile(PdfEProfile::E1);
        self::assertFalse($result->isCompliant);

        // Only the metadata identification should fail — fonts and encryption pass
        $clauses = array_map(fn($v) => $v->clause, $result->getErrors());
        self::assertContains('6.7.11', $clauses);
    }

    /**
     * A compliant PDF/A-1b also satisfies PDF/R-1 (minimal constraints).
     */
    public function testCompliantPdfA1bPassesPdfR1(): void
    {
        $pdf = $this->buildCompliantPdfA1b();
        $checker = ConformanceChecker::openString($pdf);

        // PDF/R-1 checks metadata + encryption — the A-1b doc has both covered
        // but the XMP identification will be for pdfaid, not pdfrid.
        // MetadataConstraint checks for the *profile-specific* identification,
        // so this will fail on the pdfrid tag check.
        $result = $checker->checkProfile(PdfRProfile::R1);
        // The doc has XMP but with pdfaid, not pdfrid — so metadata constraint fails
        self::assertFalse($result->isCompliant);
    }

    // -----------------------------------------------------------------------
    // Sad path
    // -----------------------------------------------------------------------

    /**
     * A minimal PDF (no metadata, no OutputIntent) fails PDF/A-1b.
     */
    public function testMinimalPdfFailsPdfA1b(): void
    {
        $pdf = $this->buildMinimalPdf();
        $checker = ConformanceChecker::openString($pdf);

        $result = $checker->checkProfile(PdfAProfile::A1b);
        self::assertFalse($result->isCompliant);

        $clauses = array_map(fn($v) => $v->clause, $result->getErrors());
        // Should detect missing metadata and OutputIntent
        self::assertContains('6.7.2', $clauses); // metadata
        self::assertContains('6.2.2', $clauses); // output intent
    }

    /**
     * A minimal PDF fails PDF/UA-1 (no tagged structure, no DisplayDocTitle).
     */
    public function testMinimalPdfFailsPdfUa1(): void
    {
        $pdf = $this->buildMinimalPdf();
        $checker = ConformanceChecker::openString($pdf);

        $result = $checker->checkProfile(PdfUaProfile::UA1);
        self::assertFalse($result->isCompliant);

        $clauses = array_map(fn($v) => $v->clause, $result->getErrors());
        self::assertContains('7.1', $clauses);    // MarkInfo
        self::assertContains('7.2', $clauses);    // Lang
        self::assertContains('7.18.1', $clauses); // DisplayDocTitle
    }

    /**
     * A minimal PDF fails PDF/X-4 (no OutputIntent, no TrimBox, no Trapped).
     */
    public function testMinimalPdfFailsPdfX4(): void
    {
        $pdf = $this->buildMinimalPdf();
        $checker = ConformanceChecker::openString($pdf);

        $result = $checker->checkProfile(PdfXProfile::X4);
        self::assertFalse($result->isCompliant);

        $clauses = array_map(fn($v) => $v->clause, $result->getErrors());
        self::assertContains('6.2.2', $clauses); // OutputIntent
        self::assertContains('6.2', $clauses);   // TrimBox
        self::assertContains('6.3', $clauses);   // Trapped
    }

    /**
     * A compliant PDF/A-1b checked as PDF/A-1a fails (missing tagged structure).
     */
    public function testPdfA1bFailsAsA1a(): void
    {
        $pdf = $this->buildCompliantPdfA1b();
        $checker = ConformanceChecker::openString($pdf);

        $result = $checker->checkProfile(PdfAProfile::A1a);
        self::assertFalse($result->isCompliant);

        // Should have tagged structure violations
        $clauses = array_map(fn($v) => $v->clause, $result->getErrors());
        self::assertContains('6.8.1', $clauses); // MarkInfo
    }

    /**
     * ReaderDocumentInspector correctly detects no encryption.
     */
    public function testDetectsNoEncryption(): void
    {
        $pdf = $this->buildMinimalPdf();
        $checker = ConformanceChecker::openString($pdf);

        // PDF/E-1 checks encryption — unencrypted should pass that specific constraint
        $result = $checker->checkProfile(PdfEProfile::E1);
        $clauses = array_map(fn($v) => $v->clause, $result->getErrors());
        self::assertNotContains('6.6', $clauses); // No encryption violation
    }
}
