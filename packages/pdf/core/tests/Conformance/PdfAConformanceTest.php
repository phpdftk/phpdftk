<?php

declare(strict_types=1);

namespace ApprLabs\Pdf\Core\Tests\Conformance;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use ApprLabs\Pdf\Core\Document\Info;
use ApprLabs\Pdf\Core\Document\MetadataStream;
use ApprLabs\Pdf\Core\Document\OutputIntent;
use ApprLabs\Pdf\Core\Font\TrueTypeFont;
use ApprLabs\Pdf\Core\PdfArray;
use ApprLabs\Pdf\Core\PdfDictionary;
use ApprLabs\Pdf\Core\PdfName;
use ApprLabs\Pdf\Core\PdfNumber;
use ApprLabs\Pdf\Core\PdfReference;
use ApprLabs\Pdf\Core\PdfStream;
use ApprLabs\Pdf\Core\PdfString;
use ApprLabs\Pdf\Writer\PdfWriter;
use ApprLabs\Tests\Support\QpdfValidationTrait;
use ApprLabs\Tests\Support\VeraPdfValidationTrait;

/**
 * Tests that validate generated PDFs against veraPDF for PDF/A compliance.
 *
 * These tests are opt-in and require veraPDF to be installed (CLI or Docker).
 * Run with: vendor/bin/phpunit --group verapdf
 */
#[Group('verapdf')]
class PdfAConformanceTest extends TestCase
{
    use QpdfValidationTrait;
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
     *
     * PDF/A-1b (ISO 19005-1) requires:
     * - dc:title as rdf:Alt (lang alt)
     * - dc:creator as rdf:Seq (ordered list)
     * - pdfaid:part and pdfaid:conformance for PDF/A identification
     * - All Info dict entries synced to XMP equivalents
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
     * Generate a PDF/A-1b compliant document with:
     * - Embedded TrueType font with WinAnsiEncoding (clause 6.3.4, 6.3.7)
     * - XMP metadata with proper RDF types and PDF/A identification (clause 6.7)
     * - Uncompressed metadata stream (clause 6.7.2)
     * - sRGB ICC profile in OutputIntent (clause 6.2.2, 6.2.3.3)
     *
     * Then validate with veraPDF.
     */
    public function testMinimalPdfWithOutputIntent(): void
    {
        $writer = new PdfWriter();

        // Document metadata
        $title = 'PDF/A Conformance Test';
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

        // OutputIntent with embedded sRGB ICC profile (clause 6.2.2/6.2.3.3)
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
            ->showText('PDF/A Conformance Test Document')
            ->endText();

        $outPath = self::OUTPUT_DIR . '/pdfa_minimal.pdf';
        $writer->save($outPath);

        self::assertFileExists($outPath);
        $this->assertQpdfValid($outPath);

        // Validate against PDF/A-1b profile
        $this->assertVeraPdfCompliant($outPath, '1b');
    }

    /**
     * Verify that the veraPDF toolchain runs correctly by generating a simple
     * PDF and checking that veraPDF produces a validation report (even if the
     * document is not PDF/A compliant).
     */
    public function testVeraPdfToolchainWorks(): void
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

        $outPath = self::OUTPUT_DIR . '/pdfa_simple.pdf';
        $writer->save($outPath);

        self::assertFileExists($outPath);
        $this->assertQpdfValid($outPath);

        // This test verifies that veraPDF runs without crashing.
        $rawResult = $this->runVeraPdfRaw($outPath, '1b');
        $fullOutput = $rawResult instanceof \ApprLabs\Tests\Support\DockerToolResult
            ? $rawResult->output
            : $rawResult;

        self::assertNotEmpty($fullOutput, 'veraPDF produced no output');
        self::assertStringContainsString('validationReport', $fullOutput, 'veraPDF output should contain a validation report');
    }
}
