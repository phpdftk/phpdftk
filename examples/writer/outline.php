<?php

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

chdir(__DIR__);

// region: example
use Phpdftk\Pdf\Core\Document\Outline;
use Phpdftk\Pdf\Core\Document\OutlineItem;
use Phpdftk\Pdf\Core\Font\StandardFont;
use Phpdftk\Pdf\Core\Font\Type1Font;
use Phpdftk\Pdf\Core\PdfArray;
use Phpdftk\Pdf\Core\PdfName;
use Phpdftk\Pdf\Core\PdfNumber;
use Phpdftk\Pdf\Core\PdfReference;
use Phpdftk\Pdf\Writer\PdfWriter;

$writer = new PdfWriter();
$body = $writer->addFont(new Type1Font(StandardFont::Helvetica))->getResourceName();
$bold = $writer->addFont(new Type1Font(StandardFont::HelveticaBold))->getResourceName();

$chapters = ['Introduction', 'Background', 'Implementation', 'Conclusions'];

// One page per chapter — keep the page reference so the bookmarks can target it.
$pageRefs = [];
foreach ($chapters as $i => $title) {
    $page = $writer->addPage();
    $pageRefs[$i] = new PdfReference($page->corePage()->objectNumber);

    $cs = $writer->addContentStream($page);
    $cs->beginText()->setFont($bold, 22)->moveTextPosition(72, 720)
        ->showText($title)->endText();
    $cs->beginText()->setFont($body, 11)->moveTextPosition(72, 690)
        ->showText(sprintf('Chapter %d of %d.', $i + 1, count($chapters)))->endText();
}

// One OutlineItem per chapter, with an /XYZ destination pointing at the page.
$items = [];
foreach ($chapters as $i => $title) {
    $item = new OutlineItem($title);
    $item->dest = new PdfArray([
        $pageRefs[$i],
        new PdfName('XYZ'),
        new PdfNumber(0),
        new PdfNumber(792),
        new PdfNumber(0),
    ]);
    $writer->addOutlineItem($item); // assigns objectNumber
    $items[] = $item;
}

// Wire the doubly-linked sibling chain.
foreach ($items as $i => $item) {
    if ($i > 0) {
        $item->prev = new PdfReference($items[$i - 1]->objectNumber);
    }
    if ($i < count($items) - 1) {
        $item->next = new PdfReference($items[$i + 1]->objectNumber);
    }
}

// Register the Outline root and point every item's /Parent at it.
$outline = new Outline();
$writer->setOutline($outline);
$outline->first = new PdfReference($items[0]->objectNumber);
$outline->last  = new PdfReference(end($items)->objectNumber);
$outline->count = count($items);

foreach ($items as $item) {
    $item->parent = new PdfReference($outline->objectNumber);
}

$writer->save('outline.pdf');
// endregion

rename(__DIR__ . '/outline.pdf', example_output_path('writer/outline.pdf'));
