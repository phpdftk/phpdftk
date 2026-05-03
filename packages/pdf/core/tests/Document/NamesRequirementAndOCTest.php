<?php

declare(strict_types=1);

namespace Phpdftk\Pdf\Core\Tests\Document;

use Phpdftk\Pdf\Core\Document\DPart;
use Phpdftk\Pdf\Core\Document\DPartRoot;
use Phpdftk\Pdf\Core\Document\HintStream;
use Phpdftk\Pdf\Core\Document\LinearizationParameters;
use Phpdftk\Pdf\Core\Document\MetadataStream;
use Phpdftk\Pdf\Core\Document\NamesDictionary;
use Phpdftk\Pdf\Core\Document\OCConfig;
use Phpdftk\Pdf\Core\Document\OCUsage;
use Phpdftk\Pdf\Core\Document\Requirement;
use Phpdftk\Pdf\Core\Document\RequirementHandler;
use Phpdftk\Pdf\Core\Document\StandardStructureType;
use Phpdftk\Pdf\Core\Document\StructAttribute\LayoutAttribute;
use Phpdftk\Pdf\Core\Document\StructAttribute\ListAttribute;
use Phpdftk\Pdf\Core\Document\StructAttribute\PrintFieldAttribute;
use Phpdftk\Pdf\Core\Document\StructAttribute\TableAttribute;
use Phpdftk\Pdf\Core\PdfArray;
use Phpdftk\Pdf\Core\PdfDictionary;
use Phpdftk\Pdf\Core\PdfName;
use Phpdftk\Pdf\Core\PdfNumber;
use Phpdftk\Pdf\Core\PdfReference;
use Phpdftk\Pdf\Core\PdfString;
use PHPUnit\Framework\TestCase;

class NamesRequirementAndOCTest extends TestCase
{
    public function testNamesDictionary(): void
    {
        $n = new NamesDictionary();
        $n->objectNumber = 1;
        $n->dests = new PdfReference(10);
        $n->embeddedFiles = new PdfReference(11);
        $n->javaScript = new PdfReference(12);
        $pdf = $n->toPdf();
        self::assertStringContainsString('/Dests 10 0 R', $pdf);
        self::assertStringContainsString('/EmbeddedFiles 11 0 R', $pdf);
        self::assertStringContainsString('/JavaScript 12 0 R', $pdf);
    }

    public function testRequirement(): void
    {
        $r = new Requirement('EnableJavaScripts');
        $r->objectNumber = 1;
        $r->rh = new PdfArray([new PdfReference(50)]);
        $pdf = $r->toPdf();
        self::assertStringContainsString('/Type /Requirement', $pdf);
        self::assertStringContainsString('/S /EnableJavaScripts', $pdf);
        self::assertStringContainsString('/RH', $pdf);
    }

    public function testRequirementHandler(): void
    {
        $rh = new RequirementHandler('JS');
        $rh->objectNumber = 1;
        $rh->script = new PdfString('return true;');
        $pdf = $rh->toPdf();
        self::assertStringContainsString('/Type /ReqHandler', $pdf);
        self::assertStringContainsString('/S /JS', $pdf);
        self::assertStringContainsString('/Script', $pdf);
    }

    public function testOCUsage(): void
    {
        $u = new OCUsage();
        $u->creatorInfo = new PdfDictionary(['Creator' => new PdfString('phpdftk')]);
        $u->print = new PdfDictionary(['PrintState' => new PdfName('OFF')]);
        $pdf = $u->toPdf();
        self::assertStringContainsString('/CreatorInfo', $pdf);
        self::assertStringContainsString('/Print', $pdf);
    }

    public function testOCConfig(): void
    {
        $c = new OCConfig();
        $c->objectNumber = 1;
        $c->name = new PdfString('Default');
        $c->baseState = new PdfName('ON');
        $c->order = new PdfArray([new PdfReference(5)]);
        $pdf = $c->toPdf();
        self::assertStringContainsString('/Name (Default)', $pdf);
        self::assertStringContainsString('/BaseState /ON', $pdf);
        self::assertStringContainsString('/Order', $pdf);
    }

    public function testDPartRootAndDPart(): void
    {
        $root = new DPartRoot(new PdfReference(10));
        $root->objectNumber = 1;
        $root->nodeNameList = new PdfArray([new PdfString('part')]);
        $root->recordLevel = 1;
        $pdf = $root->toPdf();
        self::assertStringContainsString('/Type /DPartRoot', $pdf);
        self::assertStringContainsString('/DPartRootNode', $pdf);

        $part = new DPart(new PdfReference(1));
        $part->objectNumber = 2;
        $part->start = new PdfReference(20);
        $part->dpm = new PdfDictionary(['JobID' => new PdfString('A1')]);
        $pdf2 = $part->toPdf();
        self::assertStringContainsString('/Type /DPart', $pdf2);
        self::assertStringContainsString('/Start 20 0 R', $pdf2);
        self::assertStringContainsString('/DPM', $pdf2);
    }

    public function testLinearizationAndHintStream(): void
    {
        $lin = new LinearizationParameters();
        $lin->objectNumber = 1;
        $lin->l = 100000;
        $lin->o = 5;
        $lin->n = 10;
        $lin->h = new PdfArray([new PdfNumber(123), new PdfNumber(456)]);
        $pdf = $lin->toPdf();
        self::assertStringContainsString('/Linearized 1', $pdf);
        self::assertStringContainsString('/L 100000', $pdf);
        self::assertStringContainsString('/N 10', $pdf);

        $hs = new HintStream('fakehint');
        $hs->objectNumber = 1;
        $hs->p = 0;
        $hs->s = 100;
        $hs->t = 200;
        $pdf2 = $hs->toIndirectObject();
        self::assertStringContainsString('/P 0', $pdf2);
        self::assertStringContainsString('/S 100', $pdf2);
        self::assertStringContainsString('stream', $pdf2);
    }

    public function testMetadataStream(): void
    {
        $m = new MetadataStream('<?xpacket?><rdf:RDF/><?xpacket end="w"?>');
        $m->objectNumber = 1;
        $pdf = $m->toIndirectObject();
        self::assertStringContainsString('/Type /Metadata', $pdf);
        self::assertStringContainsString('/Subtype /XML', $pdf);
        self::assertStringContainsString('xpacket', $pdf);
    }

    public function testStandardStructureTypeConstants(): void
    {
        self::assertSame('H1', StandardStructureType::H1);
        self::assertSame('Document', StandardStructureType::DOCUMENT);
        self::assertSame('TD', StandardStructureType::TD);
        self::assertSame('Artifact', StandardStructureType::ARTIFACT);
    }

    public function testStructAttributeHelpers(): void
    {
        $layout = new LayoutAttribute();
        $layout->entries['Placement'] = new PdfName('Block');
        self::assertStringContainsString('/O /Layout', $layout->toPdf());
        self::assertStringContainsString('/Placement /Block', $layout->toPdf());

        $list = new ListAttribute();
        $list->entries['ListNumbering'] = new PdfName('Decimal');
        self::assertStringContainsString('/O /List', $list->toPdf());

        $pf = new PrintFieldAttribute();
        self::assertStringContainsString('/O /PrintField', $pf->toPdf());

        $tbl = new TableAttribute();
        $tbl->entries['RowSpan'] = 2;
        self::assertStringContainsString('/O /Table', $tbl->toPdf());
        self::assertStringContainsString('/RowSpan 2', $tbl->toPdf());
    }
}
