<?php

declare(strict_types=1);

namespace ApprLabs\Pdf\Core\Tests\Action;

use PHPUnit\Framework\TestCase;
use ApprLabs\Pdf\Core\Action\GoToRAction;
use ApprLabs\Pdf\Core\PdfArray;
use ApprLabs\Pdf\Core\PdfName;
use ApprLabs\Pdf\Core\PdfNumber;
use ApprLabs\Pdf\Core\PdfReference;
use ApprLabs\Pdf\Core\PdfString;

class GoToRActionTest extends TestCase
{
    public function testActionType(): void
    {
        $a = new GoToRAction(new PdfString('other.pdf'), new PdfName('Chapter1'));
        self::assertSame('GoToR', $a->getActionType());
    }

    public function testToPdfContainsRequiredFields(): void
    {
        $a = new GoToRAction(new PdfString('other.pdf'), new PdfName('Chapter1'));
        $a->objectNumber = 1;
        $pdf = $a->toPdf();
        self::assertStringContainsString('/Type /Action', $pdf);
        self::assertStringContainsString('/S /GoToR', $pdf);
        self::assertStringContainsString('/F (other.pdf)', $pdf);
        self::assertStringContainsString('/D /Chapter1', $pdf);
    }

    public function testWithArrayDest(): void
    {
        $dest = new PdfArray([new PdfNumber(3), new PdfName('XYZ'), new PdfNumber(0), new PdfNumber(792), new PdfNumber(0)]);
        $a = new GoToRAction(new PdfString('report.pdf'), $dest);
        $a->objectNumber = 1;
        self::assertStringContainsString('/D', $a->toPdf());
    }

    public function testWithStringDest(): void
    {
        $a = new GoToRAction(new PdfString('manual.pdf'), 'intro');
        $a->objectNumber = 1;
        self::assertStringContainsString('/D (intro)', $a->toPdf());
    }

    public function testNewWindowTrue(): void
    {
        $a = new GoToRAction(new PdfString('other.pdf'), new PdfName('p1'));
        $a->objectNumber = 1;
        $a->newWindow = true;
        self::assertStringContainsString('/NewWindow true', $a->toPdf());
    }

    public function testNewWindowFalse(): void
    {
        $a = new GoToRAction(new PdfString('other.pdf'), new PdfName('p1'));
        $a->objectNumber = 1;
        $a->newWindow = false;
        self::assertStringContainsString('/NewWindow false', $a->toPdf());
    }

    public function testNewWindowNullOmitted(): void
    {
        $a = new GoToRAction(new PdfString('other.pdf'), new PdfName('p1'));
        $a->objectNumber = 1;
        self::assertStringNotContainsString('/NewWindow', $a->toPdf());
    }

    public function testWithNext(): void
    {
        $a = new GoToRAction(new PdfString('other.pdf'), new PdfName('p1'));
        $a->objectNumber = 1;
        $a->next = new PdfReference(99);
        self::assertStringContainsString('/Next 99 0 R', $a->toPdf());
    }

    public function testToIndirectObject(): void
    {
        $a = new GoToRAction(new PdfString('doc.pdf'), new PdfName('intro'));
        $a->objectNumber = 7;
        $indirect = $a->toIndirectObject();
        self::assertStringContainsString('7 0 obj', $indirect);
        self::assertStringContainsString('endobj', $indirect);
    }

    public function testAbsolutePath(): void
    {
        $a = new GoToRAction(new PdfString('/docs/annual-report.pdf'), new PdfName('Summary'));
        $a->objectNumber = 1;
        self::assertStringContainsString('/docs/annual-report.pdf', $a->toPdf());
    }
}
