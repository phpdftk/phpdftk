<?php

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

chdir(__DIR__);

// region: example
use Phpdftk\FontParser\OpenTypeParser;
use Phpdftk\HtmlToPdf\Renderer;
use Phpdftk\HtmlToPdf\RendererOptions;

// A "knowledge-base" article: TOC with internal anchors, sections,
// `<mark>` / `<ins>` / `<del>` semantics, an external link, definition
// list, multi-page content. Exercises the renderer's navigation surface:
// PDF outline from headings, /Link annotations for external + internal
// references, /Info metadata from <title> + <meta>.
$html = <<<'HTML'
<!DOCTYPE html>
<html>
  <head>
    <title>phpdftk Renderer Knowledge Base</title>
    <meta name="author" content="phpdftk">
    <meta name="description" content="Sample knowledge-base article showing navigation, semantics, and pagination.">
    <meta name="keywords" content="phpdftk, html, pdf, sample">
  </head>
  <body>
    <h1>Renderer knowledge base</h1>

    <h2>Table of contents</h2>
    <ol>
      <li><a href="#intro">Introduction</a></li>
      <li><a href="#semantics">Inline semantics</a></li>
      <li><a href="#fragmentation">Page breaks</a></li>
      <li><a href="#further">Further reading</a></li>
    </ol>

    <h2 id="intro">Introduction</h2>
    <p>
      The <mark>html-to-pdf</mark> renderer wires together the substrate
      packages — <code>phpdftk/html</code>, <code>phpdftk/css</code>,
      <code>phpdftk/text</code>, <code>phpdftk/font-parser</code> — and
      emits a PDF via <code>phpdftk/pdf-writer</code>. See the
      <a href="https://phpdftk.dev/">project site</a> for the bigger
      picture.
    </p>

    <h2 id="semantics">Inline semantics</h2>
    <dl>
      <dt><code>&lt;mark&gt;</code></dt>
      <dd>Highlighted text — yellow background by default, like a print marker.</dd>
      <dt><code>&lt;ins&gt;</code> / <code>&lt;del&gt;</code></dt>
      <dd>
        <ins>Insertions</ins> render with an underline; <del>deletions</del>
        get a line through. Both inherit `color` from the surrounding
        paragraph.
      </dd>
      <dt><code>&lt;sub&gt;</code> / <code>&lt;sup&gt;</code></dt>
      <dd>
        Water is H<sub>2</sub>O; phpdftk's spec coverage matrix is at
        section A<sup>1</sup> of the docs.
      </dd>
    </dl>

    <h2 id="fragmentation" style="break-before: page;">Page breaks</h2>
    <p>
      This section's heading carries
      <code>break-before: page</code>, so it starts on a new physical
      page — the cursor advances to the next page boundary before the
      heading lays out. Use this for chapter starts and other hard
      pagination requirements.
    </p>
    <p>
      <code>break-after: page</code> works symmetrically, and
      <code>break-inside: avoid</code> tells the layout to shift a block
      whole to the next page when it would otherwise straddle a boundary.
    </p>

    <h2 id="further" style="break-before: page;">Further reading</h2>
    <ul>
      <li><a href="#intro">Back to Introduction</a></li>
      <li><a href="https://phpdftk.dev/standards">Standards &amp; performance dashboards</a></li>
      <li><a href="https://www.w3.org/TR/CSS22/visuren.html">CSS 2.1 §9 visual formatting model</a></li>
    </ul>
    <p style="text-align: center; color: #666;">— end —</p>
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

$outputPath = example_output_path('html-to-pdf/knowledge-base.pdf');
$result->writer->save($outputPath);

printf("Wrote %s (%d bytes)\n", $outputPath, filesize($outputPath));
if ($result->warnings !== []) {
    printf("Renderer emitted %d warning(s):\n", count($result->warnings));
    foreach ($result->warnings as $w) {
        printf("  [%s/%s] %s\n", $w->severity->value, $w->code->value, $w->message);
    }
}
