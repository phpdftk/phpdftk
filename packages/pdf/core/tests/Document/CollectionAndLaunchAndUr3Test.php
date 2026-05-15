<?php

declare(strict_types=1);

namespace Phpdftk\Pdf\Core\Tests\Document;

use Phpdftk\Pdf\Core\Action\LaunchAction;
use Phpdftk\Pdf\Core\Document\Collection;
use Phpdftk\Pdf\Core\FileSpec\FileSpec;
use Phpdftk\Pdf\Core\Interactive\Signature\UR3TransformParams;
use Phpdftk\Pdf\Core\PdfArray;
use Phpdftk\Pdf\Core\PdfDictionary;
use Phpdftk\Pdf\Core\PdfName;
use Phpdftk\Pdf\Core\PdfReference;
use Phpdftk\Pdf\Core\PdfString;
use PHPUnit\Framework\TestCase;

class CollectionAndLaunchAndUr3Test extends TestCase
{
    public function testCollectionMinimal(): void
    {
        $c = new Collection();
        $pdf = $c->toPdf();
        $this->assertStringContainsString('/Type /Collection', $pdf);
        $this->assertStringNotContainsString('/Schema', $pdf);
    }

    public function testCollectionAllFields(): void
    {
        $c = new Collection();
        $c->schema = new PdfReference(2);
        $c->d = new PdfName('doc.pdf');
        $c->view = new PdfName('T');
        $c->sort = new PdfReference(3);

        $pdf = $c->toPdf();
        $this->assertStringContainsString('/Schema 2 0 R', $pdf);
        $this->assertStringContainsString('/D /doc.pdf', $pdf);
        $this->assertStringContainsString('/View /T', $pdf);
        $this->assertStringContainsString('/Sort 3 0 R', $pdf);
    }

    public function testLaunchActionMinimal(): void
    {
        $a = new LaunchAction();
        $this->assertSame('Launch', $a->getActionType());
        $pdf = $a->toPdf();
        $this->assertStringContainsString('/S /Launch', $pdf);
    }

    public function testLaunchActionAllFields(): void
    {
        $a = new LaunchAction();
        $a->f = new FileSpec('app.exe');
        $a->win = new PdfDictionary();
        $a->mac = new PdfDictionary();
        $a->unix = new PdfDictionary();
        $a->newWindow = true;

        $pdf = $a->toPdf();
        $this->assertStringContainsString('/F', $pdf);
        $this->assertStringContainsString('/Win', $pdf);
        $this->assertStringContainsString('/Mac', $pdf);
        $this->assertStringContainsString('/Unix', $pdf);
        $this->assertStringContainsString('/NewWindow true', $pdf);
    }

    public function testUR3TransformParamsMinimal(): void
    {
        $p = new UR3TransformParams();
        $pdf = $p->toPdf();
        $this->assertStringContainsString('/Type /TransformParams', $pdf);
        $this->assertStringNotContainsString('/Document', $pdf);
    }

    public function testUR3TransformParamsAllFields(): void
    {
        $p = new UR3TransformParams();
        $p->document = new PdfArray([new PdfName('FullSave')]);
        $p->msg = new PdfString('warning message');
        $p->v = new PdfName('2.2');
        $p->annots = new PdfArray([new PdfName('Create')]);
        $p->form = new PdfArray([new PdfName('FillIn')]);
        $p->signature = new PdfArray([new PdfName('Modify')]);
        $p->ef = new PdfArray([new PdfName('Create')]);
        $p->p = new PdfString('https://example.com/rights');

        $pdf = $p->toPdf();
        $this->assertStringContainsString('/Document', $pdf);
        $this->assertStringContainsString('/Msg', $pdf);
        $this->assertStringContainsString('/V /2.2', $pdf);
        $this->assertStringContainsString('/Annots', $pdf);
        $this->assertStringContainsString('/Form', $pdf);
        $this->assertStringContainsString('/Signature', $pdf);
        $this->assertStringContainsString('/EF', $pdf);
        $this->assertStringContainsString('/P', $pdf);
    }
}
