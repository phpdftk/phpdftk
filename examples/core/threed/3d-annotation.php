<?php

declare(strict_types=1);

require_once __DIR__ . '/../../bootstrap.php';

chdir(__DIR__);

// region: example
use Phpdftk\Pdf\Core\Annotation\ThreeDAnnotation;
use Phpdftk\Pdf\Core\Font\StandardFont;
use Phpdftk\Pdf\Core\Font\Type1Font;
use Phpdftk\Pdf\Core\Graphics\ColorSpace\DeviceRGB;
use Phpdftk\Pdf\Core\PdfArray;
use Phpdftk\Pdf\Core\PdfName;
use Phpdftk\Pdf\Core\PdfNumber;
use Phpdftk\Pdf\Core\ThreeD\ThreeDBackground;
use Phpdftk\Pdf\Core\ThreeD\ThreeDLightingScheme;
use Phpdftk\Pdf\Core\ThreeD\ThreeDRenderMode;
use Phpdftk\Pdf\Core\ThreeD\ThreeDStream;
use Phpdftk\Pdf\Core\ThreeD\ThreeDView;
use Phpdftk\Pdf\Writer\PdfWriter;

// 3D annotations carry a U3D or PRC payload along with viewport, background, lighting
// and render-mode configuration. This example wires the full object graph using
// placeholder model bytes — substitute a real .u3d file in production.
$writer = new PdfWriter();
$page = $writer->addPage();
$body = $writer->addFont(new Type1Font(StandardFont::Helvetica))->getResourceName();
$bold = $writer->addFont(new Type1Font(StandardFont::HelveticaBold))->getResourceName();

$cs = $writer->addContentStream($page);
$cs->beginText()->setFont($bold, 22)->moveTextPosition(72, 740)
    ->showText('3D Annotation')->endText();
$cs->beginText()->setFont($body, 11)->moveTextPosition(72, 706)
    ->showText('A 3D viewport is reserved below. Viewers with 3D support load the')
    ->endText();
$cs->beginText()->setFont($body, 11)->moveTextPosition(72, 690)
    ->showText('embedded U3D stream into the box and let users rotate/pan/zoom.')
    ->endText();

// 1) U3D model stream. Real applications supply real geometry; the structure
//    is the same.
$u3d = new ThreeDStream('U3D', "U3D PLACEHOLDER MODEL DATA — substitute a real file in production.");
$u3d->colorSpace = new DeviceRGB();
$u3dRef = $writer->register($u3d);

// 2) Default view: camera/coords/background/render/lighting.
$bg = new ThreeDBackground();
$bg->cs = new DeviceRGB();
$bg->c = new PdfArray([new PdfNumber(0.96), new PdfNumber(0.97), new PdfNumber(0.99)]);
$bgRef = $writer->register($bg);

$rm = new ThreeDRenderMode('Solid');
$rm->op = 1.0;
$rmRef = $writer->register($rm);

$ls = new ThreeDLightingScheme('Day');
$lsRef = $writer->register($ls);

$view = new ThreeDView('DefaultView');
$view->co = 120.0;
$view->ms = new PdfName('M');
$view->bg = $bgRef;
$view->rm = $rmRef;
$view->ls = $lsRef;
$viewRef = $writer->register($view);

$u3d->va = new PdfArray([$viewRef]);
$u3d->dv = $viewRef;

// 3) Annotation: the rectangle on the page where the 3D viewport renders.
$annot = new ThreeDAnnotation(new PdfArray([
    new PdfNumber(72),  new PdfNumber(200),
    new PdfNumber(540), new PdfNumber(660),
]));
$annot->dd = $u3dRef;
$annot->di = true;
$annot->db = new PdfArray([
    new PdfNumber(0), new PdfNumber(0), new PdfNumber(1), new PdfNumber(1),
]);
$page->corePage()->annots[] = $writer->register($annot);

$writer->save('3d-annotation.pdf');
// endregion

rename(__DIR__ . '/3d-annotation.pdf', example_output_path('core/threed/3d-annotation.pdf'));
