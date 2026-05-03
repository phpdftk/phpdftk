<?php

declare(strict_types=1);

namespace Phpdftk\Pdf\Writer\Tests\Writer;

use Phpdftk\FontParser\OpenTypeParser;
use Phpdftk\Pdf\Writer\PdfWriter;
use Phpdftk\Tests\Support\QpdfValidationTrait;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

#[Group("qpdf")]
class KerningIntegrationTest extends TestCase
{
    use QpdfValidationTrait;

    private static ?string $otfPath = null;

    public static function setUpBeforeClass(): void
    {
        $candidates = [
            '/System/Library/Fonts/Supplemental/STIXGeneral.otf',
            '/System/Library/Fonts/LastResort.otf',
            '/usr/share/fonts/opentype/stix/STIXGeneral.otf',
        ];
        foreach ($candidates as $path) {
            if (file_exists($path)) {
                $bytes = file_get_contents($path);
                if ($bytes !== false && substr($bytes, 0, 4) === 'OTTO') {
                    self::$otfPath = $path;
                    return;
                }
            }
        }

        $dirs = ['/System/Library/Fonts/Supplemental', '/usr/share/fonts'];
        foreach ($dirs as $dir) {
            if (!is_dir($dir)) continue;
            foreach (glob("$dir/*.otf") ?: [] as $file) {
                $bytes = file_get_contents($file);
                if ($bytes !== false && substr($bytes, 0, 4) === 'OTTO') {
                    self::$otfPath = $file;
                    return;
                }
            }
        }
    }

    public function testGeneratePdfWithKernedOpenTypeFont(): void
    {
        if (self::$otfPath === null) {
            $this->markTestSkipped('No OpenType CFF font found');
        }

        $data = (new OpenTypeParser(self::$otfPath))->parse();
        $text = 'AV To WA';
        $codepoints = [];
        foreach (mb_str_split($text) as $ch) {
            $codepoints[] = mb_ord($ch);
        }
        $codepoints = array_unique($codepoints);

        $writer = new PdfWriter();
        $page = $writer->addPage();
        $fontHandle = $writer->addOpenTypeFont($data, $codepoints, $page);
        $fontName = $fontHandle->getResourceName();

        $cs = $writer->addContentStream($page);
        $cs->beginText()
            ->setFont($fontName, 24)
            ->moveTextPosition(72, 700);

        if ($data->kernPairs !== null && $data->kernPairs !== []) {
            $cs->showUnicodeTextKerned($text, $data->fullUnicodeToGid, $data->kernPairs, $data->unitsPerEm);
        } else {
            $cs->showUnicodeText($text, $data->fullUnicodeToGid);
        }

        $cs->endText();

        $outputDir = dirname(__DIR__, 4) . '/pdf/core/tests/output';
        if (!is_dir($outputDir)) {
            mkdir($outputDir, 0o755, true);
        }
        $outputPath = $outputDir . '/kerned_opentype.pdf';
        $writer->save($outputPath);

        $this->assertFileExists($outputPath);
        $content = file_get_contents($outputPath);
        $this->assertStringStartsWith('%PDF', $content);
        $this->assertGreaterThan(0, filesize($outputPath));
        $this->assertQpdfValid($outputPath);
    }

    public function testKernedPdfContainsTjOperator(): void
    {
        if (self::$otfPath === null) {
            $this->markTestSkipped('No OpenType CFF font found');
        }

        $data = (new OpenTypeParser(self::$otfPath))->parse();
        if ($data->kernPairs === null || $data->kernPairs === []) {
            $this->markTestSkipped('Font has no kerning data');
        }

        $text = 'AV';
        $codepoints = [];
        foreach (mb_str_split($text) as $ch) {
            $codepoints[] = mb_ord($ch);
        }

        $writer = new PdfWriter();
        $page = $writer->addPage();
        $fontHandle = $writer->addOpenTypeFont($data, $codepoints, $page);
        $fontName = $fontHandle->getResourceName();

        $cs = $writer->addContentStream($page);
        $cs->beginText()
            ->setFont($fontName, 24)
            ->moveTextPosition(72, 700)
            ->showUnicodeTextKerned($text, $data->fullUnicodeToGid, $data->kernPairs, $data->unitsPerEm)
            ->endText();

        $pdfBytes = $writer->toBytes();
        $this->assertStringStartsWith('%PDF', $pdfBytes);
        $this->assertQpdfValidBytes($pdfBytes);

        // Check if kern adjustment was applied — the PDF should contain TJ if the
        // font has an AV kern pair, otherwise Tj
        $aGid = $data->fullUnicodeToGid[0x41] ?? null;
        $vGid = $data->fullUnicodeToGid[0x56] ?? null;
        if ($aGid !== null && $vGid !== null && isset($data->kernPairs[$aGid][$vGid])) {
            $this->assertStringContainsString('TJ', $pdfBytes);
        } else {
            $this->assertStringContainsString('Tj', $pdfBytes);
        }
    }
}
