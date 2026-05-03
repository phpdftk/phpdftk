<?php

declare(strict_types=1);

namespace Phpdftk\Pdf\Core\Tests\Document;

use PHPUnit\Framework\TestCase;
use Phpdftk\Pdf\Core\Annotation\AppearanceCharacteristics;
use Phpdftk\Pdf\Core\Annotation\AppearanceDict;
use Phpdftk\Pdf\Core\Document\Bead;
use Phpdftk\Pdf\Core\Document\Catalog;
use Phpdftk\Pdf\Core\Document\Collection;
use Phpdftk\Pdf\Core\Document\CollectionItem;
use Phpdftk\Pdf\Core\Document\CollectionSchema;
use Phpdftk\Pdf\Core\Document\Destination;
use Phpdftk\Pdf\Core\Document\GroupAttributes;
use Phpdftk\Pdf\Core\Document\NameTree;
use Phpdftk\Pdf\Core\Document\NumberTree;
use Phpdftk\Pdf\Core\Document\ObjectRef;
use Phpdftk\Pdf\Core\Document\OCG;
use Phpdftk\Pdf\Core\Document\OCMD;
use Phpdftk\Pdf\Core\Document\OCPropertiesDict;
use Phpdftk\Pdf\Core\Document\OutputIntent;
use Phpdftk\Pdf\Core\Document\Page;
use Phpdftk\Pdf\Core\Document\PageTree;
use Phpdftk\Pdf\Core\Document\StructElem;
use Phpdftk\Pdf\Core\Document\StructTreeRoot;
use Phpdftk\Pdf\Core\Document\Thread;
use Phpdftk\Pdf\Core\Document\TransitionDict;
use Phpdftk\Pdf\Core\PdfArray;
use Phpdftk\Pdf\Core\PdfBoolean;
use Phpdftk\Pdf\Core\PdfDictionary;
use Phpdftk\Pdf\Core\PdfName;
use Phpdftk\Pdf\Core\PdfNumber;
use Phpdftk\Pdf\Core\PdfReference;
use Phpdftk\Pdf\Core\PdfString;

class DocumentStructureTest extends TestCase
{
    // -----------------------------------------------------------------------
    // Destination
    // -----------------------------------------------------------------------

    public function testDestinationXyz(): void
    {
        $dest = Destination::xyz(new PdfReference(5), 72.0, 720.0, 1.5);
        $pdf = $dest->toPdf();
        self::assertStringContainsString('5 0 R', $pdf);
        self::assertStringContainsString('/XYZ', $pdf);
        self::assertStringContainsString('72', $pdf);
        self::assertStringContainsString('720', $pdf);
        self::assertStringContainsString('1.5', $pdf);
    }

    public function testDestinationXyzWithNulls(): void
    {
        $dest = Destination::xyz(new PdfReference(5));
        $pdf = $dest->toPdf();
        self::assertStringContainsString('/XYZ', $pdf);
        self::assertStringContainsString('null', $pdf);
    }

    public function testDestinationFit(): void
    {
        $dest = Destination::fit(new PdfReference(3));
        $pdf = $dest->toPdf();
        self::assertStringContainsString('3 0 R', $pdf);
        self::assertStringContainsString('/Fit', $pdf);
    }

    public function testDestinationFitH(): void
    {
        $dest = Destination::fitH(new PdfReference(3), 500.0);
        $pdf = $dest->toPdf();
        self::assertStringContainsString('/FitH', $pdf);
        self::assertStringContainsString('500', $pdf);
    }

    public function testDestinationFitV(): void
    {
        $dest = Destination::fitV(new PdfReference(3), 100.0);
        $pdf = $dest->toPdf();
        self::assertStringContainsString('/FitV', $pdf);
        self::assertStringContainsString('100', $pdf);
    }

    public function testDestinationFitR(): void
    {
        $dest = Destination::fitR(new PdfReference(3), 10.0, 20.0, 500.0, 700.0);
        $pdf = $dest->toPdf();
        self::assertStringContainsString('/FitR', $pdf);
        self::assertStringContainsString('10', $pdf);
        self::assertStringContainsString('20', $pdf);
        self::assertStringContainsString('500', $pdf);
        self::assertStringContainsString('700', $pdf);
    }

    public function testDestinationFitB(): void
    {
        $dest = Destination::fitB(new PdfReference(3));
        $pdf = $dest->toPdf();
        self::assertStringContainsString('/FitB', $pdf);
    }

    public function testDestinationFitBH(): void
    {
        $dest = Destination::fitBH(new PdfReference(3), 400.0);
        $pdf = $dest->toPdf();
        self::assertStringContainsString('/FitBH', $pdf);
        self::assertStringContainsString('400', $pdf);
    }

