<?php

declare(strict_types=1);

require_once __DIR__ . '/../../bootstrap.php';

chdir(__DIR__);

// region: example
use Phpdftk\Pdf\Core\Font\StandardFont;
use Phpdftk\Pdf\Core\Font\Type1Font;
use Phpdftk\Pdf\Core\Graphics\ColorSpace\DeviceRGB;
use Phpdftk\Pdf\Core\Graphics\Function\FunctionType2;
use Phpdftk\Pdf\Core\Graphics\Pattern\ShadingPattern;
use Phpdftk\Pdf\Core\Graphics\Shading\ShadingType2;
use Phpdftk\Pdf\Core\Graphics\Shading\ShadingType3;
use Phpdftk\Pdf\Core\PdfArray;
use Phpdftk\Pdf\Core\PdfNumber;
use Phpdftk\Pdf\Writer\PdfWriter;

$writer = new PdfWriter();
$page = $writer->addPage();
$body = $writer->addFont(new Type1Font(StandardFont::Helvetica))->getResourceName();
$bold = $writer->addFont(new Type1Font(StandardFont::HelveticaBold))->getResourceName();

// A Type 2 function defines the color ramp used by every shading on this page:
// magenta at t=0, cyan at t=1, linear interpolation in between.
$ramp = new FunctionType2(
    domain: new PdfArray([new PdfNumber(0), new PdfNumber(1)]),
    c0: new PdfArray([new PdfNumber(0.9), new PdfNumber(0.2), new PdfNumber(0.5)]),
    c1: new PdfArray([new PdfNumber(0.2), new PdfNumber(0.6), new PdfNumber(0.9)]),
    n: 1.0,
);
$rampRef = $writer->register($ramp);

// Axial (linear) shading from x=72 to x=540.
$axial = new ShadingType2(
    new DeviceRGB(),
    new PdfArray([
        new PdfNumber(72),  new PdfNumber(0),
        new PdfNumber(540), new PdfNumber(0),
    ]),
    $rampRef,
);
$axialRef = $writer->register($axial);
$axialPattern = $writer->register(new ShadingPattern($axialRef));

// Radial shading centered at (306, 380), starting at radius 0 and ending at radius 180.
$radial = new ShadingType3(
    new DeviceRGB(),
    new PdfArray([
        new PdfNumber(306), new PdfNumber(380), new PdfNumber(0),
        new PdfNumber(306), new PdfNumber(380), new PdfNumber(180),
    ]),
    $rampRef,
);
$radialRef = $writer->register($radial);
$radialPattern = $writer->register(new ShadingPattern($radialRef));

// Expose both patterns under page resources.
$page->corePage()->resources->pattern = [
    'P1' => $axialPattern,
    'P2' => $radialPattern,
];

$cs = $writer->addContentStream($page);
$cs->beginText()->setFont($bold, 22)->moveTextPosition(72, 740)
    ->showText('Shadings')->endText();

$cs->beginText()->setFont($body, 11)->moveTextPosition(72, 690)
    ->showText('Axial shading — Type 2 function ramped along a linear axis:')
    ->endText();
$cs->raw('/Pattern cs');
$cs->raw('/P1 scn');
$cs->rectangle(72, 620, 468, 50)->fill();

$cs->beginText()->setFont($body, 11)->moveTextPosition(72, 580)
    ->showText('Radial shading — same ramp from center outward:')
    ->endText();
$cs->raw('/Pattern cs');
$cs->raw('/P2 scn');
$cs->rectangle(126, 200, 360, 360)->fill();

$writer->save('shadings.pdf');
// endregion

rename(__DIR__ . '/shadings.pdf', example_output_path('core/graphics/shadings.pdf'));
