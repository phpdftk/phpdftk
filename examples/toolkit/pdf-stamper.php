<?php

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

chdir(__DIR__);

// Setup: generate a 3-page input PDF.
{
    $seed = new Phpdftk\Pdf\Writer\Pdf();
    for ($i = 1; $i <= 3; $i++) {
        if ($i > 1) {
            $seed->newPage();
        }
        $seed->addHeading("Section {$i}", 1);
        $seed->addText("Body content for section {$i}.");
    }
    $seed->save('report.pdf');
}

// region: example
use Phpdftk\Pdf\Toolkit\PdfStamper;
use Phpdftk\Pdf\Toolkit\Stamper\StampPosition;

PdfStamper::open('report.pdf')
    ->watermark('DRAFT')
    ->header('Q4 Internal Review')
    ->footer('Confidential')
    ->addPageNumbers(StampPosition::BottomRight)
    ->save('stamped.pdf');
// endregion

rename(__DIR__ . '/report.pdf', example_output_path('toolkit/pdf-stamper/input.pdf'));
rename(__DIR__ . '/stamped.pdf', example_output_path('toolkit/pdf-stamper/output.pdf'));
