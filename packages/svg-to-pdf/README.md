# phpdftk/svg-to-pdf

Render SVG to PDF. Geometric painter that consumes a parsed `Phpdftk\Svg\SvgDocument` and places it onto a `PdfWriter` page.

```php
use Phpdftk\Pdf\Writer\PdfWriter;
use Phpdftk\Svg\Parser as SvgParser;
use Phpdftk\SvgToPdf\SvgRenderer;

$svg    = (new SvgParser())->parse($svgString);
$writer = new PdfWriter();
$page   = $writer->addPage(612.0, 792.0);

$renderer = new SvgRenderer($page, $writer);
$renderer->draw($svg, x: 72, y: 600, width: 200, height: 200);
$renderer->draw($svg, x: 320, y: 600);            // intrinsic size

$writer->save('out.pdf');
```

The lower-level `Translator::paint()` is still available for callers that already own a `ContentStream` and want full control:

```php
$stream = $writer->addContentStream($page);
(new Translator())->paint($svg, $stream, $page, $writer);
```

## Installation

```bash
composer require phpdftk/svg-to-pdf
```

## Status

Phase 3 of the [HTML & SVG rendering roadmap](https://github.com/phpdftk/phpdftk/blob/main/docs/plans/html-and-svg.md). Landed: basic shapes (3K), `<path>` with arc-to-cubic (3L), `transform` + `<g>` + viewBox origin shift (3M), stroke params + element opacity via ExtGState (3N), linear / radial gradients with `userSpaceOnUse` + `objectBoundingBox` (3O), `<text>` with the 14 standard PDF fonts (3P), `<defs>` / `<symbol>` + `<use>` expansion + `<image>` embedding for filesystem hrefs (3Q), and the top-level `SvgRenderer` adapter (3R).

Deferred from this phase:

- SVG y-down → PDF y-up axis flip + compensating text-matrix adjustment. SVG content authored with the conventional y-down assumption appears vertically mirrored on the page today.
- `preserveAspectRatio` parsing for proper aspect-fit / aspect-fill behaviour.
- `Pdf::addSvg` (cursor-aware top-level placement) and `PdfDoc::createSvgTemplate(SvgDocument): FormXObject` (reusable Form XObject) — the two remaining adapter surfaces from the plan.
- Gradient `spreadMethod: reflect`/`repeat`, `gradientTransform`, `radialGradient` focal-point (`fx`/`fy`/`fr`).
- Per-glyph text positioning lists (SVG 2 §11.6) and per-tspan font / positioning overrides; OpenType shaping via `phpdftk/text`; `@font-face` embedding.
- `<clipPath>` / `<mask>` painters (transparency-group XObjects + soft-mask ExtGState).
- `data:` and `http(s)://` `<image>` hrefs (gated on the html-to-pdf 1L resource loader).
- Intrinsic `<image>` dimensions when `width`/`height` are omitted.
- Examples under `examples/svg-to-pdf/`, `benchmarks/SvgToPdfBench.php`, and a W3C SVG 2 conformance subset under `tests/conformance/`.

## License

MIT
