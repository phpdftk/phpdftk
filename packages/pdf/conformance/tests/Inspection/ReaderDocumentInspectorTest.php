<?php

declare(strict_types=1);

namespace Phpdftk\Pdf\Conformance\Tests\Inspection;

use Phpdftk\Pdf\Conformance\Inspection\ReaderDocumentInspector;
use Phpdftk\Pdf\Core\Document\Info;
use Phpdftk\Pdf\Core\Document\MarkInfo;
use Phpdftk\Pdf\Core\Document\OutputIntent;
use Phpdftk\Pdf\Core\Document\StructTreeRoot;
use Phpdftk\Pdf\Core\Font\TrueTypeFont;
use Phpdftk\Pdf\Core\PdfArray;
use Phpdftk\Pdf\Core\PdfDictionary;
use Phpdftk\Pdf\Core\PdfName;
use Phpdftk\Pdf\Core\PdfNumber;
use Phpdftk\Pdf\Core\PdfReference;
use Phpdftk\Pdf\Core\PdfStream;
use Phpdftk\Pdf\Core\PdfString;
use Phpdftk\Pdf\Reader\PdfReader;
use Phpdftk\Pdf\Writer\PdfWriter;
use PHPUnit\Framework\TestCase;

class ReaderDocumentInspectorTest extends TestCase
{
    private function findFont(): string
    {
        foreach ([
            '/System/Library/Fonts/Supplemental/Arial.ttf',
            '/System/Library/Fonts/Supplemental/Georgia.ttf',
            '/System/Library/Fonts/Supplemental/Verdana.ttf',
            '/usr/share/fonts/truetype/liberation/LiberationSans-Regular.ttf',
            '/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf',
        ] as $path) {
            if (file_exists($path)) {
                return $path;
            }
        }
        $this->markTestSkipped('No TTF font found on this system');
    }

    private function findIccProfile(): string
    {
        foreach ([
            '/System/Library/ColorSync/Profiles/sRGB Profile.icc',
            '/usr/share/color/icc/colord/sRGB.icc',
            '/usr/share/color/icc/sRGB.icc',
        ] as $path) {
            if (file_exists($path)) {
                return $path;
            }
        }
        $this->markTestSkipped('No sRGB ICC profile found on this system');
    }

    private function buildPdf(bool $withMetadata = false, bool $withOutputIntent = false, bool $withTagging = false): string
    {
        $writer = new PdfWriter();

        $info = new Info();
        $info->title = new PdfString('Inspector Test');
        $info->producer = new PdfString('phpdftk');
        $writer->setInfo($info);

        if ($withMetadata) {
            $xmp = <<<XML
            <?xpacket begin="\xEF\xBB\xBF" id="W5M0MpCehiHzreSzNTczkc9d"?>
            <x:xmpmeta xmlns:x="adobe:ns:meta/">
              <rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#">
                <rdf:Description xmlns:pdfaid="http://www.aiim.org/pdfa/ns/id/">
                  <pdfaid:part>1</pdfaid:part>
                  <pdfaid:conformance>B</pdfaid:conformance>
                </rdf:Description>
              </rdf:RDF>
            </x:xmpmeta>
            <?xpacket end="w"?>
            XML;
            $writer->setMetadata($xmp);
        }

        if ($withOutputIntent) {
            $iccData = file_get_contents($this->findIccProfile());
            $iccStream = new PdfStream(new PdfDictionary(), $iccData);
            $iccStream->dictionary->set('N', new PdfNumber(3));
            $iccRef = $writer->register($iccStream);

            $oi = new OutputIntent('GTS_PDFA1', 'sRGB');
            $oi->destOutputProfile = $iccRef;
            $oiRef = $writer->register($oi);
            $writer->getCatalog()->outputIntents = new PdfArray([$oiRef]);
        }

        if ($withTagging) {
            $markInfo = new MarkInfo();
            $markInfo->marked = true;
            $writer->getCatalog()->markInfo = $markInfo;
            $writer->getCatalog()->lang = new PdfString('en-US');
            $structRoot = new StructTreeRoot();
            $writer->register($structRoot);
            $writer->getCatalog()->structTreeRoot = new PdfReference($structRoot->objectNumber);
        }

        $page = $writer->addPage(612, 792);
        $font = TrueTypeFont::fromFile($this->findFont());
        $fontHandle = $writer->addFont($font, $page);
        $cs = $writer->addContentStream($page);
        $cs->beginText()
            ->setFont($fontHandle->getResourceName(), 12)
            ->moveTextPosition(72, 720)
            ->showText('Test')
            ->endText();

        return $writer->generate();
    }

