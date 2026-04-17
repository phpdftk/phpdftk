<?php

declare(strict_types=1);

namespace ApprLabs\Pdf\Core\Tests\Document;

use ApprLabs\Pdf\Core\Action\RenditionAction;
use ApprLabs\Pdf\Core\Annotation\ScreenAnnotation;
use ApprLabs\Pdf\Core\Annotation\ThreeDAnnotation;
use ApprLabs\Pdf\Core\FileSpec\EmbeddedFile;
use ApprLabs\Pdf\Core\FileSpec\FileSpec;
use ApprLabs\Pdf\Core\Font\StandardFont;
use ApprLabs\Pdf\Core\Font\Type1Font;
use ApprLabs\Pdf\Core\Graphics\ColorSpace\DeviceRGB;
use ApprLabs\Pdf\Core\Multimedia\MediaClipData;
use ApprLabs\Pdf\Core\Multimedia\MediaPlayParams;
use ApprLabs\Pdf\Core\Multimedia\MediaRendition;
use ApprLabs\Pdf\Core\Multimedia\MediaScreenParams;
use ApprLabs\Pdf\Core\PdfArray;
use ApprLabs\Pdf\Core\PdfDictionary;
use ApprLabs\Pdf\Core\PdfName;
use ApprLabs\Pdf\Core\PdfNumber;
use ApprLabs\Pdf\Core\PdfReference;
use ApprLabs\Pdf\Core\PdfString;
use ApprLabs\Pdf\Core\ThreeD\ThreeDBackground;
use ApprLabs\Pdf\Core\ThreeD\ThreeDLightingScheme;
use ApprLabs\Pdf\Core\ThreeD\ThreeDRenderMode;
use ApprLabs\Pdf\Core\ThreeD\ThreeDStream;
use ApprLabs\Pdf\Core\ThreeD\ThreeDView;
use ApprLabs\Pdf\Writer\PdfWriter;
use PHPUnit\Framework\TestCase;

/**
 * End-to-end test that generates a real PDF containing:
 *   - ScreenAnnotation + RenditionAction driving a MediaRendition with
 *     an embedded MediaClipData wrapped in MediaPlayParams/MediaScreenParams
 *   - ThreeDAnnotation wired to a ThreeDStream with view, background,
 *     render-mode and lighting-scheme dictionaries
 */
class MultimediaAndThreeDIntegrationTest extends TestCase
{
    private const OUTPUT_FILE = __DIR__ . '/../../../../../docs/sample-pdfs/multimedia_3d.pdf';

