<?php

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

chdir(__DIR__);

// region: example
use Phpdftk\Geometry\Point;
use Phpdftk\Geometry\Rectangle;
use Phpdftk\Pdf\Writer\Form\CheckboxOptions;
use Phpdftk\Pdf\Writer\Form\ChoiceFieldOptions;
use Phpdftk\Pdf\Writer\Form\TextFieldOptions;
use Phpdftk\Pdf\Writer\Pdf;

$pdf = new Pdf();
$pdf->setTitle('Phase 4 batch 2 — fields, gradients, templates, spot colors');

$pdf->addHeading('Form fields', 1);
$page = $pdf->doc()->addPage();

// 4.2 — Form fields. NeedAppearances tells viewers to render widgets
// at open time, so we don't have to pre-build their appearance dicts.
$pdf->doc()->addTextField(
    'name',
    $page,
    new Rectangle(72, 720, 250, 22),
    new TextFieldOptions(defaultValue: 'Jane Doe', required: true),
);
$pdf->doc()->addTextField(
    'notes',
    $page,
    new Rectangle(72, 640, 250, 60),
    new TextFieldOptions(multiline: true),
);
$pdf->doc()->addCheckbox(
    'newsletter',
    $page,
    new Rectangle(72, 610, 14, 14),
    new CheckboxOptions(defaultChecked: true),
);
$pdf->doc()->addChoiceField(
    'country',
    $page,
    new Rectangle(72, 570, 200, 22),
    new ChoiceFieldOptions(
        choices: [['us', 'United States'], ['ca', 'Canada'], ['mx', 'Mexico']],
        defaultValue: 'us',
        sort: true,
    ),
);
$pdf->doc()->addSignatureField(
    'signature',
    $page,
    new Rectangle(72, 500, 200, 50),
);

// 4.5 — Gradients. The shading pattern is registered on the document
// and attached to the page via useGradient(), which returns the
// resource name the content stream needs.
$page = $pdf->doc()->addPage();
$linear = $pdf->doc()->addLinearGradient(
    new Point(72, 720),
    new Point(540, 720),
    [0.95, 0.4, 0.4],
    [0.4, 0.4, 0.95],
);
$gradName = $page->useGradient($linear);
$page->contentStream()
    ->saveGraphicsState()
    ->setFillColorSpace('Pattern')
    ->setFillColor('/' . $gradName . ' scn')
    ->rectangle(72, 660, 468, 80)
    ->fill()
    ->restoreGraphicsState();

$radial = $pdf->doc()->addRadialGradient(
    new Point(300, 480), 0.0,
    new Point(300, 480), 120.0,
    [1.0, 1.0, 1.0],
    [0.1, 0.1, 0.6],
);
$radName = $page->useGradient($radial);
$page->contentStream()
    ->saveGraphicsState()
    ->setFillColorSpace('Pattern')
    ->setFillColor('/' . $radName . ' scn')
    ->rectangle(180, 360, 240, 240)
    ->fill()
    ->restoreGraphicsState();

// 4.11 — Spot colors. CMYK approximation for viewers without the ink.
$page = $pdf->doc()->addPage();
$spot = $pdf->doc()->registerSpotColor('Pantone 185 C', [0.0, 0.85, 0.6, 0.0]);
$csName = $page->useSpotColor($spot);
$page->contentStream()
    ->setFillColorSpace($csName)
    ->setFillColor(1.0)  // full tint
    ->rectangle(72, 700, 468, 40)
    ->fill();

// 4.12 — Form XObject template, reused on multiple pages.
$badge = $pdf->doc()->createTemplate(new Rectangle(0, 0, 100, 30), function ($cs): void {
    $cs->setFillColorRGB(0.2, 0.6, 0.2)
       ->rectangle(0, 0, 100, 30)
       ->fill()
       ->setFillColorRGB(1, 1, 1)
       ->beginText()
       ->setFont('/F1', 12)
       ->moveTextPosition(20, 10)
       ->showText('VERIFIED')
       ->endText();
});
// The badge content stream refers to '/F1' — register Helvetica on
// the placing pages so the resource is in scope.
$font = $pdf->writer()->addFont(new \Phpdftk\Pdf\Core\Font\Type1Font(\Phpdftk\Pdf\Core\Font\StandardFont::Helvetica));
$page->drawTemplate($badge, 72, 200);
$page->drawTemplate($badge, 240, 200);
$page->drawTemplate($badge, 408, 200);

$pdf->save('phase4-batch2.pdf');
// endregion

rename(__DIR__ . '/phase4-batch2.pdf', example_output_path('writer/phase4-batch2.pdf'));
