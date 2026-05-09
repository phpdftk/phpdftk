<?php

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

chdir(__DIR__);

// region: example
use Phpdftk\Pdf\Core\Font\StandardFont;
use Phpdftk\Pdf\Core\Font\Type1Font;
use Phpdftk\Pdf\Writer\PdfWriter;

$writer = new PdfWriter();

$page = $writer->addPage(612, 792);                          // US Letter, 8.5 x 11 in
$font = $writer->addFont(new Type1Font(StandardFont::Helvetica));

$cs = $writer->addContentStream($page);
$cs->beginText()
    ->setFont($font->getResourceName(), 24)
    ->moveTextPosition(72, 720)
    ->showText('Quarterly Report')
    ->endText();

$cs->beginText()
    ->setFont($font->getResourceName(), 12)
    ->moveTextPosition(72, 690)
    ->showText('Q4 2026 — prepared by Finance')
    ->endText();

$writer->save('quarterly-report.pdf');
// endregion

rename(__DIR__ . '/quarterly-report.pdf', example_output_path('writer/level-1-pdfwriter.pdf'));
