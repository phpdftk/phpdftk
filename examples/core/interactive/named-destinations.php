<?php

declare(strict_types=1);

require_once __DIR__ . '/../../bootstrap.php';

chdir(__DIR__);

// region: example
use Phpdftk\Pdf\Core\Annotation\LinkAnnotation;
use Phpdftk\Pdf\Core\Document\Destination;
use Phpdftk\Pdf\Core\Font\StandardFont;
use Phpdftk\Pdf\Core\Font\Type1Font;
use Phpdftk\Pdf\Core\PdfArray;
use Phpdftk\Pdf\Core\PdfDictionary;
use Phpdftk\Pdf\Core\PdfName;
use Phpdftk\Pdf\Core\PdfNumber;
use Phpdftk\Pdf\Core\PdfReference;
use Phpdftk\Pdf\Writer\PdfWriter;

$writer = new PdfWriter();
$body = $writer->addFont(new Type1Font(StandardFont::Helvetica))->getResourceName();
$bold = $writer->addFont(new Type1Font(StandardFont::HelveticaBold))->getResourceName();

// Build three target pages, each with a heading.
$chapters = [
    'introduction' => 'Introduction',
    'methodology'  => 'Methodology',
    'results'      => 'Results',
];
$pageRefs = [];
foreach ($chapters as $key => $title) {
    $page = $writer->addPage();
    $pageRefs[$key] = new PdfReference($page->corePage()->objectNumber);

    $cs = $writer->addContentStream($page);
    $cs->beginText()->setFont($bold, 24)->moveTextPosition(72, 720)
        ->showText($title)->endText();
    $cs->beginText()->setFont($body, 11)->moveTextPosition(72, 690)
        ->showText('You jumped here via a named destination.')->endText();
}

// Register a named destination per chapter — all use FitH (fit width to top of page).
$destinations = [];
foreach ($pageRefs as $key => $ref) {
    $destinations[$key] = Destination::fitH($ref, 792.0);
}
$writer->setNamedDestinations($destinations);

// Insert a table-of-contents page at position 0 (move it before the others).
$toc = $writer->addPage();
// addPage appends — re-order so the TOC is first.
$pageTree = $writer->getPageTree();
$tocRef = array_pop($pageTree->kids);
array_unshift($pageTree->kids, $tocRef);

$cs = $writer->addContentStream($toc);
$cs->beginText()->setFont($bold, 22)->moveTextPosition(72, 740)
    ->showText('Table of Contents')->endText();
$cs->beginText()->setFont($body, 11)->moveTextPosition(72, 706)
    ->showText('Each link below jumps to a named destination.')->endText();

$y = 660;
foreach ($chapters as $key => $title) {
    $cs->beginText()->setFont($body, 14)->moveTextPosition(72, $y)
        ->showText('→ ' . $title)->endText();

    $link = new LinkAnnotation(new PdfArray([
        new PdfNumber(72),  new PdfNumber($y - 4),
        new PdfNumber(300), new PdfNumber($y + 14),
    ]));
    $link->h = new PdfName('I');
    // /A action with /D pointing at the named destination string.
    $link->a = new PdfDictionary([
        'Type' => new PdfName('Action'),
        'S'    => new PdfName('GoTo'),
        'D'    => new \Phpdftk\Pdf\Core\PdfString($key),
    ]);
    $writer->register($link);
    $toc->corePage()->annots[] = new PdfReference($link->objectNumber);

    $y -= 36;
}

$writer->save('named-destinations.pdf');
// endregion

rename(__DIR__ . '/named-destinations.pdf', example_output_path('core/interactive/named-destinations.pdf'));
