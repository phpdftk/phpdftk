<?php

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

chdir(__DIR__);

// region: example
use Phpdftk\Pdf\Writer\Pdf;
use Phpdftk\Pdf\Writer\TextStyle;
use Phpdftk\Pdf\Writer\Theme;

$pdf = new Pdf();
$pdf->setTitle('Blockquote demo');

$pdf->addHeading('Blockquotes', 1);

$pdf->addText('Default blockquote — italic body, indented under a light-grey bar:');
$pdf->addSpacer(4);

$pdf->addQuote(
    'The greatest enemy of knowledge is not ignorance — it is the illusion of knowledge.',
);

$pdf->addSpacer(8);
$pdf->addText('Long quotes wrap naturally at the column width:');
$pdf->addSpacer(4);

$pdf->addQuote(
    'When you have eliminated the impossible, whatever remains, however improbable, must be the truth. '
    . 'This is the principle that underlies all reasonable investigation, and it is one that any honest '
    . 'observer will return to repeatedly when the facts begin to seem strange or contradictory.',
);

$pdf->addSpacer(8);
$pdf->addText('Override font / colour / alignment via TextStyle (italic stays the default unless you set it explicitly):');
$pdf->addSpacer(4);

$pdf->addQuote(
    'Plain upright body, in a muted colour — sometimes you want the visual signature of a quote without italic.',
    new TextStyle(italic: false, color: [0.3, 0.3, 0.3]),
);

$pdf->save('blockquote.pdf');

// region: example-themed
// Customize the bar via the Theme.
$themed = new Pdf(
    theme: new Theme(
        quoteIndent: 24.0,
        quoteBarWidth: 4.0,
        quoteBarColor: [0.1, 0.4, 0.8],
    ),
);
$themed->addQuote(
    'A themed blockquote with a thicker, blue bar.',
);
$themed->save('blockquote-themed.pdf');
// endregion
// endregion

rename(__DIR__ . '/blockquote.pdf', example_output_path('writer/blockquote.pdf'));
rename(__DIR__ . '/blockquote-themed.pdf', example_output_path('writer/blockquote-themed.pdf'));
