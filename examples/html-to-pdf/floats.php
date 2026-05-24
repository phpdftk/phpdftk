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
    <h1>Image-Text Wrap with CSS Floats</h1>
    <p class="lead">
      CSS 2.1 §9.5 floats let block boxes drop out of normal flow and
      sit at the left or right edge of their containing block. Following
      inline content wraps around them — the staple of magazine, blog,
      and brochure layouts before flexbox showed up.
    </p>

    <section>
      <div class="figure left">
        <span class="caption">Figure 1</span>
        <span class="dim">left-floated 140&times;100</span>
      </div>
      <p>
        Floats remove themselves from normal flow but still occupy
        horizontal space, so subsequent lines wrap around them instead
        of overlapping. A right-side equivalent (<code>float: right</code>)
        pushes content the other way; pair both sides for a true magazine
        column with imagery on either margin. The <code>clear</code>
        property forces a block to drop past active floats on the
        specified side — handy when a section break needs to start
        with a full-width caption that ignores prior floats.
      </p>
      <p>
        phpdftk's Phase-1 implementation honours the most common
        subset: <code>float: left</code> / <code>float: right</code>
        with <code>clear: left</code> / <code>right</code> /
        <code>both</code>. Multiple floats stack horizontally until
        they run out of horizontal room, then drop to a new row. Lines
        in a paragraph that overlaps a float's vertical extent
        automatically start past the float and resume at the container
        edge below it.
      </p>
    </section>

    <section class="cleared">
      <div class="figure right">
        <span class="caption">Figure 2</span>
        <span class="dim">right-floated 140&times;100</span>
      </div>
      <p>
        With a right float in this section, the body text wraps on the
        left side. Each line knows its own available width based on
        whichever floats overlap its vertical range, so a tall float
        affects only the lines it actually overlaps, not the ones below.
      </p>
      <p>
        Clearing a block past floats is what lets headings start
        flush-left or full-width again — try removing the
        <code>clear: both</code> below and watch the next heading slip
        next to the right float instead of starting cleanly below it.
      </p>
    </section>

    <h2 class="closer">A clean break.</h2>
    <p>
      The heading above declares <code>clear: both</code>, so it drops
      past every active float before laying out. Without it, the heading
      would have started flowing alongside the right-floated caption box
      and the page rhythm would feel cramped.
    </p>
  </body>
</html>
HTML;

$css = <<<'CSS'
body { font: 11pt sans-serif; color: #222; margin: 48pt; }
h1 { color: #1a5490; font-size: 22pt; margin-bottom: 6pt; }
h2 { color: #1a5490; font-size: 16pt; margin-top: 20pt; }
h2.closer { clear: both; }
p.lead { font-size: 13pt; color: #444; margin-bottom: 18pt; }
section { margin-bottom: 18pt; }
section p { margin: 0 0 10pt 0; }
section.cleared { clear: both; }

.figure {
  width: 140pt;
  height: 100pt;
  border: 1pt solid #888;
  background-color: #eef;
  padding: 12pt;
  text-align: center;
  margin: 0 12pt 8pt 0;
}
.figure.left { float: left; }
.figure.right { float: right; margin: 0 0 8pt 12pt; }
.figure .caption { color: #1a5490; font-weight: bold; }
.figure .dim { color: #888; font-size: 9pt; }

code { background-color: #eef; padding: 0 2pt; }
CSS;

$options = (new RendererOptions())->withPageSize(612.0, 792.0);

$fontPath = __DIR__ . '/../../tests/fixtures/fonts/NotoSans-Regular.otf';
if (is_file($fontPath)) {
    $options = $options->withDefaultFont((new OpenTypeParser($fontPath))->parse());
}

$result = (new Renderer($options))->render($html, $css);
// endregion: example

$outputPath = example_output_path('html-to-pdf/floats.pdf');
$result->writer->save($outputPath);

printf("Wrote %s (%d bytes)\n", $outputPath, filesize($outputPath));
if ($result->warnings !== []) {
    printf("Renderer emitted %d warning(s):\n", count($result->warnings));
    foreach ($result->warnings as $w) {
        printf("  [%s/%s] %s\n", $w->severity->value, $w->code->value, $w->message);
    }
}
