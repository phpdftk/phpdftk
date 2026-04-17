<?php

declare(strict_types=1);

namespace ApprLabs\Pdf\Reader\Tests\Integration;

use ApprLabs\Pdf\Reader\Exception\InvalidPdfException;
use ApprLabs\Pdf\Reader\PdfReader;
use ApprLabs\Pdf\Writer\PdfWriter;
use ApprLabs\Pdf\Core\Font\Type1Font;
use ApprLabs\Pdf\Core\Font\StandardFont;
use PHPUnit\Framework\TestCase;

class ErrorToleranceTest extends TestCase
{
    private function generateSimplePdf(): string
    {
        $writer = new PdfWriter();
        $page = $writer->addPage(612, 792);
        $font = $writer->addFont(new Type1Font(StandardFont::Helvetica));
        $cs = $writer->addContentStream($page);
        $cs->beginText()
            ->setFont($font, 12)
            ->moveTextPosition(72, 720)
            ->showText('Test')
            ->endText();
        return $writer->toBytes();
    }

    public function testValidPdfProducesNoWarnings(): void
    {
        $pdf = $this->generateSimplePdf();
        $reader = PdfReader::fromString($pdf);

        $this->assertEmpty($reader->getParseWarnings());
        $this->assertSame('1.7', $reader->getVersion());
    }

    public function testGetParseWarningsReturnsEmptyForValidPdf(): void
    {
        $pdf = $this->generateSimplePdf();
        $reader = PdfReader::fromString($pdf);

        $this->assertIsArray($reader->getParseWarnings());
        $this->assertCount(0, $reader->getParseWarnings());
    }

    public function testLenientModeWithHeaderOffset(): void
    {
        // Build a PDF where the header is not at byte 0 but all internal
        // offsets are still correct (simulating a PDF concatenated after junk
        // by a mailer/web server). We do this by injecting whitespace
        // characters before %PDF- but adjusting all xref offsets.
        $pdf = $this->generateSimplePdf();
        $garbage = "    "; // 4 bytes of whitespace
        $garbageLen = strlen($garbage);

        // Shift the internal offsets: find xref entries and startxref and add offset
        $corruptPdf = $garbage . $pdf;

        // Adjust the startxref value
        if (preg_match('/startxref\s+(\d+)/', $corruptPdf, $m, PREG_OFFSET_CAPTURE)) {
            $oldOffset = (int) $m[1][0];
            $newOffset = $oldOffset + $garbageLen;
            $corruptPdf = substr_replace(
                $corruptPdf,
                (string) $newOffset,
                $m[1][1],
                strlen($m[1][0])
            );
        }

        // Adjust all xref entry offsets (10-digit numbers followed by 5-digit gen and n/f)
        // Find xref table start
        $xrefPos = strpos($corruptPdf, "xref\n");
        if ($xrefPos !== false) {
            // Adjust each "in use" entry offset
            $result = '';
            $pos = 0;
            while (preg_match('/(\d{10}) (\d{5}) n/', $corruptPdf, $em, PREG_OFFSET_CAPTURE, $pos)) {
                $entryOffset = (int) $em[1][0];
                if ($entryOffset > 0) {
                    $adjusted = str_pad((string) ($entryOffset + $garbageLen), 10, '0', STR_PAD_LEFT);
                    $corruptPdf = substr_replace($corruptPdf, $adjusted, $em[1][1], 10);
                }
                $pos = $em[0][1] + strlen($em[0][0]);
            }
        }

        $reader = PdfReader::fromString($corruptPdf, '', false);
        $this->assertSame('1.7', $reader->getVersion());
        $this->assertNotEmpty($reader->getParseWarnings());
        $this->assertStringContainsString('header not at byte 0', $reader->getParseWarnings()[0]);
    }

    public function testStrictModeThrowsOnHeaderOffset(): void
    {
        $pdf = $this->generateSimplePdf();
        // Even a single character of garbage before %PDF- triggers strict failure
        $corruptPdf = "X" . $pdf;

        $this->expectException(InvalidPdfException::class);
        PdfReader::fromString($corruptPdf, '', true);
    }

    public function testExpandedStartxrefSearch(): void
    {
        // A normal PDF should parse fine — this tests that the expanded
        // search (trying 1024, 8192, 65536) still finds startxref
        $pdf = $this->generateSimplePdf();
        $reader = PdfReader::fromString($pdf);

        $this->assertGreaterThan(0, $reader->getPageCount());
    }

    public function testStrictModeIsDefault(): void
    {
        $pdf = $this->generateSimplePdf();
        $corruptPdf = "X" . $pdf;

        // Default strict=true should throw
        $this->expectException(InvalidPdfException::class);
        PdfReader::fromString($corruptPdf);
    }
}
