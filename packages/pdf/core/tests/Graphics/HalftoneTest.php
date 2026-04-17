<?php

declare(strict_types=1);

namespace ApprLabs\Pdf\Core\Tests\Graphics;

use ApprLabs\Pdf\Core\Graphics\Halftone\HalftoneType1;
use ApprLabs\Pdf\Core\Graphics\Halftone\HalftoneType5;
use ApprLabs\Pdf\Core\Graphics\Halftone\HalftoneType6;
use ApprLabs\Pdf\Core\Graphics\Halftone\HalftoneType10;
use ApprLabs\Pdf\Core\Graphics\Halftone\HalftoneType16;
use ApprLabs\Pdf\Core\PdfDictionary;
use ApprLabs\Pdf\Core\PdfName;
use ApprLabs\Pdf\Core\PdfNumber;
use ApprLabs\Pdf\Core\PdfReference;
use PHPUnit\Framework\TestCase;

class HalftoneTest extends TestCase
{
    public function testHalftoneType1Serialization(): void
    {
        $ht = new HalftoneType1();
        $ht->frequency = 60.0;
        $ht->angle = 45.0;
        $ht->spotFunction = new PdfName('Round');

        $pdf = $ht->toPdf();
        $this->assertStringContainsString('/Type /Halftone', $pdf);
        $this->assertStringContainsString('/HalftoneType 1', $pdf);
        $this->assertStringContainsString('/Frequency 60', $pdf);
        $this->assertStringContainsString('/Angle 45', $pdf);
        $this->assertStringContainsString('/SpotFunction /Round', $pdf);
    }

    public function testHalftoneType1WithTransferFunction(): void
    {
        $ht = new HalftoneType1();
        $ht->frequency = 120.0;
        $ht->angle = 0.0;
        $ht->spotFunction = new PdfName('Line');
        $ht->transferFunction = new PdfName('Identity');

        $pdf = $ht->toPdf();
        $this->assertStringContainsString('/TransferFunction /Identity', $pdf);
    }

    public function testHalftoneType1WithAccurateScreens(): void
    {
        $ht = new HalftoneType1();
        $ht->frequency = 60.0;
        $ht->angle = 45.0;
        $ht->spotFunction = new PdfName('Round');
        $ht->accurateScreens = true;

        $pdf = $ht->toPdf();
        $this->assertStringContainsString('/AccurateScreens', $pdf);
    }

    public function testHalftoneType1PdfType(): void
    {
        $this->assertSame('Halftone', HalftoneType1::PDF_TYPE);
    }

    public function testHalftoneType5Serialization(): void
    {
        $ht = new HalftoneType5();
        $ht->default = new PdfReference(10);

        $colorants = new PdfDictionary([
            'Cyan' => new PdfReference(11),
            'Magenta' => new PdfReference(12),
        ]);
        $ht->colorants = $colorants;

        $pdf = $ht->toPdf();
        $this->assertStringContainsString('/Type /Halftone', $pdf);
        $this->assertStringContainsString('/HalftoneType 5', $pdf);
        $this->assertStringContainsString('/Default 10 0 R', $pdf);
        $this->assertStringContainsString('/Cyan 11 0 R', $pdf);
        $this->assertStringContainsString('/Magenta 12 0 R', $pdf);
    }

    public function testHalftoneType5WithDefaultOnly(): void
    {
        $ht = new HalftoneType5();
        $ht->default = new PdfReference(10);

        $pdf = $ht->toPdf();
        $this->assertStringContainsString('/Default 10 0 R', $pdf);
        $this->assertStringNotContainsString('/Cyan', $pdf);
    }

    public function testHalftoneType5PdfType(): void
    {
        $this->assertSame('Halftone', HalftoneType5::PDF_TYPE);
    }

    public function testHalftoneType6Serialization(): void
    {
        $thresholdData = str_repeat("\x80", 16);
        $ht = new HalftoneType6(4, 4, $thresholdData);

        $pdf = $ht->toPdf();
        $this->assertStringContainsString('/Type /Halftone', $pdf);
        $this->assertStringContainsString('/HalftoneType 6', $pdf);
        $this->assertStringContainsString('/Width 4', $pdf);
        $this->assertStringContainsString('/Height 4', $pdf);
        $this->assertStringContainsString('stream', $pdf);
        $this->assertStringContainsString('endstream', $pdf);
    }

    public function testHalftoneType6WithTransferFunction(): void
    {
        $ht = new HalftoneType6(2, 2, "\x00\x80\x80\xFF");
        $ht->transferFunction = new PdfName('Identity');

        $pdf = $ht->toPdf();
        $this->assertStringContainsString('/TransferFunction /Identity', $pdf);
    }

    public function testHalftoneType6PdfType(): void
    {
        $this->assertSame('Halftone', HalftoneType6::PDF_TYPE);
    }

    public function testHalftoneType10Serialization(): void
    {
        $ht = new HalftoneType10(8, 8, str_repeat("\x00", 64));

        $pdf = $ht->toPdf();
        $this->assertStringContainsString('/Type /Halftone', $pdf);
        $this->assertStringContainsString('/HalftoneType 10', $pdf);
        $this->assertStringContainsString('/Width 8', $pdf);
        $this->assertStringContainsString('/Height 8', $pdf);
        $this->assertStringContainsString('stream', $pdf);
    }

    public function testHalftoneType10PdfType(): void
    {
        $this->assertSame('Halftone', HalftoneType10::PDF_TYPE);
    }

    public function testHalftoneType16Serialization(): void
    {
        // 16-bit thresholds: 2 bytes per cell
        $ht = new HalftoneType16(4, 4, str_repeat("\x00\xFF", 16));

        $pdf = $ht->toPdf();
        $this->assertStringContainsString('/Type /Halftone', $pdf);
        $this->assertStringContainsString('/HalftoneType 16', $pdf);
        $this->assertStringContainsString('/Width 4', $pdf);
        $this->assertStringContainsString('/Height 4', $pdf);
        $this->assertStringContainsString('stream', $pdf);
    }

    public function testHalftoneType16WithTransferFunction(): void
    {
        $ht = new HalftoneType16(2, 2, str_repeat("\x00\x80", 4));
        $ht->transferFunction = new PdfReference(5);

        $pdf = $ht->toPdf();
        $this->assertStringContainsString('/TransferFunction 5 0 R', $pdf);
    }

    public function testHalftoneType16PdfType(): void
    {
        $this->assertSame('Halftone', HalftoneType16::PDF_TYPE);
    }

    public function testHalftoneType6AsIndirectObject(): void
    {
        $ht = new HalftoneType6(2, 2, "\x00\xFF\xFF\x00");
        $ht->objectNumber = 7;

        $indirect = $ht->toIndirectObject();
        $this->assertStringStartsWith('7 0 obj', $indirect);
        $this->assertStringContainsString('endobj', $indirect);
    }
}
