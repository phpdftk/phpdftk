<?php

declare(strict_types=1);

namespace Phpdftk\Pdf\Writer\Tests;

use Phpdftk\Geometry\Rectangle;
use Phpdftk\Pdf\Core\Annotation\BorderStyle;
use Phpdftk\Pdf\Core\Document\Destination;
use Phpdftk\Pdf\Core\Document\Info;
use Phpdftk\Pdf\Core\Document\Outline;
use Phpdftk\Pdf\Core\Document\OutlineItem;
use Phpdftk\Pdf\Core\Document\PageLabel;
use Phpdftk\Pdf\Core\Font\StandardFont;
use Phpdftk\Pdf\Core\Font\Type1Font;
use Phpdftk\Pdf\Core\PdfName;
use Phpdftk\Pdf\Core\PdfReference;
use Phpdftk\Pdf\Core\PdfString;
use Phpdftk\Pdf\Writer\Pdf;
use Phpdftk\Pdf\Writer\PdfDoc;
use Phpdftk\Pdf\Writer\PdfWriter;
use Phpdftk\Tests\Support\QpdfValidationTrait;
use Phpdftk\Xmp\XmpPacket;
use Phpdftk\Xmp\XmpWriter;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

#[Group("qpdf")]
class PdfDocTest extends TestCase
{
    use QpdfValidationTrait;

    public function testPdfDocExposesUnderlyingWriter(): void
    {
        $doc = new PdfDoc();
        self::assertInstanceOf(PdfWriter::class, $doc->writer());
    }

    public function testWrapReusesExistingWriter(): void
    {
        $writer = new PdfWriter();
        $doc = PdfDoc::wrap($writer);
        self::assertSame($writer, $doc->writer());
    }

    public function testAddPageDelegatesToWriter(): void
    {
        $doc = new PdfDoc();
        $page = $doc->addPage();
        $doc->writer()->addFont(new Type1Font(StandardFont::Helvetica));
        $pdf = $doc->writer()->generate();

        self::assertStringStartsWith('%PDF-', $pdf);
        self::assertStringContainsString('/Type /Pages', $pdf);
        $this->assertQpdfValidBytes($pdf);
    }

    public function testSetInfoAttachesInfoDictionary(): void
    {
        $doc = new PdfDoc(compressStreams: false);
        $doc->addPage();

        $info = new Info();
        $info->title = new PdfString('Doc Title');
        $info->author = new PdfString('phpdftk');
        $doc->setInfo($info);

        $pdf = $doc->writer()->generate();
        self::assertStringContainsString('/Title', $pdf);
        self::assertStringContainsString('Doc Title', $pdf);
        self::assertStringContainsString('phpdftk', $pdf);
    }

    public function testSetOutlineAndAddOutlineItemWireUpCatalog(): void
    {
        $doc = new PdfDoc(compressStreams: false);
        $doc->addPage();

        $outline = new Outline();
        $doc->setOutline($outline);

        $item = new OutlineItem('Chapter 1');
        $item->parent = new PdfReference($outline->objectNumber);
        $ref = $doc->addOutlineItem($item);

        $outline->first = $ref;
        $outline->last = $ref;
        $outline->count = 1;

        $pdf = $doc->writer()->generate();
        self::assertStringContainsString('/Outlines', $pdf);
        self::assertStringContainsString('Chapter 1', $pdf);
    }

    public function testSetPageLabelsRegistersNumberTree(): void
    {
        $doc = new PdfDoc(compressStreams: false);
        $doc->addPage();
        $doc->addPage();

        $front = new PageLabel();
        $front->s = new PdfName('r'); // lowercase roman
        $main = new PageLabel();
        $main->s = new PdfName('D'); // arabic
        $doc->setPageLabels([0 => $front, 1 => $main]);

        $pdf = $doc->writer()->generate();
        self::assertStringContainsString('/PageLabels', $pdf);
        self::assertStringContainsString('/Nums', $pdf);
    }

    public function testSetNamedDestinationsRegistersNameTree(): void
    {
        $doc = new PdfDoc(compressStreams: false);
        $page = $doc->addPage();

        $pageRef = new PdfReference($page->corePage()->objectNumber);
        $dest = Destination::fit($pageRef);
        $doc->setNamedDestinations(['intro' => $dest]);

        $pdf = $doc->writer()->generate();
        self::assertStringContainsString('/Names', $pdf);
        self::assertStringContainsString('/Dests', $pdf);
        self::assertStringContainsString('intro', $pdf);
    }

    public function testSetMetadataAttachesXmpStream(): void
    {
        $doc = new PdfDoc(compressStreams: false);
        $doc->addPage();

        $packet = XmpPacket::create()->set('dc:title', 'Via PdfDoc');
        $xmp = (new XmpWriter())->serialize($packet);
        $doc->setMetadata($xmp);

        $pdf = $doc->writer()->generate();
        self::assertStringContainsString('/Type /Metadata', $pdf);
        self::assertStringContainsString('Via PdfDoc', $pdf);
    }

