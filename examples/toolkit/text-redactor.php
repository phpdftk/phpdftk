<?php

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

chdir(__DIR__);

// Setup: generate an input PDF with sensitive text.
{
    $seed = new Phpdftk\Pdf\Writer\Pdf();
    $seed->addHeading('Witness Statement', 1);
    $seed->addText('The witness, John Smith, observed the events on March 14, 2026.');
    $seed->addSpacer(8);
    $seed->addText('John Smith reported the incident to authorities at SSN 123-45-6789.');
    $seed->save('statement.pdf');
}

// region: example
use Phpdftk\Pdf\Toolkit\TextRedactor;

TextRedactor::open('statement.pdf')
    ->redactText('John Smith')                   // literal phrase
    ->redactPattern('/\d{3}-\d{2}-\d{4}/')       // SSN regex
    ->setRedactionColor(0.0, 0.0, 0.0)           // solid black bars
    ->apply()
    ->save('redacted.pdf');
// endregion

rename(__DIR__ . '/statement.pdf', example_output_path('toolkit/text-redactor/input.pdf'));
rename(__DIR__ . '/redacted.pdf', example_output_path('toolkit/text-redactor/output.pdf'));
