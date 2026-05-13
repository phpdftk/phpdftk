<?php

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

chdir(__DIR__);

// region: example
use Phpdftk\Pdf\Reader\PdfReader;

// Reuse the page-labels showcase PDF as a realistic input — it has front matter,
// chapters, and an appendix with distinctly labelled pages.
$inputPdf = example_output_path('writer/page-labels.pdf');
$reader = PdfReader::fromFile($inputPdf);

// extractAllText() concatenates every page's text with a separator.
// extractText($i) returns one page at a time when you only need a slice.
$allText = $reader->extractAllText("\n\n--- page break ---\n\n");

// Persist both the per-page and full-document forms so the docs page can show both.
file_put_contents(example_output_path('reader/text-output.txt'), $allText);

$perPage = [];
for ($i = 0, $n = $reader->getPageCount(); $i < $n; $i++) {
    $perPage[] = sprintf("=== page %d ===\n%s", $i + 1, $reader->extractText($i));
}
file_put_contents(
    example_output_path('reader/text-per-page.txt'),
    implode("\n\n", $perPage),
);
// endregion
