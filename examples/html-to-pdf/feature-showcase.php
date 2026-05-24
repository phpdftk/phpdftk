<?php

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

chdir(__DIR__);

// region: example
use Phpdftk\FontParser\OpenTypeParser;
use Phpdftk\HtmlToPdf\Renderer;
use Phpdftk\HtmlToPdf\RendererOptions;

// Tiny inline 4x4 PNG used by the image / background-image demo sections.
$pngBase64 = base64_encode(hex2bin(
    '89504E470D0A1A0A0000000D49484452000000040000000408060000'
    . '00A9F1CE7000000019744558745469746C6500496D6167652067656E657261746564206279204'
    . '7494D502E64C84E6500000010494441541857636060601800000001000001D72E1D7900000000'
    . '49454E44AE426082',
));
$dataUrl = 'data:image/png;base64,' . $pngBase64;

$html = <<<HTML
<!DOCTYPE html>
<html>
  <head>
    <title>phpdftk Renderer — feature showcase</title>
    <meta name="author" content="phpdftk">
    <meta name="description" content="Sample document exercising the renderer's full feature surface.">
  </head>
  <body>
    <h1>Renderer feature showcase</h1>

    <h2 id="rounded">Rounded boxes &amp; outlines</h2>
    <div class="card">A card box with a rounded background and matching border.</div>
    <div class="focus">Outline keeps focus indicators visually distinct from borders.</div>

    <h2 id="typography">Typography</h2>
    <p>
      <b>Bold</b>, <i>italic</i>, <mark>highlighted</mark>, <ins>inserted</ins>,
      <del>deleted</del>, <abbr title="HyperText Markup Language">HTML</abbr>,
      <code>monospace</code>, x<sup>2</sup>+y<sub>1</sub>,
      <span class="huge">large</span>, and a
      <a href="https://phpdftk.dev/" title="Visit phpdftk.dev">styled link</a>.
    </p>
    <p class="dbl">Double-underline emphasis.</p>
    <p class="dotted">Dotted-underline note.</p>

    <h2 id="counters">CSS counters</h2>
    <ol class="numbered">
      <li>Counters automatically number each item.</li>
      <li>Mid-document ordinals are reliable…</li>
      <li>…even across CSS counter() and counter-style.</li>
    </ol>

    <h2 id="images">Images</h2>
    <p>
      Inline raster: <img src="$dataUrl" width="32" height="32">
      and a background-image carrying the same PNG:
    </p>
    <div class="bg-tile"></div>

    <h2 id="quotes">Quotes</h2>
    <p>Generated quotes via <code>&lt;q&gt;</code>:
       <q>The best way to predict the future is to invent it.</q></p>

    <h2 id="definitions">Definition list</h2>
    <dl>
      <dt>Phase 1L</dt>
      <dd>Image painting — data: URLs + baseDir-resolved local files.</dd>
      <dt>Phase 1H</dt>
      <dd>Table layout — currently deferred.</dd>
    </dl>

    <h2 id="forms">Form fields (static)</h2>
    <p>Name: <input type="text" value="Alice Example"> &nbsp;
       Email: <input type="email" value="alice@example.com"> &nbsp;
       <button>Submit</button></p>
    <p><small>(PDF AcroForm field generation lands in Phase 2; these render as static text snapshots.)</small></p>

    <h2 id="tables">Tables</h2>
    <table class="data">
      <thead>
        <tr><th>Feature</th><th>Spec</th><th>Status</th></tr>
      </thead>
      <tbody>
        <tr><td>Inline images (data:)</td><td>HTML 5 §4.8.3</td><td>Done</td></tr>
        <tr><td>Border-radius</td><td>CSS Backgrounds 3 §6</td><td>Done</td></tr>
        <tr><td>Tables (auto-layout)</td><td>CSS Tables 3</td><td>Phase 2</td></tr>
      </tbody>
    </table>

    <h2 id="links">Navigation</h2>
    <p>
      Jump back to <a href="#rounded">Rounded boxes</a>, or follow the
      <a href="https://www.w3.org/TR/css-text-3/" title="CSS Text 3 spec">spec link</a>
      (external).
    </p>
  </body>
</html>
HTML;

$css = <<<'CSS'
body { font: 11pt sans-serif; color: #222; margin: 48pt; }
h1 { font-size: 22pt; color: #1a5490; }
h2 { font-size: 14pt; color: #1a5490; margin-top: 18pt; border-bottom: 1px solid #c5d4e3; padding-bottom: 2pt; }
p { margin: 0 0 8pt; }

/* Section 1: rounded + outline. */
.card { background-color: #fffbe5; border: 1px solid #e0c46b; border-radius: 8pt; padding: 12pt; margin: 8pt 0; }
.focus { background-color: #f3f6fa; outline: 2px dashed #1a5490; outline-offset: 3pt; padding: 12pt; margin: 8pt 0; }

/* Typography. */
.huge { font-size: 1.6em; }
.dbl  { text-decoration: underline double #b03060; }
.dotted { text-decoration: underline dotted; }

/* Counters. */
.numbered { counter-reset: item; padding-left: 0; }
.numbered li { counter-increment: item; list-style-type: none; }
.numbered li::before { content: counter(item, decimal-leading-zero) '. '; color: #1a5490; font-weight: bold; }

/* Background image tile. */
.bg-tile { background-image: url('IMG'); background-color: #eef; width: 64pt; height: 32pt; border-radius: 4pt; }

/* Tables. */
.data { width: 100%; border-collapse: collapse; }
.data th, .data td { border: 1px solid #c5d4e3; padding: 4pt; vertical-align: top; }
.data th { background-color: #eef; }
CSS;
$css = str_replace('IMG', $dataUrl, $css);

$options = (new RendererOptions())->withPageSize(612.0, 792.0);

$fontPath = __DIR__ . '/../../tests/fixtures/fonts/NotoSans-Regular.otf';
if (is_file($fontPath)) {
    $options = $options->withDefaultFont((new OpenTypeParser($fontPath))->parse());
}

$result = (new Renderer($options))->render($html, $css);
// endregion: example

$outputPath = example_output_path('html-to-pdf/feature-showcase.pdf');
$result->writer->save($outputPath);

printf("Wrote %s (%d bytes)\n", $outputPath, filesize($outputPath));
if ($result->warnings !== []) {
    printf("Renderer emitted %d warning(s):\n", count($result->warnings));
    foreach ($result->warnings as $w) {
        printf("  [%s/%s] %s\n", $w->severity->value, $w->code->value, $w->message);
    }
}
