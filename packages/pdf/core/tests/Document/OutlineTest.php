<?php

declare(strict_types=1);

namespace Phpdftk\Pdf\Core\Tests\Document;

use PHPUnit\Framework\TestCase;
use Phpdftk\Pdf\Core\Document\Outline;
use Phpdftk\Pdf\Core\Document\OutlineItem;
use Phpdftk\Pdf\Core\PdfArray;
use Phpdftk\Pdf\Core\PdfName;
use Phpdftk\Pdf\Core\PdfNumber;
use Phpdftk\Pdf\Core\PdfReference;
use Phpdftk\Pdf\Core\PdfString;

class OutlineTest extends TestCase
{
    // -----------------------------------------------------------------------
    // Outline (root)
    // -----------------------------------------------------------------------

    public function testOutlineType(): void
    {
        $outline = new Outline();
        $outline->objectNumber = 1;
        self::assertStringContainsString('/Type /Outlines', $outline->toPdf());
    }

    public function testOutlineWithFirstAndLast(): void
    {
        $outline = new Outline();
        $outline->objectNumber = 1;
        $outline->first = new PdfReference(2);
        $outline->last  = new PdfReference(5);
        $pdf = $outline->toPdf();
        self::assertStringContainsString('/First 2 0 R', $pdf);
        self::assertStringContainsString('/Last 5 0 R', $pdf);
    }

    public function testOutlineWithCount(): void
    {
        $outline = new Outline();
        $outline->objectNumber = 1;
        $outline->count = 4;
        self::assertStringContainsString('/Count 4', $outline->toPdf());
    }

    public function testOutlineCountZeroOmitted(): void
    {
        $outline = new Outline();
        $outline->objectNumber = 1;
        self::assertStringNotContainsString('/Count', $outline->toPdf());
    }

    public function testOutlineToIndirectObject(): void
    {
        $outline = new Outline();
        $outline->objectNumber = 3;
        $indirect = $outline->toIndirectObject();
        self::assertStringContainsString('3 0 obj', $indirect);
        self::assertStringContainsString('endobj', $indirect);
    }

    // -----------------------------------------------------------------------
    // OutlineItem (bookmark entry)
    // -----------------------------------------------------------------------

    public function testOutlineItemTitle(): void
    {
        $item = new OutlineItem('Chapter 1');
        $item->objectNumber = 2;
        self::assertStringContainsString('/Title (Chapter 1)', $item->toPdf());
    }

    public function testOutlineItemFromPdfString(): void
    {
        $item = new OutlineItem(new PdfString('Introduction'));
        $item->objectNumber = 2;
        self::assertStringContainsString('/Title (Introduction)', $item->toPdf());
    }

    public function testOutlineItemWithParent(): void
    {
        $item = new OutlineItem('Section');
        $item->objectNumber = 3;
        $item->parent = new PdfReference(1);
        self::assertStringContainsString('/Parent 1 0 R', $item->toPdf());
    }

    public function testOutlineItemWithPrevNext(): void
    {
        $item = new OutlineItem('Chapter 2');
        $item->objectNumber = 4;
        $item->prev = new PdfReference(2);
        $item->next = new PdfReference(6);
        $pdf = $item->toPdf();
        self::assertStringContainsString('/Prev 2 0 R', $pdf);
        self::assertStringContainsString('/Next 6 0 R', $pdf);
    }

    public function testOutlineItemWithFirstLastChildren(): void
    {
        $item = new OutlineItem('Part I');
        $item->objectNumber = 2;
        $item->first = new PdfReference(3);
        $item->last  = new PdfReference(7);
        $item->count = 3;
        $pdf = $item->toPdf();
        self::assertStringContainsString('/First 3 0 R', $pdf);
        self::assertStringContainsString('/Last 7 0 R', $pdf);
        self::assertStringContainsString('/Count 3', $pdf);
    }

    public function testOutlineItemWithNamedDest(): void
    {
        $item = new OutlineItem('Appendix');
        $item->objectNumber = 5;
        $item->dest = new PdfName('AppendixA');
        self::assertStringContainsString('/Dest /AppendixA', $item->toPdf());
    }

    public function testOutlineItemWithArrayDest(): void
    {
        $item = new OutlineItem('Page 5');
        $item->objectNumber = 5;
        $item->dest = new PdfArray([new PdfReference(10), new PdfName('XYZ'), new PdfNumber(0), new PdfNumber(792), new PdfNumber(0)]);
        self::assertStringContainsString('/Dest', $item->toPdf());
    }

    public function testOutlineItemWithAction(): void
    {
        $item = new OutlineItem('Visit us');
        $item->objectNumber = 6;
        $item->a = new PdfReference(20);
        self::assertStringContainsString('/A 20 0 R', $item->toPdf());
    }

    public function testOutlineItemWithColor(): void
    {
        $item = new OutlineItem('Red Chapter');
        $item->objectNumber = 7;
        $item->c = new PdfArray([new PdfNumber(1.0), new PdfNumber(0.0), new PdfNumber(0.0)]);
        self::assertStringContainsString('/C', $item->toPdf());
    }

    public function testOutlineItemWithBoldFlag(): void
    {
        $item = new OutlineItem('Bold');
        $item->objectNumber = 8;
        $item->f = 2; // bold
        self::assertStringContainsString('/F 2', $item->toPdf());
    }

    public function testOutlineItemFlagZeroOmitted(): void
    {
        $item = new OutlineItem('Normal');
        $item->objectNumber = 9;
        self::assertStringNotContainsString('/F', $item->toPdf());
    }

    public function testOutlineItemNegativeCountClosedSubtree(): void
    {
        $item = new OutlineItem('Closed Section');
        $item->objectNumber = 10;
        $item->count = -3;
        self::assertStringContainsString('/Count -3', $item->toPdf());
    }

    // -----------------------------------------------------------------------
    // Integration: build a two-level bookmark tree
    // -----------------------------------------------------------------------

    public function testOutlineTreeIntegration(): void
    {
        $outline = new Outline();
        $outline->objectNumber = 1;

        $ch1 = new OutlineItem('Chapter 1');
        $ch1->objectNumber = 2;
        $ch1->parent = new PdfReference(1);
        $ch1->dest   = new PdfName('ch1');

        $ch2 = new OutlineItem('Chapter 2');
        $ch2->objectNumber = 3;
        $ch2->parent = new PdfReference(1);
        $ch2->prev   = new PdfReference(2);
        $ch2->dest   = new PdfName('ch2');

        $ch1->next = new PdfReference(3);

        $outline->first = new PdfReference(2);
        $outline->last  = new PdfReference(3);
        $outline->count = 2;

        $outlinePdf = $outline->toPdf();
        self::assertStringContainsString('/First 2 0 R', $outlinePdf);
        self::assertStringContainsString('/Last 3 0 R', $outlinePdf);
        self::assertStringContainsString('/Count 2', $outlinePdf);

        self::assertStringContainsString('/Title (Chapter 1)', $ch1->toPdf());
        self::assertStringContainsString('/Title (Chapter 2)', $ch2->toPdf());
        self::assertStringContainsString('/Prev 2 0 R', $ch2->toPdf());
    }
}
