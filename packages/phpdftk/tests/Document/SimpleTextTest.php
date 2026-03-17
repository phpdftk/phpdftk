<?php

declare(strict_types=1);

namespace Phpdftk\Tests\Document;

use PHPUnit\Framework\TestCase;
use Phpdftk\Font\StandardFont;
use Phpdftk\Font\Type1Font;
use Phpdftk\Writer\PdfWriter;

/**
 * Generates a multi-page PDF with simple text content and verifies
 * that the output is a valid PDF file.
 */
class SimpleTextTest extends TestCase
{
    private const OUTPUT_FILE = __DIR__ . '/../output/simple_text.pdf';

    public function testGeneratesSimpleTextPdf(): void
    {
        $writer = new PdfWriter();

        // ----------------------------------------------------------------
        // Page 1: "Hello World" in Helvetica 12pt
        // ----------------------------------------------------------------
        $page1 = $writer->addPage(612, 792);
        $fontName = $writer->addFont(new Type1Font(StandardFont::Helvetica));

        $cs1 = $writer->addContentStream($page1);
        $cs1->beginText()
            ->setFont($fontName, 12)
            ->moveTextPosition(72, 720)
            ->showText('Hello World')
            ->endText();

        // ----------------------------------------------------------------
        // Page 2: Multiple text lines at different positions
        // ----------------------------------------------------------------
        $page2 = $writer->addPage(612, 792);
        // Font was already added and applied to all pages

        $cs2 = $writer->addContentStream($page2);
        $cs2->beginText()
            ->setFont($fontName, 14)
            ->moveTextPosition(72, 700)
            ->showText('Line 1: Top of page')
            ->moveTextPosition(0, -20)
            ->showText('Line 2: Second line down')
            ->moveTextPosition(0, -20)
            ->showText('Line 3: Third line')
            ->moveTextPosition(0, -40)
            ->setFont($fontName, 10)
            ->showText('Line 4: Smaller font size')
            ->endText();

        // ----------------------------------------------------------------
        // Page 3: Text in Courier font
        // ----------------------------------------------------------------
        $page3 = $writer->addPage(612, 792);
        $courierName = $writer->addFont(new Type1Font(StandardFont::Courier));

        $cs3 = $writer->addContentStream($page3);
        $cs3->beginText()
            ->setFont($courierName, 12)
            ->moveTextPosition(72, 720)
            ->showText('Courier: monospaced text')
            ->moveTextPosition(0, -18)
            ->showText('const CODE = "example";')
            ->moveTextPosition(0, -18)
            ->showText('function foo() { return 42; }')
            ->endText();

        // ----------------------------------------------------------------
        // Save and validate
        // ----------------------------------------------------------------
        $writer->save(self::OUTPUT_FILE);

        self::assertFileExists(self::OUTPUT_FILE);

        $content = file_get_contents(self::OUTPUT_FILE);
        self::assertNotFalse($content);
        self::assertStringStartsWith('%PDF-', $content);
        self::assertStringContainsString('%%EOF', $content);
        self::assertStringContainsString('xref', $content);
        self::assertStringContainsString('trailer', $content);
        self::assertStringContainsString('startxref', $content);
        // Verify page count in page tree
        self::assertStringContainsString('/Count 3', $content);
    }
}
