<?php

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

chdir(__DIR__);

// Setup: generate two source PDFs.
{
    $a = new Phpdftk\Pdf\Writer\Pdf();
    $a->addHeading('Cover Letter', 1);
    $a->addText('Dear Hiring Committee,');
    $a->addSpacer(8);
    $a->addText('Please consider my application.');
    $a->save('cover-letter.pdf');

    $b = new Phpdftk\Pdf\Writer\Pdf();
    $b->addHeading('Resume', 1);
    $b->addText('Experienced engineer.');
    $b->newPage();
    $b->addHeading('References', 1);
    $b->addText('Available on request.');
    $b->save('resume.pdf');
}

// region: example
use Phpdftk\Pdf\Toolkit\PdfMerger;

PdfMerger::create()
    ->addFile('cover-letter.pdf')
    ->addFile('resume.pdf')
    ->save('application.pdf');
// endregion

rename(__DIR__ . '/cover-letter.pdf', example_output_path('toolkit/pdf-merger/input-cover.pdf'));
rename(__DIR__ . '/resume.pdf', example_output_path('toolkit/pdf-merger/input-resume.pdf'));
rename(__DIR__ . '/application.pdf', example_output_path('toolkit/pdf-merger/output.pdf'));
