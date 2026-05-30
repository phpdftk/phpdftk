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

For top-level flow placement in a `Pdf` document — cursor advances below the SVG and the next addition lands underneath, with auto-pagination on overflow:

```php
use Phpdftk\Pdf\Writer\Pdf;

$pdf = new Pdf();
$pdf->addHeading('Quarterly report');
SvgRenderer::addToPdf($pdf, $svg, width: 200, height: 100);
$pdf->addText('See the chart above.');
$pdf->save('report.pdf');
```

To stamp the same SVG onto many pages (watermarks, repeating logos) without re-emitting operators, bake it into a reusable Form XObject:

```php
use Phpdftk\Pdf\Writer\PdfDoc;

$doc = new PdfDoc();
$cover = $doc->addPage();
$tpl = SvgRenderer::createTemplate($doc, $cover, $logo, width: 120, height: 40);
$cover->drawTemplate($tpl, x: 72, y: 720);
$doc->addPage()->drawTemplate($tpl, x: 72, y: 720);
$doc->addPage()->drawTemplate($tpl, x: 72, y: 720);
$doc->writer()->save('report.pdf');
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

Phase 3 of the [HTML & SVG rendering roadmap](https://github.com/phpdftk/phpdftk/blob/main/docs/plans/html-and-svg.md). Landed: basic shapes (3K), `<path>` with arc-to-cubic (3L), `transform` + `<g>` + viewBox origin shift (3M), stroke params + element opacity via ExtGState (3N), linear / radial gradients with `userSpaceOnUse` + `objectBoundingBox` (3O), `<text>` with the 14 standard PDF fonts (3P), `<defs>` / `<symbol>` + `<use>` expansion + `<image>` embedding for filesystem hrefs (3Q), the top-level `SvgRenderer` adapter (3R), `<clipPath>` via the `clip-path` attribute (3R+3), `gradientTransform` + `radialGradient` focal-point (3R+4), SVG 2 §11.6 per-glyph `x` / `y` / `rotate` text positioning (3R+5), the full SVG 2 §7.10 `preserveAspectRatio` matrix (3R+6), intrinsic `<image>` dimensions (3R+7), `<mask>` via soft-mask `ExtGState` (3R+8), `<path>` bounding box (3R+9), SVG 2 §14.5.4 mask region + `mask-type` (3R+10), `transform` on `<clipPath>` (3R+11), a `SvgToPdfBench` PHPBench suite (3R+12), a 12-fixture conformance smoke suite (3R+13), `SvgRenderer::addToPdf(Pdf, SvgDocument, ?w, ?h, align)` for flow-style top-level placement via the new generic `Pdf::addBlock` hook in `phpdftk/pdf-writer` (3R+14), `SvgRenderer::createTemplate(PdfDoc, Page, SvgDocument, ?w, ?h)` returning a reusable `FormXObject` for `Page::drawTemplate` (3R+15), SVG `spreadMethod: pad` correctly extending gradient endpoint colours via PDF `/Extend [true true]` (3R+16), per-glyph `dx` / `dy` text offsets as additive accumulating deltas on the sticky x/y (3R+17), and `data:` URI `<image>` hrefs (RFC 2397 — both `;base64,` and raw URL-encoded payloads) decoded and embedded via a temp file (3R+18).

Coordinate convention: `(x, y)` passed to `SvgRenderer::draw()` is the **bottom-left** of the destination rectangle in PDF user space. The renderer applies the standard SVG y-down → PDF y-up flip at the `cm` level and tells the `Translator` to compensate the flip inside text objects (via `Tm 1 0 0 -1 x y`) so glyphs render upright. Direct `Translator::paint()` usage without `SvgRenderer` keeps the pre-fix behaviour — no outer flip, `Td` for text — so existing callers don't regress.

Deferred from this phase:

- Gradient `spreadMethod: reflect`/`repeat` (would need to synthesise extended stops over a wider function domain — PDF's `/Extend` only does pad). `pad` (the SVG default) now correctly emits `/Extend [true true]` so endpoint colours fill beyond the gradient endpoints (3R+16).
- Per-`<tspan>` font and positioning overrides (`<tspan>` content is still concatenated into the parent run); OpenType shaping via `phpdftk/text`; `@font-face` embedding. Per-glyph `dx` / `dy` now ship as additive accumulating deltas on the sticky x/y (3R+17) — proper font-metric-aware "previous glyph's effective position" semantics still needs `phpdftk/text` shaping.
- Nothing major remaining on `<mask>` — region attributes, default extension, and `mask-type` all landed at 3R+10.
- Nested `clip-path` on `<clipPath>` children; per-child `clip-rule` overrides (PDF clip operators apply per-path with a single fill rule so varying rules per child would need a transparency-group-based clip).
- `http(s)://` `<image>` hrefs (gated on the html-to-pdf 1L resource loader). `data:` URIs ship at 3R+18 for both `;base64,` and raw URL-encoded payloads — the decoded bytes are materialised to a temp file so the existing `ImageParser` / `PdfWriter::addImage` flow can register them as PDF XObjects.
- Growing the conformance fixture set toward the full W3C SVG 2 testsuite — current 12 fixtures are hand-curated. Pulling in upstream fixture files (and the rasterised reference images they'd need for proper visual diff) is a follow-up.

## License

MIT
