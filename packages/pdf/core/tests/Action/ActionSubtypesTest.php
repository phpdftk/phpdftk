<?php

declare(strict_types=1);

namespace Phpdftk\Pdf\Core\Tests\Action;

use Phpdftk\Pdf\Core\Action\GoTo3DViewAction;
use Phpdftk\Pdf\Core\Action\GoToDPAction;
use Phpdftk\Pdf\Core\Action\GoToEAction;
use Phpdftk\Pdf\Core\Action\HideAction;
use Phpdftk\Pdf\Core\Action\ImportDataAction;
use Phpdftk\Pdf\Core\Action\LaunchAction;
use Phpdftk\Pdf\Core\Action\MovieAction;
use Phpdftk\Pdf\Core\Action\RenditionAction;
use Phpdftk\Pdf\Core\Action\ResetFormAction;
use Phpdftk\Pdf\Core\Action\RichMediaExecuteAction;
use Phpdftk\Pdf\Core\Action\SetOCGStateAction;
use Phpdftk\Pdf\Core\Action\SoundAction;
use Phpdftk\Pdf\Core\Action\SubmitFormAction;
use Phpdftk\Pdf\Core\Action\ThreadAction;
use Phpdftk\Pdf\Core\Action\TransAction;
use Phpdftk\Pdf\Core\Document\TransitionDict;
use Phpdftk\Pdf\Core\FileSpec\FileSpec;
use Phpdftk\Pdf\Core\PdfArray;
use Phpdftk\Pdf\Core\PdfName;
use Phpdftk\Pdf\Core\PdfNumber;
use Phpdftk\Pdf\Core\PdfReference;
use Phpdftk\Pdf\Core\PdfString;
use PHPUnit\Framework\TestCase;

class ActionSubtypesTest extends TestCase
{
    public function testLaunchAction(): void
    {
        $spec = new FileSpec('helper.exe');
        $a = new LaunchAction();
        $a->objectNumber = 1;
        $a->f = $spec;
        $a->newWindow = true;
        $pdf = $a->toPdf();
        self::assertStringContainsString('/S /Launch', $pdf);
        self::assertStringContainsString('/F', $pdf);
        self::assertStringContainsString('/NewWindow true', $pdf);
    }

    public function testThreadAction(): void
    {
        $a = new ThreadAction();
        $a->objectNumber = 1;
        $a->d = new PdfString('MyThread');
        $pdf = $a->toPdf();
        self::assertStringContainsString('/S /Thread', $pdf);
        self::assertStringContainsString('/D', $pdf);
    }

    public function testSoundAction(): void
    {
        $a = new SoundAction(new PdfReference(5));
        $a->objectNumber = 1;
        $a->volume = 0.5;
        $a->repeat = true;
        $pdf = $a->toPdf();
        self::assertStringContainsString('/S /Sound', $pdf);
        self::assertStringContainsString('/Sound 5 0 R', $pdf);
        self::assertStringContainsString('/Volume', $pdf);
        self::assertStringContainsString('/Repeat true', $pdf);
    }

    public function testMovieAction(): void
    {
        $a = new MovieAction();
        $a->objectNumber = 1;
        $a->t = new PdfString('demo');
        $a->operation = new PdfName('Play');
        $pdf = $a->toPdf();
        self::assertStringContainsString('/S /Movie', $pdf);
        self::assertStringContainsString('/Operation /Play', $pdf);
    }

    public function testHideAction(): void
    {
        $a = new HideAction(new PdfString('field.name'), false);
        $a->objectNumber = 1;
        $pdf = $a->toPdf();
        self::assertStringContainsString('/S /Hide', $pdf);
        self::assertStringContainsString('/H false', $pdf);
    }

