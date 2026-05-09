<?php

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

chdir(__DIR__);

// Setup: generate a 10-page input PDF (5 front-matter pages + 5 body pages).
{
    $seed = new Phpdftk\Pdf\Writer\Pdf();
    foreach (['Title', 'Copyright', 'Dedication', 'Preface', 'TOC', 'Chapter 1', 'Chapter 2', 'Chapter 3', 'Chapter 4', 'Chapter 5'] as $i => $title) {
        if ($i > 0) {
            $seed->newPage();
        }
        $seed->addHeading($title, 1);
    }
    $seed->save('book.pdf');
}

// region: example
use Phpdftk\Pdf\Toolkit\PageLabeler;

PageLabeler::open('book.pdf')
    ->setRomanNumerals(fromPage: 1, toPage: 5)   // i, ii, iii, iv, v
    ->setArabic(fromPage: 6)                      // 1, 2, 3, 4, 5
    ->save('labeled.pdf');
// endregion

rename(__DIR__ . '/book.pdf', example_output_path('toolkit/page-labeler/input.pdf'));
rename(__DIR__ . '/labeled.pdf', example_output_path('toolkit/page-labeler/output.pdf'));