    public function testDestinationFitBV(): void
    {
        $dest = Destination::fitBV(new PdfReference(3), 50.0);
        $pdf = $dest->toPdf();
        self::assertStringContainsString('/FitBV', $pdf);
        self::assertStringContainsString('50', $pdf);
    }

    // -----------------------------------------------------------------------
    // GroupAttributes
    // -----------------------------------------------------------------------

    public function testGroupAttributesDefaultSubtype(): void
    {
        $group = new GroupAttributes();
        $pdf = $group->toPdf();
        self::assertStringContainsString('/S /Transparency', $pdf);
    }

    public function testGroupAttributesWithColorSpace(): void
    {
        $group = new GroupAttributes();
        $group->cs = new PdfName('DeviceRGB');
        $pdf = $group->toPdf();
        self::assertStringContainsString('/CS /DeviceRGB', $pdf);
    }

    public function testGroupAttributesIsolatedAndKnockout(): void
    {
        $group = new GroupAttributes();
        $group->i = new PdfBoolean(true);
        $group->k = new PdfBoolean(false);
        $pdf = $group->toPdf();
        self::assertStringContainsString('/I true', $pdf);
        self::assertStringContainsString('/K false', $pdf);
    }

    public function testGroupAttributesHasTypeGroup(): void
    {
        $group = new GroupAttributes();
        $pdf = $group->toPdf();
        self::assertStringContainsString('/Type /Group', $pdf);
    }

    // -----------------------------------------------------------------------
    // NameTree
    // -----------------------------------------------------------------------

    public function testNameTreeLeafNode(): void
    {
        $tree = new NameTree();
        $tree->objectNumber = 10;
        $tree->names = new PdfArray([
            new PdfString('key1'),
            new PdfReference(5),
            new PdfString('key2'),
            new PdfReference(6),
        ]);
        $pdf = $tree->toPdf();
        self::assertStringContainsString('/Names', $pdf);
        self::assertStringContainsString('(key1)', $pdf);
        self::assertStringContainsString('5 0 R', $pdf);
    }

    public function testNameTreeIntermediateNode(): void
    {
        $tree = new NameTree();
        $tree->objectNumber = 10;
        $tree->kids = new PdfArray([new PdfReference(11), new PdfReference(12)]);
        $tree->limits = new PdfArray([new PdfString('A'), new PdfString('Z')]);
        $pdf = $tree->toPdf();
        self::assertStringContainsString('/Kids', $pdf);
        self::assertStringContainsString('/Limits', $pdf);
        self::assertStringContainsString('11 0 R', $pdf);
    }

    // -----------------------------------------------------------------------
    // NumberTree
    // -----------------------------------------------------------------------

    public function testNumberTreeLeafNode(): void
    {
        $tree = new NumberTree();
        $tree->objectNumber = 20;
        $tree->nums = new PdfArray([
            new PdfNumber(0),
            new PdfReference(5),
            new PdfNumber(1),
            new PdfReference(6),
        ]);
        $pdf = $tree->toPdf();
        self::assertStringContainsString('/Nums', $pdf);
        self::assertStringContainsString('5 0 R', $pdf);
    }

    public function testNumberTreeIntermediateNode(): void
    {
        $tree = new NumberTree();
        $tree->objectNumber = 20;
        $tree->kids = new PdfArray([new PdfReference(21), new PdfReference(22)]);
        $tree->limits = new PdfArray([new PdfNumber(0), new PdfNumber(99)]);
        $pdf = $tree->toPdf();
        self::assertStringContainsString('/Kids', $pdf);
        self::assertStringContainsString('/Limits', $pdf);
    }

    // -----------------------------------------------------------------------
    // OutputIntent
    // -----------------------------------------------------------------------

    public function testOutputIntentRequiredFields(): void
    {
        $oi = new OutputIntent('GTS_PDFX', 'CGATS TR 001');
        $oi->objectNumber = 30;
        $pdf = $oi->toPdf();
        self::assertStringContainsString('/Type /OutputIntent', $pdf);
        self::assertStringContainsString('/S /GTS_PDFX', $pdf);
        self::assertStringContainsString('/OutputConditionIdentifier', $pdf);
        self::assertStringContainsString('CGATS TR 001', $pdf);
    }

    public function testOutputIntentWithAllFields(): void
    {
        $oi = new OutputIntent('GTS_PDFA1', 'sRGB IEC61966-2.1');
        $oi->objectNumber = 30;
        $oi->outputCondition = new PdfString('sRGB');
        $oi->registryName = new PdfString('http://www.color.org');
        $oi->info = new PdfString('sRGB IEC61966-2.1');
        $oi->destOutputProfile = new PdfReference(40);
        $pdf = $oi->toPdf();
        self::assertStringContainsString('/OutputCondition', $pdf);
        self::assertStringContainsString('/RegistryName', $pdf);
        self::assertStringContainsString('/Info', $pdf);
        self::assertStringContainsString('/DestOutputProfile 40 0 R', $pdf);
    }

