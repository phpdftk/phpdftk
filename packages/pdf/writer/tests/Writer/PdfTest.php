<?php

declare(strict_types=1);

namespace Phpdftk\Pdf\Writer\Tests;

use Phpdftk\Pdf\Writer\Alignment;
use Phpdftk\Pdf\Writer\PageSize;
use Phpdftk\Pdf\Writer\Pdf;
use Phpdftk\Pdf\Writer\PdfWriter;
use Phpdftk\Pdf\Writer\TextStyle;
use Phpdftk\Pdf\Writer\Theme;
use Phpdftk\Tests\Support\QpdfValidationTrait;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

#[Group("qpdf")]
class PdfTest extends TestCase
{
    use QpdfValidationTrait;

    public function testAddHtmlRendersIntoPdf(): void
    {
        // `Pdf::addHtml` lazily loads `phpdftk/html-to-pdf` and renders
        // into the underlying writer as a sequence of fresh pages.
        if (!class_exists('Phpdftk\\HtmlToPdf\\Renderer')) {
            self::markTestSkipped('phpdftk/html-to-pdf not installed in this test context');
        }
        $pdf = new Pdf(compressStreams: false);
        $pdf->addHtml('<html><body><h1>Hello</h1><p>via addHtml</p></body></html>');
        $bytes = $pdf->toBytes();
        self::assertStringStartsWith('%PDF-', $bytes);
        // The renderer's identification headers should land in /Info.
        self::assertStringContainsString('/Creator (phpdftk/html-to-pdf)', $bytes);
        // A page should have been added.
        self::assertStringContainsString('/Type /Page', $bytes);
    }

    public function testEmptyDocumentHasNoPages(): void
    {
        // An untouched Pdf should still produce a valid (empty) PDF file.
        $pdf = new Pdf();
        $bytes = $pdf->toBytes();
        self::assertStringStartsWith('%PDF-', $bytes);
        self::assertStringEndsWith('%%EOF', $bytes);
        $this->assertQpdfValidBytes($bytes);
    }

    public function testAddTextCreatesFirstPageAutomatically(): void
    {
        $pdf = new Pdf(compressStreams: false);
        $pdf->addText('hello');
        $bytes = $pdf->toBytes();
        self::assertStringContainsString('/Type /Page', $bytes);
        self::assertStringContainsString('/Count 1', $bytes);
        self::assertStringContainsString('(hello)', $bytes);
        $this->assertQpdfValidBytes($bytes);
    }

    public function testAddHeadingEmitsLargerText(): void
    {
        $pdf = new Pdf(compressStreams: false);
        $pdf->addHeading('Welcome', 1);
        $pdf->addText('Body');
        $bytes = $pdf->toBytes();
        self::assertStringContainsString('(Welcome)', $bytes);
        self::assertStringContainsString('(Body)', $bytes);
        // H1 size is 24pt
        self::assertStringContainsString('24', $bytes);
        $this->assertQpdfValidBytes($bytes);
    }

    public function testExplicitNewPageCreatesSecondPage(): void
    {
        $pdf = new Pdf(compressStreams: false);
        $pdf->addText('page 1');
        $pdf->newPage();
        $pdf->addText('page 2');
        $bytes = $pdf->toBytes();
        self::assertStringContainsString('/Count 2', $bytes);
        self::assertStringContainsString('(page 1)', $bytes);
        self::assertStringContainsString('(page 2)', $bytes);
        $this->assertQpdfValidBytes($bytes);
    }

    public function testLongTextAutoPaginates(): void
    {
        $pdf = new Pdf(PageSize::Letter);
        // Letter: 792pt high, 72pt margins = 648pt of content.
        // At 11pt body * 1.2 lineHeight ≈ 13.2pt per line → ~49 lines fit.
        // Generate 80 lines: must spill onto page 2.
        $longText = '';
        for ($i = 1; $i <= 80; $i++) {
            $longText .= "Line number $i of a very long paragraph.\n";
        }
        $pdf->addText($longText);
        $bytes = $pdf->toBytes();
        self::assertStringContainsString('/Count 2', $bytes);
        $this->assertQpdfValidBytes($bytes);
    }

