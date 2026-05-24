<?php

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

chdir(__DIR__);

// region: example
use Phpdftk\Pdf\Writer\Pdf;

$pdf = new Pdf();
$pdf->setTitle('Multi-column layout demo');

// Single-column intro for the heading + abstract.
$pdf->addHeading('Multi-column layout', 1);
$pdf->addText(
    'When setColumns(N, gutter) is active, body content fills the current '
    . 'column from top to bottom, then advances to the next column. A page '
    . 'break only happens after the last column on a page overflows.',
);

// Switch to two columns for the body.
$pdf->setColumns(2, gutter: 16.0);

$pdf->addHeading('Two-column body', 2);

$paragraph = 'The quick brown fox jumps over the lazy dog while a thoughtful '
    . 'narrator describes the scene in tremendous detail. Lorem ipsum dolor '
    . 'sit amet, consectetur adipiscing elit. Praesent vitae quam vel sapien '
    . 'ornare suscipit. Curabitur eu nulla sit amet ipsum facilisis dapibus.';

for ($i = 1; $i <= 8; $i++) {
    $pdf->addText("Paragraph {$i} — {$paragraph}");
}

// Back to a single column for the closing note.
$pdf->setColumns(1);
$pdf->addHeading('Single-column footer', 2);
$pdf->addText(
    'Calling setColumns(1) restores the full-width body for any further content. '
    . 'Headers, footers and watermarks always render on the full page, '
    . 'regardless of the body\'s column configuration.',
);

$pdf->save('multi-column.pdf');
// endregion

rename(__DIR__ . '/multi-column.pdf', example_output_path('writer/multi-column.pdf'));
