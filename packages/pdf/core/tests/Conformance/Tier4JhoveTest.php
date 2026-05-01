<?php

declare(strict_types=1);

namespace ApprLabs\Pdf\Core\Tests\Conformance;

use ApprLabs\Pdf\Core\Document\Info;
use ApprLabs\Pdf\Core\Font\StandardFont;
use ApprLabs\Pdf\Core\Font\TrueTypeFont;
use ApprLabs\Pdf\Core\Font\Type1Font;
use ApprLabs\Pdf\Core\PdfString;
use ApprLabs\Pdf\Writer\PdfWriter;
use ApprLabs\Tests\Support\DockerToolResult;
use ApprLabs\Tests\Support\JhoveValidationTrait;
use ApprLabs\Tests\Support\QpdfValidationTrait;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Tier 4 — JHOVE well-formedness and validity tests for generated PDFs.
 *
 * Validates that PDFs produced by PdfWriter pass JHOVE's PDF-hul module.
 *
 * Run with: vendor/bin/phpunit --group tier4
 */
#[Group('tier4')]
class Tier4JhoveTest extends TestCase
{
    use QpdfValidationTrait;
    use JhoveValidationTrait;

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

    public function testMinimalPdfWellFormedAndValid(): void
    {
        $writer = new PdfWriter();

        $page = $writer->addPage(612, 792);
        $font = TrueTypeFont::fromFile($this->findFont());
        $fontName = $writer->addFont($font, $page)->getResourceName();

        $cs = $writer->addContentStream($page);
        $cs->beginText()
            ->setFont($fontName, 12)
            ->moveTextPosition(72, 720)
            ->showText('Minimal JHOVE test document')
            ->endText();

        $outPath = self::OUTPUT_DIR . '/jhove_minimal.pdf';
        $writer->save($outPath);

        self::assertFileExists($outPath);
        $this->assertQpdfValid($outPath);
        $this->assertJhoveValid($outPath);
    }

    public function testMultiPagePdfWellFormedAndValid(): void
    {
        $writer = new PdfWriter();

        $fontPath = $this->findFont();

        for ($i = 1; $i <= 3; $i++) {
            $page = $writer->addPage(612, 792);
            $font = TrueTypeFont::fromFile($fontPath);
            $fontName = $writer->addFont($font, $page)->getResourceName();

            $cs = $writer->addContentStream($page);
            $cs->beginText()
                ->setFont($fontName, 12)
                ->moveTextPosition(72, 720)
                ->showText("Page {$i} of 3")
                ->endText();
        }

        $outPath = self::OUTPUT_DIR . '/jhove_multipage.pdf';
        $writer->save($outPath);

        self::assertFileExists($outPath);
        $this->assertQpdfValid($outPath);
        $this->assertJhoveValid($outPath);
    }

    public function testPdfWithMetadataWellFormedAndValid(): void
    {
        $writer = new PdfWriter();

        $info = new Info();
        $info->title = new PdfString('JHOVE Metadata Test');
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

        $outPath = self::OUTPUT_DIR . '/jhove_metadata.pdf';
        $writer->save($outPath);

        self::assertFileExists($outPath);
        $this->assertQpdfValid($outPath);
        $this->assertJhoveValid($outPath);
    }

    public function testPdfWithEmbeddedFontWellFormedAndValid(): void
    {
        $writer = new PdfWriter();

        $page = $writer->addPage(612, 792);

        // Embedded TrueType font
        $ttfFont = TrueTypeFont::fromFile($this->findFont());
        $ttfName = $writer->addFont($ttfFont, $page)->getResourceName();

        $cs = $writer->addContentStream($page);
        $cs->beginText()
            ->setFont($ttfName, 14)
            ->moveTextPosition(72, 720)
            ->showText('Embedded TrueType font test')
            ->endText();

        $outPath = self::OUTPUT_DIR . '/jhove_embedded_font.pdf';
        $writer->save($outPath);

        self::assertFileExists($outPath);
        $this->assertQpdfValid($outPath);
        $this->assertJhoveValid($outPath);
    }

    public function testJhoveToolchainWorks(): void
    {
        $writer = new PdfWriter();

        $page = $writer->addPage(612, 792);
        $font = TrueTypeFont::fromFile($this->findFont());
        $fontName = $writer->addFont($font, $page)->getResourceName();

        $cs = $writer->addContentStream($page);
        $cs->beginText()
            ->setFont($fontName, 12)
            ->moveTextPosition(72, 720)
            ->showText('JHOVE smoke test')
            ->endText();

        $outPath = self::OUTPUT_DIR . '/jhove_smoke.pdf';
        $writer->save($outPath);

        self::assertFileExists($outPath);

        $rawResult = $this->runJhoveRaw($outPath);
        $output = $rawResult instanceof DockerToolResult ? $rawResult->output : $rawResult;

        self::assertNotEmpty($output, 'JHOVE produced no output');
        self::assertStringContainsString('<jhove', $output, 'JHOVE output should contain <jhove root element');
    }
}
