<?php

declare(strict_types=1);

namespace Phpdftk\Pdf\Core\Tests\Graphics\XObject;

use Phpdftk\Pdf\Core\Graphics\ColorSpace\DeviceRGB;
use Phpdftk\Pdf\Core\Graphics\XObject\ImageXObject;
use Phpdftk\Pdf\Core\PdfArray;
use Phpdftk\Pdf\Core\PdfDictionary;
use Phpdftk\Pdf\Core\PdfName;
use Phpdftk\Pdf\Core\PdfNumber;
use Phpdftk\Pdf\Core\PdfReference;
use Phpdftk\Pdf\Core\PdfString;
use PHPUnit\Framework\TestCase;

class ImageXObjectTest extends TestCase
{
    public function testMinimalImage(): void
    {
        $img = new ImageXObject(10, 20, 'DeviceRGB');
        $img->objectNumber = 1;
        $pdf = $img->toIndirectObject();
        $this->assertStringContainsString('/Type /XObject', $pdf);
        $this->assertStringContainsString('/Subtype /Image', $pdf);
        $this->assertStringContainsString('/Width 10', $pdf);
        $this->assertStringContainsString('/Height 20', $pdf);
        $this->assertStringContainsString('/ColorSpace /DeviceRGB', $pdf);
        $this->assertStringContainsString('/BitsPerComponent 8', $pdf);
    }

    public function testSerializableColorSpace(): void
    {
        $img = new ImageXObject(1, 1, new DeviceRGB());
        $img->objectNumber = 1;
        $pdf = $img->toIndirectObject();
        $this->assertStringContainsString('/ColorSpace', $pdf);
    }

    public function testAllOptionalFields(): void
    {
        $img = new ImageXObject(100, 100, 'DeviceGray', bitsPerComponent: 1);
        $img->objectNumber = 1;
        $img->filter = new PdfName('FlateDecode');
        $img->decodeParams = new PdfDictionary();
        $img->intent = new PdfName('Perceptual');
        $img->imageMask = false;
        $img->mask = new PdfReference(2);
        $img->sMask = new PdfReference(3);
        $img->sMaskInData = 1;
        $img->decode = new PdfArray([new PdfNumber(0), new PdfNumber(1)]);
        $img->interpolate = true;
        $img->alternates = new PdfArray([]);
        $img->nameField = new PdfName('Img1');
        $img->structParent = 4;
        $img->id = new PdfString('uniqueid');
        $img->opi = new PdfDictionary();
        $img->metadata = new PdfReference(5);
        $img->oc = new PdfReference(6);
        $img->af = new PdfArray([new PdfReference(7)]);
        $img->measure = new PdfReference(8);
        $img->ptData = new PdfReference(9);
        $img->matte = new PdfArray([new PdfNumber(0)]);

        $pdf = $img->toIndirectObject();
        foreach (['/Filter', '/DecodeParms', '/Intent /Perceptual', '/ImageMask false', '/Mask 2 0 R',
            '/SMask 3 0 R', '/SMaskInData 1', '/Decode', '/Interpolate true', '/Alternates',
            '/Name /Img1', '/StructParent 4', '/ID', '/OPI', '/Metadata 5 0 R', '/OC 6 0 R',
            '/AF', '/Measure 8 0 R', '/PtData 9 0 R', '/Matte'] as $needle) {
            $this->assertStringContainsString($needle, $pdf);
        }
    }
}
