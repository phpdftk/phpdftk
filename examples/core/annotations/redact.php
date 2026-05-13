<?php

declare(strict_types=1);

require_once __DIR__ . '/../../bootstrap.php';

chdir(__DIR__);

// region: example
use Phpdftk\Pdf\Core\Annotation\RedactAnnotation;
use Phpdftk\Pdf\Core\Font\StandardFont;
use Phpdftk\Pdf\Core\Font\Type1Font;
use Phpdftk\Pdf\Core\PdfArray;
use Phpdftk\Pdf\Core\PdfNumber;
use Phpdftk\Pdf\Core\PdfReference;
use Phpdftk\Pdf\Core\PdfString;
use Phpdftk\Pdf\Writer\PdfWriter;

$writer = new PdfWriter();
$page = $writer->addPage();
$body = $writer->addFont(new Type1Font(StandardFont::Helvetica))->getResourceName();
$bold = $writer->addFont(new Type1Font(StandardFont::HelveticaBold))->getResourceName();

$cs = $writer->addContentStream($page);
$cs->beginText()->setFont($bold, 22)->moveTextPosition(72, 740)
    ->showText('Redaction Annotations')->endText();
$cs->beginText()->setFont($body, 11)->moveTextPosition(72, 710)
    ->showText('Pending redactions are marked on the page. A redaction-aware viewer can')
    ->endText();
$cs->beginText()->setFont($body, 11)->moveTextPosition(72, 694)
    ->showText('apply them, replacing the underlying content with the overlay below.')
    ->endText();

// Sensitive paragraph that we want to flag for redaction.
$lines = [
    [640, 'Patient: John Doe — SSN 123-45-6789'],
    [620, 'Date of birth: 1979-03-14'],
    [600, 'Visit notes: routine checkup, all vitals nominal.'],
];
foreach ($lines as [$y, $text]) {
    $cs->beginText()->setFont($body, 12)->moveTextPosition(72, $y)
        ->showText($text)->endText();
}

// Mark the SSN and DOB as pending redactions with black fill and "REDACTED" overlay.
$targets = [
    ['rect' => [220, 636, 350, 652], 'overlay' => 'REDACTED'],
    ['rect' => [200, 616, 330, 632], 'overlay' => 'REDACTED'],
];

foreach ($targets as $target) {
    [$x1, $y1, $x2, $y2] = $target['rect'];
    $rect = new PdfArray([
        new PdfNumber($x1), new PdfNumber($y1),
        new PdfNumber($x2), new PdfNumber($y2),
    ]);
    $annot = new RedactAnnotation($rect);
    $annot->quadPoints = new PdfArray([
        new PdfNumber($x1), new PdfNumber($y2), new PdfNumber($x2), new PdfNumber($y2),
        new PdfNumber($x1), new PdfNumber($y1), new PdfNumber($x2), new PdfNumber($y1),
    ]);
    // Black fill so the redaction is visible before application.
    $annot->ic = new PdfArray([new PdfNumber(0), new PdfNumber(0), new PdfNumber(0)]);
    $annot->overlayText = new PdfString($target['overlay']);
    $annot->contents = new PdfString('Pending redaction — apply in a redaction-capable viewer.');
    $writer->register($annot);
    $page->corePage()->annots[] = new PdfReference($annot->objectNumber);
}

$writer->save('redact.pdf');
// endregion

rename(__DIR__ . '/redact.pdf', example_output_path('core/annotations/redact.pdf'));
