<?php

declare(strict_types=1);

require_once __DIR__ . '/../../bootstrap.php';

chdir(__DIR__);

// region: example
use Phpdftk\Pdf\Core\Font\StandardFont;
use Phpdftk\Pdf\Core\Font\TrueTypeFont;
use Phpdftk\Pdf\Core\Font\Type1Font;
use Phpdftk\Pdf\Writer\PdfWriter;

// TrueTypeFont::fromFile() reads a .ttf, parses widths and metrics, and registers it as
// a simple (non-composite) PDF font. Calling addFont() embeds and subsets the program
// so only the glyphs needed for WinAnsi-encoded text are written into the file.
$writer = new PdfWriter();
$page = $writer->addPage();
$caption = $writer->addFont(new Type1Font(StandardFont::HelveticaBold))->getResourceName();

$fontPath = __DIR__ . '/../../../vendor/mpdf/mpdf/ttfonts/DejaVuSerif.ttf';
$dejavu = TrueTypeFont::fromFile($fontPath);
$dejavuName = $writer->addFont($dejavu)->getResourceName();

$cs = $writer->addContentStream($page);
$cs->beginText()->setFont($caption, 22)->moveTextPosition(72, 740)
    ->showText('TrueType embedding & subsetting')->endText();

$lines = [
    [700, 'DejaVu Serif at 28pt — the font program is embedded.', 28],
    [650, 'Only the WinAnsi-mapped glyphs you use are written.',    18],
    [620, 'Smaller files than a full font, identical fidelity.',     18],
    [580, '"Lorem ipsum dolor sit amet, consectetur adipiscing."',  14],
    [560, 'The quick brown fox jumps over the lazy dog.',           14],
    [540, '0 1 2 3 4 5 6 7 8 9 - $ % & @ # ! ? * +',                 14],
];

foreach ($lines as [$y, $text, $size]) {
    $cs->beginText()->setFont($dejavuName, $size)->moveTextPosition(72, $y)
        ->showText($text)->endText();
}

$writer->save('truetype-subset.pdf');
// endregion

rename(__DIR__ . '/truetype-subset.pdf', example_output_path('core/fonts/truetype-subset.pdf'));