    public function testOutputIntentPdfX(): void
    {
        $oi = new OutputIntent('GTS_PDFX', 'FOGRA39');
        $oi->objectNumber = 30;
        $pdf = $oi->toPdf();
        self::assertStringContainsString('/S /GTS_PDFX', $pdf);
        self::assertStringContainsString('FOGRA39', $pdf);
    }

    // -----------------------------------------------------------------------
    // Thread and Bead
    // -----------------------------------------------------------------------

    public function testThreadBasic(): void
    {
        $thread = new Thread();
        $thread->objectNumber = 50;
        $infoDict = new PdfDictionary();
        $infoDict->set('Title', new PdfString('Article 1'));
        $thread->i = $infoDict;
        $thread->f = new PdfReference(51);
        $pdf = $thread->toPdf();
        self::assertStringContainsString('/Type /Thread', $pdf);
        self::assertStringContainsString('/I', $pdf);
        self::assertStringContainsString('/F 51 0 R', $pdf);
    }

    public function testBeadBasic(): void
    {
        $bead = new Bead();
        $bead->objectNumber = 51;
        $bead->t = new PdfReference(50);
        $bead->n = new PdfReference(52);
        $bead->v = new PdfReference(53);
        $bead->p = new PdfReference(10);
        $bead->r = new PdfArray([new PdfNumber(0), new PdfNumber(0), new PdfNumber(200), new PdfNumber(300)]);
        $pdf = $bead->toPdf();
        self::assertStringContainsString('/Type /Bead', $pdf);
        self::assertStringContainsString('/T 50 0 R', $pdf);
        self::assertStringContainsString('/N 52 0 R', $pdf);
        self::assertStringContainsString('/V 53 0 R', $pdf);
        self::assertStringContainsString('/P 10 0 R', $pdf);
        self::assertStringContainsString('/R', $pdf);
    }

    public function testBeadLinking(): void
    {
        $bead1 = new Bead();
        $bead1->objectNumber = 51;
        $bead2 = new Bead();
        $bead2->objectNumber = 52;

        // Wire next/prev
        $bead1->n = new PdfReference($bead2->objectNumber);
        $bead2->v = new PdfReference($bead1->objectNumber);

        $pdf1 = $bead1->toPdf();
        $pdf2 = $bead2->toPdf();

        self::assertStringContainsString('/N 52 0 R', $pdf1);
        self::assertStringContainsString('/V 51 0 R', $pdf2);
    }

    // -----------------------------------------------------------------------
    // PageTree — new fields
    // -----------------------------------------------------------------------

    public function testPageTreeCropBox(): void
    {
        $pt = new PageTree();
        $pt->objectNumber = 2;
        $pt->cropBox = new PdfArray([new PdfNumber(0), new PdfNumber(0), new PdfNumber(612), new PdfNumber(792)]);
        $pdf = $pt->toPdf();
        self::assertStringContainsString('/CropBox', $pdf);
    }

    public function testPageTreeBleedBox(): void
    {
        $pt = new PageTree();
        $pt->objectNumber = 2;
        $pt->bleedBox = new PdfArray([new PdfNumber(0), new PdfNumber(0), new PdfNumber(612), new PdfNumber(792)]);
        $pdf = $pt->toPdf();
        self::assertStringContainsString('/BleedBox', $pdf);
    }

    public function testPageTreeTrimBox(): void
    {
        $pt = new PageTree();
        $pt->objectNumber = 2;
        $pt->trimBox = new PdfArray([new PdfNumber(10), new PdfNumber(10), new PdfNumber(602), new PdfNumber(782)]);
        $pdf = $pt->toPdf();
        self::assertStringContainsString('/TrimBox', $pdf);
    }

    public function testPageTreeArtBox(): void
    {
        $pt = new PageTree();
        $pt->objectNumber = 2;
        $pt->artBox = new PdfArray([new PdfNumber(20), new PdfNumber(20), new PdfNumber(592), new PdfNumber(772)]);
        $pdf = $pt->toPdf();
        self::assertStringContainsString('/ArtBox', $pdf);
    }

    public function testPageTreeTabs(): void
    {
        $pt = new PageTree();
        $pt->objectNumber = 2;
        $pt->tabs = new PdfName('S');
        $pdf = $pt->toPdf();
        self::assertStringContainsString('/Tabs /S', $pdf);
    }

