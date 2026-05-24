<?php

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

chdir(__DIR__);

// region: example
use Phpdftk\Pdf\Writer\ListStyle;
use Phpdftk\Pdf\Writer\Pdf;

$pdf = new Pdf();
$pdf->setTitle('Lists demo');

$pdf->addHeading('Bullet list', 2);
$pdf->addList([
    'Espresso — concentrated extraction under pressure',
    'Pour-over — slow gravity drip through a paper filter',
    'French press — full-immersion brew with a metal mesh',
    'Cold brew — long extraction in cold water, low acidity',
]);

$pdf->addHeading('Numbered list', 2);
$pdf->addNumberedList([
    'Weigh the beans (15g for a single shot).',
    'Grind to fine consistency.',
    'Tamp with consistent pressure.',
    'Pull the shot in roughly 25 to 30 seconds.',
]);

$pdf->addHeading('Customized markers', 2);
$pdf->addText('Lists accept a ListStyle to override indent, bullet glyphs, item spacing, and the numbering suffix:');
$pdf->addSpacer(6);

$pdf->addNumberedList(
    [
        'First option',
        'Second option',
        'Third option',
    ],
    new ListStyle(
        indent: 24.0,
        itemSpacing: 6.0,
        numberSuffix: ')',
    ),
);

$pdf->addHeading('Wrapping long items', 2);
$pdf->addList([
    'Short item.',
    'A much longer item that contains enough text to overflow the available column width, '
    . 'causing the renderer to wrap it onto subsequent lines while keeping the bullet at the '
    . 'item start position and indenting the continuation lines to align with the first line of text.',
    'Another short item to show how the gap between items respects ListStyle::itemSpacing.',
]);

$pdf->save('lists.pdf');
// endregion

rename(__DIR__ . '/lists.pdf', example_output_path('writer/lists.pdf'));
