<?php

declare(strict_types=1);

namespace Phpdftk\Pdf\Conformance\Tests\Inspection;

use Phpdftk\Pdf\Conformance\Inspection\WriterDocumentInspector;
use Phpdftk\Pdf\Core\Document\Catalog;
use Phpdftk\Pdf\Core\Document\Info;
use Phpdftk\Pdf\Core\Document\MetadataStream;
use Phpdftk\Pdf\Core\Document\NamesDictionary;
use Phpdftk\Pdf\Core\Document\OutputIntent;
use Phpdftk\Pdf\Core\Document\Page;
use Phpdftk\Pdf\Core\File\PdfFileWriter;
use Phpdftk\Pdf\Core\Graphics\XObject\FormXObject;
use Phpdftk\Pdf\Core\Graphics\XObject\ImageXObject;
use Phpdftk\Pdf\Core\PdfArray;
use Phpdftk\Pdf\Core\PdfDictionary;
use Phpdftk\Pdf\Core\PdfNumber;
use Phpdftk\Pdf\Core\PdfReference;
use Phpdftk\Pdf\Core\PdfStream;
use PHPUnit\Framework\TestCase;

class WriterDocumentInspectorTest extends TestCase
{
    private function makeInspector(?Catalog $catalog = null, ?PdfFileWriter $fw = null, array $fonts = []): WriterDocumentInspector
    {
        return new WriterDocumentInspector($catalog ?? new Catalog(), $fw ?? new PdfFileWriter(), $fonts);
    }

    public function testEmptyInspector(): void
    {
        $insp = $this->makeInspector();
        $this->assertNull($insp->getInfo());
        $this->assertFalse($insp->hasEncryption());
        $this->assertFalse($insp->hasXmpMetadata());
        $this->assertNull($insp->getXmpBytes());
        $this->assertFalse($insp->hasOutputIntents());
        $this->assertFalse($insp->hasOutputIntentWithIccProfile());
        $this->assertFalse($insp->hasTransparency());
        $this->assertFalse($insp->hasJavaScript());
        $this->assertFalse($insp->hasEmbeddedFiles());
        $this->assertFalse($insp->hasThreeDAnnotations());
        $this->assertFalse($insp->hasMultimediaContent());
        $this->assertFalse($insp->hasInteractiveForms());
        $this->assertTrue($insp->hasRasterOnlyContent()); // no fonts present
    }

    public function testHasXmpMetadataAndGetXmpBytes(): void
    {
        $catalog = new Catalog();
        $fw = new PdfFileWriter();
        $xmpBytes = '<?xml version="1.0"?><x:xmpmeta xmlns:x="adobe:ns:meta/"/>';
        $metaStream = new MetadataStream($xmpBytes);
        $fw->register($metaStream);
        $catalog->metadata = new PdfReference($metaStream->objectNumber);

        $insp = $this->makeInspector($catalog, $fw);
        $this->assertTrue($insp->hasXmpMetadata());
        $this->assertSame($xmpBytes, $insp->getXmpBytes());
    }

    public function testGetXmpBytesFallsThroughOnNonMetadataStream(): void
    {
        $catalog = new Catalog();
        $fw = new PdfFileWriter();

        // Register a plain PdfStream — getXmpBytes should still return the stream data
        // via the second branch (PdfStream check).
        $stream = new PdfStream(new PdfDictionary(), 'plain stream data');
        $fw->register($stream);
        $catalog->metadata = new PdfReference($stream->objectNumber);

        $insp = $this->makeInspector($catalog, $fw);
        $this->assertSame('plain stream data', $insp->getXmpBytes());
    }

