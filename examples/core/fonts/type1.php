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

// The 14 standard Type1 fonts are always available — every PDF viewer ships them.
// They cost zero bytes in the file and are perfect for prose, code, and tabular data
// when you don't need a custom typeface.
$specimens = [
    [StandardFont::Helvetica,        'Helvetica - the workhorse sans-serif'],
    [StandardFont::HelveticaBold,    'Helvetica-Bold - a bolder workhorse'],
    [StandardFont::HelveticaOblique, 'Helvetica-Oblique - italicised'],
    [StandardFont::TimesRoman,       'Times-Roman - a serif for body copy'],
    [StandardFont::TimesBold,        'Times-Bold - a bolder serif'],
    [StandardFont::TimesItalic,      'Times-Italic - the cursive serif'],
    [StandardFont::Courier,          'Courier - monospace for code 0123456789'],
    [StandardFont::CourierBold,      'Courier-Bold - bolder monospace'],
];

$y = 740;
foreach ($specimens as [$face, $caption]) {
    $name = $writer->addFont(new Type1Font($face))->getResourceName();
    $cs = $writer->addContentStream($page);
    $cs->beginText()->setFont($name, 16)->moveTextPosition(72, $y)
        ->showText($caption)->endText();
    $y -= 36;
}

$writer->save('type1.pdf');
// endregion

rename(__DIR__ . '/type1.pdf', example_output_path('core/fonts/type1.pdf'));