    public function testPageTreeUserUnit(): void
    {
        $pt = new PageTree();
        $pt->objectNumber = 2;
        $pt->userUnit = new PdfNumber(2.0);
        $pdf = $pt->toPdf();
        self::assertStringContainsString('/UserUnit', $pdf);
    }

    // -----------------------------------------------------------------------
    // Page — new fields
    // -----------------------------------------------------------------------

    public function testPageTabs(): void
    {
        $page = new Page();
        $page->objectNumber = 3;
        $page->tabs = new PdfName('R');
        $pdf = $page->toPdf();
        self::assertStringContainsString('/Tabs /R', $pdf);
    }

    public function testPageId(): void
    {
        $page = new Page();
        $page->objectNumber = 3;
        $page->id = new PdfString('page-001');
        $pdf = $page->toPdf();
        self::assertStringContainsString('/ID', $pdf);
        self::assertStringContainsString('page-001', $pdf);
    }

    public function testPagePz(): void
    {
        $page = new Page();
        $page->objectNumber = 3;
        $page->pz = new PdfNumber(1.5);
        $pdf = $page->toPdf();
        self::assertStringContainsString('/PZ', $pdf);
    }

    public function testPageAa(): void
    {
        $page = new Page();
        $page->objectNumber = 3;
        $page->aa = new PdfReference(99);
        $pdf = $page->toPdf();
        self::assertStringContainsString('/AA 99 0 R', $pdf);
    }

    public function testPagePieceInfo(): void
    {
        $page = new Page();
        $page->objectNumber = 3;
        $page->pieceInfo = new PdfReference(88);
        $pdf = $page->toPdf();
        self::assertStringContainsString('/PieceInfo 88 0 R', $pdf);
    }

    // -----------------------------------------------------------------------
    // Catalog — new fields
    // -----------------------------------------------------------------------

    public function testCatalogAa(): void
    {
        $cat = new Catalog();
        $cat->objectNumber = 1;
        $cat->aa = new PdfReference(100);
        $pdf = $cat->toPdf();
        self::assertStringContainsString('/AA 100 0 R', $pdf);
    }

    public function testCatalogUri(): void
    {
        $cat = new Catalog();
        $cat->objectNumber = 1;
        $dict = new PdfDictionary();
        $dict->set('Base', new PdfString('http://example.com'));
        $cat->uri = $dict;
        $pdf = $cat->toPdf();
        self::assertStringContainsString('/URI', $pdf);
    }

    public function testCatalogOutputIntents(): void
    {
        $cat = new Catalog();
        $cat->objectNumber = 1;
        $cat->outputIntents = new PdfArray([new PdfReference(30)]);
        $pdf = $cat->toPdf();
        self::assertStringContainsString('/OutputIntents', $pdf);
        self::assertStringContainsString('30 0 R', $pdf);
    }

    public function testCatalogNeedsRendering(): void
    {
        $cat = new Catalog();
        $cat->objectNumber = 1;
        $cat->needsRendering = new PdfBoolean(true);
        $pdf = $cat->toPdf();
        self::assertStringContainsString('/NeedsRendering true', $pdf);
    }

    public function testCatalogLegal(): void
    {
        $cat = new Catalog();
        $cat->objectNumber = 1;
        $dict = new PdfDictionary();
        $dict->set('Attestation', new PdfString('Legal'));
        $cat->legal = $dict;
        $pdf = $cat->toPdf();
        self::assertStringContainsString('/Legal', $pdf);
    }

    public function testCatalogOcProperties(): void
    {
        $cat = new Catalog();
        $cat->objectNumber = 1;
        $cat->ocProperties = new PdfReference(200);
        $pdf = $cat->toPdf();
        self::assertStringContainsString('/OCProperties 200 0 R', $pdf);
    }

    public function testCatalogPerms(): void
    {
        $cat = new Catalog();
        $cat->objectNumber = 1;
        $dict = new PdfDictionary();
        $dict->set('DocMDP', new PdfReference(300));
        $cat->perms = $dict;
        $pdf = $cat->toPdf();
        self::assertStringContainsString('/Perms', $pdf);
    }

    public function testCatalogRequirements(): void
    {
        $cat = new Catalog();
        $cat->objectNumber = 1;
        $cat->requirements = new PdfArray([new PdfDictionary()]);
        $pdf = $cat->toPdf();
        self::assertStringContainsString('/Requirements', $pdf);
    }

    public function testCatalogCollection(): void
    {
        $cat = new Catalog();
        $cat->objectNumber = 1;
        $cat->collection = new PdfReference(400);
        $pdf = $cat->toPdf();
        self::assertStringContainsString('/Collection 400 0 R', $pdf);
    }

