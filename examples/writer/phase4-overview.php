<?php

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

chdir(__DIR__);

// region: example
use Phpdftk\Geometry\Rectangle;
use Phpdftk\Pdf\Core\Document\ViewerPreferences;
use Phpdftk\Pdf\Writer\Action;
use Phpdftk\Pdf\Writer\Pdf;

$pdf = new Pdf();

// 4.10 — Viewer preferences. Closure form mutates a fresh instance.
$pdf->setViewerPreferences(function (ViewerPreferences $vp): void {
    $vp->displayDocTitle = true;
    $vp->fitWindow = true;
});

// 4.8 — Open action. Jump straight to a URL the first time the doc opens
// (most viewers respect this with a security prompt).
$pdf->setOpenAction(Action::uri('https://phpdftk.dev/'));

$pdf->setTitle('Phase 4 overview');
$pdf->addHeading('Phase 4 — Level 2 wrappers', 1);
$pdf->addText('This document exercises the Phase 4 wrappers exposed on PdfDoc and Writer\\Page.');

// 4.3 — File attachments. Pass-through bytes (no file on disk needed).
$pdf->doc()->attachFileBytes(
    'invoice.xml',
    "<?xml version=\"1.0\"?>\n<invoice><id>I-001</id></invoice>",
    description: 'Embedded ZUGFeRD-style invoice (demo only)',
    mimeType: 'application/xml',
    relationship: 'Alternative',
);

// 4.1 — Annotation builders.
$pdf->newPage();
$pdf->addHeading('Annotations', 2);
$page = $pdf->doc()->addPage(); // explicit, positioned drawing surface
$pdf->doc()->addStickyNote($page, 72, 720, 'A sticky note attached at (72, 720).');
$pdf->doc()->addSquare($page, new Rectangle(72, 600, 200, 80));
$pdf->doc()->addCircleAnnotation($page, new Rectangle(300, 600, 100, 80));
$pdf->doc()->addLineAnnotation($page, 72, 540, 540, 540);
$pdf->doc()->addStamp($page, new Rectangle(72, 460, 180, 60), 'Approved');

// 4.4 — Graphics state transforms + opacity. Scoped to a single withTransform block.
$page->withTransform(function ($p): void {
    $p->translate(300, 300);
    $p->rotate(15);
    $p->setOpacity(0.4);
    $p->drawRectangle(0, 0, 120, 60,
        fill: new \Phpdftk\Color\RgbColor(0.2, 0.5, 0.9),
    );
});

// 4.6 — Optional content (layers).
$layer = $pdf->doc()->addLayer('Markup', visible: true);
$page->inLayer($layer, function ($p): void {
    $p->drawLine(72, 200, 540, 200,
        color: new \Phpdftk\Color\RgbColor(0.8, 0.2, 0.2),
    );
});

// 4.7 — Page rotation + boxes. Set a TrimBox slightly inside the MediaBox.
$page->setTrimBox(new Rectangle(36, 36, 540, 720));

$pdf->save('phase4-overview.pdf');
// endregion

rename(__DIR__ . '/phase4-overview.pdf', example_output_path('writer/phase4-overview.pdf'));
