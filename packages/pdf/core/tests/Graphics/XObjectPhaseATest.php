<?php

declare(strict_types=1);

namespace ApprLabs\Pdf\Core\Tests\Graphics;

use ApprLabs\Pdf\Core\Content\Resources;
use ApprLabs\Pdf\Core\Graphics\XObject\FormXObject;
use ApprLabs\Pdf\Core\Graphics\XObject\ImageXObject;
use ApprLabs\Pdf\Core\PdfArray;
use ApprLabs\Pdf\Core\PdfDictionary;
use ApprLabs\Pdf\Core\PdfName;
use ApprLabs\Pdf\Core\PdfNull;
use ApprLabs\Pdf\Core\PdfNumber;
use ApprLabs\Pdf\Core\PdfReference;
use ApprLabs\Pdf\Core\PdfString;
use PHPUnit\Framework\TestCase;

class XObjectPhaseATest extends TestCase
{
    public function testFormXObjectNewFields(): void
    {
        $bbox = new PdfArray([
            new PdfNumber(0), new PdfNumber(0),
            new PdfNumber(100), new PdfNumber(100),
        ]);
        $xo = new FormXObject($bbox, 'q Q');
        $xo->objectNumber = 1;
        $xo->resources = new Resources();
        $xo->metadata = new PdfReference(5);
        $xo->structParent = 3;
        $xo->oc = new PdfReference(6);
        $xo->af = new PdfArray([new PdfReference(7)]);
        $xo->lastModified = new PdfString('D:20260411120000Z');
        $xo->ref = new PdfReference(9);
        $pdf = $xo->toIndirectObject();
        self::assertStringContainsString('/Subtype /Form', $pdf);
        self::assertStringContainsString('/Metadata 5 0 R', $pdf);
        self::assertStringContainsString('/StructParent 3', $pdf);
        self::assertStringContainsString('/OC 6 0 R', $pdf);
        self::assertStringContainsString('/AF', $pdf);
        self::assertStringContainsString('/LastModified', $pdf);
        self::assertStringContainsString('/Ref 9 0 R', $pdf);
    }

    public function testImageXObjectNewFields(): void
    {
        $img = new ImageXObject(100, 100, 'DeviceRGB', 8, 'fake');
        $img->objectNumber = 1;
        $img->decode = new PdfArray([
            new PdfNumber(0), new PdfNumber(1),
            new PdfNumber(0), new PdfNumber(1),
            new PdfNumber(0), new PdfNumber(1),
        ]);
        $img->structParent = 4;
        $img->id = new PdfString('image-123');
        $img->metadata = new PdfReference(5);
        $img->matte = new PdfArray([new PdfNumber(0), new PdfNumber(0), new PdfNumber(0)]);
        $pdf = $img->toIndirectObject();
        self::assertStringContainsString('/Decode', $pdf);
        self::assertStringContainsString('/StructParent 4', $pdf);
        self::assertStringContainsString('/Metadata 5 0 R', $pdf);
        self::assertStringContainsString('/Matte', $pdf);
    }

    public function testImageXObjectFilterChain(): void
    {
        $img = new ImageXObject(10, 10, 'DeviceRGB', 8, 'fake');
        $img->objectNumber = 1;
        $img->filter = new PdfArray([new PdfName('ASCII85Decode'), new PdfName('FlateDecode')]);
        $img->decodeParams = new PdfArray([new PdfNull(), new PdfDictionary(['Predictor' => new PdfNumber(15)])]);
        $pdf = $img->toIndirectObject();
        self::assertStringContainsString('/Filter', $pdf);
        self::assertStringContainsString('/ASCII85Decode', $pdf);
        self::assertStringContainsString('/FlateDecode', $pdf);
        self::assertStringContainsString('/Predictor 15', $pdf);
    }
}
