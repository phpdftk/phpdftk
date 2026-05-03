<?php

declare(strict_types=1);

namespace Phpdftk\Pdf\Core\Tests\Annotation;

use Phpdftk\Pdf\Core\Annotation\HighlightAnnotation;
use Phpdftk\Pdf\Core\Annotation\MarkupAnnotation;
use Phpdftk\Pdf\Core\Annotation\TextAnnotation;
use Phpdftk\Pdf\Core\PdfArray;
use Phpdftk\Pdf\Core\PdfName;
use Phpdftk\Pdf\Core\PdfNumber;
use Phpdftk\Pdf\Core\PdfReference;
use Phpdftk\Pdf\Core\PdfString;
use PHPUnit\Framework\TestCase;

class MarkupAnnotationTest extends TestCase
{
    private function rect(): PdfArray
    {
        return new PdfArray([
            new PdfNumber(0), new PdfNumber(0),
            new PdfNumber(100), new PdfNumber(100),
        ]);
    }

    public function testTextAnnotationIsMarkup(): void
    {
        $a = new TextAnnotation($this->rect());
        self::assertInstanceOf(MarkupAnnotation::class, $a);
    }

    public function testMarkupFieldsEmitted(): void
    {
        $a = new TextAnnotation($this->rect());
        $a->objectNumber = 1;
        $a->t = new PdfString('Alice');
        $a->subj = new PdfString('Comment');
        $a->creationDate = new PdfString('D:20260411120000Z');
        $a->irt = new PdfReference(42);
        $a->rt = new PdfName('R');
        $a->it = new PdfName('Review');
        $a->markupCa = 0.5;
        $a->popup = new PdfReference(43);
        $a->rc = new PdfString('<p>rich</p>');
        $pdf = $a->toPdf();
        self::assertStringContainsString('/T (Alice)', $pdf);
        self::assertStringContainsString('/Subj (Comment)', $pdf);
        self::assertStringContainsString('/CreationDate', $pdf);
        self::assertStringContainsString('/IRT 42 0 R', $pdf);
        self::assertStringContainsString('/RT /R', $pdf);
        self::assertStringContainsString('/IT /Review', $pdf);
        self::assertStringContainsString('/CA 0.5', $pdf);
        self::assertStringContainsString('/Popup 43 0 R', $pdf);
        self::assertStringContainsString('/RC', $pdf);
    }

    public function testHighlightIsMarkupAndStillCarriesQuadPoints(): void
    {
        $quad = new PdfArray([
            new PdfNumber(0), new PdfNumber(0),
            new PdfNumber(10), new PdfNumber(0),
            new PdfNumber(10), new PdfNumber(10),
            new PdfNumber(0), new PdfNumber(10),
        ]);
        $a = new HighlightAnnotation($this->rect(), $quad);
        $a->objectNumber = 1;
        $a->t = new PdfString('Reviewer');
        $a->subj = new PdfString('Important');
        self::assertInstanceOf(MarkupAnnotation::class, $a);
        $pdf = $a->toPdf();
        self::assertStringContainsString('/Subtype /Highlight', $pdf);
        self::assertStringContainsString('/QuadPoints', $pdf);
        self::assertStringContainsString('/T (Reviewer)', $pdf);
        self::assertStringContainsString('/Subj (Important)', $pdf);
    }
}
