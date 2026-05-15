<?php

declare(strict_types=1);

namespace Phpdftk\Pdf\Core\Tests\Font;

use Phpdftk\Pdf\Core\Font\FontDescriptor;
use Phpdftk\Pdf\Core\PdfArray;
use Phpdftk\Pdf\Core\PdfDictionary;
use Phpdftk\Pdf\Core\PdfName;
use Phpdftk\Pdf\Core\PdfNumber;
use Phpdftk\Pdf\Core\PdfReference;
use Phpdftk\Pdf\Core\PdfString;
use PHPUnit\Framework\TestCase;

class FontDescriptorTest extends TestCase
{
    public function testMinimalFontDescriptor(): void
    {
        $fd = new FontDescriptor(new PdfName('Helvetica'));
        $pdf = $fd->toPdf();

        $this->assertStringContainsString('/Type /FontDescriptor', $pdf);
        $this->assertStringContainsString('/FontName /Helvetica', $pdf);
        $this->assertStringContainsString('/Flags 0', $pdf);
        $this->assertStringContainsString('/ItalicAngle 0', $pdf);
        // Optional fields not set
        $this->assertStringNotContainsString('/Ascent', $pdf);
        $this->assertStringNotContainsString('/FontFamily', $pdf);
    }

    public function testAllFieldsSet(): void
    {
        $fd = new FontDescriptor(new PdfName('CustomFont'));
        $fd->fontFamily = new PdfString('Custom Family');
        $fd->fontStretch = new PdfName('Normal');
        $fd->fontWeight = 700;
        $fd->flags = 4;
        $fd->fontBBox = new PdfArray([
            new PdfNumber(-100),
            new PdfNumber(-200),
            new PdfNumber(1100),
            new PdfNumber(900),
        ]);
        $fd->italicAngle = -12.5;
        $fd->ascent = 850.0;
        $fd->descent = -150.0;
        $fd->leading = 100.0;
        $fd->capHeight = 700.0;
        $fd->xHeight = 500.0;
        $fd->stemV = 80.0;
        $fd->stemH = 70.0;
        $fd->avgWidth = 450.0;
        $fd->maxWidth = 1000.0;
        $fd->missingWidth = 250.0;
        $fd->fontFile = new PdfReference(10);
        $fd->fontFile2 = new PdfReference(11);
        $fd->fontFile3 = new PdfReference(12);
        $fd->charSet = new PdfString('/A/B/C');
        $fd->style = new PdfDictionary();
        $fd->lang = new PdfString('en-US');
        $fd->fd = new PdfDictionary();
        $fd->cidSet = new PdfReference(13);

        $pdf = $fd->toPdf();

        $this->assertStringContainsString('/FontFamily', $pdf);
        $this->assertStringContainsString('/FontStretch /Normal', $pdf);
        $this->assertStringContainsString('/FontWeight 700', $pdf);
        $this->assertStringContainsString('/Flags 4', $pdf);
        $this->assertStringContainsString('/FontBBox', $pdf);
        $this->assertStringContainsString('/ItalicAngle -12.5', $pdf);
        $this->assertStringContainsString('/Ascent 850', $pdf);
        $this->assertStringContainsString('/Descent -150', $pdf);
        $this->assertStringContainsString('/Leading 100', $pdf);
        $this->assertStringContainsString('/CapHeight 700', $pdf);
        $this->assertStringContainsString('/XHeight 500', $pdf);
        $this->assertStringContainsString('/StemV 80', $pdf);
        $this->assertStringContainsString('/StemH 70', $pdf);
        $this->assertStringContainsString('/AvgWidth 450', $pdf);
        $this->assertStringContainsString('/MaxWidth 1000', $pdf);
        $this->assertStringContainsString('/MissingWidth 250', $pdf);
        $this->assertStringContainsString('/FontFile 10 0 R', $pdf);
        $this->assertStringContainsString('/FontFile2 11 0 R', $pdf);
        $this->assertStringContainsString('/FontFile3 12 0 R', $pdf);
        $this->assertStringContainsString('/CharSet', $pdf);
        $this->assertStringContainsString('/Style', $pdf);
        $this->assertStringContainsString('/Lang', $pdf);
        $this->assertStringContainsString('/FD', $pdf);
        $this->assertStringContainsString('/CIDSet 13 0 R', $pdf);
    }

    public function testZeroMetricsAreOmitted(): void
    {
        $fd = new FontDescriptor(new PdfName('Zeros'));
        // Explicitly leave all numeric metrics at 0 (default).
        $pdf = $fd->toPdf();

        $this->assertStringNotContainsString('/Ascent', $pdf);
        $this->assertStringNotContainsString('/CapHeight', $pdf);
        $this->assertStringNotContainsString('/AvgWidth', $pdf);
    }
}
