<?php

declare(strict_types=1);

namespace ApprLabs\Pdf\Core\Tests\File;

use ApprLabs\Pdf\Core\Document\Catalog;
use ApprLabs\Pdf\Core\Document\Page;
use ApprLabs\Pdf\Core\Document\PageTree;
use ApprLabs\Pdf\Core\File\PdfHydrator;
use ApprLabs\Pdf\Core\PdfArray;
use ApprLabs\Pdf\Core\PdfDictionary;
use ApprLabs\Pdf\Core\PdfName;
use ApprLabs\Pdf\Core\PdfNumber;
use ApprLabs\Pdf\Core\PdfReference;
use ApprLabs\Pdf\Core\PdfString;
use PHPUnit\Framework\TestCase;

class PdfHydratorTest extends TestCase
{
    protected function setUp(): void
    {
        PdfHydrator::registerDefaults();
    }

    public function testHydrateCatalog(): void
    {
        $dict = new PdfDictionary();
        $dict->set('Type', new PdfName('Catalog'));
        $dict->set('Pages', new PdfReference(2));
        $dict->set('PageMode', new PdfName('UseOutlines'));
        $dict->set('Lang', new PdfString('en-US'));

        $result = PdfHydrator::hydrate($dict, objectNumber: 1);

        $this->assertInstanceOf(Catalog::class, $result);
        $this->assertSame(1, $result->objectNumber);
        $this->assertInstanceOf(PdfReference::class, $result->pages);
        $this->assertSame(2, $result->pages->objectNumber);
        $this->assertInstanceOf(PdfName::class, $result->pageMode);
        $this->assertSame('UseOutlines', $result->pageMode->value);
        $this->assertInstanceOf(PdfString::class, $result->lang);
    }

    public function testHydratePage(): void
    {
        $dict = new PdfDictionary();
        $dict->set('Type', new PdfName('Page'));
        $dict->set('Parent', new PdfReference(2));
        $dict->set('MediaBox', new PdfArray([
            new PdfNumber(0), new PdfNumber(0),
            new PdfNumber(612), new PdfNumber(792),
        ]));
        $dict->set('Rotate', new PdfNumber(90));
        $dict->set('Contents', new PdfReference(5));

        $result = PdfHydrator::hydrate($dict, objectNumber: 3);

        $this->assertInstanceOf(Page::class, $result);
        $this->assertSame(3, $result->objectNumber);
        $this->assertInstanceOf(PdfReference::class, $result->parent);
        $this->assertSame(2, $result->parent->objectNumber);
        $this->assertInstanceOf(PdfArray::class, $result->mediaBox);
    }

    public function testHydratePageTree(): void
    {
        $dict = new PdfDictionary();
        $dict->set('Type', new PdfName('Pages'));
        $dict->set('Kids', new PdfArray([new PdfReference(3)]));
        $dict->set('Count', new PdfNumber(1));

        $result = PdfHydrator::hydrate($dict, objectNumber: 2);

        $this->assertInstanceOf(PageTree::class, $result);
        $this->assertSame(2, $result->objectNumber);
    }

    public function testHydrateUnknownTypeReturnsDictionary(): void
    {
        $dict = new PdfDictionary();
        $dict->set('Type', new PdfName('UnknownType'));
        $dict->set('Foo', new PdfName('Bar'));

        $result = PdfHydrator::hydrate($dict);

        $this->assertInstanceOf(PdfDictionary::class, $result);
    }

    public function testHydrateWithoutTypeReturnsDictionary(): void
    {
        $dict = new PdfDictionary();
        $dict->set('Foo', new PdfName('Bar'));

        $result = PdfHydrator::hydrate($dict);

        $this->assertInstanceOf(PdfDictionary::class, $result);
    }

    public function testHydratedObjectCanSerialize(): void
    {
        $dict = new PdfDictionary();
        $dict->set('Type', new PdfName('Catalog'));
        $dict->set('Pages', new PdfReference(2));

        $catalog = PdfHydrator::hydrate($dict, objectNumber: 1);
        $this->assertInstanceOf(Catalog::class, $catalog);

        $pdf = $catalog->toPdf();
        $this->assertStringContainsString('/Type /Catalog', $pdf);
        $this->assertStringContainsString('/Pages 2 0 R', $pdf);
    }

    public function testRegisterCustomType(): void
    {
        // Register a custom type mapping
        PdfHydrator::registerType('Catalog', Catalog::class);

        $dict = new PdfDictionary();
        $dict->set('Type', new PdfName('Catalog'));
        $dict->set('Pages', new PdfReference(2));

        $result = PdfHydrator::hydrate($dict);
        $this->assertInstanceOf(Catalog::class, $result);
    }

    public function testHydrateExtGState(): void
    {
        $dict = new PdfDictionary();
        $dict->set('Type', new PdfName('ExtGState'));
        $dict->set('CA', new PdfNumber(0.5));

        $result = PdfHydrator::hydrate($dict, objectNumber: 10);

        $this->assertInstanceOf(\ApprLabs\Pdf\Core\Graphics\ExtGState::class, $result);
        $this->assertSame(0.5, $result->ca);
    }

