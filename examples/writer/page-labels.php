<?php

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

chdir(__DIR__);

// region: example
use Phpdftk\Pdf\Core\Document\PageLabel;
use Phpdftk\Pdf\Core\Font\StandardFont;
use Phpdftk\Pdf\Core\Font\Type1Font;
use Phpdftk\Pdf\Core\PdfName;
use Phpdftk\Pdf\Core\PdfString;
use Phpdftk\Pdf\Writer\PdfWriter;

$writer = new PdfWriter();
$body  = $writer->addFont(new Type1Font(StandardFont::Helvetica))->getResourceName();
$bold  = $writer->addFont(new Type1Font(StandardFont::HelveticaBold))->getResourceName();

$toRoman = static function (int $n): string {
    $map = ['m' => 1000, 'cm' => 900, 'd' => 500, 'cd' => 400, 'c' => 100, 'xc' => 90,
            'l' => 50, 'xl' => 40, 'x' => 10, 'ix' => 9, 'v' => 5, 'iv' => 4, 'i' => 1];
    $out = '';
    foreach ($map as $r => $v) {
        while ($n >= $v) { $out .= $r; $n -= $v; }
    }
    return $out;
};

// 3 front-matter pages numbered i, ii, iii
for ($i = 1; $i <= 3; $i++) {
    $page = $writer->addPage();
    $cs = $writer->addContentStream($page);
    $cs->beginText()->setFont($bold, 20)->moveTextPosition(72, 720)
        ->showText('Front Matter')->endText();
    $cs->beginText()->setFont($body, 11)->moveTextPosition(72, 690)
        ->showText('Table of contents, preface, acknowledgements.')->endText();
    $cs->beginText()->setFont($body, 10)->moveTextPosition(296, 36)
        ->showText($toRoman($i))->endText();
}

// 5 main-content pages numbered 1..5
for ($i = 1; $i <= 5; $i++) {
    $page = $writer->addPage();
    $cs = $writer->addContentStream($page);
    $cs->beginText()->setFont($bold, 18)->moveTextPosition(72, 720)
        ->showText(sprintf('Chapter %d', $i))->endText();
    $cs->beginText()->setFont($body, 11)->moveTextPosition(72, 690)
        ->showText('Lorem ipsum dolor sit amet, consectetur adipiscing elit.')->endText();
    $cs->beginText()->setFont($body, 10)->moveTextPosition(296, 36)
        ->showText((string) $i)->endText();
}

// 2 appendix pages with "App-A", "App-B"
for ($i = 0; $i < 2; $i++) {
    $page = $writer->addPage();
    $cs = $writer->addContentStream($page);
    $cs->beginText()->setFont($bold, 16)->moveTextPosition(72, 720)
        ->showText(sprintf('Appendix %s', chr(65 + $i)))->endText();
    $cs->beginText()->setFont($body, 11)->moveTextPosition(72, 690)
        ->showText('Supplementary material and references.')->endText();
    $cs->beginText()->setFont($body, 10)->moveTextPosition(290, 36)
        ->showText('App-' . chr(65 + $i))->endText();
}

// Wire up the three label ranges
$frontMatter = new PageLabel();
$frontMatter->s = new PdfName('r'); // lowercase roman

$mainContent = new PageLabel();
$mainContent->s = new PdfName('D'); // decimal

$appendix = new PageLabel();
$appendix->s = new PdfName('A'); // uppercase alpha
$appendix->p = new PdfString('App-');

$writer->setPageLabels([
    0 => $frontMatter, // pages 0-2 → i, ii, iii
    3 => $mainContent, // pages 3-7 → 1..5
    8 => $appendix,    // pages 8-9 → App-A, App-B
]);

$writer->save('page-labels.pdf');
// endregion

rename(__DIR__ . '/page-labels.pdf', example_output_path('writer/page-labels.pdf'));