    public function testCatalogSpiderInfo(): void
    {
        $cat = new Catalog();
        $cat->objectNumber = 1;
        $cat->spiderInfo = new PdfReference(500);
        $pdf = $cat->toPdf();
        self::assertStringContainsString('/SpiderInfo 500 0 R', $pdf);
    }

    public function testCatalogPieceInfo(): void
    {
        $cat = new Catalog();
        $cat->objectNumber = 1;
        $dict = new PdfDictionary();
        $dict->set('MyApp', new PdfDictionary());
        $cat->pieceInfo = $dict;
        $pdf = $cat->toPdf();
        self::assertStringContainsString('/PieceInfo', $pdf);
    }

    // -----------------------------------------------------------------------
    // PageTree — 17 new fields
    // -----------------------------------------------------------------------

    public function testPageTreeGroup(): void
    {
        $pt = new PageTree();
        $pt->objectNumber = 2;
        $pt->group = new PdfReference(50);
        $pdf = $pt->toPdf();
        self::assertStringContainsString('/Group 50 0 R', $pdf);
    }

    public function testPageTreeThumb(): void
    {
        $pt = new PageTree();
        $pt->objectNumber = 2;
        $pt->thumb = new PdfReference(51);
        $pdf = $pt->toPdf();
        self::assertStringContainsString('/Thumb 51 0 R', $pdf);
    }

    public function testPageTreeB(): void
    {
        $pt = new PageTree();
        $pt->objectNumber = 2;
        $pt->b = new PdfArray([new PdfReference(60), new PdfReference(61)]);
        $pdf = $pt->toPdf();
        self::assertStringContainsString('/B', $pdf);
        self::assertStringContainsString('60 0 R', $pdf);
    }

    public function testPageTreeDur(): void
    {
        $pt = new PageTree();
        $pt->objectNumber = 2;
        $pt->dur = new PdfNumber(5.0);
        $pdf = $pt->toPdf();
        self::assertStringContainsString('/Dur', $pdf);
    }

    public function testPageTreeTransition(): void
    {
        $pt = new PageTree();
        $pt->objectNumber = 2;
        $trans = new TransitionDict();
        $trans->s = new PdfName('Dissolve');
        $pt->transition = $trans;
        $pdf = $pt->toPdf();
        self::assertStringContainsString('/Trans', $pdf);
        self::assertStringContainsString('/S /Dissolve', $pdf);
    }

    public function testPageTreeAnnots(): void
    {
        $pt = new PageTree();
        $pt->objectNumber = 2;
        $pt->annots = new PdfArray([new PdfReference(70)]);
        $pdf = $pt->toPdf();
        self::assertStringContainsString('/Annots', $pdf);
        self::assertStringContainsString('70 0 R', $pdf);
    }

    public function testPageTreeAa(): void
    {
        $pt = new PageTree();
        $pt->objectNumber = 2;
        $pt->aa = new PdfReference(80);
        $pdf = $pt->toPdf();
        self::assertStringContainsString('/AA 80 0 R', $pdf);
    }

    public function testPageTreeMetadata(): void
    {
        $pt = new PageTree();
        $pt->objectNumber = 2;
        $pt->metadata = new PdfReference(81);
        $pdf = $pt->toPdf();
        self::assertStringContainsString('/Metadata 81 0 R', $pdf);
    }

    public function testPageTreePieceInfo(): void
    {
        $pt = new PageTree();
        $pt->objectNumber = 2;
        $pt->pieceInfo = new PdfDictionary();
        $pt->pieceInfo->set('App', new PdfDictionary());
        $pdf = $pt->toPdf();
        self::assertStringContainsString('/PieceInfo', $pdf);
    }

    public function testPageTreeStructParents(): void
    {
        $pt = new PageTree();
        $pt->objectNumber = 2;
        $pt->structParents = 3;
        $pdf = $pt->toPdf();
        self::assertStringContainsString('/StructParents 3', $pdf);
    }

    public function testPageTreeId(): void
    {
        $pt = new PageTree();
        $pt->objectNumber = 2;
        $pt->id = new PdfString('tree-001');
        $pdf = $pt->toPdf();
        self::assertStringContainsString('/ID', $pdf);
        self::assertStringContainsString('tree-001', $pdf);
    }

    public function testPageTreePz(): void
    {
        $pt = new PageTree();
        $pt->objectNumber = 2;
        $pt->pz = new PdfNumber(1.5);
        $pdf = $pt->toPdf();
        self::assertStringContainsString('/PZ', $pdf);
    }

    public function testPageTreeBoxColorInfo(): void
    {
        $pt = new PageTree();
        $pt->objectNumber = 2;
        $pt->boxColorInfo = new PdfDictionary();
        $pdf = $pt->toPdf();
        self::assertStringContainsString('/BoxColorInfo', $pdf);
    }

