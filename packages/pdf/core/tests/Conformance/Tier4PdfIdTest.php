<?php

declare(strict_types=1);

namespace ApprLabs\Pdf\Core\Tests\Conformance;

use ApprLabs\Pdf\Core\Document\Info;
use ApprLabs\Pdf\Core\Font\TrueTypeFont;
use ApprLabs\Pdf\Core\PdfString;
use ApprLabs\Pdf\Writer\PdfWriter;
use ApprLabs\Tests\Support\DockerToolResult;
use ApprLabs\Tests\Support\PdfIdValidationTrait;
use ApprLabs\Tests\Support\QpdfValidationTrait;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Tier 4 — pdfid.py security lint tests for generated PDFs.
 *
 * Validates that PDFs produced by PdfWriter have no suspicious security
 * indicators (/JS, /JavaScript, /AA, /OpenAction, /Launch).
 *
 * Run with: vendor/bin/phpunit --group tier4-security
 */
#[Group('tier4')]
#[Group('tier4-security')]
class Tier4PdfIdTest extends TestCase
{
    use QpdfValidationTrait;
    use PdfIdValidationTrait;

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

    public function testMinimalPdfHasNoSuspiciousIndicators(): void
    {
        $writer = new PdfWriter();

        $page = $writer->addPage(612, 792);
        $font = TrueTypeFont::fromFile($this->findFont());
        $fontName = $writer->addFont($font, $page)->getResourceName();

        $cs = $writer->addContentStream($page);
        $cs->beginText()
            ->setFont($fontName, 12)
            ->moveTextPosition(72, 720)
            ->showText('Minimal pdfid test document')
            ->endText();

        $outPath = self::OUTPUT_DIR . '/pdfid_minimal.pdf';
        $writer->save($outPath);

        self::assertFileExists($outPath);
        $this->assertQpdfValid($outPath);
        $this->assertPdfIdClean($outPath);
    }

    public function testPdfWithMetadataHasNoSuspiciousIndicators(): void
    {
        $writer = new PdfWriter();

        $info = new Info();
        $info->title = new PdfString('PdfId Metadata Test');
        $info->author = new PdfString('phpdftk test suite');
        $info->producer = new PdfString('phpdftk');
        $writer->setInfo($info);

        $page = $writer->addPage(612, 792);
        $font = TrueTypeFont::fromFile($this->findFont());
        $fontName = $writer->addFont($font, $page)->getResourceName();

        $cs = $writer->addContentStream($page);
        $cs->beginText()
            ->setFont($fontName, 12)
            ->moveTextPosition(72, 720)
            ->showText('Document with metadata')
            ->endText();

        $outPath = self::OUTPUT_DIR . '/pdfid_metadata.pdf';
        $writer->save($outPath);

        self::assertFileExists($outPath);
        $this->assertQpdfValid($outPath);
        $this->assertPdfIdClean($outPath);
    }

    public function testPdfWithEmbeddedFontHasNoSuspiciousIndicators(): void
    {
        $writer = new PdfWriter();

        $page = $writer->addPage(612, 792);

        $ttfFont = TrueTypeFont::fromFile($this->findFont());
        $ttfName = $writer->addFont($ttfFont, $page)->getResourceName();

        $cs = $writer->addContentStream($page);
        $cs->beginText()
            ->setFont($ttfName, 14)
            ->moveTextPosition(72, 720)
            ->showText('Embedded TrueType font security test')
            ->endText();

        $outPath = self::OUTPUT_DIR . '/pdfid_embedded_font.pdf';
        $writer->save($outPath);

        self::assertFileExists($outPath);
        $this->assertQpdfValid($outPath);
        $this->assertPdfIdClean($outPath);
    }

    public function testPdfIdToolchainWorks(): void
    {
        $writer = new PdfWriter();

        $page = $writer->addPage(612, 792);
        $font = TrueTypeFont::fromFile($this->findFont());
        $fontName = $writer->addFont($font, $page)->getResourceName();

        $cs = $writer->addContentStream($page);
        $cs->beginText()
            ->setFont($fontName, 12)
            ->moveTextPosition(72, 720)
            ->showText('pdfid smoke test')
            ->endText();

        $outPath = self::OUTPUT_DIR . '/pdfid_smoke.pdf';
        $writer->save($outPath);

        self::assertFileExists($outPath);

        $rawResult = $this->runPdfIdRaw($outPath);
        $output = $rawResult instanceof DockerToolResult ? $rawResult->output : $rawResult;

        self::assertNotEmpty($output, 'pdfid.py produced no output');
        self::assertStringContainsString('PDFiD', $output, 'pdfid.py output should contain PDFiD header');
    }
}
