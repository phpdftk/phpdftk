<?php

declare(strict_types=1);

namespace Phpdftk\Pdf\Core\Tests\Graphics;

use Phpdftk\Pdf\Core\Graphics\ColorSpace\CalGray;
use Phpdftk\Pdf\Core\Graphics\ColorSpace\CalRGB;
use Phpdftk\Pdf\Core\Graphics\ColorSpace\DeviceN;
use Phpdftk\Pdf\Core\Graphics\ColorSpace\DeviceRGB;
use Phpdftk\Pdf\Core\Graphics\ColorSpace\ICCBased;
use Phpdftk\Pdf\Core\Graphics\ColorSpace\Indexed;
use Phpdftk\Pdf\Core\Graphics\ColorSpace\Lab;
use Phpdftk\Pdf\Core\Graphics\ColorSpace\Pattern;
use Phpdftk\Pdf\Core\Graphics\ColorSpace\Separation;
use Phpdftk\Pdf\Core\PdfArray;
use Phpdftk\Pdf\Core\PdfName;
use Phpdftk\Pdf\Core\PdfNumber;
use Phpdftk\Pdf\Core\PdfReference;
use Phpdftk\Pdf\Core\PdfString;
use PHPUnit\Framework\TestCase;

class ColorSpaceTest extends TestCase
{
    private function whitePoint(): PdfArray
    {
        return new PdfArray([
            new PdfNumber(0.9505),
            new PdfNumber(1.0),
            new PdfNumber(1.0890),
        ]);
    }

    public function testCalGray(): void
    {
        $cs = new CalGray($this->whitePoint());
        $cs->gamma = 2.2;
        $pdf = $cs->toPdf();
        self::assertStringContainsString('/CalGray', $pdf);
        self::assertStringContainsString('/WhitePoint', $pdf);
        self::assertStringContainsString('/Gamma', $pdf);
    }

    public function testCalRGB(): void
    {
        $cs = new CalRGB($this->whitePoint());
        $cs->gamma = new PdfArray([new PdfNumber(2.2), new PdfNumber(2.2), new PdfNumber(2.2)]);
        $pdf = $cs->toPdf();
        self::assertStringContainsString('/CalRGB', $pdf);
        self::assertStringContainsString('/Gamma', $pdf);
    }

    public function testLab(): void
    {
        $cs = new Lab($this->whitePoint());
        $cs->range = new PdfArray([
            new PdfNumber(-100), new PdfNumber(100),
            new PdfNumber(-100), new PdfNumber(100),
        ]);
        $pdf = $cs->toPdf();
        self::assertStringContainsString('/Lab', $pdf);
        self::assertStringContainsString('/Range', $pdf);
    }

    public function testICCBased(): void
    {
        $cs = new ICCBased(new PdfReference(5));
        self::assertStringContainsString('/ICCBased', $cs->toPdf());
        self::assertStringContainsString('5 0 R', $cs->toPdf());
    }

    public function testIndexed(): void
    {
        $cs = new Indexed(
            new DeviceRGB(),
            3,
            new PdfString(str_repeat("\x00\xFF\x00", 4), hex: true)
        );
        $pdf = $cs->toPdf();
        self::assertStringContainsString('/Indexed', $pdf);
        self::assertStringContainsString('/DeviceRGB', $pdf);
        self::assertStringContainsString('3', $pdf);
    }

    public function testPatternBareName(): void
    {
        self::assertSame('/Pattern', (new Pattern())->toPdf());
    }

    public function testPatternWithUnderlyingSpace(): void
    {
        $cs = new Pattern(new DeviceRGB());
        $pdf = $cs->toPdf();
        self::assertStringContainsString('/Pattern', $pdf);
        self::assertStringContainsString('/DeviceRGB', $pdf);
    }

    public function testSeparation(): void
    {
        $cs = new Separation(
            new PdfName('PANTONE#20185#20C'),
            new DeviceRGB(),
            new PdfReference(8)
        );
        $pdf = $cs->toPdf();
        self::assertStringContainsString('/Separation', $pdf);
        self::assertStringContainsString('8 0 R', $pdf);
    }

    public function testDeviceN(): void
    {
        $cs = new DeviceN(
            new PdfArray([new PdfName('Cyan'), new PdfName('Magenta')]),
            new DeviceRGB(),
            new PdfReference(9)
        );
        $pdf = $cs->toPdf();
        self::assertStringContainsString('/DeviceN', $pdf);
        self::assertStringContainsString('/Cyan', $pdf);
        self::assertStringContainsString('9 0 R', $pdf);
    }
}