    public function testPageTreeSeparationInfo(): void
    {
        $pt = new PageTree();
        $pt->objectNumber = 2;
        $pt->separationInfo = new PdfDictionary();
        $pdf = $pt->toPdf();
        self::assertStringContainsString('/SeparationInfo', $pdf);
    }

    public function testPageTreeTemplateInstantiated(): void
    {
        $pt = new PageTree();
        $pt->objectNumber = 2;
        $pt->templateInstantiated = new PdfName('MyTemplate');
        $pdf = $pt->toPdf();
        self::assertStringContainsString('/TemplateInstantiated /MyTemplate', $pdf);
    }

    public function testPageTreePresSteps(): void
    {
        $pt = new PageTree();
        $pt->objectNumber = 2;
        $pt->presSteps = new PdfReference(90);
        $pdf = $pt->toPdf();
        self::assertStringContainsString('/PresSteps 90 0 R', $pdf);
    }

    public function testPageTreeVp(): void
    {
        $pt = new PageTree();
        $pt->objectNumber = 2;
        $pt->vp = new PdfArray([new PdfDictionary()]);
        $pdf = $pt->toPdf();
        self::assertStringContainsString('/VP', $pdf);
    }

    // -----------------------------------------------------------------------
    // Page — 6 new fields
    // -----------------------------------------------------------------------

    public function testPageBoxColorInfo(): void
    {
        $page = new Page();
        $page->objectNumber = 3;
        $page->boxColorInfo = new PdfDictionary();
        $pdf = $page->toPdf();
        self::assertStringContainsString('/BoxColorInfo', $pdf);
    }

    public function testPageB(): void
    {
        $page = new Page();
        $page->objectNumber = 3;
        $page->b = new PdfArray([new PdfReference(60)]);
        $pdf = $page->toPdf();
        self::assertStringContainsString('/B', $pdf);
        self::assertStringContainsString('60 0 R', $pdf);
    }

    public function testPageSeparationInfo(): void
    {
        $page = new Page();
        $page->objectNumber = 3;
        $page->separationInfo = new PdfDictionary();
        $pdf = $page->toPdf();
        self::assertStringContainsString('/SeparationInfo', $pdf);
    }

    public function testPageTemplateInstantiated(): void
    {
        $page = new Page();
        $page->objectNumber = 3;
        $page->templateInstantiated = new PdfName('MyTemplate');
        $pdf = $page->toPdf();
        self::assertStringContainsString('/TemplateInstantiated /MyTemplate', $pdf);
    }

    public function testPagePresSteps(): void
    {
        $page = new Page();
        $page->objectNumber = 3;
        $page->presSteps = new PdfReference(90);
        $pdf = $page->toPdf();
        self::assertStringContainsString('/PresSteps 90 0 R', $pdf);
    }

    public function testPageVp(): void
    {
        $page = new Page();
        $page->objectNumber = 3;
        $page->vp = new PdfArray([new PdfDictionary()]);
        $pdf = $page->toPdf();
        self::assertStringContainsString('/VP', $pdf);
    }

    // -----------------------------------------------------------------------
    // AppearanceDict
    // -----------------------------------------------------------------------

    public function testAppearanceDictNormal(): void
    {
        $ap = new AppearanceDict();
        $ap->n = new PdfReference(10);
        $pdf = $ap->toPdf();
        self::assertStringContainsString('/N 10 0 R', $pdf);
    }

    public function testAppearanceDictAllStates(): void
    {
        $ap = new AppearanceDict();
        $ap->n = new PdfReference(10);
        $ap->r = new PdfReference(11);
        $ap->d = new PdfReference(12);
        $pdf = $ap->toPdf();
        self::assertStringContainsString('/N 10 0 R', $pdf);
        self::assertStringContainsString('/R 11 0 R', $pdf);
        self::assertStringContainsString('/D 12 0 R', $pdf);
    }

    public function testAppearanceDictEmpty(): void
    {
        $ap = new AppearanceDict();
        $pdf = $ap->toPdf();
        self::assertStringContainsString('<<', $pdf);
        self::assertStringContainsString('>>', $pdf);
    }

    // -----------------------------------------------------------------------
    // AppearanceCharacteristics
    // -----------------------------------------------------------------------

    public function testAppearanceCharacteristicsRotation(): void
    {
        $mk = new AppearanceCharacteristics();
        $mk->r = 90;
        $pdf = $mk->toPdf();
        self::assertStringContainsString('/R 90', $pdf);
    }

