<?php

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

chdir(__DIR__);

// region: example
use Phpdftk\Pdf\Writer\Pdf;
use Phpdftk\Pdf\Writer\TextStyle;

$pdf = new Pdf();
$pdf->setTitle('Text decoration demo');

$pdf->addHeading('Text decoration', 1);

$pdf->addText('Plain body text for comparison.');

$pdf->addText('Underlined paragraph.', new TextStyle(underline: true));

$pdf->addText('Struck-through paragraph.', new TextStyle(strikethrough: true));

$pdf->addText(
    'A paragraph with both underline and strikethrough applied.',
    new TextStyle(underline: true, strikethrough: true),
);

$pdf->addText(
    'Decoration colour follows the text fill colour, so coloured paragraphs '
    . 'render coloured decoration lines too. Each wrapped line gets its own line.',
    new TextStyle(
        color: [0.1, 0.4, 0.8],
        underline: true,
    ),
);

$pdf->addText(
    'Decoration also works with the link field — the strike line is drawn over '
    . 'the same text region as the link annotation.',
    new TextStyle(
        link: 'https://example.com/',
        underline: true,
        color: [0.6, 0.0, 0.0],
    ),
);

$pdf->save('text-decoration.pdf');
// endregion

rename(__DIR__ . '/text-decoration.pdf', example_output_path('writer/text-decoration.pdf'));
