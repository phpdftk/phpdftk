<?php

declare(strict_types=1);

require_once __DIR__ . '/../../bootstrap.php';

chdir(__DIR__);

// region: example
use Phpdftk\Pdf\Core\Document\NameTree;
use Phpdftk\Pdf\Core\Document\NamesDictionary;
use Phpdftk\Pdf\Core\FileSpec\EmbeddedFile;
use Phpdftk\Pdf\Core\FileSpec\FileSpec;
use Phpdftk\Pdf\Core\Font\StandardFont;
use Phpdftk\Pdf\Core\Font\Type1Font;
use Phpdftk\Pdf\Core\PdfArray;
use Phpdftk\Pdf\Core\PdfReference;
use Phpdftk\Pdf\Core\PdfString;
use Phpdftk\Pdf\Writer\PdfWriter;

// FileAttachment annotations expose embedded files at fixed page locations.
// /Names → /EmbeddedFiles makes the same files available document-wide —
// they show up in any viewer's "Attachments" panel without taking page space.
$writer = new PdfWriter();
$page = $writer->addPage();
$body = $writer->addFont(new Type1Font(StandardFont::Helvetica))->getResourceName();
$bold = $writer->addFont(new Type1Font(StandardFont::HelveticaBold))->getResourceName();

$cs = $writer->addContentStream($page);
$cs->beginText()->setFont($bold, 22)->moveTextPosition(72, 740)
    ->showText('Embedded Files (Attachments Panel)')->endText();
$cs->beginText()->setFont($body, 12)->moveTextPosition(72, 706)
    ->showText('Open the Attachments panel in any viewer to download the files below.')
    ->endText();

$attachments = [
    ['hello.txt',   'Plain-text greeting',  "Hello from inside the PDF.\n"],
    ['report.csv',  'CSV report data',      "month,visits\nJan,1240\nFeb,1430\nMar,1700\n"],
    ['config.json', 'Sample configuration', json_encode(['version' => '1.0', 'mode' => 'demo'], JSON_PRETTY_PRINT)],
];

// Build the names tree: an alternating array of name strings and FileSpec references.
$namesArray = [];
$y = 660;
foreach ($attachments as [$filename, $description, $payload]) {
    $embedded = new EmbeddedFile($payload);
    $writer->register($embedded);

    $fs = new FileSpec($filename);
    $fs->desc = new PdfString($description);
    $fs->attachEmbeddedFile(new PdfReference($embedded->objectNumber));
    $writer->register($fs);

    $namesArray[] = new PdfString($filename);
    $namesArray[] = new PdfReference($fs->objectNumber);

    // Render a line on the page so the viewer also sees the file inventory.
    $cs->beginText()->setFont($bold, 12)->moveTextPosition(72, $y)
        ->showText($filename)->endText();
    $cs->beginText()->setFont($body, 12)->moveTextPosition(220, $y)
        ->showText($description)->endText();
    $y -= 24;
}

// Wire the embedded-files name tree into the catalog's /Names dictionary.
$nameTree = new NameTree();
$nameTree->names = new PdfArray($namesArray);
$writer->register($nameTree);

$names = new NamesDictionary();
$names->embeddedFiles = new PdfReference($nameTree->objectNumber);
$writer->register($names);

$writer->getCatalog()->names = new PdfReference($names->objectNumber);

$writer->save('embedded-file.pdf');
// endregion

rename(__DIR__ . '/embedded-file.pdf', example_output_path('core/multimedia/embedded-file.pdf'));
