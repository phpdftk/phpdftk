<?php

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

chdir(__DIR__);

// region: example
use Phpdftk\Pdf\Writer\Pdf;
use Phpdftk\Pdf\Writer\TextStyle;

$pdf = new Pdf();
$pdf->setTitle('Inline links demo');
$pdf->addHeading('Inline links', 1);

$pdf->addText(
    'Mixing plain paragraphs with linked paragraphs is as simple as passing a TextStyle '
    . 'with the link field set. Each wrapped line of the linked text gets its own link '
    . 'annotation, so the whole block is clickable.',
);

$pdf->addText(
    'Click this paragraph to visit phpdftk.dev — the official documentation site for the project.',
    new TextStyle(
        color: [0.1, 0.4, 0.8],
        link: 'https://phpdftk.dev/',
    ),
);

$pdf->addText('Back to plain text in between.');

$pdf->addText(
    'Long linked paragraphs wrap naturally and the entire wrapped region is clickable. '
    . 'Every line that the paragraph occupies is covered by its own annotation rectangle, '
    . 'including continuation lines on the next page when the paragraph spans a page break.',
    new TextStyle(
        bold: true,
        color: [0.6, 0.0, 0.0],
        link: 'https://example.com/long-url-here',
    ),
);

$pdf->save('inline-links.pdf');
// endregion

rename(__DIR__ . '/inline-links.pdf', example_output_path('writer/inline-links.pdf'));
