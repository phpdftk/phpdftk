<?php

declare(strict_types=1);

namespace Phpdftk\Pdf\Core\Tests\Document;

use PHPUnit\Framework\TestCase;
use Phpdftk\Pdf\Core\Document\PageLabel;
use Phpdftk\Pdf\Core\PdfName;
use Phpdftk\Pdf\Core\PdfString;

class PageLabelTest extends TestCase
{
    public function testPageLabelType(): void
    {
        $label = new PageLabel();
        $label->objectNumber = 1;
        self::assertStringContainsString('/Type /PageLabel', $label->toPdf());
    }

    public function testPageLabelDecimal(): void
    {
        $label = new PageLabel();
        $label->objectNumber = 1;
        $label->s = new PdfName('D');
        self::assertStringContainsString('/S /D', $label->toPdf());
    }

    public function testPageLabelRomanLower(): void
    {
        $label = new PageLabel();
        $label->objectNumber = 1;
        $label->s = new PdfName('r');
        self::assertStringContainsString('/S /r', $label->toPdf());
    }

    public function testPageLabelRomanUpper(): void
    {
        $label = new PageLabel();
        $label->objectNumber = 1;
        $label->s = new PdfName('R');
        self::assertStringContainsString('/S /R', $label->toPdf());
    }

    public function testPageLabelAlphaLower(): void
    {
        $label = new PageLabel();
        $label->objectNumber = 1;
        $label->s = new PdfName('a');
        self::assertStringContainsString('/S /a', $label->toPdf());
    }

    public function testPageLabelWithPrefix(): void
    {
        $label = new PageLabel();
        $label->objectNumber = 1;
        $label->s = new PdfName('D');
        $label->p = new PdfString('App-');
        $pdf = $label->toPdf();
        self::assertStringContainsString('/P (App-)', $pdf);
    }

    public function testPageLabelWithStartingValue(): void
    {
        $label = new PageLabel();
        $label->objectNumber = 1;
        $label->s  = new PdfName('D');
        $label->st = 5;
        self::assertStringContainsString('/St 5', $label->toPdf());
    }

    public function testPageLabelDefaultStartOmitted(): void
    {
        $label = new PageLabel();
        $label->objectNumber = 1;
        $label->s = new PdfName('D');
        self::assertStringNotContainsString('/St', $label->toPdf());
    }

    public function testPageLabelPrefixOnly(): void
    {
        // No /S — label is just the prefix with no numeric part
        $label = new PageLabel();
        $label->objectNumber = 1;
        $label->p = new PdfString('Cover');
        $pdf = $label->toPdf();
        self::assertStringContainsString('/Type /PageLabel', $pdf);
        self::assertStringContainsString('/P (Cover)', $pdf);
        self::assertStringNotContainsString('/S', $pdf);
    }

    public function testPageLabelToIndirectObject(): void
    {
        $label = new PageLabel();
        $label->objectNumber = 4;
        $indirect = $label->toIndirectObject();
        self::assertStringContainsString('4 0 obj', $indirect);
        self::assertStringContainsString('endobj', $indirect);
    }
}
