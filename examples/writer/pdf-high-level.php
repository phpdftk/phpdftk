<?php

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

chdir(__DIR__);

// region: example
use Phpdftk\Pdf\Writer\Alignment;
use Phpdftk\Pdf\Writer\Pdf;
use Phpdftk\Pdf\Writer\TextStyle;

$pdf = new Pdf();

$pdf->addHeading('Annual Report', 1);
$pdf->addText('Fiscal year 2026 was a year of disciplined growth across every line of business.');
$pdf->addSpacer(12);

$pdf->addHeading('Revenue', 2);
$pdf->addText('Total revenue reached $4.2M, up 23% year over year.');
$pdf->addSpacer(8);
$pdf->addRule();
$pdf->addSpacer(8);

$pdf->addText(
    'Prepared by Finance · Confidential',
    new TextStyle(alignment: Alignment::Center, italic: true),
);

$pdf->save('annual-report.pdf');
// endregion

rename(__DIR__ . '/annual-report.pdf', example_output_path('writer/pdf-high-level.pdf'));
