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
    <article>
      <header>
        <h1>The phpdftk html-to-pdf renderer</h1>
        <p class="byline">A working tour of the engine in its current state.</p>
      </header>

      <section>
        <h2>What's plumbed end-to-end</h2>
        <p>
          The renderer parses the HTML through <code>phpdftk/html</code>,
          runs the cascade through <code>phpdftk/css</code> (with the box-edge,
          <code>font</code>, <code>background</code>, <code>list-style</code>,
          and <code>text-decoration</code> shorthand expanders), generates a
          box tree, lays it out, and paints the result via the existing
          <code>phpdftk/pdf-writer</code> content stream.
        </p>
        <p>
          The painter handles backgrounds, four-side borders, shaped text
          with per-glyph kerning, three kinds of text decoration, three
          kinds of list marker, and basic multi-page pagination.
        </p>
      </section>

      <section>
        <h2>Bullet list (default disc)</h2>
        <ul>
          <li>HTML parsed by the WHATWG-conformant tokenizer + tree builder.</li>
          <li>CSS cascade with origins, !important, specificity, inheritance.</li>
          <li>Text shaping via OpenType cmap / GSUB / kern.</li>
          <li>Block + inline layout with greedy line breaking.</li>
        </ul>
      </section>

      <section>
        <h2>Square markers</h2>
        <ul class="square">
          <li>Sibling vertical margin collapsing per CSS 2.1 §8.3.1.</li>
          <li>Parent-child margin collapsing closes the §8.3.1 loop.</li>
          <li>Per-glyph kerning via TJ arrays when shaper advances diverge.</li>
        </ul>
      </section>

      <section>
        <h2>Outlined circle markers</h2>
        <ul class="circle">
          <li>Painter approximates circles with four cubic Bézier curves.</li>
          <li>Marker colour follows the cascaded <code>color</code>.</li>
        </ul>
      </section>

      <section>
        <h2>Text alignment</h2>
        <p class="centered">Centered headline text sits at the page midpoint.</p>
        <p class="right">Right-aligned text ends at the content edge.</p>
      </section>

      <section class="callout">
        <h3>Callout</h3>
        <p>
          The background, border, padding, and inheritance of font / color
          all cooperate here without manual styling per element. This block
          also demonstrates <code>box-shadow</code> (hard-edged) as a
          subtle right/down offset behind the callout.
        </p>
        <p class="faded">
          And this paragraph is at 50% opacity — emitted via an ExtGState
          on the page, applied via <code>q /Gs gs … Q</code> in the
          content stream so the whole paragraph (including text and
          background) fades together.
        </p>
      </section>
    </article>
  </body>
</html>
HTML;

$css = <<<'CSS'
body { font: 11pt sans-serif; color: #222; margin: 48pt; }
article { background-color: #fff; }
header { margin-bottom: 18pt; }
h1 { font-size: 22pt; color: #1a5490; margin-bottom: 6pt; }
h2 { font-size: 15pt; color: #1a5490; border-bottom: 1px solid #1a5490; margin-top: 18pt; padding-bottom: 2pt; }
h3 { font-size: 12pt; color: #1a5490; }
p { margin: 0 0 8pt 0; }
p.byline { color: #666; font-style: italic; text-decoration: underline; }
code { background-color: #eef; }
ul { margin: 0 0 10pt 0; padding-left: 24pt; }
ul.square li { list-style-type: square; }
ul.circle li { list-style-type: circle; }
p.centered { text-align: center; }
p.right { text-align: right; }
.callout {
    background-color: #fffbe5;
    border: 1px solid #e0c46b;
    padding: 12pt;
    margin: 18pt 0;
    box-shadow: 3pt 3pt #cccccc;
}
.faded { opacity: 0.5; }
CSS;

$options = (new RendererOptions())->withPageSize(612.0, 792.0);

// Wire a real font so body text renders. Without one, only backgrounds /
// borders / markers paint.
$fontPath = __DIR__ . '/../../tests/fixtures/fonts/NotoSans-Regular.otf';
if (is_file($fontPath)) {
    $options = $options->withDefaultFont((new OpenTypeParser($fontPath))->parse());
}

$result = (new Renderer($options))->render($html, $css);
// endregion: example

$outputPath = example_output_path('html-to-pdf/article.pdf');
$result->writer->save($outputPath);

printf("Wrote %s (%d bytes)\n", $outputPath, filesize($outputPath));
if ($result->warnings !== []) {
    printf("Renderer emitted %d warning(s):\n", count($result->warnings));
    foreach ($result->warnings as $w) {
        printf("  [%s/%s] %s\n", $w->severity->value, $w->code->value, $w->message);
    }
}
