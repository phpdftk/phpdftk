<?php

declare(strict_types=1);

namespace Phpdftk\Pdf\Core\Tests\Document;

use Phpdftk\Pdf\Core\Document\NamesDictionary;
use Phpdftk\Pdf\Core\Document\OCConfig;
use Phpdftk\Pdf\Core\Document\OCUsage;
use Phpdftk\Pdf\Core\PdfArray;
use Phpdftk\Pdf\Core\PdfDictionary;
use Phpdftk\Pdf\Core\PdfName;
use Phpdftk\Pdf\Core\PdfReference;
use Phpdftk\Pdf\Core\PdfString;
use PHPUnit\Framework\TestCase;

class OCAndNamesTest extends TestCase
{
    public function testOCConfigEmpty(): void
    {
        $oc = new OCConfig();
        $pdf = $oc->toPdf();
        $this->assertSame('<<' . "\n" . '>>', trim($pdf));
    }

    public function testOCConfigAllFields(): void
    {
        $oc = new OCConfig();
        $oc->name = new PdfString('Layers');
        $oc->creator = new PdfString('phpdftk');
        $oc->baseState = new PdfName('Unchanged');
        $oc->on = new PdfArray([new PdfReference(10)]);
        $oc->off = new PdfArray([new PdfReference(11)]);
        $oc->intent = new PdfArray([new PdfName('View')]);
        $oc->as = new PdfArray([]);
        $oc->order = new PdfArray([]);
        $oc->listMode = new PdfName('AllPages');
        $oc->rbGroups = new PdfArray([]);
        $oc->locked = new PdfArray([]);

        $pdf = $oc->toPdf();
        $this->assertStringContainsString('/Name', $pdf);
        $this->assertStringContainsString('/Creator', $pdf);
        $this->assertStringContainsString('/BaseState /Unchanged', $pdf);
        $this->assertStringContainsString('/ON', $pdf);
        $this->assertStringContainsString('/OFF', $pdf);
        $this->assertStringContainsString('/Intent', $pdf);
        $this->assertStringContainsString('/AS', $pdf);
        $this->assertStringContainsString('/Order', $pdf);
        $this->assertStringContainsString('/ListMode /AllPages', $pdf);
        $this->assertStringContainsString('/RBGroups', $pdf);
        $this->assertStringContainsString('/Locked', $pdf);
    }

    public function testOCUsageEmpty(): void
    {
        $u = new OCUsage();
        $pdf = $u->toPdf();
        $this->assertStringContainsString('<<', $pdf);
        $this->assertStringNotContainsString('/Print', $pdf);
    }

    public function testOCUsageAllFields(): void
    {
        $u = new OCUsage();
        $u->creatorInfo = new PdfDictionary();
        $u->language = new PdfDictionary();
        $u->export = new PdfDictionary();
        $u->zoom = new PdfDictionary();
        $u->print = new PdfDictionary();
        $u->view = new PdfDictionary();
        $u->user = new PdfDictionary();
        $u->pageElement = new PdfDictionary();

        $pdf = $u->toPdf();
        foreach (['CreatorInfo', 'Language', 'Export', 'Zoom', 'Print', 'View', 'User', 'PageElement'] as $key) {
            $this->assertStringContainsString("/$key", $pdf);
        }
    }

    public function testNamesDictionaryEmpty(): void
    {
        $n = new NamesDictionary();
        $pdf = $n->toPdf();
        $this->assertSame('<<' . "\n" . '>>', trim($pdf));
    }

    public function testNamesDictionaryAllFields(): void
    {
        $n = new NamesDictionary();
        $n->dests = new PdfReference(1);
        $n->ap = new PdfReference(2);
        $n->javaScript = new PdfReference(3);
        $n->pages = new PdfReference(4);
        $n->templates = new PdfReference(5);
        $n->ids = new PdfReference(6);
        $n->urls = new PdfReference(7);
        $n->embeddedFiles = new PdfReference(8);
        $n->alternatePresentations = new PdfReference(9);
        $n->renditions = new PdfReference(10);

        $pdf = $n->toPdf();
        foreach (['Dests', 'AP', 'JavaScript', 'Pages', 'Templates', 'IDS', 'URLS', 'EmbeddedFiles', 'AlternatePresentations', 'Renditions'] as $key) {
            $this->assertStringContainsString("/$key", $pdf);
        }
    }
}
