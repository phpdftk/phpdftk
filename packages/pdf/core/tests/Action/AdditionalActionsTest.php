<?php

declare(strict_types=1);

namespace ApprLabs\Pdf\Core\Tests\Action;

use ApprLabs\Pdf\Core\Action\AdditionalActions;
use ApprLabs\Pdf\Core\Action\JavaScriptAction;
use ApprLabs\Pdf\Core\Action\NamedAction;
use ApprLabs\Pdf\Core\PdfReference;
use ApprLabs\Pdf\Core\PdfString;
use PHPUnit\Framework\TestCase;

class AdditionalActionsTest extends TestCase
{
    public function testCatalogTriggers(): void
    {
        $aa = new AdditionalActions();
        $aa->objectNumber = 1;
        $aa->onWillClose(new PdfReference(10))
           ->onWillSave(new PdfReference(11))
           ->onDidPrint(new PdfReference(12));
        $pdf = $aa->toPdf();
        self::assertStringContainsString('/WC 10 0 R', $pdf);
        self::assertStringContainsString('/WS 11 0 R', $pdf);
        self::assertStringContainsString('/DP 12 0 R', $pdf);
    }

    public function testPageTriggers(): void
    {
        $js = new JavaScriptAction(new PdfString('app.alert("hi")'));
        $js->objectNumber = 5;
        $aa = new AdditionalActions();
        $aa->objectNumber = 1;
        $aa->onPageOpen($js);
        $pdf = $aa->toPdf();
        self::assertStringContainsString('/O', $pdf);
        self::assertStringContainsString('/S /JavaScript', $pdf);
    }

    public function testFieldTriggers(): void
    {
        $aa = new AdditionalActions();
        $aa->objectNumber = 1;
        $aa->onKeystroke(new PdfReference(20))
           ->onFormat(new PdfReference(21))
           ->onValidate(new PdfReference(22))
           ->onCalculate(new PdfReference(23));
        $pdf = $aa->toPdf();
        self::assertStringContainsString('/K 20 0 R', $pdf);
        self::assertStringContainsString('/F 21 0 R', $pdf);
        self::assertStringContainsString('/V 22 0 R', $pdf);
        self::assertStringContainsString('/C 23 0 R', $pdf);
    }

    public function testAnnotationTriggers(): void
    {
        $aa = new AdditionalActions();
        $aa->objectNumber = 1;
        $aa->onMouseEnter(new PdfReference(30))
           ->onMouseExit(new PdfReference(31))
           ->onFocus(new PdfReference(32))
           ->onBlur(new PdfReference(33));
        $pdf = $aa->toPdf();
        self::assertStringContainsString('/E 30 0 R', $pdf);
        self::assertStringContainsString('/X 31 0 R', $pdf);
        self::assertStringContainsString('/Fo 32 0 R', $pdf);
        self::assertStringContainsString('/Bl 33 0 R', $pdf);
    }
}
