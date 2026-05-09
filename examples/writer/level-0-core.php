<?php

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

chdir(__DIR__);

// region: example
use Phpdftk\Pdf\Core\Content\ContentStream;
use Phpdftk\Pdf\Core\Content\Resources;
use Phpdftk\Pdf\Core\Document\Catalog;
use Phpdftk\Pdf\Core\Document\Page;
use Phpdftk\Pdf\Core\Document\PageTree;
use Phpdftk\Pdf\Core\File\PdfFileWriter;
use Phpdftk\Pdf\Core\Font\StandardFont;
use Phpdftk\Pdf\Core\Font\Type1Font;
use Phpdftk\Pdf\Core\PdfArray;
use Phpdftk\Pdf\Core\PdfNumber;
use Phpdftk\Pdf\Core\PdfReference;

$fw = new PdfFileWriter();

$catalog = new Catalog();
$fw->setCatalog($catalog);

$pageTree = new PageTree();
$fw->register($pageTree);
$catalog->pages = new PdfReference($pageTree->objectNumber);

$font = new Type1Font(StandardFont::Helvetica);
$fw->register($font);

$page = new Page();
$page->mediaBox = new PdfArray([
    new PdfNumber(0), new PdfNumber(0), new PdfNumber(612), new PdfNumber(792),
]);

$cs = new ContentStream();
$cs->beginText()
    ->setFont('F1', 18)
    ->moveTextPosition(72, 720)
    ->showText('Bare-metal PDF')
    ->endText();
$fw->register($cs);

$resources = new Resources();
$resources->addFont('F1', new PdfReference($font->objectNumber));

$page->contents = [new PdfReference($cs->objectNumber)];
$page->resources = $resources;
$fw->register($page);

$pageTree->kids = [new PdfReference($page->objectNumber)];
$pageTree->count = 1;

file_put_contents('bare-metal.pdf', $fw->generate());
// endregion

rename(__DIR__ . '/bare-metal.pdf', example_output_path('writer/level-0-core.pdf'));