    public function testHasOutputIntentsTrueAndIccProfile(): void
    {
        $catalog = new Catalog();
        $fw = new PdfFileWriter();
        $oi = new OutputIntent('GTS_PDFA1', 'sRGB');
        $oi->destOutputProfile = new PdfReference(99);
        $fw->register($oi);
        $catalog->outputIntents = new PdfArray([new PdfReference($oi->objectNumber)]);

        $insp = $this->makeInspector($catalog, $fw);
        $this->assertTrue($insp->hasOutputIntents());
        $this->assertTrue($insp->hasOutputIntentWithIccProfile());
    }

    public function testHasEmbeddedFilesViaCatalogNames(): void
    {
        $catalog = new Catalog();
        $fw = new PdfFileWriter();
        $names = new NamesDictionary();
        $fw->register($names);
        $catalog->names = new PdfReference($names->objectNumber);

        $insp = $this->makeInspector($catalog, $fw);
        $this->assertTrue($insp->hasEmbeddedFiles());
    }

    public function testHasInteractiveForms(): void
    {
        $catalog = new Catalog();
        $fw = new PdfFileWriter();
        $catalog->acroForm = new PdfReference(99);
        $insp = $this->makeInspector($catalog, $fw);
        $this->assertTrue($insp->hasInteractiveForms());
    }

    public function testGetPagesAndImageXObjects(): void
    {
        $fw = new PdfFileWriter();
        $page1 = new Page();
        $page1->mediaBox = new PdfArray([new PdfNumber(0), new PdfNumber(0), new PdfNumber(100), new PdfNumber(100)]);
        $fw->register($page1);

        $img = new ImageXObject(10, 10, 'DeviceRGB', 8, '');
        $fw->register($img);

        $insp = $this->makeInspector(null, $fw);
        $this->assertCount(1, iterator_to_array($insp->getPages()));
        $this->assertCount(1, iterator_to_array($insp->getImageXObjects()));
    }

    public function testGetReferenceXObjectsFiltersByRef(): void
    {
        $fw = new PdfFileWriter();
        $f1 = new FormXObject(new PdfArray([
            new PdfNumber(0), new PdfNumber(0), new PdfNumber(10), new PdfNumber(10),
        ]));
        $f1->ref = new PdfReference(99);
        $fw->register($f1);

        $f2 = new FormXObject(new PdfArray([
            new PdfNumber(0), new PdfNumber(0), new PdfNumber(10), new PdfNumber(10),
        ]));
        $fw->register($f2);

        $insp = $this->makeInspector(null, $fw);
        $this->assertCount(1, iterator_to_array($insp->getReferenceXObjects()));
    }

    public function testGetRegisteredObjectsYieldsRegistry(): void
    {
        $fw = new PdfFileWriter();
        $page = new Page();
        $page->mediaBox = new PdfArray([new PdfNumber(0), new PdfNumber(0), new PdfNumber(100), new PdfNumber(100)]);
        $fw->register($page);

        $insp = $this->makeInspector(null, $fw);
        $registered = iterator_to_array($insp->getRegisteredObjects(), preserve_keys: false);
        $this->assertNotEmpty($registered);
    }

    public function testHasTransparencyWhenPageHasGroup(): void
    {
        $fw = new PdfFileWriter();
        $page = new Page();
        $page->mediaBox = new PdfArray([new PdfNumber(0), new PdfNumber(0), new PdfNumber(100), new PdfNumber(100)]);
        $page->group = new PdfReference(99);
        $fw->register($page);

        $insp = $this->makeInspector(null, $fw);
        $this->assertTrue($insp->hasTransparency());
    }

    public function testGetCatalogReturnsConfiguredCatalog(): void
    {
        $catalog = new Catalog();
        $insp = $this->makeInspector($catalog);
        $this->assertSame($catalog, $insp->getCatalog());
    }

    public function testGetInfoReturnsWriterInfo(): void
    {
        $fw = new PdfFileWriter();
        $info = new Info();
        $fw->register($info);
        $fw->setInfo($info);

        $insp = $this->makeInspector(null, $fw);
        $this->assertSame($info, $insp->getInfo());
    }
}
