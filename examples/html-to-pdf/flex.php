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
    <h1>Flex Layouts</h1>
    <p class="lead">
      A few common arrangements that CSS Flexible Box Layout 1 makes
      trivial — equal-width cards, header bars with logo + actions, and
      vertically-centered hero strips.
    </p>

    <h2>Equal-width cards (justify-content: space-between)</h2>
    <div class="cards">
      <div class="card"><h3>Fast</h3><p>Pure-PHP layout, no Chromium boot, no headless browser.</p></div>
      <div class="card"><h3>Spec-aligned</h3><p>CSS 2.1 + selected Level-3 modules implemented to spec.</p></div>
      <div class="card"><h3>PDF-aware</h3><p>Lays out for paged media: page breaks, page boxes, named destinations.</p></div>
    </div>

    <h2>Header bar (logo left, actions right)</h2>
    <div class="bar">
      <div class="brand">phpdftk</div>
      <div class="actions">
        <span class="link">Docs</span>
        <span class="link">Examples</span>
        <span class="link">GitHub</span>
      </div>
    </div>

    <h2>Hero strip (align-items: center)</h2>
    <div class="hero">
      <div class="hero-art">★</div>
      <div class="hero-copy">
        <h3>Vertically centred</h3>
        <p>The art glyph and the copy block have different heights;
           <code>align-items: center</code> places both on the cross-axis midline.</p>
      </div>
    </div>
  </body>
</html>
HTML;

$css = <<<'CSS'
body { font: 11pt sans-serif; color: #222; margin: 48pt; }
h1 { color: #1a5490; font-size: 22pt; margin-bottom: 6pt; }
h2 { color: #1a5490; font-size: 14pt; margin: 18pt 0 8pt; }
h3 { font-size: 12pt; margin: 0 0 4pt; }
p.lead { font-size: 12pt; color: #444; margin-bottom: 18pt; }

.cards { display: flex; justify-content: space-between; column-gap: 12pt; }
.card { width: 150pt; padding: 10pt; background-color: #f4f6fa; }
.card p { margin: 0; font-size: 10pt; color: #555; }

.bar { display: flex; justify-content: space-between; align-items: center;
       padding: 8pt 12pt; background-color: #1a5490; color: #fff; }
.brand { font-weight: bold; font-size: 13pt; }
.actions { display: flex; column-gap: 16pt; }
.link { font-size: 10pt; }

.hero { display: flex; align-items: center; column-gap: 16pt;
        padding: 16pt; background-color: #fef9e6; margin-top: 12pt; }
.hero-art { width: 60pt; height: 60pt; font-size: 32pt; text-align: center;
            background-color: #ffe082; color: #663c00; padding: 12pt; }
.hero-copy p { margin: 0; }
code { background-color: #eef; padding: 0 2pt; }
CSS;

$options = (new RendererOptions())->withPageSize(612.0, 792.0);

$fontPath = __DIR__ . '/../../tests/fixtures/fonts/NotoSans-Regular.otf';
if (is_file($fontPath)) {
    $options = $options->withDefaultFont((new OpenTypeParser($fontPath))->parse());
}

$result = (new Renderer($options))->render($html, $css);
// endregion: example

$outputPath = example_output_path('html-to-pdf/flex.pdf');
$result->writer->save($outputPath);

printf("Wrote %s (%d bytes)\n", $outputPath, filesize($outputPath));
if ($result->warnings !== []) {
    printf("Renderer emitted %d warning(s):\n", count($result->warnings));
    foreach ($result->warnings as $w) {
        printf("  [%s/%s] %s\n", $w->severity->value, $w->code->value, $w->message);
    }
}
