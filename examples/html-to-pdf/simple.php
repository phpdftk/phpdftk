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
    <h1>phpdftk html-to-pdf</h1>
    <p class="lead">A pure-PHP HTML &amp; CSS renderer for paged documents.</p>
    <section>
      <h2>What just happened</h2>
      <p>
        The renderer parsed this HTML through <code>phpdftk/html</code>, ran
        the cascade through <code>phpdftk/css</code> (including the box-edge
        and <code>font</code> shorthand expanders), generated a box tree,
        laid the blocks out top-down with shaper-driven inline wrapping, and
        painted backgrounds, borders, and text via the
        <code>phpdftk/pdf-writer</code> content stream.
      </p>
    </section>
    <section>
      <h2>Multi-page output</h2>
      <p>
        Content that overflows the page boundary gets sliced across as many
        pages as needed. Each page paints the whole tree clipped to its own
        MediaBox.
      </p>
      <div class="filler"></div>
    </section>
  </body>
</html>
HTML;

$css = <<<'CSS'
/* Heading sizes + margins + monospace `code` now come from the renderer's
   built-in UA stylesheet — only override colour and the h2 section divider. */
body { font: 12pt sans-serif; color: #222; margin: 36pt; }
h1, h2 { color: #1a5490; }
h2 { border-bottom: 1px solid #1a5490; padding-bottom: 2pt; }
p.lead { font-size: 14pt; color: #444; text-decoration: underline; }
code { background-color: #eef; }
section { background-color: #fafafa; border: 1px solid #ddd; padding: 12pt; margin-bottom: 12pt; }
.filler { background-color: #1a5490; height: 600pt; margin-top: 12pt; box-shadow: 4pt 4pt #00000044; }
CSS;

$options = (new RendererOptions())->withPageSize(612.0, 792.0);

// Wire a real font in if the shared fixture is present so the body text
// actually shows up. Without a font, the renderer still produces a valid
// PDF — backgrounds + borders render normally, text fragments are
// no-ops (mark the file with a clear visual hint via the .filler block).
$fontPath = __DIR__ . '/../../tests/fixtures/fonts/NotoSans-Regular.otf';
if (is_file($fontPath)) {
    $options = $options->withDefaultFont((new OpenTypeParser($fontPath))->parse());
}

$result = (new Renderer($options))->render($html, $css);
// endregion: example

$outputPath = example_output_path('html-to-pdf/simple.pdf');
$result->writer->save($outputPath);

printf("Wrote %s (%d bytes)\n", $outputPath, filesize($outputPath));
if ($result->warnings !== []) {
    printf("Renderer emitted %d warning(s):\n", count($result->warnings));
    foreach ($result->warnings as $w) {
        printf("  [%s/%s] %s\n", $w->severity->value, $w->code->value, $w->message);
    }
}
