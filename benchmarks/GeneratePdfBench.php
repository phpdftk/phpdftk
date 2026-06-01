<?php

declare(strict_types=1);

namespace Phpdftk\Benchmarks;

use PhpBench\Attributes as Bench;
use Phpdftk\Pdf\Core\Annotation\HighlightAnnotation;
use Phpdftk\Pdf\Core\Annotation\LineAnnotation;
use Phpdftk\Pdf\Core\Annotation\SquareAnnotation;
use Phpdftk\Pdf\Core\Annotation\TextAnnotation;
use Phpdftk\Pdf\Core\Document\Destination;
use Phpdftk\Pdf\Core\Document\MarkInfo;
use Phpdftk\Pdf\Core\Document\Outline;
use Phpdftk\Pdf\Core\Document\OutlineItem;
use Phpdftk\Pdf\Core\Document\OutputIntent;
use Phpdftk\Pdf\Core\Document\PageLabel;
use Phpdftk\Pdf\Core\Document\StructElem;
use Phpdftk\Pdf\Core\Document\StructTreeRoot;
use Phpdftk\Pdf\Core\Document\TransitionDict;
use Phpdftk\Pdf\Core\Content\ContentStream;
use Phpdftk\Pdf\Core\Document\CrossReferenceStream;
use Phpdftk\Pdf\Core\Document\ObjectStream;
use Phpdftk\Pdf\Core\Font\Encoding;
use Phpdftk\Pdf\Core\Font\StandardFont;
use Phpdftk\Pdf\Core\Font\TrueTypeFont;
use Phpdftk\Pdf\Core\Font\Type1Font;
use Phpdftk\Pdf\Core\Font\Type3Font;
use Phpdftk\Pdf\Core\Graphics\ColorSpace\DeviceRGB;
use Phpdftk\Pdf\Core\Graphics\Function\FunctionType2;
use Phpdftk\Pdf\Core\Graphics\Pattern\ShadingPattern;
use Phpdftk\Pdf\Core\Graphics\Pattern\TilingPattern;
use Phpdftk\Pdf\Core\Graphics\Shading\ShadingType2;
use Phpdftk\Pdf\Core\Multimedia\MediaClipData;
use Phpdftk\Pdf\Core\Multimedia\MediaRendition;
use Phpdftk\Pdf\Core\ThreeD\ThreeDStream;
use Phpdftk\Pdf\Core\ThreeD\ThreeDView;
use Phpdftk\Pdf\Core\FileSpec\FileSpec;
use Phpdftk\Pdf\Core\Annotation\ScreenAnnotation;
use Phpdftk\Pdf\Core\Annotation\ThreeDAnnotation;
use Phpdftk\Pdf\Core\Action\RenditionAction;
use Phpdftk\Pdf\Core\Annotation\WidgetAnnotation;
use Phpdftk\Pdf\Core\Interactive\Form\AcroForm;
use Phpdftk\Pdf\Core\Interactive\Form\SignatureField;
use Phpdftk\Pdf\Core\Interactive\Signature\DocMDPTransformParams;
use Phpdftk\Pdf\Core\Interactive\Signature\Pkcs7Signer;
use Phpdftk\Pdf\Core\Interactive\Signature\SignatureReference;
use Phpdftk\Pdf\Core\Interactive\Signature\SignatureValue;
use Phpdftk\Pdf\Core\PdfArray;
use Phpdftk\Pdf\Core\PdfDictionary;
use Phpdftk\Pdf\Core\PdfName;
use Phpdftk\Pdf\Core\PdfNumber;
use Phpdftk\Pdf\Core\PdfReference;
use Phpdftk\Pdf\Core\PdfString;
use Phpdftk\Pdf\Core\Interactive\Signature\CertificateUtils;
use Phpdftk\Pdf\Conformance\Profile\PdfAProfile;
use Phpdftk\Pdf\Conformance\Profile\PdfEProfile;
use Phpdftk\Pdf\Conformance\Profile\PdfRProfile;
use Phpdftk\Pdf\Conformance\Profile\PdfUaProfile;
use Phpdftk\Pdf\Conformance\Profile\PdfVtProfile;
use Phpdftk\Pdf\Conformance\Profile\PdfXProfile;
use Phpdftk\Pdf\Toolkit\LtvSigner;
use Phpdftk\Pdf\Writer\PdfWriter;

#[Bench\Iterations(5)]
#[Bench\Revs(3)]
class GeneratePdfBench
{
    private string $tempDir;