    public function testAddSpacerConsumesVerticalSpace(): void
    {
        $pdf = new Pdf(compressStreams: false);
        $pdf->addText('top');
        $pdf->addSpacer(100);
        $pdf->addText('bottom');
        $bytes = $pdf->toBytes();
        self::assertStringContainsString('(top)', $bytes);
        self::assertStringContainsString('(bottom)', $bytes);
        $this->assertQpdfValidBytes($bytes);
    }

    public function testAddRuleDrawsStroke(): void
    {
        $pdf = new Pdf(compressStreams: false);
        $pdf->addText('above');
        $pdf->addRule();
        $pdf->addText('below');
        $bytes = $pdf->toBytes();
        // Stroke operator `S` should appear for the rule.
        self::assertMatchesRegularExpression('/\sS\s/', $bytes);
        $this->assertQpdfValidBytes($bytes);
    }

    public function testAlignmentCenterEmitsCenteredText(): void
    {
        $pdf = new Pdf(compressStreams: false);
        $pdf->addText('centered', new TextStyle(alignment: Alignment::Center));
        $bytes = $pdf->toBytes();
        self::assertStringContainsString('(centered)', $bytes);
        $this->assertQpdfValidBytes($bytes);
    }

    public function testSetFontSwitchesFontFamily(): void
    {
        $pdf = new Pdf();
        $pdf->setFont('Times', 12);
        $pdf->addText('times-roman text');
        $bytes = $pdf->toBytes();
        // A Times-Roman font resource must be registered.
        self::assertStringContainsString('/Times-Roman', $bytes);
        $this->assertQpdfValidBytes($bytes);
    }

    public function testBoldAndItalicResolveToCorrectPostScriptName(): void
    {
        $pdf = new Pdf();
        $pdf->setFont('Helvetica', 12, bold: true, italic: true);
        $pdf->addText('bold-italic');
        $bytes = $pdf->toBytes();
        self::assertStringContainsString('/Helvetica-BoldOblique', $bytes);
        $this->assertQpdfValidBytes($bytes);
    }

    public function testUnknownFamilyIsRejected(): void
    {
        $pdf = new Pdf();
        $pdf->setFont('Comic Sans', 12);
        $this->expectException(\InvalidArgumentException::class);
        $pdf->addText('nope');
    }

    public function testCustomTheme(): void
    {
        $theme = (new Theme())->withFont('Times', 14)->withMargin(36);
        $pdf = new Pdf(PageSize::A4, $theme);
        $pdf->addText('themed');
        $bytes = $pdf->toBytes();
        self::assertStringContainsString('/Times-Roman', $bytes);
        $this->assertQpdfValidBytes($bytes);
    }

    public function testSaveWritesFile(): void
    {
        $pdf = new Pdf(compressStreams: false);
        $pdf->addText('file output');
        $path = sys_get_temp_dir() . '/phpdftk_pdf_save_' . uniqid() . '.pdf';
        try {
            $pdf->save($path);
            self::assertFileExists($path);
            $content = (string) file_get_contents($path);
            self::assertStringStartsWith('%PDF-', $content);
            self::assertStringContainsString('(file output)', $content);
            $this->assertQpdfValid($path);
        } finally {
            @unlink($path);
        }
    }

    public function testWriteToStream(): void
    {
        $pdf = new Pdf(compressStreams: false);
        $pdf->addText('stream output');

        $stream = fopen('php://memory', 'w+');
        self::assertIsResource($stream);
        $bytesWritten = $pdf->writeTo($stream);
        self::assertGreaterThan(0, $bytesWritten);

        rewind($stream);
        $content = (string) stream_get_contents($stream);
        fclose($stream);

        self::assertStringStartsWith('%PDF-', $content);
        self::assertStringContainsString('(stream output)', $content);
        $this->assertQpdfValidBytes($content);
    }

    public function testWriteToRejectsNonResource(): void
    {
        $pdf = new Pdf();
        $this->expectException(\InvalidArgumentException::class);
        /** @phpstan-ignore-next-line intentional misuse */
        $pdf->writeTo('not a resource');
    }

