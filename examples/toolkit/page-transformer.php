<?php

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

chdir(__DIR__);

// Setup: generate a simple 2-page input PDF.
{
    $seed = new Phpdftk\Pdf\Writer\Pdf();
    $seed->addHeading('Original Page', 1);
    $seed->addText('This page will be rotated and scaled.');
    $seed->newPage();
    $seed->addHeading('Second Page', 1);
    $seed->addText('Untouched.');
    $seed->save('original.pdf');
}

// region: example
use Phpdftk\Pdf\Toolkit\PageSelector;
use Phpdftk\Pdf\Toolkit\PageTransformer;

PageTransformer::open('original.pdf')
    ->rotate(90, PageSelector::pages(1))    // rotate just page 1
    ->scale(0.75)                            // shrink every page to 75%
    ->save('transformed.pdf');
// endregion

rename(__DIR__ . '/original.pdf', example_output_path('toolkit/page-transformer/input.pdf'));
rename(__DIR__ . '/transformed.pdf', example_output_path('toolkit/page-transformer/output.pdf'));
