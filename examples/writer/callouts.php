<?php

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

chdir(__DIR__);

// region: example
use Phpdftk\Pdf\Writer\CalloutStyle;
use Phpdftk\Pdf\Writer\CalloutType;
use Phpdftk\Pdf\Writer\Pdf;

$pdf = new Pdf();
$pdf->setTitle('Callouts demo');
$pdf->addHeading('Callouts', 1);
$pdf->addText('Four built-in callout types — each with its own bar / background palette:');
$pdf->addSpacer(4);

$pdf->addCallout(
    'A neutral aside or contextual reminder. Notes are the safe choice when you '
    . 'want to draw attention without alarm.',
    CalloutType::Note,
);

$pdf->addCallout(
    'A helpful suggestion or shortcut that improves on the default approach.',
    CalloutType::Tip,
);

$pdf->addCallout(
    'Something the reader needs to know but that is not yet a hard failure. '
    . 'Examples: deprecation notice, potentially confusing edge case, opt-in '
    . 'risk that the reader can accept.',
    CalloutType::Warning,
);

$pdf->addCallout(
    'Stop and read this carefully — this content describes a hazard, data-loss '
    . 'risk, or other situation that absolutely must be acknowledged before '
    . 'continuing.',
    CalloutType::Danger,
);

$pdf->addHeading('Customizing a callout', 2);
$pdf->addText('Pass a CalloutStyle to override the label, colours, or to hide the title row:');
$pdf->addSpacer(4);

$pdf->addCallout(
    'Body content rendered without a title row.',
    CalloutType::Note,
    new CalloutStyle(showLabel: false),
);

$pdf->addCallout(
    'The label can be replaced with any string — useful for localized docs.',
    CalloutType::Warning,
    new CalloutStyle(labelOverride: 'Heads up'),
);

$pdf->addCallout(
    'Bar and background colours are fully replaceable.',
    CalloutType::Note,
    new CalloutStyle(
        barColor: [0.5, 0.0, 0.5],   // purple bar
        bgColor:  [0.97, 0.94, 0.99], // very light purple tint
    ),
);

$pdf->save('callouts.pdf');
// endregion

rename(__DIR__ . '/callouts.pdf', example_output_path('writer/callouts.pdf'));