    public function testHydrateFontDescriptorWithRequiredArg(): void
    {
        $dict = new PdfDictionary();
        $dict->set('Type', new PdfName('FontDescriptor'));
        $dict->set('FontName', new PdfName('Helvetica'));
        $dict->set('Flags', new PdfNumber(32));
        $dict->set('ItalicAngle', new PdfNumber(0));

        $result = PdfHydrator::hydrate($dict, objectNumber: 5);

        $this->assertInstanceOf(\ApprLabs\Pdf\Core\Font\FontDescriptor::class, $result);
        $this->assertSame('Helvetica', $result->fontName->value);
        $this->assertSame(32, $result->flags);
    }

    public function testHydrateAnnotationBySubtype(): void
    {
        $dict = new PdfDictionary();
        $dict->set('Type', new PdfName('Annot'));
        $dict->set('Subtype', new PdfName('Text'));
        $dict->set('Rect', new PdfArray([
            new PdfNumber(0), new PdfNumber(0),
            new PdfNumber(100), new PdfNumber(100),
        ]));

        $result = PdfHydrator::hydrate($dict, objectNumber: 7);

        $this->assertInstanceOf(\ApprLabs\Pdf\Core\Annotation\TextAnnotation::class, $result);
    }

    public function testHydrateAnnotationLinkBySubtype(): void
    {
        $dict = new PdfDictionary();
        $dict->set('Type', new PdfName('Annot'));
        $dict->set('Subtype', new PdfName('Link'));
        $dict->set('Rect', new PdfArray([
            new PdfNumber(0), new PdfNumber(0),
            new PdfNumber(200), new PdfNumber(50),
        ]));

        $result = PdfHydrator::hydrate($dict, objectNumber: 8);

        $this->assertInstanceOf(\ApprLabs\Pdf\Core\Annotation\LinkAnnotation::class, $result);
    }

    public function testHydrateSignatureValue(): void
    {
        $dict = new PdfDictionary();
        $dict->set('Type', new PdfName('Sig'));
        $dict->set('Filter', new PdfName('Adobe.PPKLite'));
        $dict->set('SubFilter', new PdfName('adbe.pkcs7.detached'));

        $result = PdfHydrator::hydrate($dict, objectNumber: 12);

        $this->assertInstanceOf(\ApprLabs\Pdf\Core\Interactive\Signature\SignatureValue::class, $result);
    }

    public function testHydrateOutlineItemWithRequiredArg(): void
    {
        $dict = new PdfDictionary();
        $dict->set('Title', new PdfString('Chapter 1'));
        $dict->set('Parent', new PdfReference(3));
        $dict->set('Count', new PdfNumber(2));

        // OutlineItem doesn't have /Type — the hydrator won't match it
        // by type, but if we register it manually it should work
        PdfHydrator::registerType('_OutlineItem', \ApprLabs\Pdf\Core\Document\OutlineItem::class);
        $dict->set('Type', new PdfName('_OutlineItem'));

        $result = PdfHydrator::hydrate($dict, objectNumber: 4);

        $this->assertInstanceOf(\ApprLabs\Pdf\Core\Document\OutlineItem::class, $result);
        $this->assertSame('Chapter 1', $result->title->value);
    }

    public function testHydrateEmbeddedFile(): void
    {
        $dict = new PdfDictionary();
        $dict->set('Type', new PdfName('EmbeddedFile'));
        $dict->set('Subtype', new PdfName('application/pdf'));

        $result = PdfHydrator::hydrate($dict, objectNumber: 15);

        $this->assertInstanceOf(\ApprLabs\Pdf\Core\FileSpec\EmbeddedFile::class, $result);
    }

    public function testHydrateMetadataStream(): void
    {
        $dict = new PdfDictionary();
        $dict->set('Type', new PdfName('Metadata'));
        $dict->set('Subtype', new PdfName('XML'));

        $result = PdfHydrator::hydrate($dict, objectNumber: 20);

        $this->assertInstanceOf(\ApprLabs\Pdf\Core\Document\MetadataStream::class, $result);
    }

    public function testSubtypeRegistration(): void
    {
        $quad = new PdfArray([
            new PdfNumber(0), new PdfNumber(0),
            new PdfNumber(100), new PdfNumber(0),
            new PdfNumber(100), new PdfNumber(20),
            new PdfNumber(0), new PdfNumber(20),
        ]);
        $dict = new PdfDictionary();
        $dict->set('Type', new PdfName('Annot'));
        $dict->set('Subtype', new PdfName('Highlight'));
        $dict->set('Rect', new PdfArray([
            new PdfNumber(0), new PdfNumber(0),
            new PdfNumber(100), new PdfNumber(20),
        ]));
        $dict->set('QuadPoints', $quad);

        $result = PdfHydrator::hydrate($dict, objectNumber: 9);

        $this->assertInstanceOf(\ApprLabs\Pdf\Core\Annotation\HighlightAnnotation::class, $result);
    }

    public function testUnknownSubtypeReturnsDictionary(): void
    {
        $dict = new PdfDictionary();
        $dict->set('Type', new PdfName('Annot'));
        $dict->set('Subtype', new PdfName('CustomAnnot'));
        $dict->set('Rect', new PdfArray([
            new PdfNumber(0), new PdfNumber(0),
            new PdfNumber(100), new PdfNumber(100),
        ]));

        $result = PdfHydrator::hydrate($dict);

        // No base Annot class registered (it's abstract), so falls through
        $this->assertInstanceOf(PdfDictionary::class, $result);
    }
}
