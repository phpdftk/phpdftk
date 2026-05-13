<?php

declare(strict_types=1);

require_once __DIR__ . '/../../bootstrap.php';

chdir(__DIR__);

// region: example
use Phpdftk\Pdf\Core\Content\Resources;
use Phpdftk\Pdf\Core\Font\StandardFont;
use Phpdftk\Pdf\Core\Font\Type1Font;
use Phpdftk\Pdf\Core\Graphics\Pattern\TilingPattern;
use Phpdftk\Pdf\Core\PdfArray;
use Phpdftk\Pdf\Core\PdfNumber;
use Phpdftk\Pdf\Writer\PdfWriter;

$writer = new PdfWriter();
$page = $writer->addPage();
$body = $writer->addFont(new Type1Font(StandardFont::Helvetica))->getResourceName();
$bold = $writer->addFont(new Type1Font(StandardFont::HelveticaBold))->getResourceName();

$bbox = static fn () => new PdfArray([
    new PdfNumber(0), new PdfNumber(0), new PdfNumber(20), new PdfNumber(20),
]);

// Checkerboard tile: two filled squares in a 20×20 cell.
$checker = new TilingPattern(
    paintType: 1,
    tilingType: 1,
    bbox: $bbox(),
    xStep: 20,
    yStep: 20,
    resources: new Resources(),
    contentStream:
        "0.92 0.96 1 rg\n0 0 20 20 re\nf\n" .
        "0.20 0.36 0.85 rg\n0 0 10 10 re\nf\n" .
        "0.20 0.36 0.85 rg\n10 10 10 10 re\nf\n",
);

// Dot grid tile: a single small filled circle (approximated by curves).
$dots = new TilingPattern(
    paintType: 1,
    tilingType: 1,
    bbox: $bbox(),
    xStep: 20,
    yStep: 20,
    resources: new Resources(),
    contentStream:
        "1 1 1 rg\n0 0 20 20 re\nf\n" .
        "0.85 0.20 0.36 rg\n" .
        "10 6 m " .
        "12.21 6 14 7.79 14 10 c " .
        "14 12.21 12.21 14 10 14 c " .
        "7.79 14 6 12.21 6 10 c " .
        "6 7.79 7.79 6 10 6 c f\n",
);

$checkerRef = $writer->register($checker);
$dotsRef    = $writer->register($dots);

$page->corePage()->resources->pattern = [
    'P1' => $checkerRef,
    'P2' => $dotsRef,
];

$cs = $writer->addContentStream($page);
$cs->beginText()->setFont($bold, 22)->moveTextPosition(72, 740)
    ->showText('Tiling Patterns')->endText();

$cs->beginText()->setFont($body, 11)->moveTextPosition(72, 700)
    ->showText('Checkerboard pattern (paintType 1, 20×20 cell):')
    ->endText();
$cs->raw('/Pattern cs');
$cs->raw('/P1 scn');
$cs->rectangle(72, 480, 468, 200)->fill();

$cs->beginText()->setFont($body, 11)->moveTextPosition(72, 440)
    ->showText('Dot-grid pattern (curves approximating circles):')
    ->endText();
$cs->raw('/Pattern cs');
$cs->raw('/P2 scn');
$cs->rectangle(72, 220, 468, 200)->fill();

$writer->save('patterns.pdf');
// endregion

rename(__DIR__ . '/patterns.pdf', example_output_path('core/graphics/patterns.pdf'));
