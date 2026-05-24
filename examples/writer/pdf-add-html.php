<?php

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

chdir(__DIR__);

// region: example
use Phpdftk\FontParser\OpenTypeParser;
use Phpdftk\Pdf\Writer\Pdf;

$fontPath = __DIR__ . '/../../tests/fixtures/fonts/NotoSans-Regular.otf';
$htmlFont = is_file($fontPath) ? (new OpenTypeParser($fontPath))->parse() : null;

// `Pdf::addHtml` lets you mix the high-level cursor-driven flow API with
// the full HTML+CSS renderer. The HTML render lands in a fresh sequence
// of pages; after it returns, subsequent flow-API calls (`addText`,
// `addHeading`, etc.) start on a new page automatically.

$pdf = new Pdf();

// 1. A "cover" page rendered with the simple flow API.
$pdf->addHeading('Hello from the flow API', 1);
$pdf->addText(
    'This page is produced by the cursor-driven `Pdf` builder — heading + '
    . 'paragraph + automatic margin handling. The next pages are emitted '
    . 'by the full HTML + CSS renderer via `addHtml`.',
);

// 2. A multi-page HTML report — full CSS coverage, tables, links, etc.
$pdf->addHtml(<<<HTML
<!DOCTYPE html>
<html>
  <head><title>Mixed Output</title></head>
  <body style="font: 11pt sans-serif; color: #222; margin: 48pt;">
    <h1 style="font-size: 22pt; color: #1a5490;">HTML-rendered section</h1>
    <p>This portion is parsed and laid out as full HTML + CSS, with
    table-row layout, border-radius, generated content, and link
    annotations:</p>

    <table style="width: 100%; border-collapse: collapse; margin: 12pt 0;">
      <thead style="background-color: #eef;">
        <tr><th style="border: 1px solid #c5d4e3; padding: 4pt;">Section</th>
            <th style="border: 1px solid #c5d4e3; padding: 4pt;">Notes</th></tr>
      </thead>
      <tbody>
        <tr><td style="border: 1px solid #c5d4e3; padding: 4pt;">Flow API</td>
            <td style="border: 1px solid #c5d4e3; padding: 4pt;">Cursor-driven.</td></tr>
        <tr><td style="border: 1px solid #c5d4e3; padding: 4pt;">HTML renderer</td>
            <td style="border: 1px solid #c5d4e3; padding: 4pt;">CSS-driven.</td></tr>
      </tbody>
    </table>

    <p>See the <a href="https://phpdftk.dev/" title="Project site">project site</a>
    for full feature coverage.</p>
  </body>
</html>
HTML, font: $htmlFont);

// 3. Resume with the flow API on a fresh page.
$pdf->addHeading('Back to the flow API', 1);
$pdf->addText('After `addHtml` returns, the cursor resets to a new page.');
// endregion: example

$outputPath = example_output_path('writer/pdf-add-html.pdf');
$pdf->save($outputPath);

printf("Wrote %s (%d bytes)\n", $outputPath, filesize($outputPath));