    public function testAppearanceCharacteristicsColors(): void
    {
        $mk = new AppearanceCharacteristics();
        $mk->bc = new PdfArray([new PdfNumber(0), new PdfNumber(0), new PdfNumber(0)]);
        $mk->bg = new PdfArray([new PdfNumber(1), new PdfNumber(1), new PdfNumber(1)]);
        $pdf = $mk->toPdf();
        self::assertStringContainsString('/BC', $pdf);
        self::assertStringContainsString('/BG', $pdf);
    }

    public function testAppearanceCharacteristicsCaptions(): void
    {
        $mk = new AppearanceCharacteristics();
        $mk->ca = new PdfString('Normal');
        $mk->rc = new PdfString('Rollover');
        $mk->ac = new PdfString('Alternate');
        $pdf = $mk->toPdf();
        self::assertStringContainsString('/CA', $pdf);
        self::assertStringContainsString('/RC', $pdf);
        self::assertStringContainsString('/AC', $pdf);
    }

    public function testAppearanceCharacteristicsIcons(): void
    {
        $mk = new AppearanceCharacteristics();
        $mk->i = new PdfReference(20);
        $mk->ri = new PdfReference(21);
        $mk->ix = new PdfReference(22);
        $pdf = $mk->toPdf();
        self::assertStringContainsString('/I 20 0 R', $pdf);
        self::assertStringContainsString('/RI 21 0 R', $pdf);
        self::assertStringContainsString('/IX 22 0 R', $pdf);
    }

    public function testAppearanceCharacteristicsIconFit(): void
    {
        $mk = new AppearanceCharacteristics();
        $mk->if_ = new PdfDictionary();
        $mk->if_->set('SW', new PdfName('A'));
        $pdf = $mk->toPdf();
        self::assertStringContainsString('/IF', $pdf);
    }

    public function testAppearanceCharacteristicsTp(): void
    {
        $mk = new AppearanceCharacteristics();
        $mk->tp = 1;
        $pdf = $mk->toPdf();
        self::assertStringContainsString('/TP 1', $pdf);
    }

    // -----------------------------------------------------------------------
    // OCG
    // -----------------------------------------------------------------------

    public function testOcgBasic(): void
    {
        $ocg = new OCG('Watermark');
        $ocg->objectNumber = 100;
        $pdf = $ocg->toPdf();
        self::assertStringContainsString('/Type /OCG', $pdf);
        self::assertStringContainsString('/Name /Watermark', $pdf);
    }

    public function testOcgWithIntent(): void
    {
        $ocg = new OCG('Layer1');
        $ocg->objectNumber = 100;
        $ocg->intent = new PdfName('Design');
        $pdf = $ocg->toPdf();
        self::assertStringContainsString('/Intent /Design', $pdf);
    }

    // -----------------------------------------------------------------------
    // OCMD
    // -----------------------------------------------------------------------

    public function testOcmdBasic(): void
    {
        $ocmd = new OCMD();
        $ocmd->objectNumber = 101;
        $ocmd->ocgs = new PdfArray([new PdfReference(100)]);
        $pdf = $ocmd->toPdf();
        self::assertStringContainsString('/Type /OCMD', $pdf);
        self::assertStringContainsString('/OCGs', $pdf);
        self::assertStringContainsString('100 0 R', $pdf);
    }

    public function testOcmdWithPolicy(): void
    {
        $ocmd = new OCMD();
        $ocmd->objectNumber = 101;
        $ocmd->p = new PdfName('AnyOn');
        $pdf = $ocmd->toPdf();
        self::assertStringContainsString('/P /AnyOn', $pdf);
    }

    // -----------------------------------------------------------------------
    // OCPropertiesDict
    // -----------------------------------------------------------------------

    public function testOcPropertiesBasic(): void
    {
        $ocgs = new PdfArray([new PdfReference(100)]);
        $config = new PdfDictionary();
        $config->set('Name', new PdfString('Default'));
        $ocProps = new OCPropertiesDict($ocgs, $config);
        $ocProps->objectNumber = 102;
        $pdf = $ocProps->toPdf();
        self::assertStringContainsString('/OCGs', $pdf);
        self::assertStringContainsString('/D', $pdf);
    }

    public function testOcPropertiesWithConfigs(): void
    {
        $ocgs = new PdfArray([new PdfReference(100)]);
        $config = new PdfDictionary();
        $ocProps = new OCPropertiesDict($ocgs, $config);
        $ocProps->objectNumber = 102;
        $ocProps->configs = new PdfArray([new PdfDictionary()]);
        $pdf = $ocProps->toPdf();
        self::assertStringContainsString('/Configs', $pdf);
    }

    // -----------------------------------------------------------------------
    // Collection / CollectionItem / CollectionSchema
    // -----------------------------------------------------------------------