    public function testSyncInfoToMetadataMirrorsInfoIntoXmp(): void
    {
        $doc = new PdfDoc(compressStreams: false);
        $doc->addPage();

        $info = new Info();
        $info->title = new PdfString('Sync Title');
        $info->author = new PdfString('Sync Author');
        $doc->setInfo($info);
        $doc->syncInfoToMetadata();

        $pdf = $doc->writer()->generate();
        self::assertStringContainsString('/Type /Metadata', $pdf);
        self::assertStringContainsString('Sync Title', $pdf);
        self::assertStringContainsString('Sync Author', $pdf);
    }

    public function testPdfLevelOneExposesPdfDocAccessor(): void
    {
        $pdf = new Pdf();
        self::assertInstanceOf(PdfDoc::class, $pdf->doc());
        self::assertSame($pdf->writer(), $pdf->doc()->writer());
    }

    public function testFluentMetadataSettersPopulateInfoDict(): void
    {
        $doc = new PdfDoc(compressStreams: false);
        $doc->addPage();
        $doc->setTitle('Friendly Title')
            ->setAuthor('Author Person')
            ->setSubject('Doc Subject')
            ->setKeywords('one two three')
            ->setCreator('phpdftk test');

        $pdf = $doc->writer()->generate();
        self::assertStringContainsString('Friendly Title', $pdf);
        self::assertStringContainsString('Author Person', $pdf);
        self::assertStringContainsString('Doc Subject', $pdf);
        self::assertStringContainsString('one two three', $pdf);
    }

    public function testMetadataSettersOnPdfForwardToDoc(): void
    {
        $pdf = new Pdf();
        $pdf->setTitle('From Level 1')->setAuthor('Anonymous');

        $bytes = $pdf->toBytes();
        self::assertStringContainsString('From Level 1', $bytes);
        self::assertStringContainsString('Anonymous', $bytes);
    }

    public function testAddLinkWithUriBuildsActionAnnotation(): void
    {
        $doc = new PdfDoc(compressStreams: false);
        $page = $doc->addPage();
        $rect = new Rectangle(72.0, 700.0, 200.0, 14.0);
        $link = $doc->addLink($page, $rect, 'https://example.com/');

        $pdf = $doc->writer()->generate();
        self::assertStringContainsString('/Subtype /Link', $pdf);
        self::assertStringContainsString('/S /URI', $pdf);
        self::assertStringContainsString('example.com', $pdf);
        self::assertNotNull($link->a);
    }

    public function testAddLinkWithDestinationFillsDestField(): void
    {
        $doc = new PdfDoc(compressStreams: false);
        $page = $doc->addPage();
        $pageRef = new PdfReference($page->corePage()->objectNumber);
        $rect = new Rectangle(72.0, 600.0, 150.0, 14.0);
        $link = $doc->addLink($page, $rect, Destination::fit($pageRef));

        $pdf = $doc->writer()->generate();
        self::assertStringContainsString('/Subtype /Link', $pdf);
        self::assertStringContainsString('/Dest', $pdf);
        self::assertStringContainsString('/Fit', $pdf);
        self::assertNotNull($link->dest);
    }

    public function testAddLinkAttachesBorderStyle(): void
    {
        $doc = new PdfDoc(compressStreams: false);
        $page = $doc->addPage();
        $rect = new Rectangle(72.0, 500.0, 150.0, 14.0);

        $bs = new BorderStyle();
        $bs->s = new \Phpdftk\Pdf\Core\PdfName('D');
        $bs->w = new \Phpdftk\Pdf\Core\PdfNumber(2.0);

        $link = $doc->addLink($page, $rect, 'https://example.com/', $bs);

        $pdf = $doc->writer()->generate();
        self::assertStringContainsString('/BS', $pdf);
        self::assertSame($bs, $link->bs);
    }

    public function testWrapPreservesPreConfiguredWriterState(): void
    {
        $writer = new PdfWriter(compressStreams: false);
        $writer->setLinearized(false);
        $page = $writer->addPage();
        $doc = PdfDoc::wrap($writer);

        // PdfDoc shouldn't add a second page when wrapping.
        $doc->setTitle('Wrapped');

        $pdf = $writer->generate();
        self::assertSame(1, substr_count($pdf, "/Type /Page\n"));
        self::assertStringContainsString('Wrapped', $pdf);
    }

    public function testFluentMetadataChainMutatesSameInfoDict(): void
    {
        $doc = new PdfDoc(compressStreams: false);
        $doc->addPage();
        $doc->setTitle('Original Title');
        $doc->setAuthor('Original Author');
        $doc->setTitle('Updated Title'); // Overwrite

        $pdf = $doc->writer()->generate();
        self::assertStringContainsString('Updated Title', $pdf);
        self::assertStringNotContainsString('Original Title', $pdf);
        self::assertStringContainsString('Original Author', $pdf);
    }

