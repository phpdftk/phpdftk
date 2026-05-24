<?php

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

chdir(__DIR__);

// region: example
use Phpdftk\FontParser\OpenTypeParser;
use Phpdftk\HtmlToPdf\Renderer;
use Phpdftk\HtmlToPdf\RendererOptions;

$html = <<<'HTML'
<!DOCTYPE html>
<html>
  <head>
    <title>Quarterly Report</title>
    <style>
      /* CSS Paged Media 3 §3.6 — running headers / footers via the
         margin-box at-rules nested inside @page. The same six positions
         repeat on every output page. */
      @page {
        /* `font-size` and `color` set on @page cascade into every
           margin box that doesn't restate its own — common defaults
           DRY'd up. */
        font-size: 10pt;
        color: #555;
        @top-left    { content: "Acme Corp"; color: #1a5490; font-size: 12pt; }
        @top-center  { content: "Quarterly Report"; font-size: 14pt; }
        @top-right   { content: "Q3 2026"; }
        @bottom-left { content: "Confidential"; color: #cc0000; font-size: 8pt; }
        @bottom-center { content: "Page " counter(page, upper-roman) " of " counter(pages, upper-roman); }
        @bottom-right { content: "DRAFT"; color: #999; font-size: 9pt; }
      }
      /* Cover-page override: the first page suppresses the running
         title and shows a centered cover line instead. Subsequent
         pages keep the standard headers above. */
      @page :first {
        @top-left { content: ""; }
        @top-center { content: "Cover"; font-size: 24pt; color: #1a5490; }
        @top-right { content: ""; }
      }
      body { font: 12pt sans-serif; margin: 72pt 36pt; color: #222; }
      h1 { color: #1a5490; }
      section { page-break-after: always; }
      section:last-child { page-break-after: auto; }
      .filler { height: 500pt; background-color: #eef; border: 1px solid #ddd; }
    </style>
  </head>
  <body>
    <section>
      <h1>Page 1 — Summary</h1>
      <p>The headers and footers above and below this content are drawn
      from the @page rule's nested margin-box at-rules. They repeat on
      every page automatically.</p>
      <div class="filler"></div>
    </section>
    <section>
      <h1>Page 2 — Detail</h1>
      <p>Each margin-box position picks its own horizontal alignment:
      left-anchored at the page margin, centred across the page, or
      right-anchored. The y-band is symmetric: top boxes sit halfway
      through the top margin, bottom boxes halfway through the bottom.</p>
    </section>
  </body>
</html>
HTML;

$options = (new RendererOptions())->withPageSize(612.0, 792.0);

$fontPath = __DIR__ . '/../../tests/fixtures/fonts/NotoSans-Regular.otf';
if (is_file($fontPath)) {
    $options = $options->withDefaultFont((new OpenTypeParser($fontPath))->parse());
}

$result = (new Renderer($options))->render($html);
// endregion: example

$outputPath = example_output_path('html-to-pdf/page-margin-boxes.pdf');
$result->writer->save($outputPath);

printf("Wrote %s (%d bytes)\n", $outputPath, filesize($outputPath));
if ($result->warnings !== []) {
    printf("Renderer emitted %d warning(s):\n", count($result->warnings));
    foreach ($result->warnings as $w) {
        printf("  [%s/%s] %s\n", $w->severity->value, $w->code->value, $w->message);
    }
}
