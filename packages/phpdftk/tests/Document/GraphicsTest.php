<?php

declare(strict_types=1);

namespace Phpdftk\Tests\Document;

use PHPUnit\Framework\TestCase;
use Phpdftk\Font\StandardFont;
use Phpdftk\Font\Type1Font;
use Phpdftk\Writer\PdfWriter;

/**
 * Generates a PDF with various graphics content (shapes, colors, paths).
 */
class GraphicsTest extends TestCase
{
    private const OUTPUT_FILE = __DIR__ . '/../output/graphics.pdf';

    public function testGeneratesGraphicsPdf(): void
    {
        $writer = new PdfWriter();
        $page   = $writer->addPage(612, 792);
        $writer->addFont(new Type1Font(StandardFont::Helvetica));

        $cs = $writer->addContentStream($page);

        // Title
        $cs->beginText()
           ->setFont('F1', 16)
           ->moveTextPosition(72, 750)
           ->showText('Graphics Test Page')
           ->endText();

        // ----------------------------------------------------------------
        // Filled rectangles with different colors
        // ----------------------------------------------------------------

        // RGB red fill
        $cs->saveGraphicsState()
           ->setFillColorRGB(1.0, 0.0, 0.0)
           ->setStrokeColorRGB(0.0, 0.0, 0.0)
           ->setLineWidth(1.0)
           ->rectangle(72, 650, 100, 50)
           ->fillAndStroke()
           ->restoreGraphicsState();

        // RGB green fill
        $cs->saveGraphicsState()
           ->setFillColorRGB(0.0, 0.8, 0.0)
           ->rectangle(200, 650, 100, 50)
           ->fill()
           ->restoreGraphicsState();

        // RGB blue fill
        $cs->saveGraphicsState()
           ->setFillColorRGB(0.0, 0.0, 1.0)
           ->rectangle(330, 650, 100, 50)
           ->fill()
           ->restoreGraphicsState();

        // Gray fill
        $cs->saveGraphicsState()
           ->setFillColorGray(0.5)
           ->rectangle(72, 580, 100, 50)
           ->fill()
           ->restoreGraphicsState();

        // CMYK fill (cyan)
        $cs->saveGraphicsState()
           ->setFillColorCMYK(1.0, 0.0, 0.0, 0.0)
           ->rectangle(200, 580, 100, 50)
           ->fill()
           ->restoreGraphicsState();

        // CMYK fill (magenta)
        $cs->saveGraphicsState()
           ->setFillColorCMYK(0.0, 1.0, 0.0, 0.0)
           ->rectangle(330, 580, 100, 50)
           ->fill()
           ->restoreGraphicsState();

        // ----------------------------------------------------------------
        // Stroked paths
        // ----------------------------------------------------------------

        // Thick black line
        $cs->saveGraphicsState()
           ->setStrokeColorRGB(0.0, 0.0, 0.0)
           ->setLineWidth(3.0)
           ->moveTo(72, 540)
           ->lineTo(540, 540)
           ->stroke()
           ->restoreGraphicsState();

        // Dashed line
        $cs->saveGraphicsState()
           ->setStrokeColorRGB(0.5, 0.5, 0.5)
           ->setLineWidth(1.5)
           ->setDashPattern([6, 3], 0)
           ->moveTo(72, 510)
           ->lineTo(540, 510)
           ->stroke()
           ->restoreGraphicsState();

        // Triangle (closed path)
        $cs->saveGraphicsState()
           ->setStrokeColorRGB(1.0, 0.0, 0.5)
           ->setFillColorRGB(1.0, 0.9, 0.9)
           ->setLineWidth(2.0)
           ->moveTo(72, 460)
           ->lineTo(172, 460)
           ->lineTo(122, 500)
           ->closePath()
           ->fillAndStroke()
           ->restoreGraphicsState();

        // ----------------------------------------------------------------
        // Bezier curves
        // ----------------------------------------------------------------

        // Smooth S-curve
        $cs->saveGraphicsState()
           ->setStrokeColorRGB(0.0, 0.5, 1.0)
           ->setLineWidth(2.0)
           ->moveTo(200, 480)
           ->curveTo(230, 510, 270, 450, 300, 480)
           ->curveTo(330, 510, 370, 450, 400, 480)
           ->stroke()
           ->restoreGraphicsState();

        // ----------------------------------------------------------------
        // Clipping path — draw a circle-shaped clip region then fill it
        // ----------------------------------------------------------------
        $cs->saveGraphicsState();

        // Approximate a circle with 4 Bezier curves
        $cx = 480.0;
        $cy = 460.0;
        $r  = 40.0;
        $k  = 0.5523; // magic number for circular Bezier approximation

        $cs->moveTo($cx, $cy + $r)
           ->curveTo($cx + $k * $r, $cy + $r, $cx + $r, $cy + $k * $r, $cx + $r, $cy)
           ->curveTo($cx + $r, $cy - $k * $r, $cx + $k * $r, $cy - $r, $cx, $cy - $r)
           ->curveTo($cx - $k * $r, $cy - $r, $cx - $r, $cy - $k * $r, $cx - $r, $cy)
           ->curveTo($cx - $r, $cy + $k * $r, $cx - $k * $r, $cy + $r, $cx, $cy + $r)
           ->clip()
           ->endPath();

        // Fill the clipped region with gradient-like stripes
        for ($i = 0; $i < 10; $i++) {
            $shade = $i / 10.0;
            $cs->setFillColorRGB($shade, 0.0, 1.0 - $shade)
               ->rectangle($cx - $r + ($i * ($r * 2 / 10)), $cy - $r, $r * 2 / 10, $r * 2)
               ->fill();
        }

        $cs->restoreGraphicsState();

        // Labels
        $cs->beginText()
           ->setFont('F1', 9)
           ->moveTextPosition(72, 635)
           ->showText('RGB fills')
           ->moveTextPosition(0, -70)
           ->showText('CMYK fills')
           ->endText();

        // ----------------------------------------------------------------
        // Save and validate
        // ----------------------------------------------------------------
        $writer->save(self::OUTPUT_FILE);

        self::assertFileExists(self::OUTPUT_FILE);

        $content = file_get_contents(self::OUTPUT_FILE);
        self::assertNotFalse($content);

        // Use str_contains for binary-safe checks (the file has binary comment bytes)
        self::assertTrue(str_starts_with($content, '%PDF-'), 'PDF must start with %PDF-');
        self::assertTrue(str_contains($content, '%%EOF'), 'PDF must contain %%EOF');
        // Verify graphics operators appear in the content stream
        self::assertTrue(str_contains($content, 'rg'), 'Must contain fill color RGB operator');
        self::assertTrue(str_contains($content, 'RG'), 'Must contain stroke color RGB operator');
        self::assertTrue(str_contains($content, ' re'), 'Must contain rectangle operator');
        self::assertTrue(str_contains($content, ' m'), 'Must contain moveTo operator');
        self::assertTrue(str_contains($content, ' l'), 'Must contain lineTo operator');
        self::assertTrue(str_contains($content, ' c'), 'Must contain curveTo operator');
        self::assertTrue(str_contains($content, "\nW\n"), 'Must contain clip operator');
    }
}
