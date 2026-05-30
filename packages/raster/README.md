# phpdftk/raster

Pure-PHP software raster compositor for phpdftk's HTML / CSS / SVG rendering pipeline.

When PDF's native primitives can't express what a CSS or SVG spec demands — Gaussian blur on a `<filter>`, `mix-blend-mode: hue` outside the PDF-native blend set, `backdrop-filter`, `mask-composite: subtract`, 3D transforms with perspective + intersection — the translator raises the geometry into this package, renders it to an RGBA buffer, then embeds the buffer as a PDF Image XObject. Everything else stays in vector PDF primitives.

## Status

**Phase 4C scaffold.** The package shape lands here — `RasterSurface` as the pixel buffer, `RgbaPixel` as the per-pixel value object, `BlendMode` enum covering the full CSS Compositing 1 mode set. The painter, filter chain, blend-mode compositor, and PNG / PDF-XObject exporter are stubs and land in `4C.1`–`4C.6`.

```
4C.1  Painter                primitive tree → surface (path fills, strokes, gradients, images)
4C.2  BlendModeCompositor    source-over composite + the 16 CSS blend modes (Normal..Luminosity)
4C.3  Filter primitives      feGaussianBlur, feColorMatrix, feConvolveMatrix,
                             feMorphology, feFlood, feImage, feTurbulence,
                             feDisplacementMap, feSpecularLighting, feMerge,
                             feTile, feOffset, feDropShadow, feComponentTransfer
4C.4  Mask compositor        alpha + luminance masks, mask-composite
4C.5  PNG encoder            surface → PNG bytes
4C.6  PDF XObject factory    surface → registered Image XObject + content-stream Do op
```

## Who uses it

- **`phpdftk/svg-to-pdf`** — `<filter>` primitives, `<foreignObject>` (renders HTML inside SVG, which requires `phpdftk/html-to-pdf` to raster a region), per-child `clip-rule`, `spreadMethod: reflect / repeat` via raster fallback.
- **`phpdftk/html-to-pdf`** — CSS `filter:`, `backdrop-filter:`, `mask:`, `mix-blend-mode:` outside PDF-native set, CSS Transforms 3 (3D), CSS Backgrounds 4 conic gradients.
- **`phpdftk/wpt-harness`** — eventually swaps in for the v1 Ghostscript-based rasteriser (`packages/wpt-harness/src/Rasteriser.php`) so the harness has zero external binary dependencies.

## Usage (target API)

```php
use Phpdftk\Raster\RasterSurface;
use Phpdftk\Raster\BlendMode;
use Phpdftk\Raster\Filter\GaussianBlur;
use Phpdftk\Raster\Encoder\PngEncoder;

$surface = new RasterSurface(width: 400, height: 300);
$painter = new Painter($surface);
$painter->fillPath($pathOps, $rgbaFill);
$painter->compositeImage($srcSurface, x: 10, y: 10, blendMode: BlendMode::Multiply);

$blurred = (new GaussianBlur(stdDev: 4.0))->apply($surface);
$pngBytes = (new PngEncoder())->encode($blurred);
```

## Performance

Pure PHP — no GD / Imagick / FFI. The default pixel storage is a flat byte string with 4 bytes per pixel (RGBA), row-major. Hot-loop filter primitives can pull the buffer out and operate on it directly via `RasterSurface::buffer()` to avoid per-pixel call overhead. A 1024×1024 surface uses ~4 MB.

For documents with many filter surfaces, the painter deduplicates identical surfaces across pages (4C.6) so a repeating drop-shadow is encoded as a PDF Image XObject once and referenced N times.

## Installation

```bash
composer require phpdftk/raster
```

## License

MIT
