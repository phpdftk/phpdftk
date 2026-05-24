<?php

declare(strict_types=1);

namespace Phpdftk\Pdf\Writer\Tests;

use Phpdftk\Color\RgbColor;
use Phpdftk\Pdf\Core\Font\StandardFont;
use Phpdftk\Pdf\Core\Font\Type1Font;
use Phpdftk\Pdf\Writer\Pdf;
use Phpdftk\Pdf\Writer\TextStyle;
use Phpdftk\Tests\Support\QpdfValidationTrait;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

#[Group("qpdf")]
class TextDecorationTest extends TestCase
{
    use QpdfValidationTrait;

    public function testUnderlineEmitsStrokeBelowBaseline(): void
    {
        $pdf = new Pdf(compressStreams: false);
        $pdf->addText('Underlined text', new TextStyle(underline: true));

        $bytes = $pdf->toBytes();
        self::assertStringContainsString('Underlined text', $bytes);
        // A stroke op `S` should appear in the content stream for the underline.
        self::assertStringContainsString("\nS\n", $bytes);
        $this->assertQpdfValidBytes($bytes);
    }

    public function testStrikethroughEmitsStroke(): void
    {
        $pdf = new Pdf(compressStreams: false);
        $pdf->addText('Crossed-out text', new TextStyle(strikethrough: true));

        $bytes = $pdf->toBytes();
        self::assertStringContainsString('Crossed-out text', $bytes);
        self::assertStringContainsString("\nS\n", $bytes);
    }

    public function testUnderlineAndStrikethroughBothApplied(): void
    {
        $pdf = new Pdf(compressStreams: false);
        $pdf->addText(
            'Both decorations',
            new TextStyle(underline: true, strikethrough: true),
        );

        $bytes = $pdf->toBytes();
        // Two strokes per line — at least 2 `S\n` ops.
        self::assertGreaterThanOrEqual(2, substr_count($bytes, "\nS\n"));
    }

    public function testNoDecorationByDefaultEmitsNoStroke(): void
    {
        $pdf = new Pdf(compressStreams: false);
        $pdf->addText('Plain text');

        $bytes = $pdf->toBytes();
        // No underline / strikethrough → no stroke ops in body content.
        $body = $this->extractFirstContentStream($bytes);
        self::assertStringNotContainsString("\nS\n", $body);
    }

    public function testUnderlineSpansEveryWrappedLine(): void
    {
        $pdf = new Pdf(compressStreams: false);
        $longText = 'A long line that will wrap across multiple lines inside the default content '
            . 'column, demonstrating that the underline is applied per wrapped line.';
        $pdf->addText($longText, new TextStyle(underline: true));

        $bytes = $pdf->toBytes();
        // Multiple Tj operators correspond to multiple lines, each with its own underline stroke.
        $tjCount = substr_count($bytes, ') Tj');
        $strokeCount = substr_count($bytes, "\nS\n");
        self::assertGreaterThanOrEqual(2, $tjCount);
        self::assertSame(
            $tjCount,
            $strokeCount,
            'each wrapped line should produce exactly one underline stroke',
        );
    }

    public function testDecorationUsesTextFillColor(): void
    {
        $pdf = new Pdf(compressStreams: false);
        $pdf->addText(
            'Red underlined',
            new TextStyle(color: [1.0, 0.0, 0.0], underline: true),
        );

        $bytes = $pdf->toBytes();
        // Red text fill (rg) AND red stroke (RG) should both appear.
        self::assertMatchesRegularExpression('/1 0 0 rg/', $bytes);
        self::assertMatchesRegularExpression('/1 0 0 RG/', $bytes);
    }

    public function testWriterPageDrawTextUnderline(): void
    {
        $pdf = new Pdf(compressStreams: false);
        $page = $pdf->doc()->addPage();
        $font = $pdf->writer()->addFont(new Type1Font(StandardFont::Helvetica));

        $page->drawText('Positioned underlined', 72.0, 720.0, $font, 12.0, null, underline: true);

        $bytes = $pdf->toBytes();
        self::assertStringContainsString('Positioned underlined', $bytes);
        self::assertStringContainsString("\nS\n", $bytes);
    }

    public function testWriterPageDrawTextStrikethroughWithColor(): void
    {
        $pdf = new Pdf(compressStreams: false);
        $page = $pdf->doc()->addPage();
        $font = $pdf->writer()->addFont(new Type1Font(StandardFont::Helvetica));

        $page->drawText(
            'Struck',
            72.0,
            720.0,
            $font,
            14.0,
            new RgbColor(0.5, 0.0, 0.5),
            strikethrough: true,
        );

        $bytes = $pdf->toBytes();
        self::assertStringContainsString('Struck', $bytes);
        // Purple stroke
        self::assertMatchesRegularExpression('/0\.5 0 0\.5 RG/', $bytes);
    }

    public function testWriterPageDrawTextWithEmptyStringIsNoOp(): void
    {
        $pdf = new Pdf(compressStreams: false);
        $page = $pdf->doc()->addPage();
        $font = $pdf->writer()->addFont(new Type1Font(StandardFont::Helvetica));

        $page->drawText('', 72.0, 720.0, $font, 12.0, null, underline: true);

        $bytes = $pdf->toBytes();
        // Empty text shouldn't emit any text or stroke operations
        $body = $this->extractFirstContentStream($bytes);
        self::assertStringNotContainsString(') Tj', $body);
        self::assertStringNotContainsString("\nS\n", $body);
    }

    private function extractFirstContentStream(string $pdf): string
    {
        $start = strpos($pdf, "stream\n");
        $end = strpos($pdf, "\nendstream");
        if ($start === false || $end === false) {
            return '';
        }
        return substr($pdf, $start + 7, $end - $start - 7);
    }
}
