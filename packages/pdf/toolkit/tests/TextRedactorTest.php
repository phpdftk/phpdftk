<?php

declare(strict_types=1);

namespace ApprLabs\Pdf\Toolkit\Tests;

use ApprLabs\Pdf\Core\Font\StandardFont;
use ApprLabs\Pdf\Core\Font\Type1Font;
use ApprLabs\Pdf\Reader\PdfReader;
use ApprLabs\Pdf\Toolkit\TextRedactor;
use ApprLabs\Pdf\Writer\PdfWriter;
use PHPUnit\Framework\TestCase;

class TextRedactorTest extends TestCase
{
    private function generatePdf(): string
    {
        $writer = new PdfWriter(compressStreams: false);
        $font = $writer->addFont(new Type1Font(StandardFont::Helvetica));

        $page = $writer->addPage(612, 792);
        $cs = $writer->addContentStream($page);
        $cs->beginText()
            ->setFont($font->getResourceName(), 12)
            ->moveTextPosition(72, 720)
            ->showText('This document contains sensitive information.')
            ->endText();

        return $writer->generate();
    }

    public function testRedactArea(): void
    {
        $pdf = $this->generatePdf();
        $result = TextRedactor::openString($pdf)
            ->redactArea(1, 72, 710, 200, 20)
            ->apply()
            ->toBytes();

        $this->assertStringStartsWith('%PDF', $result);
        $reader = PdfReader::fromString($result);
        $this->assertSame(1, $reader->getPageCount());
    }

    public function testRedactMultipleAreas(): void
    {
        $pdf = $this->generatePdf();
        $redactor = TextRedactor::openString($pdf)
            ->redactArea(1, 72, 710, 100, 20)
            ->redactArea(1, 200, 710, 100, 20)
            ->apply();

        $this->assertSame(2, $redactor->getRedactionCount());
        $result = $redactor->toBytes();
        $this->assertStringStartsWith('%PDF', $result);
    }

    public function testRedactByText(): void
    {
        $pdf = $this->generatePdf();
        $redactor = TextRedactor::openString($pdf)
            ->redactText('sensitive')
            ->apply();

        $this->assertGreaterThan(0, $redactor->getRedactionCount());
        $result = $redactor->toBytes();
        $this->assertStringStartsWith('%PDF', $result);
    }

    public function testRedactByPattern(): void
    {
        $pdf = $this->generatePdf();
        $redactor = TextRedactor::openString($pdf)
            ->redactPattern('/sensitive|information/')
            ->apply();

        $this->assertGreaterThanOrEqual(2, $redactor->getRedactionCount());
    }

    public function testCustomRedactionColor(): void
    {
        $pdf = $this->generatePdf();
        $result = TextRedactor::openString($pdf)
            ->setRedactionColor(1.0, 1.0, 1.0) // white
            ->redactArea(1, 72, 710, 200, 20)
            ->apply()
            ->toBytes();

        $this->assertStringStartsWith('%PDF', $result);
    }

    public function testApplyRequiredBeforeToBytes(): void
    {
        $pdf = $this->generatePdf();
        $this->expectException(\RuntimeException::class);

        TextRedactor::openString($pdf)
            ->redactArea(1, 72, 710, 200, 20)
            ->toBytes();
    }

    public function testNoRedactionsReturnsOriginal(): void
    {
        $pdf = $this->generatePdf();
        $result = TextRedactor::openString($pdf)->toBytes();
        $this->assertSame($pdf, $result);
    }

    public function testPageCount(): void
    {
        $pdf = $this->generatePdf();
        $redactor = TextRedactor::openString($pdf);
        $this->assertSame(1, $redactor->getPageCount());
    }

    public function testEscapeHatch(): void
    {
        $pdf = $this->generatePdf();
        $redactor = TextRedactor::openString($pdf);
        $this->assertInstanceOf(PdfReader::class, $redactor->getReader());
    }

    public function testSaveToFile(): void
    {
        $pdf = $this->generatePdf();
        $path = sys_get_temp_dir() . '/phpdftk_redact_test_' . uniqid() . '.pdf';

        try {
            TextRedactor::openString($pdf)
                ->redactArea(1, 72, 710, 200, 20)
                ->apply()
                ->save($path);

            $this->assertFileExists($path);
            $this->assertStringStartsWith('%PDF', file_get_contents($path));
        } finally {
            @unlink($path);
        }
    }
}
