<?php

declare(strict_types=1);

namespace Phpdftk\Pdf\Writer\Tests\Writer;

use Phpdftk\Color\CmykColor;
use Phpdftk\Color\GrayColor;
use Phpdftk\Color\RgbColor;
use Phpdftk\Pdf\Core\Font\StandardFont;
use Phpdftk\Pdf\Core\Font\Type1Font;
use Phpdftk\Pdf\Writer\DashPattern;
use Phpdftk\Pdf\Writer\Font;
use Phpdftk\Pdf\Writer\Page;
use Phpdftk\Pdf\Writer\PathBuilder;
use Phpdftk\Pdf\Writer\PdfWriter;
use Phpdftk\Pdf\Reader\PdfReader;
use PHPUnit\Framework\TestCase;

class Level1PageTest extends TestCase
{
    private function createWriter(): PdfWriter
    {
        return new PdfWriter(compressStreams: false);
    }

    private function addPageAndFont(PdfWriter $writer): array
    {
        $page = $writer->addPage(612, 792);
        $font = $writer->addFont(new Type1Font(StandardFont::Helvetica));
        return [$page, $font];
    }

    // -----------------------------------------------------------------------
    // Text
    // -----------------------------------------------------------------------

    public function testDrawTextEmitsTextOperators(): void
    {
        $writer = $this->createWriter();
        [$page, $font] = $this->addPageAndFont($writer);

        $page->drawText('Hello World', 72, 720, $font, 14);

        $pdf = $writer->generate();
        $this->assertStringContainsString('BT', $pdf);
        $this->assertStringContainsString('Hello World', $pdf);
        $this->assertStringContainsString('ET', $pdf);
    }

    public function testDrawTextWithColor(): void
    {
        $writer = $this->createWriter();
        [$page, $font] = $this->addPageAndFont($writer);

        $page->drawText('Red text', 72, 720, $font, 12, RgbColor::fromInt(255, 0, 0));

        $pdf = $writer->generate();
        $this->assertStringContainsString('1 0 0 rg', $pdf);
    }

    // -----------------------------------------------------------------------
    // Line
    // -----------------------------------------------------------------------

    public function testDrawLine(): void
    {
        $writer = $this->createWriter();
        [$page] = $this->addPageAndFont($writer);

        $page->drawLine(72, 700, 540, 700, RgbColor::fromInt(0, 0, 0), 2.0);

        $pdf = $writer->generate();
        $this->assertStringContainsString('72 700 m', $pdf);
        $this->assertStringContainsString('540 700 l', $pdf);
        $this->assertStringContainsString('S', $pdf);
    }

    public function testDrawLineWithDash(): void
    {
        $writer = $this->createWriter();
        [$page] = $this->addPageAndFont($writer);

        $page->drawLine(72, 700, 540, 700, dash: DashPattern::dashed(4, 2));

        $pdf = $writer->generate();
        $this->assertStringContainsString('[ 4 2 ] 0 d', $pdf);
    }

    // -----------------------------------------------------------------------
    // Rectangle
    // -----------------------------------------------------------------------

    public function testDrawRectangleFillAndStroke(): void
    {
        $writer = $this->createWriter();
        [$page] = $this->addPageAndFont($writer);

        $page->drawRectangle(
            100,
            600,
            200,
            100,
            fill: RgbColor::fromInt(200, 200, 255),
            stroke: RgbColor::fromInt(0, 0, 0),
        );

        $pdf = $writer->generate();
        $this->assertStringContainsString('100 600 200 100 re', $pdf);
        $this->assertStringContainsString('B', $pdf); // fill and stroke
    }

    public function testDrawRectangleFillOnly(): void
    {
        $writer = $this->createWriter();
        [$page] = $this->addPageAndFont($writer);

        $page->drawRectangle(100, 600, 200, 100, fill: GrayColor::black());

        $pdf = $writer->generate();
        $this->assertStringContainsString('re', $pdf);
        $this->assertStringContainsString('f', $pdf);
    }

