<?php

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

chdir(__DIR__);

// Setup: generate a 10-page input PDF.
{
    $seed = new Phpdftk\Pdf\Writer\Pdf();
    for ($i = 1; $i <= 10; $i++) {
        if ($i > 1) {
            $seed->newPage();
        }
        $seed->addHeading("Page {$i}", 1);
        $seed->addText("Content for page {$i}.");
    }
    $seed->save('book.pdf');
}

// region: example
use Phpdftk\Pdf\Toolkit\PageSlicer;

// Keep just the first three pages
PageSlicer::open('book.pdf')
    ->keepRange(1, 3)
    ->save('first-three.pdf');

// Or pull a non-contiguous selection into a new document
PageSlicer::open('book.pdf')
    ->keepPages(1, 3, 5, 7, 9)
    ->save('odd-pages.pdf');
// endregion

rename(__DIR__ . '/book.pdf', example_output_path('toolkit/page-slicer/input.pdf'));
rename(__DIR__ . '/first-three.pdf', example_output_path('toolkit/page-slicer/output.pdf'));
unlink(__DIR__ . '/odd-pages.pdf');
