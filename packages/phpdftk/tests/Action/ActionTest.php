<?php

declare(strict_types=1);

namespace Phpdftk\Tests\Action;

use PHPUnit\Framework\TestCase;
use Phpdftk\Action\GoToAction;
use Phpdftk\Action\JavaScriptAction;
use Phpdftk\Action\NamedAction;
use Phpdftk\Action\URIAction;
use Phpdftk\Core\PdfArray;
use Phpdftk\Core\PdfName;
use Phpdftk\Core\PdfNumber;
use Phpdftk\Core\PdfReference;
use Phpdftk\Core\PdfString;

class ActionTest extends TestCase
{
    // -----------------------------------------------------------------------
    // GoToAction
    // -----------------------------------------------------------------------

    public function testGoToActionType(): void
    {
        $action = new GoToAction(new PdfName('FirstPage'));
        $action->objectNumber = 1;
        self::assertSame('GoTo', $action->getActionType());
    }

    public function testGoToActionToPdfContainsType(): void
    {
        $action = new GoToAction(new PdfName('FirstPage'));
        $action->objectNumber = 1;
        $pdf = $action->toPdf();
        self::assertStringContainsString('/Type /Action', $pdf);
        self::assertStringContainsString('/S /GoTo', $pdf);
    }

    public function testGoToActionWithNamedDest(): void
    {
        $action = new GoToAction(new PdfName('Appendix'));
        $action->objectNumber = 1;
        $pdf = $action->toPdf();
        self::assertStringContainsString('/D /Appendix', $pdf);
    }

    public function testGoToActionWithArrayDest(): void
    {
        $dest = new PdfArray([new PdfReference(3), new PdfName('XYZ')]);
        $action = new GoToAction($dest);
        $action->objectNumber = 1;
        $pdf = $action->toPdf();
        self::assertStringContainsString('/D', $pdf);
    }

    public function testGoToActionWithStringDest(): void
    {
        $action = new GoToAction('chapter1');
        $action->objectNumber = 1;
        $pdf = $action->toPdf();
        self::assertStringContainsString('/D', $pdf);
    }

    public function testGoToActionWithNext(): void
    {
        $action = new GoToAction(new PdfName('FirstPage'));
        $action->objectNumber = 1;
        $action->next = new PdfReference(10);
        $pdf = $action->toPdf();
        self::assertStringContainsString('/Next 10 0 R', $pdf);
    }

    public function testGoToActionToIndirectObject(): void
    {
        $action = new GoToAction(new PdfName('FirstPage'));
        $action->objectNumber = 5;
        $action->generationNumber = 0;
        $indirect = $action->toIndirectObject();
        self::assertStringContainsString('5 0 obj', $indirect);
        self::assertStringContainsString('endobj', $indirect);
    }

    // -----------------------------------------------------------------------
    // URIAction
    // -----------------------------------------------------------------------

    public function testURIActionType(): void
    {
        $action = new URIAction(new PdfString('https://example.com'));
        $action->objectNumber = 1;
        self::assertSame('URI', $action->getActionType());
    }

    public function testURIActionToPdf(): void
    {
        $action = new URIAction(new PdfString('https://example.com'));
        $action->objectNumber = 1;
        $pdf = $action->toPdf();
        self::assertStringContainsString('/Type /Action', $pdf);
        self::assertStringContainsString('/S /URI', $pdf);
        self::assertStringContainsString('/URI', $pdf);
    }

    public function testURIActionIsMap(): void
    {
        $action = new URIAction(new PdfString('https://example.com/cgi'));
        $action->objectNumber = 1;
        $action->isMap = true;
        $pdf = $action->toPdf();
        self::assertStringContainsString('/IsMap true', $pdf);
    }

    public function testURIActionIsMapFalse(): void
    {
        $action = new URIAction(new PdfString('https://example.com'));
        $action->objectNumber = 1;
        $action->isMap = false;
        $pdf = $action->toPdf();
        self::assertStringContainsString('/IsMap false', $pdf);
    }

    public function testURIActionWithNext(): void
    {
        $action = new URIAction(new PdfString('https://example.com'));
        $action->objectNumber = 1;
        $action->next = new PdfReference(12);
        $pdf = $action->toPdf();
        self::assertStringContainsString('/Next 12 0 R', $pdf);
    }

    // -----------------------------------------------------------------------
    // JavaScriptAction
    // -----------------------------------------------------------------------

    public function testJavaScriptActionType(): void
    {
        $action = new JavaScriptAction(new PdfString('app.alert("Hello");'));
        $action->objectNumber = 1;
        self::assertSame('JavaScript', $action->getActionType());
    }

    public function testJavaScriptActionToPdf(): void
    {
        $action = new JavaScriptAction(new PdfString('app.alert("Hello");'));
        $action->objectNumber = 1;
        $pdf = $action->toPdf();
        self::assertStringContainsString('/Type /Action', $pdf);
        self::assertStringContainsString('/S /JavaScript', $pdf);
        self::assertStringContainsString('/JS', $pdf);
    }

    public function testJavaScriptActionWithNext(): void
    {
        $action = new JavaScriptAction(new PdfString('console.println("hi");'));
        $action->objectNumber = 1;
        $action->next = new PdfReference(15);
        $pdf = $action->toPdf();
        self::assertStringContainsString('/Next 15 0 R', $pdf);
    }

    // -----------------------------------------------------------------------
    // NamedAction
    // -----------------------------------------------------------------------

    public function testNamedActionType(): void
    {
        $action = new NamedAction(new PdfName('NextPage'));
        $action->objectNumber = 1;
        self::assertSame('Named', $action->getActionType());
    }

    public function testNamedActionToPdf(): void
    {
        $action = new NamedAction(new PdfName('NextPage'));
        $action->objectNumber = 1;
        $pdf = $action->toPdf();
        self::assertStringContainsString('/Type /Action', $pdf);
        self::assertStringContainsString('/S /Named', $pdf);
        self::assertStringContainsString('/N /NextPage', $pdf);
    }

    public function testNamedActionPrevPage(): void
    {
        $action = new NamedAction(new PdfName('PrevPage'));
        $action->objectNumber = 1;
        $pdf = $action->toPdf();
        self::assertStringContainsString('/N /PrevPage', $pdf);
    }

    public function testNamedActionFirstPage(): void
    {
        $action = new NamedAction(new PdfName('FirstPage'));
        $action->objectNumber = 1;
        $pdf = $action->toPdf();
        self::assertStringContainsString('/N /FirstPage', $pdf);
    }

    public function testNamedActionLastPage(): void
    {
        $action = new NamedAction(new PdfName('LastPage'));
        $action->objectNumber = 1;
        $pdf = $action->toPdf();
        self::assertStringContainsString('/N /LastPage', $pdf);
    }

    public function testNamedActionWithNext(): void
    {
        $action = new NamedAction(new PdfName('NextPage'));
        $action->objectNumber = 1;
        $action->next = new PdfReference(20);
        $pdf = $action->toPdf();
        self::assertStringContainsString('/Next 20 0 R', $pdf);
    }
}
