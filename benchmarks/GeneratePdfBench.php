<?php

declare(strict_types=1);

namespace ApprLabs\Benchmarks;

use PhpBench\Attributes as Bench;
use ApprLabs\Pdf\Core\Annotation\HighlightAnnotation;
use ApprLabs\Pdf\Core\Annotation\LineAnnotation;
use ApprLabs\Pdf\Core\Annotation\SquareAnnotation;
use ApprLabs\Pdf\Core\Annotation\TextAnnotation;
use ApprLabs\Pdf\Core\Document\Destination;
use ApprLabs\Pdf\Core\Document\MarkInfo;
use ApprLabs\Pdf\Core\Document\Outline;
use ApprLabs\Pdf\Core\Document\OutlineItem;
use ApprLabs\Pdf\Core\Document\OutputIntent;
use ApprLabs\Pdf\Core\Document\PageLabel;
use ApprLabs\Pdf\Core\Document\StructElem;
use ApprLabs\Pdf\Core\Document\StructTreeRoot;
use ApprLabs\Pdf\Core\Document\TransitionDict;
use ApprLabs\Pdf\Core\Content\ContentStream;
use ApprLabs\Pdf\Core\Document\CrossReferenceStream;
use ApprLabs\Pdf\Core\Document\ObjectStream;
use ApprLabs\Pdf\Core\Font\Encoding;
use ApprLabs\Pdf\Core\Font\StandardFont;
use ApprLabs\Pdf\Core\Font\TrueTypeFont;
use ApprLabs\Pdf\Core\Font\Type1Font;
use ApprLabs\Pdf\Core\Font\Type3Font;
use ApprLabs\Pdf\Core\Graphics\ColorSpace\DeviceRGB;
use ApprLabs\Pdf\Core\Graphics\Function\FunctionType2;
use ApprLabs\Pdf\Core\Graphics\Pattern\ShadingPattern;
use ApprLabs\Pdf\Core\Graphics\Pattern\TilingPattern;
use ApprLabs\Pdf\Core\Graphics\Shading\ShadingType2;
use ApprLabs\Pdf\Core\Multimedia\MediaClipData;
use ApprLabs\Pdf\Core\Multimedia\MediaRendition;
use ApprLabs\Pdf\Core\ThreeD\ThreeDStream;
use ApprLabs\Pdf\Core\ThreeD\ThreeDView;
use ApprLabs\Pdf\Core\FileSpec\FileSpec;
use ApprLabs\Pdf\Core\Annotation\ScreenAnnotation;
use ApprLabs\Pdf\Core\Annotation\ThreeDAnnotation;
use ApprLabs\Pdf\Core\Action\RenditionAction;
use ApprLabs\Pdf\Core\Annotation\WidgetAnnotation;
use ApprLabs\Pdf\Core\Interactive\Form\AcroForm;
use ApprLabs\Pdf\Core\Interactive\Form\SignatureField;
use ApprLabs\Pdf\Core\Interactive\Signature\DocMDPTransformParams;
use ApprLabs\Pdf\Core\Interactive\Signature\Pkcs7Signer;
use ApprLabs\Pdf\Core\Interactive\Signature\SignatureReference;
use ApprLabs\Pdf\Core\Interactive\Signature\SignatureValue;
use ApprLabs\Pdf\Core\PdfArray;
use ApprLabs\Pdf\Core\PdfDictionary;
use ApprLabs\Pdf\Core\PdfName;
use ApprLabs\Pdf\Core\PdfNumber;
use ApprLabs\Pdf\Core\PdfReference;
use ApprLabs\Pdf\Core\PdfString;
use ApprLabs\Pdf\Writer\PdfWriter;

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
        $fontName = $writer->addFont(new Type1Font(StandardFont::Helvetica));

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
        $fontName = $writer->addFont(new Type1Font(StandardFont::Helvetica));

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
        $fontName = $writer->addFont(new Type1Font(StandardFont::Helvetica));

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
        $fontName = $writer->addFont(new Type1Font(StandardFont::Helvetica));

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
        $fontName = $writer->addFont(new Type1Font(StandardFont::Helvetica));

        $outline  = $writer->setOutline(new Outline());
        $prevRef  = null;

        for ($i = 1; $i <= 10; $i++) {
            $transition = new TransitionDict();
            $transition->s = new PdfName('Dissolve');
            $transition->d = new PdfNumber(0.5);

            $page = $writer->addPage(612, 792);
            $page->transition = $transition;
            $page->dur        = new PdfNumber(5.0);

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
        $fontName = $writer->addFont(new Type1Font(StandardFont::Helvetica));

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
                new PdfArray([new PdfNumber(72), new PdfNumber(680), new PdfNumber(120), new PdfNumber(710)])
            );
            $textAnnot->contents = new PdfString(sprintf('Note on page %d', $i));
            $textAnnot->name = new PdfName('Note');
            $writer->register($textAnnot);
            $page->annots[] = new PdfReference($textAnnot->objectNumber);

            // HighlightAnnotation
            $highlight = new HighlightAnnotation(
                new PdfArray([new PdfNumber(72), new PdfNumber(620), new PdfNumber(300), new PdfNumber(640)]),
                new PdfArray([
                    new PdfNumber(72), new PdfNumber(640),
                    new PdfNumber(300), new PdfNumber(640),
                    new PdfNumber(72), new PdfNumber(620),
                    new PdfNumber(300), new PdfNumber(620),
                ])
            );
            $highlight->c = new PdfArray([new PdfNumber(1), new PdfNumber(1), new PdfNumber(0)]);
            $writer->register($highlight);
            $page->annots[] = new PdfReference($highlight->objectNumber);

            // LineAnnotation
            $line = new LineAnnotation(
                new PdfArray([new PdfNumber(72), new PdfNumber(560), new PdfNumber(300), new PdfNumber(590)])
            );
            $line->l = new PdfArray([
                new PdfNumber(72), new PdfNumber(575),
                new PdfNumber(300), new PdfNumber(575),
            ]);
            $line->le = new PdfArray([new PdfName('None'), new PdfName('OpenArrow')]);
            $writer->register($line);
            $page->annots[] = new PdfReference($line->objectNumber);

            // SquareAnnotation
            $square = new SquareAnnotation(
                new PdfArray([new PdfNumber(72), new PdfNumber(480), new PdfNumber(200), new PdfNumber(540)])
            );
            $square->ic = new PdfArray([new PdfNumber(0.8), new PdfNumber(0.9), new PdfNumber(1.0)]);
            $writer->register($square);
            $page->annots[] = new PdfReference($square->objectNumber);
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
        $fontName = $writer->addFont($font);

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
        $fontName = $writer->addFont(new Type1Font(StandardFont::Helvetica));

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
                new PdfReference($page->objectNumber), 72, 720, 1.0
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
            $elem->pg = new PdfReference($page->objectNumber);
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
        $fontName = $writer->addFont($font);

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
        $catalog = new \ApprLabs\Pdf\Core\Document\Catalog();
        $catalog->objectNumber = 1;

        $pageTree = new \ApprLabs\Pdf\Core\Document\PageTree();
        $pageTree->objectNumber = 2;

        $page = new \ApprLabs\Pdf\Core\Document\Page();
        $page->objectNumber = 3;
        $page->parent = new PdfReference($pageTree->objectNumber);
        $page->mediaBox = new PdfArray([
            new PdfNumber(0), new PdfNumber(0),
            new PdfNumber(612), new PdfNumber(792),
        ]);
        $page->resources = new \ApprLabs\Pdf\Core\Content\Resources();

        $info = new \ApprLabs\Pdf\Core\Document\Info();
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
        $fontName = $writer->addFont(new Type1Font(StandardFont::Helvetica));

        $ramp = new FunctionType2(
            new PdfArray([new PdfNumber(0), new PdfNumber(1)]),
            new PdfArray([new PdfNumber(1), new PdfNumber(0), new PdfNumber(0)]),
            new PdfArray([new PdfNumber(0), new PdfNumber(0), new PdfNumber(1)]),
            1.0
        );
        $rampRef = $writer->register($ramp);

        $axial = new ShadingType2(
            new DeviceRGB(),
            new PdfArray([
                new PdfNumber(72), new PdfNumber(600),
                new PdfNumber(540), new PdfNumber(600),
            ]),
            $rampRef
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
            resources: new \ApprLabs\Pdf\Core\Content\Resources(),
            contentStream: "0 0.6 0 rg 0 0 20 20 re f 1 0 0 rg 5 5 10 10 re f",
        );
        $tilingRef = $writer->register($tiling);

        for ($i = 1; $i <= 10; $i++) {
            $page = $writer->addPage(612, 792);
            if ($page->resources !== null) {
                $page->resources->pattern = [
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
        $fontName = $writer->addFont(new Type1Font(StandardFont::Helvetica));

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
            $page->annots[] = $screenRef;

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
            $page->annots[] = $writer->register($threeD);
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
        $fontName = $writer->addFont(new Type1Font(StandardFont::Helvetica));

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
                $page->annots[] = $writer->register($widget);
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
        $fontName = $writer->addFont(new Type1Font(StandardFont::Helvetica));

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
                $page->annots[] = $writer->register($widget);
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
        $fontName = $writer->addFont(new Type1Font(StandardFont::Helvetica));

        for ($i = 1; $i <= 10; $i++) {
            $page = $writer->addPage(612, 792);
            $cs = $writer->addContentStream($page);
            $cs->beginText()
               ->setFont($fontName, 12)
               ->moveTextPosition(72, 740)
               ->showText(sprintf('Markup page %d of 10', $i))
               ->endText();

            $popup = new \ApprLabs\Pdf\Core\Annotation\PopupAnnotation(new PdfArray([
                new PdfNumber(400), new PdfNumber(600),
                new PdfNumber(540), new PdfNumber(700),
            ]));
            $popupRef = $writer->register($popup);
            $page->annots[] = $popupRef;

            $note = new \ApprLabs\Pdf\Core\Annotation\TextAnnotation(new PdfArray([
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
            $page->annots[] = $noteRef;

            $hl = new \ApprLabs\Pdf\Core\Annotation\HighlightAnnotation(
                new PdfArray([new PdfNumber(72), new PdfNumber(500), new PdfNumber(540), new PdfNumber(520)]),
                new PdfArray([
                    new PdfNumber(72), new PdfNumber(520),
                    new PdfNumber(540), new PdfNumber(520),
                    new PdfNumber(540), new PdfNumber(500),
                    new PdfNumber(72), new PdfNumber(500),
                ])
            );
            $hl->t = new PdfString('Bob');
            $hl->subj = new PdfString('Agreed');
            $hl->irt = $noteRef;
            $hl->rt = new PdfName('R');
            $page->annots[] = $writer->register($hl);
        }

        $writer->save($this->tempDir . '/phpdftk_10pages_markup.pdf');
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
            $fontName = $writer->addFont(new Type1Font(StandardFont::Helvetica));

            $cs = $writer->addContentStream($page);
            $cs->beginText()->setFont($fontName, 12)->moveTextPosition(72, 720)
               ->showText("Form page $i")->endText();

            // Generate text field appearance
            $rect = new PdfArray([new PdfNumber(72), new PdfNumber(680), new PdfNumber(300), new PdfNumber(700)]);
            $xObj = \ApprLabs\Pdf\Core\Interactive\Form\AppearanceGenerator::textField($rect, $fontName, 12, "Value $i");
            $writer->register($xObj);

            // Generate checkbox appearance
            $checkRect = new PdfArray([new PdfNumber(72), new PdfNumber(650), new PdfNumber(90), new PdfNumber(668)]);
            $checkStates = \ApprLabs\Pdf\Core\Interactive\Form\AppearanceGenerator::checkbox($checkRect);
            $writer->register($checkStates['on']);
            $writer->register($checkStates['off']);
        }

        $writer->save($this->tempDir . '/phpdftk_form_appearances.pdf');
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

        $data = (new \ApprLabs\FontParser\OpenTypeParser($fontPath))->parse();
        $text = 'The quick brown fox jumps over the lazy dog. 0123456789';
        $codepoints = array_unique(array_map('mb_ord', mb_str_split($text)));

        $writer = new PdfWriter();
        for ($i = 1; $i <= 10; $i++) {
            $page = $writer->addPage(612, 792);
            $fontName = $writer->addOpenTypeFont($data, $codepoints, $page);

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
}
