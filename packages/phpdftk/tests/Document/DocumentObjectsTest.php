<?php

declare(strict_types=1);

namespace Phpdftk\Tests\Document;

use PHPUnit\Framework\TestCase;
use Phpdftk\Document\Catalog;
use Phpdftk\Document\Info;
use Phpdftk\Document\PageTree;
use Phpdftk\Document\ViewerPreferences;
use Phpdftk\Core\PdfName;
use Phpdftk\Core\PdfReference;
use Phpdftk\Core\PdfString;
use Phpdftk\Core\PdfArray;
use Phpdftk\Core\PdfNumber;

class DocumentObjectsTest extends TestCase
{
    // -----------------------------------------------------------------------
    // Catalog
    // -----------------------------------------------------------------------

    public function testCatalogTypeIsCatalog(): void
    {
        $cat = new Catalog();
        $cat->objectNumber = 1;
        $pdf = $cat->toPdf();
        self::assertStringContainsString('/Type /Catalog', $pdf);
    }

    public function testCatalogWithPages(): void
    {
        $cat = new Catalog();
        $cat->objectNumber = 1;
        $cat->pages = new PdfReference(2);
        $pdf = $cat->toPdf();
        self::assertStringContainsString('/Pages 2 0 R', $pdf);
    }

    public function testCatalogWithVersion(): void
    {
        $cat = new Catalog();
        $cat->objectNumber = 1;
        $cat->version = new PdfName('1.7');
        $pdf = $cat->toPdf();
        self::assertStringContainsString('/Version /1.7', $pdf);
    }

    public function testCatalogWithPageLayout(): void
    {
        $cat = new Catalog();
        $cat->objectNumber = 1;
        $cat->pageLayout = new PdfName('TwoColumnLeft');
        $pdf = $cat->toPdf();
        self::assertStringContainsString('/PageLayout /TwoColumnLeft', $pdf);
    }

    public function testCatalogWithPageMode(): void
    {
        $cat = new Catalog();
        $cat->objectNumber = 1;
        $cat->pageMode = new PdfName('UseOutlines');
        $pdf = $cat->toPdf();
        self::assertStringContainsString('/PageMode /UseOutlines', $pdf);
    }

    public function testCatalogWithLang(): void
    {
        $cat = new Catalog();
        $cat->objectNumber = 1;
        $cat->lang = new PdfString('en-US');
        $pdf = $cat->toPdf();
        self::assertStringContainsString('/Lang', $pdf);
    }

    public function testCatalogWithAcroForm(): void
    {
        $cat = new Catalog();
        $cat->objectNumber = 1;
        $cat->acroForm = new PdfReference(5);
        $pdf = $cat->toPdf();
        self::assertStringContainsString('/AcroForm 5 0 R', $pdf);
    }

    public function testCatalogWithOpenAction(): void
    {
        $cat = new Catalog();
        $cat->objectNumber = 1;
        $cat->openAction = new PdfReference(3);
        $pdf = $cat->toPdf();
        self::assertStringContainsString('/OpenAction 3 0 R', $pdf);
    }

    public function testCatalogToIndirectObject(): void
    {
        $cat = new Catalog();
        $cat->objectNumber = 1;
        $cat->generationNumber = 0;
        $indirect = $cat->toIndirectObject();
        self::assertStringContainsString('1 0 obj', $indirect);
        self::assertStringContainsString('endobj', $indirect);
    }

    // -----------------------------------------------------------------------
    // PageTree
    // -----------------------------------------------------------------------

    public function testPageTreeType(): void
    {
        $pt = new PageTree();
        $pt->objectNumber = 2;
        $pdf = $pt->toPdf();
        self::assertStringContainsString('/Type /Pages', $pdf);
    }

    public function testPageTreeWithKids(): void
    {
        $pt = new PageTree();
        $pt->objectNumber = 2;
        $pt->kids = [new PdfReference(3), new PdfReference(4)];
        $pt->count = 2;
        $pdf = $pt->toPdf();
        self::assertStringContainsString('/Kids', $pdf);
        self::assertStringContainsString('/Count 2', $pdf);
    }

    public function testPageTreeWithParent(): void
    {
        $pt = new PageTree();
        $pt->objectNumber = 2;
        $pt->parent = new PdfReference(1);
        $pdf = $pt->toPdf();
        self::assertStringContainsString('/Parent 1 0 R', $pdf);
    }

