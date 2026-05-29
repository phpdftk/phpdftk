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

Phase 3 of the [HTML & SVG rendering roadmap](https://github.com/phpdftk/phpdftk/blob/main/docs/plans/html-and-svg.md). Landed: basic shapes (3K), `<path>` with arc-to-cubic (3L), `transform` + `<g>` + viewBox origin shift (3M), stroke params + element opacity via ExtGState (3N), linear / radial gradients with `userSpaceOnUse` + `objectBoundingBox` (3O), `<text>` with the 14 standard PDF fonts (3P), and `<defs>` / `<symbol>` skip + `<use>` expansion with translation + `<image>` embedding for filesystem hrefs (3Q). Deferred: gradient `spreadMethod: reflect`/`repeat`, `gradientTransform`, radial focal-point; per-glyph text positioning, per-tspan overrides, OpenType shaping; `<clipPath>` / `<mask>`; `data:` / `http(s)` image hrefs; intrinsic image dimensions. Next: 3R adapter API (`Pdf::addSvg`, `Page::drawSvg`, `PdfDoc::createSvgTemplate`) + samples + benchmarks.

## License

MIT
