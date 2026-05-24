<?php

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

chdir(__DIR__);

// region: example
use Phpdftk\Geometry\Rectangle;
use Phpdftk\Pdf\Core\Annotation\BorderStyle;
use Phpdftk\Pdf\Core\Document\Destination;
use Phpdftk\Pdf\Core\Font\StandardFont;
use Phpdftk\Pdf\Core\Font\Type1Font;
use Phpdftk\Pdf\Core\PdfName;
use Phpdftk\Pdf\Core\PdfNumber;
use Phpdftk\Pdf\Core\PdfReference;
use Phpdftk\Pdf\Writer\PdfDoc;

$doc = new PdfDoc();

// Fluent metadata setters create the /Info dict lazily.
$doc->setTitle('PdfDoc demo')
    ->setAuthor('phpdftk')
    ->setSubject('Level 2 — friendly catalog API')
    ->setKeywords('pdfdoc, level 2, links, metadata')
    ->setCreator('examples/writer/pdf-doc.php');

// Mirror Info into XMP — required for PDF/A and good practice anywhere.
$doc->syncInfoToMetadata();

// Register a font on the underlying PdfWriter so the two pages have
// something to render with.
$writer = $doc->writer();
$bodyFont = $writer->addFont(new Type1Font(StandardFont::Helvetica));
$boldFont = $writer->addFont(new Type1Font(StandardFont::HelveticaBold));

// ---- Page 1 — outbound URI link ----
$cover = $doc->addPage();
$cover->drawText('PdfDoc — friendly catalog API', 72.0, 720.0, $boldFont, 22.0);
$cover->drawText('Click the box below to visit the project homepage.', 72.0, 680.0, $bodyFont, 12.0);

$uriRect = new Rectangle(72.0, 640.0, 200.0, 18.0);
$border = new BorderStyle();
$border->s = new PdfName('S'); // solid
$border->w = new PdfNumber(0.75);

$doc->addLink($cover, $uriRect, 'https://phpdftk.dev/', $border);
$cover->drawText('phpdftk.dev', 78.0, 645.0, $bodyFont, 12.0);

// Internal navigation: link cover → details page via an inline Destination.
$detailsPage = $doc->addPage();
$detailsRef = new PdfReference($detailsPage->corePage()->objectNumber);
$internalRect = new Rectangle(72.0, 600.0, 200.0, 18.0);
$doc->addLink($cover, $internalRect, Destination::fit($detailsRef));
$cover->drawText('Jump to page 2 →', 78.0, 605.0, $bodyFont, 12.0);

// ---- Page 2 — destination of the internal link ----
$detailsPage->drawText('Page 2 — details', 72.0, 720.0, $boldFont, 22.0);
$detailsPage->drawText(
    'This page was opened via an inline /Dest array on the link annotation.',
    72.0,
    680.0,
    $bodyFont,
    12.0,
);

$doc->writer()->save('pdf-doc.pdf');
// endregion

rename(__DIR__ . '/pdf-doc.pdf', example_output_path('writer/pdf-doc.pdf'));
