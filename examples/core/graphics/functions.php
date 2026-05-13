<?php

declare(strict_types=1);

require_once __DIR__ . '/../../bootstrap.php';

chdir(__DIR__);

// region: example
use Phpdftk\Pdf\Core\Font\StandardFont;
use Phpdftk\Pdf\Core\Font\Type1Font;
use Phpdftk\Pdf\Core\Graphics\ColorSpace\DeviceRGB;
use Phpdftk\Pdf\Core\Graphics\Function\FunctionType2;
use Phpdftk\Pdf\Core\Graphics\Function\FunctionType3;
use Phpdftk\Pdf\Core\Graphics\Pattern\ShadingPattern;
use Phpdftk\Pdf\Core\Graphics\Shading\ShadingType2;
use Phpdftk\Pdf\Core\PdfArray;
use Phpdftk\Pdf\Core\PdfNumber;
use Phpdftk\Pdf\Writer\PdfWriter;

$writer = new PdfWriter();
$page = $writer->addPage();
$body = $writer->addFont(new Type1Font(StandardFont::Helvetica))->getResourceName();
$bold = $writer->addFont(new Type1Font(StandardFont::HelveticaBold))->getResourceName();

// Helper: build an exponential function ramp between two RGB triplets.
$ramp = static function (array $c0, array $c1, float $n = 1.0) {
    return new FunctionType2(
        domain: new PdfArray([new PdfNumber(0), new PdfNumber(1)]),
        c0: new PdfArray(array_map(fn ($v) => new PdfNumber($v), $c0)),
        c1: new PdfArray(array_map(fn ($v) => new PdfNumber($v), $c1)),
        n: $n,
    );
};

// 1) Linear Type 2 function: blue → orange.
$linear = $writer->register($ramp([0.10, 0.30, 0.85], [0.95, 0.55, 0.10], 1.0));

// 2) Non-linear Type 2 function with N=4 (a steep falloff).
$steep = $writer->register($ramp([0.05, 0.05, 0.05], [1.00, 0.85, 0.15], 4.0));

// 3) Stitching (Type 3) function: red → white → green, joined at t=0.5.
$redWhite   = $ramp([0.85, 0.20, 0.20], [1.0, 1.0, 1.0]);
$whiteGreen = $ramp([1.0, 1.0, 1.0], [0.10, 0.55, 0.20]);
$redWhiteRef   = $writer->register($redWhite);
$whiteGreenRef = $writer->register($whiteGreen);

$stitching = new FunctionType3(
    domain:    new PdfArray([new PdfNumber(0), new PdfNumber(1)]),
    functions: new PdfArray([$redWhiteRef, $whiteGreenRef]),
    bounds:    new PdfArray([new PdfNumber(0.5)]),
    encode:    new PdfArray([
        new PdfNumber(0), new PdfNumber(1),
        new PdfNumber(0), new PdfNumber(1),
    ]),
);
$stitchingRef = $writer->register($stitching);

// Wire each function to an axial shading and a pattern for paint.
$axisCoords = new PdfArray([
    new PdfNumber(72),  new PdfNumber(0),
    new PdfNumber(540), new PdfNumber(0),
]);
$bands = [
    ['P1', $linear,       640, 'Type 2 — linear (N=1):'],
    ['P2', $steep,        540, 'Type 2 — exponential (N=4):'],
    ['P3', $stitchingRef, 440, 'Type 3 — stitching of two ramps:'],
];

foreach ($bands as [$name, $funcRef, $y, $label]) {
    $shading = new ShadingType2(new DeviceRGB(), $axisCoords, $funcRef);
    $shadingRef = $writer->register($shading);
    $page->corePage()->resources->pattern[$name] = $writer->register(new ShadingPattern($shadingRef));
}

$cs = $writer->addContentStream($page);
$cs->beginText()->setFont($bold, 22)->moveTextPosition(72, 740)
    ->showText('Functions')->endText();
$cs->beginText()->setFont($body, 11)->moveTextPosition(72, 706)
    ->showText('Functions drive the color computation behind every shading.')
    ->endText();

foreach ($bands as [$name, , $y, $label]) {
    $cs->beginText()->setFont($body, 11)->moveTextPosition(72, $y + 50)
        ->showText($label)->endText();
    $cs->raw('/Pattern cs');
    $cs->raw('/' . $name . ' scn');
    $cs->rectangle(72, $y, 468, 40)->fill();
}

$writer->save('functions.pdf');
// endregion

rename(__DIR__ . '/functions.pdf', example_output_path('core/graphics/functions.pdf'));
