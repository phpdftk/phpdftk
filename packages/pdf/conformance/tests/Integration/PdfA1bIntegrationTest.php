<?php

declare(strict_types=1);

namespace ApprLabs\Pdf\Conformance\Tests\Integration;

use ApprLabs\Pdf\Conformance\ConformanceException;
use ApprLabs\Pdf\Conformance\Profile\PdfAProfile;
use ApprLabs\Pdf\Core\Document\Info;
use ApprLabs\Pdf\Core\Document\OutputIntent;
use ApprLabs\Pdf\Core\Font\TrueTypeFont;
use ApprLabs\Pdf\Core\PdfArray;
use ApprLabs\Pdf\Core\PdfDictionary;
use ApprLabs\Pdf\Core\PdfNumber;
use ApprLabs\Pdf\Core\PdfStream;
use ApprLabs\Pdf\Core\PdfString;
use ApprLabs\Pdf\Writer\PdfWriter;
use PHPUnit\Framework\TestCase;

class PdfA1bIntegrationTest extends TestCase
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
     * Build a compliant PDF/A-1b document using the new setConformance() API.
     */
    public function testCompliantPdfA1bGeneratesSuccessfully(): void
    {
        $writer = new PdfWriter();
        $writer->setConformance(PdfAProfile::A1b);

        // Info dict
        $info = new Info();
        $info->title = new PdfString('PDF/A-1b Conformance Test');
        $info->author = new PdfString('phpdftk');
        $info->producer = new PdfString('phpdftk');
        $writer->setInfo($info);

        // OutputIntent with ICC profile
        $iccData = file_get_contents($this->findIccProfile());
        $iccStream = new PdfStream(new PdfDictionary(), $iccData);
        $iccStream->dictionary->set('N', new PdfNumber(3));
        $iccRef = $writer->register($iccStream);

        $outputIntent = new OutputIntent('GTS_PDFA1', 'sRGB IEC61966-2.1');
        $outputIntent->registryName = new PdfString('http://www.color.org');
        $outputIntent->info = new PdfString('sRGB IEC61966-2.1');
        $outputIntent->destOutputProfile = $iccRef;
        $oiRef = $writer->register($outputIntent);
        $writer->getCatalog()->outputIntents = new PdfArray([$oiRef]);

        // Page with embedded font
        $page = $writer->addPage(612, 792);
        $font = TrueTypeFont::fromFile($this->findFont());
        $fontHandle = $writer->addFont($font, $page);

        $cs = $writer->addContentStream($page);
        $cs->beginText()
            ->setFont($fontHandle->getResourceName(), 12)
            ->moveTextPosition(72, 720)
            ->showText('PDF/A-1b via setConformance()')
            ->endText();

        // Should generate without throwing
        $outPath = self::OUTPUT_DIR . '/pdfa1b_conformance.pdf';
        $writer->save($outPath);

        self::assertFileExists($outPath);
        self::assertStringStartsWith('%PDF', file_get_contents($outPath));

        // Check results
        $results = $writer->getConformanceResults();
        self::assertCount(1, $results);
        self::assertTrue($results[0]->isCompliant);
    }

    /**
     * A document without an OutputIntent should fail conformance in strict mode.
     */
    public function testMissingOutputIntentThrowsInStrictMode(): void
    {
        $writer = new PdfWriter();
        $writer->setConformance(PdfAProfile::A1b, strict: true);

        // Embedded font but NO OutputIntent
        $page = $writer->addPage(612, 792);
        $font = TrueTypeFont::fromFile($this->findFont());
        $fontHandle = $writer->addFont($font, $page);

        $cs = $writer->addContentStream($page);
        $cs->beginText()
            ->setFont($fontHandle->getResourceName(), 12)
            ->moveTextPosition(72, 720)
            ->showText('Missing OutputIntent')
            ->endText();

        $this->expectException(ConformanceException::class);
        $writer->generate();
    }

    /**
     * In lenient mode, conformance violations are collected but don't throw.
     */
    public function testLenientModeCollectsViolations(): void
    {
        $writer = new PdfWriter();
        $writer->setConformance(PdfAProfile::A1b, strict: false);

        // Minimal doc — no OutputIntent, no embedded font issues
        $page = $writer->addPage(612, 792);
        $font = TrueTypeFont::fromFile($this->findFont());
        $fontHandle = $writer->addFont($font, $page);

        $cs = $writer->addContentStream($page);
        $cs->beginText()
            ->setFont($fontHandle->getResourceName(), 12)
            ->moveTextPosition(72, 720)
            ->showText('Lenient mode test')
            ->endText();

        // Should NOT throw
        $pdf = $writer->generate();
        self::assertStringStartsWith('%PDF', $pdf);

        // But should have violations
        $results = $writer->getConformanceResults();
        self::assertCount(1, $results);
        self::assertFalse($results[0]->isCompliant);
        self::assertNotEmpty($results[0]->getErrors());
    }

    /**
     * Conformance auto-injects XMP metadata when not manually set.
     */
    public function testAutoInjectsXmpMetadata(): void
    {
        $writer = new PdfWriter();
        $writer->setConformance(PdfAProfile::A1b, strict: false);

        $info = new Info();
        $info->title = new PdfString('Auto XMP Test');
        $info->producer = new PdfString('phpdftk');
        $writer->setInfo($info);

        $page = $writer->addPage(612, 792);
        $font = TrueTypeFont::fromFile($this->findFont());
        $writer->addFont($font, $page);

        $pdf = $writer->generate();

        // The PDF should contain the pdfaid identification
        self::assertStringContainsString('pdfaid:part', $pdf);
        self::assertStringContainsString('pdfaid:conformance', $pdf);
    }

    /**
     * checkConformance() can be called before generate() for advisory feedback.
     */
    public function testCheckConformanceBeforeGenerate(): void
    {
        $writer = new PdfWriter();
        $writer->setConformance(PdfAProfile::A1b);

        $page = $writer->addPage(612, 792);
        $font = TrueTypeFont::fromFile($this->findFont());
        $writer->addFont($font, $page);

        $results = $writer->checkConformance();
        self::assertCount(1, $results);
        // Should have violations (no OutputIntent, no XMP yet)
        self::assertFalse($results[0]->isCompliant);
    }
}