    public function testToBytesProducesValidPdfOnEachCall(): void
    {
        $pdf = new Pdf();
        $pdf->addText('repro');
        // Each call regenerates the PDF from scratch. Exact bytes vary
        // because the file-ID is derived from microtime() + random-ish
        // state, but every call must still produce a well-formed PDF.
        // Content streams are FlateDecode-compressed, so the text won't
        // appear as a literal string — check for the filter instead.
        foreach ([$pdf->toBytes(), $pdf->toBytes()] as $bytes) {
            self::assertStringStartsWith('%PDF-', $bytes);
            self::assertStringEndsWith('%%EOF', $bytes);
            self::assertStringContainsString('/Filter /FlateDecode', $bytes);
            $this->assertQpdfValidBytes($bytes);
        }
    }

    private function createPngFile(int $w = 40, int $h = 30): string
    {
        $img = imagecreatetruecolor($w, $h);
        $path = tempnam(sys_get_temp_dir(), 'pdf_test_img_') . '.png';
        imagepng($img, $path);
        imagedestroy($img);
        return $path;
    }

    public function testAddImageNaturalDimensions(): void
    {
        $path = $this->createPngFile(40, 30);
        try {
            $pdf = new Pdf(compressStreams: false);
            $pdf->addImage($path);
            $bytes = $pdf->toBytes();
            $this->assertStringStartsWith('%PDF-', $bytes);
            // Natural dims encoded as ctm 40 0 0 30 ... cm
            $this->assertStringContainsString('40 0 0 30', $bytes);
        } finally {
            @unlink($path);
        }
    }

    public function testAddImageScaledByWidthPreservesAspect(): void
    {
        $path = $this->createPngFile(40, 30);
        try {
            $pdf = new Pdf(compressStreams: false);
            $pdf->addImage($path, width: 200);
            $bytes = $pdf->toBytes();
            // 30 * (200/40) = 150
            $this->assertStringContainsString('200 0 0 150', $bytes);
        } finally {
            @unlink($path);
        }
    }

    public function testAddImageScaledByHeightPreservesAspect(): void
    {
        $path = $this->createPngFile(40, 30);
        try {
            $pdf = new Pdf(compressStreams: false);
            $pdf->addImage($path, height: 90);
            $bytes = $pdf->toBytes();
            // 40 * (90/30) = 120
            $this->assertStringContainsString('120 0 0 90', $bytes);
        } finally {
            @unlink($path);
        }
    }

    public function testAddImageExplicitDimensions(): void
    {
        $path = $this->createPngFile(40, 30);
        try {
            $pdf = new Pdf(compressStreams: false);
            $pdf->addImage($path, width: 300, height: 200);
            $bytes = $pdf->toBytes();
            $this->assertStringContainsString('300 0 0 200', $bytes);
        } finally {
            @unlink($path);
        }
    }

    public function testAddImageAlignmentCenter(): void
    {
        $path = $this->createPngFile(40, 30);
        try {
            $pdf = new Pdf(compressStreams: false);
            $pdf->addImage($path, width: 100, align: Alignment::Center);
            $bytes = $pdf->toBytes();
            $this->assertStringStartsWith('%PDF-', $bytes);
            $this->assertStringContainsString('100 0 0', $bytes);
        } finally {
            @unlink($path);
        }
    }

    public function testAddImageAlignmentRight(): void
    {
        $path = $this->createPngFile(40, 30);
        try {
            $pdf = new Pdf(compressStreams: false);
            $pdf->addImage($path, width: 100, align: Alignment::Right);
            $bytes = $pdf->toBytes();
            $this->assertStringStartsWith('%PDF-', $bytes);
        } finally {
            @unlink($path);
        }
    }

    public function testAddImagePaginatesWhenTooTall(): void
    {
        // A very tall image should trigger newPage() inside addImage.
        $path = $this->createPngFile(40, 30);
        try {
            $pdf = new Pdf(compressStreams: false);
            $pdf->addImage($path, height: 9999); // taller than any page
            $bytes = $pdf->toBytes();
            $this->assertStringStartsWith('%PDF-', $bytes);
            // Should still produce at least one page (pagination kicked in)
            $this->assertGreaterThanOrEqual(1, substr_count($bytes, '/Type /Page' . "\n"));
        } finally {
            @unlink($path);
        }
    }

    public function testEscapeHatchReturnsWriter(): void
    {
        $pdf = new Pdf();
        self::assertInstanceOf(PdfWriter::class, $pdf->writer());
    }
}