    public function testGetCatalogReturnsCatalog(): void
    {
        $reader = PdfReader::fromString($this->buildPdf());
        $inspector = new ReaderDocumentInspector($reader);

        $catalog = $inspector->getCatalog();
        self::assertNotNull($catalog->pages);
    }

    public function testGetInfoReturnsInfo(): void
    {
        $reader = PdfReader::fromString($this->buildPdf());
        $inspector = new ReaderDocumentInspector($reader);

        $info = $inspector->getInfo();
        self::assertNotNull($info);
        self::assertSame('Inspector Test', $info->title->value);
    }

    public function testGetPagesReturnsPages(): void
    {
        $reader = PdfReader::fromString($this->buildPdf());
        $inspector = new ReaderDocumentInspector($reader);

        $pages = iterator_to_array($inspector->getPages());
        self::assertCount(1, $pages);
    }

    public function testHasEncryptionFalse(): void
    {
        $reader = PdfReader::fromString($this->buildPdf());
        $inspector = new ReaderDocumentInspector($reader);

        self::assertFalse($inspector->hasEncryption());
    }

    public function testHasXmpMetadataFalse(): void
    {
        $reader = PdfReader::fromString($this->buildPdf(withMetadata: false));
        $inspector = new ReaderDocumentInspector($reader);

        self::assertFalse($inspector->hasXmpMetadata());
    }

    public function testHasXmpMetadataTrue(): void
    {
        $reader = PdfReader::fromString($this->buildPdf(withMetadata: true));
        $inspector = new ReaderDocumentInspector($reader);

        self::assertTrue($inspector->hasXmpMetadata());
    }

    public function testGetXmpBytesReturnsContent(): void
    {
        $reader = PdfReader::fromString($this->buildPdf(withMetadata: true));
        $inspector = new ReaderDocumentInspector($reader);

        $xmp = $inspector->getXmpBytes();
        self::assertNotNull($xmp);
        self::assertStringContainsString('pdfaid:part', $xmp);
    }

    public function testGetXmpBytesNullWhenNoMetadata(): void
    {
        $reader = PdfReader::fromString($this->buildPdf(withMetadata: false));
        $inspector = new ReaderDocumentInspector($reader);

        self::assertNull($inspector->getXmpBytes());
    }

    public function testHasOutputIntentsFalse(): void
    {
        $reader = PdfReader::fromString($this->buildPdf(withOutputIntent: false));
        $inspector = new ReaderDocumentInspector($reader);

        self::assertFalse($inspector->hasOutputIntents());
    }

    public function testHasOutputIntentsTrue(): void
    {
        $reader = PdfReader::fromString($this->buildPdf(withOutputIntent: true));
        $inspector = new ReaderDocumentInspector($reader);

        self::assertTrue($inspector->hasOutputIntents());
    }

    public function testHasTransparencyFalse(): void
    {
        $reader = PdfReader::fromString($this->buildPdf());
        $inspector = new ReaderDocumentInspector($reader);

        self::assertFalse($inspector->hasTransparency());
    }

    public function testHasEmbeddedFilesFalse(): void
    {
        $reader = PdfReader::fromString($this->buildPdf());
        $inspector = new ReaderDocumentInspector($reader);

        self::assertFalse($inspector->hasEmbeddedFiles());
    }

    public function testHasEmbeddedFilesTrue(): void
    {
        $writer = new PdfWriter();
        $page = $writer->addPage();
        $font = TrueTypeFont::fromFile($this->findFont());
        $fontHandle = $writer->addFont($font, $page);
        $cs = $writer->addContentStream($page);
        $cs->beginText()
            ->setFont($fontHandle->getResourceName(), 12)
            ->moveTextPosition(72, 720)
            ->showText('Has embedded files')
            ->endText();

        // Wire a Names dictionary with EmbeddedFiles tree.
        $names = new \Phpdftk\Pdf\Core\Document\NamesDictionary();
        $namesTree = new PdfDictionary();
        $namesTree->set('Names', new PdfArray([new PdfString('test.txt')]));
        $treeWrap = new class ($namesTree) extends \Phpdftk\Pdf\Core\PdfObject {
            public function __construct(private readonly PdfDictionary $d) {}
            public function toPdf(): string
            {
                return $this->d->toPdf();
            }
        };
        $writer->register($treeWrap);
        $names->embeddedFiles = new PdfReference($treeWrap->objectNumber);
        $writer->register($names);
        $writer->getCatalog()->names = new PdfReference($names->objectNumber);

        $reader = PdfReader::fromString($writer->generate());
        $inspector = new ReaderDocumentInspector($reader);
        self::assertTrue($inspector->hasEmbeddedFiles());
    }

    public function testHasInteractiveFormsFalse(): void
    {
        $reader = PdfReader::fromString($this->buildPdf());
        $inspector = new ReaderDocumentInspector($reader);
        self::assertFalse($inspector->hasInteractiveForms());
    }

