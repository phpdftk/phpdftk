<?php

declare(strict_types=1);

namespace Phpdftk\Pdf\Core\Tests\Graphics\XObject;

use Phpdftk\Pdf\Core\Content\Resources;
use Phpdftk\Pdf\Core\Graphics\XObject\FormXObject;
use Phpdftk\Pdf\Core\PdfArray;
use Phpdftk\Pdf\Core\PdfDictionary;
use Phpdftk\Pdf\Core\PdfName;
use Phpdftk\Pdf\Core\PdfNumber;
use Phpdftk\Pdf\Core\PdfReference;
use Phpdftk\Pdf\Core\PdfString;
use PHPUnit\Framework\TestCase;

class FormXObjectTest extends TestCase
{
    private function bbox(): PdfArray
    {
        return new PdfArray([
            new PdfNumber(0),
            new PdfNumber(0),
            new PdfNumber(100),
            new PdfNumber(100),
        ]);
    }

    public function testMinimalFormXObject(): void
    {
        $f = new FormXObject($this->bbox(), '0 0 m 100 100 l S');
        $f->objectNumber = 1;
        $pdf = $f->toPdf();
        $this->assertStringContainsString('/Type /XObject', $pdf);
        $this->assertStringContainsString('/Subtype /Form', $pdf);
        $this->assertStringContainsString('/BBox', $pdf);
    }

    public function testFormXObjectAllOptionalFields(): void
    {
        $f = new FormXObject($this->bbox(), 'content');
        $f->objectNumber = 1;
        $f->formType = new PdfName('1');
        $f->matrix = new PdfArray([
            new PdfNumber(1),
            new PdfNumber(0),
            new PdfNumber(0),
            new PdfNumber(1),
            new PdfNumber(0),
            new PdfNumber(0),
        ]);
        $f->resources = new Resources();
        $f->ref = new PdfReference(10);
        $f->metadata = new PdfReference(11);
        $f->pieceInfo = new PdfReference(12);
        $f->lastModified = new PdfString('D:20260101000000Z');
        $f->structParent = 5;
        $f->structParents = 6;
        $f->oc = new PdfReference(13);
        $f->af = new PdfArray([new PdfReference(14)]);
        $f->opi = new PdfDictionary();
        $f->measure = new PdfReference(15);
        $f->ptData = new PdfReference(16);
        $f->name = new PdfName('MyForm');

        $pdf = $f->toPdf();
        foreach (['/FormType /1', '/Matrix', '/Resources', '/Ref 10 0 R', '/Metadata 11 0 R', '/PieceInfo 12 0 R',
            '/LastModified', '/StructParent 5', '/StructParents 6', '/OC 13 0 R', '/AF', '/OPI',
            '/Measure 15 0 R', '/PtData 16 0 R', '/Name /MyForm'] as $needle) {
            $this->assertStringContainsString($needle, $pdf);
        }
    }
}
