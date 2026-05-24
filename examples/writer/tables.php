<?php

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

chdir(__DIR__);

// region: example
use Phpdftk\Pdf\Writer\Alignment;
use Phpdftk\Pdf\Writer\Pdf;
use Phpdftk\Pdf\Writer\TableStyle;

$pdf = new Pdf();
$pdf->setTitle('Tables demo');
$pdf->addHeading('Quarterly results', 1);
$pdf->addText('A simple table with a repeating header row, explicit column widths, and per-column alignment:');
$pdf->addSpacer(8);

$pdf->addTable(
    rows: [
        ['Q1 2026', 'Widgets',  '12,340',  '$148,080.00'],
        ['Q2 2026', 'Widgets',  '14,520',  '$174,240.00'],
        ['Q3 2026', 'Widgets',  '11,890',  '$142,680.00'],
        ['Q4 2026', 'Widgets',  '17,200',  '$206,400.00'],
        ['Q1 2026', 'Gadgets',   '3,210',   '$80,250.00'],
        ['Q2 2026', 'Gadgets',   '4,140',  '$103,500.00'],
        ['Q3 2026', 'Gadgets',   '3,990',   '$99,750.00'],
        ['Q4 2026', 'Gadgets',   '5,820',  '$145,500.00'],
    ],
    columnWidths: [80.0, 120.0, 100.0, 140.0],
    headerRow: ['Period', 'Product', 'Units', 'Revenue'],
    style: new TableStyle(
        cellAlignments: [Alignment::Left, Alignment::Left, Alignment::Right, Alignment::Right],
    ),
);

$pdf->addSpacer(16);
$pdf->addHeading('Long content auto-wraps', 2);
$pdf->addText('Cells that overflow the column width wrap onto multiple lines, and the row grows to fit the tallest cell:');
$pdf->addSpacer(8);

$pdf->addTable(
    rows: [
        ['Short', 'Cells stay on a single line when they fit.'],
        ['Longer cell', 'This cell contains enough text that it overflows the narrower column and wraps onto a second and likely third line.'],
        ['Multi-paragraph', "Explicit newlines are honoured too.\nThe second paragraph gets its own line break inside the cell."],
    ],
    columnWidths: [100.0, 320.0],
);

$pdf->save('tables.pdf');
// endregion

rename(__DIR__ . '/tables.pdf', example_output_path('writer/tables.pdf'));
