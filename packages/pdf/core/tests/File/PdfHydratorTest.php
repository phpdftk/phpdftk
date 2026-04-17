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
}