    public function testCollectionBasic(): void
    {
        $coll = new Collection();
        $coll->objectNumber = 110;
        $coll->view = new PdfName('D');
        $pdf = $coll->toPdf();
        self::assertStringContainsString('/Type /Collection', $pdf);
        self::assertStringContainsString('/View /D', $pdf);
    }

    public function testCollectionItemBasic(): void
    {
        $item = new CollectionItem();
        $item->objectNumber = 111;
        $item->fields->set('FileName', new PdfString('report.pdf'));
        $pdf = $item->toPdf();
        self::assertStringContainsString('/FileName', $pdf);
        self::assertStringContainsString('report.pdf', $pdf);
    }

    public function testCollectionSchemaBasic(): void
    {
        $schema = new CollectionSchema();
        $schema->objectNumber = 112;
        $schema->fields->set('FileName', new PdfDictionary());
        $pdf = $schema->toPdf();
        self::assertStringContainsString('/Type /CollectionSchema', $pdf);
        self::assertStringContainsString('/FileName', $pdf);
    }

    // -----------------------------------------------------------------------
    // StructTreeRoot
    // -----------------------------------------------------------------------

    public function testStructTreeRootBasic(): void
    {
        $root = new StructTreeRoot();
        $root->objectNumber = 120;
        $root->k = new PdfReference(121);
        $root->roleMap = new PdfDictionary();
        $root->roleMap->set('Figure', new PdfName('Span'));
        $pdf = $root->toPdf();
        self::assertStringContainsString('/Type /StructTreeRoot', $pdf);
        self::assertStringContainsString('/K 121 0 R', $pdf);
        self::assertStringContainsString('/RoleMap', $pdf);
    }

    public function testStructTreeRootWithParentTree(): void
    {
        $root = new StructTreeRoot();
        $root->objectNumber = 120;
        $root->parentTree = new PdfReference(130);
        $root->parentTreeNextKey = 5;
        $pdf = $root->toPdf();
        self::assertStringContainsString('/ParentTree 130 0 R', $pdf);
        self::assertStringContainsString('/ParentTreeNextKey 5', $pdf);
    }

    // -----------------------------------------------------------------------
    // StructElem
    // -----------------------------------------------------------------------

    public function testStructElemBasic(): void
    {
        $elem = new StructElem('P');
        $elem->objectNumber = 121;
        $pdf = $elem->toPdf();
        self::assertStringContainsString('/Type /StructElem', $pdf);
        self::assertStringContainsString('/S /P', $pdf);
    }

    public function testStructElemWithAllFields(): void
    {
        $elem = new StructElem('Document');
        $elem->objectNumber = 121;
        $elem->p = new PdfReference(120);
        $elem->id = new PdfString('elem-001');
        $elem->pg = new PdfReference(3);
        $elem->k = new PdfArray([new PdfReference(122), new PdfReference(123)]);
        $elem->a = new PdfArray([new PdfDictionary()]);
        $elem->c = new PdfArray([new PdfName('class1')]);
        $elem->r = 2;
        $elem->t = new PdfString('Document Title');
        $elem->lang = new PdfString('en-US');
        $elem->alt = new PdfString('Alternative text');
        $elem->e = new PdfString('Expanded');
        $elem->actualText = new PdfString('Actual text');
        $pdf = $elem->toPdf();
        self::assertStringContainsString('/S /Document', $pdf);
        self::assertStringContainsString('/P 120 0 R', $pdf);
        self::assertStringContainsString('/ID', $pdf);
        self::assertStringContainsString('/Pg 3 0 R', $pdf);
        self::assertStringContainsString('/K', $pdf);
        self::assertStringContainsString('/A', $pdf);
        self::assertStringContainsString('/C', $pdf);
        self::assertStringContainsString('/R 2', $pdf);
        self::assertStringContainsString('/T', $pdf);
        self::assertStringContainsString('/Lang', $pdf);
        self::assertStringContainsString('/Alt', $pdf);
        self::assertStringContainsString('/E', $pdf);
        self::assertStringContainsString('/ActualText', $pdf);
    }

    // -----------------------------------------------------------------------
    // ObjectRef
    // -----------------------------------------------------------------------

    public function testObjectRefBasic(): void
    {
        $objRef = new ObjectRef();
        $objRef->objectNumber = 130;
        $objRef->pg = new PdfReference(3);
        $objRef->obj = new PdfReference(70);
        $pdf = $objRef->toPdf();
        self::assertStringContainsString('/Type /OBJR', $pdf);
        self::assertStringContainsString('/Pg 3 0 R', $pdf);
        self::assertStringContainsString('/Obj 70 0 R', $pdf);
    }
}
