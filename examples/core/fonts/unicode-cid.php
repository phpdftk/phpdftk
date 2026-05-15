<?php

declare(strict_types=1);

require_once __DIR__ . '/../../bootstrap.php';

chdir(__DIR__);

// region: example
use Phpdftk\FontParser\TrueTypeParser;
use Phpdftk\Pdf\Core\Font\StandardFont;
use Phpdftk\Pdf\Core\Font\Type1Font;
use Phpdftk\Pdf\Writer\PdfWriter;

// Type 0 (composite) fonts let you write any Unicode character a TTF font supports
// — no encoding tricks, no WinAnsi limitations. The font is automatically subset
// so only the glyphs you actually use are embedded.
$writer = new PdfWriter();
$page = $writer->addPage();
$caption = $writer->addFont(new Type1Font(StandardFont::HelveticaBold))->getResourceName();

$fontPath = __DIR__ . '/../../../vendor/mpdf/mpdf/ttfonts/DejaVuSans.ttf';
$ttData = (new TrueTypeParser($fontPath))->parse();

$samples = [
    'Latin Extended:  café · résumé · naïve · jalapeño · København',
    'Cyrillic:        Привет, мир! Я люблю PDFs.',
    'Greek:           Καλημέρα κόσμε — αβγδεζηθ',
    'Mathematical:    ∑ x²  ≤  ∫ f(x) dx  ·  π ≈ 3.14159  ·  ∞ ≠ 0',
    'Symbols:         © ® ™ § ¶ † ‡ • ‰ ‹ › « »  ←↑→↓ ↔',
];

// Collect every codepoint actually used across all samples so the embedded font
// is subset to exactly those glyphs.
$allText = implode(' ', $samples);
$codepoints = array_values(array_unique(array_map('mb_ord', mb_str_split($allText))));

$dejavu = $writer->addCompositeFont($ttData, $codepoints);
$dejavuName = $dejavu->getResourceName();

// Post-subset Unicode → GID map from the font handle. Subsetting renumbers
// the kept glyphs, so the original $ttData->fullUnicodeToGid points at
// the wrong slots in the embedded font.
$unicodeToGid = $dejavu->getUnicodeToGidMap();

$encodeHex = static function (string $text, array $unicodeToGid): string {
    $hex = '';
    foreach (mb_str_split($text) as $char) {
        $gid = $unicodeToGid[mb_ord($char)] ?? 0;
        $hex .= sprintf('%04X', $gid);
    }
    return $hex;
};

$cs = $writer->addContentStream($page);
$cs->beginText()->setFont($caption, 22)->moveTextPosition(72, 740)
    ->showText('Unicode via Type 0 (CID) fonts')->endText();

$y = 690;
foreach ($samples as $line) {
    $cs->beginText()->setFont($dejavuName, 14)->moveTextPosition(72, $y)
        ->showTextHex($encodeHex($line, $unicodeToGid))->endText();
    $y -= 40;
}

$writer->save('unicode-cid.pdf');
// endregion

rename(__DIR__ . '/unicode-cid.pdf', example_output_path('core/fonts/unicode-cid.pdf'));