    public function testSubmitFormAction(): void
    {
        $spec = new FileSpec();
        $spec->f = new PdfString('https://example.com/submit');
        $a = new SubmitFormAction($spec);
        $a->objectNumber = 1;
        $a->flags = 4;
        $pdf = $a->toPdf();
        self::assertStringContainsString('/S /SubmitForm', $pdf);
        self::assertStringContainsString('/Flags 4', $pdf);
    }

    public function testResetFormAction(): void
    {
        $a = new ResetFormAction();
        $a->objectNumber = 1;
        $a->flags = 1;
        $pdf = $a->toPdf();
        self::assertStringContainsString('/S /ResetForm', $pdf);
        self::assertStringContainsString('/Flags 1', $pdf);
    }

    public function testImportDataAction(): void
    {
        $spec = new FileSpec('data.fdf');
        $a = new ImportDataAction($spec);
        $a->objectNumber = 1;
        $pdf = $a->toPdf();
        self::assertStringContainsString('/S /ImportData', $pdf);
        self::assertStringContainsString('/F', $pdf);
    }

    public function testSetOCGStateAction(): void
    {
        $state = new PdfArray([new PdfName('ON'), new PdfReference(9)]);
        $a = new SetOCGStateAction($state);
        $a->objectNumber = 1;
        $a->preserveRB = false;
        $pdf = $a->toPdf();
        self::assertStringContainsString('/S /SetOCGState', $pdf);
        self::assertStringContainsString('/State', $pdf);
        self::assertStringContainsString('/PreserveRB false', $pdf);
    }

    public function testRenditionAction(): void
    {
        $a = new RenditionAction();
        $a->objectNumber = 1;
        $a->op = 0;
        $a->r = new PdfReference(7);
        $pdf = $a->toPdf();
        self::assertStringContainsString('/S /Rendition', $pdf);
        self::assertStringContainsString('/OP 0', $pdf);
        self::assertStringContainsString('/R 7 0 R', $pdf);
    }

    public function testTransAction(): void
    {
        $trans = new TransitionDict();
        $trans->s = new PdfName('Wipe');
        $a = new TransAction($trans);
        $a->objectNumber = 1;
        $pdf = $a->toPdf();
        self::assertStringContainsString('/S /Trans', $pdf);
        self::assertStringContainsString('/Wipe', $pdf);
    }

    public function testGoTo3DViewAction(): void
    {
        $a = new GoTo3DViewAction(new PdfReference(11));
        $a->objectNumber = 1;
        $a->v = new PdfName('F');
        $pdf = $a->toPdf();
        self::assertStringContainsString('/S /GoTo3DView', $pdf);
        self::assertStringContainsString('/TA 11 0 R', $pdf);
        self::assertStringContainsString('/V /F', $pdf);
    }

    public function testRichMediaExecuteAction(): void
    {
        $a = new RichMediaExecuteAction();
        $a->objectNumber = 1;
        $a->ta = new PdfReference(22);
        $pdf = $a->toPdf();
        self::assertStringContainsString('/S /RichMediaExecute', $pdf);
        self::assertStringContainsString('/TA 22 0 R', $pdf);
    }

    public function testGoToEAction(): void
    {
        $a = new GoToEAction(new PdfString('DestName'));
        $a->objectNumber = 1;
        $a->newWindow = true;
        $pdf = $a->toPdf();
        self::assertStringContainsString('/S /GoToE', $pdf);
        self::assertStringContainsString('/NewWindow true', $pdf);
    }

    public function testGoToDPAction(): void
    {
        $a = new GoToDPAction();
        $a->objectNumber = 1;
        $a->d = new PdfNumber(0);
        $a->dp = new PdfReference(4);
        $pdf = $a->toPdf();
        self::assertStringContainsString('/S /GoToDP', $pdf);
        self::assertStringContainsString('/DP 4 0 R', $pdf);
    }

    public function testNextChaining(): void
    {
        $a = new ResetFormAction();
        $a->objectNumber = 1;
        $a->next = new PdfReference(99);
        self::assertStringContainsString('/Next 99 0 R', $a->toPdf());
    }
}
