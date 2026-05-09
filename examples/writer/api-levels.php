<?php

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

chdir(__DIR__);

// All three sections produce the *same* visible output: a Letter-sized page
// with "Hello from phpdftk" near the top. The difference is in how much you
// have to do yourself.

// region: high-level
use Phpdftk\Pdf\Writer\Pdf;

$pdf = new Pdf();
$pdf->addHeading('Hello from phpdftk', 1);
$pdf->save('hello-high.pdf');
// endregion

// region: mid-level
use Phpdftk\Pdf\Core\Font\StandardFont;
use Phpdftk\Pdf\Core\Font\Type1Font;
use Phpdftk\Pdf\Writer\PdfWriter;

$writer = new PdfWriter();
$page = $writer->addPage(612, 792);
$font = $writer->addFont(new Type1Font(StandardFont::Helvetica));

$writer->addContentStream($page)
    ->beginText()
    ->setFont($font->getResourceName(), 24)
    ->moveTextPosition(72, 720)
    ->showText('Hello from phpdftk')
    ->endText();

$writer->save('hello-mid.pdf');
// endregion

// region: low-level
use Phpdftk\Pdf\Core\Content\ContentStream;
use Phpdftk\Pdf\Core\Content\Resources;
use Phpdftk\Pdf\Core\Document\Catalog;
use Phpdftk\Pdf\Core\Document\Page;
use Phpdftk\Pdf\Core\Document\PageTree;
use Phpdftk\Pdf\Core\File\PdfFileWriter;
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
$cs->beginText()->setFont('F1', 24)->moveTextPosition(72, 720)
    ->showText('Hello from phpdftk')->endText();
$fw->register($cs);

$resources = new Resources();
$resources->addFont('F1', new PdfReference($font->objectNumber));
$page->contents = [new PdfReference($cs->objectNumber)];
$page->resources = $resources;
$fw->register($page);

$pageTree->kids = [new PdfReference($page->objectNumber)];
$pageTree->count = 1;

file_put_contents('hello-low.pdf', $fw->generate());
// endregion

rename(__DIR__ . '/hello-high.pdf', example_output_path('writer/api-levels-high.pdf'));
rename(__DIR__ . '/hello-mid.pdf', example_output_path('writer/api-levels-mid.pdf'));
rename(__DIR__ . '/hello-low.pdf', example_output_path('writer/api-levels-low.pdf'));
