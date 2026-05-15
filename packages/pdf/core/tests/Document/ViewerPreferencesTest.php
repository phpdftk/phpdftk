<?php

declare(strict_types=1);

namespace Phpdftk\Pdf\Core\Tests\Document;

use Phpdftk\Pdf\Core\Document\ViewerPreferences;
use Phpdftk\Pdf\Core\PdfArray;
use Phpdftk\Pdf\Core\PdfName;
use Phpdftk\Pdf\Core\PdfNumber;
use PHPUnit\Framework\TestCase;

class ViewerPreferencesTest extends TestCase
{
    public function testEmptyPreferencesSerializesToEmptyDict(): void
    {
        $vp = new ViewerPreferences();
        $pdf = $vp->toPdf();
        $this->assertStringContainsString('<<', $pdf);
        $this->assertStringContainsString('>>', $pdf);
        $this->assertStringNotContainsString('/HideToolbar', $pdf);
    }

    public function testAllBooleanFieldsSerialize(): void
    {
        $vp = new ViewerPreferences();
        $vp->hideToolbar = true;
        $vp->hideMenubar = false;
        $vp->hideWindowUI = true;
        $vp->fitWindow = false;
        $vp->centerWindow = true;
        $vp->displayDocTitle = true;
        $vp->pickTrayByPDFSize = true;

        $pdf = $vp->toPdf();
        $this->assertStringContainsString('/HideToolbar true', $pdf);
        $this->assertStringContainsString('/HideMenubar false', $pdf);
        $this->assertStringContainsString('/HideWindowUI true', $pdf);
        $this->assertStringContainsString('/FitWindow false', $pdf);
        $this->assertStringContainsString('/CenterWindow true', $pdf);
        $this->assertStringContainsString('/DisplayDocTitle true', $pdf);
        $this->assertStringContainsString('/PickTrayByPDFSize true', $pdf);
    }

    public function testAllNameFieldsSerialize(): void
    {
        $vp = new ViewerPreferences();
        $vp->nonFullScreenPageMode = new PdfName('UseNone');
        $vp->direction = new PdfName('L2R');
        $vp->viewArea = new PdfName('CropBox');
        $vp->viewClip = new PdfName('MediaBox');
        $vp->printArea = new PdfName('CropBox');
        $vp->printClip = new PdfName('MediaBox');
        $vp->printScaling = new PdfName('AppDefault');
        $vp->duplex = new PdfName('DuplexFlipShortEdge');

        $pdf = $vp->toPdf();
        $this->assertStringContainsString('/NonFullScreenPageMode /UseNone', $pdf);
        $this->assertStringContainsString('/Direction /L2R', $pdf);
        $this->assertStringContainsString('/ViewArea /CropBox', $pdf);
        $this->assertStringContainsString('/ViewClip /MediaBox', $pdf);
        $this->assertStringContainsString('/PrintArea /CropBox', $pdf);
        $this->assertStringContainsString('/PrintClip /MediaBox', $pdf);
        $this->assertStringContainsString('/PrintScaling /AppDefault', $pdf);
        $this->assertStringContainsString('/Duplex /DuplexFlipShortEdge', $pdf);
    }

    public function testNumericAndArrayFieldsSerialize(): void
    {
        $vp = new ViewerPreferences();
        $vp->numCopies = 3;
        $vp->printPageRange = new PdfArray([
            new PdfNumber(1),
            new PdfNumber(3),
        ]);
        $vp->enforce = new PdfArray([new PdfName('PrintScaling')]);

        $pdf = $vp->toPdf();
        $this->assertStringContainsString('/NumCopies 3', $pdf);
        $this->assertStringContainsString('/PrintPageRange', $pdf);
        $this->assertStringContainsString('/Enforce', $pdf);
    }

    public function testRoundsTripWithMixedFlagsAndNumbers(): void
    {
        $vp = new ViewerPreferences();
        $vp->fitWindow = true;
        $vp->numCopies = 0;
        $pdf = $vp->toPdf();
        $this->assertStringContainsString('/FitWindow true', $pdf);
        $this->assertStringContainsString('/NumCopies 0', $pdf);
    }
}
