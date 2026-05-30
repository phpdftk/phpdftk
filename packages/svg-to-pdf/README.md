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

Phase 3 of the [HTML & SVG rendering roadmap](https://github.com/phpdftk/phpdftk/blob/main/docs/plans/html-and-svg.md). Landed: basic shapes (3K), `<path>` with arc-to-cubic (3L), `transform` + `<g>` + viewBox origin shift (3M), stroke params + element opacity via ExtGState (3N), linear / radial gradients with `userSpaceOnUse` + `objectBoundingBox` (3O), `<text>` with the 14 standard PDF fonts (3P), `<defs>` / `<symbol>` + `<use>` expansion + `<image>` embedding for filesystem hrefs (3Q), the top-level `SvgRenderer` adapter (3R), `<clipPath>` via the `clip-path` attribute (3R+3), `gradientTransform` + `radialGradient` focal-point (3R+4), SVG 2 §11.6 per-glyph `x` / `y` / `rotate` text positioning (3R+5), the full SVG 2 §7.10 `preserveAspectRatio` matrix (3R+6), intrinsic `<image>` dimensions (3R+7), `<mask>` via soft-mask `ExtGState` (3R+8), and `<path>` bounding-box computation (cubic + quadratic Bézier interior extrema) unlocking `objectBoundingBox`-mode gradients / clip-paths / masks on path elements (3R+9).

Coordinate convention: `(x, y)` passed to `SvgRenderer::draw()` is the **bottom-left** of the destination rectangle in PDF user space. The renderer applies the standard SVG y-down → PDF y-up flip at the `cm` level and tells the `Translator` to compensate the flip inside text objects (via `Tm 1 0 0 -1 x y`) so glyphs render upright. Direct `Translator::paint()` usage without `SvgRenderer` keeps the pre-fix behaviour — no outer flip, `Td` for text — so existing callers don't regress.

Deferred from this phase:

- `Pdf::addSvg` (cursor-aware top-level placement) and `PdfDoc::createSvgTemplate(SvgDocument): FormXObject` (reusable Form XObject) — the two remaining adapter surfaces from the plan.
- Gradient `spreadMethod: reflect`/`repeat` (would need to synthesise extended stops over a wider function domain); PDF `/Extend [true true]` for the SVG default `pad` (gradients cut off cleanly at endpoints today rather than extending the edge colours).
- Per-glyph `dx` / `dy` offsets (would need font-metric-aware auto-advance to combine with sticky `x` / `y`); per-`<tspan>` font and positioning overrides (`<tspan>` content is still concatenated into the parent run); OpenType shaping via `phpdftk/text`; `@font-face` embedding.
- Explicit `mask` `x` / `y` / `width` / `height` attributes (currently uses masked element's bbox); SVG 2 §14.5.4 default extension (`-10%` / `120%`) of the mask region; `mask-type="alpha"` (only luminance supported).
- Nested `clip-path` on `<clipPath>` children; per-child `clip-rule` overrides; `transform` on `<clipPath>` itself.
- `data:` and `http(s)://` `<image>` hrefs (gated on the html-to-pdf 1L resource loader).
- Examples under `examples/svg-to-pdf/`, `benchmarks/SvgToPdfBench.php`, and a W3C SVG 2 conformance subset under `tests/conformance/`.

## License

MIT
