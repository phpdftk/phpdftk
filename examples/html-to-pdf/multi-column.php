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
  <body>
    <h1>The Two-Column Newsletter</h1>
    <p class="lead">
      Long-form content flows into two balanced columns with a divider rule
      between them — the same layout most printed periodicals have used since
      the linotype era.
    </p>
    <section class="columns">
      <p>
        CSS Multi-column 1 lets a single block split its in-flow children
        into N side-by-side fragmentainers. <strong>column-count</strong>
        and <strong>column-width</strong> jointly determine how many
        columns appear; <strong>column-gap</strong> sets the spacing
        between them; <strong>column-rule</strong> strokes a divider in
        each gap. Heights balance by default so neither column ends
        ragged when the content allows.
      </p>
      <p>
        phpdftk's Phase-1 implementation honours the most-asked subset of
        the spec: explicit <code>column-count</code>, the
        <code>columns</code> shorthand, the <code>column-rule</code>
        shorthand and its longhands, balanced fill, and an approximate
        equal-height balance algorithm.
      </p>
      <p>
        Tables, inline-only blocks, and replaced elements are skipped for
        column generation — multi-column only applies to block containers
        whose children form a block formatting context.
      </p>
      <p>
        Mid-content fragmentation (a single block splitting across
        columns or pages) lands with 1I.2; until then a child taller than
        the column simply overflows its column boundary while staying
        whole. For typical newsletter copy that's fine.
      </p>
      <p>
        Forced column breaks (<code>break-before: column</code>) and
        column-spanning elements (<code>column-span: all</code>) are
        Phase-2 targets — recorded in the project plan and tracked in
        the rendering coverage docs.
      </p>
      <p>
        Until then the two-column layout you're reading proves the
        engine end-to-end: cascade resolves the shorthand, layout
        balances the heights, painter strokes the rule between the
        columns. Pure PHP, zero browser dependency.
      </p>
    </section>
  </body>
</html>
HTML;

$css = <<<'CSS'
body { font: 11pt sans-serif; color: #222; margin: 48pt; }
h1 { color: #1a5490; font-size: 22pt; margin-bottom: 6pt; }
p.lead { font-size: 13pt; color: #444; margin-bottom: 18pt; }
section.columns {
  columns: 2;
  column-gap: 24pt;
  column-rule: 1pt solid #999;
}
section.columns p { margin: 0 0 10pt 0; }
code { background-color: #eef; padding: 0 2pt; }
strong { color: #1a5490; }
CSS;

$options = (new RendererOptions())->withPageSize(612.0, 792.0);

$fontPath = __DIR__ . '/../../tests/fixtures/fonts/NotoSans-Regular.otf';
if (is_file($fontPath)) {
    $options = $options->withDefaultFont((new OpenTypeParser($fontPath))->parse());
}

$result = (new Renderer($options))->render($html, $css);
// endregion: example

$outputPath = example_output_path('html-to-pdf/multi-column.pdf');
$result->writer->save($outputPath);

printf("Wrote %s (%d bytes)\n", $outputPath, filesize($outputPath));
if ($result->warnings !== []) {
    printf("Renderer emitted %d warning(s):\n", count($result->warnings));
    foreach ($result->warnings as $w) {
        printf("  [%s/%s] %s\n", $w->severity->value, $w->code->value, $w->message);
    }
}