    public function testGeneratesMultimediaAnd3DPdf(): void
    {
        $writer = new PdfWriter();
        $page = $writer->addPage(612, 792);
        $fontName = $writer->addFont(new Type1Font(StandardFont::Helvetica));

        $cs = $writer->addContentStream($page);
        $cs->beginText()
            ->setFont($fontName, 18)
            ->moveTextPosition(72, 740)
            ->showText('Multimedia + 3D integration')
            ->moveTextPosition(0, -24)
            ->setFont($fontName, 11)
            ->showText('Screen annot + rendition above; 3D annot below.')
            ->endText();

        // --------------------------------------------------------------
        // Multimedia: embedded audio clip played by a screen annotation.
        // --------------------------------------------------------------
        $embedded = new EmbeddedFile("fake mp3 bytes", 'audio/mpeg');
        $embeddedRef = $writer->register($embedded);

        $clipSpec = new FileSpec('demo.mp3');
        $clipSpec->attachEmbeddedFile($embeddedRef);
        $clipSpecRef = $writer->register($clipSpec);

        $clip = new MediaClipData($clipSpecRef);
        $clip->ct = new PdfString('audio/mpeg');
        $clip->n = new PdfString('Demo clip');
        $clipRef = $writer->register($clip);

        $playParams = new MediaPlayParams();
        $playParams->mh = new PdfDictionary(['V' => new PdfNumber(100)]);
        $playParamsRef = $writer->register($playParams);

        $screenParams = new MediaScreenParams();
        $screenParams->be = new PdfDictionary(['W' => new PdfNumber(3)]);
        $screenParamsRef = $writer->register($screenParams);

        $rendition = new MediaRendition();
        $rendition->n = new PdfString('DemoRendition');
        $rendition->c = $clipRef;
        $rendition->p = $playParamsRef;
        $rendition->sp = $screenParamsRef;
        $renditionRef = $writer->register($rendition);

        $screen = new ScreenAnnotation(new PdfArray([
            new PdfNumber(72), new PdfNumber(560),
            new PdfNumber(300), new PdfNumber(660),
        ]));
        $screen->t = new PdfString('Demo screen');
        $screenRef = $writer->register($screen);
        $page->annots[] = $screenRef;

        $action = new RenditionAction();
        $action->op = 0;  // play
        $action->r = $renditionRef;
        $action->an = $screenRef;
        $actionRef = $writer->register($action);
        $screen->a = $actionRef;

        // --------------------------------------------------------------
        // 3D annotation with supporting view/background/render/lighting.
        // --------------------------------------------------------------
        $u3d = new ThreeDStream('U3D', "fake u3d bytes");
        $u3d->colorSpace = new DeviceRGB();
        $u3dRef = $writer->register($u3d);

        $bg = new ThreeDBackground();
        $bg->cs = new DeviceRGB();
        $bg->c = new PdfArray([new PdfNumber(1), new PdfNumber(1), new PdfNumber(1)]);
        $bgRef = $writer->register($bg);

        $rm = new ThreeDRenderMode('Solid');
        $rm->op = 0.9;
        $rmRef = $writer->register($rm);

        $ls = new ThreeDLightingScheme('Day');
        $lsRef = $writer->register($ls);

        $view = new ThreeDView('DefaultView');
        $view->co = 100.0;
        $view->ms = new PdfName('M');
        $view->bg = $bgRef;
        $view->rm = $rmRef;
        $view->ls = $lsRef;
        $viewRef = $writer->register($view);

        // Link the view back to the 3D stream via /VA + /DV.
        $u3d->va = new PdfArray([$viewRef]);
        $u3d->dv = $viewRef;

        $threeD = new ThreeDAnnotation(new PdfArray([
            new PdfNumber(72), new PdfNumber(180),
            new PdfNumber(540), new PdfNumber(500),
        ]));
        $threeD->dd = $u3dRef;
        $threeD->di = true;
        $threeD->db = new PdfArray([
            new PdfNumber(0), new PdfNumber(0),
            new PdfNumber(1), new PdfNumber(1),
        ]);
        $threeDRef = $writer->register($threeD);
        $page->annots[] = $threeDRef;

        $writer->save(self::OUTPUT_FILE);

        self::assertFileExists(self::OUTPUT_FILE);
        $contents = file_get_contents(self::OUTPUT_FILE);
        self::assertNotFalse($contents);
        self::assertStringStartsWith('%PDF-', $contents);
        self::assertStringContainsString('/Type /Rendition', $contents);
        self::assertStringContainsString('/S /MR', $contents);
        self::assertStringContainsString('/Type /MediaClip', $contents);
        self::assertStringContainsString('/S /MCD', $contents);
        self::assertStringContainsString('/Type /MediaPlayParams', $contents);
        self::assertStringContainsString('/Type /MediaScreenParams', $contents);
        self::assertStringContainsString('/Subtype /Screen', $contents);
        self::assertStringContainsString('/S /Rendition', $contents);
        self::assertStringContainsString('/Type /3D', $contents);
        self::assertStringContainsString('/Subtype /U3D', $contents);
        self::assertStringContainsString('/Type /3DView', $contents);
        self::assertStringContainsString('/Type /3DBG', $contents);
        self::assertStringContainsString('/Type /3DRenderMode', $contents);
        self::assertStringContainsString('/Type /3DLightingScheme', $contents);
        self::assertStringContainsString('/Subtype /3D', $contents);
        self::assertStringContainsString('%%EOF', $contents);
    }
}
