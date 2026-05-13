<?php

declare(strict_types=1);

require_once __DIR__ . '/../../bootstrap.php';

chdir(__DIR__);

// region: example
use Phpdftk\Pdf\Core\Font\StandardFont;
use Phpdftk\Pdf\Core\Font\Type1Font;
use Phpdftk\Pdf\Core\Graphics\ExtGState;
use Phpdftk\Pdf\Writer\PdfWriter;

$writer = new PdfWriter();
$page = $writer->addPage();
$body = $writer->addFont(new Type1Font(StandardFont::Helvetica))->getResourceName();
$bold = $writer->addFont(new Type1Font(StandardFont::HelveticaBold))->getResourceName();

// Define a graphics state for each alpha level we want to demonstrate.
$alphaStates = ['GS25' => 0.25, 'GS50' => 0.50, 'GS75' => 0.75, 'GS100' => 1.0];
foreach ($alphaStates as $name => $alpha) {
    $gs = new ExtGState();
    $gs->caLower = $alpha; // /ca — fill alpha
    $gs->ca = $alpha;      // /CA — stroke alpha
    $ref = $writer->register($gs);
    $page->corePage()->resources->addExtGState($name, $ref);
}

// Define a Multiply blend mode so overlapping circles mix.
$blend = new ExtGState();
$blend->bm = new \Phpdftk\Pdf\Core\PdfName('Multiply');
$page->corePage()->resources->addExtGState('GSMul', $writer->register($blend));

$cs = $writer->addContentStream($page);
$cs->beginText()->setFont($bold, 22)->moveTextPosition(72, 740)
    ->showText('Transparency & Blend Modes')->endText();

// Row 1 — four squares at increasing alpha over a striped background.
$cs->beginText()->setFont($body, 11)->moveTextPosition(72, 700)
    ->showText('Fill alpha 0.25 / 0.50 / 0.75 / 1.00 over a striped backdrop:')
    ->endText();

// Vertical stripes background
for ($x = 72; $x < 540; $x += 24) {
    $cs->setFillColorRGB(0.85, 0.85, 0.92);
    $cs->rectangle($x, 580, 12, 100)->fill();
}

$x = 90;
foreach ($alphaStates as $name => $_alpha) {
    $cs->saveGraphicsState();
    $cs->setGraphicsState($name);
    $cs->setFillColorRGB(0.85, 0.15, 0.35);
    $cs->rectangle($x, 590, 90, 80)->fill();
    $cs->restoreGraphicsState();
    $x += 115;
}

// Row 2 — three overlapping discs using a Multiply blend mode.
$cs->beginText()->setFont($body, 11)->moveTextPosition(72, 550)
    ->showText('Multiply blend mode mixing three CMYK-like primaries:')
    ->endText();

$disc = static function ($cs, float $cx, float $cy, float $r): void {
    // Approximate a circle with cubic Béziers (Kappa ≈ 0.5523).
    $k = 0.5523 * $r;
    $cs->moveTo($cx - $r, $cy);
    $cs->curveTo($cx - $r, $cy + $k, $cx - $k, $cy + $r, $cx, $cy + $r);
    $cs->curveTo($cx + $k, $cy + $r, $cx + $r, $cy + $k, $cx + $r, $cy);
    $cs->curveTo($cx + $r, $cy - $k, $cx + $k, $cy - $r, $cx, $cy - $r);
    $cs->curveTo($cx - $k, $cy - $r, $cx - $r, $cy - $k, $cx - $r, $cy);
    $cs->closePath();
    $cs->fill();
};

$cs->saveGraphicsState();
$cs->setGraphicsState('GSMul');

$cs->setFillColorRGB(0.95, 0.20, 0.20);
$disc($cs, 230, 380, 80);

$cs->setFillColorRGB(0.20, 0.75, 0.30);
$disc($cs, 300, 380, 80);

$cs->setFillColorRGB(0.20, 0.30, 0.95);
$disc($cs, 265, 320, 80);

$cs->restoreGraphicsState();

$writer->save('transparency.pdf');
// endregion

rename(__DIR__ . '/transparency.pdf', example_output_path('core/graphics/transparency.pdf'));
