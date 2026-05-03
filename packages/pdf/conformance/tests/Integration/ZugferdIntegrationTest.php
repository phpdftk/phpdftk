<?php

declare(strict_types=1);

namespace Phpdftk\Pdf\Conformance\Tests\Integration;

use Phpdftk\Pdf\Conformance\ConformanceException;
use Phpdftk\Pdf\Conformance\Profile\ZugferdProfile;
use Phpdftk\Pdf\Core\Document\Info;
use Phpdftk\Pdf\Core\Document\NamesDictionary;
use Phpdftk\Pdf\Core\Document\NameTree;
use Phpdftk\Pdf\Core\Document\OutputIntent;
use Phpdftk\Pdf\Core\FileSpec\EmbeddedFile;
use Phpdftk\Pdf\Core\FileSpec\FileSpec;
use Phpdftk\Pdf\Core\Font\TrueTypeFont;
use Phpdftk\Pdf\Core\PdfArray;
use Phpdftk\Pdf\Core\PdfDictionary;
use Phpdftk\Pdf\Core\PdfNumber;
use Phpdftk\Pdf\Core\PdfStream;
use Phpdftk\Pdf\Core\PdfString;
use Phpdftk\Pdf\Writer\PdfWriter;
use PHPUnit\Framework\TestCase;

class ZugferdIntegrationTest extends TestCase
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

    private function buildCompliantWriter(ZugferdProfile $profile = ZugferdProfile::BASIC, bool $strict = true): PdfWriter
    {
        $writer = new PdfWriter();
        $writer->setConformance($profile, strict: $strict);

        // Info dict
        $info = new Info();
        $info->title = new PdfString('Factur-X Test');
        $info->producer = new PdfString('phpdftk');
        $writer->setInfo($info);

        // OutputIntent with ICC profile (required by PDF/A-3b base)
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

        // Embedded font (required by PDF/A-3b base)
        $page = $writer->addPage(612, 792);
        $font = TrueTypeFont::fromFile($this->findFont());
        $fontHandle = $writer->addFont($font, $page);

        $cs = $writer->addContentStream($page);
        $cs->beginText()
            ->setFont($fontHandle->getResourceName(), 12)
            ->moveTextPosition(72, 720)
            ->showText('Factur-X compliant document')
            ->endText();

        // Embed factur-x.xml invoice
        $xmlData = '<?xml version="1.0" encoding="UTF-8"?><invoice/>';
        $embeddedFile = new EmbeddedFile($xmlData, 'text/xml');
        $efRef = $writer->register($embeddedFile);

        $fileSpec = new FileSpec('factur-x.xml');
        $fileSpec->attachEmbeddedFile($efRef);
        $fsRef = $writer->register($fileSpec);

        // Wire into catalog Names/EmbeddedFiles name tree
        $nameTree = new NameTree();
        $nameTree->names = new PdfArray([new PdfString('factur-x.xml'), $fsRef]);
        $ntRef = $writer->register($nameTree);

        $namesDictionary = new NamesDictionary();
        $namesDictionary->embeddedFiles = $ntRef;
        $ndRef = $writer->register($namesDictionary);
        $writer->getCatalog()->names = $ndRef;

        return $writer;
    }

    // -----------------------------------------------------------------------
    // Happy path
    // -----------------------------------------------------------------------

    public function testCompliantZugferdBasic(): void
    {
        $writer = $this->buildCompliantWriter();

        $outPath = self::OUTPUT_DIR . '/zugferd_basic_compliant.pdf';
        $writer->save($outPath);

        self::assertFileExists($outPath);
        self::assertStringStartsWith('%PDF', file_get_contents($outPath));

        $results = $writer->getConformanceResults();
        self::assertCount(1, $results);
        self::assertTrue($results[0]->isCompliant);
    }

    public function testAutoInjectsFacturXXmp(): void
    {
        $writer = $this->buildCompliantWriter(strict: false);
        $pdf = $writer->generate();

        self::assertStringContainsString('fx:ConformanceLevel', $pdf);
        self::assertStringContainsString('fx:DocumentType', $pdf);
        self::assertStringContainsString('fx:DocumentFileName', $pdf);
    }

    public function testZugferdPinsVersion(): void
    {
        $writer = $this->buildCompliantWriter(strict: false);
        $pdf = $writer->generate();

        // ZUGFeRD is based on PDF/A-3b which requires PDF 1.7
        self::assertStringStartsWith('%PDF-1.7', $pdf);
    }

    public function testAllProfileLevelsGenerate(): void
    {
        foreach (ZugferdProfile::cases() as $profile) {
            $writer = $this->buildCompliantWriter($profile, strict: false);
            $pdf = $writer->generate();
            self::assertStringStartsWith('%PDF', $pdf, "Profile {$profile->value} failed to generate");
        }
    }

    // -----------------------------------------------------------------------
    // Sad path
    // -----------------------------------------------------------------------

    public function testMissingEmbeddedInvoiceFails(): void
    {
        $writer = new PdfWriter();
        $writer->setConformance(ZugferdProfile::BASIC, strict: false);

        $info = new Info();
        $info->title = new PdfString('ZUGFeRD No Invoice');
        $info->producer = new PdfString('phpdftk');
        $writer->setInfo($info);

        // OutputIntent (PDF/A-3b base)
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

        // Font
        $page = $writer->addPage(612, 792);
        $font = TrueTypeFont::fromFile($this->findFont());
        $writer->addFont($font, $page);

        // No embedded XML invoice
        $writer->generate();
        $results = $writer->getConformanceResults();
        self::assertFalse($results[0]->isCompliant);

        $errors = $results[0]->getErrors();
        $invoiceErrors = array_filter(
            $errors,
            fn($v) => str_contains($v->message, 'invoice') || $v->clause === 'A.2',
        );
        self::assertNotEmpty($invoiceErrors, 'Expected a ZUGFeRD invoice constraint violation');
    }

    public function testMissingOutputIntentFails(): void
    {
        $writer = new PdfWriter();
        $writer->setConformance(ZugferdProfile::BASIC, strict: false);

        $page = $writer->addPage(612, 792);
        $font = TrueTypeFont::fromFile($this->findFont());
        $writer->addFont($font, $page);

        $writer->generate();
        $results = $writer->getConformanceResults();
        self::assertFalse($results[0]->isCompliant);
    }

    public function testMissingOutputIntentThrowsInStrictMode(): void
    {
        $writer = new PdfWriter();
        $writer->setConformance(ZugferdProfile::BASIC, strict: true);

        $page = $writer->addPage(612, 792);
        $font = TrueTypeFont::fromFile($this->findFont());
        $writer->addFont($font, $page);

        $this->expectException(ConformanceException::class);
        $writer->generate();
    }

    public function testCheckConformanceAdvisory(): void
    {
        $writer = new PdfWriter();
        $writer->setConformance(ZugferdProfile::BASIC);

        $page = $writer->addPage(612, 792);
        $font = TrueTypeFont::fromFile($this->findFont());
        $writer->addFont($font, $page);

        $results = $writer->checkConformance();
        self::assertCount(1, $results);
        self::assertFalse($results[0]->isCompliant);
    }
}
