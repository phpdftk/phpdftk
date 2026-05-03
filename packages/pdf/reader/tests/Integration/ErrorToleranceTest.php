<?php

declare(strict_types=1);

namespace Phpdftk\Pdf\Reader\Tests\Integration;

use Phpdftk\Pdf\Reader\Exception\InvalidPdfException;
use Phpdftk\Pdf\Reader\PdfReader;
use Phpdftk\Pdf\Writer\PdfWriter;
use Phpdftk\Pdf\Core\Font\Type1Font;
use Phpdftk\Pdf\Core\Font\StandardFont;
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
            ->setFont($font->getResourceName(), 12)
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

    // --- Phase C: New robustness tests ---

    public function testMissingStartxrefFallsBackToReconstruction(): void
    {
        $pdf = $this->generateSimplePdf();

        // Remove startxref and everything after it
        $startxrefPos = strrpos($pdf, 'startxref');
        $this->assertNotFalse($startxrefPos);
        $corruptPdf = substr($pdf, 0, $startxrefPos) . "\n%%EOF";

        $reader = PdfReader::fromString($corruptPdf, '', false);
        $this->assertGreaterThan(0, $reader->getPageCount());

        $warnings = $reader->getParseWarnings();
        $hasReconstructionWarning = false;
        foreach ($warnings as $w) {
            if (str_contains($w, 'reconstruct')) {
                $hasReconstructionWarning = true;
                break;
            }
        }
        $this->assertTrue($hasReconstructionWarning, 'Expected reconstruction warning');
    }

    public function testMissingStartxrefThrowsInStrictMode(): void
    {
        $pdf = $this->generateSimplePdf();

        $startxrefPos = strrpos($pdf, 'startxref');
        $corruptPdf = substr($pdf, 0, $startxrefPos) . "\n%%EOF";

        $this->expectException(InvalidPdfException::class);
        PdfReader::fromString($corruptPdf, '', true);
    }

    public function testCorruptedXrefFallsBackToReconstruction(): void
    {
        $pdf = $this->generateSimplePdf();

        // Corrupt the xref table by replacing it with garbage,
        // but keep startxref pointing to the right place
        $xrefPos = strpos($pdf, "xref\n");
        $this->assertNotFalse($xrefPos);

        // Replace "xref\n" with "XXXX\n" so it's not recognized
        $corruptPdf = substr_replace($pdf, "XXXX\n", $xrefPos, 5);

        $reader = PdfReader::fromString($corruptPdf, '', false);
        $this->assertGreaterThan(0, $reader->getPageCount());

        $warnings = $reader->getParseWarnings();
        $hasWarning = false;
        foreach ($warnings as $w) {
            if (str_contains($w, 'reconstruct') || str_contains($w, 'failed')) {
                $hasWarning = true;
                break;
            }
        }
        $this->assertTrue($hasWarning, 'Expected warning about xref failure or reconstruction');
    }

    public function testTruncatedPdfFallsBackToReconstruction(): void
    {
        $pdf = $this->generateSimplePdf();

        // Truncate the PDF at ~80% — removes xref/trailer/startxref
        $truncateAt = (int) (strlen($pdf) * 0.6);
        $corruptPdf = substr($pdf, 0, $truncateAt);

        $reader = PdfReader::fromString($corruptPdf, '', false);
        // Should still find at least some structure
        $this->assertNotEmpty($reader->getParseWarnings());
    }

    public function testPdfWithTrailingGarbageParses(): void
    {
        $pdf = $this->generateSimplePdf();

        // Add garbage after %%EOF — common with email attachments
        $pdfWithGarbage = $pdf . str_repeat("\x00", 1024) . "GARBAGE DATA HERE";

        $reader = PdfReader::fromString($pdfWithGarbage);
        $this->assertSame('1.7', $reader->getVersion());
        $this->assertGreaterThan(0, $reader->getPageCount());
    }

    public function testPdfWithMissingEofMarkerParses(): void
    {
        $pdf = $this->generateSimplePdf();

        // Remove %%EOF marker
        $eofPos = strrpos($pdf, '%%EOF');
        if ($eofPos !== false) {
            $pdfNoEof = substr($pdf, 0, $eofPos);
        } else {
            $pdfNoEof = $pdf;
        }

        // Should still parse because startxref is present
        $reader = PdfReader::fromString($pdfNoEof);
        $this->assertGreaterThan(0, $reader->getPageCount());
    }
}
