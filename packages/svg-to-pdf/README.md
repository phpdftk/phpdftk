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

Phase 3 of the [HTML & SVG rendering roadmap](https://github.com/phpdftk/phpdftk/blob/main/docs/plans/html-and-svg.md). Landed: basic-shape painters (3K), `<path>` with the full SVG 2 path grammar including arc-to-cubic conversion (3L), and the `transform` attribute on any element + `<g>` containers + minimal viewBox origin shift (3M). Next: 3N opacity / dash, 3O gradients, 3P text, 3Q use/clip/mask/image, 3R adapter API + samples + benchmarks.

## License

MIT