    // -----------------------------------------------------------------------
    // Circle / Ellipse
    // -----------------------------------------------------------------------

    public function testDrawCircle(): void
    {
        $writer = $this->createWriter();
        [$page] = $this->addPageAndFont($writer);

        $page->drawCircle(300, 400, 50, fill: RgbColor::fromInt(0, 128, 255));

        $pdf = $writer->generate();
        // Bézier curves for circle
        $this->assertStringContainsString(' c', $pdf);
        $this->assertStringContainsString('f', $pdf);
    }

    public function testDrawEllipse(): void
    {
        $writer = $this->createWriter();
        [$page] = $this->addPageAndFont($writer);

        $page->drawEllipse(300, 400, 80, 40, stroke: RgbColor::fromInt(0, 0, 0));

        $pdf = $writer->generate();
        $this->assertStringContainsString(' c', $pdf);
        $this->assertStringContainsString('S', $pdf);
    }

    // -----------------------------------------------------------------------
    // Rounded rectangle
    // -----------------------------------------------------------------------

    public function testDrawRoundedRectangle(): void
    {
        $writer = $this->createWriter();
        [$page] = $this->addPageAndFont($writer);

        $page->drawRoundedRectangle(
            100,
            500,
            200,
            80,
            10,
            fill: RgbColor::fromInt(240, 240, 240),
            stroke: RgbColor::fromInt(0, 0, 0),
        );

        $pdf = $writer->generate();
        // Should have Bézier curves for corners + line segments
        $this->assertStringContainsString(' c', $pdf);
        $this->assertStringContainsString(' l', $pdf);
        $this->assertStringContainsString('B', $pdf);
    }

    // -----------------------------------------------------------------------
    // Polygon
    // -----------------------------------------------------------------------

    public function testDrawPolygon(): void
    {
        $writer = $this->createWriter();
        [$page] = $this->addPageAndFont($writer);

        $page->drawPolygon(
            [[100, 100], [200, 100], [150, 180]],
            fill: RgbColor::fromInt(255, 200, 0),
        );

        $pdf = $writer->generate();
        $this->assertStringContainsString('100 100 m', $pdf);
        $this->assertStringContainsString('200 100 l', $pdf);
        $this->assertStringContainsString('150 180 l', $pdf);
        $this->assertStringContainsString('h', $pdf); // close path
    }

    // -----------------------------------------------------------------------
    // Arrow
    // -----------------------------------------------------------------------

    public function testDrawArrow(): void
    {
        $writer = $this->createWriter();
        [$page] = $this->addPageAndFont($writer);

        $page->drawArrow(100, 400, 300, 400, 10, RgbColor::fromInt(0, 0, 0));

        $pdf = $writer->generate();
        // Line + arrowhead (triangle fill)
        $this->assertStringContainsString('S', $pdf); // stroke (line)
        $this->assertStringContainsString('f', $pdf); // fill (arrowhead)
    }

    // -----------------------------------------------------------------------
    // Star
    // -----------------------------------------------------------------------

    public function testDrawStar(): void
    {
        $writer = $this->createWriter();
        [$page] = $this->addPageAndFont($writer);

        $page->drawStar(
            300,
            400,
            50,
            20,
            5,
            fill: RgbColor::fromInt(255, 215, 0),
            stroke: RgbColor::fromInt(0, 0, 0),
        );

        $pdf = $writer->generate();
        // 10 vertices (5 outer + 5 inner)
        $lineCount = substr_count($pdf, ' l');
        $this->assertGreaterThanOrEqual(9, $lineCount);
        $this->assertStringContainsString('h', $pdf); // close path
    }

    // -----------------------------------------------------------------------
    // Path builder
    // -----------------------------------------------------------------------

