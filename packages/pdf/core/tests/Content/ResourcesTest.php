<?php

declare(strict_types=1);

namespace Phpdftk\Pdf\Core\Tests\Content;

use Phpdftk\Pdf\Core\Content\Resources;
use Phpdftk\Pdf\Core\PdfReference;
use PHPUnit\Framework\TestCase;

class ResourcesTest extends TestCase
{
    public function testEmptyResourcesEmitDefaultProcSet(): void
    {
        $r = new Resources();
        $pdf = $r->toPdf();
        $this->assertStringContainsString('/ProcSet', $pdf);
        $this->assertStringContainsString('/PDF', $pdf);
        $this->assertStringContainsString('/Text', $pdf);
        $this->assertStringContainsString('/ImageB', $pdf);
        $this->assertStringContainsString('/ImageC', $pdf);
        $this->assertStringContainsString('/ImageI', $pdf);
        $this->assertStringNotContainsString('/Font', $pdf);
        $this->assertStringNotContainsString('/XObject', $pdf);
    }

    public function testCustomProcSet(): void
    {
        $r = new Resources();
        $r->procSet = ['PDF', 'Text'];
        $pdf = $r->toPdf();
        $this->assertStringContainsString('/ProcSet [ /PDF /Text ]', $pdf);
        $this->assertStringNotContainsString('/ImageB', $pdf);
    }

    public function testAddFontXObjectExtGState(): void
    {
        $r = new Resources();
        $r->addFont('F1', new PdfReference(10));
        $r->addXObject('Im1', new PdfReference(20));
        $r->addExtGState('GS1', new PdfReference(30));

        $pdf = $r->toPdf();
        $this->assertStringContainsString('/Font', $pdf);
        $this->assertStringContainsString('/F1 10 0 R', $pdf);
        $this->assertStringContainsString('/XObject', $pdf);
        $this->assertStringContainsString('/Im1 20 0 R', $pdf);
        $this->assertStringContainsString('/ExtGState', $pdf);
        $this->assertStringContainsString('/GS1 30 0 R', $pdf);
    }

    public function testAllResourceTypesPopulated(): void
    {
        $r = new Resources();
        $r->font['F1'] = new PdfReference(1);
        $r->xObject['Im1'] = new PdfReference(2);
        $r->extGState['GS1'] = new PdfReference(3);
        $r->colorSpace['CS1'] = new PdfReference(4);
        $r->pattern['P1'] = new PdfReference(5);
        $r->shading['Sh1'] = new PdfReference(6);
        $r->properties['MC1'] = new PdfReference(7);

        $pdf = $r->toPdf();
        $this->assertStringContainsString('/Font', $pdf);
        $this->assertStringContainsString('/XObject', $pdf);
        $this->assertStringContainsString('/ExtGState', $pdf);
        $this->assertStringContainsString('/ColorSpace', $pdf);
        $this->assertStringContainsString('/Pattern', $pdf);
        $this->assertStringContainsString('/Shading', $pdf);
        $this->assertStringContainsString('/Properties', $pdf);
    }

    public function testMultipleEntriesPerCategory(): void
    {
        $r = new Resources();
        $r->addFont('F1', new PdfReference(1));
        $r->addFont('F2', new PdfReference(2));
        $r->addFont('F3', new PdfReference(3));

        $pdf = $r->toPdf();
        $this->assertStringContainsString('/F1 1 0 R', $pdf);
        $this->assertStringContainsString('/F2 2 0 R', $pdf);
        $this->assertStringContainsString('/F3 3 0 R', $pdf);
    }
}
