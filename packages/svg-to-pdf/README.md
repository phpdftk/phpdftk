# phpdftk/svg-to-pdf

Render SVG to PDF. Geometric painter that consumes a parsed `Phpdftk\Svg\SvgDocument` and emits PDF content-stream operators into a stream you supply.

```php
use Phpdftk\Pdf\Writer\PdfWriter;
use Phpdftk\Svg\Parser as SvgParser;
use Phpdftk\SvgToPdf\Translator;

$svg    = (new SvgParser())->parse($svgString);
$writer = new PdfWriter();
$page   = $writer->addPage(612.0, 792.0);
$stream = $writer->addContentStream($page);

(new Translator())->paint($svg, $stream);
$writer->save('out.pdf');
```

The translator does **not** create its own writer or pick a page size — those are the caller's choices. That keeps `Translator` composable with any code that already owns a `ContentStream`, including the adapter API (`Pdf::addSvg`, `Writer\Page::drawSvg`, `PdfDoc::createSvgTemplate`) that lands in 3R.

## Installation

```bash
composer require phpdftk/svg-to-pdf
```

## Status

Phase 3 of the [HTML & SVG rendering roadmap](https://github.com/phpdftk/phpdftk/blob/main/docs/plans/html-and-svg.md). Landed: basic-shape painters (3K), `<path>` with arc-to-cubic conversion (3L), the `transform` attribute + `<g>` + viewBox origin shift (3M), stroke params + element opacity via ExtGState (3N), linear / radial gradients with `userSpaceOnUse` + `objectBoundingBox` (3O), and `<text>` with the 14 standard PDF fonts via family / weight / style resolution (3P). 3O / 3P deferred: gradient `spreadMethod: reflect`/`repeat`, `gradientTransform`, radial focal-point (fx/fy/fr); per-glyph text positioning, per-tspan font overrides, OpenType shaping, `@font-face` embedding. Next: 3Q use/clip/mask/image, 3R adapter API + samples + benchmarks.

## License

MIT
