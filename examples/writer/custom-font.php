<?php

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

chdir(__DIR__);

// region: example
use Phpdftk\FontParser\TrueTypeParser;
use Phpdftk\Pdf\Core\Font\StandardFont;
use Phpdftk\Pdf\Core\Font\Type1Font;
use Phpdftk\Pdf\Writer\PdfWriter;

// Any TTF on disk works. DejaVu ships with mpdf and has broad Unicode coverage,
// so it's a convenient choice for showcase examples that run anywhere.
$fontPath = __DIR__ . '/../../vendor/mpdf/mpdf/ttfonts/DejaVuSans.ttf';
$ttData = (new TrueTypeParser($fontPath))->parse();

$writer = new PdfWriter();
$page   = $writer->addPage();

// Headline in a built-in standard font for contrast.
$caption = $writer->addFont(new Type1Font(StandardFont::HelveticaBold))->getResourceName();

// Embed DejaVu Sans as a Type 0 (CID) font, subset to just the glyphs we use.
$sample = "Custom font subsetting — embedded DejaVu Sans with accented Latin: "
       . "café · résumé · naïve · jalapeño · København · groß";
$codepoints = array_values(array_unique(array_map('mb_ord', mb_str_split($sample))));
$dejavu = $writer->addCompositeFont($ttData, $codepoints);
$dejavuName = $dejavu->getResourceName();

// CID fonts use hex glyph IDs in the content stream rather than literal text.
// Use the post-subset Unicode → GID map from the font handle; the map on
// the parsed font data points at glyphs that no longer exist in the subset.
$unicodeToGid = $dejavu->getUnicodeToGidMap();
$gidHex = '';
foreach (mb_str_split($sample) as $char) {
    $gid = $unicodeToGid[mb_ord($char)] ?? 0;
    $gidHex .= sprintf('%04X', $gid);
}

$cs = $writer->addContentStream($page);
$cs->beginText()
    ->setFont($caption, 18)
    ->moveTextPosition(72, 720)
    ->showText('Custom Font Embedding')
    ->endText();

$cs->beginText()
    ->setFont($dejavuName, 14)
    ->moveTextPosition(72, 680)
    ->showTextHex($gidHex)
    ->endText();

$writer->save('custom-font.pdf');
// endregion

rename(__DIR__ . '/custom-font.pdf', example_output_path('writer/custom-font.pdf'));