    public function testDrawPathWithBuilder(): void
    {
        $writer = $this->createWriter();
        [$page] = $this->addPageAndFont($writer);

        $page->drawPath(
            function (PathBuilder $p) {
                $p->moveTo(100, 100)
                    ->lineTo(200, 150)
                    ->curveTo(250, 200, 300, 100, 350, 150)
                    ->close();
            },
            fill: RgbColor::fromInt(100, 200, 100),
        );

        $pdf = $writer->generate();
        $this->assertStringContainsString('100 100 m', $pdf);
        $this->assertStringContainsString('200 150 l', $pdf);
        $this->assertStringContainsString(' c', $pdf);
        $this->assertStringContainsString('h', $pdf);
    }

    // -----------------------------------------------------------------------
    // CMYK color
    // -----------------------------------------------------------------------

    public function testDrawWithCmykColor(): void
    {
        $writer = $this->createWriter();
        [$page] = $this->addPageAndFont($writer);

        $page->drawRectangle(
            100,
            600,
            200,
            100,
            fill: new CmykColor(1, 0, 0, 0), // cyan
        );

        $pdf = $writer->generate();
        $this->assertStringContainsString('1 0 0 0 k', $pdf);
    }

    // -----------------------------------------------------------------------
    // Escape hatches
    // -----------------------------------------------------------------------

    public function testContentStreamEscapeHatch(): void
    {
        $writer = $this->createWriter();
        [$page] = $this->addPageAndFont($writer);

        // Use Level 0 operators directly
        $page->raw(fn($cs) => $cs->beginText()
            ->setFont('F1', 10)
            ->moveTextPosition(72, 720)
            ->showText('Raw text')
            ->endText());

        $pdf = $writer->generate();
        $this->assertStringContainsString('Raw text', $pdf);
    }

    public function testFileWriterEscapeHatch(): void
    {
        $writer = $this->createWriter();
        $fw = $writer->fileWriter();

        $this->assertInstanceOf(\Phpdftk\Pdf\Core\File\PdfFileWriter::class, $fw);
    }

    // -----------------------------------------------------------------------
    // End-to-end: readable by PdfReader
    // -----------------------------------------------------------------------

    public function testGeneratedPdfIsReadable(): void
    {
        $writer = $this->createWriter();
        $page = $writer->addPage(612, 792);
        $font = $writer->addFont(new Type1Font(StandardFont::Helvetica));

        $page->drawText('Test document', 72, 720, $font, 14);
        $page->drawLine(72, 710, 540, 710);
        $page->drawRectangle(72, 600, 200, 80, fill: RgbColor::fromInt(230, 230, 250));
        $page->drawCircle(400, 640, 30, stroke: RgbColor::fromInt(0, 0, 0));

        $pdf = $writer->generate();
        $this->assertStringStartsWith('%PDF-', $pdf);

        $reader = PdfReader::fromString($pdf);
        $this->assertSame(1, $reader->getPageCount());
    }

    // -----------------------------------------------------------------------
    // DashPattern
    // -----------------------------------------------------------------------

    public function testDashPatternFactories(): void
    {
        $solid = DashPattern::solid();
        $this->assertSame([], $solid->pattern);

        $dashed = DashPattern::dashed(6, 3);
        $this->assertSame([6.0, 3.0], $dashed->pattern);

        $dotted = DashPattern::dotted();
        $this->assertSame([1.0, 2.0], $dotted->pattern);

        $dashDot = DashPattern::dashDot();
        $this->assertSame([6.0, 2.0, 1.0, 2.0], $dashDot->pattern);
    }

    // -----------------------------------------------------------------------
    // Font handle
    // -----------------------------------------------------------------------

    public function testFontHandle(): void
    {
        $font = new Font('F1', 'Helvetica');
        $this->assertSame('F1', $font->getResourceName());
        $this->assertSame('Helvetica', $font->getFamily());
        $this->assertNull($font->getParsedData());
    }
}
