<?php

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

chdir(__DIR__);

// Generate an input PDF first so the example below has something to read.
// This setup is intentionally hidden from the docs page.
{
    $seed = new Phpdftk\Pdf\Writer\Pdf();
    $seed->addHeading('Lease Agreement', 1);
    $seed->addText('This Lease Agreement governs the rental of the property at 123 Main Street.');
    $seed->addSpacer(12);
    $seed->addHeading('Indemnification', 2);
    $seed->addText('Tenant agrees to indemnify and hold harmless the Landlord from any liability arising during the lease term.');
    $seed->addSpacer(12);
    $seed->addHeading('Rent', 2);
    $seed->addText('Monthly rent is $2,400.00, due on the first day of each calendar month.');
    $seed->save('contract.pdf');
}

// region: example
use Phpdftk\Pdf\Toolkit\TextExtractor;

$extractor = TextExtractor::open('contract.pdf');

echo "Pages: {$extractor->getPageCount()}\n\n";

// Page-by-page extraction (1-based)
$firstPage = $extractor->page(1);

// Search for a literal phrase
$results = $extractor->search('indemnify');
foreach ($results as $match) {
    echo "Page {$match->pageNumber}: {$match->text}\n";
}

// Regex search — match dollar amounts
$amounts = $extractor->searchPattern('/\$[\d,]+\.\d{2}/');
foreach ($amounts as $match) {
    echo "Found amount: {$match->text}\n";
}
// endregion

// Copy the input PDF to the docs samples dir so the docs link can point at it.
rename(__DIR__ . '/contract.pdf', example_output_path('toolkit/text-extractor.pdf'));
