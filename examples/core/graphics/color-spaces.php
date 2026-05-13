<?php

declare(strict_types=1);

require_once __DIR__ . '/../../bootstrap.php';

chdir(__DIR__);

// region: example
use Phpdftk\Pdf\Core\Font\StandardFont;
use Phpdftk\Pdf\Core\Font\Type1Font;
use Phpdftk\Pdf\Writer\PdfWriter;

$writer = new PdfWriter();
$page = $writer->addPage();
$body = $writer->addFont(new Type1Font(StandardFont::Helvetica));
$bold = $writer->addFont(new Type1Font(StandardFont::HelveticaBold));

$cs = $writer->addContentStream($page);
$cs->beginText()->setFont($bold, 22)->moveTextPosition(72, 740)
    ->showText('Device Color Spaces')->endText();

// ---- DeviceRGB ----------------------------------------------------------
$cs->beginText()->setFont($bold, 13)->moveTextPosition(72, 700)
    ->showText('DeviceRGB (additive — used for screens):')->endText();

$rgb = [
    [1.0, 0.0, 0.0, 'Red'],
    [0.0, 1.0, 0.0, 'Green'],
    [0.0, 0.0, 1.0, 'Blue'],
    [1.0, 1.0, 0.0, 'Yellow'],
    [1.0, 0.0, 1.0, 'Magenta'],
    [0.0, 1.0, 1.0, 'Cyan'],
];
$x = 72;
foreach ($rgb as [$r, $g, $b, $label]) {
    $cs->setFillColorRGB($r, $g, $b);
    $cs->rectangle($x, 620, 70, 50)->fill();
    $cs->setFillColorRGB(0, 0, 0);
    $cs->beginText()->setFont($body, 9)->moveTextPosition($x, 605)
        ->showText($label)->endText();
    $x += 78;
}

// ---- DeviceCMYK ---------------------------------------------------------
$cs->beginText()->setFont($bold, 13)->moveTextPosition(72, 560)
    ->showText('DeviceCMYK (subtractive — used for print):')->endText();

$cmyk = [
    [0.0, 0.0, 0.0, 0.0, 'White (0,0,0,0)'],
    [1.0, 0.0, 0.0, 0.0, 'Cyan'],
    [0.0, 1.0, 0.0, 0.0, 'Magenta'],
    [0.0, 0.0, 1.0, 0.0, 'Yellow'],
    [0.0, 0.0, 0.0, 1.0, 'Black'],
    [0.6, 0.4, 0.0, 0.0, 'Mid blue'],
];
$x = 72;
foreach ($cmyk as [$c, $m, $y, $k, $label]) {
    $cs->setFillColorCMYK($c, $m, $y, $k);
    $cs->rectangle($x, 480, 70, 50)->fill();
    $cs->setFillColorRGB(0, 0, 0);
    $cs->beginText()->setFont($body, 9)->moveTextPosition($x, 465)
        ->showText($label)->endText();
    $x += 78;
}

// ---- DeviceGray --------------------------------------------------------
$cs->beginText()->setFont($bold, 13)->moveTextPosition(72, 420)
    ->showText('DeviceGray ramp:')->endText();

for ($i = 0; $i <= 10; $i++) {
    $gray = $i / 10;
    $cs->setFillColorGray($gray);
    $cs->rectangle(72 + $i * 42, 340, 40, 60)->fill();
}

$writer->save('color-spaces.pdf');
// endregion

rename(__DIR__ . '/color-spaces.pdf', example_output_path('core/graphics/color-spaces.pdf'));