    public function testPageTreeWithRotate(): void
    {
        $pt = new PageTree();
        $pt->objectNumber = 2;
        $pt->rotate = 90;
        $pdf = $pt->toPdf();
        self::assertStringContainsString('/Rotate 90', $pdf);
    }

    public function testPageTreeDefaultRotateNotIncluded(): void
    {
        $pt = new PageTree();
        $pt->objectNumber = 2;
        $pdf = $pt->toPdf();
        // rotate=0 is default, should not be included
        self::assertStringNotContainsString('/Rotate', $pdf);
    }

    // -----------------------------------------------------------------------
    // Info
    // -----------------------------------------------------------------------

    public function testInfoWithTitle(): void
    {
        $info = new Info();
        $info->objectNumber = 5;
        $info->title = new PdfString('Test Document');
        $pdf = $info->toPdf();
        self::assertStringContainsString('/Title', $pdf);
    }

    public function testInfoWithAuthor(): void
    {
        $info = new Info();
        $info->objectNumber = 5;
        $info->author = new PdfString('Test Author');
        $pdf = $info->toPdf();
        self::assertStringContainsString('/Author', $pdf);
    }

    public function testInfoWithSubjectAndKeywords(): void
    {
        $info = new Info();
        $info->objectNumber = 5;
        $info->subject = new PdfString('Testing');
        $info->keywords = new PdfString('test php pdf');
        $pdf = $info->toPdf();
        self::assertStringContainsString('/Subject', $pdf);
        self::assertStringContainsString('/Keywords', $pdf);
    }

    public function testInfoWithCreatorAndProducer(): void
    {
        $info = new Info();
        $info->objectNumber = 5;
        $info->creator = new PdfString('phpdftk');
        $info->producer = new PdfString('phpdftk v1.0');
        $pdf = $info->toPdf();
        self::assertStringContainsString('/Creator', $pdf);
        self::assertStringContainsString('/Producer', $pdf);
    }

    public function testInfoWithDates(): void
    {
        $info = new Info();
        $info->objectNumber = 5;
        $info->creationDate = new PdfString("D:20240101000000Z");
        $info->modDate = new PdfString("D:20240102000000Z");
        $pdf = $info->toPdf();
        self::assertStringContainsString('/CreationDate', $pdf);
        self::assertStringContainsString('/ModDate', $pdf);
    }

    public function testEmptyInfo(): void
    {
        $info = new Info();
        $info->objectNumber = 5;
        $pdf = $info->toPdf();
        // Should still produce a valid dictionary
        self::assertStringContainsString('<<', $pdf);
        self::assertStringContainsString('>>', $pdf);
    }

    // -----------------------------------------------------------------------
    // ViewerPreferences
    // -----------------------------------------------------------------------

    public function testViewerPreferencesHideToolbar(): void
    {
        $vp = new ViewerPreferences();
        $vp->objectNumber = 6;
        $vp->hideToolbar = true;
        $pdf = $vp->toPdf();
        self::assertStringContainsString('/HideToolbar true', $pdf);
    }

    public function testViewerPreferencesHideMenubar(): void
    {
        $vp = new ViewerPreferences();
        $vp->objectNumber = 6;
        $vp->hideMenubar = false;
        $pdf = $vp->toPdf();
        self::assertStringContainsString('/HideMenubar false', $pdf);
    }

    public function testViewerPreferencesFitWindow(): void
    {
        $vp = new ViewerPreferences();
        $vp->objectNumber = 6;
        $vp->fitWindow = true;
        $pdf = $vp->toPdf();
        self::assertStringContainsString('/FitWindow true', $pdf);
    }

    public function testViewerPreferencesPageDirection(): void
    {
        $vp = new ViewerPreferences();
        $vp->objectNumber = 6;
        $vp->direction = new PdfName('R2L');
        $pdf = $vp->toPdf();
        self::assertStringContainsString('/Direction /R2L', $pdf);
    }

    public function testViewerPreferencesEmpty(): void
    {
        $vp = new ViewerPreferences();
        $vp->objectNumber = 6;
        $pdf = $vp->toPdf();
        self::assertStringContainsString('<<', $pdf);
    }
}
