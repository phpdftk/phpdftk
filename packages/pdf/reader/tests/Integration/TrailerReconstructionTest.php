<?php

declare(strict_types=1);

namespace Phpdftk\Pdf\Reader\Tests\Integration;

use Phpdftk\Pdf\Core\Font\StandardFont;
use Phpdftk\Pdf\Core\Font\Type1Font;
use Phpdftk\Pdf\Reader\Exception\InvalidPdfException;
use Phpdftk\Pdf\Reader\PdfReader;
use Phpdftk\Pdf\Writer\PdfWriter;
use PHPUnit\Framework\TestCase;

class TrailerReconstructionTest extends TestCase
{
    private function generateSimplePdf(int $pageCount = 1): string
    {
        $writer = new PdfWriter();
        for ($i = 0; $i < $pageCount; $i++) {
            $page = $writer->addPage(612, 792);
            $font = $writer->addFont(new Type1Font(StandardFont::Helvetica), $page);
            $cs = $writer->addContentStream($page);
            $cs->beginText()
                ->setFont($font->getResourceName(), 12)
                ->moveTextPosition(72, 720)
                ->showText('Page ' . ($i + 1))
                ->endText();
        }
        return $writer->toBytes();
    }

    private function corruptXref(string $pdf): string
    {
        // Replace "xref\n" with "XXXX\n" to corrupt the xref table
        $pos = strrpos($pdf, "xref\n");
        if ($pos === false) {
            // Try with \r\n
            $pos = strrpos($pdf, "xref\r\n");
            if ($pos !== false) {
                return substr_replace($pdf, "XXXX\r\n", $pos, 6);
            }
            $this->fail('Could not find xref in generated PDF');
        }
        return substr_replace($pdf, "XXXX\n", $pos, 5);
    }

    private function corruptStartxref(string $pdf): string
    {
        // Change the startxref offset to an invalid value
        if (preg_match('/startxref\s+(\d+)/', $pdf, $m, PREG_OFFSET_CAPTURE)) {
            $offsetPos = $m[1][1];
            $offsetLen = strlen($m[1][0]);
            return substr_replace($pdf, '99999', $offsetPos, $offsetLen);
        }
        $this->fail('Could not find startxref in generated PDF');
    }

    public function testReconstructsCorruptedXref(): void
    {
        $pdf = $this->generateSimplePdf(2);
        $corrupt = $this->corruptXref($pdf);

        $reader = PdfReader::fromString($corrupt, '', false);

        $this->assertSame(2, $reader->getPageCount());
    }

    public function testReconstructsCorruptedStartxref(): void
    {
        $pdf = $this->generateSimplePdf(1);
        $corrupt = $this->corruptStartxref($pdf);

        $reader = PdfReader::fromString($corrupt, '', false);

        $this->assertSame(1, $reader->getPageCount());
    }

    public function testReconstructionEmitsWarning(): void
    {
        $pdf = $this->generateSimplePdf(1);
        $corrupt = $this->corruptXref($pdf);

        $reader = PdfReader::fromString($corrupt, '', false);

        $warnings = $reader->getParseWarnings();
        $this->assertNotEmpty($warnings);
        $found = false;
        foreach ($warnings as $warning) {
            if (str_contains($warning, 'xref table reconstructed from object scan')) {
                $found = true;
                break;
            }
        }
        $this->assertTrue($found, 'Expected reconstruction warning in parse warnings');
    }

    public function testStrictModeStillThrowsOnCorruptXref(): void
    {
        $pdf = $this->generateSimplePdf(1);
        $corrupt = $this->corruptXref($pdf);

        $this->expectException(InvalidPdfException::class);
        PdfReader::fromString($corrupt, '', true);
    }

    public function testReconstructionFindsCorrectCatalog(): void
    {
        $pdf = $this->generateSimplePdf(1);
        $corrupt = $this->corruptXref($pdf);

        $reader = PdfReader::fromString($corrupt, '', false);

        $catalog = $reader->getCatalog();
        $this->assertNotNull($catalog->get('Pages'), 'Reconstructed catalog should have /Pages');
    }
}
