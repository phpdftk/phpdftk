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

Phase 3 of the [HTML & SVG rendering roadmap](https://github.com/phpdftk/phpdftk/blob/main/docs/plans/html-and-svg.md). Sub-phase 3K landed: basic-shape painters for `<rect>`, `<circle>`, `<ellipse>`, `<line>`, `<polyline>`, `<polygon>` with fill / stroke / fill-rule resolution and SVG 2 default paint (black fill, no stroke). Unrecognised containers are walked transparently. Next: 3L `<path>` painter (arc → cubic conversion), 3M `<g>` + transforms, 3N opacity / dash, 3O gradients, 3P text, 3Q use/clip/mask/image, 3R adapter API + samples + benchmarks.

## License

MIT