    public function testFluentSetterCreatesInfoDictIfAbsent(): void
    {
        $doc = new PdfDoc(compressStreams: false);
        $doc->addPage();
        // No prior setInfo — first fluent call should create the Info dict.
        $doc->setTitle('Lazy Created');

        $info = $doc->writer()->fileWriter()->getInfo();
        self::assertNotNull($info, 'Info dict should be auto-created by fluent setters');
        self::assertNotNull($info->title);
        self::assertSame('Lazy Created', $info->title->value);
    }

    public function testSyncInfoToMetadataIsNoOpWithoutInfoDict(): void
    {
        $doc = new PdfDoc(compressStreams: false);
        $doc->addPage();
        // No setInfo / setTitle calls — Info dict is null.
        $doc->syncInfoToMetadata();

        $pdf = $doc->writer()->generate();
        self::assertStringNotContainsString('/Type /Metadata', $pdf);
    }

    public function testSetPageLabelsWithEmptyArrayStillProducesTree(): void
    {
        $doc = new PdfDoc(compressStreams: false);
        $doc->addPage();
        $doc->setPageLabels([]);

        $pdf = $doc->writer()->generate();
        // Empty tree still registered on Catalog — /PageLabels reference exists.
        self::assertStringContainsString('/PageLabels', $pdf);
    }

    public function testSetNamedDestinationsWithEmptyArrayStillRegisters(): void
    {
        $doc = new PdfDoc(compressStreams: false);
        $doc->addPage();
        $doc->setNamedDestinations([]);

        $pdf = $doc->writer()->generate();
        self::assertStringContainsString('/Names', $pdf);
        self::assertStringContainsString('/Dests', $pdf);
    }

    public function testAddLinkWithNamedDestinationReferenceUsesPdfReference(): void
    {
        $doc = new PdfDoc(compressStreams: false);
        $page = $doc->addPage();
        $pageRef = new PdfReference($page->corePage()->objectNumber);
        $doc->setNamedDestinations(['intro' => Destination::fit($pageRef)]);

        $rect = new Rectangle(72.0, 600.0, 100.0, 14.0);
        // Reference points to a registered destination; PdfDoc accepts PdfReference.
        $namedRef = new PdfReference(1); // dummy; real users would resolve via the name tree
        $doc->addLink($page, $rect, $namedRef);

        $pdf = $doc->writer()->generate();
        self::assertStringContainsString('/Subtype /Link', $pdf);
        self::assertStringContainsString('/Dest', $pdf);
    }

    public function testAddLinkAcceptsCorePageDirectly(): void
    {
        $doc = new PdfDoc(compressStreams: false);
        $page = $doc->addPage();
        $corePage = $page->corePage();
        $rect = new Rectangle(0.0, 0.0, 100.0, 14.0);

        // Passing CorePage directly (not Writer\Page wrapper) should also work.
        $link = $doc->addLink($corePage, $rect, 'https://example.com/');

        $pdf = $doc->writer()->generate();
        self::assertStringContainsString('/Subtype /Link', $pdf);
        self::assertNotEmpty($corePage->annots, 'Annotation should be attached to corePage->annots');
    }

    public function testAddLinkWithZeroSizeRectStillProducesAnnotation(): void
    {
        $doc = new PdfDoc(compressStreams: false);
        $page = $doc->addPage();
        $rect = new Rectangle(100.0, 100.0, 0.0, 0.0); // degenerate
        $doc->addLink($page, $rect, 'https://example.com/');

        $pdf = $doc->writer()->generate();
        // Degenerate rect is the user's choice; we still emit the annotation.
        self::assertStringContainsString('/Subtype /Link', $pdf);
    }

    public function testPdfDocWriterAndPdfWriterReferenceMatch(): void
    {
        $doc = new PdfDoc();
        self::assertSame($doc->writer(), $doc->writer(), 'writer() must be referentially stable');
    }

    public function testPdfLevel3DocAccessorIsStable(): void
    {
        $pdf = new Pdf();
        self::assertSame($pdf->doc(), $pdf->doc(), 'doc() must return the same instance every call');
    }

    public function testDeprecatedPdfWriterForwardersStillWork(): void
    {
        // Existing tests already exercise this path through PdfWriter; this
        // is an explicit regression guard against the forwarding stubs
        // breaking. Each deprecated call should produce the same catalog
        // entries as the direct PdfDoc call.
        $writer = new PdfWriter(compressStreams: false);
        $writer->addPage();

        $outline = new Outline();
        $writer->setOutline($outline); // deprecated path

        $packet = XmpPacket::create()->set('dc:title', 'Legacy Call');
        $xmp = (new XmpWriter())->serialize($packet);
        $writer->setMetadata($xmp); // deprecated path

        $pdf = $writer->generate();
        self::assertStringContainsString('/Outlines', $pdf);
        self::assertStringContainsString('/Metadata', $pdf);
        self::assertStringContainsString('Legacy Call', $pdf);
    }
}