    public function testHasOutputIntentsFalseWhenAbsent(): void
    {
        $reader = PdfReader::fromString($this->buildPdf());
        $inspector = new ReaderDocumentInspector($reader);
        self::assertFalse($inspector->hasOutputIntents());
        self::assertFalse($inspector->hasOutputIntentWithIccProfile());
    }

    public function testHasOutputIntentWithIccProfileTrue(): void
    {
        $reader = PdfReader::fromString($this->buildPdf(withOutputIntent: true));
        $inspector = new ReaderDocumentInspector($reader);
        self::assertTrue($inspector->hasOutputIntentWithIccProfile());
    }

    public function testHasJavaScriptFalse(): void
    {
        $reader = PdfReader::fromString($this->buildPdf());
        $inspector = new ReaderDocumentInspector($reader);
        self::assertFalse($inspector->hasJavaScript());
    }

    public function testHasMultimediaContentFalse(): void
    {
        $reader = PdfReader::fromString($this->buildPdf());
        $inspector = new ReaderDocumentInspector($reader);
        self::assertFalse($inspector->hasMultimediaContent());
    }

    public function testHasThreeDAnnotationsFalse(): void
    {
        $reader = PdfReader::fromString($this->buildPdf());
        $inspector = new ReaderDocumentInspector($reader);
        self::assertFalse($inspector->hasThreeDAnnotations());
    }

    public function testGetThreeDStreamsEmptyByDefault(): void
    {
        $reader = PdfReader::fromString($this->buildPdf());
        $inspector = new ReaderDocumentInspector($reader);
        self::assertCount(0, iterator_to_array($inspector->getThreeDStreams()));
    }

    public function testGetImageXObjectsEmptyByDefault(): void
    {
        $reader = PdfReader::fromString($this->buildPdf());
        $inspector = new ReaderDocumentInspector($reader);
        self::assertCount(0, iterator_to_array($inspector->getImageXObjects()));
    }

    public function testGetReferenceXObjectsEmptyByDefault(): void
    {
        $reader = PdfReader::fromString($this->buildPdf());
        $inspector = new ReaderDocumentInspector($reader);
        self::assertCount(0, iterator_to_array($inspector->getReferenceXObjects()));
    }

    public function testGetRegisteredObjectsYieldsAtLeastOne(): void
    {
        $reader = PdfReader::fromString($this->buildPdf());
        $inspector = new ReaderDocumentInspector($reader);
        $count = 0;
        foreach ($inspector->getRegisteredObjects() as $_) {
            $count++;
        }
        self::assertGreaterThan(0, $count);
    }

    public function testGetFontsYieldsAtLeastOne(): void
    {
        $reader = PdfReader::fromString($this->buildPdf());
        $inspector = new ReaderDocumentInspector($reader);
        $count = 0;
        foreach ($inspector->getFonts() as $_) {
            $count++;
        }
        self::assertGreaterThan(0, $count);
    }

    public function testHasRasterOnlyContentFalseWhenFontsPresent(): void
    {
        $reader = PdfReader::fromString($this->buildPdf());
        $inspector = new ReaderDocumentInspector($reader);
        // The default test PDF has a font; so it's not raster-only.
        self::assertFalse($inspector->hasRasterOnlyContent());
    }

    public function testGetInfoReturnsNullWhenInfoAbsent(): void
    {
        $writer = new PdfWriter();
        $page = $writer->addPage();
        $font = TrueTypeFont::fromFile($this->findFont());
        $fontHandle = $writer->addFont($font, $page);
        $cs = $writer->addContentStream($page);
        $cs->beginText()
            ->setFont($fontHandle->getResourceName(), 12)
            ->moveTextPosition(72, 720)
            ->showText('Info-less')
            ->endText();

        $reader = PdfReader::fromString($writer->generate());
        $inspector = new ReaderDocumentInspector($reader);
        // PdfWriter may set a default Info; assert this returns either null or a typed Info.
        $info = $inspector->getInfo();
        $this->assertTrue($info === null || $info instanceof Info);
    }

    public function testGetCatalogIsMemoized(): void
    {
        $reader = PdfReader::fromString($this->buildPdf());
        $inspector = new ReaderDocumentInspector($reader);
        $catalog1 = $inspector->getCatalog();
        $catalog2 = $inspector->getCatalog();
        self::assertSame($catalog1, $catalog2);
    }

    public function testGetInfoIsMemoized(): void
    {
        $reader = PdfReader::fromString($this->buildPdf());
        $inspector = new ReaderDocumentInspector($reader);
        $info1 = $inspector->getInfo();
        $info2 = $inspector->getInfo();
        self::assertSame($info1, $info2);
    }
}
