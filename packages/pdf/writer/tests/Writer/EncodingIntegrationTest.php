<?php

declare(strict_types=1);

namespace Phpdftk\Pdf\Writer\Tests;

use Phpdftk\Pdf\Core\Font\StandardFont;
use Phpdftk\Pdf\Core\Font\Type1Font;
use Phpdftk\Pdf\Reader\PdfReader;
use Phpdftk\Pdf\Writer\Pdf;
use Phpdftk\Pdf\Writer\PdfWriter;
use Phpdftk\Tests\Support\QpdfValidationTrait;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * End-to-end check that UTF-8 strings handed to a WinAnsi-encoded font
 * land as the right single bytes in the content stream, and round-trip
 * back to UTF-8 through PdfReader.
 */
#[Group("qpdf")]
class EncodingIntegrationTest extends TestCase
{
    use QpdfValidationTrait;

    private const OUTPUT_FILE = __DIR__ . '/../../../core/tests/output/encoding-roundtrip.pdf';

    public function testWinAnsiRoundTripFromShowText(): void
    {
        $writer = new PdfWriter(compressStreams: false);
        $page = $writer->addPage(612, 792);
        $font = $writer->addFont(new Type1Font(StandardFont::Helvetica));

        $cs = $writer->addContentStream($page);
        $cs->beginText()
            ->setFont($font, 12)
            ->moveTextPosition(72, 720)
            ->showText("caf\u{00E9} \u{2014} r\u{00E9}sum\u{00E9} \u{00B7} 20\u{00D7} 20")
            ->endText();

        $bytes = $writer->toBytes();

        self::assertStringStartsWith('%PDF-', $bytes);
        // Exact byte sequence: é=0xE9, em-dash=0x97, middle-dot=0xB7, multiply=0xD7.
        self::assertStringContainsString(
            "(caf\xE9 \x97 r\xE9sum\xE9 \xB7 20\xD7 20) Tj",
            $bytes,
        );
        $this->assertQpdfValidBytes($bytes);
        $this->assertSame([], $writer->getEncodingWarnings());
    }

    public function testHighLevelPdfRoundTrip(): void
    {
        $dir = dirname(self::OUTPUT_FILE);
        if (!is_dir($dir)) {
            mkdir($dir, 0o755, true);
        }

        $pdf = new Pdf();
        $pdf->addText("caf\u{00E9} \u{2014} r\u{00E9}sum\u{00E9} \u{00B7} 20\u{00D7} 20");
        $pdf->save(self::OUTPUT_FILE);

        $reader = PdfReader::fromFile(self::OUTPUT_FILE);
        $extracted = $reader->extractText(0);
        $this->assertStringContainsString("caf\u{00E9}", $extracted);
        $this->assertStringContainsString("\u{2014}", $extracted);
        $this->assertStringContainsString("r\u{00E9}sum\u{00E9}", $extracted);
        $this->assertStringContainsString("\u{00B7}", $extracted);
        $this->assertStringContainsString("20\u{00D7}", $extracted);
        $this->assertSame([], $pdf->getEncodingWarnings());
    }
}
