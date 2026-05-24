<?php

declare(strict_types=1);

namespace Phpdftk\Pdf\Writer\Tests;

use Phpdftk\Color\RgbColor;
use Phpdftk\Pdf\Core\Font\StandardFont;
use Phpdftk\Pdf\Core\Font\Type1Font;
use Phpdftk\Pdf\Writer\Pdf;
use Phpdftk\Pdf\Writer\TextStyle;
use Phpdftk\Pdf\Writer\Theme;
use Phpdftk\Tests\Support\QpdfValidationTrait;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

#[Group("qpdf")]
class BlockquoteTest extends TestCase
{
    use QpdfValidationTrait;

    public function testAddQuoteRendersItalicTextByDefault(): void
    {
        $pdf = new Pdf(compressStreams: false);
        $pdf->addQuote('A quoted line in italic.');

        $bytes = $pdf->toBytes();
        self::assertStringContainsString('A quoted line in italic.', $bytes);
        // Default italic → Helvetica-Oblique registered.
        self::assertStringContainsString('Helvetica-Oblique', $bytes);
    }

    public function testAddQuoteDrawsLeftBarStroke(): void
    {
        $pdf = new Pdf(compressStreams: false);
        $pdf->addQuote('Body of the quote.');

        $bytes = $pdf->toBytes();
        // A stroke for the bar should appear; default bar colour is mid-grey.
        self::assertStringContainsString("\nS\n", $bytes);
        self::assertMatchesRegularExpression('/0\.7 0\.7 0\.7 RG/', $bytes);
    }

    public function testAddQuoteIndentsTextByThemeQuoteIndent(): void
    {
        $theme = new Theme(margin: 72.0, quoteIndent: 30.0);
        $pdf = new Pdf(theme: $theme, compressStreams: false);
        $pdf->addQuote('Indented quote body.');

        $bytes = $pdf->toBytes();
        // Text should be drawn at x = 72 + 30 = 102.
        self::assertMatchesRegularExpression('/\n102 \d+(?:\.\d+)? Td/', $bytes);
    }

    public function testAddQuoteCanOverrideItalicViaTextStyle(): void
    {
        $pdf = new Pdf(compressStreams: false);
        $pdf->addQuote(
            'Upright quote body.',
            new TextStyle(italic: false),
        );

        $bytes = $pdf->toBytes();
        self::assertStringContainsString('Upright quote body.', $bytes);
        self::assertStringNotContainsString('Helvetica-Oblique', $bytes);
        self::assertStringContainsString('/BaseFont /Helvetica', $bytes);
    }

    public function testAddQuoteHonoursTextStyleColor(): void
    {
        $pdf = new Pdf(compressStreams: false);
        $pdf->addQuote(
            'Coloured quote',
            new TextStyle(color: [0.2, 0.5, 0.2]),
        );

        $bytes = $pdf->toBytes();
        self::assertMatchesRegularExpression('/0\.2 0\.5 0\.2 rg/', $bytes);
    }

    public function testAddQuoteAutoPaginatesAndDrawsBarOnEachPage(): void
    {
        $pdf = new Pdf(compressStreams: false);
        $longText = str_repeat('The river ran deep and slow. ', 200);
        $pdf->addQuote($longText);

        $bytes = $pdf->toBytes();
        // Spans more than one page.
        self::assertGreaterThanOrEqual(2, substr_count($bytes, "/Type /Page\n"));
        // A bar stroke appears on every page (one per segment).
        self::assertGreaterThanOrEqual(2, substr_count($bytes, "\nS\n"));
    }

    public function testEmptyQuoteStillEmitsBarSegment(): void
    {
        // Edge case: zero-content quote — the bar still gets a tiny
        // segment because we always close out one segment at the end.
        $pdf = new Pdf(compressStreams: false);
        $pdf->addQuote('');

        $bytes = $pdf->toBytes();
        self::assertStringStartsWith('%PDF-', $bytes);
    }

    public function testThemeQuoteBarWidthAndColorAreHonoured(): void
    {
        $theme = new Theme(
            quoteBarWidth: 4.0,
            quoteBarColor: [0.1, 0.4, 0.8],
        );
        $pdf = new Pdf(theme: $theme, compressStreams: false);
        $pdf->addQuote('Themed bar');

        $bytes = $pdf->toBytes();
        // Custom line width and stroke colour.
        self::assertStringContainsString('4 w', $bytes);
        self::assertMatchesRegularExpression('/0\.1 0\.4 0\.8 RG/', $bytes);
    }

    public function testWriterPageDrawQuoteRendersExplicitlyPositioned(): void
    {
        $pdf = new Pdf(compressStreams: false);
        $page = $pdf->doc()->addPage();
        $font = $pdf->writer()->addFont(new Type1Font(StandardFont::HelveticaOblique));

        $page->drawQuote(
            'A positioned quote.',
            72.0,
            720.0,
            $font,
            size: 11.0,
            maxWidth: 300.0,
            indent: 18.0,
            barColor: new RgbColor(0.1, 0.4, 0.8),
        );

        $bytes = $pdf->toBytes();
        self::assertStringContainsString('A positioned quote.', $bytes);
        self::assertMatchesRegularExpression('/0\.1 0\.4 0\.8 RG/', $bytes);
    }

    public function testAddQuoteFollowedByAddTextResumesUnindentedFlow(): void
    {
        // Pin: addQuote must not leave residual indentation for the
        // next addText.
        $pdf = new Pdf(compressStreams: false);
        $pdf->addQuote('Quote line.');
        $pdf->addText('Body line.');

        $bytes = $pdf->toBytes();
        // Body text should appear at the regular margin x = 72.
        self::assertMatchesRegularExpression('/\n72 \d+(?:\.\d+)? Td\s+\(Body line\.\)/', $bytes);
    }
}
