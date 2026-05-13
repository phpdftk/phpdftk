<?php

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

chdir(__DIR__);

// region: example
use Phpdftk\Pdf\Reader\PdfReader;

// Inspect the structure of the outline showcase PDF — it has multiple pages,
// nested bookmarks, and named destinations.
$inputPdf = example_output_path('writer/outline.pdf');
$reader = PdfReader::fromFile($inputPdf);

$catalog = $reader->getCatalog();
$pages   = $reader->getPages();

// Summarise the page tree.
$pageSummary = [];
foreach ($pages as $i => $page) {
    $mediaBox = null;
    if ($page->has('MediaBox')) {
        $box = $page->get('MediaBox');
        if ($box instanceof \Phpdftk\Pdf\Core\PdfArray) {
            $mediaBox = array_map(
                fn ($n) => $n instanceof \Phpdftk\Pdf\Core\PdfNumber ? $n->value : null,
                $box->items,
            );
        }
    }
    $pageSummary[] = [
        'index'    => $i,
        'mediaBox' => $mediaBox,
        'hasContents' => $page->has('Contents'),
        'hasAnnots'   => $page->has('Annots'),
    ];
}

// Summarise the catalog's top-level keys (only the structural ones the reader
// can resolve without a typed schema).
$catalogKeys = [];
foreach (['Type', 'Version', 'Pages', 'Outlines', 'Names', 'PageLabels', 'AcroForm', 'OpenAction'] as $key) {
    $catalogKeys[$key] = $catalog->has($key) ? 'present' : 'absent';
}

$summary = [
    'pdfVersion' => $reader->getVersion(),
    'linearized' => $reader->isLinearized(),
    'pageCount'  => $reader->getPageCount(),
    'catalog'    => $catalogKeys,
    'pages'      => $pageSummary,
];

file_put_contents(
    example_output_path('reader/structure.json'),
    json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n",
);
// endregion
