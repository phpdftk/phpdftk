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

Phase 3 of the [HTML & SVG rendering roadmap](https://github.com/phpdftk/phpdftk/blob/main/docs/plans/html-and-svg.md). Landed: basic shapes (3K), `<path>` with arc-to-cubic (3L), `transform` + `<g>` + viewBox origin shift (3M), stroke params + element opacity via ExtGState (3N), linear / radial gradients with `userSpaceOnUse` + `objectBoundingBox` (3O), `<text>` with the 14 standard PDF fonts (3P), `<defs>` / `<symbol>` + `<use>` expansion + `<image>` embedding for filesystem hrefs (3Q), the top-level `SvgRenderer` adapter with y-axis flip + `preserveAspectRatio` (`none` + default `xMidYMid meet`) (3R), `<clipPath>` via the `clip-path` attribute with both `userSpaceOnUse` and `objectBoundingBox` modes plus `clip-rule="evenodd"` (3R+3), `gradientTransform` baked into the PDF pattern `/Matrix` plus `radialGradient` focal-point (`fx`/`fy`/`fr`) (3R+4), and SVG 2 §11.6 per-glyph `x` / `y` / `rotate` positioning (3R+5).

Coordinate convention: `(x, y)` passed to `SvgRenderer::draw()` is the **bottom-left** of the destination rectangle in PDF user space. The renderer applies the standard SVG y-down → PDF y-up flip at the `cm` level and tells the `Translator` to compensate the flip inside text objects (via `Tm 1 0 0 -1 x y`) so glyphs render upright. Direct `Translator::paint()` usage without `SvgRenderer` keeps the pre-fix behaviour — no outer flip, `Td` for text — so existing callers don't regress.

Deferred from this phase:

- Non-default `preserveAspectRatio` alignment keywords (`xMinYMin`, `xMaxYMax`, …) and `slice` meet-mode — both collapse to `xMidYMid meet` for now.
- `Pdf::addSvg` (cursor-aware top-level placement) and `PdfDoc::createSvgTemplate(SvgDocument): FormXObject` (reusable Form XObject) — the two remaining adapter surfaces from the plan.
- Gradient `spreadMethod: reflect`/`repeat` (would need to synthesise extended stops over a wider function domain); PDF `/Extend [true true]` for the SVG default `pad` (gradients cut off cleanly at endpoints today rather than extending the edge colours).
- Per-glyph `dx` / `dy` offsets (would need font-metric-aware auto-advance to combine with sticky `x` / `y`); per-`<tspan>` font and positioning overrides (`<tspan>` content is still concatenated into the parent run); OpenType shaping via `phpdftk/text`; `@font-face` embedding.
- `<mask>` painter (transparency-group XObject + soft-mask ExtGState); nested `clip-path` on `<clipPath>` children; per-child `clip-rule` overrides; `transform` on `<clipPath>` itself.
- `data:` and `http(s)://` `<image>` hrefs (gated on the html-to-pdf 1L resource loader).
- Intrinsic `<image>` dimensions when `width`/`height` are omitted.
- Examples under `examples/svg-to-pdf/`, `benchmarks/SvgToPdfBench.php`, and a W3C SVG 2 conformance subset under `tests/conformance/`.

## License

MIT