    public function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/phpdftk_bench';
        if (!is_dir($this->tempDir)) {
            mkdir($this->tempDir, 0755, true);
        }
    }

    // -----------------------------------------------------------------------
    // phpdftk benchmarks
    // -----------------------------------------------------------------------

    /**
     * @BeforeMethods({"setUp"})
     */
    #[Bench\Subject]
    #[Bench\BeforeMethods('setUp')]
    public function benchPhpdftk1Page(): void
    {
        $writer = new PdfWriter();
        $page   = $writer->addPage(612, 792);
        $writer->addFont(new Type1Font(StandardFont::Helvetica));
        $cs = $writer->addContentStream($page);
        $cs->beginText()
           ->setFont('F1', 12)
           ->moveTextPosition(72, 720)
           ->showText('Hello World - phpdftk benchmark 1 page')
           ->endText();

        $writer->save($this->tempDir . '/phpdftk_1page.pdf');
    }

    #[Bench\Subject]
    #[Bench\BeforeMethods('setUp')]
    public function benchPhpdftk5Pages(): void
    {
        $writer   = new PdfWriter();
        $fontName = $writer->addFont(new Type1Font(StandardFont::Helvetica))->getResourceName();

        for ($i = 1; $i <= 5; $i++) {
            $page = $writer->addPage(612, 792);
            $cs   = $writer->addContentStream($page);
            $cs->beginText()
               ->setFont($fontName, 12)
               ->moveTextPosition(72, 720)
               ->showText(sprintf('Page %d of 5 — phpdftk benchmark', $i))
               ->moveTextPosition(0, -20)
               ->showText('The quick brown fox jumps over the lazy dog.')
               ->endText();
        }

        $writer->save($this->tempDir . '/phpdftk_5pages.pdf');
    }

    #[Bench\Subject]
    #[Bench\BeforeMethods('setUp')]
    public function benchPhpdftk10Pages(): void
    {
        $writer   = new PdfWriter();
        $fontName = $writer->addFont(new Type1Font(StandardFont::Helvetica))->getResourceName();

        for ($i = 1; $i <= 10; $i++) {
            $page = $writer->addPage(612, 792);
            $cs   = $writer->addContentStream($page);
            $cs->beginText()
               ->setFont($fontName, 12)
               ->moveTextPosition(72, 720)
               ->showText(sprintf('Page %d of 10 — phpdftk benchmark', $i))
               ->moveTextPosition(0, -20)
               ->showText('The quick brown fox jumps over the lazy dog.')
               ->endText();
        }

        $writer->save($this->tempDir . '/phpdftk_10pages.pdf');
    }

    #[Bench\Subject]
    #[Bench\BeforeMethods('setUp')]
    public function benchPhpdftk50Pages(): void
    {
        $writer   = new PdfWriter();
        $fontName = $writer->addFont(new Type1Font(StandardFont::Helvetica))->getResourceName();

        for ($i = 1; $i <= 50; $i++) {
            $page = $writer->addPage(612, 792);
            $cs   = $writer->addContentStream($page);
            $cs->beginText()
               ->setFont($fontName, 12)
               ->moveTextPosition(72, 720)
               ->showText(sprintf('Page %d of 50 — phpdftk benchmark', $i))
               ->moveTextPosition(0, -20)
               ->showText('The quick brown fox jumps over the lazy dog.')
               ->endText();
        }

        $writer->save($this->tempDir . '/phpdftk_50pages.pdf');
    }

    #[Bench\Subject]
    #[Bench\BeforeMethods('setUp')]
    public function benchPhpdftk100Pages(): void
    {
        $writer   = new PdfWriter();
        $fontName = $writer->addFont(new Type1Font(StandardFont::Helvetica))->getResourceName();

        for ($i = 1; $i <= 100; $i++) {
            $page = $writer->addPage(612, 792);
            $cs   = $writer->addContentStream($page);
            $cs->beginText()
               ->setFont($fontName, 12)
               ->moveTextPosition(72, 720)
               ->showText(sprintf('Page %d of 100 — phpdftk benchmark', $i))
               ->moveTextPosition(0, -20)
               ->showText('The quick brown fox jumps over the lazy dog.')
               ->endText();
        }

        $writer->save($this->tempDir . '/phpdftk_100pages.pdf');
    }

    /**
     * 10-page PDF with bookmarks (Outline + OutlineItems) and page transitions.
     * Exercises Tier 1 & 2 spec additions without competitors.
     */
    #[Bench\Subject]
    #[Bench\BeforeMethods('setUp')]
    public function benchPhpdftk10PagesWithBookmarksAndTransitions(): void
    {
        $writer   = new PdfWriter();
        $fontName = $writer->addFont(new Type1Font(StandardFont::Helvetica))->getResourceName();

        $outline  = $writer->setOutline(new Outline());
        $prevRef  = null;

        for ($i = 1; $i <= 10; $i++) {
            $transition = new TransitionDict();
            $transition->s = new PdfName('Dissolve');
            $transition->d = new PdfNumber(0.5);

            $page = $writer->addPage(612, 792);
            $page->corePage()->transition = $transition;
            $page->corePage()->dur        = new PdfNumber(5.0);

            $cs = $writer->addContentStream($page);
            $cs->beginText()
               ->setFont($fontName, 12)
               ->moveTextPosition(72, 720)
               ->showText(sprintf('Chapter %d', $i))
               ->moveTextPosition(0, -20)
               ->showText('The quick brown fox jumps over the lazy dog.')
               ->endText();

            $item = new OutlineItem(sprintf('Chapter %d', $i));
            $item->dest = new PdfName('ch' . $i);
            if ($prevRef !== null) {
                $item->prev = $prevRef;
            }
            $ref = $writer->addOutlineItem($item);
            if ($prevRef !== null) {
                // back-patch next on previous item (object already registered; just update property)
            }
            if ($i === 1) {
                $outline->first = $ref;
            }
            $outline->last = $ref;
            $outline->count = $i;
            $prevRef = $ref;
        }

        $writer->save($this->tempDir . '/phpdftk_10pages_bookmarks.pdf');
    }

    /**
     * 10-page PDF with various annotation types on each page.
     */
    #[Bench\Subject]
    #[Bench\BeforeMethods('setUp')]
    public function benchPhpdftk10PagesWithAnnotations(): void
    {
        $writer   = new PdfWriter();
        $fontName = $writer->addFont(new Type1Font(StandardFont::Helvetica))->getResourceName();

        for ($i = 1; $i <= 10; $i++) {
            $page = $writer->addPage(612, 792);
            $cs   = $writer->addContentStream($page);
            $cs->beginText()
               ->setFont($fontName, 12)
               ->moveTextPosition(72, 720)
               ->showText(sprintf('Page %d — Annotations benchmark', $i))
               ->endText();

            // TextAnnotation (sticky note)
            $textAnnot = new TextAnnotation(
                new PdfArray([new PdfNumber(72), new PdfNumber(680), new PdfNumber(120), new PdfNumber(710)]),
            );
            $textAnnot->contents = new PdfString(sprintf('Note on page %d', $i));
            $textAnnot->name = new PdfName('Note');
            $writer->register($textAnnot);
            $page->corePage()->annots[] = new PdfReference($textAnnot->objectNumber);

            // HighlightAnnotation
            $highlight = new HighlightAnnotation(
                new PdfArray([new PdfNumber(72), new PdfNumber(620), new PdfNumber(300), new PdfNumber(640)]),
                new PdfArray([
                    new PdfNumber(72), new PdfNumber(640),
                    new PdfNumber(300), new PdfNumber(640),
                    new PdfNumber(72), new PdfNumber(620),
                    new PdfNumber(300), new PdfNumber(620),
                ]),
            );
            $highlight->c = new PdfArray([new PdfNumber(1), new PdfNumber(1), new PdfNumber(0)]);
            $writer->register($highlight);
            $page->corePage()->annots[] = new PdfReference($highlight->objectNumber);

            // LineAnnotation
            $line = new LineAnnotation(
                new PdfArray([new PdfNumber(72), new PdfNumber(560), new PdfNumber(300), new PdfNumber(590)]),
            );
            $line->l = new PdfArray([
                new PdfNumber(72), new PdfNumber(575),
                new PdfNumber(300), new PdfNumber(575),
            ]);
            $line->le = new PdfArray([new PdfName('None'), new PdfName('OpenArrow')]);
            $writer->register($line);
            $page->corePage()->annots[] = new PdfReference($line->objectNumber);

            // SquareAnnotation
            $square = new SquareAnnotation(
                new PdfArray([new PdfNumber(72), new PdfNumber(480), new PdfNumber(200), new PdfNumber(540)]),
            );
            $square->ic = new PdfArray([new PdfNumber(0.8), new PdfNumber(0.9), new PdfNumber(1.0)]);
            $writer->register($square);
            $page->corePage()->annots[] = new PdfReference($square->objectNumber);
        }

        $writer->save($this->tempDir . '/phpdftk_10pages_annotations.pdf');
    }

    /**
     * 10-page PDF with an embedded TrueType font.
     * Skips silently if no TTF found on system.
     */
    #[Bench\Subject]
    #[Bench\BeforeMethods('setUp')]
    public function benchPhpdftk10PagesWithEmbeddedFont(): void
    {
        $fontPath = null;
        foreach ([
            '/System/Library/Fonts/Supplemental/Arial.ttf',
            '/System/Library/Fonts/Supplemental/Georgia.ttf',
            '/System/Library/Fonts/Supplemental/Verdana.ttf',
            '/usr/share/fonts/truetype/liberation/LiberationSans-Regular.ttf',
            '/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf',
        ] as $path) {
            if (file_exists($path)) {
                $fontPath = $path;
                break;
            }
        }

        if ($fontPath === null) {
            return;
        }

        $writer   = new PdfWriter();
        $font     = TrueTypeFont::fromFile($fontPath);
        $fontName = $writer->addFont($font)->getResourceName();

        for ($i = 1; $i <= 10; $i++) {
            $page = $writer->addPage(612, 792);
            $cs   = $writer->addContentStream($page);
            $cs->beginText()
               ->setFont($fontName, 14)
               ->moveTextPosition(72, 720)
               ->showText(sprintf('Embedded font page %d of 10', $i))
               ->moveTextPosition(0, -24)
               ->showText('The quick brown fox jumps over the lazy dog.')
               ->endText();
        }

        $writer->save($this->tempDir . '/phpdftk_10pages_embedded_font.pdf');
    }

    /**
     * 10-page PDF with OutputIntent, named destinations, page labels,
     * and StructTreeRoot for tagged PDF.
     */
    #[Bench\Subject]
    #[Bench\BeforeMethods('setUp')]
    public function benchPhpdftk10PagesWithDocumentStructure(): void
    {
        $writer   = new PdfWriter();
        $fontName = $writer->addFont(new Type1Font(StandardFont::Helvetica))->getResourceName();

        // OutputIntent
        $outputIntent = new OutputIntent('GTS_PDFX', 'CGATS TR 001');
        $outputIntent->registryName = new PdfString('http://www.color.org');
        $outputIntent->info = new PdfString('sRGB IEC61966-2.1');
        $writer->register($outputIntent);
        $writer->getCatalog()->outputIntents = new PdfArray([
            new PdfReference($outputIntent->objectNumber),
        ]);

        // Create pages
        $pages = [];
        $namedDests = [];
        for ($i = 1; $i <= 10; $i++) {
            $page = $writer->addPage(612, 792);
            $pages[] = $page;
            $cs = $writer->addContentStream($page);
            $cs->beginText()
               ->setFont($fontName, 12)
               ->moveTextPosition(72, 720)
               ->showText(sprintf('Structured document — page %d', $i))
               ->endText();

            $namedDests['page' . $i] = Destination::xyz(
                new PdfReference($page->corePage()->objectNumber),
                72,
                720,
                1.0,
            );
        }

        // Named destinations
        $writer->setNamedDestinations($namedDests);

        // Page labels: roman for first 3, arabic for the rest
        $romanLabel = new PageLabel();
        $romanLabel->s = new PdfName('r');
        $arabicLabel = new PageLabel();
        $arabicLabel->s = new PdfName('D');
        $writer->setPageLabels([
            0 => $romanLabel,
            3 => $arabicLabel,
        ]);

        // StructTreeRoot with a StructElem per page
        $structRoot = new StructTreeRoot();
        $structRoot->roleMap = new PdfDictionary();
        $writer->register($structRoot);

        $childRefs = [];
        foreach ($pages as $idx => $page) {
            $elem = new StructElem('P');
            $elem->p = new PdfReference($structRoot->objectNumber);
            $elem->pg = new PdfReference($page->corePage()->objectNumber);
            $writer->register($elem);
            $childRefs[] = new PdfReference($elem->objectNumber);
        }

        $structRoot->k = new PdfArray($childRefs);

        $writer->getCatalog()->structTreeRoot = new PdfReference($structRoot->objectNumber);

        // MarkInfo
        $markInfo = new MarkInfo();
        $markInfo->marked = true;
        $writer->getCatalog()->markInfo = $markInfo;

        $writer->save($this->tempDir . '/phpdftk_10pages_structure.pdf');
    }

    /**
     * 10-page PDF that uses a custom Type 3 font whose glyphs are inline
     * content streams.
     */
    #[Bench\Subject]
    #[Bench\BeforeMethods('setUp')]
    public function benchPhpdftk10PagesWithType3Font(): void
    {
        $writer = new PdfWriter();

        // Square glyph
        $sq = new ContentStream();
        $sq->setGlyphWidthAndBoundingBox(700, 0, 0, 0, 700, 700)
           ->rectangle(50, 50, 600, 600)
           ->fill();
        $sqRef = $writer->register($sq);

        // Triangle glyph
        $tri = new ContentStream();
        $tri->setGlyphWidthAndBoundingBox(700, 0, 0, 0, 700, 700)
            ->moveTo(350, 650)
            ->lineTo(50, 50)
            ->lineTo(650, 50)
            ->closePath()
            ->fill();
        $triRef = $writer->register($tri);

        $encoding = new Encoding();
        $encoding->differences = new PdfArray([
            new PdfNumber(65),
            new PdfName('square'),
            new PdfName('triangle'),
        ]);
        $encodingRef = $writer->register($encoding);

        $font = new Type3Font('BenchType3');
        $font->fontBBox = new PdfArray([
            new PdfNumber(0), new PdfNumber(0),
            new PdfNumber(700), new PdfNumber(700),
        ]);
        $font->firstChar = 65;
        $font->lastChar = 66;
        $font->widths = new PdfArray([new PdfNumber(700), new PdfNumber(700)]);
        $font->encoding = $encodingRef;
        $font->addCharProc('square', $sqRef);
        $font->addCharProc('triangle', $triRef);
        $font->resources = new PdfDictionary([
            'ProcSet' => new PdfArray([new PdfName('PDF')]),
        ]);
        $fontName = $writer->addFont($font)->getResourceName();

        for ($i = 1; $i <= 10; $i++) {
            $page = $writer->addPage(612, 792);
            $cs = $writer->addContentStream($page);
            $cs->beginText()
               ->setFont($fontName, 36)
               ->moveTextPosition(72, 700)
               ->showText('ABAB')
               ->endText();
        }

        $writer->save($this->tempDir . '/phpdftk_10pages_type3.pdf');
    }

    /**
     * Hand-rolled PDF 1.5 file with a CrossReferenceStream trailer and an
     * ObjectStream for compressed object packing.
     */
    #[Bench\Subject]
    #[Bench\BeforeMethods('setUp')]
    public function benchPhpdftkXRefAndObjectStreams(): void
    {
        $catalog = new \Phpdftk\Pdf\Core\Document\Catalog();
        $catalog->objectNumber = 1;

        $pageTree = new \Phpdftk\Pdf\Core\Document\PageTree();
        $pageTree->objectNumber = 2;

        $page = new \Phpdftk\Pdf\Core\Document\Page();
        $page->objectNumber = 3;
        $page->parent = new PdfReference($pageTree->objectNumber);
        $page->mediaBox = new PdfArray([
            new PdfNumber(0), new PdfNumber(0),
            new PdfNumber(612), new PdfNumber(792),
        ]);
        $page->resources = new \Phpdftk\Pdf\Core\Content\Resources();

        $info = new \Phpdftk\Pdf\Core\Document\Info();
        $info->objectNumber = 4;
        $info->title = new PdfString('XRef Stream Bench');

        $catalog->pages = new PdfReference($pageTree->objectNumber);
        $pageTree->kids = [new PdfReference($page->objectNumber)];
        $pageTree->count = 1;

        $objStm = new ObjectStream();
        $objStm->objectNumber = 5;
        $objStm->addObject($info);

        $chunks = ["%PDF-1.5\n", "%\xE2\xE3\xCF\xD3\n"];
        $offset = strlen($chunks[0]) + strlen($chunks[1]);
        $offsets = [];
        foreach ([$catalog, $pageTree, $page, $objStm] as $obj) {
            $offsets[$obj->objectNumber] = $offset;
            $c = $obj->toIndirectObject() . "\n";
            $chunks[] = $c;
            $offset += strlen($c);
        }

        $xref = new CrossReferenceStream();
        $xref->objectNumber = 6;
        $xref->size = 7;
        $xref->root = new PdfReference($catalog->objectNumber);
        $xref->info = new PdfReference($info->objectNumber);
        $xref->addFreeEntry(0, 65535);
        $xref->addInUseEntry($offsets[1]);
        $xref->addInUseEntry($offsets[2]);
        $xref->addInUseEntry($offsets[3]);
        $xref->addCompressedEntry($objStm->objectNumber, 0);
        $xref->addInUseEntry($offsets[5]);
        $xrefOffset = $offset;
        $xref->addInUseEntry($xrefOffset);

        $chunks[] = $xref->toIndirectObject() . "\n";
        $chunks[] = "startxref\n" . $xrefOffset . "\n";
        $chunks[] = '%%EOF';

        file_put_contents($this->tempDir . '/phpdftk_xref_stream.pdf', implode('', $chunks));
    }

    /**
     * 10-page PDF exercising axial shading + shading pattern + tiling pattern.
     */
    #[Bench\Subject]
    #[Bench\BeforeMethods('setUp')]
    public function benchPhpdftk10PagesWithShadingsAndPatterns(): void
    {
        $writer = new PdfWriter();
        $fontName = $writer->addFont(new Type1Font(StandardFont::Helvetica))->getResourceName();

        $ramp = new FunctionType2(
            new PdfArray([new PdfNumber(0), new PdfNumber(1)]),
            new PdfArray([new PdfNumber(1), new PdfNumber(0), new PdfNumber(0)]),
            new PdfArray([new PdfNumber(0), new PdfNumber(0), new PdfNumber(1)]),
            1.0,
        );
        $rampRef = $writer->register($ramp);

        $axial = new ShadingType2(
            new DeviceRGB(),
            new PdfArray([
                new PdfNumber(72), new PdfNumber(600),
                new PdfNumber(540), new PdfNumber(600),
            ]),
            $rampRef,
        );
        $axialRef = $writer->register($axial);
        $axialPatternRef = $writer->register(new ShadingPattern($axialRef));

        $tiling = new TilingPattern(
            paintType: 1,
            tilingType: 1,
            bbox: new PdfArray([
                new PdfNumber(0), new PdfNumber(0),
                new PdfNumber(20), new PdfNumber(20),
            ]),
            xStep: 20,
            yStep: 20,
            resources: new \Phpdftk\Pdf\Core\Content\Resources(),
            contentStream: "0 0.6 0 rg 0 0 20 20 re f 1 0 0 rg 5 5 10 10 re f",
        );
        $tilingRef = $writer->register($tiling);

        for ($i = 1; $i <= 10; $i++) {
            $page = $writer->addPage(612, 792);
            if ($page->corePage()->resources !== null) {
                $page->corePage()->resources->pattern = [
                    'P1' => $axialPatternRef,
                    'P2' => $tilingRef,
                ];
            }
            $cs = $writer->addContentStream($page);
            $cs->beginText()
               ->setFont($fontName, 14)
               ->moveTextPosition(72, 740)
               ->showText(sprintf('Shading + pattern page %d of 10', $i))
               ->endText();
            $cs->raw('/Pattern cs');
            $cs->raw('/P1 scn');
            $cs->rectangle(72, 580, 468, 40)->fill();
            $cs->raw('/Pattern cs');
            $cs->raw('/P2 scn');
            $cs->rectangle(72, 120, 468, 80)->fill();
        }

        $writer->save($this->tempDir . '/phpdftk_10pages_shading_pattern.pdf');
    }

    /**
     * 10-page PDF with a Screen annotation playing a media rendition on
     * each page and a 3D annotation driving a ThreeDStream + view.
     */
    #[Bench\Subject]
    #[Bench\BeforeMethods('setUp')]
    public function benchPhpdftk10PagesWithMultimediaAnd3D(): void
    {
        $writer = new PdfWriter();
        $fontName = $writer->addFont(new Type1Font(StandardFont::Helvetica))->getResourceName();

        $clipSpec = new FileSpec('clip.mp3');
        $clipSpecRef = $writer->register($clipSpec);
        $clip = new MediaClipData($clipSpecRef);
        $clip->ct = new PdfString('audio/mpeg');
        $clipRef = $writer->register($clip);
        $rendition = new MediaRendition();
        $rendition->c = $clipRef;
        $renditionRef = $writer->register($rendition);

        $u3d = new ThreeDStream('U3D', "bench u3d bytes");
        $u3dRef = $writer->register($u3d);
        $view = new ThreeDView('BenchView');
        $view->co = 100.0;
        $viewRef = $writer->register($view);
        $u3d->va = new PdfArray([$viewRef]);
        $u3d->dv = $viewRef;

        for ($i = 1; $i <= 10; $i++) {
            $page = $writer->addPage(612, 792);
            $cs = $writer->addContentStream($page);
            $cs->beginText()
               ->setFont($fontName, 12)
               ->moveTextPosition(72, 740)
               ->showText(sprintf('Multimedia + 3D page %d of 10', $i))
               ->endText();

            $screen = new ScreenAnnotation(new PdfArray([
                new PdfNumber(72), new PdfNumber(560),
                new PdfNumber(300), new PdfNumber(660),
            ]));
            $screenRef = $writer->register($screen);
            $page->corePage()->annots[] = $screenRef;

            $action = new RenditionAction();
            $action->r = $renditionRef;
            $action->an = $screenRef;
            $actionRef = $writer->register($action);
            $screen->a = $actionRef;

            $threeD = new ThreeDAnnotation(new PdfArray([
                new PdfNumber(72), new PdfNumber(180),
                new PdfNumber(540), new PdfNumber(500),
            ]));
            $threeD->dd = $u3dRef;
            $threeD->di = false;
            $page->corePage()->annots[] = $writer->register($threeD);
        }

        $writer->save($this->tempDir . '/phpdftk_10pages_multimedia_3d.pdf');
    }

    /**
     * 10-page PDF with a SignatureField, SignatureValue placeholder,
     * SignatureReference + DocMDPTransformParams, WidgetAnnotation and
     * AcroForm with /SigFlags. Catalog /Perms carries the DocMDP pointer.
     */
    #[Bench\Subject]
    #[Bench\BeforeMethods('setUp')]
    public function benchPhpdftk10PagesWithSignatureField(): void
    {
        $writer = new PdfWriter();
        $fontName = $writer->addFont(new Type1Font(StandardFont::Helvetica))->getResourceName();

        $docMdp = new DocMDPTransformParams(p: 2);
        $docMdpRef = $writer->register($docMdp);

        $sigRef = new SignatureReference('DocMDP');
        $sigRef->transformParams = $docMdpRef;
        $sigRef->digestMethod = new PdfName('SHA256');
        $sigRefRef = $writer->register($sigRef);

        $sigValue = new SignatureValue();
        $sigValue->name = new PdfString('Benchmark Signer');
        $sigValue->reference = new PdfArray([$sigRefRef]);
        $sigValue->byteRange = new PdfArray([
            new PdfNumber(0), new PdfNumber(0),
            new PdfNumber(0), new PdfNumber(0),
        ]);
        $sigValueRef = $writer->register($sigValue);

        $field = new SignatureField();
        $field->t = new PdfString('Signature1');
        $field->setSignatureValue($sigValueRef);
        $fieldRef = $writer->register($field);

        for ($i = 1; $i <= 10; $i++) {
            $page = $writer->addPage(612, 792);
            $cs = $writer->addContentStream($page);
            $cs->beginText()
               ->setFont($fontName, 12)
               ->moveTextPosition(72, 740)
               ->showText(sprintf('Signature page %d of 10', $i))
               ->endText();
            if ($i === 1) {
                $widget = new WidgetAnnotation(new PdfArray([
                    new PdfNumber(72), new PdfNumber(600),
                    new PdfNumber(320), new PdfNumber(680),
                ]));
                $widget->parent = $fieldRef;
                $page->corePage()->annots[] = $writer->register($widget);
            }
        }

        $acroForm = new AcroForm();
        $acroForm->fields = [$fieldRef];
        $acroForm->sigFlags = 3;
        $writer->getCatalog()->acroForm = $writer->register($acroForm);
        $writer->getCatalog()->perms = new PdfDictionary(['DocMDP' => $sigValueRef]);

        $writer->save($this->tempDir . '/phpdftk_10pages_signature.pdf');
    }

    /**
     * 10-page PDF signed with a self-generated RSA-2048 cert. Exercises
     * the full `PdfWriter::setSigner()` pipeline: placeholder rendering,
     * /ByteRange computation, PKCS#7 signing via openssl, and /Contents
     * patching.
     */
    #[Bench\Subject]
    #[Bench\BeforeMethods('setUp')]
    public function benchPhpdftk10PagesSigned(): void
    {
        if (!extension_loaded('openssl')) {
            return;
        }
        $creds = Pkcs7Signer::createSelfSignedTestCredentials('bench');

        $writer = new PdfWriter();
        $fontName = $writer->addFont(new Type1Font(StandardFont::Helvetica))->getResourceName();

        $sigValue = new SignatureValue();
        $sigValue->name = new PdfString('Bench signer');
        $sigValueRef = $writer->register($sigValue);

        $field = new SignatureField();
        $field->t = new PdfString('Signature1');
        $field->setSignatureValue($sigValueRef);
        $fieldRef = $writer->register($field);

        for ($i = 1; $i <= 10; $i++) {
            $page = $writer->addPage(612, 792);
            $cs = $writer->addContentStream($page);
            $cs->beginText()
               ->setFont($fontName, 12)
               ->moveTextPosition(72, 740)
               ->showText(sprintf('Signed bench page %d of 10', $i))
               ->endText();
            if ($i === 1) {
                $widget = new WidgetAnnotation(new PdfArray([
                    new PdfNumber(72), new PdfNumber(600),
                    new PdfNumber(320), new PdfNumber(680),
                ]));
                $widget->parent = $fieldRef;
                $page->corePage()->annots[] = $writer->register($widget);
            }
        }

        $acroForm = new AcroForm();
        $acroForm->fields = [$fieldRef];
        $acroForm->sigFlags = 3;
        $writer->getCatalog()->acroForm = $writer->register($acroForm);

        $writer->setSigner($sigValue, new Pkcs7Signer($creds['cert'], $creds['key']));
        $writer->save($this->tempDir . '/phpdftk_10pages_signed.pdf');
    }

    /**
     * 10-page PDF with threaded markup annotations per page: Text note
     * + Popup + Highlight reply exercising /T, /Subj, /CreationDate,
     * /IRT, /RT, /Popup.
     */
    #[Bench\Subject]
    #[Bench\BeforeMethods('setUp')]
    public function benchPhpdftk10PagesWithMarkupAnnotations(): void
    {
        $writer = new PdfWriter();
        $fontName = $writer->addFont(new Type1Font(StandardFont::Helvetica))->getResourceName();

        for ($i = 1; $i <= 10; $i++) {
            $page = $writer->addPage(612, 792);
            $cs = $writer->addContentStream($page);
            $cs->beginText()
               ->setFont($fontName, 12)
               ->moveTextPosition(72, 740)
               ->showText(sprintf('Markup page %d of 10', $i))
               ->endText();

            $popup = new \Phpdftk\Pdf\Core\Annotation\PopupAnnotation(new PdfArray([
                new PdfNumber(400), new PdfNumber(600),
                new PdfNumber(540), new PdfNumber(700),
            ]));
            $popupRef = $writer->register($popup);
            $page->corePage()->annots[] = $popupRef;

            $note = new \Phpdftk\Pdf\Core\Annotation\TextAnnotation(new PdfArray([
                new PdfNumber(100), new PdfNumber(690),
                new PdfNumber(120), new PdfNumber(710),
            ]));
            $note->contents = new PdfString(sprintf('Page %d comment', $i));
            $note->t = new PdfString('Alice');
            $note->subj = new PdfString('Review');
            $note->creationDate = new PdfString('D:20260411120000Z');
            $note->popup = $popupRef;
            $noteRef = $writer->register($note);
            $popup->parent = $noteRef;
            $page->corePage()->annots[] = $noteRef;

            $hl = new \Phpdftk\Pdf\Core\Annotation\HighlightAnnotation(
                new PdfArray([new PdfNumber(72), new PdfNumber(500), new PdfNumber(540), new PdfNumber(520)]),
                new PdfArray([
                    new PdfNumber(72), new PdfNumber(520),
                    new PdfNumber(540), new PdfNumber(520),
                    new PdfNumber(540), new PdfNumber(500),
                    new PdfNumber(72), new PdfNumber(500),
                ]),
            );
            $hl->t = new PdfString('Bob');
            $hl->subj = new PdfString('Agreed');
            $hl->irt = $noteRef;
            $hl->rt = new PdfName('R');
            $page->corePage()->annots[] = $writer->register($hl);
        }

        $writer->save($this->tempDir . '/phpdftk_10pages_markup.pdf');
    }

    // -----------------------------------------------------------------------
    // phpdftk — Image and PDF stamping
    // -----------------------------------------------------------------------

    /**
     * 10-page PDF with a JPEG image stamped at center on all pages.
     * Exercises PdfStamper::stampImage() with ImageXObject creation,
     * resource injection, and Do operator rendering.
     */
    #[Bench\Subject]
    #[Bench\BeforeMethods('setUp')]
    public function benchPhpdftk10PagesWithImageStamp(): void
    {
        // Generate source PDF
        $writer = new PdfWriter(compressStreams: false);
        $fontName = $writer->addFont(new Type1Font(StandardFont::Helvetica))->getResourceName();
        for ($i = 1; $i <= 10; $i++) {
            $page = $writer->addPage(612, 792);
            $cs = $writer->addContentStream($page);
            $cs->beginText()
               ->setFont($fontName, 12)
               ->moveTextPosition(72, 720)
               ->showText(sprintf('Image stamp page %d of 10', $i))
               ->endText();
        }
        $pdfBytes = $writer->generate();
        $pdfPath = $this->tempDir . '/bench_source_10pages.pdf';
        file_put_contents($pdfPath, $pdfBytes);

        // Create a test JPEG
        $img = imagecreatetruecolor(100, 100);
        $red = imagecolorallocate($img, 255, 0, 0);
        imagefill($img, 0, 0, $red);
        $imgPath = $this->tempDir . '/bench_stamp.jpg';
        imagejpeg($img, $imgPath, 90);
        imagedestroy($img);

        $style = new \Phpdftk\Pdf\Toolkit\Stamper\ImageStampStyle(width: 80.0, opacity: 0.6);
        \Phpdftk\Pdf\Toolkit\PdfStamper::open($pdfPath)
            ->stampImage($imgPath, \Phpdftk\Pdf\Toolkit\Stamper\StampPosition::BottomRight, style: $style)
            ->save($this->tempDir . '/phpdftk_10pages_image_stamp.pdf');
    }

    /**
     * 10-page PDF with a single-page PDF stamped as a Form XObject overlay.
     * Exercises PdfStamper::stampPdf() with FormXObject import from source PDF,
     * resource embedding, and Do operator.
     */
    #[Bench\Subject]
    #[Bench\BeforeMethods('setUp')]
    public function benchPhpdftk10PagesWithPdfStamp(): void
    {
        // Generate source PDF (target)
        $writer = new PdfWriter(compressStreams: false);
        $fontName = $writer->addFont(new Type1Font(StandardFont::Helvetica))->getResourceName();
        for ($i = 1; $i <= 10; $i++) {
            $page = $writer->addPage(612, 792);
            $cs = $writer->addContentStream($page);
            $cs->beginText()
               ->setFont($fontName, 12)
               ->moveTextPosition(72, 720)
               ->showText(sprintf('PDF stamp target page %d of 10', $i))
               ->endText();
        }
        $targetPath = $this->tempDir . '/bench_target_10pages.pdf';
        $writer->save($targetPath);

        // Generate stamp PDF (source overlay)
        $stampWriter = new PdfWriter(compressStreams: false);
        $stampFont = $stampWriter->addFont(new Type1Font(StandardFont::Helvetica))->getResourceName();
        $stampPage = $stampWriter->addPage(200, 100);
        $stampCs = $stampWriter->addContentStream($stampPage);
        $stampCs->beginText()
            ->setFont($stampFont, 14)
            ->moveTextPosition(10, 40)
            ->showText('STAMP OVERLAY')
            ->endText();
        $stampPath = $this->tempDir . '/bench_stamp_overlay.pdf';
        $stampWriter->save($stampPath);

        $style = new \Phpdftk\Pdf\Toolkit\Stamper\ImageStampStyle(width: 200.0, height: 100.0, opacity: 0.5);
        \Phpdftk\Pdf\Toolkit\PdfStamper::open($targetPath)
            ->stampPdf($stampPath, position: \Phpdftk\Pdf\Toolkit\Stamper\StampPosition::TopRight, style: $style)
            ->save($this->tempDir . '/phpdftk_10pages_pdf_stamp.pdf');
    }

    // -----------------------------------------------------------------------
    // TCPDF benchmarks (if available)
    // -----------------------------------------------------------------------

    #[Bench\Subject]
    #[Bench\BeforeMethods('setUp')]
    public function benchTcpdf1Page(): void
    {
        if (!class_exists(\TCPDF::class)) {
            return;
        }

        $pdf = new \TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
        $pdf->SetCreator('phpdftk benchmark');
        $pdf->SetAuthor('benchmark');
        $pdf->AddPage();
        $pdf->SetFont('helvetica', '', 12);
        $pdf->Cell(0, 10, 'Hello World - TCPDF benchmark 1 page', 0, 1, 'L');
        $pdf->Output($this->tempDir . '/tcpdf_1page.pdf', 'F');
    }

    #[Bench\Subject]
    #[Bench\BeforeMethods('setUp')]
    public function benchTcpdf5Pages(): void
    {
        if (!class_exists(\TCPDF::class)) {
            return;
        }

        $pdf = new \TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
        $pdf->SetCreator('phpdftk benchmark');
        $pdf->SetFont('helvetica', '', 12);

        for ($i = 1; $i <= 5; $i++) {
            $pdf->AddPage();
            $pdf->Cell(0, 10, sprintf('Page %d of 5 - TCPDF benchmark', $i), 0, 1, 'L');
            $pdf->Cell(0, 10, 'The quick brown fox jumps over the lazy dog.', 0, 1, 'L');
        }

        $pdf->Output($this->tempDir . '/tcpdf_5pages.pdf', 'F');
    }

    #[Bench\Subject]
    #[Bench\BeforeMethods('setUp')]
    public function benchTcpdf10Pages(): void
    {
        if (!class_exists(\TCPDF::class)) {
            return;
        }

        $pdf = new \TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
        $pdf->SetCreator('phpdftk benchmark');
        $pdf->SetFont('helvetica', '', 12);

        for ($i = 1; $i <= 10; $i++) {
            $pdf->AddPage();
            $pdf->Cell(0, 10, sprintf('Page %d of 10 - TCPDF benchmark', $i), 0, 1, 'L');
            $pdf->Cell(0, 10, 'The quick brown fox jumps over the lazy dog.', 0, 1, 'L');
        }

        $pdf->Output($this->tempDir . '/tcpdf_10pages.pdf', 'F');
    }

    #[Bench\Subject]
    #[Bench\BeforeMethods('setUp')]
    public function benchTcpdf50Pages(): void
    {
        if (!class_exists(\TCPDF::class)) {
            return;
        }

        $pdf = new \TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
        $pdf->SetCreator('phpdftk benchmark');
        $pdf->SetFont('helvetica', '', 12);

        for ($i = 1; $i <= 50; $i++) {
            $pdf->AddPage();
            $pdf->Cell(0, 10, sprintf('Page %d of 50 - TCPDF benchmark', $i), 0, 1, 'L');
            $pdf->Cell(0, 10, 'The quick brown fox jumps over the lazy dog.', 0, 1, 'L');
        }

        $pdf->Output($this->tempDir . '/tcpdf_50pages.pdf', 'F');
    }

    #[Bench\Subject]
    #[Bench\BeforeMethods('setUp')]
    public function benchTcpdf100Pages(): void
    {
        if (!class_exists(\TCPDF::class)) {
            return;
        }

        $pdf = new \TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
        $pdf->SetCreator('phpdftk benchmark');
        $pdf->SetFont('helvetica', '', 12);

        for ($i = 1; $i <= 100; $i++) {
            $pdf->AddPage();
            $pdf->Cell(0, 10, sprintf('Page %d of 100 - TCPDF benchmark', $i), 0, 1, 'L');
            $pdf->Cell(0, 10, 'The quick brown fox jumps over the lazy dog.', 0, 1, 'L');
        }

        $pdf->Output($this->tempDir . '/tcpdf_100pages.pdf', 'F');
    }

    // -----------------------------------------------------------------------
    // FPDF benchmarks (if available)
    // -----------------------------------------------------------------------

    #[Bench\Subject]
    #[Bench\BeforeMethods('setUp')]
    public function benchFpdf1Page(): void
    {
        if (!class_exists(\FPDF::class)) {
            return;
        }

        $pdf = new \FPDF();
        $pdf->AddPage();
        $pdf->SetFont('Helvetica', '', 12);
        $pdf->Cell(0, 10, 'Hello World - FPDF benchmark 1 page');
        $pdf->Output('F', $this->tempDir . '/fpdf_1page.pdf');
    }

    #[Bench\Subject]
    #[Bench\BeforeMethods('setUp')]
    public function benchFpdf5Pages(): void
    {
        if (!class_exists(\FPDF::class)) {
            return;
        }

        $pdf = new \FPDF();
        $pdf->SetFont('Helvetica', '', 12);

        for ($i = 1; $i <= 5; $i++) {
            $pdf->AddPage();
            $pdf->Cell(0, 10, sprintf('Page %d of 5 - FPDF benchmark', $i));
            $pdf->Ln();
            $pdf->Cell(0, 10, 'The quick brown fox jumps over the lazy dog.');
        }

        $pdf->Output('F', $this->tempDir . '/fpdf_5pages.pdf');
    }

    #[Bench\Subject]
    #[Bench\BeforeMethods('setUp')]
    public function benchFpdf10Pages(): void
    {
        if (!class_exists(\FPDF::class)) {
            return;
        }

        $pdf = new \FPDF();
        $pdf->SetFont('Helvetica', '', 12);

        for ($i = 1; $i <= 10; $i++) {
            $pdf->AddPage();
            $pdf->Cell(0, 10, sprintf('Page %d of 10 - FPDF benchmark', $i));
            $pdf->Ln();
            $pdf->Cell(0, 10, 'The quick brown fox jumps over the lazy dog.');
        }

        $pdf->Output('F', $this->tempDir . '/fpdf_10pages.pdf');
    }

    #[Bench\Subject]
    #[Bench\BeforeMethods('setUp')]
    public function benchFpdf50Pages(): void
    {
        if (!class_exists(\FPDF::class)) {
            return;
        }

        $pdf = new \FPDF();
        $pdf->SetFont('Helvetica', '', 12);

        for ($i = 1; $i <= 50; $i++) {
            $pdf->AddPage();
            $pdf->Cell(0, 10, sprintf('Page %d of 50 - FPDF benchmark', $i));
            $pdf->Ln();
            $pdf->Cell(0, 10, 'The quick brown fox jumps over the lazy dog.');
        }

        $pdf->Output('F', $this->tempDir . '/fpdf_50pages.pdf');
    }

    #[Bench\Subject]
    #[Bench\BeforeMethods('setUp')]
    public function benchFpdf100Pages(): void
    {
        if (!class_exists(\FPDF::class)) {
            return;
        }

        $pdf = new \FPDF();
        $pdf->SetFont('Helvetica', '', 12);

        for ($i = 1; $i <= 100; $i++) {
            $pdf->AddPage();
            $pdf->Cell(0, 10, sprintf('Page %d of 100 - FPDF benchmark', $i));
            $pdf->Ln();
            $pdf->Cell(0, 10, 'The quick brown fox jumps over the lazy dog.');
        }

        $pdf->Output('F', $this->tempDir . '/fpdf_100pages.pdf');
    }

    // -----------------------------------------------------------------------
    // mPDF benchmarks (if available)
    // -----------------------------------------------------------------------

    #[Bench\Subject]
    #[Bench\BeforeMethods('setUp')]
    public function benchMpdf1Page(): void
    {
        if (!class_exists(\Mpdf\Mpdf::class)) {
            return;
        }

        $mpdf = new \Mpdf\Mpdf(['tempDir' => $this->tempDir]);
        $mpdf->WriteHTML('<p>Hello World - mPDF benchmark 1 page</p>');
        $mpdf->Output($this->tempDir . '/mpdf_1page.pdf', \Mpdf\Output\Destination::FILE);
    }

    #[Bench\Subject]
    #[Bench\BeforeMethods('setUp')]
    public function benchMpdf5Pages(): void
    {
        if (!class_exists(\Mpdf\Mpdf::class)) {
            return;
        }

        $mpdf = new \Mpdf\Mpdf(['tempDir' => $this->tempDir]);
        $html = '';
        for ($i = 1; $i <= 5; $i++) {
            $html .= sprintf('<p>Page %d of 5 - mPDF benchmark</p>', $i);
            $html .= '<p>The quick brown fox jumps over the lazy dog.</p>';
            if ($i < 5) {
                $html .= '<pagebreak/>';
            }
        }
        $mpdf->WriteHTML($html);
        $mpdf->Output($this->tempDir . '/mpdf_5pages.pdf', \Mpdf\Output\Destination::FILE);
    }

    #[Bench\Subject]
    #[Bench\BeforeMethods('setUp')]
    public function benchMpdf10Pages(): void
    {
        if (!class_exists(\Mpdf\Mpdf::class)) {
            return;
        }

        $mpdf = new \Mpdf\Mpdf(['tempDir' => $this->tempDir]);
        $html = '';
        for ($i = 1; $i <= 10; $i++) {
            $html .= sprintf('<p>Page %d of 10 - mPDF benchmark</p>', $i);
            $html .= '<p>The quick brown fox jumps over the lazy dog.</p>';
            if ($i < 10) {
                $html .= '<pagebreak/>';
            }
        }
        $mpdf->WriteHTML($html);
        $mpdf->Output($this->tempDir . '/mpdf_10pages.pdf', \Mpdf\Output\Destination::FILE);
    }

    #[Bench\Subject]
    #[Bench\BeforeMethods('setUp')]
    public function benchMpdf50Pages(): void
    {
        if (!class_exists(\Mpdf\Mpdf::class)) {
            return;
        }

        $mpdf = new \Mpdf\Mpdf(['tempDir' => $this->tempDir]);
        $html = '';
        for ($i = 1; $i <= 50; $i++) {
            $html .= sprintf('<p>Page %d of 50 - mPDF benchmark</p>', $i);
            $html .= '<p>The quick brown fox jumps over the lazy dog.</p>';
            if ($i < 50) {
                $html .= '<pagebreak/>';
            }
        }
        $mpdf->WriteHTML($html);
        $mpdf->Output($this->tempDir . '/mpdf_50pages.pdf', \Mpdf\Output\Destination::FILE);
    }

    #[Bench\Subject]
    #[Bench\BeforeMethods('setUp')]
    public function benchMpdf100Pages(): void
    {
        if (!class_exists(\Mpdf\Mpdf::class)) {
            return;
        }

        $mpdf = new \Mpdf\Mpdf(['tempDir' => $this->tempDir]);
        $html = '';
        for ($i = 1; $i <= 100; $i++) {
            $html .= sprintf('<p>Page %d of 100 - mPDF benchmark</p>', $i);
            $html .= '<p>The quick brown fox jumps over the lazy dog.</p>';
            if ($i < 100) {
                $html .= '<pagebreak/>';
            }
        }
        $mpdf->WriteHTML($html);
        $mpdf->Output($this->tempDir . '/mpdf_100pages.pdf', \Mpdf\Output\Destination::FILE);
    }

    // -----------------------------------------------------------------------
    // Dompdf benchmarks (if available)
    // -----------------------------------------------------------------------

    #[Bench\Subject]
    #[Bench\BeforeMethods('setUp')]
    public function benchDompdf1Page(): void
    {
        if (!class_exists(\Dompdf\Dompdf::class)) {
            return;
        }

        $dompdf = new \Dompdf\Dompdf();
        $dompdf->loadHtml('<p>Hello World - Dompdf benchmark 1 page</p>');
        $dompdf->setPaper('letter', 'portrait');
        $dompdf->render();
        file_put_contents($this->tempDir . '/dompdf_1page.pdf', $dompdf->output());
    }

    #[Bench\Subject]
    #[Bench\BeforeMethods('setUp')]
    public function benchDompdf5Pages(): void
    {
        if (!class_exists(\Dompdf\Dompdf::class)) {
            return;
        }

        $html = '';
        for ($i = 1; $i <= 5; $i++) {
            $html .= sprintf('<p>Page %d of 5 - Dompdf benchmark</p>', $i);
            $html .= '<p>The quick brown fox jumps over the lazy dog.</p>';
            if ($i < 5) {
                $html .= '<div style="page-break-after: always;"></div>';
            }
        }

        $dompdf = new \Dompdf\Dompdf();
        $dompdf->loadHtml($html);
        $dompdf->setPaper('letter', 'portrait');
        $dompdf->render();
        file_put_contents($this->tempDir . '/dompdf_5pages.pdf', $dompdf->output());
    }

    #[Bench\Subject]
    #[Bench\BeforeMethods('setUp')]
    public function benchDompdf10Pages(): void
    {
        if (!class_exists(\Dompdf\Dompdf::class)) {
            return;
        }

        $html = '';
        for ($i = 1; $i <= 10; $i++) {
            $html .= sprintf('<p>Page %d of 10 - Dompdf benchmark</p>', $i);
            $html .= '<p>The quick brown fox jumps over the lazy dog.</p>';
            if ($i < 10) {
                $html .= '<div style="page-break-after: always;"></div>';
            }
        }

        $dompdf = new \Dompdf\Dompdf();
        $dompdf->loadHtml($html);
        $dompdf->setPaper('letter', 'portrait');
        $dompdf->render();
        file_put_contents($this->tempDir . '/dompdf_10pages.pdf', $dompdf->output());
    }

    #[Bench\Subject]
    #[Bench\BeforeMethods('setUp')]
    public function benchDompdf50Pages(): void
    {
        if (!class_exists(\Dompdf\Dompdf::class)) {
            return;
        }

        $html = '';
        for ($i = 1; $i <= 50; $i++) {
            $html .= sprintf('<p>Page %d of 50 - Dompdf benchmark</p>', $i);
            $html .= '<p>The quick brown fox jumps over the lazy dog.</p>';
            if ($i < 50) {
                $html .= '<div style="page-break-after: always;"></div>';
            }
        }

        $dompdf = new \Dompdf\Dompdf();
        $dompdf->loadHtml($html);
        $dompdf->setPaper('letter', 'portrait');
        $dompdf->render();
        file_put_contents($this->tempDir . '/dompdf_50pages.pdf', $dompdf->output());
    }

    #[Bench\Subject]
    #[Bench\BeforeMethods('setUp')]
    public function benchDompdf100Pages(): void
    {
        if (!class_exists(\Dompdf\Dompdf::class)) {
            return;
        }

        $html = '';
        for ($i = 1; $i <= 100; $i++) {
            $html .= sprintf('<p>Page %d of 100 - Dompdf benchmark</p>', $i);
            $html .= '<p>The quick brown fox jumps over the lazy dog.</p>';
            if ($i < 100) {
                $html .= '<div style="page-break-after: always;"></div>';
            }
        }

        $dompdf = new \Dompdf\Dompdf();
        $dompdf->loadHtml($html);
        $dompdf->setPaper('letter', 'portrait');
        $dompdf->render();
        file_put_contents($this->tempDir . '/dompdf_100pages.pdf', $dompdf->output());
    }

    // -----------------------------------------------------------------------
    // phpdftk — Form appearances
    // -----------------------------------------------------------------------

    #[Bench\Subject]
    #[Bench\BeforeMethods('setUp')]
    public function benchPhpdftk10PagesWithFormAppearances(): void
    {
        $writer = new PdfWriter();

        for ($i = 1; $i <= 10; $i++) {
            $page = $writer->addPage(612, 792);
            $fontName = $writer->addFont(new Type1Font(StandardFont::Helvetica))->getResourceName();

            $cs = $writer->addContentStream($page);
            $cs->beginText()->setFont($fontName, 12)->moveTextPosition(72, 720)
               ->showText("Form page $i")->endText();

            // Generate text field appearance
            $rect = new PdfArray([new PdfNumber(72), new PdfNumber(680), new PdfNumber(300), new PdfNumber(700)]);
            $xObj = \Phpdftk\Pdf\Core\Interactive\Form\AppearanceGenerator::textField($rect, $fontName, 12, "Value $i");
            $writer->register($xObj);

            // Generate checkbox appearance
            $checkRect = new PdfArray([new PdfNumber(72), new PdfNumber(650), new PdfNumber(90), new PdfNumber(668)]);
            $checkStates = \Phpdftk\Pdf\Core\Interactive\Form\AppearanceGenerator::checkbox($checkRect);
            $writer->register($checkStates['on']);
            $writer->register($checkStates['off']);
        }

        $writer->save($this->tempDir . '/phpdftk_form_appearances.pdf');
    }

    // -----------------------------------------------------------------------
    // phpdftk — Form appearances with custom (embedded TrueType) font
    // -----------------------------------------------------------------------

    #[Bench\Subject]
    #[Bench\BeforeMethods('setUp')]
    public function benchPhpdftk10PagesWithCustomFontFormAppearances(): void
    {
        $fontPath = null;
        foreach ([
            '/System/Library/Fonts/Supplemental/Arial.ttf',
            '/System/Library/Fonts/Supplemental/Georgia.ttf',
            '/System/Library/Fonts/Supplemental/Verdana.ttf',
            '/usr/share/fonts/truetype/liberation/LiberationSans-Regular.ttf',
            '/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf',
        ] as $path) {
            if (file_exists($path)) {
                $fontPath = $path;
                break;
            }
        }
        if ($fontPath === null) {
            return;
        }

        $ttData = (new \Phpdftk\FontParser\TrueTypeParser($fontPath))->parse();
        $text = 'Form value on page 0123456789';
        $codepoints = array_unique(array_map('mb_ord', mb_str_split($text)));

        $writer = new PdfWriter();

        for ($i = 1; $i <= 10; $i++) {
            $page = $writer->addPage(612, 792);
            $compositeFont = $writer->addCompositeFont($ttData, $codepoints, $page);
            $fontName = $compositeFont->getResourceName();
            $fontRef = $page->corePage()->resources->font[$fontName];

            $fontCtx = new \Phpdftk\Pdf\Core\Interactive\Form\FontContext(
                fontRef: $fontRef,
                unicodeToGid: $ttData->fullUnicodeToGid,
            );

            // Text field with custom font appearance
            $rect = new PdfArray([new PdfNumber(72), new PdfNumber(680), new PdfNumber(300), new PdfNumber(700)]);
            $xObj = \Phpdftk\Pdf\Core\Interactive\Form\AppearanceGenerator::textField(
                $rect,
                $fontName,
                12,
                "Value $i",
                fontContext: $fontCtx,
            );
            $writer->register($xObj);

            // Choice field with custom font appearance
            $choiceRect = new PdfArray([new PdfNumber(72), new PdfNumber(640), new PdfNumber(300), new PdfNumber(660)]);
            $choiceXObj = \Phpdftk\Pdf\Core\Interactive\Form\AppearanceGenerator::choiceField(
                $choiceRect,
                $fontName,
                12,
                "Option $i",
                fontContext: $fontCtx,
            );
            $writer->register($choiceXObj);
        }

        $writer->save($this->tempDir . '/phpdftk_custom_font_form_appearances.pdf');
    }

    // -----------------------------------------------------------------------
    // phpdftk — OpenType CFF font
    // -----------------------------------------------------------------------

    #[Bench\Subject]
    #[Bench\BeforeMethods('setUp')]
    public function benchPhpdftk10PagesWithOpenTypeCff(): void
    {
        $fontPath = '/System/Library/Fonts/Supplemental/STIXGeneral.otf';
        if (!file_exists($fontPath) || substr(file_get_contents($fontPath), 0, 4) !== 'OTTO') {
            return; // Skip if no OTF font available
        }

        $data = (new \Phpdftk\FontParser\OpenTypeParser($fontPath))->parse();
        $text = 'The quick brown fox jumps over the lazy dog. 0123456789';
        $codepoints = array_unique(array_map('mb_ord', mb_str_split($text)));

        $writer = new PdfWriter();
        for ($i = 1; $i <= 10; $i++) {
            $page = $writer->addPage(612, 792);
            $fontName = $writer->addOpenTypeFont($data, $codepoints, $page)->getResourceName();

            $cs = $writer->addContentStream($page);
            $cs->beginText()->setFont($fontName, 14)->moveTextPosition(72, 720);

            $hexGids = '';
            foreach (mb_str_split($text) as $char) {
                $gid = $data->fullUnicodeToGid[mb_ord($char)] ?? 0;
                $hexGids .= sprintf('%04X', $gid);
            }
            $cs->showTextHex($hexGids);
            $cs->endText();
        }

        $writer->save($this->tempDir . '/phpdftk_opentype_cff.pdf');
    }

    #[Bench\Subject]
    #[Bench\BeforeMethods('setUp')]
    public function benchPhpdftk10PagesWithCffSubsetting(): void
    {
        $fontPath = '/System/Library/Fonts/Supplemental/STIXGeneral.otf';
        if (!file_exists($fontPath) || substr(file_get_contents($fontPath), 0, 4) !== 'OTTO') {
            return;
        }

        $data = (new \Phpdftk\FontParser\OpenTypeParser($fontPath))->parse();
        // Use a small character set to demonstrate subsetting benefit
        $text = 'Hello World';
        $codepoints = array_unique(array_map('mb_ord', mb_str_split($text)));

        $writer = new PdfWriter();
        for ($i = 1; $i <= 10; $i++) {
            $page = $writer->addPage(612, 792);
            $fontName = $writer->addOpenTypeFont($data, $codepoints, $page)->getResourceName();

            $cs = $writer->addContentStream($page);
            $cs->beginText()->setFont($fontName, 14)->moveTextPosition(72, 720)
                ->showUnicodeText($text, $data->fullUnicodeToGid)
                ->endText();
        }

        $writer->save($this->tempDir . '/phpdftk_cff_subsetted.pdf');
    }

    #[Bench\Subject]
    #[Bench\BeforeMethods('setUp')]
    public function benchPhpdftk10PagesWithKernedText(): void
    {
        $fontPath = '/System/Library/Fonts/Supplemental/STIXGeneral.otf';
        if (!file_exists($fontPath) || substr(file_get_contents($fontPath), 0, 4) !== 'OTTO') {
            return;
        }

        $data = (new \Phpdftk\FontParser\OpenTypeParser($fontPath))->parse();
        $text = 'AV To WA Typography WAVE';
        $codepoints = array_unique(array_map('mb_ord', mb_str_split($text)));

        $writer = new PdfWriter();
        for ($i = 1; $i <= 10; $i++) {
            $page = $writer->addPage(612, 792);
            $fontName = $writer->addOpenTypeFont($data, $codepoints, $page)->getResourceName();

            $cs = $writer->addContentStream($page);
            $cs->beginText()->setFont($fontName, 14)->moveTextPosition(72, 720);

            if ($data->kernPairs !== null) {
                $cs->showUnicodeTextKerned($text, $data->fullUnicodeToGid, $data->kernPairs, $data->unitsPerEm);
            } else {
                $cs->showUnicodeText($text, $data->fullUnicodeToGid);
            }

            $cs->endText();
        }

        $writer->save($this->tempDir . '/phpdftk_kerned_text.pdf');
    }

    #[Bench\Subject]
    #[Bench\BeforeMethods('setUp')]
    public function benchPhpdftk10PagesWithPublicKeyEncryption(): void
    {
        // Generate a test certificate
        $config = [
            'digest_alg' => 'sha256',
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ];
        $key = openssl_pkey_new($config);
        $csr = openssl_csr_new(['commonName' => 'bench-test'], $key, $config);
        $cert = openssl_csr_sign($csr, null, $key, 365, $config);
        openssl_x509_export($cert, $certPem);

        $fileWriter = new \Phpdftk\Pdf\Core\File\PdfFileWriter(compressStreams: false);
        $catalog = new \Phpdftk\Pdf\Core\Document\Catalog();
        $fileWriter->setCatalog($catalog);
        $pageTree = new \Phpdftk\Pdf\Core\Document\PageTree();
        $fileWriter->register($pageTree);
        $catalog->pages = new \Phpdftk\Pdf\Core\PdfReference($pageTree->objectNumber);

        $kids = [];
        for ($i = 1; $i <= 10; $i++) {
            $page = new \Phpdftk\Pdf\Core\Document\Page();
            $fileWriter->register($page);
            $page->parent = new \Phpdftk\Pdf\Core\PdfReference($pageTree->objectNumber);
            $page->mediaBox = new \Phpdftk\Pdf\Core\PdfArray([
                new \Phpdftk\Pdf\Core\PdfNumber(0), new \Phpdftk\Pdf\Core\PdfNumber(0),
                new \Phpdftk\Pdf\Core\PdfNumber(612), new \Phpdftk\Pdf\Core\PdfNumber(792),
            ]);
            $page->resources = new \Phpdftk\Pdf\Core\Content\Resources();

            $cs = new \Phpdftk\Pdf\Core\Content\ContentStream();
            $fileWriter->register($cs);
            $cs->beginText()
                ->setFont('F1', 12)
                ->moveTextPosition(72, 720)
                ->showText("Public-key encrypted page $i")
                ->endText();
            $page->contents = [new \Phpdftk\Pdf\Core\PdfReference($cs->objectNumber)];
            $kids[] = new \Phpdftk\Pdf\Core\PdfReference($page->objectNumber);
        }
        $pageTree->kids = $kids;
        $pageTree->count = 10;

        $fileId = md5('bench-pubkey', true);
        $encryptor = \Phpdftk\Pdf\Core\Security\PdfEncryptor::publicKeyAes128(
            [['cert' => $certPem]],
            $fileId,
        );
        $fileWriter->setEncryption($encryptor);

        $path = $this->tempDir . '/phpdftk_public_key_encrypted.pdf';
        file_put_contents($path, $fileWriter->generate());
    }

    /**
     * Benchmark TSA request building and response parsing (no network).
     *
     * Exercises the full ASN.1 DER encoding of an RFC 3161
     * TimeStampReq and parsing of a synthetic TimeStampResp.
     */
    #[Bench\Subject]
    #[Bench\BeforeMethods('setUp')]
    public function benchPhpdftkTsaRequestBuildAndParse(): void
    {
        $client = new \Phpdftk\Pdf\Core\Interactive\Signature\TsaClient('http://example.com/tsa');

        // Build 100 timestamp requests (exercises DER encoding)
        for ($i = 0; $i < 100; $i++) {
            $hash = hash('sha256', "data-$i", binary: true);
            $req = $client->buildTimeStampReq($hash);
            assert(ord($req[0]) === 0x30); // valid SEQUENCE
        }

        // Parse 100 synthetic responses (exercises DER parsing)
        // Build a fake TimeStampResp with granted status + token
        $fakeOid = "\x06\x09\x2A\x86\x48\x86\xF7\x0D\x01\x07\x02"; // id-signedData
        $fakeContent = "\xA0\x05\x30\x03\x02\x01\x03"; // [0] EXPLICIT SEQUENCE { INTEGER 3 }
        $fakeToken = "\x30" . chr(strlen($fakeOid . $fakeContent)) . $fakeOid . $fakeContent;
        $statusInfo = "\x30\x03\x02\x01\x00"; // SEQUENCE { INTEGER 0 }
        $resp = "\x30" . chr(strlen($statusInfo . $fakeToken)) . $statusInfo . $fakeToken;

        for ($i = 0; $i < 100; $i++) {
            $token = $client->parseTimeStampResp($resp);
            assert(strlen($token) > 0);
        }
    }

    /**
     * Measure version-gating overhead: 10 pages with annotations that
     * trigger version checks on every register() call.
     */
    #[Bench\Revs(5)]
    #[Bench\Iterations(3)]
    public function benchPhpdftk10PagesWithVersionGating(): void
    {
        $writer = new \Phpdftk\Pdf\Writer\PdfWriter(version: \Phpdftk\Pdf\Core\PdfVersion::V1_4);

        for ($i = 0; $i < 10; $i++) {
            $page = $writer->addPage(612, 792);
            $font = $writer->addFont(new Type1Font(StandardFont::Helvetica));
            $cs = $writer->addContentStream($page->corePage());
            $cs->beginText()
                ->setFont($font->getResourceName(), 12)
                ->moveTextPosition(72, 720)
                ->showText("Page " . ($i + 1))
                ->endText();

            // Register version-sensitive objects that trigger checks
            $highlight = new HighlightAnnotation(
                new PdfArray([new PdfNumber(72), new PdfNumber(700), new PdfNumber(200), new PdfNumber(720)]),
                new PdfArray([new PdfNumber(72), new PdfNumber(720), new PdfNumber(200), new PdfNumber(720),
                    new PdfNumber(200), new PdfNumber(700), new PdfNumber(72), new PdfNumber(700)]),
            );
            $writer->register($highlight);

            $line = new LineAnnotation(
                new PdfArray([new PdfNumber(72), new PdfNumber(680), new PdfNumber(200), new PdfNumber(700)]),
                new PdfArray([new PdfNumber(72), new PdfNumber(690), new PdfNumber(200), new PdfNumber(690)]),
            );
            $writer->register($line);
        }

        $bytes = $writer->generate();
        assert(str_starts_with($bytes, '%PDF-'));
    }

    // -----------------------------------------------------------------------
    // phpdftk — linearized PDF writing
    // -----------------------------------------------------------------------

    /**
     * 10-page linearized PDF (web-optimized, two-pass write with hint stream).
     */
    #[Bench\Subject]
    #[Bench\BeforeMethods('setUp')]
    public function benchPhpdftk10PagesLinearized(): void
    {
        $writer = new PdfWriter();
        $writer->setLinearized();
        $fontName = $writer->addFont(new Type1Font(StandardFont::Helvetica))->getResourceName();

        for ($i = 1; $i <= 10; $i++) {
            $page = $writer->addPage(612, 792);
            $cs = $writer->addContentStream($page);
            $cs->beginText()
               ->setFont($fontName, 12)
               ->moveTextPosition(72, 720)
               ->showText(sprintf('Linearized page %d of 10', $i))
               ->moveTextPosition(0, -20)
               ->showText('The quick brown fox jumps over the lazy dog.')
               ->endText();
        }

        $writer->save($this->tempDir . '/phpdftk_10pages_linearized.pdf');
    }

    // -----------------------------------------------------------------------
    // phpdftk — Type 1 font parsing
    // -----------------------------------------------------------------------

    /**
     * Parse a synthetic Type 1 PFB font (exercises PFB segment parsing,
     * ASCII header extraction, and encoding vector parsing).
     */
    #[Bench\Subject]
    #[Bench\BeforeMethods('setUp')]
    #[Bench\Revs(10)]
    #[Bench\Iterations(5)]
    public function benchPhpdftkType1FontParsing(): void
    {
        // Build a minimal synthetic PFB in memory
        $ascii = implode("\n", [
            '%!PS-AdobeFont-1.0: BenchFont 001.000',
            '/FontName /BenchFont def',
            '/FullName (Bench Font) def',
            '/FamilyName (Bench) def',
            '/Weight (Medium) def',
            '/ItalicAngle 0 def',
            '/isFixedPitch false def',
            '/FontBBox {-100 -200 1000 800} def',
            '/UnderlinePosition -100 def',
            '/UnderlineThickness 50 def',
            '/Encoding StandardEncoding def',
            'currentdict end',
            'currentfile eexec',
        ]);
        $binary = str_repeat("\x00", 100);
        $trailer = "0000000000000000000000000000000000000000000000000000000000000000\n0000000000000000000000000000000000000000000000000000000000000000\ncleartomark\n";

        // PFB segments: type 1 (ASCII), type 2 (binary), type 1 (trailer)
        $pfb = "\x80\x01" . pack('V', strlen($ascii)) . $ascii;
        $pfb .= "\x80\x02" . pack('V', strlen($binary)) . $binary;
        $pfb .= "\x80\x01" . pack('V', strlen($trailer)) . $trailer;
        $pfb .= "\x80\x03";

        for ($i = 0; $i < 100; $i++) {
            $parser = \Phpdftk\FontParser\Type1Parser::fromBytes($pfb);
            $data = $parser->parse();
            assert($data->familyName === 'Bench Font');
        }
    }

    // -----------------------------------------------------------------------
    // phpdftk — CCITTFax decoding
    // -----------------------------------------------------------------------

    /**
     * Decode CCITTFax Group 3 encoded rows (exercises Huffman table lookup
     * and run-length decoding).
     */
    #[Bench\Subject]
    #[Bench\BeforeMethods('setUp')]
    #[Bench\Revs(10)]
    #[Bench\Iterations(5)]
    public function benchPhpdftkCCITTFaxDecode(): void
    {
        // Build 100 rows of alternating white/black runs (8 pixels wide)
        // White run of 4: '1011', Black run of 4: '011'
        $rowBits = '1011011';
        $allBits = str_repeat($rowBits, 100);
        $padded = str_pad($allBits, (int) (ceil(strlen($allBits) / 8) * 8), '0');
        $data = '';
        for ($i = 0; $i < strlen($padded); $i += 8) {
            $data .= chr((int) bindec(substr($padded, $i, 8)));
        }

        $filter = new \Phpdftk\Filters\CCITTFaxFilter(
            k: 0,
            columns: 8,
            rows: 100,
            endOfBlock: false,
        );

        for ($i = 0; $i < 100; $i++) {
            $result = $filter->decode($data);
            assert(strlen($result) === 100); // 100 rows × 1 byte each
        }
    }

    // -----------------------------------------------------------------------
    // phpdftk — CCITTFax encoding
    // -----------------------------------------------------------------------

    /**
     * Encode raw pixel rows to CCITTFax Group 3 (exercises reverse Huffman
     * lookup and run-length encoding).
     */
    #[Bench\Subject]
    #[Bench\BeforeMethods('setUp')]
    #[Bench\Revs(10)]
    #[Bench\Iterations(5)]
    public function benchPhpdftkCCITTFaxEncode(): void
    {
        // 100 rows of 8-pixel alternating data (same pattern as decode bench)
        $rawRow = chr(0xF0); // 4 white + 4 black
        $rawData = str_repeat($rawRow, 100);

        $filter = new \Phpdftk\Filters\CCITTFaxFilter(
            k: 0,
            columns: 8,
            rows: 100,
            endOfBlock: false,
        );

        for ($i = 0; $i < 100; $i++) {
            $result = $filter->encode($rawData);
            assert(strlen($result) > 0);
        }
    }

    // -----------------------------------------------------------------------
    // phpdftk — JBIG2 encoding
    // -----------------------------------------------------------------------

    /**
     * Encode raw pixel bitmap to JBIG2 MMR generic region (exercises segment
     * building and Group 4 encoding).
     */
    #[Bench\Subject]
    #[Bench\BeforeMethods('setUp')]
    #[Bench\Revs(10)]
    #[Bench\Iterations(5)]
    public function benchPhpdftkJbig2Encode(): void
    {
        $rawRow = chr(0xF0);
        $rawData = str_repeat($rawRow, 100);

        $filter = new \Phpdftk\Filters\Jbig2Filter(width: 8, height: 100);

        for ($i = 0; $i < 100; $i++) {
            $result = $filter->encode($rawData);
            assert(strlen($result) > 0);
        }
    }

    /**
     * 10-page signed PDF + LTV data (DSS with certificates, dummy OCSP/CRL,
     * VRI entry) via LtvSigner incremental update.
     */
    #[Bench\Subject]
    #[Bench\BeforeMethods('setUp')]
    public function benchPhpdftk10PagesWithLtvSignature(): void
    {
        if (!extension_loaded('openssl')) {
            return;
        }
        $creds = Pkcs7Signer::createSelfSignedTestCredentials('bench-ltv');

        $writer = new PdfWriter();
        $fontName = $writer->addFont(new Type1Font(StandardFont::Helvetica))->getResourceName();

        $sigValue = new SignatureValue();
        $sigValue->name = new PdfString('Bench LTV signer');
        $sigValueRef = $writer->register($sigValue);

        $field = new SignatureField();
        $field->t = new PdfString('Signature1');
        $field->setSignatureValue($sigValueRef);
        $fieldRef = $writer->register($field);

        for ($i = 1; $i <= 10; $i++) {
            $page = $writer->addPage(612, 792);
            $cs = $writer->addContentStream($page);
            $cs->beginText()
               ->setFont($fontName, 12)
               ->moveTextPosition(72, 740)
               ->showText(sprintf('LTV bench page %d of 10', $i))
               ->endText();
            if ($i === 1) {
                $widget = new WidgetAnnotation(new PdfArray([
                    new PdfNumber(72), new PdfNumber(600),
                    new PdfNumber(320), new PdfNumber(680),
                ]));
                $widget->parent = $fieldRef;
                $page->corePage()->annots[] = $writer->register($widget);
            }
        }

        $acroForm = new AcroForm();
        $acroForm->fields = [$fieldRef];
        $acroForm->sigFlags = 3;
        $writer->getCatalog()->acroForm = $writer->register($acroForm);

        $writer->setSigner($sigValue, new Pkcs7Signer($creds['cert'], $creds['key']));
        $signedPdf = $writer->generate();

        // Add LTV data: certificate chain + dummy OCSP/CRL
        $certDer = CertificateUtils::pemToDer($creds['cert']);
        LtvSigner::openString($signedPdf)
            ->addCertificate($certDer)
            ->addOcspResponse(random_bytes(256))
            ->addCrl(random_bytes(512))
            ->save($this->tempDir . '/phpdftk_10pages_ltv.pdf');
    }

    /**
     * 10-page PDF with PDF/A-1b conformance validation.
     *
     * Exercises the full conformance pipeline: setConformance(), auto XMP
     * injection, OutputIntent with ICC profile, embedded TrueType font,
     * and generate-time constraint validation.
     */
    #[Bench\Subject]
    #[Bench\BeforeMethods('setUp')]
    public function benchPhpdftk10PagesWithPdfAConformance(): void
    {
        $fontPath = null;
        foreach ([
            '/System/Library/Fonts/Supplemental/Arial.ttf',
            '/System/Library/Fonts/Supplemental/Georgia.ttf',
            '/usr/share/fonts/truetype/liberation/LiberationSans-Regular.ttf',
            '/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf',
        ] as $path) {
            if (file_exists($path)) {
                $fontPath = $path;
                break;
            }
        }
        if ($fontPath === null) {
            return;
        }

        $iccPath = null;
        foreach ([
            '/System/Library/ColorSync/Profiles/sRGB Profile.icc',
            '/usr/share/color/icc/colord/sRGB.icc',
            '/usr/share/color/icc/sRGB.icc',
        ] as $path) {
            if (file_exists($path)) {
                $iccPath = $path;
                break;
            }
        }
        if ($iccPath === null) {
            return;
        }

        $writer = new PdfWriter();
        $writer->setConformance(PdfAProfile::A1b);

        $info = new \Phpdftk\Pdf\Core\Document\Info();
        $info->title = new PdfString('PDF/A Benchmark');
        $info->producer = new PdfString('phpdftk');
        $writer->setInfo($info);

        // OutputIntent with ICC profile
        $iccData = file_get_contents($iccPath);
        $iccStream = new \Phpdftk\Pdf\Core\PdfStream(new PdfDictionary(), $iccData);
        $iccStream->dictionary->set('N', new PdfNumber(3));
        $iccRef = $writer->register($iccStream);

        $outputIntent = new OutputIntent('GTS_PDFA1', 'sRGB IEC61966-2.1');
        $outputIntent->registryName = new PdfString('http://www.color.org');
        $outputIntent->info = new PdfString('sRGB IEC61966-2.1');
        $outputIntent->destOutputProfile = $iccRef;
        $oiRef = $writer->register($outputIntent);
        $writer->getCatalog()->outputIntents = new PdfArray([$oiRef]);

        // Embedded TrueType font
        $font = TrueTypeFont::fromFile($fontPath);
        $fontName = $writer->addFont($font)->getResourceName();

        for ($i = 1; $i <= 10; $i++) {
            $page = $writer->addPage(612, 792);
            $cs = $writer->addContentStream($page);
            $cs->beginText()
               ->setFont($fontName, 12)
               ->moveTextPosition(72, 720)
               ->showText(sprintf('PDF/A-1b conformance bench page %d of 10', $i))
               ->endText();
        }

        $writer->save($this->tempDir . '/phpdftk_10pages_pdfa.pdf');
    }

    /**
     * 10-page PDF with PDF/UA-1 conformance validation.
     *
     * Exercises the full PDF/UA pipeline: tagged structure (MarkInfo,
     * StructTreeRoot, Lang), ViewerPreferences DisplayDocTitle, /Tabs /S
     * on pages, embedded TrueType font, and generate-time constraint
     * validation including annotation accessibility checks.
     */
    #[Bench\Subject]
    #[Bench\BeforeMethods('setUp')]
    public function benchPhpdftk10PagesWithPdfUaConformance(): void
    {
        $fontPath = null;
        foreach ([
            '/System/Library/Fonts/Supplemental/Arial.ttf',
            '/System/Library/Fonts/Supplemental/Georgia.ttf',
            '/usr/share/fonts/truetype/liberation/LiberationSans-Regular.ttf',
            '/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf',
        ] as $path) {
            if (file_exists($path)) {
                $fontPath = $path;
                break;
            }
        }
        if ($fontPath === null) {
            return;
        }

        $writer = new PdfWriter();
        $writer->setConformance(PdfUaProfile::UA1);

        $info = new \Phpdftk\Pdf\Core\Document\Info();
        $info->title = new PdfString('PDF/UA Benchmark');
        $info->producer = new PdfString('phpdftk');
        $writer->setInfo($info);

        // Tagged structure
        $catalog = $writer->getCatalog();
        $markInfo = new \Phpdftk\Pdf\Core\Document\MarkInfo();
        $markInfo->marked = true;
        $catalog->markInfo = $markInfo;
        $catalog->lang = new PdfString('en-US');

        $structRoot = new \Phpdftk\Pdf\Core\Document\StructTreeRoot();
        $writer->register($structRoot);
        $catalog->structTreeRoot = new PdfReference($structRoot->objectNumber);

        // ViewerPreferences
        $vp = new \Phpdftk\Pdf\Core\Document\ViewerPreferences();
        $vp->displayDocTitle = true;
        $writer->register($vp);
        $catalog->viewerPreferences = new PdfDictionary([
            'DisplayDocTitle' => new \Phpdftk\Pdf\Core\PdfBoolean(true),
        ]);

        // Embedded font
        $font = TrueTypeFont::fromFile($fontPath);
        $fontName = $writer->addFont($font)->getResourceName();

        for ($i = 1; $i <= 10; $i++) {
            $page = $writer->addPage(612, 792);
            $page->corePage()->tabs = new PdfName('S');
            $cs = $writer->addContentStream($page);
            $cs->beginText()
               ->setFont($fontName, 12)
               ->moveTextPosition(72, 720)
               ->showText(sprintf('PDF/UA-1 conformance bench page %d of 10', $i))
               ->endText();
        }

        $writer->save($this->tempDir . '/phpdftk_10pages_pdfua.pdf');
    }

    /**
     * 10-page PDF with PDF/X-4 conformance validation.
     *
     * Exercises the full PDF/X pipeline: OutputIntent with ICC profile,
     * /TrimBox on every page, /Trapped in Info dict, embedded TrueType
     * font, pdfxid XMP identification, and generate-time constraint
     * validation.
     */
    #[Bench\Subject]
    #[Bench\BeforeMethods('setUp')]
    public function benchPhpdftk10PagesWithPdfXConformance(): void
    {
        $fontPath = null;
        foreach ([
            '/System/Library/Fonts/Supplemental/Arial.ttf',
            '/System/Library/Fonts/Supplemental/Georgia.ttf',
            '/usr/share/fonts/truetype/liberation/LiberationSans-Regular.ttf',
            '/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf',
        ] as $path) {
            if (file_exists($path)) {
                $fontPath = $path;
                break;
            }
        }
        if ($fontPath === null) {
            return;
        }

        $iccPath = null;
        foreach ([
            '/System/Library/ColorSync/Profiles/sRGB Profile.icc',
            '/usr/share/color/icc/colord/sRGB.icc',
            '/usr/share/color/icc/sRGB.icc',
        ] as $path) {
            if (file_exists($path)) {
                $iccPath = $path;
                break;
            }
        }
        if ($iccPath === null) {
            return;
        }

        $writer = new PdfWriter();
        $writer->setConformance(PdfXProfile::X4);

        $info = new \Phpdftk\Pdf\Core\Document\Info();
        $info->title = new PdfString('PDF/X Benchmark');
        $info->producer = new PdfString('phpdftk');
        $info->trapped = new PdfName('False');
        $writer->setInfo($info);

        // OutputIntent
        $iccData = file_get_contents($iccPath);
        $iccStream = new \Phpdftk\Pdf\Core\PdfStream(new PdfDictionary(), $iccData);
        $iccStream->dictionary->set('N', new PdfNumber(3));
        $iccRef = $writer->register($iccStream);

        $outputIntent = new OutputIntent('GTS_PDFX', 'CGATS TR 001');
        $outputIntent->registryName = new PdfString('http://www.color.org');
        $outputIntent->info = new PdfString('CGATS TR 001');
        $outputIntent->destOutputProfile = $iccRef;
        $oiRef = $writer->register($outputIntent);
        $writer->getCatalog()->outputIntents = new PdfArray([$oiRef]);

        $font = TrueTypeFont::fromFile($fontPath);
        $fontName = $writer->addFont($font)->getResourceName();

        $trimBox = new PdfArray([
            new PdfNumber(0), new PdfNumber(0),
            new PdfNumber(612), new PdfNumber(792),
        ]);

        for ($i = 1; $i <= 10; $i++) {
            $page = $writer->addPage(612, 792);
            $page->corePage()->trimBox = $trimBox;
            $cs = $writer->addContentStream($page);
            $cs->beginText()
               ->setFont($fontName, 12)
               ->moveTextPosition(72, 720)
               ->showText(sprintf('PDF/X-4 conformance bench page %d of 10', $i))
               ->endText();
        }

        $writer->save($this->tempDir . '/phpdftk_10pages_pdfx.pdf');
    }

    /**
     * 10-page PDF with PDF/VT-1 conformance validation.
     *
     * Exercises the full PDF/VT pipeline: builds on PDF/X-4 constraints
     * plus DPartRoot for variable-data printing, embedded TrueType font,
     * pdfvtid XMP identification, and generate-time constraint validation.
     */
    #[Bench\Subject]
    #[Bench\BeforeMethods('setUp')]
    public function benchPhpdftk10PagesWithPdfVtConformance(): void
    {
        $fontPath = null;
        foreach ([
            '/System/Library/Fonts/Supplemental/Arial.ttf',
            '/System/Library/Fonts/Supplemental/Georgia.ttf',
            '/usr/share/fonts/truetype/liberation/LiberationSans-Regular.ttf',
            '/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf',
        ] as $path) {
            if (file_exists($path)) {
                $fontPath = $path;
                break;
            }
        }
        if ($fontPath === null) {
            return;
        }

        $iccPath = null;
        foreach ([
            '/System/Library/ColorSync/Profiles/sRGB Profile.icc',
            '/usr/share/color/icc/colord/sRGB.icc',
            '/usr/share/color/icc/sRGB.icc',
        ] as $path) {
            if (file_exists($path)) {
                $iccPath = $path;
                break;
            }
        }
        if ($iccPath === null) {
            return;
        }

        $writer = new PdfWriter();
        $writer->setConformance(PdfVtProfile::VT1);

        $info = new \Phpdftk\Pdf\Core\Document\Info();
        $info->title = new PdfString('PDF/VT Benchmark');
        $info->producer = new PdfString('phpdftk');
        $info->trapped = new PdfName('False');
        $writer->setInfo($info);

        // OutputIntent
        $iccData = file_get_contents($iccPath);
        $iccStream = new \Phpdftk\Pdf\Core\PdfStream(new PdfDictionary(), $iccData);
        $iccStream->dictionary->set('N', new PdfNumber(3));
        $iccRef = $writer->register($iccStream);

        $outputIntent = new OutputIntent('GTS_PDFX', 'sRGB IEC61966-2.1');
        $outputIntent->registryName = new PdfString('http://www.color.org');
        $outputIntent->info = new PdfString('sRGB IEC61966-2.1');
        $outputIntent->destOutputProfile = $iccRef;
        $oiRef = $writer->register($outputIntent);
        $writer->getCatalog()->outputIntents = new PdfArray([$oiRef]);

        // DPartRoot
        $dpart = new \Phpdftk\Pdf\Core\Document\DPart(new PdfReference(0));
        $dpartRef = $writer->register($dpart);
        $dpartRoot = new \Phpdftk\Pdf\Core\Document\DPartRoot($dpartRef);
        $writer->register($dpartRoot);
        $writer->getCatalog()->dPartRoot = new PdfReference($dpartRoot->objectNumber);

        $font = TrueTypeFont::fromFile($fontPath);
        $fontName = $writer->addFont($font)->getResourceName();

        $trimBox = new PdfArray([
            new PdfNumber(0), new PdfNumber(0),
            new PdfNumber(612), new PdfNumber(792),
        ]);

        for ($i = 1; $i <= 10; $i++) {
            $page = $writer->addPage(612, 792);
            $page->corePage()->trimBox = $trimBox;
            $cs = $writer->addContentStream($page);
            $cs->beginText()
               ->setFont($fontName, 12)
               ->moveTextPosition(72, 720)
               ->showText(sprintf('PDF/VT-1 conformance bench page %d of 10', $i))
               ->endText();
        }

        $writer->save($this->tempDir . '/phpdftk_10pages_pdfvt.pdf');
    }

    #[Bench\Subject]
    #[Bench\BeforeMethods('setUp')]
    public function benchPhpdftk10PagesWithPdfEConformance(): void
    {
        $fontPath = null;
        foreach ([
            '/System/Library/Fonts/Supplemental/Arial.ttf',
            '/System/Library/Fonts/Supplemental/Georgia.ttf',
            '/usr/share/fonts/truetype/liberation/LiberationSans-Regular.ttf',
            '/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf',
        ] as $path) {
            if (file_exists($path)) {
                $fontPath = $path;
                break;
            }
        }
        if ($fontPath === null) {
            return;
        }

        $iccPath = null;
        foreach ([
            '/System/Library/ColorSync/Profiles/sRGB Profile.icc',
            '/usr/share/color/icc/colord/sRGB.icc',
            '/usr/share/color/icc/sRGB.icc',
        ] as $path) {
            if (file_exists($path)) {
                $iccPath = $path;
                break;
            }
        }
        if ($iccPath === null) {
            return;
        }

        $writer = new PdfWriter();
        $writer->setConformance(PdfEProfile::E1);

        $info = new \Phpdftk\Pdf\Core\Document\Info();
        $info->title = new PdfString('PDF/E Benchmark');
        $info->producer = new PdfString('phpdftk');
        $writer->setInfo($info);

        // OutputIntent
        $iccData = file_get_contents($iccPath);
        $iccStream = new \Phpdftk\Pdf\Core\PdfStream(new PdfDictionary(), $iccData);
        $iccStream->dictionary->set('N', new PdfNumber(3));
        $iccRef = $writer->register($iccStream);

        $outputIntent = new OutputIntent('GTS_PDFE', 'sRGB IEC61966-2.1');
        $outputIntent->registryName = new PdfString('http://www.color.org');
        $outputIntent->info = new PdfString('sRGB IEC61966-2.1');
        $outputIntent->destOutputProfile = $iccRef;
        $oiRef = $writer->register($outputIntent);
        $writer->getCatalog()->outputIntents = new PdfArray([$oiRef]);

        $font = TrueTypeFont::fromFile($fontPath);
        $fontName = $writer->addFont($font)->getResourceName();

        for ($i = 1; $i <= 10; $i++) {
            $page = $writer->addPage(612, 792);

            // Add a 3D stream + annotation per page
            $stream3d = new ThreeDStream('U3D', 'dummy-u3d-artwork-page-' . $i);
            $streamRef = $writer->register($stream3d);
            $view3d = new ThreeDView('Default View');
            $viewRef = $writer->register($view3d);
            $stream3d->va = new PdfArray([$viewRef]);

            $rect = new PdfArray([
                new PdfNumber(100), new PdfNumber(100),
                new PdfNumber(300), new PdfNumber(300),
            ]);
            $annot3d = new ThreeDAnnotation($rect);
            $annot3d->dd = $streamRef;
            $annotRef = $writer->register($annot3d);
            $page->corePage()->annots[] = $annotRef;

            $cs = $writer->addContentStream($page);
            $cs->beginText()
               ->setFont($fontName, 12)
               ->moveTextPosition(72, 720)
               ->showText(sprintf('PDF/E-1 conformance bench page %d of 10', $i))
               ->endText();
        }

        $writer->save($this->tempDir . '/phpdftk_10pages_pdfe.pdf');
    }

    #[Bench\Subject]
    #[Bench\BeforeMethods('setUp')]
    public function benchPhpdftk10PagesWithPdfRConformance(): void
    {
        $writer = new PdfWriter();
        $writer->setConformance(PdfRProfile::R1, strict: false);

        $info = new \Phpdftk\Pdf\Core\Document\Info();
        $info->title = new PdfString('PDF/R Benchmark');
        $info->producer = new PdfString('phpdftk');
        $writer->setInfo($info);

        for ($i = 1; $i <= 10; $i++) {
            $page = $writer->addPage(612, 792);
            // Raster-only: add an inline image placeholder per page
            $cs = $writer->addContentStream($page);
            $cs->saveGraphicsState()
               ->rectangle(72, 72, 468, 648)
               ->setFillColorGray(0.9)
               ->fill()
               ->restoreGraphicsState();
        }

        $writer->save($this->tempDir . '/phpdftk_10pages_pdfr.pdf');
    }

    #[Bench\Subject]
    #[Bench\BeforeMethods('setUp')]
    public function benchPhpdftk10PagesWithPdfX5Conformance(): void
    {
        $fontPath = null;
        foreach ([
            '/System/Library/Fonts/Supplemental/Arial.ttf',
            '/System/Library/Fonts/Supplemental/Georgia.ttf',
            '/usr/share/fonts/truetype/liberation/LiberationSans-Regular.ttf',
            '/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf',
        ] as $path) {
            if (file_exists($path)) {
                $fontPath = $path;
                break;
            }
        }
        if ($fontPath === null) {
            return;
        }

        $iccPath = null;
        foreach ([
            '/System/Library/ColorSync/Profiles/sRGB Profile.icc',
            '/usr/share/color/icc/colord/sRGB.icc',
            '/usr/share/color/icc/sRGB.icc',
        ] as $path) {
            if (file_exists($path)) {
                $iccPath = $path;
                break;
            }
        }
        if ($iccPath === null) {
            return;
        }

        $writer = new PdfWriter();
        $writer->setConformance(PdfXProfile::X5g);

        $info = new \Phpdftk\Pdf\Core\Document\Info();
        $info->title = new PdfString('PDF/X-5g Benchmark');
        $info->producer = new PdfString('phpdftk');
        $info->trapped = new PdfName('False');
        $writer->setInfo($info);

        // OutputIntent
        $iccData = file_get_contents($iccPath);
        $iccStream = new \Phpdftk\Pdf\Core\PdfStream(new PdfDictionary(), $iccData);
        $iccStream->dictionary->set('N', new PdfNumber(3));
        $iccRef = $writer->register($iccStream);

        $outputIntent = new OutputIntent('GTS_PDFX', 'sRGB IEC61966-2.1');
        $outputIntent->registryName = new PdfString('http://www.color.org');
        $outputIntent->info = new PdfString('sRGB IEC61966-2.1');
        $outputIntent->destOutputProfile = $iccRef;
        $oiRef = $writer->register($outputIntent);
        $writer->getCatalog()->outputIntents = new PdfArray([$oiRef]);

        $font = TrueTypeFont::fromFile($fontPath);
        $fontName = $writer->addFont($font)->getResourceName();

        $trimBox = new PdfArray([
            new PdfNumber(0), new PdfNumber(0),
            new PdfNumber(612), new PdfNumber(792),
        ]);

        for ($i = 1; $i <= 10; $i++) {
            $page = $writer->addPage(612, 792);
            $page->corePage()->trimBox = $trimBox;
            $cs = $writer->addContentStream($page);
            $cs->beginText()
               ->setFont($fontName, 12)
               ->moveTextPosition(72, 720)
               ->showText(sprintf('PDF/X-5g conformance bench page %d of 10', $i))
               ->endText();
        }

        $writer->save($this->tempDir . '/phpdftk_10pages_pdfx5.pdf');
    }

    #[Bench\Subject]
    #[Bench\BeforeMethods('setUp')]
    public function benchPhpdftk10PagesWithZugferdConformance(): void
    {
        $fontPath = null;
        foreach ([
            '/System/Library/Fonts/Supplemental/Arial.ttf',
            '/System/Library/Fonts/Supplemental/Georgia.ttf',
            '/usr/share/fonts/truetype/liberation/LiberationSans-Regular.ttf',
            '/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf',
        ] as $path) {
            if (file_exists($path)) {
                $fontPath = $path;
                break;
            }
        }
        if ($fontPath === null) {
            return;
        }

        $iccPath = null;
        foreach ([
            '/System/Library/ColorSync/Profiles/sRGB Profile.icc',
            '/usr/share/color/icc/colord/sRGB.icc',
            '/usr/share/color/icc/sRGB.icc',
        ] as $path) {
            if (file_exists($path)) {
                $iccPath = $path;
                break;
            }
        }
        if ($iccPath === null) {
            return;
        }

        $writer = new PdfWriter();
        $writer->setConformance(\Phpdftk\Pdf\Conformance\Profile\ZugferdProfile::BASIC, strict: false);

        $info = new \Phpdftk\Pdf\Core\Document\Info();
        $info->title = new PdfString('Factur-X Benchmark');
        $info->producer = new PdfString('phpdftk');
        $writer->setInfo($info);

        // OutputIntent
        $iccData = file_get_contents($iccPath);
        $iccStream = new \Phpdftk\Pdf\Core\PdfStream(new PdfDictionary(), $iccData);
        $iccStream->dictionary->set('N', new PdfNumber(3));
        $iccRef = $writer->register($iccStream);

        $outputIntent = new OutputIntent('GTS_PDFA1', 'sRGB IEC61966-2.1');
        $outputIntent->registryName = new PdfString('http://www.color.org');
        $outputIntent->info = new PdfString('sRGB IEC61966-2.1');
        $outputIntent->destOutputProfile = $iccRef;
        $oiRef = $writer->register($outputIntent);
        $writer->getCatalog()->outputIntents = new PdfArray([$oiRef]);

        // Embed a dummy XML invoice
        $invoiceXml = '<?xml version="1.0"?><Invoice>dummy</Invoice>';
        $fileSpec = new FileSpec('factur-x.xml');
        $fileSpec->afRelationship = new PdfName('Data');
        $fsRef = $writer->register($fileSpec);

        $font = TrueTypeFont::fromFile($fontPath);
        $fontName = $writer->addFont($font)->getResourceName();

        for ($i = 1; $i <= 10; $i++) {
            $page = $writer->addPage(612, 792);
            $cs = $writer->addContentStream($page);
            $cs->beginText()
               ->setFont($fontName, 12)
               ->moveTextPosition(72, 720)
               ->showText(sprintf('Factur-X conformance bench page %d of 10', $i))
               ->endText();
        }

        $writer->save($this->tempDir . '/phpdftk_10pages_zugferd.pdf');
    }

    #[Bench\Subject]
    #[Bench\BeforeMethods('setUp')]
    public function benchPhpdftk10PagesWithPdfMailConformance(): void
    {
        $fontPath = null;
        foreach ([
            '/System/Library/Fonts/Supplemental/Arial.ttf',
            '/System/Library/Fonts/Supplemental/Georgia.ttf',
            '/usr/share/fonts/truetype/liberation/LiberationSans-Regular.ttf',
            '/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf',
        ] as $path) {
            if (file_exists($path)) {
                $fontPath = $path;
                break;
            }
        }
        if ($fontPath === null) {
            return;
        }

        $writer = new PdfWriter();
        $writer->setConformance(\Phpdftk\Pdf\Conformance\Profile\PdfMailProfile::Mail1);

        $info = new \Phpdftk\Pdf\Core\Document\Info();
        $info->title = new PdfString('PDF/mail Benchmark');
        $info->producer = new PdfString('phpdftk');
        $writer->setInfo($info);

        $font = TrueTypeFont::fromFile($fontPath);
        $fontName = $writer->addFont($font)->getResourceName();

        for ($i = 1; $i <= 10; $i++) {
            $page = $writer->addPage(612, 792);
            $cs = $writer->addContentStream($page);
            $cs->beginText()
               ->setFont($fontName, 12)
               ->moveTextPosition(72, 720)
               ->showText(sprintf('PDF/mail-1 conformance bench page %d of 10', $i))
               ->endText();
        }

        $writer->save($this->tempDir . '/phpdftk_10pages_pdfmail.pdf');
    }
}
