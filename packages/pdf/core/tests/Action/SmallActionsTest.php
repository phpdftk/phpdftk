<?php

declare(strict_types=1);

namespace Phpdftk\Pdf\Core\Tests\Action;

use Phpdftk\Pdf\Core\Action\RichMediaExecuteAction;
use Phpdftk\Pdf\Core\Action\ThreadAction;
use Phpdftk\Pdf\Core\FileSpec\FileSpec;
use Phpdftk\Pdf\Core\PdfDictionary;
use Phpdftk\Pdf\Core\PdfName;
use Phpdftk\Pdf\Core\PdfNumber;
use Phpdftk\Pdf\Core\PdfReference;
use PHPUnit\Framework\TestCase;

class SmallActionsTest extends TestCase
{
    public function testRichMediaExecuteMinimal(): void
    {
        $a = new RichMediaExecuteAction();
        $this->assertSame('RichMediaExecute', $a->getActionType());
        $this->assertStringContainsString('/S /RichMediaExecute', $a->toPdf());
    }

    public function testRichMediaExecuteAllFields(): void
    {
        $a = new RichMediaExecuteAction();
        $a->ta = new PdfReference(10);
        $a->ti = new PdfReference(11);
        $a->cmd = new PdfDictionary();
        $pdf = $a->toPdf();
        $this->assertStringContainsString('/TA 10 0 R', $pdf);
        $this->assertStringContainsString('/TI 11 0 R', $pdf);
        $this->assertStringContainsString('/CMD', $pdf);
    }

    public function testThreadActionMinimal(): void
    {
        $a = new ThreadAction();
        $this->assertSame('Thread', $a->getActionType());
        $this->assertStringContainsString('/S /Thread', $a->toPdf());
    }

    public function testThreadActionAllFields(): void
    {
        $a = new ThreadAction();
        $a->f = new FileSpec('threads.pdf');
        $a->d = new PdfName('MainThread');
        $a->b = new PdfNumber(3);

        $pdf = $a->toPdf();
        $this->assertStringContainsString('/F', $pdf);
        $this->assertStringContainsString('/D /MainThread', $pdf);
        $this->assertStringContainsString('/B 3', $pdf);
    }
}
