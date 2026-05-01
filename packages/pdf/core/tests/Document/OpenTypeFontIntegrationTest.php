<?php

declare(strict_types=1);

namespace ApprLabs\Pdf\Core\Tests\Document;

use ApprLabs\FontParser\OpenTypeParser;
use ApprLabs\Pdf\Reader\PdfReader;
use ApprLabs\Pdf\Writer\PdfWriter;
use ApprLabs\Tests\Support\QpdfValidationTrait;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

#[Group("qpdf")]
class OpenTypeFontIntegrationTest extends TestCase
{
    use QpdfValidationTrait;

    private const OUTPUT_FILE = __DIR__ . '/../../../../../docs/sample-pdfs/opentype_cff.pdf';

    private static ?string $fontPath = null;

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
                    self::$fontPath = $path;
                    return;
                }
            }
        }
    }

    public function testGeneratesPdfWithOpenTypeCff(): void
    {
        if (self::$fontPath === null) {
            $this->markTestSkipped('No OpenType CFF font found');
        }

        $parser = new OpenTypeParser(self::$fontPath);
        $data = $parser->parse();

        $text = 'Hello, OpenType CFF World!';
        $codepoints = array_unique(array_map('mb_ord', mb_str_split($text)));

        $writer = new PdfWriter();
        $page = $writer->addPage(612, 792);
        $fontName = $writer->addOpenTypeFont($data, $codepoints)->getResourceName();

        $cs = $writer->addContentStream($page);
        $cs->beginText()
            ->setFont($fontName, 24)
            ->moveTextPosition(72, 700);

        // Build hex-encoded GID string
        $hexGids = '';
        foreach (mb_str_split($text) as $char) {
            $cp = mb_ord($char);
            $gid = $data->fullUnicodeToGid[$cp] ?? 0;
            $hexGids .= sprintf('%04X', $gid);
        }
        $cs->showTextHex($hexGids);
        $cs->endText();

        $dir = dirname(self::OUTPUT_FILE);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        $writer->save(self::OUTPUT_FILE);

        self::assertFileExists(self::OUTPUT_FILE);
        $this->assertQpdfValid(self::OUTPUT_FILE);
        $contents = file_get_contents(self::OUTPUT_FILE);
        self::assertStringStartsWith('%PDF-', $contents);
        self::assertStringContainsString('/Subtype /Type0', $contents);
        self::assertStringContainsString('/CIDFontType0C', $contents);

        // Read back
        $reader = PdfReader::fromFile(self::OUTPUT_FILE);
        self::assertSame(1, $reader->getPageCount());
    }

    public function testOpenTypeFontEmbedsCffBytes(): void
    {
        if (self::$fontPath === null) {
            $this->markTestSkipped('No OpenType CFF font found');
        }

        $data = (new OpenTypeParser(self::$fontPath))->parse();

        $writer = new PdfWriter(compressStreams: false);
        $page = $writer->addPage();
        $writer->addOpenTypeFont($data, [0x41, 0x42, 0x43]);

        $pdf = $writer->generate();

        // Should contain the CFF font program reference
        $this->assertStringContainsString('/FontFile3', $pdf);
        $this->assertStringContainsString('/Subtype /CIDFontType0C', $pdf);
    }
}
