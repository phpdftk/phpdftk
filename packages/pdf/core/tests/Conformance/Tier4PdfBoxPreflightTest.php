<?php

declare(strict_types=1);

namespace Phpdftk\Pdf\Core\Tests\Conformance;

use Phpdftk\Pdf\Core\Document\Info;
use Phpdftk\Pdf\Core\Document\OutputIntent;
use Phpdftk\Pdf\Core\Font\TrueTypeFont;
use Phpdftk\Pdf\Core\PdfArray;
use Phpdftk\Pdf\Core\PdfDictionary;
use Phpdftk\Pdf\Core\PdfName;
use Phpdftk\Pdf\Core\PdfNumber;
use Phpdftk\Pdf\Core\PdfStream;
use Phpdftk\Pdf\Core\PdfString;
use Phpdftk\Pdf\Writer\PdfWriter;
use Phpdftk\Tests\Support\DockerToolResult;
use Phpdftk\Tests\Support\PdfBoxPreflightValidationTrait;
use Phpdftk\Tests\Support\QpdfValidationTrait;
use Phpdftk\Tests\Support\VeraPdfValidationTrait;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Tier 4 — PDFBox Preflight PDF/A-1b validation tests for generated PDFs.
 *
 * Validates that PDF/A-1b documents produced by PdfWriter pass Apache PDFBox
 * Preflight as a second independent validator (alongside veraPDF).
 *
 * Run with: vendor/bin/phpunit --group tier4
 */
#[Group('tier4')]
class Tier4PdfBoxPreflightTest extends TestCase
{
    use QpdfValidationTrait;
    use PdfBoxPreflightValidationTrait;
    use VeraPdfValidationTrait;

    private const OUTPUT_DIR = __DIR__ . '/../output';

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
     * Build PDF/A-1b compliant XMP metadata with proper RDF types.
     */
    private function buildPdfAXmp(string $title, string $author, string $producer): string
    {
        $bom = "\xEF\xBB\xBF";
        $titleEsc = htmlspecialchars($title, ENT_XML1 | ENT_COMPAT, 'UTF-8');
        $authorEsc = htmlspecialchars($author, ENT_XML1 | ENT_COMPAT, 'UTF-8');
        $producerEsc = htmlspecialchars($producer, ENT_XML1 | ENT_COMPAT, 'UTF-8');

        return <<<XML
        <?xpacket begin="{$bom}" id="W5M0MpCehiHzreSzNTczkc9d"?>
        <x:xmpmeta xmlns:x="adobe:ns:meta/">
          <rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#">
            <rdf:Description rdf:about=""
              xmlns:dc="http://purl.org/dc/elements/1.1/"
              xmlns:pdf="http://ns.adobe.com/pdf/1.3/"
              xmlns:pdfaid="http://www.aiim.org/pdfa/ns/id/">
              <dc:title>
                <rdf:Alt>
                  <rdf:li xml:lang="x-default">{$titleEsc}</rdf:li>
                </rdf:Alt>
              </dc:title>
              <dc:creator>
                <rdf:Seq>
                  <rdf:li>{$authorEsc}</rdf:li>
                </rdf:Seq>
              </dc:creator>
              <pdf:Producer>{$producerEsc}</pdf:Producer>
              <pdfaid:part>1</pdfaid:part>
              <pdfaid:conformance>B</pdfaid:conformance>
            </rdf:Description>
          </rdf:RDF>
        </x:xmpmeta>
        <?xpacket end="w"?>
        XML;
    }

    /**
     * Generate a PDF/A-1b compliant document and validate with PDFBox Preflight.
     *
     * Builds the same document structure as PdfAConformanceTest::testMinimalPdfWithOutputIntent():
     * - Embedded TrueType font with WinAnsiEncoding
     * - XMP metadata with PDF/A identification
     * - sRGB ICC profile in OutputIntent
     */
    public function testPdfA1bPassesPdfBoxPreflight(): void
    {
        $writer = new PdfWriter();

        // Document metadata
        $title = 'PDFBox Preflight Conformance Test';
        $author = 'phpdftk';
        $producer = 'phpdftk';

        $info = new Info();
        $info->title = new PdfString($title);
        $info->author = new PdfString($author);
        $info->producer = new PdfString($producer);
        $writer->setInfo($info);

        // PDF/A-1b compliant XMP with proper RDF types and pdfaid schema
        $xmpXml = $this->buildPdfAXmp($title, $author, $producer);
        $writer->setMetadata($xmpXml);

        // OutputIntent with embedded sRGB ICC profile
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

        // Page with embedded TrueType font
        $page = $writer->addPage(612, 792);
        $font = TrueTypeFont::fromFile($this->findFont());
        $fontName = $writer->addFont($font, $page)->getResourceName();

        $cs = $writer->addContentStream($page);
        $cs->beginText()
            ->setFont($fontName, 12)
            ->moveTextPosition(72, 720)
            ->showText('PDFBox Preflight Conformance Test Document')
            ->endText();

        $outPath = self::OUTPUT_DIR . '/pdfbox_preflight_pdfa.pdf';
        $writer->save($outPath);

        self::assertFileExists($outPath);
        $this->assertQpdfValid($outPath);
        $this->assertPdfBoxPreflightValid($outPath);
    }

    /**
     * Verify that the PDFBox Preflight toolchain runs correctly by generating
     * a simple (non-PDF/A) PDF and checking that Preflight produces output.
     * We expect a non-zero exit (the document is not PDF/A), but the tool should run.
     */
    public function testPdfBoxPreflightToolchainWorks(): void
    {
        $writer = new PdfWriter();

        $page = $writer->addPage(612, 792);
        $font = TrueTypeFont::fromFile($this->findFont());
        $fontName = $writer->addFont($font, $page)->getResourceName();

        $cs = $writer->addContentStream($page);
        $cs->beginText()
            ->setFont($fontName, 12)
            ->moveTextPosition(72, 720)
            ->showText('Simple test document')
            ->endText();

        $outPath = self::OUTPUT_DIR . '/pdfbox_preflight_simple.pdf';
        $writer->save($outPath);

        self::assertFileExists($outPath);
        $this->assertQpdfValid($outPath);

        // This test verifies that PDFBox Preflight runs without crashing.
        // A simple PDF is NOT PDF/A-1b, so we expect validation output (likely failures).
        $rawResult = $this->runPdfBoxPreflightRaw($outPath);
        $output = $rawResult instanceof DockerToolResult ? $rawResult->output : $rawResult;

        self::assertNotEmpty($output, 'PDFBox Preflight produced no output');
    }
}
